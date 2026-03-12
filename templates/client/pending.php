<meta name="base-url" content="<?= BASE_URL ?>">

<h4 class="mb-4"><i class="fas fa-hourglass-half me-2 text-bofa-red"></i>Fonds en attente de conformité</h4>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($pending)): ?>
            <div class="text-center text-muted p-4">
                <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                <p>Aucun fonds en attente.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID Dossier</th>
                            <th>Montant</th>
                            <th>Émetteur</th>
                            <th>Pays d'origine</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Fonds</th>
                            <th>Agent assigné</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['case_id_unique']) ?></strong></td>
                            <td class="fw-bold">$<?= number_format($c['montant'], 2) ?></td>
                            <td><?= htmlspecialchars($c['emetteur_nom']) ?></td>
                            <td>
                                <i class="fas fa-globe me-1"></i>
                                <?= htmlspecialchars($c['pays_origine']) ?>
                            </td>
                            <td><?= htmlspecialchars($c['type_actif']) ?></td>
                            <td><span class="badge-status status-<?= $c['statut'] ?>"><?= CaseModel::getStatusLabel($c['statut']) ?></span></td>
                            <td><span class="badge-status fonds-<?= $c['statut_fonds'] ?>"><?= CaseModel::getFondsLabel($c['statut_fonds']) ?></span></td>
                            <td><?= htmlspecialchars($c['agent_name'] ?? 'Non assigné') ?></td>
                            <td><?= date('d/m/Y', strtotime($c['date_creation'])) ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>client/case?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($c['statut'] === 'pret_pour_transfert' && $c['statut_fonds'] === 'disponible'): ?>
                                    <form method="POST" action="<?= BASE_URL ?>client/transfer" class="d-inline">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="case_id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success" data-confirm="Confirmer le transfert ?">
                                            <i class="fas fa-arrow-right"></i> Transférer
                                        </button>
                                    </form>
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
