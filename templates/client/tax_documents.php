<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-landmark me-2 text-bofa-red"></i>Documents fiscaux</h4>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card stat-card" style="border-left-color: var(--bofa-navy)">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total transféré</div>
                    <div class="stat-value text-bofa-navy">$<?= number_format($totalTransferred, 2) ?></div>
                    <small class="text-muted"><?= count($transfers) ?> transfert(s) complété(s)</small>
                </div>
                <div class="stat-icon text-bofa-navy"><i class="fas fa-dollar-sign"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card stat-card" style="border-left-color: #198754">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Année fiscale</div>
                    <div class="stat-value text-success"><?= date('Y') ?></div>
                    <small class="text-muted">Exercice en cours</small>
                </div>
                <div class="stat-icon text-success"><i class="fas fa-calendar"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-file-alt me-2"></i>Documents disponibles</div>
            <div class="card-body">
                <div class="list-group">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-file-pdf text-danger me-2"></i> Attestation de fonds reçus — <?= date('Y') ?></div>
                        <button class="btn btn-sm btn-bofa-outline" disabled><i class="fas fa-download"></i></button>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-file-pdf text-danger me-2"></i> Récapitulatif annuel AML — <?= date('Y') ?></div>
                        <button class="btn btn-sm btn-bofa-outline" disabled><i class="fas fa-download"></i></button>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-file-pdf text-danger me-2"></i> Déclaration de conformité — <?= date('Y') ?></div>
                        <button class="btn btn-sm btn-bofa-outline" disabled><i class="fas fa-download"></i></button>
                    </div>
                </div>
                <small class="text-muted d-block mt-3"><i class="fas fa-info-circle me-1"></i>Les documents seront disponibles au téléchargement après la clôture de l'exercice fiscal.</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Informations fiscales</div>
            <div class="card-body">
                <p class="small text-muted">Conformément à la réglementation FATCA et CRS, les informations relatives à vos transactions internationales sont communiquées aux autorités fiscales compétentes.</p>
                <hr>
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Compte</td><td><?= htmlspecialchars($account['numero_compte_principal'] ?? 'N/A') ?></td></tr>
                    <tr><td class="text-muted">Solde au <?= date('d/m/Y') ?></td><td>$<?= number_format($account['solde'] ?? 0, 2) ?></td></tr>
                    <tr><td class="text-muted">Mouvements <?= date('Y') ?></td><td><?= count($transfers) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
