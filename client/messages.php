<?php
/**
 * Messagerie sécurisée client (F07) — BofaDueDiligence
 * Échange de messages entre le client et son agent référent.
 */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';

bofa_auth_check(['client']);

require_once BOFA_ROOT . '/src/CaseAML.php';
require_once BOFA_ROOT . '/src/Notification.php';

$userId   = (int) $_SESSION['user_id'];
$caseObj  = new CaseAML();
$notifObj = new Notification();
$errors   = [];
$notifCount = $notifObj->getUnreadCount($userId);

/* -----------------------------------------------------------------------
 * Récupération des dossiers du client
 * ----------------------------------------------------------------------- */
$dossiersData = $caseObj->getByClient($userId, 1, 100);
$dossiers     = $dossiersData['cases'] ?? $dossiersData;

/* Dossier sélectionné */
$caseId = (int) ($_GET['case_id'] ?? ($_POST['case_id'] ?? 0));
if ($caseId === 0 && !empty($dossiers)) {
    $caseId = (int) $dossiers[0]['id'];
}

/* Vérification appartenance */
$dossierCourant = null;
foreach ($dossiers as $d) {
    if ((int)$d['id'] === $caseId) { $dossierCourant = $d; break; }
}

/* -----------------------------------------------------------------------
 * Traitement POST — envoi d'un message
 * ----------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {

    $token   = $_POST['csrf_token'] ?? '';
    $contenu = trim($_POST['message'] ?? '');
    $postCaseId = (int) ($_POST['case_id'] ?? 0);

    if (!bofa_csrf_validate($token)) {
        $errors[] = 'Jeton de sécurité invalide. Veuillez réessayer.';
    } elseif ($postCaseId <= 0 || $dossierCourant === null) {
        $errors[] = 'Dossier invalide.';
    } elseif (mb_strlen($contenu) < 2) {
        $errors[] = 'Le message ne peut pas être vide.';
    } elseif (mb_strlen($contenu) > 4000) {
        $errors[] = 'Le message est trop long (4 000 caractères max).';
    } else {
        $attachmentId = null;

        /* Traitement pièce jointe optionnelle */
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            require_once BOFA_ROOT . '/src/Document.php';
            $docObj = new Document();
            $allowedMimes = $docObj->getAllowedMimes();
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($_FILES['attachment']['tmp_name']);

            if (!in_array($realMime, $allowedMimes, true)) {
                $errors[] = 'Type de fichier non autorisé pour la pièce jointe.';
            } elseif ($_FILES['attachment']['size'] > BOFA_UPLOAD_MAX) {
                $errors[] = 'La pièce jointe dépasse la taille maximale (10 Mo).';
            } else {
                $attachmentId = $docObj->upload($postCaseId, $userId, $_FILES['attachment']);
            }
        }

        if (empty($errors)) {
            try {
                $db = bofa_db();
                $stmt = $db->prepare(
                    "INSERT INTO messages (case_id, sender_id, contenu, attachment_id, created_at)
                     VALUES (:cid, :sid, :contenu, :att, NOW())"
                );
                $stmt->execute([
                    ':cid'     => $postCaseId,
                    ':sid'     => $userId,
                    ':contenu' => $contenu,
                    ':att'     => $attachmentId,
                ]);

                /* Notifier l'agent si assigné */
                if (!empty($dossierCourant['agent_id'])) {
                    $notifObj->send(
                        (int)$dossierCourant['agent_id'],
                        'Nouveau message client sur le dossier ' . ($dossierCourant['case_number'] ?? ''),
                        'message',
                        $postCaseId
                    );
                }

                bofa_flash('Message envoyé avec succès.', 'success');
                bofa_redirect('/bofa/client/messages.php?case_id=' . $postCaseId);
            } catch (PDOException $e) {
                error_log('[messages.php] Erreur envoi message : ' . $e->getMessage());
                $errors[] = 'Erreur lors de l\'envoi du message.';
            }
        }
    }
}

/* -----------------------------------------------------------------------
 * Chargement du fil de messages
 * ----------------------------------------------------------------------- */
$messages = [];
if ($caseId > 0) {
    try {
        $db = bofa_db();

        /* Marquer les messages comme lus */
        $stmtRead = $db->prepare(
            "UPDATE messages SET is_read = 1, read_at = NOW()
             WHERE case_id = :cid AND sender_id != :uid AND is_read = 0"
        );
        $stmtRead->execute([':cid' => $caseId, ':uid' => $userId]);

        /* Charger les messages */
        $stmtMsg = $db->prepare(
            "SELECT m.*, u.prenom, u.nom, u.role,
                    d.original_name AS attach_name, d.id AS doc_id
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             LEFT JOIN documents d ON d.id = m.attachment_id
             WHERE m.case_id = :cid
             ORDER BY m.created_at ASC
             LIMIT 200"
        );
        $stmtMsg->execute([':cid' => $caseId]);
        $messages = $stmtMsg->fetchAll();
    } catch (PDOException $e) {
        error_log('[messages.php] Erreur chargement messages : ' . $e->getMessage());
    }
}

