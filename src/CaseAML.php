<?php
defined('BOFA_APP') || die('Accès direct interdit.');

/**
 * Gestion des dossiers AML/EDD.
 * Création, suivi du cycle de vie, règles métier, sanctions, transferts, KPIs.
 */
class CaseAML
{
    // -------------------------------------------------------------------------
    // Création et récupération
    // -------------------------------------------------------------------------

    /**
     * Crée un nouveau dossier AML/EDD.
     * Génère l'identifiant unique, calcule le score de risque, applique les règles
     * métier, vérifie les sanctions, crée un sous-compte et enregistre l'audit.
     *
     * @throws InvalidArgumentException si les champs obligatoires sont absents
     */
    public function create(array $data): int
    {
        if (empty($data['titre']) || empty($data['user_id'])) {
            throw new InvalidArgumentException('Champs obligatoires manquants : titre, user_id.');
        }

        $db = bofa_db();

        $caseId   = bofa_generate_case_id();
        $userId   = (int) $data['user_id'];
        $agentId  = !empty($data['agent_id'])   ? (int) $data['agent_id']  : null;
        $accountId= !empty($data['account_id']) ? (int) $data['account_id']: null;
        $titre    = bofa_sanitize($data['titre']);
        $desc     = bofa_sanitize($data['description'] ?? '');
        $typeCas  = in_array($data['type_cas'] ?? '', ['aml','edd','kyc','sanctions','pep','fraude','autre'], true)
                    ? $data['type_cas'] : 'aml';
        $statut   = 'ouvert';
        $priorite = in_array($data['priorite'] ?? '', ['faible','normale','haute','critique'], true)
                    ? $data['priorite'] : 'normale';
        $montant  = isset($data['montant'])    ? (float)  $data['montant']   : 0.0;
        $devise   = bofa_sanitize($data['devise'] ?? 'EUR');
        $pays     = strtoupper(bofa_sanitize($data['pays_origine'] ?? ''));
        $typeActif= bofa_sanitize($data['type_actif'] ?? 'cash');
        $echeance = bofa_sanitize($data['date_echeance'] ?? '');

        // Calcul du score de risque via le helper centralisé
        $score = bofa_calculer_score($montant, $pays, $typeActif);

        // Construction du tableau de données pour les règles métier
        $caseData = [
            'titre'       => $titre,
            'user_id'     => $userId,
            'agent_id'    => $agentId,
            'account_id'  => $accountId,
            'type_cas'    => $typeCas,
            'statut'      => $statut,
            'priorite'    => $priorite,
            'score_risque'=> $score,
            'montant'     => $montant,
            'devise'      => $devise,
            'pays_origine'=> $pays,
            'type_actif'  => $typeActif,
        ];

        // Application des règles métier (peut modifier score/statut/priorité)
        $caseData = $this->applyBusinessRules($caseData);

        // Vérification des sanctions sur le client
        $user = $db->prepare("SELECT nom, prenom FROM users WHERE id = :id LIMIT 1");
        $user->execute([':id' => $userId]);
        $userRow = $user->fetch();
        if ($userRow && $this->checkSanctions($userRow['nom'] . ' ' . $userRow['prenom'])) {
            $caseData['priorite']    = 'critique';
            $caseData['score_risque'] = min(100.0, $caseData['score_risque'] + 30);
        }

        // Insertion du dossier en base
        $stmt = $db->prepare(
            "INSERT INTO cases
                (case_number, user_id, agent_id, account_id, titre, description,
                 type_cas, statut, priorite, score_risque, montant, devise,
                 pays_origine, type_actif, date_echeance, created_at)
             VALUES
                (:case_number, :user_id, :agent_id, :account_id, :titre, :description,
                 :type_cas, :statut, :priorite, :score_risque, :montant, :devise,
                 :pays_origine, :type_actif, :date_echeance, NOW())"
        );
        $stmt->execute([
            ':case_number' => $caseId,
            ':user_id'     => $caseData['user_id'],
            ':agent_id'    => $caseData['agent_id'],
            ':account_id'  => $caseData['account_id'],
            ':titre'       => $caseData['titre'],
            ':description' => $desc,
            ':type_cas'    => $caseData['type_cas'],
            ':statut'      => $caseData['statut'],
            ':priorite'    => $caseData['priorite'],
            ':score_risque'=> $caseData['score_risque'],
            ':montant'     => $caseData['montant'],
            ':devise'      => $caseData['devise'],
            ':pays_origine'=> $caseData['pays_origine'],
            ':type_actif'  => $caseData['type_actif'],
            ':date_echeance'=> $echeance ?: null,
        ]);

        $newId = (int) $db->lastInsertId();

        // Création d'un sous-compte associé si un compte est lié
        if ($accountId) {
            $db->prepare(
                "INSERT INTO sub_accounts (account_id, libelle, type_actif, montant, devise, created_at)
                 VALUES (:aid, :lib, :ta, :montant, :devise, NOW())"
            )->execute([
                ':aid'     => $accountId,
                ':lib'     => 'Dossier ' . $caseId,
                ':ta'      => $typeActif,
                ':montant' => $montant,
                ':devise'  => $devise,
            ]);
        }

        // Ajout de l'entrée initiale dans l'historique de statut
        $db->prepare(
            "INSERT INTO case_status_history (case_id, user_id, ancien_statut, nouveau_statut, commentaire, created_at)
             VALUES (:cid, :uid, NULL, 'ouvert', 'Création du dossier', NOW())"
        )->execute([':cid' => $newId, ':uid' => $userId]);

        bofa_audit($userId, 'CREATE', 'cases', $newId, null, ['case_number' => $caseId, 'score' => $score]);

        return $newId;
    }

