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

            <li class="sidebar-divider"></li>
            <li class="sidebar-section-title">Comptes & Finances</li>

            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/account-summary') ?>" href="<?= BASE_URL ?>client/account-summary">
                    <i class="fas fa-chart-line me-2"></i> Synthèse du compte
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/pending') ?>" href="<?= BASE_URL ?>client/pending">
                    <i class="fas fa-hourglass-half me-2"></i> Fonds en attente
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/history') ?>" href="<?= BASE_URL ?>client/history">
                    <i class="fas fa-exchange-alt me-2"></i> Virements & Transferts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/beneficiaries') ?>" href="<?= BASE_URL ?>client/beneficiaries">
                    <i class="fas fa-users me-2"></i> Bénéficiaires
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/statements') ?>" href="<?= BASE_URL ?>client/statements">
                    <i class="fas fa-file-invoice-dollar me-2"></i> Relevés de compte
                </a>
            </li>

            <li class="sidebar-divider"></li>
            <li class="sidebar-section-title">Conformité & Risque</li>

            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/compliance-center') ?>" href="<?= BASE_URL ?>client/compliance-center">
                    <i class="fas fa-shield-halved me-2"></i> Centre de conformité
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/kyc-verification') ?>" href="<?= BASE_URL ?>client/kyc-verification">
                    <i class="fas fa-user-check me-2"></i> Vérification KYC
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/risk-profile') ?>" href="<?= BASE_URL ?>client/risk-profile">
                    <i class="fas fa-chart-pie me-2"></i> Profil de risque
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/transaction-monitoring') ?>" href="<?= BASE_URL ?>client/transaction-monitoring">
                    <i class="fas fa-satellite-dish me-2"></i> Suivi des transactions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/regulatory-alerts') ?>" href="<?= BASE_URL ?>client/regulatory-alerts">
                    <i class="fas fa-bell me-2"></i> Alertes réglementaires
                </a>
            </li>

            <li class="sidebar-divider"></li>
            <li class="sidebar-section-title">Documents & Rapports</li>

            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/document-vault') ?>" href="<?= BASE_URL ?>client/document-vault">
                    <i class="fas fa-vault me-2"></i> Coffre-fort documents
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/tax-documents') ?>" href="<?= BASE_URL ?>client/tax-documents">
                    <i class="fas fa-landmark me-2"></i> Documents fiscaux
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/declarations') ?>" href="<?= BASE_URL ?>client/declarations">
                    <i class="fas fa-file-signature me-2"></i> Déclarations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/reports') ?>" href="<?= BASE_URL ?>client/reports">
                    <i class="fas fa-chart-bar me-2"></i> Rapports
                </a>
            </li>

            <li class="sidebar-divider"></li>
            <li class="sidebar-section-title">Communication</li>

            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/secure-messages') ?>" href="<?= BASE_URL ?>client/secure-messages">
                    <i class="fas fa-envelope-open-text me-2"></i> Messagerie sécurisée
                </a>
            </li>

            <li class="sidebar-divider"></li>
            <li class="sidebar-section-title">Paramètres</li>

            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/compliance-training') ?>" href="<?= BASE_URL ?>client/compliance-training">
                    <i class="fas fa-graduation-cap me-2"></i> Formation conformité
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/security-settings') ?>" href="<?= BASE_URL ?>client/security-settings">
                    <i class="fas fa-lock me-2"></i> Sécurité du compte
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/profile') ?>" href="<?= BASE_URL ?>client/profile">
                    <i class="fas fa-user me-2"></i> Mon profil
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/activity-log') ?>" href="<?= BASE_URL ?>client/activity-log">
                    <i class="fas fa-clipboard-list me-2"></i> Journal d'activité
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('client/help-support') ?>" href="<?= BASE_URL ?>client/help-support">
                    <i class="fas fa-life-ring me-2"></i> Aide & Support
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
            <li class="sidebar-section-title">Conformité</li>

            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('admin/compliance-overview') ?>" href="<?= BASE_URL ?>admin/compliance-overview">
                    <i class="fas fa-shield-halved me-2"></i> Vue conformité
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('admin/kyc-management') ?>" href="<?= BASE_URL ?>admin/kyc-management">
                    <i class="fas fa-user-check me-2"></i> Gestion KYC
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('admin/client-risk-overview') ?>" href="<?= BASE_URL ?>admin/client-risk-overview">
                    <i class="fas fa-chart-pie me-2"></i> Risques clients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= sidebarActive('admin/banking-services') ?>" href="<?= BASE_URL ?>admin/banking-services">
                    <i class="fas fa-th me-2"></i> Services bancaires
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
