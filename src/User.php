<?php
defined('BOFA_APP') || die('Accès direct interdit.');

/**
 * Gestion des utilisateurs — admins, agents AML et clients.
 * Authentification, protection brute-force, sessions, 2FA, absences, conflits.
 */
class User
{
    /** Nombre maximal de tentatives de connexion avant blocage */
    private const MAX_ATTEMPTS = 5;

    /** Durée de blocage après dépassement des tentatives, en secondes (15 min) */
    private const LOCKOUT_SECONDS = 900;

    public function __construct() {}

    // -------------------------------------------------------------------------
    // Authentification
    // -------------------------------------------------------------------------

    /**
     * Authentifie un utilisateur par email et mot de passe.
     * Vérifie le statut actif, protège contre le brute-force (5 tentatives / 15 min),
     * met à jour derniere_connexion et enregistre un audit.
     *
     * @return array|false Données utilisateur (sans password_hash) ou false
     */
    public function authenticate(string $email, string $password): array|false
    {
        $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return false;
        }

        $db   = bofa_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        // Vérification du blocage temporaire anti-brute-force
        if ($user['bloque_jusqu'] !== null && strtotime($user['bloque_jusqu']) > time()) {
            return false;
        }

        // Réinitialiser un blocage expiré
        if ($user['bloque_jusqu'] !== null && strtotime($user['bloque_jusqu']) <= time()) {
            $db->prepare("UPDATE users SET tentatives_connexion = 0, bloque_jusqu = NULL WHERE id = :id")
               ->execute([':id' => $user['id']]);
            $user['tentatives_connexion'] = 0;
        }

        // Vérification du mot de passe
        if (!password_verify($password, $user['password_hash'])) {
            $tentatives = (int) $user['tentatives_connexion'] + 1;
            if ($tentatives >= self::MAX_ATTEMPTS) {
                $db->prepare(
                    "UPDATE users
                     SET tentatives_connexion = :t,
                         bloque_jusqu = DATE_ADD(NOW(), INTERVAL :d SECOND)
                     WHERE id = :id"
                )->execute([':t' => $tentatives, ':d' => self::LOCKOUT_SECONDS, ':id' => $user['id']]);
            } else {
                $db->prepare("UPDATE users SET tentatives_connexion = :t WHERE id = :id")
                   ->execute([':t' => $tentatives, ':id' => $user['id']]);
            }
            return false;
        }

        // Vérification du statut du compte
        if ($user['statut'] !== 'actif') {
            return false;
        }

        // Mise à jour de la dernière connexion et remise à zéro du compteur de tentatives
        $db->prepare(
            "UPDATE users
             SET derniere_connexion = NOW(), tentatives_connexion = 0, bloque_jusqu = NULL
             WHERE id = :id"
        )->execute([':id' => $user['id']]);

        unset($user['password_hash']);
        $user['derniere_connexion'] = date('Y-m-d H:i:s');

        bofa_audit($user['id'], 'LOGIN', 'users', $user['id']);

        return $user;
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Crée un nouvel utilisateur avec hachage BCRYPT coût 12.
     *
     * @throws InvalidArgumentException si les champs obligatoires sont manquants
     */
    public function create(array $data): int
    {
        $nom    = bofa_sanitize($data['nom']    ?? '');
        $prenom = bofa_sanitize($data['prenom'] ?? '');
        $email  = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $pass   = $data['password'] ?? '';

        if (!$nom || !$prenom || !$email || !$pass) {
            throw new InvalidArgumentException('Champs obligatoires manquants : nom, prénom, email, password.');
        }

        $role   = in_array($data['role']   ?? '', ['admin', 'agent', 'client'], true) ? $data['role']   : 'client';
        $statut = in_array($data['statut'] ?? '', ['actif', 'inactif', 'suspendu'], true) ? $data['statut'] : 'actif';
        $tel    = bofa_sanitize($data['telephone'] ?? '');

        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

        $db   = bofa_db();
        $stmt = $db->prepare(
            "INSERT INTO users (nom, prenom, email, password_hash, role, statut, telephone, created_at)
             VALUES (:nom, :prenom, :email, :hash, :role, :statut, :tel, NOW())"
        );
        $stmt->execute([
            ':nom'    => $nom,
            ':prenom' => $prenom,
            ':email'  => $email,
            ':hash'   => $hash,
            ':role'   => $role,
            ':statut' => $statut,
            ':tel'    => $tel ?: null,
        ]);

        $newId = (int) $db->lastInsertId();
        bofa_audit(0, 'CREATE', 'users', $newId, null, ['email' => $email, 'role' => $role]);

        return $newId;
    }

