<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-shield-halved me-2 text-bofa-red"></i>Vue d'ensemble Conformité</h4>

<!-- Risk Distribution Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #198754">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Risque faible</div>
                    <div class="stat-value text-success"><?= count($lowRiskCases) ?></div>
                    <small class="text-muted">Score &lt; 3.0</small>
                </div>
                <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #ffc107">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Risque modéré</div>
                    <div class="stat-value text-warning"><?= count($mediumRiskCases) ?></div>
                    <small class="text-muted">Score 3.0 - 7.0</small>
                </div>
                <div class="stat-icon text-warning"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #dc3545">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Risque élevé</div>
                    <div class="stat-value text-danger"><?= count($highRiskCases) ?></div>
                    <small class="text-muted">Score ≥ 7.0</small>
                </div>
                <div class="stat-icon text-danger"><i class="fas fa-exclamation-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #6c757d">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Fonds gelés</div>
                    <div class="stat-value" style="color:#6c757d"><?= count($frozenCases) ?></div>
                    <small class="text-muted">Gel préventif</small>
                </div>
                <div class="stat-icon" style="color:#6c757d"><i class="fas fa-snowflake"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card" style="border-left-color: #6f42c1">
            <div class="stat-label">Attente validation superviseur</div>
            <div class="stat-value" style="color:#6f42c1"><?= count($pendingValidation) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card" style="border-left-color: #0dcaf0">
            <div class="stat-label">Documents en attente</div>
            <div class="stat-value text-info"><?= $pendingDocsCount ?> / <?= $totalDocuments ?></div>
            <small class="text-muted"><?= $validatedDocs ?> validé(s)</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card" style="border-left-color: #dc3545">
            <div class="stat-label">Dossiers en retard</div>
            <div class="stat-value text-danger"><?= count($overdue) ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Distribution des risques</div>
            <div class="card-body"><canvas id="riskChart" height="250"></canvas></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header text-danger"><i class="fas fa-exclamation-circle me-2"></i>Dossiers à haut risque</div>
            <div class="card-body p-0">
                <?php if (empty($highRiskCases)): ?>
                    <div class="text-center text-muted p-4"><i class="fas fa-check-circle text-success"></i> Aucun dossier à haut risque</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Dossier</th><th>Client</th><th>Montant</th><th>Score</th><th>Statut</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($highRiskCases, 0, 10) as $c): ?>
                                <tr>
                                    <td><a href="<?= BASE_URL ?>admin/case?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['case_id_unique']) ?></a></td>
                                    <td><?= htmlspecialchars($c['client_name'] ?? '') ?></td>
                                    <td>$<?= number_format($c['montant'], 2) ?></td>
                                    <td><span class="badge bg-danger"><?= number_format($c['score_risque'], 2) ?></span></td>
                                    <td><span class="badge-status status-<?= $c['statut'] ?>"><?= CaseModel::getStatusLabel($c['statut']) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($pendingValidation)): ?>
<div class="card mt-4">
    <div class="card-header text-warning"><i class="fas fa-user-shield me-2"></i>En attente de validation superviseur</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Dossier</th><th>Client</th><th>Montant</th><th>Score</th><th>Agent</th><th>Date limite</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($pendingValidation as $c): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['case_id_unique']) ?></strong></td>
                        <td><?= htmlspecialchars($c['client_name'] ?? '') ?></td>
                        <td>$<?= number_format($c['montant'], 2) ?></td>
                        <td><span class="badge bg-danger"><?= number_format($c['score_risque'], 2) ?></span></td>
                        <td><?= htmlspecialchars($c['agent_name'] ?? 'Non assigné') ?></td>
                        <td><?= $c['date_limite'] ? date('d/m/Y', strtotime($c['date_limite'])) : 'N/A' ?></td>
                        <td><a href="<?= BASE_URL ?>admin/case?id=<?= $c['id'] ?>" class="btn btn-sm btn-bofa"><i class="fas fa-eye"></i> Examiner</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('riskChart'), {
        type: 'doughnut',
        data: {
            labels: ['Risque faible (<3)', 'Risque modéré (3-7)', 'Risque élevé (≥7)'],
            datasets: [{
                data: [<?= count($lowRiskCases) ?>, <?= count($mediumRiskCases) ?>, <?= count($highRiskCases) ?>],
                backgroundColor: ['#198754', '#ffc107', '#dc3545']
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
});
</script>
