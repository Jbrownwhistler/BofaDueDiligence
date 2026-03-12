<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-globe me-2 text-bofa-red"></i>Pays à risque</h4>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Pays</th><th>Code</th><th>Coefficient</th><th>Niveau</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($countries as $c): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($c['nom_pays']) ?></td>
                                <td><code><?= htmlspecialchars($c['code_pays']) ?></code></td>
                                <td><span class="fw-bold"><?= $c['coefficient_risque'] ?></span></td>
                                <td><span class="risk-badge bg-<?= RiskCalculator::getScoreClass($c['coefficient_risque'] * 2) ?> text-white"><?= RiskCalculator::getScoreLabel($c['coefficient_risque'] * 2) ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCountry<?= $c['id'] ?>"><i class="fas fa-edit"></i></button>
                                    <form method="POST" action="<?= BASE_URL ?>admin/risk-countries/delete" class="d-inline">
                                        <?= CSRF::field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" data-confirm="Supprimer ce pays ?"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <!-- Edit Modal -->
                            <div class="modal fade" id="editCountry<?= $c['id'] ?>"><div class="modal-dialog"><div class="modal-content">
                                <form method="POST" action="<?= BASE_URL ?>admin/risk-countries/save">
                                    <?= CSRF::field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <div class="modal-header"><h6 class="modal-title">Modifier le pays</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <div class="mb-2"><label class="form-label">Nom</label><input type="text" name="nom_pays" class="form-control" value="<?= htmlspecialchars($c['nom_pays']) ?>" required></div>
                                        <div class="mb-2"><label class="form-label">Code</label><input type="text" name="code_pays" class="form-control" value="<?= htmlspecialchars($c['code_pays']) ?>" maxlength="3" required></div>
                                        <div class="mb-2"><label class="form-label">Coefficient</label><input type="number" name="coefficient_risque" class="form-control" value="<?= $c['coefficient_risque'] ?>" step="0.01" min="0.01" required></div>
                                    </div>
                                    <div class="modal-footer"><button type="submit" class="btn btn-bofa">Enregistrer</button></div>
                                </form>
                            </div></div></div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-bofa-navy text-white"><i class="fas fa-plus me-2"></i>Ajouter un pays</div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>admin/risk-countries/save">
                    <?= CSRF::field() ?><input type="hidden" name="id" value="0">
                    <div class="mb-2"><label class="form-label">Nom du pays</label><input type="text" name="nom_pays" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Code ISO</label><input type="text" name="code_pays" class="form-control" maxlength="3" required placeholder="ex: FR"></div>
                    <div class="mb-3"><label class="form-label">Coefficient de risque</label><input type="number" name="coefficient_risque" class="form-control" step="0.01" min="0.01" value="1.00" required></div>
                    <button type="submit" class="btn btn-bofa w-100"><i class="fas fa-plus me-1"></i>Ajouter</button>
                </form>
            </div>
        </div>
    </div>
</div>
