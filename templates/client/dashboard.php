<meta name="base-url" content="<?= BASE_URL ?>">

<h4 class="mb-4"><i class="fas fa-tachometer-alt me-2 text-bofa-red"></i>Tableau de bord</h4>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
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
    <div class="col-md-4">
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
    <div class="col-md-4">
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
