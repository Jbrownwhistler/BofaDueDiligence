<?php
class CaseModel {
    private static array $statusLabels = [
        'en_analyse' => 'En cours d\'analyse',
        'documents_demandes' => 'Documents demandés',
        'en_attente_validation' => 'En attente de validation',
        'valide' => 'Validé',
        'pret_pour_transfert' => 'Prêt pour transfert',
        'rejete' => 'Rejeté',
        'gele' => 'Gelé',
    ];

    private static array $fondsLabels = [
        'bloque' => 'Bloqué',
        'gele' => 'Gelé',
        'disponible' => 'Disponible',
        'transfere' => 'Transféré',
    ];

    public static function getStatusLabel(string $status): string {
        return self::$statusLabels[$status] ?? $status;
    }

    public static function getFondsLabel(string $status): string {
        return self::$fondsLabels[$status] ?? $status;
    }

    public static function findById(int $id): ?array {
        $stmt = getDB()->prepare(
            'SELECT c.*, sa.numero_sous_compte, sa.ledger,
                    a.numero_compte_principal, a.user_id as client_user_id,
                    CONCAT(u.prenom, " ", u.nom) as agent_name
             FROM cases c
             JOIN sub_accounts sa ON c.sub_account_id = sa.id
             JOIN accounts a ON sa.account_id = a.id
             LEFT JOIN users u ON c.agent_assigne_id = u.id
             WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function getByClient(int $userId): array {
        $stmt = getDB()->prepare(
            'SELECT c.*, sa.numero_sous_compte, sa.ledger,
                    CONCAT(u.prenom, " ", u.nom) as agent_name
             FROM cases c
             JOIN sub_accounts sa ON c.sub_account_id = sa.id
             JOIN accounts a ON sa.account_id = a.id
             LEFT JOIN users u ON c.agent_assigne_id = u.id
             WHERE a.user_id = ?
             ORDER BY c.date_creation DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getByAgent(int $agentId, array $filters = []): array {
        $sql = 'SELECT c.*, sa.numero_sous_compte,
                       CONCAT(uc.prenom, " ", uc.nom) as client_name
                FROM cases c
                JOIN sub_accounts sa ON c.sub_account_id = sa.id
                JOIN accounts a ON sa.account_id = a.id
                JOIN users uc ON a.user_id = uc.id
                WHERE c.agent_assigne_id = ?';
        $params = [$agentId];

        if (!empty($filters['statut'])) {
            $sql .= ' AND c.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['pays'])) {
            $sql .= ' AND (c.pays_origine = ? OR c.pays_destination = ?)';
            $params[] = $filters['pays'];
            $params[] = $filters['pays'];
        }

        $sql .= ' ORDER BY c.date_creation DESC';
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getAll(array $filters = []): array {
        $sql = 'SELECT c.*, sa.numero_sous_compte,
                       CONCAT(uc.prenom, " ", uc.nom) as client_name,
                       CONCAT(ua.prenom, " ", ua.nom) as agent_name
                FROM cases c
                JOIN sub_accounts sa ON c.sub_account_id = sa.id
                JOIN accounts a ON sa.account_id = a.id
                JOIN users uc ON a.user_id = uc.id
                LEFT JOIN users ua ON c.agent_assigne_id = ua.id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['statut'])) {
            $sql .= ' AND c.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['agent_id'])) {
            $sql .= ' AND c.agent_assigne_id = ?';
            $params[] = $filters['agent_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (c.case_id_unique LIKE ? OR c.emetteur_nom LIKE ? OR c.beneficiaire_nom LIKE ?)';
            $s = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$s, $s, $s]);
        }

        $sql .= ' ORDER BY c.date_creation DESC';
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function updateStatus(int $id, string $newStatus, ?string $comment = null): void {
        $case = self::findById($id);
        $oldStatus = $case['statut'];

        getDB()->prepare('UPDATE cases SET statut = ? WHERE id = ?')->execute([$newStatus, $id]);

        // Log status change
        getDB()->prepare(
            'INSERT INTO case_status_history (case_id, ancien_statut, nouveau_statut, commentaire, utilisateur_id)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$id, $oldStatus, $newStatus, $comment, Auth::id()]);

        AuditLog::log("Changement de statut: $oldStatus → $newStatus", 'cases', $id, $oldStatus, $newStatus);
    }

    public static function updateFundsStatus(int $id, string $status): void {
        getDB()->prepare('UPDATE cases SET statut_fonds = ? WHERE id = ?')->execute([$status, $id]);
    }

    public static function countByStatus(?int $agentId = null): array {
        $sql = 'SELECT statut, COUNT(*) as cnt FROM cases';
        $params = [];
        if ($agentId) {
            $sql .= ' WHERE agent_assigne_id = ?';
            $params[] = $agentId;
        }
        $sql .= ' GROUP BY statut';
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['statut']] = (int)$row['cnt'];
        }
        return $result;
    }

    public static function totalAmount(?int $agentId = null): float {
        $sql = 'SELECT COALESCE(SUM(montant), 0) as total FROM cases';
        $params = [];
        if ($agentId) {
            $sql .= ' WHERE agent_assigne_id = ?';
            $params[] = $agentId;
        }
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetch()['total'];
    }

    public static function getOverdue(?int $agentId = null): array {
        $sql = 'SELECT c.*, CONCAT(uc.prenom, " ", uc.nom) as client_name
                FROM cases c
                JOIN sub_accounts sa ON c.sub_account_id = sa.id
                JOIN accounts a ON sa.account_id = a.id
                JOIN users uc ON a.user_id = uc.id
                WHERE c.date_limite < CURDATE()
                AND c.statut NOT IN ("valide","pret_pour_transfert","rejete","transfere")';
        $params = [];
        if ($agentId) {
            $sql .= ' AND c.agent_assigne_id = ?';
            $params[] = $agentId;
        }
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getStatusHistory(int $caseId): array {
        $stmt = getDB()->prepare(
            'SELECT csh.*, CONCAT(u.prenom, " ", u.nom) as user_name
             FROM case_status_history csh
             LEFT JOIN users u ON csh.utilisateur_id = u.id
             WHERE csh.case_id = ?
             ORDER BY csh.date DESC'
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }

    public static function generateCaseId(): string {
        $year = date('Y');
        $stmt = getDB()->query("SELECT MAX(CAST(SUBSTRING(case_id_unique, -5) AS UNSIGNED)) as max_num FROM cases WHERE case_id_unique LIKE 'AML-EDD-$year-%'");
        $result = $stmt->fetch();
        $nextNum = ($result['max_num'] ?? 0) + 1;
        return sprintf('AML-EDD-%s-%05d', $year, $nextNum);
    }

    public static function getTransferred(int $userId): array {
        $stmt = getDB()->prepare(
            'SELECT c.*, sa.numero_sous_compte
             FROM cases c
             JOIN sub_accounts sa ON c.sub_account_id = sa.id
             JOIN accounts a ON sa.account_id = a.id
             WHERE a.user_id = ? AND c.statut_fonds = "transfere"
             ORDER BY c.date_maj DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
