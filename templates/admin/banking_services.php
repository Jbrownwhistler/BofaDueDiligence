<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-th me-2 text-bofa-red"></i>Gestion des services bancaires</h4>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: var(--bofa-navy)">
            <div class="stat-label">Services actifs</div>
            <div class="stat-value text-bofa-navy"><?= count($services) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #198754">
            <div class="stat-label">Clients actifs</div>
            <div class="stat-value text-success"><?= $userCounts['client'] ?? 0 ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #ffc107">
            <div class="stat-label">Dossiers en cours</div>
            <div class="stat-value text-warning"><?= $totalCases ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #0dcaf0">
            <div class="stat-label">Documents traités</div>
            <div class="stat-value text-info"><?= $totalDocuments ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>20 Services bancaires en ligne</span>
        <span class="badge bg-success"><?= count(array_filter($services, fn($s) => $s['status'] === 'active')) ?> actif(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>#</th><th>Service</th><th>Route</th><th>Statut</th><th>Catégorie</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $i => $s): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><i class="fas <?= $s['icon'] ?> me-2 text-bofa-navy"></i> <strong><?= htmlspecialchars($s['name']) ?></strong></td>
                        <td><code><?= htmlspecialchars($s['route']) ?></code></td>
                        <td>
                            <?php if ($s['status'] === 'active'): ?>
                                <span class="badge bg-success"><i class="fas fa-check"></i> Actif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="fas fa-pause"></i> Inactif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $category = match(true) {
                                in_array($i, [0, 1, 2, 8, 9]) => 'Finances',
                                in_array($i, [3, 4, 5, 10, 11]) => 'Conformité',
                                in_array($i, [6, 13, 14, 15]) => 'Documents',
                                in_array($i, [7]) => 'Communication',
                                default => 'Paramètres',
                            };
                            $catColor = match($category) {
                                'Finances' => 'primary',
                                'Conformité' => 'danger',
                                'Documents' => 'info',
                                'Communication' => 'purple',
                                default => 'secondary',
                            };
                            ?>
                            <span class="badge bg-<?= $catColor ?>"><?= $category ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
