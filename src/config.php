<?php
/**
 * BofaDueDiligence - Configuration
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'bofa_due_diligence');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'BofaDueDiligence');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Session config
define('SESSION_LIFETIME', 3600); // 1 hour

// Database connection (PDO singleton)
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
