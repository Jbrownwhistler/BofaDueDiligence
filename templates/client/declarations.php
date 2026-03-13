<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-file-signature me-2 text-bofa-red"></i>Centre de déclarations</h4>

<div class="alert alert-warning mb-4">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Obligations déclaratives</strong> — En tant que client, vous êtes tenu de déclarer toute information pertinente concernant l'origine et la destination de vos fonds, conformément aux réglementations AML/CFT.
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-list-check me-2"></i>Déclarations obligatoires</div>
            <div class="card-body">
                <div class="list-group">
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Déclaration d'identité (KYC)</h6>
                                <small class="text-muted">Vérification de votre identité et de vos informations personnelles.</small>
                            </div>
                            <span class="badge bg-success">Complétée</span>
                        </div>
                    </div>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><i class="fas fa-<?= count($cases) > 0 ? 'check-circle text-success' : 'circle text-muted' ?> me-2"></i>Déclaration d'origine des fonds</h6>
                                <small class="text-muted">Documentation de la provenance de chaque transfert entrant.</small>
                            </div>
                            <span class="badge bg-<?= count($cases) > 0 ? 'success' : 'warning' ?>"><?= count($cases) > 0 ? 'En cours' : 'À compléter' ?></span>
                        </div>
                    </div>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><i class="fas fa-circle text-muted me-2"></i>Déclaration FATCA/CRS</h6>
                                <small class="text-muted">Attestation de résidence fiscale pour conformité aux échanges automatiques.</small>
                            </div>
                            <span class="badge bg-secondary">Non requise</span>
                        </div>
                    </div>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><i class="fas fa-circle text-muted me-2"></i>Déclaration de personne politiquement exposée (PEP)</h6>
                                <small class="text-muted">Attestation de statut PEP conformément aux exigences réglementaires.</small>
                            </div>
                            <span class="badge bg-secondary">Non requise</span>
                        </div>
                    </div>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><i class="fas fa-circle text-muted me-2"></i>Déclaration de bénéficiaire effectif (UBO)</h6>
                                <small class="text-muted">Identification des bénéficiaires effectifs des fonds.</small>
                            </div>
                            <span class="badge bg-secondary">Non requise</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Réglementations applicables</div>
            <div class="card-body">
                <ul class="list-unstyled small">
                    <li class="mb-2"><i class="fas fa-gavel text-bofa-navy me-2"></i> Bank Secrecy Act (BSA)</li>
                    <li class="mb-2"><i class="fas fa-gavel text-bofa-navy me-2"></i> USA PATRIOT Act</li>
                    <li class="mb-2"><i class="fas fa-gavel text-bofa-navy me-2"></i> FATCA (Foreign Account Tax Compliance Act)</li>
                    <li class="mb-2"><i class="fas fa-gavel text-bofa-navy me-2"></i> CRS (Common Reporting Standard)</li>
                    <li class="mb-2"><i class="fas fa-gavel text-bofa-navy me-2"></i> OFAC Sanctions Programs</li>
                    <li class="mb-2"><i class="fas fa-gavel text-bofa-navy me-2"></i> FinCEN Requirements</li>
                    <li class="mb-0"><i class="fas fa-gavel text-bofa-navy me-2"></i> FATF/GAFI Recommendations</li>
                </ul>
            </div>
        </div>
    </div>
</div>
