<?php
/**
 * Barre latérale — rôle client — BofaDueDiligence
 * Variables attendues (optionnelles) :
 *   $notifCount   (int)    — nombre de notifications non lues
 *   $messagesNon  (int)    — nombre de messages non lus
 *   $currentPage  (string) — identifiant de la page active (pour la classe active)
 */
defined('BOFA_APP') || die('Accès direct interdit.');

$notifCount  = isset($notifCount)  ? (int) $notifCount  : 0;
$messagesNon = isset($messagesNon) ? (int) $messagesNon : 0;
$currentPage = $currentPage ?? '';

/* Détermine la classe active pour un lien de navigation */
$actif = fn(string $page): string => $currentPage === $page ? ' active' : '';
?>
<aside class="bofa-sidebar" id="bofa-sidebar" role="navigation" aria-label="Menu client">

    <!-- Marque / logo -->
    <a class="sidebar-brand text-decoration-none" href="/bofa/client/dashboard.php">
        <div class="sidebar-brand-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div class="sidebar-brand-text">
            <span class="brand-name">BofA<span style="color:var(--bofa-rouge);">DD</span></span>
            <span class="brand-sub">Espace Client</span>
        </div>
    </a>

    <!-- Navigation principale -->
    <ul class="sidebar-nav" role="menubar">

        <li class="nav-section-title">Navigation</li>

        <!-- Tableau de bord -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('dashboard') ?>"
               href="/bofa/client/dashboard.php"
               role="menuitem"
               title="Tableau de bord">
                <i class="sidebar-icon fa-solid fa-gauge-high"></i>
                <span class="sidebar-label">Tableau de bord</span>
            </a>
        </li>

        <!-- Fonds en attente -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('fonds') ?>"
               href="/bofa/client/fonds.php"
               role="menuitem"
               title="Fonds en attente">
                <i class="sidebar-icon fa-solid fa-circle-dollar-to-slot"></i>
                <span class="sidebar-label">Fonds en attente</span>
            </a>
        </li>

        <!-- Documents -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('documents') ?>"
               href="/bofa/client/documents.php"
               role="menuitem"
               title="Documents">
                <i class="sidebar-icon fa-solid fa-folder-open"></i>
                <span class="sidebar-label">Documents</span>
            </a>
        </li>

        <!-- Messages avec badge non-lus -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('messages') ?>"
               href="/bofa/client/messages.php"
               role="menuitem"
               title="Messages">
                <i class="sidebar-icon fa-regular fa-envelope"></i>
                <span class="sidebar-label">Messages</span>
                <?php if ($messagesNon > 0): ?>
                <span class="badge bg-danger rounded-pill" style="font-size:.65rem;">
                    <?= $messagesNon > 99 ? '99+' : $messagesNon ?>
                </span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Historique -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('historique') ?>"
               href="/bofa/client/historique.php"
               role="menuitem"
               title="Historique">
                <i class="sidebar-icon fa-solid fa-clock-rotate-left"></i>
                <span class="sidebar-label">Historique</span>
            </a>
        </li>

        <li class="nav-section-title">Compte</li>

        <!-- Profil -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('profil') ?>"
               href="/bofa/client/profil.php"
               role="menuitem"
               title="Mon profil">
                <i class="sidebar-icon fa-regular fa-id-card"></i>
                <span class="sidebar-label">Mon profil</span>
            </a>
        </li>

    </ul>

    <!-- Pied de sidebar -->
    <div class="sidebar-footer">

        <!-- Cloche notifications -->
        <a href="/bofa/client/notifications.php"
           class="d-flex align-items-center gap-2 sidebar-link text-decoration-none mb-2"
           title="Notifications">
            <span class="sidebar-icon position-relative">
                <i class="fa-regular fa-bell"></i>
                <span class="notif-badge" data-count="<?= $notifCount ?>"
                    <?= $notifCount > 0 ? '' : 'style="display:none;"' ?>>
                    <?= $notifCount > 99 ? '99+' : $notifCount ?>
                </span>
            </span>
            <span class="sidebar-label">Notifications</span>
        </a>

        <!-- Bascule mode sombre -->
        <button class="sidebar-link w-100 border-0 text-start"
                data-dark-toggle
                title="Mode sombre"
                aria-label="Basculer le mode sombre"
                style="background:none;">
            <i class="sidebar-icon fa-solid fa-moon"></i>
            <span class="sidebar-label">Mode sombre</span>
        </button>

        <!-- Déconnexion -->
        <a class="sidebar-link text-decoration-none mt-1"
           href="<?= BOFA_URL ?>/logout.php"
           title="Déconnexion"
           data-confirm="Voulez-vous vraiment vous déconnecter ?"
           data-confirm-title="Déconnexion">
            <i class="sidebar-icon fa-solid fa-right-from-bracket" style="color:var(--bofa-rouge);"></i>
            <span class="sidebar-label" style="color:var(--bofa-rouge);">Déconnexion</span>
        </a>

    </div>
</aside>
