<?php
/**
 * Déconnexion — BofaDueDiligence
 * Détruit la session, efface les cookies et redirige vers la connexion.
 */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';

/* Terminer la session persistante en base si elle existe */
if (!empty($_SESSION['user_id']) && !empty($_SESSION['session_token'])) {
    try {
        require_once dirname(__DIR__) . '/src/User.php';
        $user = new User();
        $user->terminateSession($_SESSION['session_token']);
    } catch (Throwable $e) {
        error_log('[BofaDueDiligence] Erreur déconnexion session : ' . $e->getMessage());
    }
}

/* Détruire la session PHP */
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

/* Effacer le cookie de mode sombre (facultatif — conserver ou non selon préférence) */
/* setcookie('bofa_dark_mode', '', time() - 3600, '/', '', false, true); */

bofa_redirect(BOFA_URL . '/login.php');
