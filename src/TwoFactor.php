<?php
defined('BOFA_APP') || die('Accès direct interdit.');

/**
 * Authentification à deux facteurs TOTP (RFC 6238).
 * Implémentation sans dépendance Composer : Base32, HMAC-SHA1, codes de secours.
 */
class TwoFactor
{
    /** Alphabet Base32 selon RFC 4648 */
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Durée d'un intervalle TOTP en secondes */
    private const STEP = 30;

    /** Longueur du code TOTP en chiffres */
    private const DIGITS = 6;

    /** Tolérance : nombre d'intervalles acceptés autour de l'intervalle courant */
    private const WINDOW = 1;

    public function __construct() {}

    // -------------------------------------------------------------------------
    // Génération du secret
    // -------------------------------------------------------------------------

    /**
     * Génère un secret aléatoire encodé en Base32 (160 bits = 20 octets).
     * Compatible avec Google Authenticator, Authy, etc.
     */
    public function generateSecret(): string
    {
        $bytes = random_bytes(20); // 160 bits
        return $this->_base32Encode($bytes);
    }

    // -------------------------------------------------------------------------
    // QR Code
    // -------------------------------------------------------------------------

    /**
     * Retourne l'URL du QR Code Google Charts pour l'enrôlement TOTP.
     * Format : otpauth://totp/{issuer}:{email}?secret={secret}&issuer={issuer}
     */
    public function getQRCodeUrl(string $email, string $secret): string
    {
        $email  = rawurlencode(bofa_sanitize($email));
        $secret = preg_replace('/[^A-Z2-7]/', '', strtoupper($secret));
        $issuer = rawurlencode('BofaDueDiligence');

        $otpauth = "otpauth://totp/{$issuer}:{$email}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";

        return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl='
               . rawurlencode($otpauth);
    }

    // -------------------------------------------------------------------------
    // Vérification TOTP
    // -------------------------------------------------------------------------

    /**
     * Vérifie un code TOTP selon RFC 6238.
     * Accepte ±WINDOW intervalles de 30 secondes autour de l'heure courante.
     *
     * @param string $secret Secret Base32 de l'utilisateur
     * @param string $code   Code à 6 chiffres soumis par l'utilisateur
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $secretBytes = $this->_base32Decode($secret);
        if ($secretBytes === '') {
            return false;
        }

        $timeStep = (int) floor(time() / self::STEP);

        // Vérification sur la fenêtre temporelle (courant ± tolérance)
        for ($delta = -self::WINDOW; $delta <= self::WINDOW; $delta++) {
            $computed = $this->_generateOTP($secretBytes, $timeStep + $delta);
            if (hash_equals($computed, $code)) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Codes de secours
    // -------------------------------------------------------------------------

    /**
     * Génère un tableau de codes de secours à usage unique.
     * Chaque code est au format XXXX-XXXX (8 caractères hexadécimaux).
     *
     * @return array Codes en clair (à afficher une seule fois à l'utilisateur)
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $count = max(1, min(20, $count));
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw     = bin2hex(random_bytes(4)); // 8 caractères hex
            $codes[] = strtoupper(substr($raw, 0, 4) . '-' . substr($raw, 4, 4));
        }
        return $codes;
    }

    /**
     * Vérifie et consomme un code de secours.
     * Les codes sont stockés sous forme de hachages SHA-256 dans le payload 2FA
     * de l'utilisateur (user_sessions, id = 'twofa_{userId}').
     *
     * @param int    $userId Identifiant de l'utilisateur
     * @param string $code   Code de secours en clair (format XXXX-XXXX)
     * @return bool true si le code est valide (et est alors invalidé)
     */
    public function verifyBackupCode(int $userId, string $code): bool
    {
        $code = strtoupper(preg_replace('/[^A-F0-9\-]/', '', $code));
        if (!preg_match('/^[A-F0-9]{4}-[A-F0-9]{4}$/', $code)) {
            return false;
        }

        $payload = $this->_loadTwoFactorPayload($userId);
        $stored  = $payload['backup_codes'] ?? [];

        if (empty($stored)) {
            return false;
        }

        $codeHash = hash('sha256', $code);

        foreach ($stored as $index => $hashEntry) {
            if (hash_equals($hashEntry, $codeHash)) {
                // Invalider le code utilisé (usage unique)
                unset($stored[$index]);
                $payload['backup_codes'] = array_values($stored);
                $this->_saveTwoFactorPayload($userId, $payload);
                return true;
            }
        }

        return false;
    }

