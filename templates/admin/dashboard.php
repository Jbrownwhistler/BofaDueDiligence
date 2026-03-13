<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-tachometer-alt me-2 text-bofa-red"></i>Tableau de bord Administration</h4>

<!-- Compliance Fortress Banner -->
<div class="alert alert-dark border-start border-4 border-bofa-red mb-4" style="border-left-color: var(--bofa-red) !important; background: linear-gradient(135deg, #0a1628 0%, #012169 100%); color: white;">
    <div class="d-flex align-items-center">
        <i class="fas fa-shield-halved fa-2x me-3" style="color: var(--bofa-red)"></i>
        <div>
            <strong>Forteresse de Conformité BofA</strong>
            <div class="small mt-1 opacity-75">
                <span class="me-3"><i class="fas fa-folder-open me-1"></i><?= $totalCases ?> dossiers</span>
                <span class="me-3"><i class="fas fa-exclamation-triangle me-1"></i><?= count($overdue) ?> en retard</span>
                <span class="me-3"><i class="fas fa-users me-1"></i><?= array_sum($userCounts) ?> utilisateurs</span>
                <span><i class="fas fa-shield-halved me-1"></i><?= $highRiskCount ?> haut risque</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: var(--bofa-navy)">
            <div class="stat-label">Total dossiers</div>
            <div class="stat-value text-bofa-navy"><?= $totalCases ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #198754">
            <div class="stat-label">Montant total</div>
            <div class="stat-value text-success" style="font-size:1.3rem">$<?= number_format($totalAmount, 0) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #dc3545">
            <div class="stat-label">Dossiers en retard</div>
            <div class="stat-value text-danger"><?= count($overdue) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #6f42c1">
            <div class="stat-label">Utilisateurs</div>
            <div class="stat-value" style="color:#6f42c1"><?= array_sum($userCounts) ?></div>
            <small class="text-muted"><?= $userCounts['client'] ?? 0 ?> clients, <?= $userCounts['agent'] ?? 0 ?> agents</small>
        </div>
    </div>
</div>

<!-- Compliance KPIs Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #dc3545">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Dossiers haut risque</div>
                    <div class="stat-value text-danger"><?= $highRiskCount ?></div>
                    <small class="text-muted">Score ≥ 7.0</small>
                </div>
                <div class="stat-icon text-danger"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #ffc107">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Attente validation</div>
                    <div class="stat-value text-warning"><?= $statusCounts['en_attente_validation'] ?? 0 ?></div>
                    <small class="text-muted">Superviseur requis</small>
                </div>
                <div class="stat-icon text-warning"><i class="fas fa-user-shield"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #6c757d">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Fonds gelés</div>
                    <div class="stat-value" style="color:#6c757d"><?= $frozenCount ?></div>
                    <small class="text-muted">$<?= number_format($frozenAmount, 0) ?></small>
                </div>
                <div class="stat-icon" style="color:#6c757d"><i class="fas fa-snowflake"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #0dcaf0">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Documents en attente</div>
                    <div class="stat-value text-info"><?= $pendingDocsCount ?></div>
                    <small class="text-muted">À valider</small>
                </div>
                <div class="stat-icon text-info"><i class="fas fa-file-circle-question"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Admin Quick Actions -->
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-bolt me-2"></i>Actions rapides — Conformité</div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>admin/compliance-overview" class="btn btn-outline-danger w-100"><i class="fas fa-shield-halved me-1"></i> Vue conformité</a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>admin/kyc-management" class="btn btn-outline-primary w-100"><i class="fas fa-user-check me-1"></i> Gestion KYC</a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>admin/client-risk-overview" class="btn btn-outline-warning w-100"><i class="fas fa-chart-pie me-1"></i> Risques clients</a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>admin/banking-services" class="btn btn-outline-info w-100"><i class="fas fa-th me-1"></i> Services bancaires</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Dossiers par statut</div>
            <div class="card-body"><canvas id="statusChart" height="250"></canvas></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header text-danger"><i class="fas fa-clock me-2"></i>Dossiers en retard</div>
            <div class="card-body p-0">
                <?php if (empty($overdue)): ?>
                    <div class="text-center text-muted p-4"><i class="fas fa-check-circle text-success"></i> Aucun retard</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($overdue, 0, 8) as $o): ?>
                        <a href="<?= BASE_URL ?>admin/case?id=<?= $o['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <strong><?= htmlspecialchars($o['case_id_unique']) ?></strong>
                                <span class="badge bg-danger"><?= date('d/m', strtotime($o['date_limite'])) ?></span>
                            </div>
                            <small><?= htmlspecialchars($o['client_name'] ?? '') ?> — $<?= number_format($o['montant'], 2) ?></small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['En analyse','Docs demandés','Attente validation','Validé','Prêt transfert','Rejeté','Gelé'],
            datasets: [{
                data: [<?= $statusCounts['en_analyse'] ?? 0 ?>,<?= $statusCounts['documents_demandes'] ?? 0 ?>,<?= $statusCounts['en_attente_validation'] ?? 0 ?>,<?= $statusCounts['valide'] ?? 0 ?>,<?= $statusCounts['pret_pour_transfert'] ?? 0 ?>,<?= $statusCounts['rejete'] ?? 0 ?>,<?= $statusCounts['gele'] ?? 0 ?>],
                backgroundColor: ['#0dcaf0','#ffc107','#6f42c1','#198754','#20c997','#dc3545','#6c757d']
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
});
</script>
