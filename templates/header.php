<?php
/**
 * En-tête HTML commun — BofaDueDiligence
 * Variables attendues :
 *   $pageTitle  (string) — titre de la page
 *   $userRole   (string) — rôle de l'utilisateur connecté (pour la sidebar)
 */
defined('BOFA_APP') || die('Accès direct interdit.');

/* Titre de la page avec valeur de repli */
$pageTitle = isset($pageTitle) ? trim($pageTitle) : 'BofaDueDiligence';

/* Rôle utilisateur avec valeur de repli */
$userRole = isset($userRole) ? $userRole : ($_SESSION['user_role'] ?? 'client');

/* Jeton CSRF disponible dans les vues */
$csrfToken = bofa_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= $csrfToken ?>">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — BofaDueDiligence</title>

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    <!-- FontAwesome 6 -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"
            integrity="sha256-kFBPHrBYAI0T9KgKqDPT6MFXVkvqh+JOi3RrFB/4RAo="
            crossorigin="anonymous"></script>

    <!-- Styles BofA -->
    <link rel="stylesheet" href="<?= BOFA_URL ?>/assets/css/bofa.css">

    <!-- Configuration applicative accessible aux scripts JS -->
    <script>
    window.bofaConfig = {
        url:          '<?= BOFA_URL ?>',
        notifApiUrl:  '<?= BOFA_URL ?>/api/notifications.php',
        sessionTimeout: <?= BOFA_SESSION_TIMEOUT ?>
    };
    </script>
</head>
<body>
<div class="bofa-wrapper">

    <!-- Overlay mobile pour fermer la sidebar -->
    <div class="sidebar-overlay" aria-hidden="true"></div>

    <!-- Inclusion de la sidebar selon le rôle -->
    <?php
    $sidebarFile = match ($userRole) {
        'admin'  => dirname(__DIR__) . '/templates/sidebar-admin.php',
        'agent'  => dirname(__DIR__) . '/templates/sidebar-agent.php',
        default  => dirname(__DIR__) . '/templates/sidebar-client.php',
    };
    if (file_exists($sidebarFile)) {
        include $sidebarFile;
    }
    ?>

    <!-- Zone principale -->
    <div class="bofa-main" id="bofa-main">

        <!-- Barre supérieure -->
        <header class="bofa-topbar">
            <!-- Bouton hamburger (mobile) -->
            <button class="btn btn-sm btn-outline-secondary d-lg-none me-2"
                    data-mobile-sidebar
                    title="Menu"
                    aria-label="Ouvrir le menu">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- Bouton repli desktop -->
            <button class="btn btn-sm btn-outline-secondary d-none d-lg-inline-flex me-2"
                    data-sidebar-toggle
                    title="Réduire/agrandir la barre latérale"
                    aria-label="Réduire la barre latérale">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- Titre de la page -->
            <span class="topbar-title">
                <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
            </span>

            <div class="topbar-actions">
                <!-- Cloche de notifications -->
                <a href="#" class="notif-bell text-decoration-none" title="Notifications" aria-label="Notifications">
                    <i class="fa-regular fa-bell"></i>
                    <span class="notif-badge" data-count="0" style="display:none;"></span>
                </a>

                <!-- Bascule mode sombre -->
                <button class="btn btn-sm btn-outline-secondary"
                        data-dark-toggle
                        title="Mode sombre"
                        aria-label="Basculer le mode sombre">
                    <i class="fa-solid fa-moon"></i>
                </button>

                <!-- Menu utilisateur -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                        <i class="fa-regular fa-circle-user me-1"></i>
                        <?= htmlspecialchars(($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text text-secondary small">
                                <?= htmlspecialchars($_SESSION['user_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                <br>
                                <span class="role-badge role-<?= htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="/bofa/<?= htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') ?>/profil.php">
                                <i class="fa-regular fa-id-card me-2"></i>Mon profil
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= BOFA_URL ?>/logout.php"
                               data-confirm="Voulez-vous vraiment vous déconnecter ?"
                               data-confirm-title="Déconnexion">
                                <i class="fa-solid fa-right-from-bracket me-2"></i>Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Conteneur des alertes flash injectées via JS ou PHP -->
        <div id="alert-container" class="px-3 pt-2"></div>

        <!-- Contenu principal de la page -->
        <main class="bofa-content">
