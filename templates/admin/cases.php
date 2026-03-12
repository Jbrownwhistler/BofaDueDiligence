<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-folder-open me-2 text-bofa-red"></i>Tous les dossiers</h4>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <select name="statut" class="form-select form-select-sm">
                    <option value="">Tous statuts</option>
                    <option value="en_analyse" <?= ($_GET['statut'] ?? '') === 'en_analyse' ? 'selected' : '' ?>>En analyse</option>
                    <option value="documents_demandes" <?= ($_GET['statut'] ?? '') === 'documents_demandes' ? 'selected' : '' ?>>Docs demandés</option>
                    <option value="en_attente_validation" <?= ($_GET['statut'] ?? '') === 'en_attente_validation' ? 'selected' : '' ?>>Attente validation</option>
                    <option value="gele" <?= ($_GET['statut'] ?? '') === 'gele' ? 'selected' : '' ?>>Gelé</option>
                    <option value="rejete" <?= ($_GET['statut'] ?? '') === 'rejete' ? 'selected' : '' ?>>Rejeté</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="agent_id" class="form-select form-select-sm">
                    <option value="">Tous agents</option>
                    <?php foreach ($agents as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= ($_GET['agent_id'] ?? '') == $ag['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ag['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="ID, nom, email..."></div>
            <div class="col-md-2"><button type="submit" class="btn btn-sm btn-bofa w-100"><i class="fas fa-filter me-1"></i>Filtrer</button></div>
            <div class="col-md-1"><a href="<?= BASE_URL ?>admin/cases" class="btn btn-sm btn-outline-secondary w-100">Reset</a></div>
            <div class="col-md-2"><a href="<?= BASE_URL ?>api/export/csv?type=cases" class="btn btn-sm btn-outline-success w-100"><i class="fas fa-file-csv me-1"></i>CSV</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>ID</th><th>Client</th><th>Montant</th><th>Pays</th><th>Score</th><th>Statut</th><th>Fonds</th><th>Agent</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($cases)): ?>
                        <tr><td colspan="9" class="text-center text-muted p-4">Aucun dossier.</td></tr>
                    <?php else: ?>
                        <?php foreach ($cases as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['case_id_unique']) ?></strong></td>
                            <td><?= htmlspecialchars($c['client_name'] ?? '-') ?></td>
                            <td class="fw-bold">$<?= number_format($c['montant'], 2) ?></td>
                            <td><?= htmlspecialchars($c['pays_origine']) ?></td>
                            <td><span class="risk-badge bg-<?= RiskCalculator::getScoreClass($c['score_risque']) ?> text-white"><?= $c['score_risque'] ?></span></td>
                            <td><span class="badge-status status-<?= $c['statut'] ?>"><?= CaseModel::getStatusLabel($c['statut']) ?></span></td>
                            <td><span class="badge-status fonds-<?= $c['statut_fonds'] ?>"><?= CaseModel::getFondsLabel($c['statut_fonds']) ?></span></td>
                            <td>
                                <?php if ($c['agent_name']): ?>
                                    <?= htmlspecialchars($c['agent_name']) ?>
                                <?php else: ?>
                                    <form method="POST" action="<?= BASE_URL ?>admin/users/assign-agent" class="d-inline">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="case_id" value="<?= $c['id'] ?>">
                                        <select name="agent_id" class="form-select form-select-sm d-inline" style="width:auto" onchange="this.form.submit()">
                                            <option value="">Assigner...</option>
                                            <?php foreach ($agents as $ag): ?>
                                                <option value="<?= $ag['id'] ?>"><?= htmlspecialchars($ag['full_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td><a href="<?= BASE_URL ?>admin/case?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
