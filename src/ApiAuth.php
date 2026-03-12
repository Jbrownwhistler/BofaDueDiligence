<?php
defined('BOFA_APP') || die('Accès direct interdit.');

/**
 * Authentification par token Bearer pour l'API REST BofaDueDiligence.
 * Tokens aléatoires (128 caractères hex) stockés dans user_sessions.
 * Durée de validité : 30 jours (stockée dans le champ payload en JSON).
 */
class ApiAuth
{
    /** Durée de validité des tokens API en secondes (30 jours) */
    private const TOKEN_TTL = 2592000;

    public function __construct() {}

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * Valide un token Bearer.
     * Vérifie l'existence en base, la date d'expiration stockée dans payload,
     * et retourne les données de l'utilisateur associé ou false.
     *
     * @return array|false Tableau [user_id, role, nom, prenom, email, token] ou false
     */
    public function validateToken(string $token): array|false
    {
        $token = $this->_sanitizeToken($token);
        if (!$token) return false;

        $db   = bofa_db();
        $stmt = $db->prepare(
            "SELECT us.id AS token, us.user_id, us.payload, us.created_at,
                    u.nom, u.prenom, u.email, u.role, u.statut
             FROM user_sessions us
             JOIN users u ON u.id = us.user_id
             WHERE us.id = :token
               AND u.statut = 'actif'
             LIMIT 1"
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();

        if (!$row) return false;

        // Vérification de l'expiration via le payload JSON
        $payload   = $row['payload'] ? (json_decode($row['payload'], true) ?? []) : [];
        $expiresAt = $payload['expires_at'] ?? null;

        if ($expiresAt !== null && strtotime($expiresAt) < time()) {
            // Token expiré : nettoyage automatique
            $this->revokeToken($token);
            return false;
        }

        // Mise à jour de la dernière activité
        $db->prepare("UPDATE user_sessions SET derniere_activite = NOW() WHERE id = :token")
           ->execute([':token' => $token]);

        return [
            'user_id' => (int) $row['user_id'],
            'role'    => $row['role'],
            'nom'     => $row['nom'],
            'prenom'  => $row['prenom'],
            'email'   => $row['email'],
            'token'   => $token,
        ];
    }

    // -------------------------------------------------------------------------
    // Génération et révocation
    // -------------------------------------------------------------------------

    /**
     * Génère un token API sécurisé pour un utilisateur et le persiste en base.
     * Retourne le token en clair (128 caractères hexadécimaux).
     */
    public function generateToken(int $userId): string
    {
        $token     = bin2hex(random_bytes(64)); // 128 caractères hex
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL);

        $payload = json_encode([
            'type'       => 'api',
            'expires_at' => $expiresAt,
        ], JSON_UNESCAPED_UNICODE);

        $db   = bofa_db();
        $stmt = $db->prepare(
            "INSERT INTO user_sessions (id, user_id, payload, derniere_activite, created_at)
             VALUES (:token, :uid, :payload, NOW(), NOW())"
        );
        $stmt->execute([':token' => $token, ':uid' => $userId, ':payload' => $payload]);

        bofa_audit($userId, 'API_TOKEN_CREATE', 'user_sessions', 0, null, ['expires_at' => $expiresAt]);

        return $token;
    }

    /**
     * Révoque (supprime) un token API.
     */
    public function revokeToken(string $token): bool
    {
        $token = $this->_sanitizeToken($token);
        if (!$token) return false;

        $stmt = bofa_db()->prepare("DELETE FROM user_sessions WHERE id = :token");
        $stmt->execute([':token' => $token]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Retourne tous les tokens API actifs d'un utilisateur.
     */
    public function getTokens(int $userId): array
    {
        $stmt = bofa_db()->prepare(
            "SELECT id AS token, ip_address, user_agent, payload,
                    derniere_activite, created_at
             FROM user_sessions
             WHERE user_id = :uid
               AND JSON_VALID(payload) = 1
               AND JSON_EXTRACT(payload, '$.type') = 'api'
             ORDER BY created_at DESC"
        );
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();

        // Enrichir chaque token avec la date d'expiration décodée
        return array_map(function (array $row): array {
            $payload           = json_decode($row['payload'] ?? '{}', true) ?? [];
            $row['expires_at'] = $payload['expires_at'] ?? null;
            $row['expired']    = isset($payload['expires_at'])
                                 && strtotime($payload['expires_at']) < time();
            unset($row['payload']);
            return $row;
        }, $rows);
    }

    // -------------------------------------------------------------------------
    // Extraction depuis la requête HTTP
    // -------------------------------------------------------------------------

    /**
     * Extrait le token Bearer depuis l'en-tête Authorization de la requête courante.
     * Retourne null si aucun token n'est présent ou si le format est incorrect.
     */
    public function getFromRequest(): string|null
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
                  ?? apache_request_headers()['Authorization']
                  ?? '';

        if (!$header) return null;

        if (!preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches)) {
            return null;
        }

        $token = $this->_sanitizeToken($matches[1]);
        return $token ?: null;
    }

    // -------------------------------------------------------------------------
    // Méthode privée utilitaire
    // -------------------------------------------------------------------------

    /**
     * Sanitise et valide un token : uniquement des caractères hexadécimaux, longueur 128.
     * Retourne la chaîne nettoyée ou une chaîne vide si invalide.
     */
    private function _sanitizeToken(string $token): string
    {
        $clean = preg_replace('/[^a-fA-F0-9]/', '', $token);
        // Accepter aussi les tokens des sessions PHP (alphanumérique, 26–128 chars)
        if (strlen($clean) < 26) {
            return '';
        }
        return strtolower(substr($clean, 0, 128));
    }
}
