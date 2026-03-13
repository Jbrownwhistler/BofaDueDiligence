<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-chart-pie me-2 text-bofa-red"></i>Vue d'ensemble des risques clients</h4>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card" style="border-left-color: #198754">
            <div class="stat-label">Risque faible</div>
            <div class="stat-value text-success"><?= $riskDistribution['low'] ?></div>
            <small class="text-muted">Score &lt; 3.0</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card" style="border-left-color: #ffc107">
            <div class="stat-label">Risque modéré</div>
            <div class="stat-value text-warning"><?= $riskDistribution['medium'] ?></div>
            <small class="text-muted">Score 3.0 - 7.0</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card" style="border-left-color: #dc3545">
            <div class="stat-label">Risque élevé</div>
            <div class="stat-value text-danger"><?= $riskDistribution['high'] ?></div>
            <small class="text-muted">Score ≥ 7.0</small>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-globe me-2"></i>Risque par pays d'origine</div>
            <div class="card-body p-0">
                <?php if (empty($riskByCountry)): ?>
                    <div class="text-center text-muted p-4">Aucune donnée.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Pays</th><th>Dossiers</th><th>Score moyen</th><th>Montant total</th></tr></thead>
                            <tbody>
                                <?php foreach ($riskByCountry as $country => $data): ?>
                                <tr>
                                    <td><i class="fas fa-globe me-1"></i> <?= htmlspecialchars($country) ?></td>
                                    <td><span class="badge bg-secondary"><?= $data['count'] ?></span></td>
                                    <td>
                                        <?php $avg = $data['total_risk'] / $data['count']; ?>
                                        <span class="badge bg-<?= $avg < 3 ? 'success' : ($avg < 7 ? 'warning' : 'danger') ?>"><?= number_format($avg, 2) ?></span>
                                    </td>
                                    <td>$<?= number_format($data['total_amount'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-coins me-2"></i>Risque par type d'actif</div>
            <div class="card-body p-0">
                <?php if (empty($riskByAsset)): ?>
                    <div class="text-center text-muted p-4">Aucune donnée.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Type d'actif</th><th>Dossiers</th><th>Score moyen</th><th>Montant total</th></tr></thead>
                            <tbody>
                                <?php foreach ($riskByAsset as $asset => $data): ?>
                                <tr>
                                    <td><i class="fas fa-coins me-1"></i> <?= htmlspecialchars($asset) ?></td>
                                    <td><span class="badge bg-secondary"><?= $data['count'] ?></span></td>
                                    <td>
                                        <?php $avg = $data['total_risk'] / $data['count']; ?>
                                        <span class="badge bg-<?= $avg < 3 ? 'success' : ($avg < 7 ? 'warning' : 'danger') ?>"><?= number_format($avg, 2) ?></span>
                                    </td>
                                    <td>$<?= number_format($data['total_amount'], 2) ?></td>
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

<div class="card mt-4">
    <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Distribution des risques</div>
    <div class="card-body"><canvas id="riskDistChart" height="200"></canvas></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('riskDistChart'), {
        type: 'bar',
        data: {
            labels: ['Faible (<3)', 'Modéré (3-7)', 'Élevé (≥7)'],
            datasets: [{
                label: 'Nombre de dossiers',
                data: [<?= $riskDistribution['low'] ?>, <?= $riskDistribution['medium'] ?>, <?= $riskDistribution['high'] ?>],
                backgroundColor: ['#198754', '#ffc107', '#dc3545']
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
});
</script>
