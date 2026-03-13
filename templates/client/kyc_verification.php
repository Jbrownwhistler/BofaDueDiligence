<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-user-check me-2 text-bofa-red"></i>Vérification KYC</h4>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><i class="fas fa-id-card me-2"></i>Statut d'identification</div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="avatar-lg mx-auto mb-3" style="width:80px;height:80px;border-radius:50%;background:var(--bofa-navy);display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-user fa-2x text-white"></i>
                    </div>
                    <h5><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h5>
                    <p class="text-muted small"><?= htmlspecialchars($user['email']) ?></p>
                </div>
                <table class="table table-sm table-borderless">
                    <tr><td class="text-muted">Niveau KYC</td><td><span class="badge bg-success">Vérifié</span></td></tr>
                    <tr><td class="text-muted">Identité</td><td><span class="badge bg-success"><i class="fas fa-check me-1"></i>Confirmée</span></td></tr>
                    <tr><td class="text-muted">Adresse</td><td><span class="badge bg-warning"><i class="fas fa-clock me-1"></i>En attente</span></td></tr>
                    <tr><td class="text-muted">Source de fonds</td><td><span class="badge bg-<?= count($allDocuments) > 0 ? 'success' : 'warning' ?>"><?= count($allDocuments) > 0 ? 'Documentée' : 'À fournir' ?></span></td></tr>
                    <tr><td class="text-muted">Due Diligence</td><td><span class="badge bg-<?= count($cases) > 0 ? 'info' : 'secondary' ?>"><?= count($cases) > 0 ? 'En cours' : 'Non démarrée' ?></span></td></tr>
                    <tr><td class="text-muted">Dernière mise à jour</td><td><?= $user['dernier_login'] ? date('d/m/Y', strtotime($user['dernier_login'])) : 'N/A' ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><i class="fas fa-file-alt me-2"></i>Documents KYC soumis</div>
            <div class="card-body p-0">
                <?php if (empty($allDocuments)): ?>
                    <div class="text-center text-muted p-4">
                        <i class="fas fa-folder-open fa-3x mb-3"></i>
                        <p>Aucun document KYC soumis.</p>
                        <small>Téléversez vos documents via vos dossiers en cours.</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Document</th><th>Type</th><th>Dossier</th><th>Statut</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($allDocuments as $d): ?>
                                <tr>
                                    <td><i class="fas fa-file me-1"></i> <?= htmlspecialchars($d['nom_fichier']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($d['type_document']) ?></span></td>
                                    <td><small><?= htmlspecialchars($d['case_id_unique']) ?></small></td>
                                    <td>
                                        <?php if ($d['statut_validation'] === 'valide'): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Validé</span>
                                        <?php elseif ($d['statut_validation'] === 'rejete'): ?>
                                            <span class="badge bg-danger"><i class="fas fa-times"></i> Rejeté</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning"><i class="fas fa-clock"></i> En attente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= date('d/m/Y', strtotime($d['date_upload'])) ?></small></td>
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
