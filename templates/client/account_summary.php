<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-chart-line me-2 text-bofa-red"></i>Synthèse du compte</h4>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card" style="border-left-color: var(--bofa-navy)">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Solde total</div>
                    <div class="stat-value text-bofa-navy">$<?= number_format($account['solde'] ?? 0, 2) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($account['numero_compte_principal'] ?? 'N/A') ?></small>
                </div>
                <div class="stat-icon text-bofa-navy"><i class="fas fa-wallet"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card" style="border-left-color: #198754">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total reçu</div>
                    <div class="stat-value text-success">$<?= number_format($totalIncoming, 2) ?></div>
                    <small class="text-muted"><?= count($transfers) ?> transfert(s) effectué(s)</small>
                </div>
                <div class="stat-icon text-success"><i class="fas fa-arrow-down"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card" style="border-left-color: #ffc107">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">En attente</div>
                    <div class="stat-value text-warning">$<?= number_format($totalPending, 2) ?></div>
                    <small class="text-muted">Fonds en cours de traitement</small>
                </div>
                <div class="stat-icon text-warning"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><i class="fas fa-layer-group me-2"></i>Sous-comptes</div>
            <div class="card-body p-0">
                <?php if (empty($subAccounts)): ?>
                    <div class="text-center text-muted p-4">Aucun sous-compte.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>N° Sous-compte</th><th>Ledger</th><th>Date création</th></tr></thead>
                            <tbody>
                                <?php foreach ($subAccounts as $sa): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($sa['numero_sous_compte']) ?></strong></td>
                                    <td>$<?= number_format($sa['ledger'], 2) ?></td>
                                    <td><?= date('d/m/Y', strtotime($sa['date_creation'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Détails du compte</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Compte principal</td><td class="fw-bold"><?= htmlspecialchars($account['numero_compte_principal'] ?? 'N/A') ?></td></tr>
                    <tr><td class="text-muted">Devise</td><td><?= htmlspecialchars($account['devise'] ?? 'USD') ?></td></tr>
                    <tr><td class="text-muted">Sous-comptes</td><td><?= count($subAccounts) ?></td></tr>
                    <tr><td class="text-muted">Dossiers actifs</td><td><?= count($cases) ?></td></tr>
                    <tr><td class="text-muted">Transferts effectués</td><td><?= count($transfers) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
