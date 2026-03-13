<meta name="base-url" content="<?= BASE_URL ?>">

<h4 class="mb-4"><i class="fas fa-tachometer-alt me-2 text-bofa-red"></i>Tableau de bord</h4>

<!-- Compliance Alert Banner -->
<?php
$frozenCases = array_filter($cases, fn($c) => $c['statut_fonds'] === 'gele');
$docRequired = array_filter($cases, fn($c) => $c['statut'] === 'documents_demandes');
?>
<?php if (!empty($frozenCases) || !empty($docRequired)): ?>
<div class="alert alert-warning border-start border-4 border-warning mb-4">
    <div class="d-flex align-items-center">
        <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
        <div>
            <strong>Alertes de conformité</strong>
            <div class="small mt-1">
                <?php if (!empty($frozenCases)): ?>
                    <span class="me-3"><i class="fas fa-snowflake me-1"></i><?= count($frozenCases) ?> fonds gelé(s) — action requise</span>
                <?php endif; ?>
                <?php if (!empty($docRequired)): ?>
                    <span><i class="fas fa-file-alt me-1"></i><?= count($docRequired) ?> dossier(s) nécessitent des documents</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: var(--bofa-navy)">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Solde disponible</div>
                    <div class="stat-value text-bofa-navy">$<?= number_format($account['solde'] ?? 0, 2) ?></div>
                    <small class="text-muted"><?= $account['numero_compte_principal'] ?? 'N/A' ?></small>
                </div>
                <div class="stat-icon text-bofa-navy"><i class="fas fa-wallet"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #ffc107">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Fonds en attente</div>
                    <div class="stat-value text-warning"><?= $pendingCount ?></div>
                    <small class="text-muted">$<?= number_format($pendingAmount, 2) ?> bloqués</small>
                </div>
                <div class="stat-icon text-warning"><i class="fas fa-hourglass-half"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #198754">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Prêts à transférer</div>
                    <div class="stat-value text-success"><?= $readyCount ?></div>
                    <small class="text-muted">Cliquez pour transférer</small>
                </div>
                <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: <?= $complianceScore >= 80 ? '#198754' : ($complianceScore >= 50 ? '#ffc107' : '#dc3545') ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Score de conformité</div>
                    <div class="stat-value" style="color: <?= $complianceScore >= 80 ? '#198754' : ($complianceScore >= 50 ? '#ffc107' : '#dc3545') ?>"><?= $complianceScore ?>%</div>
                    <small class="text-muted"><?= $complianceScore >= 80 ? 'Conforme' : ($complianceScore >= 50 ? 'À améliorer' : 'Action requise') ?></small>
                </div>
                <div class="stat-icon" style="color: <?= $complianceScore >= 80 ? '#198754' : ($complianceScore >= 50 ? '#ffc107' : '#dc3545') ?>"><i class="fas fa-shield-halved"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Banking Services Grid - 20 Online Banking Options -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-th me-2"></i>Services bancaires en ligne</span>
        <span class="badge bg-bofa-navy">20 services disponibles</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <!-- 1. Synthèse du compte -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/account-summary" class="banking-tile">
                    <div class="banking-tile-icon bg-navy-light"><i class="fas fa-chart-line"></i></div>
                    <div class="banking-tile-label">Synthèse du compte</div>
                    <div class="banking-tile-desc">Vue d'ensemble détaillée</div>
                </a>
            </div>
            <!-- 2. Fonds en attente -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/pending" class="banking-tile">
                    <div class="banking-tile-icon bg-warning-light"><i class="fas fa-hourglass-half"></i></div>
                    <div class="banking-tile-label">Fonds en attente</div>
                    <div class="banking-tile-desc"><?= $pendingCount ?> dossier(s) en cours</div>
                    <?php if ($pendingCount > 0): ?><span class="banking-tile-badge bg-warning"><?= $pendingCount ?></span><?php endif; ?>
                </a>
            </div>
            <!-- 3. Virements & Transferts -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/history" class="banking-tile">
                    <div class="banking-tile-icon bg-success-light"><i class="fas fa-exchange-alt"></i></div>
                    <div class="banking-tile-label">Virements & Transferts</div>
                    <div class="banking-tile-desc">Historique des opérations</div>
                </a>
            </div>
            <!-- 4. Centre de conformité -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/compliance-center" class="banking-tile">
                    <div class="banking-tile-icon bg-danger-light"><i class="fas fa-shield-halved"></i></div>
                    <div class="banking-tile-label">Centre de conformité</div>
                    <div class="banking-tile-desc">Statut AML/KYC</div>
                    <?php if ($complianceScore < 80): ?><span class="banking-tile-badge bg-danger">!</span><?php endif; ?>
                </a>
            </div>
            <!-- 5. Vérification KYC -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/kyc-verification" class="banking-tile">
                    <div class="banking-tile-icon bg-info-light"><i class="fas fa-user-check"></i></div>
                    <div class="banking-tile-label">Vérification KYC</div>
                    <div class="banking-tile-desc">Identité & documents</div>
                </a>
            </div>
            <!-- 6. Profil de risque -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/risk-profile" class="banking-tile">
                    <div class="banking-tile-icon bg-purple-light"><i class="fas fa-chart-pie"></i></div>
                    <div class="banking-tile-label">Profil de risque</div>
                    <div class="banking-tile-desc">Évaluation personnelle</div>
                </a>
            </div>
            <!-- 7. Coffre-fort documents -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/document-vault" class="banking-tile">
                    <div class="banking-tile-icon bg-teal-light"><i class="fas fa-vault"></i></div>
                    <div class="banking-tile-label">Coffre-fort documents</div>
                    <div class="banking-tile-desc">Stockage sécurisé</div>
                    <?php if (!empty($docRequired)): ?><span class="banking-tile-badge bg-warning"><?= count($docRequired) ?></span><?php endif; ?>
                </a>
            </div>
            <!-- 8. Messagerie sécurisée -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/secure-messages" class="banking-tile">
                    <div class="banking-tile-icon bg-indigo-light"><i class="fas fa-envelope-open-text"></i></div>
                    <div class="banking-tile-label">Messagerie sécurisée</div>
                    <div class="banking-tile-desc">Communication chiffrée</div>
                    <?php if ($unreadMessages > 0): ?><span class="banking-tile-badge bg-danger"><?= $unreadMessages ?></span><?php endif; ?>
                </a>
            </div>
            <!-- 9. Gestion des bénéficiaires -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/beneficiaries" class="banking-tile">
                    <div class="banking-tile-icon bg-orange-light"><i class="fas fa-users"></i></div>
                    <div class="banking-tile-label">Bénéficiaires</div>
                    <div class="banking-tile-desc">Gestion des destinataires</div>
                </a>
            </div>
            <!-- 10. Relevés de compte -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/statements" class="banking-tile">
                    <div class="banking-tile-icon bg-slate-light"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="banking-tile-label">Relevés de compte</div>
                    <div class="banking-tile-desc">Relevés mensuels</div>
                </a>
            </div>
            <!-- 11. Alertes réglementaires -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/regulatory-alerts" class="banking-tile">
                    <div class="banking-tile-icon bg-red-light"><i class="fas fa-bell"></i></div>
                    <div class="banking-tile-label">Alertes réglementaires</div>
                    <div class="banking-tile-desc">Notifications compliance</div>
                    <?php if ($alertCount > 0): ?><span class="banking-tile-badge bg-danger"><?= $alertCount ?></span><?php endif; ?>
                </a>
            </div>
            <!-- 12. Suivi des transactions -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/transaction-monitoring" class="banking-tile">
                    <div class="banking-tile-icon bg-cyan-light"><i class="fas fa-radar"></i></div>
                    <div class="banking-tile-label">Suivi des transactions</div>
                    <div class="banking-tile-desc">Monitoring en temps réel</div>
                </a>
            </div>
            <!-- 13. Journal d'activité -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/activity-log" class="banking-tile">
                    <div class="banking-tile-icon bg-gray-light"><i class="fas fa-clipboard-list"></i></div>
                    <div class="banking-tile-label">Journal d'activité</div>
                    <div class="banking-tile-desc">Historique des actions</div>
                </a>
            </div>
            <!-- 14. Documents fiscaux -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/tax-documents" class="banking-tile">
                    <div class="banking-tile-icon bg-emerald-light"><i class="fas fa-landmark"></i></div>
                    <div class="banking-tile-label">Documents fiscaux</div>
                    <div class="banking-tile-desc">Déclarations & attestations</div>
                </a>
            </div>
            <!-- 15. Centre de déclarations -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/declarations" class="banking-tile">
                    <div class="banking-tile-icon bg-amber-light"><i class="fas fa-file-signature"></i></div>
                    <div class="banking-tile-label">Centre de déclarations</div>
                    <div class="banking-tile-desc">Déclarations obligatoires</div>
                </a>
            </div>
            <!-- 16. Rapports -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/reports" class="banking-tile">
                    <div class="banking-tile-icon bg-blue-light"><i class="fas fa-chart-bar"></i></div>
                    <div class="banking-tile-label">Rapports</div>
                    <div class="banking-tile-desc">Rapports & analyses</div>
                </a>
            </div>
            <!-- 17. Formation conformité -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/compliance-training" class="banking-tile">
                    <div class="banking-tile-icon bg-lime-light"><i class="fas fa-graduation-cap"></i></div>
                    <div class="banking-tile-label">Formation conformité</div>
                    <div class="banking-tile-desc">Modules AML/KYC</div>
                </a>
            </div>
            <!-- 18. Sécurité du compte -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/security-settings" class="banking-tile">
                    <div class="banking-tile-icon bg-dark-light"><i class="fas fa-lock"></i></div>
                    <div class="banking-tile-label">Sécurité du compte</div>
                    <div class="banking-tile-desc">2FA & paramètres</div>
                </a>
            </div>
            <!-- 19. Mon profil -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/profile" class="banking-tile">
                    <div class="banking-tile-icon bg-navy-light"><i class="fas fa-user-circle"></i></div>
                    <div class="banking-tile-label">Mon profil</div>
                    <div class="banking-tile-desc">Informations personnelles</div>
                </a>
            </div>
            <!-- 20. Aide & Support -->
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="<?= BASE_URL ?>client/help-support" class="banking-tile">
                    <div class="banking-tile-icon bg-teal-light"><i class="fas fa-life-ring"></i></div>
                    <div class="banking-tile-label">Aide & Support</div>
                    <div class="banking-tile-desc">FAQ & assistance</div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Cases -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-folder-open me-2"></i>Mes dossiers récents</span>
        <a href="<?= BASE_URL ?>client/pending" class="btn btn-sm btn-bofa-outline">Voir tout</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($cases)): ?>
            <div class="text-center text-muted p-4">Aucun dossier en cours.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID Dossier</th>
                            <th>Montant</th>
                            <th>Origine</th>
                            <th>Statut</th>
                            <th>Fonds</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($cases, 0, 5) as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['case_id_unique']) ?></strong></td>
                            <td>$<?= number_format($c['montant'], 2) ?></td>
                            <td><?= htmlspecialchars($c['pays_origine']) ?></td>
                            <td><span class="badge-status status-<?= $c['statut'] ?>"><?= CaseModel::getStatusLabel($c['statut']) ?></span></td>
                            <td><span class="badge-status fonds-<?= $c['statut_fonds'] ?>"><?= CaseModel::getFondsLabel($c['statut_fonds']) ?></span></td>
                            <td>
                                <a href="<?= BASE_URL ?>client/case?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($c['statut'] === 'pret_pour_transfert' && $c['statut_fonds'] === 'disponible'): ?>
                                    <form method="POST" action="<?= BASE_URL ?>client/transfer" class="d-inline">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="case_id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success" data-confirm="Confirmer le transfert de $<?= number_format($c['montant'], 2) ?> ?">
                                            <i class="fas fa-arrow-right"></i> Transférer
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
