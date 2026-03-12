<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-clipboard-list me-2 text-bofa-red"></i>Journal d'audit global</h4>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Table</th><th>ID</th><th>IP</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-nowrap"><?= date('d/m/Y H:i:s', strtotime($log['date'])) ?></td>
                        <td><?= htmlspecialchars($log['user_name'] ?? 'Système') ?></td>
                        <td><?= htmlspecialchars($log['action']) ?></td>
                        <td><code><?= htmlspecialchars($log['table_concernee'] ?? '-') ?></code></td>
                        <td><?= $log['enregistrement_id'] ?? '-' ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($log['ip'] ?? '-') ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer">
        <nav><ul class="pagination pagination-sm justify-content-center mb-0">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>admin/audit?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>
