<?php
/**
 * Point d'entrée principal — BofaDueDiligence
 * Redirige l'utilisateur vers son tableau de bord selon son rôle.
 */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';

/* L'utilisateur n'est pas connecté → page de connexion */
if (empty($_SESSION['user_id'])) {
    bofa_redirect(BOFA_URL . '/login.php');
}

$role = $_SESSION['user_role'] ?? '';

/* Base de l'application (un niveau au-dessus de public/) */
$baseApp = rtrim(dirname(BOFA_URL), '/');

switch ($role) {
    case 'client':
        bofa_redirect($baseApp . '/client/dashboard.php');
        break;
    case 'agent':
        bofa_redirect($baseApp . '/agent/dashboard.php');
        break;
    case 'admin':
        bofa_redirect($baseApp . '/admin/dashboard.php');
        break;
    default:
        /* Rôle inconnu — déconnecter et renvoyer à la connexion */
        session_unset();
        session_destroy();
        bofa_redirect(BOFA_URL . '/login.php');
}