    /**
     * Récupère un dossier par son identifiant numérique.
     */
    public function getById(int $id): array|null
    {
        $stmt = bofa_db()->prepare(
            "SELECT c.*,
                    CONCAT(u.nom, ' ', u.prenom) AS client_nom,
                    CONCAT(a.nom, ' ', a.prenom) AS agent_nom
             FROM cases c
             LEFT JOIN users u ON u.id = c.user_id
             LEFT JOIN users a ON a.id = c.agent_id
             WHERE c.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Récupère un dossier par son identifiant textuel (ex. AML-EDD-2025-00001).
     */
    public function getByCaseId(string $caseId): array|null
    {
        $caseId = bofa_sanitize($caseId);
        $stmt   = bofa_db()->prepare(
            "SELECT c.*,
                    CONCAT(u.nom, ' ', u.prenom) AS client_nom,
                    CONCAT(a.nom, ' ', a.prenom) AS agent_nom
             FROM cases c
             LEFT JOIN users u ON u.id = c.user_id
             LEFT JOIN users a ON a.id = c.agent_id
             WHERE c.case_number = :cn LIMIT 1"
        );
        $stmt->execute([':cn' => $caseId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Liste les dossiers avec filtres et pagination.
     * Filtres disponibles : statut, pays, agent_id, score_min, score_max,
     *                       date_debut, date_fin, search, tags.
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $db     = bofa_db();
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['statut'])) {
            $where[]           = 'c.statut = :statut';
            $params[':statut'] = bofa_sanitize($filters['statut']);
        }
        if (!empty($filters['pays'])) {
            $where[]         = 'c.pays_origine = :pays';
            $params[':pays'] = strtoupper(bofa_sanitize($filters['pays']));
        }
        if (!empty($filters['agent_id'])) {
            $where[]            = 'c.agent_id = :agent_id';
            $params[':agent_id']= (int) $filters['agent_id'];
        }
        if (isset($filters['score_min'])) {
            $where[]              = 'c.score_risque >= :score_min';
            $params[':score_min'] = (float) $filters['score_min'];
        }
        if (isset($filters['score_max'])) {
            $where[]              = 'c.score_risque <= :score_max';
            $params[':score_max'] = (float) $filters['score_max'];
        }
        if (!empty($filters['date_debut'])) {
            $where[]               = 'c.created_at >= :date_debut';
            $params[':date_debut'] = bofa_sanitize($filters['date_debut']) . ' 00:00:00';
        }
        if (!empty($filters['date_fin'])) {
            $where[]             = 'c.created_at <= :date_fin';
            $params[':date_fin'] = bofa_sanitize($filters['date_fin']) . ' 23:59:59';
        }
        if (!empty($filters['search'])) {
            $where[]           = '(c.titre LIKE :search OR c.case_number LIKE :search)';
            $params[':search'] = '%' . bofa_sanitize($filters['search']) . '%';
        }
        // Filtre par tag
        if (!empty($filters['tags'])) {
            $tagId             = (int) $filters['tags'];
            $where[]           = 'EXISTS (SELECT 1 FROM case_tags ct WHERE ct.case_id = c.id AND ct.tag_id = :tag_id)';
            $params[':tag_id'] = $tagId;
        }

        $whereStr  = implode(' AND ', $where);
        $countStmt = $db->prepare("SELECT COUNT(*) FROM cases c WHERE {$whereStr}");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetchColumn();
        $pagination = bofa_paginate($total, $perPage, $page);

        $stmt = $db->prepare(
            "SELECT c.*, CONCAT(u.nom, ' ', u.prenom) AS client_nom,
                    CONCAT(a.nom, ' ', a.prenom) AS agent_nom
             FROM cases c
             LEFT JOIN users u ON u.id = c.user_id
             LEFT JOIN users a ON a.id = c.agent_id
             WHERE {$whereStr}
             ORDER BY c.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit',  $pagination['perPage'],  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(), 'pagination' => $pagination];
    }

    /**
     * Liste les dossiers d'un client avec pagination.
     */
    public function getByClient(int $userId, int $page = 1, int $perPage = 10): array
    {
        return $this->getAll(['user_id_filter' => $userId], $page, $perPage);
    }

    /**
     * Liste les dossiers assignés à un agent avec filtres et pagination.
     */
    public function getByAgent(int $agentId, array $filters = [], int $page = 1): array
    {
        $filters['agent_id'] = $agentId;
        return $this->getAll($filters, $page);
    }

    // -------------------------------------------------------------------------
    // Transitions de statut
    // -------------------------------------------------------------------------

    /**
     * Met à jour le statut d'un dossier, enregistre l'historique et notifie le client.
     */
    public function updateStatus(int $id, string $newStatus, int $userId, string $comment = ''): bool
    {
        $allowed = ['ouvert', 'en_cours', 'en_attente', 'cloture', 'rejete', 'approuve'];
        if (!in_array($newStatus, $allowed, true)) {
            return false;
        }

        $db      = bofa_db();
        $current = $this->getById($id);
        if (!$current) return false;

        $comment = bofa_sanitize($comment);

        // Mise à jour du statut principal
        $closedAt = in_array($newStatus, ['cloture', 'rejete', 'approuve']) ? ', closed_at = NOW()' : '';
        $db->prepare("UPDATE cases SET statut = :s {$closedAt} WHERE id = :id")
           ->execute([':s' => $newStatus, ':id' => $id]);

        // Enregistrement dans l'historique
        $db->prepare(
            "INSERT INTO case_status_history (case_id, user_id, ancien_statut, nouveau_statut, commentaire, created_at)
             VALUES (:cid, :uid, :old, :new, :comment, NOW())"
        )->execute([
            ':cid'     => $id,
            ':uid'     => $userId,
            ':old'     => $current['statut'],
            ':new'     => $newStatus,
            ':comment' => $comment,
        ]);

        // Notification du client
        $this->sendStatusChange($id, $newStatus, $current['user_id']);

        bofa_audit($userId, 'STATUS_CHANGE', 'cases', $id,
            ['statut' => $current['statut']],
            ['statut' => $newStatus, 'commentaire' => $comment]
        );

        return true;
    }

    /**
     * Gèle un dossier et le compte bancaire associé.
     */
    public function freeze(int $id, int $agentId): bool
    {
        $db   = bofa_db();
        $case = $this->getById($id);
        if (!$case) return false;

        // Geler le compte bancaire lié si présent
        if ($case['account_id']) {
            $db->prepare("UPDATE accounts SET statut = 'gele' WHERE id = :aid")
               ->execute([':aid' => $case['account_id']]);
        }

        bofa_audit($agentId, 'FREEZE', 'cases', $id);
        return $this->updateStatus($id, 'en_attente', $agentId, 'Dossier gelé par agent.');
    }

    /**
     * Dégèle un dossier et le compte bancaire associé.
     */
    public function unfreeze(int $id, int $agentId): bool
    {
        $db   = bofa_db();
        $case = $this->getById($id);
        if (!$case) return false;

        // Réactiver le compte bancaire
        if ($case['account_id']) {
            $db->prepare("UPDATE accounts SET statut = 'actif' WHERE id = :aid")
               ->execute([':aid' => $case['account_id']]);
        }

        bofa_audit($agentId, 'UNFREEZE', 'cases', $id);
        return $this->updateStatus($id, 'en_cours', $agentId, 'Dossier dégelé par agent.');
    }

    /**
     * Valide le dossier et le marque en attente de transfert (statut en_attente + motif).
     */
    public function validateTransfer(int $id, int $agentId): bool
    {
        $db = bofa_db();
        $motif = "Transfert validé — en attente d'exécution";
        $db->prepare("UPDATE cases SET motif_cloture = :motif WHERE id = :id")
           ->execute([':motif' => $motif, ':id' => $id]);
        bofa_audit($agentId, 'VALIDATE_TRANSFER', 'cases', $id);
        return $this->updateStatus($id, 'en_attente', $agentId, 'Transfert validé par agent AML.');
    }

    /**
     * Exécute le transfert : mouvemente les fonds sur le compte et clôture le dossier.
     */
    public function executeTransfer(int $id, int $clientId): bool
    {
        $db   = bofa_db();
        $case = $this->getById($id);
        if (!$case || $case['statut'] !== 'en_attente') {
            return false;
        }

        // Mise à jour du solde du compte bancaire si présent
        if ($case['account_id'] && $case['montant'] > 0) {
            $db->prepare(
                "UPDATE accounts SET solde = solde + :montant WHERE id = :aid"
            )->execute([':montant' => $case['montant'], ':aid' => $case['account_id']]);
        }

        $db->prepare("UPDATE cases SET motif_cloture = 'Transfert effectué' WHERE id = :id")
           ->execute([':id' => $id]);

        bofa_audit($clientId, 'EXECUTE_TRANSFER', 'cases', $id, null, ['montant' => $case['montant']]);
        return $this->updateStatus($id, 'approuve', $clientId, 'Transfert exécuté.');
    }

    /**
     * Rejette un dossier avec un motif obligatoire.
     */
    public function reject(int $id, int $agentId, string $reason): bool
    {
        $reason = bofa_sanitize($reason);
        bofa_db()->prepare("UPDATE cases SET motif_cloture = :reason WHERE id = :id")
                 ->execute([':reason' => $reason, ':id' => $id]);
        return $this->updateStatus($id, 'rejete', $agentId, $reason);
    }

    /**
     * Assigne un agent à un dossier.
     */
    public function assignAgent(int $id, int $agentId): bool
    {
        $old  = $this->getById($id);
        $stmt = bofa_db()->prepare("UPDATE cases SET agent_id = :aid WHERE id = :id");
        $stmt->execute([':aid' => $agentId, ':id' => $id]);
        bofa_audit($agentId, 'ASSIGN_AGENT', 'cases', $id, $old, ['agent_id' => $agentId]);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Historique et dossiers similaires
    // -------------------------------------------------------------------------

    /**
     * Retourne la timeline complète d'un dossier (historique de statuts + messages internes).
     */
    public function getTimeline(int $id): array
    {
        $db = bofa_db();

        // Historique des statuts
        $stmtHist = $db->prepare(
            "SELECT csh.*, CONCAT(u.nom, ' ', u.prenom) AS auteur
             FROM case_status_history csh
             LEFT JOIN users u ON u.id = csh.user_id
             WHERE csh.case_id = :id
             ORDER BY csh.created_at ASC"
        );
        $stmtHist->execute([':id' => $id]);
        $historique = $stmtHist->fetchAll();

        // Messages internes (notes)
        $stmtMsg = $db->prepare(
            "SELECT m.*, CONCAT(u.nom, ' ', u.prenom) AS auteur
             FROM messages m
             LEFT JOIN users u ON u.id = m.expediteur_id
             WHERE m.case_id = :id
             ORDER BY m.created_at ASC"
        );
        $stmtMsg->execute([':id' => $id]);
        $notes = $stmtMsg->fetchAll();

        // Événements triés par date
        $events = [];
        foreach ($historique as $h) {
            $events[] = array_merge($h, ['event_type' => 'status_change']);
        }
        foreach ($notes as $n) {
            $events[] = array_merge($n, ['event_type' => 'note']);
        }
        usort($events, static fn($a, $b) => strtotime($a['created_at']) <=> strtotime($b['created_at']));

        return $events;
    }

    /**
     * Retourne les dossiers similaires selon la règle F45 :
     * même pays, même type d'actif, montant dans un intervalle ±20%,
     * ou même client.
     */
    public function getSimilarCases(int $id): array
    {
        $case = $this->getById($id);
        if (!$case) return [];

        $montant = (float) $case['montant'];
        $min     = $montant * 0.80;
        $max     = $montant * 1.20;

        $stmt = bofa_db()->prepare(
            "SELECT c.id, c.case_number, c.titre, c.statut, c.score_risque,
                    c.montant, c.pays_origine, c.type_actif, c.created_at
             FROM cases c
             WHERE c.id != :id
               AND (
                 (c.pays_origine = :pays AND c.type_actif = :ta AND c.montant BETWEEN :min AND :max)
                 OR c.user_id = :uid
               )
             ORDER BY c.created_at DESC
             LIMIT 10"
        );
        $stmt->execute([
            ':id'   => $id,
            ':pays' => $case['pays_origine'],
            ':ta'   => $case['type_actif'],
            ':min'  => $min,
            ':max'  => $max,
            ':uid'  => $case['user_id'],
        ]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // KPIs et statistiques
    // -------------------------------------------------------------------------

    /**
     * Retourne les indicateurs clés de performance AML.
     * Si agentId > 0, filtre sur l'agent concerné.
     */
    public function getKPIs(int $agentId = 0): array
    {
        $db     = bofa_db();
        $filter = $agentId > 0 ? ' AND agent_id = :aid' : '';

        // Dossiers actifs (ouverts ou en cours)
        $stmtActifs = $db->prepare(
            "SELECT COUNT(*) FROM cases WHERE statut IN ('ouvert','en_cours'){$filter}"
        );
        $agentId > 0 ? $stmtActifs->execute([':aid' => $agentId]) : $stmtActifs->execute();
        $actifs = (int) $stmtActifs->fetchColumn();

        // Dossiers en retard (date_echeance dépassée, non clôturés)
        $stmtRetard = $db->prepare(
            "SELECT COUNT(*) FROM cases
             WHERE date_echeance < CURDATE()
               AND statut NOT IN ('cloture','rejete','approuve'){$filter}"
        );
        $agentId > 0 ? $stmtRetard->execute([':aid' => $agentId]) : $stmtRetard->execute();
        $enRetard = (int) $stmtRetard->fetchColumn();

        // Dossiers en attente de validation
        $stmtValider = $db->prepare(
            "SELECT COUNT(*) FROM cases WHERE statut = 'en_attente'{$filter}"
        );
        $agentId > 0 ? $stmtValider->execute([':aid' => $agentId]) : $stmtValider->execute();
        $aValider = (int) $stmtValider->fetchColumn();

        // Délai moyen de traitement (en heures) pour les dossiers clôturés
        $stmtDelai = $db->prepare(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, closed_at))
             FROM cases
             WHERE closed_at IS NOT NULL{$filter}"
        );
        $agentId > 0 ? $stmtDelai->execute([':aid' => $agentId]) : $stmtDelai->execute();
        $moyenneDelai = round((float) $stmtDelai->fetchColumn(), 1);

        return [
            'actifs'        => $actifs,
            'en_retard'     => $enRetard,
            'a_valider'     => $aValider,
            'moyenne_delai' => $moyenneDelai, // en heures
        ];
    }

    // -------------------------------------------------------------------------
    // Tags
    // -------------------------------------------------------------------------

    /**
     * Ajoute un tag à un dossier.
     */
    public function addTag(int $caseId, int $tagId, int $userId): bool
    {
        $stmt = bofa_db()->prepare(
            "INSERT IGNORE INTO case_tags (case_id, tag_id, assigned_by, assigned_at)
             VALUES (:cid, :tid, :uid, NOW())"
        );
        return $stmt->execute([':cid' => $caseId, ':tid' => $tagId, ':uid' => $userId]);
    }

    /**
     * Retire un tag d'un dossier.
     */
    public function removeTag(int $caseId, int $tagId): bool
    {
        $stmt = bofa_db()->prepare("DELETE FROM case_tags WHERE case_id = :cid AND tag_id = :tid");
        $stmt->execute([':cid' => $caseId, ':tid' => $tagId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Retourne tous les tags associés à un dossier.
     */
    public function getTags(int $caseId): array
    {
        $stmt = bofa_db()->prepare(
            "SELECT t.id, t.libelle, t.couleur, ct.assigned_at
             FROM case_tags ct
             JOIN tags t ON t.id = ct.tag_id
             WHERE ct.case_id = :cid
             ORDER BY t.libelle"
        );
        $stmt->execute([':cid' => $caseId]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Règles métier
    // -------------------------------------------------------------------------

    /**
     * Applique les règles métier actives sur les données du dossier.
     * Délègue à la classe BusinessRule.
     */
    public function applyBusinessRules(array $caseData): array
    {
        $ruleEngine = new BusinessRule();
        return $ruleEngine->evaluate($caseData);
    }

    // -------------------------------------------------------------------------
    // Archivage
    // -------------------------------------------------------------------------

    /**
     * Archive les dossiers clôturés ou rejetés plus anciens que X mois.
     * Retourne le nombre de dossiers archivés.
     */
    public function archiveOld(int $monthsOld = 6): int
    {
        $db   = bofa_db();
        $stmt = $db->prepare(
            "UPDATE cases
             SET statut = 'cloture', motif_cloture = COALESCE(motif_cloture, 'Archivage automatique')
             WHERE statut IN ('rejete','approuve')
               AND closed_at < DATE_SUB(NOW(), INTERVAL :months MONTH)"
        );
        $stmt->bindValue(':months', $monthsOld, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->rowCount();

        bofa_audit(0, 'ARCHIVE_OLD', 'cases', 0, null, ['months' => $monthsOld, 'count' => $count]);

        return $count;
    }

    // -------------------------------------------------------------------------
    // Vérification des sanctions
    // -------------------------------------------------------------------------

    /**
     * Vérifie si un nom figure dans les pays sous sanctions (liste_noire).
     * Vérification simplifiée : correspondance partielle sur les noms de pays sanctionnés.
     */
    public function checkSanctions(string $name): bool
    {
        $name = bofa_sanitize(strtolower($name));
        if (empty($name)) return false;

        // Récupérer les noms des pays sanctionnés
        $stmt = bofa_db()->prepare(
            "SELECT LOWER(nom) AS nom FROM risk_countries WHERE liste_noire = 1"
        );
        $stmt->execute();
        $sanctionedCountries = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($sanctionedCountries as $country) {
            if (str_contains($name, $country) || str_contains($country, $name)) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Notes internes
    // -------------------------------------------------------------------------

    /**
     * Ajoute une note interne à un dossier (via la table messages).
     */
    public function addInternalNote(int $caseId, int $userId, string $note): bool
    {
        $note = bofa_sanitize($note);
        if (empty($note)) return false;

        $stmt = bofa_db()->prepare(
            "INSERT INTO messages (case_id, expediteur_id, destinataire_id, contenu, created_at)
             VALUES (:cid, :uid, NULL, :note, NOW())"
        );
        return $stmt->execute([':cid' => $caseId, ':uid' => $userId, ':note' => $note]);
    }

    // -------------------------------------------------------------------------
    // Méthode privée — notification de changement de statut
    // -------------------------------------------------------------------------

    /**
     * Envoie une notification au client lors d'un changement de statut.
     */
    private function sendStatusChange(int $caseId, string $newStatus, int $clientId): void
    {
        $notif = new Notification();
        $notif->sendStatusChange($caseId, $newStatus, $clientId);
    }
}
