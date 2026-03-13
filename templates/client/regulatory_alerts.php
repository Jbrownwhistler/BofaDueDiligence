<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-bell me-2 text-bofa-red"></i>Alertes réglementaires</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-shield-halved me-2"></i>Notifications de conformité</span>
        <span class="badge bg-bofa-navy"><?= count($notifications) ?> alerte(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="text-center text-muted p-5">
                <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                <p>Aucune alerte réglementaire.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $n): ?>
                <div class="list-group-item <?= !$n['lu'] ? 'list-group-item-light' : '' ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="d-flex align-items-center mb-1">
                                <?php
                                $iconClass = match($n['type']) {
                                    'danger' => 'fa-exclamation-circle text-danger',
                                    'warning' => 'fa-exclamation-triangle text-warning',
                                    'success' => 'fa-check-circle text-success',
                                    default => 'fa-info-circle text-info',
                                };
                                ?>
                                <i class="fas <?= $iconClass ?> me-2"></i>
                                <strong><?= htmlspecialchars($n['message']) ?></strong>
                                <?php if (!$n['lu']): ?><span class="badge bg-danger ms-2">Nouveau</span><?php endif; ?>
                            </div>
                            <small class="text-muted"><i class="fas fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($n['date'])) ?></small>
                        </div>
                        <?php if ($n['lien']): ?>
                            <a href="<?= BASE_URL . ltrim($n['lien'], '/') ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-arrow-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
