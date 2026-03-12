<?php
$currentPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

function sidebarActive(string $path): string {
    global $currentPath;
    return str_starts_with($currentPath, $path) ? 'active' : '';
}
?>
<nav id="sidebar" class="bg-bofa-dark text-white">
    <div class="sidebar-header p-3 text-center border-bottom border-secondary">
        <small class="text-uppercase text-muted ls-1">
            <?php if (Auth::isAdmin()): ?>
                Administration
            <?php elseif (Auth::isAgent()): ?>
                Conformité AML
            <?php else: ?>
                Espace Client
            <?php endif; ?>
        </small>
    </div>

    <ul class="nav flex-column p-2">
        <?php if (Auth::isClient()): ?>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/dashboard') ?>" href="<?= BASE_URL ?>client/dashboard">
                    <i class="fas fa-tachometer-alt me-2"></i> Tableau de bord
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/pending') ?>" href="<?= BASE_URL ?>client/pending">
                    <i class="fas fa-hourglass-half me-2"></i> Fonds en attente
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/history') ?>" href="<?= BASE_URL ?>client/history">
                    <i class="fas fa-history me-2"></i> Historique transferts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/profile') ?>" href="<?= BASE_URL ?>client/profile">
                    <i class="fas fa-user me-2"></i> Mon profil
                </a>
            </li>

        <?php elseif (Auth::isAgent()): ?>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('agent/dashboard') ?>" href="<?= BASE_URL ?>agent/dashboard">
                    <i class="fas fa-tachometer-alt me-2"></i> Tableau de bord
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('agent/cases') ?>" href="<?= BASE_URL ?>agent/cases">
                    <i class="fas fa-folder-open me-2"></i> Dossiers AML
                </a>
            </li>

        <?php elseif (Auth::isAdmin()): ?>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('admin/dashboard') ?>" href="<?= BASE_URL ?>admin/dashboard">
                    <i class="fas fa-tachometer-alt me-2"></i> Tableau de bord
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('admin/cases') ?>" href="<?= BASE_URL ?>admin/cases">
                    <i class="fas fa-folder-open me-2"></i> Tous les dossiers
                </a>
            </li>

            <li class="sidebar-divider"></li>
            <li class="sidebar-section-title">Gestion</li>

            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('admin/users') ?>" href="<?= BASE_URL ?>admin/users">
                    <i class="fas fa-users me-2"></i> Utilisateurs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('admin/risk-countries') ?>" href="<?= BASE_URL ?>admin/risk-countries">
                    <i class="fas fa-globe me-2"></i> Pays à risque
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('admin/risk-assets') ?>" href="<?= BASE_URL ?>admin/risk-assets">
                    <i class="fas fa-coins me-2"></i> Types d'actifs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('admin/settings') ?>" href="<?= BASE_URL ?>admin/settings">
                    <i class="fas fa-cog me-2"></i> Paramètres
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('admin/audit') ?>" href="<?= BASE_URL ?>admin/audit">
                    <i class="fas fa-clipboard-list me-2"></i> Journal d'audit
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer p-3 text-center border-top border-secondary mt-auto">
        <small class="text-muted"><?= APP_NAME ?> v<?= APP_VERSION ?></small>
    </div>
</nav>
