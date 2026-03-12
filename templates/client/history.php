<meta name="base-url" content="<?= BASE_URL ?>">

<h4 class="mb-4"><i class="fas fa-history me-2 text-bofa-red"></i>Historique des transferts</h4>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($transfers)): ?>
            <div class="text-center text-muted p-4">
                <i class="fas fa-inbox fa-3x mb-3"></i>
                <p>Aucun transfert effectué.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID Dossier</th>
                            <th>Montant</th>
                            <th>Émetteur</th>
                            <th>Origine</th>
                            <th>Type</th>
                            <th>Date transfert</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfers as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['case_id_unique']) ?></strong></td>
                            <td class="fw-bold text-success">+$<?= number_format($t['montant'], 2) ?></td>
                            <td><?= htmlspecialchars($t['emetteur_nom']) ?></td>
                            <td><?= htmlspecialchars($t['pays_origine']) ?></td>
                            <td><?= htmlspecialchars($t['type_actif']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($t['date_maj'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
