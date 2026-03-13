<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-satellite-dish me-2 text-bofa-red"></i>Suivi des transactions</h4>

<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Monitoring en temps réel</strong> — Toutes vos transactions sont surveillées conformément au programme AML de Bank of America. Les fonds sont soumis à un processus de vérification avant d'être disponibles.
</div>

<div class="card">
    <div class="card-header"><i class="fas fa-stream me-2"></i>Flux de transactions</div>
    <div class="card-body p-0">
        <?php if (empty($cases)): ?>
            <div class="text-center text-muted p-5">
                <i class="fas fa-inbox fa-3x mb-3"></i>
                <p>Aucune transaction à surveiller.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Montant</th>
                            <th>Émetteur</th>
                            <th>Origine → Destination</th>
                            <th>Type</th>
                            <th>Score risque</th>
                            <th>Statut dossier</th>
                            <th>Statut fonds</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cases as $c): ?>
                        <tr>
                            <td><a href="<?= BASE_URL ?>client/case?id=<?= $c['id'] ?>"><strong><?= htmlspecialchars($c['case_id_unique']) ?></strong></a></td>
                            <td class="fw-bold">$<?= number_format($c['montant'], 2) ?></td>
                            <td><?= htmlspecialchars($c['emetteur_nom']) ?></td>
                            <td>
                                <small><?= htmlspecialchars($c['pays_origine']) ?> <i class="fas fa-arrow-right mx-1"></i> <?= htmlspecialchars($c['pays_destination']) ?></small>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($c['type_actif']) ?></span></td>
                            <td>
                                <span class="badge bg-<?= $c['score_risque'] < 3 ? 'success' : ($c['score_risque'] < 7 ? 'warning' : 'danger') ?>">
                                    <?= number_format($c['score_risque'], 2) ?>
                                </span>
                            </td>
                            <td><span class="badge-status status-<?= $c['statut'] ?>"><?= CaseModel::getStatusLabel($c['statut']) ?></span></td>
                            <td><span class="badge-status fonds-<?= $c['statut_fonds'] ?>"><?= CaseModel::getFondsLabel($c['statut_fonds']) ?></span></td>
                            <td><small><?= date('d/m/Y', strtotime($c['date_creation'])) ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
