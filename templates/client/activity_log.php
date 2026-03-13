<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-clipboard-list me-2 text-bofa-red"></i>Journal d'activité</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-history me-2"></i>Historique de vos actions</span>
        <small class="text-muted"><?= $total ?> entrée(s) au total</small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="text-center text-muted p-5">
                <i class="fas fa-clipboard fa-3x mb-3"></i>
                <p>Aucune activité enregistrée.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($logs as $l): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <div>
                            <i class="fas fa-circle-dot me-2 text-bofa-navy" style="font-size:0.6rem"></i>
                            <?= htmlspecialchars($l['action']) ?>
                        </div>
                        <small class="text-muted">
                            <?= date('d/m/Y H:i', strtotime($l['date'])) ?>
                            <?php if ($l['ip']): ?> — IP: <?= htmlspecialchars($l['ip']) ?><?php endif; ?>
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= BASE_URL ?>client/activity-log?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
