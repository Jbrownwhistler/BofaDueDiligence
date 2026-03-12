<meta name="base-url" content="<?= BASE_URL ?>">

<h4 class="mb-4"><i class="fas fa-folder-open me-2 text-bofa-red"></i>Dossiers AML</h4>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Statut</label>
                <select name="statut" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    <option value="en_analyse" <?= ($_GET['statut'] ?? '') === 'en_analyse' ? 'selected' : '' ?>>En analyse</option>
                    <option value="documents_demandes" <?= ($_GET['statut'] ?? '') === 'documents_demandes' ? 'selected' : '' ?>>Documents demandés</option>
                    <option value="en_attente_validation" <?= ($_GET['statut'] ?? '') === 'en_attente_validation' ? 'selected' : '' ?>>En attente validation</option>
                    <option value="pret_pour_transfert" <?= ($_GET['statut'] ?? '') === 'pret_pour_transfert' ? 'selected' : '' ?>>Prêt pour transfert</option>
                    <option value="gele" <?= ($_GET['statut'] ?? '') === 'gele' ? 'selected' : '' ?>>Gelé</option>
                    <option value="rejete" <?= ($_GET['statut'] ?? '') === 'rejete' ? 'selected' : '' ?>>Rejeté</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Pays</label>
                <input type="text" name="pays" class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['pays'] ?? '') ?>" placeholder="Pays d'origine...">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-bofa w-100"><i class="fas fa-filter me-1"></i>Filtrer</button>
            </div>
            <div class="col-md-2">
                <a href="<?= BASE_URL ?>agent/cases" class="btn btn-sm btn-outline-secondary w-100">Réinitialiser</a>
            </div>
            <div class="col-md-2">
                <a href="<?= BASE_URL ?>api/export/csv?type=cases" class="btn btn-sm btn-outline-success w-100"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID Dossier</th>
                        <th>Client</th>
                        <th>Montant</th>
                        <th>Pays</th>
                        <th>Type</th>
                        <th>Score</th>
                        <th>Statut</th>
                        <th>Fonds</th>
                        <th>Échéance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cases)): ?>
                        <tr><td colspan="10" class="text-center text-muted p-4">Aucun dossier trouvé.</td></tr>
                    <?php else: ?>
                        <?php foreach ($cases as $c):
                            $isOverdue = $c['date_limite'] && strtotime($c['date_limite']) < time() && !in_array($c['statut'], ['valide','pret_pour_transfert','rejete']);
                        ?>
                        <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                            <td><strong><?= htmlspecialchars($c['case_id_unique']) ?></strong></td>
                            <td><?= htmlspecialchars($c['client_name'] ?? '-') ?></td>
                            <td class="fw-bold">$<?= number_format($c['montant'], 2) ?></td>
                            <td><i class="fas fa-globe me-1"></i><?= htmlspecialchars($c['pays_origine']) ?></td>
                            <td><?= htmlspecialchars($c['type_actif']) ?></td>
                            <td>
                                <span class="risk-badge bg-<?= RiskCalculator::getScoreClass($c['score_risque']) ?> text-white">
                                    <?= $c['score_risque'] ?>
                                </span>
                            </td>
                            <td><span class="badge-status status-<?= $c['statut'] ?>"><?= CaseModel::getStatusLabel($c['statut']) ?></span></td>
                            <td><span class="badge-status fonds-<?= $c['statut_fonds'] ?>"><?= CaseModel::getFondsLabel($c['statut_fonds']) ?></span></td>
                            <td>
                                <?php if ($c['date_limite']): ?>
                                    <span class="<?= $isOverdue ? 'text-danger fw-bold' : '' ?>">
                                        <?= $isOverdue ? '<i class="fas fa-exclamation-triangle me-1"></i>' : '' ?>
                                        <?= date('d/m/Y', strtotime($c['date_limite'])) ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>agent/case?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
