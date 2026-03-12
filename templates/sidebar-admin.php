<?php
/**
 * Barre latérale — rôle admin — BofaDueDiligence
 * Variables attendues (optionnelles) :
 *   $notifCount  (int)    — nombre de notifications non lues
 *   $currentPage (string) — identifiant de la page active
 */
defined('BOFA_APP') || die('Accès direct interdit.');

$notifCount  = isset($notifCount)  ? (int) $notifCount  : 0;
$currentPage = $currentPage ?? '';

$actif = fn(string $page): string => $currentPage === $page ? ' active' : '';
?>
<aside class="bofa-sidebar" id="bofa-sidebar" role="navigation" aria-label="Menu administrateur">

    <!-- Marque / logo -->
    <a class="sidebar-brand text-decoration-none" href="/bofa/admin/dashboard.php">
        <div class="sidebar-brand-icon" style="background: var(--bofa-rouge);">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div class="sidebar-brand-text">
            <span class="brand-name">BofA<span style="color:var(--bofa-rouge);">DD</span></span>
            <span class="brand-sub">Administration</span>
        </div>
    </a>

    <!-- Navigation principale -->
    <ul class="sidebar-nav" role="menubar">

        <li class="nav-section-title">Tableau de bord</li>

        <!-- Tableau de bord -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('dashboard') ?>"
               href="/bofa/admin/dashboard.php"
               role="menuitem"
               title="Tableau de bord">
                <i class="sidebar-icon fa-solid fa-gauge-high"></i>
                <span class="sidebar-label">Tableau de bord</span>
            </a>
        </li>

        <li class="nav-section-title">Gestion</li>

        <!-- Utilisateurs -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('users') ?>"
               href="/bofa/admin/users.php"
               role="menuitem"
               title="Gestion des utilisateurs">
                <i class="sidebar-icon fa-solid fa-users"></i>
                <span class="sidebar-label">Utilisateurs</span>
            </a>
        </li>

        <!-- Configuration des risques -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('config-risques') ?>"
               href="/bofa/admin/config-risques.php"
               role="menuitem"
               title="Configuration des risques">
                <i class="sidebar-icon fa-solid fa-sliders"></i>
                <span class="sidebar-label">Config. Risques</span>
            </a>
        </li>

        <!-- Étiquettes / Tags -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('tags') ?>"
               href="/bofa/admin/tags.php"
               role="menuitem"
               title="Tags et étiquettes">
                <i class="sidebar-icon fa-solid fa-tags"></i>
                <span class="sidebar-label">Tags</span>
            </a>
        </li>

        <!-- Règles métier -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('regles') ?>"
               href="/bofa/admin/regles.php"
               role="menuitem"
               title="Règles métier automatiques">
                <i class="sidebar-icon fa-solid fa-code-branch"></i>
                <span class="sidebar-label">Règles métier</span>
            </a>
        </li>

        <li class="nav-section-title">Surveillance</li>

        <!-- Journal d'audit -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('audit') ?>"
               href="/bofa/admin/audit.php"
               role="menuitem"
               title="Journal d'audit">
                <i class="sidebar-icon fa-solid fa-scroll"></i>
                <span class="sidebar-label">Journal d'audit</span>
            </a>
        </li>

        <!-- Rapports -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('rapports') ?>"
               href="/bofa/admin/rapports.php"
               role="menuitem"
               title="Rapports et exports">
                <i class="sidebar-icon fa-solid fa-chart-pie"></i>
                <span class="sidebar-label">Rapports</span>
            </a>
        </li>

        <!-- Performances -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('performances') ?>"
               href="/bofa/admin/performances.php"
               role="menuitem"
               title="Indicateurs de performance">
                <i class="sidebar-icon fa-solid fa-chart-line"></i>
                <span class="sidebar-label">Performances</span>
            </a>
        </li>

        <li class="nav-section-title">Intégration</li>

        <!-- Tokens API -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('api-tokens') ?>"
               href="/bofa/admin/api-tokens.php"
               role="menuitem"
               title="Jetons d'accès API">
                <i class="sidebar-icon fa-solid fa-key"></i>
                <span class="sidebar-label">API Tokens</span>
            </a>
        </li>

        <li class="nav-section-title">Compte</li>

        <!-- Profil -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('profil') ?>"
               href="/bofa/admin/profil.php"
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
        <a href="/bofa/admin/notifications.php"
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
