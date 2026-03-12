<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-tachometer-alt me-2 text-bofa-red"></i>Tableau de bord Administration</h4>

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
