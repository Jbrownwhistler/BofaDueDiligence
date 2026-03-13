<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-vault me-2 text-bofa-red"></i>Coffre-fort documents</h4>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: var(--bofa-navy)">
            <div class="stat-label">Total documents</div>
            <div class="stat-value text-bofa-navy"><?= count($allDocuments) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #198754">
            <div class="stat-label">Validés</div>
            <div class="stat-value text-success"><?= count(array_filter($allDocuments, fn($d) => $d['statut_validation'] === 'valide')) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #ffc107">
            <div class="stat-label">En attente</div>
            <div class="stat-value text-warning"><?= count(array_filter($allDocuments, fn($d) => $d['statut_validation'] === 'en_attente')) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card" style="border-left-color: #dc3545">
            <div class="stat-label">Rejetés</div>
            <div class="stat-value text-danger"><?= count(array_filter($allDocuments, fn($d) => $d['statut_validation'] === 'rejete')) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-folder-open me-2"></i>Tous les documents</span>
        <span class="badge bg-bofa-navy"><?= count($allDocuments) ?> fichier(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($allDocuments)): ?>
            <div class="text-center text-muted p-4">
                <i class="fas fa-folder-open fa-3x mb-3"></i>
                <p>Aucun document dans le coffre-fort.</p>
                <a href="<?= BASE_URL ?>client/pending" class="btn btn-bofa btn-sm">Soumettre des documents</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Document</th><th>Type</th><th>Dossier associé</th><th>Statut</th><th>Date d'envoi</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allDocuments as $d): ?>
                        <tr>
                            <td><i class="fas fa-file-pdf me-1 text-danger"></i> <?= htmlspecialchars($d['nom_fichier']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($d['type_document']) ?></span></td>
                            <td><a href="<?= BASE_URL ?>client/case?id=<?= $d['case_db_id'] ?>"><?= htmlspecialchars($d['case_id_unique']) ?></a></td>
                            <td>
                                <?php if ($d['statut_validation'] === 'valide'): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Validé</span>
                                <?php elseif ($d['statut_validation'] === 'rejete'): ?>
                                    <span class="badge bg-danger"><i class="fas fa-times"></i> Rejeté</span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><i class="fas fa-clock"></i> En attente</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($d['date_upload'])) ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>client/case?id=<?= $d['case_db_id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir le dossier"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
