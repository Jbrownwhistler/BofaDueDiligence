<meta name="base-url" content="<?= BASE_URL ?>">

<h4 class="mb-4"><i class="fas fa-tachometer-alt me-2 text-bofa-red"></i>Tableau de bord <?= Auth::isAdmin() ? 'Superviseur' : 'Agent' ?></h4>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: var(--bofa-navy)">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total dossiers</div>
                    <div class="stat-value text-bofa-navy"><?= $totalCases ?></div>
                </div>
                <div class="stat-icon text-bofa-navy"><i class="fas fa-folder-open"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #ffc107">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">En analyse</div>
                    <div class="stat-value text-warning"><?= $statusCounts['en_analyse'] ?? 0 ?></div>
                </div>
                <div class="stat-icon text-warning"><i class="fas fa-search"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #dc3545">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">En retard</div>
                    <div class="stat-value text-danger"><?= count($overdue) ?></div>
                </div>
                <div class="stat-icon text-danger"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #198754">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Montant total</div>
                    <div class="stat-value text-success" style="font-size:1.3rem">$<?= number_format($totalAmount, 0) ?></div>
                </div>
                <div class="stat-icon text-success"><i class="fas fa-dollar-sign"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Chart -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Répartition par statut</div>
            <div class="card-body">
                <canvas id="statusChart" height="250"></canvas>
            </div>
        </div>
    </div>

    <!-- Overdue cases -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header text-danger"><i class="fas fa-clock me-2"></i>Dossiers en retard</div>
            <div class="card-body p-0">
                <?php if (empty($overdue)): ?>
                    <div class="text-center text-muted p-4"><i class="fas fa-check-circle text-success"></i> Aucun dossier en retard</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($overdue, 0, 5) as $o): ?>
                        <a href="<?= BASE_URL ?>agent/case?id=<?= $o['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <strong><?= htmlspecialchars($o['case_id_unique']) ?></strong>
                                <span class="badge bg-danger">Échéance: <?= date('d/m', strtotime($o['date_limite'])) ?></span>
                            </div>
                            <small class="text-muted"><?= htmlspecialchars($o['client_name'] ?? '') ?> — $<?= number_format($o['montant'], 2) ?></small>
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
    const ctx = document.getElementById('statusChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['En analyse', 'Docs demandés', 'En attente validation', 'Validé', 'Prêt transfert', 'Rejeté', 'Gelé'],
                datasets: [{
                    data: [
                        <?= $statusCounts['en_analyse'] ?? 0 ?>,
                        <?= $statusCounts['documents_demandes'] ?? 0 ?>,
                        <?= $statusCounts['en_attente_validation'] ?? 0 ?>,
                        <?= $statusCounts['valide'] ?? 0 ?>,
                        <?= $statusCounts['pret_pour_transfert'] ?? 0 ?>,
                        <?= $statusCounts['rejete'] ?? 0 ?>,
                        <?= $statusCounts['gele'] ?? 0 ?>
                    ],
                    backgroundColor: ['#0dcaf0','#ffc107','#6f42c1','#198754','#20c997','#dc3545','#6c757d']
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }
});
</script>
