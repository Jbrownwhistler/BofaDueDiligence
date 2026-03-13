<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-user-check me-2 text-bofa-red"></i>Gestion KYC</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-users me-2"></i>Statut KYC des clients</span>
        <span class="badge bg-bofa-navy"><?= count($clientData) ?> client(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($clientData)): ?>
            <div class="text-center text-muted p-4">Aucun client enregistré.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Email</th>
                            <th>Compte</th>
                            <th>Dossiers</th>
                            <th>Documents</th>
                            <th>Validés</th>
                            <th>En attente</th>
                            <th>Risque moyen</th>
                            <th>Statut KYC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientData as $cd): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($cd['user']['full_name']) ?></strong></td>
                            <td><small><?= htmlspecialchars($cd['user']['email']) ?></small></td>
                            <td><small><?= htmlspecialchars($cd['account']['numero_compte_principal'] ?? 'N/A') ?></small></td>
                            <td><span class="badge bg-secondary"><?= $cd['cases_count'] ?></span></td>
                            <td><span class="badge bg-info"><?= $cd['total_docs'] ?></span></td>
                            <td><span class="badge bg-success"><?= $cd['validated_docs'] ?></span></td>
                            <td>
                                <?php if ($cd['pending_docs'] > 0): ?>
                                    <span class="badge bg-warning"><?= $cd['pending_docs'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $cd['avg_risk'] < 3 ? 'success' : ($cd['avg_risk'] < 7 ? 'warning' : 'danger') ?>">
                                    <?= number_format($cd['avg_risk'], 2) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($cd['total_docs'] > 0 && $cd['pending_docs'] === 0): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Complet</span>
                                <?php elseif ($cd['pending_docs'] > 0): ?>
                                    <span class="badge bg-warning"><i class="fas fa-clock"></i> En cours</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="fas fa-minus"></i> Aucun doc</span>
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
