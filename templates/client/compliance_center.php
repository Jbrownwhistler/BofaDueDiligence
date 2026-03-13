<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-shield-halved me-2 text-bofa-red"></i>Centre de conformité</h4>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center p-4">
            <div class="compliance-gauge mb-3">
                <div class="compliance-score-circle <?= $complianceScore >= 80 ? 'score-good' : ($complianceScore >= 50 ? 'score-warning' : 'score-danger') ?>">
                    <span class="display-4 fw-bold"><?= $complianceScore ?>%</span>
                </div>
            </div>
            <h5>Score de conformité</h5>
            <p class="text-muted small mb-0">
                <?php if ($complianceScore >= 80): ?>
                    <span class="text-success"><i class="fas fa-check-circle"></i> Niveau de conformité satisfaisant</span>
                <?php elseif ($complianceScore >= 50): ?>
                    <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Améliorations nécessaires</span>
                <?php else: ?>
                    <span class="text-danger"><i class="fas fa-times-circle"></i> Action immédiate requise</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Répartition des dossiers</div>
            <div class="card-body">
                <?php
                $allStatuses = ['en_analyse', 'documents_demandes', 'en_attente_validation', 'valide', 'pret_pour_transfert', 'rejete', 'gele'];
                foreach ($allStatuses as $s):
                    $count = $statusBreakdown[$s] ?? 0;
                    $total = count($cases);
                    $pct = $total > 0 ? round(($count / $total) * 100) : 0;
                ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small><span class="badge-status status-<?= $s ?>"><?= CaseModel::getStatusLabel($s) ?></span></small>
                        <small class="text-muted"><?= $count ?> (<?= $pct ?>%)</small>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" style="width: <?= $pct ?>%; background-color: <?= match($s) { 'en_analyse' => '#0dcaf0', 'documents_demandes' => '#ffc107', 'en_attente_validation' => '#6f42c1', 'valide' => '#198754', 'pret_pour_transfert' => '#20c997', 'rejete' => '#dc3545', 'gele' => '#6c757d', default => '#6c757d' } ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-tasks me-2"></i>Exigences de conformité AML</div>
            <div class="card-body">
                <div class="compliance-checklist">
                    <div class="checklist-item completed"><i class="fas fa-check-circle text-success me-2"></i> Création du compte vérifiée</div>
                    <div class="checklist-item <?= !empty($user['dernier_login']) ? 'completed' : '' ?>"><i class="fas fa-<?= !empty($user['dernier_login']) ? 'check-circle text-success' : 'circle text-muted' ?> me-2"></i> Connexion authentifiée</div>
                    <div class="checklist-item <?= count($cases) > 0 ? 'completed' : '' ?>"><i class="fas fa-<?= count($cases) > 0 ? 'check-circle text-success' : 'circle text-muted' ?> me-2"></i> Dossiers AML soumis</div>
                    <div class="checklist-item <?= $complianceScore >= 50 ? 'completed' : '' ?>"><i class="fas fa-<?= $complianceScore >= 50 ? 'check-circle text-success' : 'circle text-muted' ?> me-2"></i> Score de conformité ≥ 50%</div>
                    <div class="checklist-item <?= $complianceScore >= 80 ? 'completed' : '' ?>"><i class="fas fa-<?= $complianceScore >= 80 ? 'check-circle text-success' : 'circle text-muted' ?> me-2"></i> Score de conformité ≥ 80%</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Informations réglementaires</div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-gavel me-2"></i>
                    <strong>Réglementation AML/CFT</strong><br>
                    <small>Conformément aux exigences du Bank Secrecy Act (BSA), du USA PATRIOT Act et des directives FATF/GAFI, tous les dossiers de due diligence sont soumis à une vérification approfondie.</small>
                </div>
                <div class="alert alert-secondary mb-0">
                    <i class="fas fa-shield-halved me-2"></i>
                    <strong>Programme de conformité BofA</strong><br>
                    <small>Notre programme intègre les contrôles KYC (Know Your Customer), CDD (Customer Due Diligence) et EDD (Enhanced Due Diligence) pour garantir la sécurité de vos transactions.</small>
                </div>
            </div>
        </div>
    </div>
</div>