$pageTitle   = 'Messagerie sécurisée';
$currentPage = 'messages';
require_once BOFA_ROOT . '/templates/header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold text-bofa-bleu">
                <i class="fa-regular fa-envelope me-2"></i>Messagerie sécurisée
            </h1>
            <p class="text-muted small mb-0">Communiquez directement avec votre agent de conformité.</p>
        </div>
    </div>

    <?php foreach ($errors as $err): ?>
        <?php $alertType = 'danger'; $alertMessage = $err; require BOFA_ROOT . '/templates/partials/alert.php'; ?>
    <?php endforeach; ?>

    <div class="row g-4">

        <!-- ----------------------------------------------------------------
             Sélecteur de dossier (colonne gauche)
             ---------------------------------------------------------------- -->
        <div class="col-12 col-lg-3">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3">
                    <h2 class="h6 mb-0 fw-semibold">Mes dossiers</h2>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($dossiers)): ?>
                    <div class="list-group-item text-muted small py-3">Aucun dossier.</div>
                    <?php endif; ?>
                    <?php foreach ($dossiers as $d):
                        $isActive  = (int)$d['id'] === $caseId;
                        /* Compter les messages non lus */
                        $unread = 0;
                        try {
                            $db = bofa_db();
                            $stmtUr = $db->prepare(
                                "SELECT COUNT(*) FROM messages
                                 WHERE case_id = :cid AND sender_id != :uid AND is_read = 0"
                            );
                            $stmtUr->execute([':cid' => (int)$d['id'], ':uid' => $userId]);
                            $unread = (int) $stmtUr->fetchColumn();
                        } catch (PDOException $e) { /* silencieux */ }
                    ?>
                    <a href="/bofa/client/messages.php?case_id=<?= (int)$d['id'] ?>"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-start py-3
                              <?= $isActive ? 'active' : '' ?>">
                        <div>
                            <div class="fw-semibold small">
                                <?= bofa_sanitize($d['case_number'] ?? 'AML-EDD-?') ?>
                            </div>
                            <small class="<?= $isActive ? 'text-white-50' : 'text-muted' ?>">
                                <?= number_format((float)($d['montant'] ?? 0), 0, ',', ' ') ?> <?= bofa_sanitize($d['devise'] ?? 'EUR') ?>
                            </small>
                        </div>
                        <?php if ($unread > 0): ?>
                        <span class="badge bg-danger rounded-pill mt-1"><?= $unread ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ----------------------------------------------------------------
             Fil de messages (colonne centrale/droite)
             ---------------------------------------------------------------- -->
        <div class="col-12 col-lg-9">
            <?php if ($dossierCourant === null): ?>
            <div class="card shadow-sm border-0 text-center py-5">
                <div class="card-body">
                    <i class="fa-regular fa-envelope fa-4x text-muted opacity-25 mb-3"></i>
                    <h3 class="h5 text-muted">Sélectionnez un dossier</h3>
                    <p class="text-muted small">Choisissez un dossier dans la liste pour afficher la conversation.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="card shadow-sm border-0 d-flex flex-column" style="height:calc(100vh - 200px);">

                <!-- En-tête du fil -->
                <div class="card-header bg-transparent py-3 d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:40px;height:40px;background:var(--bofa-bleu);color:#fff;">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <div>
                        <p class="fw-semibold mb-0">
                            <?php
                            $agentNom = trim(($dossierCourant['agent_prenom'] ?? '') . ' ' . ($dossierCourant['agent_nom'] ?? ''));
                            echo $agentNom ? bofa_sanitize($agentNom) : '<em class="text-muted">Agent non assigné</em>';
                            ?>
                        </p>
                        <small class="text-muted">
                            Dossier <?= bofa_sanitize($dossierCourant['case_number'] ?? '') ?>
                        </small>
                    </div>
                </div>

                <!-- Zone de messages scrollable -->
                <div class="card-body overflow-auto flex-grow-1 p-3" id="msgThread">
                    <?php if (empty($messages)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fa-regular fa-comments fa-3x opacity-25 mb-2"></i>
                        <p class="small">Aucun message pour l'instant. Démarrez la conversation.</p>
                    </div>
                    <?php endif; ?>

                    <?php foreach ($messages as $msg):
                        $isMe       = (int)$msg['sender_id'] === $userId;
                        $senderName = bofa_sanitize(($msg['prenom'] ?? '') . ' ' . ($msg['nom'] ?? ''));
                        $dateFmt    = isset($msg['created_at'])
                            ? date('d/m/Y H:i', strtotime($msg['created_at']))
                            : '—';
                        $isUnread   = !(bool)$msg['is_read'] && !$isMe;
                    ?>
                    <div class="d-flex mb-3 <?= $isMe ? 'justify-content-end' : 'justify-content-start' ?>">
                        <?php if (!$isMe): ?>
                        <div class="rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0"
                             style="width:32px;height:32px;background:var(--bofa-bleu);color:#fff;font-size:.75rem;">
                            <?= mb_strtoupper(mb_substr($msg['prenom'] ?? '?', 0, 1)) ?>
                        </div>
                        <?php endif; ?>

                        <div style="max-width:70%;">
                            <div class="rounded-3 p-3 shadow-sm
                                <?= $isMe ? 'text-white' : 'bg-light' ?>"
                                 style="<?= $isMe ? 'background:var(--bofa-bleu);' : '' ?>">
                                <p class="mb-1 small" style="white-space:pre-wrap;">
                                    <?= bofa_sanitize($msg['contenu'] ?? '') ?>
                                </p>
                                <?php if (!empty($msg['attach_name'])): ?>
                                <div class="mt-2 pt-2 border-top <?= $isMe ? 'border-white border-opacity-25' : '' ?>">
                                    <i class="fa-solid fa-paperclip me-1"></i>
                                    <small><?= bofa_sanitize($msg['attach_name']) ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-1 mt-1
                                <?= $isMe ? 'justify-content-end' : '' ?>">
                                <small class="text-muted" style="font-size:.7rem;"><?= $dateFmt ?></small>
                                <?php if ($isMe): ?>
                                <i class="fa-solid fa-check-double <?= $msg['is_read'] ? 'text-primary' : 'text-muted' ?>"
                                   style="font-size:.65rem;"
                                   title="<?= $msg['is_read'] ? 'Lu' : 'Non lu' ?>"></i>
                                <?php endif; ?>
                                <?php if ($isUnread): ?>
                                <span class="badge bg-primary rounded-pill" style="font-size:.6rem;">Nouveau</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Formulaire d'envoi -->
                <div class="card-footer bg-transparent p-3">
                    <form method="POST"
                          action="/bofa/client/messages.php"
                          enctype="multipart/form-data"
                          class="d-flex flex-column gap-2">
                        <input type="hidden" name="action"     value="send">
                        <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                        <input type="hidden" name="case_id"    value="<?= $caseId ?>">

                        <div class="d-flex gap-2 align-items-end">
                            <div class="flex-grow-1">
                                <textarea name="message"
                                          class="form-control form-control-sm"
                                          rows="3"
                                          placeholder="Rédigez votre message… (Shift+Entrée pour sauter une ligne)"
                                          maxlength="4000"
                                          required
                                          id="msgInput"></textarea>
                            </div>
                            <button type="submit" class="btn btn-bofa align-self-end" title="Envoyer">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>

                        <!-- Pièce jointe -->
                        <div class="d-flex align-items-center gap-2">
                            <label class="btn btn-sm btn-outline-secondary mb-0" for="attachment" title="Joindre un fichier">
                                <i class="fa-solid fa-paperclip"></i>
                            </label>
                            <input type="file"
                                   name="attachment"
                                   id="attachment"
                                   class="d-none"
                                   accept=".pdf,.jpg,.jpeg,.png,.docx">
                            <span class="text-muted small" id="attachName">Aucune pièce jointe</span>
                        </div>
                    </form>
                </div>

            </div><!-- /.card -->
            <?php endif; ?>
        </div><!-- /.col messages -->
    </div><!-- /.row -->
</div><!-- /.container-fluid -->

<script>
/* Défilement automatique vers le bas du fil */
(function () {
    const thread = document.getElementById('msgThread');
    if (thread) thread.scrollTop = thread.scrollHeight;

    /* Nom de la pièce jointe */
    const att = document.getElementById('attachment');
    if (att) {
        att.addEventListener('change', () => {
            document.getElementById('attachName').textContent =
                att.files[0]?.name ?? 'Aucune pièce jointe';
        });
    }

    /* Envoi par Ctrl+Entrée */
    const msgInput = document.getElementById('msgInput');
    if (msgInput) {
        msgInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.ctrlKey) {
                e.target.closest('form').requestSubmit();
            }
        });
    }
})();
</script>

<?php require_once BOFA_ROOT . '/templates/footer.php'; ?>
