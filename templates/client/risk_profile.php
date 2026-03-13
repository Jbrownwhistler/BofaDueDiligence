<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-chart-pie me-2 text-bofa-red"></i>Profil de risque</h4>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card" style="border-left-color: <?= $avgRisk < 3 ? '#198754' : ($avgRisk < 7 ? '#ffc107' : '#dc3545') ?>">
            <div class="stat-label">Score de risque moyen</div>
            <div class="stat-value" style="color: <?= $avgRisk < 3 ? '#198754' : ($avgRisk < 7 ? '#ffc107' : '#dc3545') ?>"><?= number_format($avgRisk, 2) ?></div>
            <small class="text-muted"><?= $avgRisk < 3 ? 'Risque faible' : ($avgRisk < 7 ? 'Risque modéré' : 'Risque élevé') ?></small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card" style="border-left-color: <?= $maxRisk < 3 ? '#198754' : ($maxRisk < 7 ? '#ffc107' : '#dc3545') ?>">
            <div class="stat-label">Score maximal</div>
            <div class="stat-value" style="color: <?= $maxRisk < 3 ? '#198754' : ($maxRisk < 7 ? '#ffc107' : '#dc3545') ?>"><?= number_format($maxRisk, 2) ?></div>
            <small class="text-muted">Plus haut score enregistré</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card" style="border-left-color: var(--bofa-navy)">
            <div class="stat-label">Dossiers évalués</div>
            <div class="stat-value text-bofa-navy"><?= count($cases) ?></div>
            <small class="text-muted">Transactions analysées</small>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-list me-2"></i>Détail par dossier</div>
            <div class="card-body p-0">
                <?php if (empty($cases)): ?>
                    <div class="text-center text-muted p-4">Aucun dossier évalué.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Dossier</th><th>Montant</th><th>Origine</th><th>Score</th><th>Niveau</th></tr></thead>
                            <tbody>
                                <?php foreach ($cases as $c): ?>
                                <tr>
                                    <td><a href="<?= BASE_URL ?>client/case?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['case_id_unique']) ?></a></td>
                                    <td>$<?= number_format($c['montant'], 2) ?></td>
                                    <td><?= htmlspecialchars($c['pays_origine']) ?></td>
                                    <td><strong><?= number_format($c['score_risque'], 2) ?></strong></td>
                                    <td>
                                        <?php if ($c['score_risque'] < 3): ?>
                                            <span class="badge bg-success">Faible</span>
                                        <?php elseif ($c['score_risque'] < 7): ?>
                                            <span class="badge bg-warning">Modéré</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Élevé</span>
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
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-globe me-2"></i>Risque par pays d'origine</div>
            <div class="card-body p-0">
                <?php if (empty($riskByCountry)): ?>
                    <div class="text-center text-muted p-4">Aucune donnée.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Pays</th><th>Transactions</th><th>Score moyen</th></tr></thead>
                            <tbody>
                                <?php foreach ($riskByCountry as $country => $data): ?>
                                <tr>
                                    <td><i class="fas fa-globe me-1"></i> <?= htmlspecialchars($country) ?></td>
                                    <td><?= $data['count'] ?></td>
                                    <td>
                                        <?php $avg = $data['total_risk'] / $data['count']; ?>
                                        <span class="badge bg-<?= $avg < 3 ? 'success' : ($avg < 7 ? 'warning' : 'danger') ?>"><?= number_format($avg, 2) ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Échelle de risque</div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-2"><span class="badge bg-success me-2" style="width:60px">0 - 3</span> Risque faible — Traitement standard</div>
                <div class="d-flex align-items-center mb-2"><span class="badge bg-warning me-2" style="width:60px">3 - 7</span> Risque modéré — Vérification renforcée</div>
                <div class="d-flex align-items-center"><span class="badge bg-danger me-2" style="width:60px">7+</span> Risque élevé — Validation superviseur requise</div>
            </div>
        </div>
    </div>
</div>
