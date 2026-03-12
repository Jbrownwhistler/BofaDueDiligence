<meta name="base-url" content="<?= BASE_URL ?>">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-comments me-2 text-bofa-red"></i>Messages - <?= htmlspecialchars($case['case_id_unique']) ?></h4>
    <a href="<?= BASE_URL ?>client/case?id=<?= $case['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour au dossier</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="mb-4" style="max-height:500px;overflow-y:auto">
            <?php if (empty($messages)): ?>
                <p class="text-muted text-center p-4">Aucun message échangé.</p>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="d-flex mb-3">
                        <div class="message-bubble <?= $msg['expediteur_id'] == Auth::id() ? 'message-sent' : 'message-received' ?>">
                            <div class="fw-bold small mb-1"><?= htmlspecialchars($msg['sender_name']) ?>
                                <span class="badge bg-<?= $msg['sender_role'] === 'agent' ? 'info' : 'secondary' ?> ms-1"><?= ucfirst($msg['sender_role']) ?></span>
                            </div>
                            <?= nl2br(htmlspecialchars($msg['message'])) ?>
                            <?php if ($msg['piece_jointe']): ?>
                                <div class="mt-1"><i class="fas fa-paperclip"></i> Pièce jointe</div>
                            <?php endif; ?>
                            <div class="text-end mt-1" style="font-size:0.7rem;opacity:0.7"><?= date('d/m/Y H:i', strtotime($msg['date'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form method="POST" action="<?= BASE_URL ?>client/send-message">
            <?= CSRF::field() ?>
            <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
            <div class="input-group">
                <input type="text" name="message" class="form-control" placeholder="Écrire un message..." required>
                <button type="submit" class="btn btn-bofa"><i class="fas fa-paper-plane me-1"></i>Envoyer</button>
            </div>
        </form>
    </div>
</div>