    /**
     * Met à jour les champs autorisés d'un utilisateur.
     * Le mot de passe est exclu — utiliser changePassword() ou resetPassword().
     */
    public function update(int $id, array $data): bool
    {
        $allowedFields = ['nom', 'prenom', 'email', 'role', 'statut', 'telephone', 'avatar'];
        $sets          = [];
        $params        = [];

        $oldUser = $this->getById($id);

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $val = bofa_sanitize((string) ($data[$field] ?? ''));

            // Validation spécifique par champ
            if ($field === 'email') {
                $val = filter_var(trim($data[$field]), FILTER_VALIDATE_EMAIL);
                if (!$val) continue;
            } elseif ($field === 'role' && !in_array($val, ['admin', 'agent', 'client'], true)) {
                continue;
            } elseif ($field === 'statut' && !in_array($val, ['actif', 'inactif', 'suspendu'], true)) {
                continue;
            }

            $sets[]             = "{$field} = :{$field}";
            $params[":{$field}"] = $val ?: null;
        }

        if (empty($sets)) {
            return false;
        }

        $params[':id'] = $id;
        $stmt = bofa_db()->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);

        bofa_audit(0, 'UPDATE', 'users', $id, $oldUser, array_intersect_key($data, array_flip($allowedFields)));

        return $stmt->rowCount() >= 0;
    }

    /**
     * Suppression logique : passage du statut à 'inactif'.
     */
    public function delete(int $id): bool
    {
        $old  = $this->getById($id);
        $stmt = bofa_db()->prepare("UPDATE users SET statut = 'inactif' WHERE id = :id");
        $stmt->execute([':id' => $id]);
        bofa_audit(0, 'DELETE', 'users', $id, $old, ['statut' => 'inactif']);
        return $stmt->rowCount() > 0;
    }

    /**
     * Récupère un utilisateur par son identifiant (sans le hash du mot de passe).
     */
    public function getById(int $id): array|null
    {
        $stmt = bofa_db()->prepare(
            "SELECT id, nom, prenom, email, role, statut, telephone, avatar,
                    derniere_connexion, created_at, updated_at
             FROM users WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Récupère un utilisateur par son adresse e-mail (inclut le hash pour l'auth).
     */
    public function getByEmail(string $email): array|null
    {
        $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        if (!$email) return null;

        $stmt = bofa_db()->prepare(
            "SELECT id, nom, prenom, email, role, statut, telephone, avatar,
                    derniere_connexion, password_hash, created_at
             FROM users WHERE email = :email LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Liste les utilisateurs avec filtres optionnels et pagination.
     * Filtres : role, statut, search (nom / prénom / email).
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $db     = bofa_db();
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['role'])) {
            $where[]         = 'role = :role';
            $params[':role'] = bofa_sanitize($filters['role']);
        }
        if (!empty($filters['statut'])) {
            $where[]           = 'statut = :statut';
            $params[':statut'] = bofa_sanitize($filters['statut']);
        }
        if (!empty($filters['search'])) {
            $where[]           = '(nom LIKE :search OR prenom LIKE :search OR email LIKE :search)';
            $params[':search'] = '%' . bofa_sanitize($filters['search']) . '%';
        }

        $whereStr  = implode(' AND ', $where);
        $countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE {$whereStr}");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetchColumn();
        $pagination = bofa_paginate($total, $perPage, $page);

        $stmt = $db->prepare(
            "SELECT id, nom, prenom, email, role, statut, telephone, derniere_connexion, created_at
             FROM users WHERE {$whereStr}
             ORDER BY created_at DESC
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

    // -------------------------------------------------------------------------
    // Gestion des mots de passe
    // -------------------------------------------------------------------------

    /**
     * Modifie le mot de passe après vérification de l'ancien.
     */
    public function changePassword(int $id, string $oldPass, string $newPass): bool
    {
        $stmt = bofa_db()->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row  = $stmt->fetch();

        if (!$row || !password_verify($oldPass, $row['password_hash'])) {
            return false;
        }

        $newHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $upd     = bofa_db()->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
        $upd->execute([':hash' => $newHash, ':id' => $id]);

        bofa_audit($id, 'CHANGE_PASSWORD', 'users', $id);

        return $upd->rowCount() > 0;
    }

    /**
     * Réinitialise le mot de passe et retourne le mot de passe temporaire en clair.
     * Le mot de passe doit être communiqué à l'utilisateur par un canal sécurisé.
     */
    public function resetPassword(int $id): string
    {
        $tempPass = bin2hex(random_bytes(8)); // 16 caractères hexadécimaux
        $hash     = password_hash($tempPass, PASSWORD_BCRYPT, ['cost' => 12]);

        bofa_db()->prepare("UPDATE users SET password_hash = :hash WHERE id = :id")
                 ->execute([':hash' => $hash, ':id' => $id]);

        bofa_audit($id, 'RESET_PASSWORD', 'users', $id);

        return $tempPass;
    }

    // -------------------------------------------------------------------------
    // Authentification à deux facteurs (2FA TOTP)
    // Les données 2FA sont stockées dans user_sessions (payload JSON, id = 'twofa_{userId}')
    // -------------------------------------------------------------------------

    /**
     * Enregistre le secret 2FA pour l'utilisateur (non encore activé).
     */
    public function setupTwoFactor(int $id, string $secret): bool
    {
        $payload = $this->_get2FAPayload($id);
        $payload['secret']  = bofa_sanitize($secret);
        $payload['enabled'] = false;
        return $this->_save2FAPayload($id, $payload);
    }

    /**
     * Active l'authentification à deux facteurs (le secret doit déjà être configuré).
     */
    public function enableTwoFactor(int $id): bool
    {
        $payload = $this->_get2FAPayload($id);
        if (empty($payload['secret'])) {
            return false;
        }
        $payload['enabled'] = true;
        bofa_audit($id, 'ENABLE_2FA', 'users', $id);
        return $this->_save2FAPayload($id, $payload);
    }

    /**
     * Désactive l'authentification à deux facteurs.
     */
    public function disableTwoFactor(int $id): bool
    {
        $payload            = $this->_get2FAPayload($id);
        $payload['enabled'] = false;
        bofa_audit($id, 'DISABLE_2FA', 'users', $id);
        return $this->_save2FAPayload($id, $payload);
    }

    // -------------------------------------------------------------------------
    // Gestion des sessions
    // -------------------------------------------------------------------------

    /**
     * Enregistre une session active dans user_sessions.
     */
    public function logSession(int $userId, string $token, string $ip, string $userAgent): bool
    {
        $token = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $token), 0, 128);
        $ip    = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
        $ua    = substr(bofa_sanitize($userAgent), 0, 500);

        $stmt = bofa_db()->prepare(
            "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, derniere_activite, created_at)
             VALUES (:id, :uid, :ip, :ua, NOW(), NOW())
             ON DUPLICATE KEY UPDATE derniere_activite = NOW()"
        );
        return $stmt->execute([':id' => $token, ':uid' => $userId, ':ip' => $ip, ':ua' => $ua]);
    }

    /**
     * Retourne toutes les sessions actives d'un utilisateur (exclut les métadonnées internes).
     */
    public function getActiveSessions(int $userId): array
    {
        $stmt = bofa_db()->prepare(
            "SELECT id AS token, ip_address, user_agent, derniere_activite, created_at
             FROM user_sessions
             WHERE user_id = :uid
               AND id NOT LIKE 'twofa_%'
               AND id NOT LIKE 'meta_%'
             ORDER BY derniere_activite DESC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Termine une session spécifique par son token.
     */
    public function terminateSession(string $token): bool
    {
        $token = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $token), 0, 128);
        $stmt  = bofa_db()->prepare("DELETE FROM user_sessions WHERE id = :token");
        $stmt->execute([':token' => $token]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Termine toutes les sessions actives d'un utilisateur (préserve les métadonnées internes).
     */
    public function terminateAllSessions(int $userId): bool
    {
        $stmt = bofa_db()->prepare(
            "DELETE FROM user_sessions
             WHERE user_id = :uid
               AND id NOT LIKE 'twofa_%'
               AND id NOT LIKE 'meta_%'"
        );
        $stmt->execute([':uid' => $userId]);
        return true;
    }

    // -------------------------------------------------------------------------
    // Absences et conflits d'intérêt
    // Stockés dans user_sessions avec des identifiants de métadonnées spéciaux
    // -------------------------------------------------------------------------

    /**
     * Déclare une période d'absence pour un utilisateur avec désignation du remplaçant.
     */
    public function declareAbsence(int $id, string $debut, string $fin, int $remplacantId): bool
    {
        $payload = json_encode([
            'absence_debut'  => bofa_sanitize($debut),
            'absence_fin'    => bofa_sanitize($fin),
            'remplacant_id'  => $remplacantId,
            'declared_at'    => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);

        $db   = bofa_db();
        $stmt = $db->prepare(
            "INSERT INTO user_sessions (id, user_id, payload, derniere_activite, created_at)
             VALUES (:id, :uid, :payload, NOW(), NOW())
             ON DUPLICATE KEY UPDATE payload = :payload2, derniere_activite = NOW()"
        );
        bofa_audit($id, 'DECLARE_ABSENCE', 'users', $id);
        return $stmt->execute([
            ':id'       => 'meta_absence_' . $id,
            ':uid'      => $id,
            ':payload'  => $payload,
            ':payload2' => $payload,
        ]);
    }

    /**
     * Déclare un conflit d'intérêt pour un utilisateur.
     */
    public function declareConflict(int $id): bool
    {
        $payload = json_encode([
            'conflit'      => true,
            'declared_at'  => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);

        $db   = bofa_db();
        $stmt = $db->prepare(
            "INSERT INTO user_sessions (id, user_id, payload, derniere_activite, created_at)
             VALUES (:id, :uid, :payload, NOW(), NOW())
             ON DUPLICATE KEY UPDATE payload = :payload2, derniere_activite = NOW()"
        );
        bofa_audit($id, 'DECLARE_CONFLICT', 'users', $id);
        return $stmt->execute([
            ':id'       => 'meta_conflict_' . $id,
            ':uid'      => $id,
            ':payload'  => $payload,
            ':payload2' => $payload,
        ]);
    }

    // -------------------------------------------------------------------------
    // Agents
    // -------------------------------------------------------------------------

    /**
     * Retourne la liste de tous les agents actifs.
     */
    public function getAgents(): array
    {
        $stmt = bofa_db()->prepare(
            "SELECT id, nom, prenom, email, statut, telephone, derniere_connexion
             FROM users
             WHERE role = 'agent' AND statut = 'actif'
             ORDER BY nom, prenom"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Assigne un agent à un client et met à jour les dossiers en cours.
     */
    public function assignAgentToClient(int $clientId, int $agentId): bool
    {
        $payload = json_encode([
            'agent_id'    => $agentId,
            'assigned_at' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);

        $db   = bofa_db();
        $stmt = $db->prepare(
            "INSERT INTO user_sessions (id, user_id, payload, derniere_activite, created_at)
             VALUES (:id, :uid, :payload, NOW(), NOW())
             ON DUPLICATE KEY UPDATE payload = :payload2, derniere_activite = NOW()"
        );
        $ok = $stmt->execute([
            ':id'       => 'meta_agent_' . $clientId,
            ':uid'      => $clientId,
            ':payload'  => $payload,
            ':payload2' => $payload,
        ]);

        // Mettre à jour les dossiers actifs du client avec le nouvel agent
        if ($ok) {
            $db->prepare(
                "UPDATE cases SET agent_id = :aid
                 WHERE user_id = :cid AND statut IN ('ouvert','en_cours','en_attente')"
            )->execute([':aid' => $agentId, ':cid' => $clientId]);

            bofa_audit($agentId, 'ASSIGN_AGENT', 'users', $clientId, null, ['agent_id' => $agentId]);
        }

        return $ok;
    }

    // -------------------------------------------------------------------------
    // Méthodes privées — gestion du payload 2FA
    // -------------------------------------------------------------------------

    /**
     * Lit le payload 2FA depuis user_sessions pour un utilisateur donné.
     */
    private function _get2FAPayload(int $userId): array
    {
        $stmt = bofa_db()->prepare(
            "SELECT payload FROM user_sessions WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => 'twofa_' . $userId]);
        $row = $stmt->fetch();

        if (!$row || !$row['payload']) {
            return ['secret' => '', 'enabled' => false, 'backup_codes' => []];
        }
        return json_decode($row['payload'], true)
            ?? ['secret' => '', 'enabled' => false, 'backup_codes' => []];
    }

    /**
     * Sauvegarde le payload 2FA dans user_sessions pour un utilisateur donné.
     */
    private function _save2FAPayload(int $userId, array $data): bool
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $stmt    = bofa_db()->prepare(
            "INSERT INTO user_sessions (id, user_id, payload, derniere_activite, created_at)
             VALUES (:id, :uid, :payload, NOW(), NOW())
             ON DUPLICATE KEY UPDATE payload = :payload2, derniere_activite = NOW()"
        );
        return $stmt->execute([
            ':id'       => 'twofa_' . $userId,
            ':uid'      => $userId,
            ':payload'  => $payload,
            ':payload2' => $payload,
        ]);
    }
}
