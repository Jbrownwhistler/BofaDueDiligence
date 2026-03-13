<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-chart-bar me-2 text-bofa-red"></i>Rapports</h4>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: var(--bofa-navy)">
            <div class="stat-label">Total dossiers</div>
            <div class="stat-value text-bofa-navy"><?= count($cases) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #198754">
            <div class="stat-label">Transferts effectués</div>
            <div class="stat-value text-success"><?= count($transfers) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #ffc107">
            <?php
            $totalAmount = 0;
            foreach ($cases as $c) $totalAmount += $c['montant'];
            ?>
            <div class="stat-label">Volume total</div>
            <div class="stat-value text-warning" style="font-size:1.3rem">$<?= number_format($totalAmount, 2) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #6f42c1">
            <div class="stat-label">Solde</div>
            <div class="stat-value" style="color:#6f42c1;font-size:1.3rem">$<?= number_format($account['solde'] ?? 0, 2) ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Répartition par statut</div>
            <div class="card-body"><canvas id="reportStatusChart" height="250"></canvas></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Répartition par type d'actif</div>
            <div class="card-body"><canvas id="reportAssetChart" height="250"></canvas></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php
    $statusCounts = [];
    $assetCounts = [];
    foreach ($cases as $c) {
        $s = $c['statut'];
        $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
        $a = $c['type_actif'];
        $assetCounts[$a] = ($assetCounts[$a] ?? 0) + 1;
    }
    ?>
    new Chart(document.getElementById('reportStatusChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($s) => CaseModel::getStatusLabel($s), array_keys($statusCounts))) ?>,
            datasets: [{
                data: <?= json_encode(array_values($statusCounts)) ?>,
                backgroundColor: ['#0dcaf0','#ffc107','#6f42c1','#198754','#20c997','#dc3545','#6c757d']
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('reportAssetChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($assetCounts)) ?>,
            datasets: [{
                label: 'Transactions',
                data: <?= json_encode(array_values($assetCounts)) ?>,
                backgroundColor: '#012169'
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
});
</script>
