<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-users me-2 text-bofa-red"></i>Gestion des bénéficiaires</h4>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-user-friends me-2"></i>Bénéficiaires enregistrés</div>
            <div class="card-body p-0">
                <?php if (empty($beneficiaries)): ?>
                    <div class="text-center text-muted p-4">Aucun bénéficiaire enregistré.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Nom</th><th>Banque</th><th>Pays</th><th>Transactions</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($beneficiaries as $b): ?>
                                <tr>
                                    <td><i class="fas fa-user me-1"></i> <strong><?= htmlspecialchars($b['nom']) ?></strong></td>
                                    <td><?= htmlspecialchars($b['banque']) ?></td>
                                    <td><i class="fas fa-globe me-1"></i> <?= htmlspecialchars($b['pays']) ?></td>
                                    <td><span class="badge bg-secondary"><?= $b['transactions'] ?></span></td>
                                    <td class="fw-bold">$<?= number_format($b['total_montant'], 2) ?></td>
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
            <div class="card-header"><i class="fas fa-paper-plane me-2"></i>Émetteurs (sources de fonds)</div>
            <div class="card-body p-0">
                <?php if (empty($emetteurs)): ?>
                    <div class="text-center text-muted p-4">Aucun émetteur enregistré.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Nom</th><th>Banque</th><th>Pays</th><th>Transactions</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($emetteurs as $e): ?>
                                <tr>
                                    <td><i class="fas fa-building me-1"></i> <strong><?= htmlspecialchars($e['nom']) ?></strong></td>
                                    <td><?= htmlspecialchars($e['banque']) ?></td>
                                    <td><i class="fas fa-globe me-1"></i> <?= htmlspecialchars($e['pays']) ?></td>
                                    <td><span class="badge bg-secondary"><?= $e['transactions'] ?></span></td>
                                    <td class="fw-bold">$<?= number_format($e['total_montant'], 2) ?></td>
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
