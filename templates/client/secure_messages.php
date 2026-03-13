<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-envelope-open-text me-2 text-bofa-red"></i>Messagerie sécurisée</h4>

<div class="alert alert-info mb-4">
    <i class="fas fa-lock me-2"></i>
    <strong>Communication chiffrée</strong> — Tous les messages échangés sont sécurisés et archivés conformément aux exigences réglementaires.
</div>

<?php if (empty($caseMessages)): ?>
    <div class="card">
        <div class="card-body text-center text-muted p-5">
            <i class="fas fa-comments fa-3x mb-3"></i>
            <p>Aucun message. Les conversations sont liées à vos dossiers de conformité.</p>
            <a href="<?= BASE_URL ?>client/pending" class="btn btn-bofa btn-sm">Voir mes dossiers</a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($caseMessages as $cm): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <i class="fas fa-folder me-1"></i>
                <a href="<?= BASE_URL ?>client/case?id=<?= $cm['case']['id'] ?>"><?= htmlspecialchars($cm['case']['case_id_unique']) ?></a>
                — <?= htmlspecialchars($cm['case']['emetteur_nom']) ?>
            </span>
            <span>
                <?php if ($cm['unread'] > 0): ?>
                    <span class="badge bg-danger"><?= $cm['unread'] ?> non lu(s)</span>
                <?php endif; ?>
                <span class="badge bg-secondary"><?= count($cm['messages']) ?> message(s)</span>
            </span>
        </div>
        <div class="card-body">
            <div class="mb-2">
                <small class="text-muted">Dernier message de <strong><?= htmlspecialchars($cm['last_message']['sender_name']) ?></strong> 
                le <?= date('d/m/Y H:i', strtotime($cm['last_message']['date'])) ?></small>
            </div>
            <p class="mb-2"><?= htmlspecialchars(mb_substr($cm['last_message']['message'], 0, 200)) ?><?= mb_strlen($cm['last_message']['message']) > 200 ? '...' : '' ?></p>
            <a href="<?= BASE_URL ?>client/messages?case_id=<?= $cm['case']['id'] ?>" class="btn btn-sm btn-bofa-outline">
                <i class="fas fa-comments me-1"></i> Ouvrir la conversation
            </a>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
