<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-file-invoice-dollar me-2 text-bofa-red"></i>Relevés de compte</h4>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Compte principal</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">N° Compte</td><td class="fw-bold"><?= htmlspecialchars($account['numero_compte_principal'] ?? 'N/A') ?></td></tr>
                    <tr><td class="text-muted">Solde actuel</td><td class="fw-bold text-success">$<?= number_format($account['solde'] ?? 0, 2) ?></td></tr>
                    <tr><td class="text-muted">Devise</td><td><?= htmlspecialchars($account['devise'] ?? 'USD') ?></td></tr>
                </table>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-download me-2"></i>Téléchargements</div>
            <div class="card-body">
                <p class="text-muted small">Les relevés officiels sont disponibles au format PDF.</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-bofa-outline btn-sm" disabled><i class="fas fa-file-pdf me-1"></i> Relevé mensuel</button>
                    <button class="btn btn-bofa-outline btn-sm" disabled><i class="fas fa-file-pdf me-1"></i> Relevé trimestriel</button>
                    <button class="btn btn-bofa-outline btn-sm" disabled><i class="fas fa-file-pdf me-1"></i> Relevé annuel</button>
                </div>
                <small class="text-muted d-block mt-2">Disponible prochainement</small>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-list me-2"></i>Mouvements récents</div>
            <div class="card-body p-0">
                <?php if (empty($transfers) && empty($cases)): ?>
                    <div class="text-center text-muted p-4">Aucun mouvement enregistré.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Date</th><th>Description</th><th>Référence</th><th>Montant</th><th>Statut</th></tr></thead>
                            <tbody>
                                <?php foreach ($cases as $c): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($c['date_creation'])) ?></td>
                                    <td>
                                        <?php if ($c['statut_fonds'] === 'transfere'): ?>
                                            <i class="fas fa-arrow-down text-success me-1"></i> Réception — <?= htmlspecialchars($c['emetteur_nom']) ?>
                                        <?php else: ?>
                                            <i class="fas fa-clock text-warning me-1"></i> En cours — <?= htmlspecialchars($c['emetteur_nom']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= htmlspecialchars($c['case_id_unique']) ?></small></td>
                                    <td class="fw-bold <?= $c['statut_fonds'] === 'transfere' ? 'text-success' : 'text-muted' ?>">
                                        <?= $c['statut_fonds'] === 'transfere' ? '+' : '' ?>$<?= number_format($c['montant'], 2) ?>
                                    </td>
                                    <td><span class="badge-status fonds-<?= $c['statut_fonds'] ?>"><?= CaseModel::getFondsLabel($c['statut_fonds']) ?></span></td>
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
