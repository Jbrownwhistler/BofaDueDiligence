<?php
/**
 * Endpoint AJAX — Comptage des notifications non lues — BofaDueDiligence
 * Retourne un objet JSON : {"count": N}
 *
 * Appelé par bofa.js toutes les 30 secondes pour mettre à jour le badge.
 */
define('BOFA_APP', true);
require_once dirname(__DIR__, 2) . '/config.php';

/* Seules les requêtes AJAX authentifiées sont acceptées */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

/* Vérifier que la requête est bien AJAX */
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Requête invalide.']);
    exit();
}

/* Vérifier que l'utilisateur est connecté */
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié.', 'count' => 0]);
    exit();
}

$userId = (int) $_SESSION['user_id'];
$count  = 0;

try {
    require_once dirname(__DIR__, 2) . '/src/Notification.php';
    $notif = new Notification();
    $count = $notif->getUnreadCount($userId);
} catch (Throwable $e) {
    error_log('[BofaDueDiligence] Erreur notifications API : ' . $e->getMessage());
    /* Retourner 0 sans exposer l'erreur au client */
}

echo json_encode(['count' => $count]);
