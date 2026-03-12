<?php
/**
 * Barre latérale — rôle agent — BofaDueDiligence
 * Variables attendues (optionnelles) :
 *   $notifCount  (int)    — nombre de notifications non lues
 *   $currentPage (string) — identifiant de la page active
 */
defined('BOFA_APP') || die('Accès direct interdit.');

$notifCount  = isset($notifCount)  ? (int) $notifCount  : 0;
$currentPage = $currentPage ?? '';

$actif = fn(string $page): string => $currentPage === $page ? ' active' : '';
?>
<aside class="bofa-sidebar" id="bofa-sidebar" role="navigation" aria-label="Menu agent">

    <!-- Marque / logo -->
    <a class="sidebar-brand text-decoration-none" href="/bofa/agent/dashboard.php">
        <div class="sidebar-brand-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div class="sidebar-brand-text">
            <span class="brand-name">BofA<span style="color:var(--bofa-rouge);">DD</span></span>
            <span class="brand-sub">Espace Agent</span>
        </div>
    </a>

    <!-- Navigation principale -->
    <ul class="sidebar-nav" role="menubar">

        <li class="nav-section-title">Navigation</li>

        <!-- Tableau de bord -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('dashboard') ?>"
               href="/bofa/agent/dashboard.php"
               role="menuitem"
               title="Tableau de bord">
                <i class="sidebar-icon fa-solid fa-gauge-high"></i>
                <span class="sidebar-label">Tableau de bord</span>
            </a>
        </li>

        <!-- Dossiers AML/EDD -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('dossiers') ?>"
               href="/bofa/agent/dossiers.php"
               role="menuitem"
               title="Dossiers AML/EDD">
                <i class="sidebar-icon fa-solid fa-briefcase"></i>
                <span class="sidebar-label">Dossiers</span>
            </a>
        </li>

        <!-- Checklist de conformité -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('checklist') ?>"
               href="/bofa/agent/checklist.php"
               role="menuitem"
               title="Checklist de conformité">
                <i class="sidebar-icon fa-solid fa-list-check"></i>
                <span class="sidebar-label">Checklist</span>
            </a>
        </li>

        <!-- Notes de travail -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('notes') ?>"
               href="/bofa/agent/notes.php"
               role="menuitem"
               title="Notes">
                <i class="sidebar-icon fa-regular fa-note-sticky"></i>
                <span class="sidebar-label">Notes</span>
            </a>
        </li>

        <!-- Rappels / échéances -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('rappels') ?>"
               href="/bofa/agent/rappels.php"
               role="menuitem"
               title="Rappels">
                <i class="sidebar-icon fa-regular fa-calendar-check"></i>
                <span class="sidebar-label">Rappels</span>
            </a>
        </li>

        <li class="nav-section-title">Compte</li>

        <!-- Profil -->
        <li class="sidebar-item" role="none">
            <a class="sidebar-link<?= $actif('profil') ?>"
               href="/bofa/agent/profil.php"
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
        <a href="/bofa/agent/notifications.php"
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