    /**
     * Sauvegarde les codes de secours hachés pour un utilisateur.
     * À appeler lors de la génération initiale des codes.
     *
     * @param int   $userId     Identifiant de l'utilisateur
     * @param array $plainCodes Codes en clair retournés par generateBackupCodes()
     */
    public function storeBackupCodes(int $userId, array $plainCodes): bool
    {
        $payload              = $this->_loadTwoFactorPayload($userId);
        $payload['backup_codes'] = array_map(
            static fn(string $c) => hash('sha256', strtoupper(preg_replace('/[^A-F0-9\-]/', '', $c))),
            $plainCodes
        );
        return $this->_saveTwoFactorPayload($userId, $payload);
    }

    // -------------------------------------------------------------------------
    // Méthodes privées — TOTP / Base32
    // -------------------------------------------------------------------------

    /**
     * Calcule un code TOTP pour un intervalle de temps donné.
     * Algorithme RFC 6238 : HMAC-SHA1 + troncature dynamique.
     */
    private function _generateOTP(string $secretBytes, int $timeStep): string
    {
        // Empaquetage du compteur de temps en 8 octets big-endian
        $msg = pack('N*', 0) . pack('N*', $timeStep);

        // HMAC-SHA1
        $hmac = hash_hmac('sha1', $msg, $secretBytes, true);

        // Troncature dynamique
        $offset = ord($hmac[19]) & 0x0F;
        $code   = (
            ((ord($hmac[$offset])     & 0x7F) << 24)
          | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
          | ((ord($hmac[$offset + 2]) & 0xFF) <<  8)
          |  (ord($hmac[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($code % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Encode une chaîne binaire en Base32 (RFC 4648).
     */
    private function _base32Encode(string $data): string
    {
        $alphabet = self::BASE32_ALPHABET;
        $output   = '';
        $buffer   = 0;
        $bitsLeft = 0;

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $buffer    = ($buffer << 8) | ord($data[$i]);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $output   .= $alphabet[($buffer >> $bitsLeft) & 0x1F];
            }
        }

        // Rembourrage final si nécessaire
        if ($bitsLeft > 0) {
            $output .= $alphabet[($buffer << (5 - $bitsLeft)) & 0x1F];
        }

        // Padding '=' pour compléter les blocs de 8 caractères
        $padLen = (8 - (strlen($output) % 8)) % 8;
        return $output . str_repeat('=', $padLen);
    }

    /**
     * Décode une chaîne Base32 en binaire.
     * Retourne une chaîne vide en cas d'entrée invalide.
     */
    private function _base32Decode(string $data): string
    {
        // Normalisation
        $data = strtoupper(preg_replace('/[^A-Z2-7=]/', '', $data));
        $data = rtrim($data, '=');

        $alphabet = self::BASE32_ALPHABET;
        $lookup   = array_flip(str_split($alphabet));

        $buffer   = 0;
        $bitsLeft = 0;
        $output   = '';

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $char = $data[$i];
            if (!isset($lookup[$char])) {
                return ''; // Caractère invalide
            }
            $buffer    = ($buffer << 5) | $lookup[$char];
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output   .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }

    // -------------------------------------------------------------------------
    // Méthodes privées — persistance du payload 2FA
    // -------------------------------------------------------------------------

    /**
     * Charge le payload 2FA depuis user_sessions pour un utilisateur.
     */
    private function _loadTwoFactorPayload(int $userId): array
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
     * Sauvegarde le payload 2FA dans user_sessions pour un utilisateur.
     */
    private function _saveTwoFactorPayload(int $userId, array $data): bool
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
