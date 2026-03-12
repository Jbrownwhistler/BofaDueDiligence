<?php
/**
 * Détail dossier AML — agent (F13–F17, F37, F40, F42, F44, F45) — BofaDueDiligence
 * Interface à 5 onglets : Récapitulatif, Documents, Checklist, Messagerie, Journal d'audit.
 */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';

bofa_auth_check(['agent']);

require_once BOFA_ROOT . '/src/CaseAML.php';
require_once BOFA_ROOT . '/src/Document.php';
require_once BOFA_ROOT . '/src/AuditLog.php';
require_once BOFA_ROOT . '/src/Notification.php';

$agentId  = (int) $_SESSION['user_id'];
$caseObj  = new CaseAML();
$docObj   = new Document();
$auditObj = new AuditLog();
$notifObj = new Notification();
$notifCount = $notifObj->getUnreadCount($agentId);
$errors   = [];

/* -----------------------------------------------------------------------
 * Récupération du dossier
 * ----------------------------------------------------------------------- */
$caseId  = (int) ($_GET['id'] ?? 0);
$dossier = $caseId > 0 ? $caseObj->getById($caseId) : null;

if (!$dossier) {
    bofa_flash('Dossier introuvable.', 'error');
    bofa_redirect('/bofa/agent/dossiers.php');
}

/* -----------------------------------------------------------------------
 * Traitement POST — actions variées
 * ----------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action']     ?? '';

    if (!bofa_csrf_validate($token)) {
        $errors[] = 'Jeton de sécurité invalide.';
    } else {
        $ok = false;
        $db = bofa_db();

        switch ($action) {

            /* Changement de statut */
            case 'update_status':
                $newStatus = $_POST['new_status'] ?? '';
                $comment   = trim($_POST['comment'] ?? '');
                $allowed   = ['en_cours','en_attente_doc','pret_transfert','gele','rejete','archive'];
                if (in_array($newStatus, $allowed, true)) {
                    $ok = $caseObj->updateStatus($caseId, $newStatus, $agentId, $comment);
                } else { $errors[] = 'Statut invalide.'; }
                break;

            /* Valider document */
            case 'validate_doc':
                $docId = (int)($_POST['doc_id'] ?? 0);
                if ($docId > 0) $ok = $docObj->validate($docId, $agentId);
                break;

            /* Rejeter document */
            case 'reject_doc':
                $docId  = (int)($_POST['doc_id'] ?? 0);
                $reason = trim($_POST['reject_reason'] ?? '');
                if ($docId > 0 && $reason !== '') {
                    $ok = $docObj->reject($docId, $agentId, $reason);
                } else { $errors[] = 'Motif de rejet requis.'; }
                break;

            /* Sauvegarder checklist */
            case 'save_checklist':
                $items  = $_POST['cl_label'] ?? [];
                $types  = $_POST['cl_type']  ?? [];
                $checks = $_POST['cl_done']  ?? [];
                try {
                    $db->prepare("DELETE FROM checklist_items WHERE case_id = :cid")->execute([':cid' => $caseId]);
                    $stmtCl = $db->prepare(
                        "INSERT INTO checklist_items (case_id, label, type, is_completed, position, created_at)
                         VALUES (:cid, :label, :type, :done, :pos, NOW())"
                    );
                    foreach ($items as $i => $label) {
                        $label = trim($label);
                        if ($label === '') continue;
                        $stmtCl->execute([
                            ':cid'   => $caseId,
                            ':label' => mb_substr($label, 0, 255),
                            ':type'  => in_array($types[$i] ?? '', ['document','required'], true) ? $types[$i] : 'required',
                            ':done'  => isset($checks[$i]) ? 1 : 0,
                            ':pos'   => $i,
                        ]);
                    }
                    $ok = true;
                } catch (PDOException $e) {
                    error_log('[dossier-detail] checklist : ' . $e->getMessage());
                    $errors[] = 'Erreur lors de la sauvegarde de la checklist.';
                }
                break;

            /* Envoyer message */
            case 'send_message':
                $contenu = trim($_POST['message'] ?? '');
                if (mb_strlen($contenu) < 2) {
                    $errors[] = 'Message vide.';
                } elseif (mb_strlen($contenu) > 4000) {
                    $errors[] = 'Message trop long.';
                } else {
                    try {
                        $stmtMsg = $db->prepare(
                            "INSERT INTO messages (case_id, sender_id, contenu, created_at)
                             VALUES (:cid, :sid, :contenu, NOW())"
                        );
                        $stmtMsg->execute([':cid' => $caseId, ':sid' => $agentId, ':contenu' => $contenu]);
                        /* Notifier le client */
                        $notifObj->send(
                            (int)$dossier['client_id'],
                            'Nouveau message de votre agent sur le dossier ' . $dossier['case_number'],
                            'message',
                            $caseId
                        );
                        $ok = true;
                    } catch (PDOException $e) {
                        error_log('[dossier-detail] message : ' . $e->getMessage());
                        $errors[] = 'Erreur lors de l\'envoi.';
                    }
                }
                break;

            /* Ajouter note interne */
            case 'add_note':
                $note = trim($_POST['note'] ?? '');
                if (mb_strlen($note) < 2) {
                    $errors[] = 'Note vide.';
                } else {
                    $ok = $caseObj->addInternalNote($caseId, $agentId, $note);
                }
                break;

            /* Geler / dégeler */
            case 'freeze':   $ok = $caseObj->freeze($caseId, $agentId);   break;
            case 'unfreeze': $ok = $caseObj->unfreeze($caseId, $agentId); break;

            /* Valider transfert */
            case 'validate_transfer': $ok = $caseObj->validateTransfer($caseId, $agentId); break;

            /* Rejeter dossier */
            case 'reject_case':
                $reason = trim($_POST['reject_reason'] ?? '');
                if (mb_strlen($reason) < 5) {
                    $errors[] = 'Motif de rejet trop court (5 caractères minimum).';
                } else {
                    $ok = $caseObj->reject($caseId, $agentId, $reason);
                }
                break;

            /* Déclarer conflit (F44) */
            case 'declare_conflict':
                require_once BOFA_ROOT . '/src/User.php';
                $userObj = new User();
                $ok = $userObj->declareConflict($agentId);
                break;

            /* Ajouter / supprimer tag */
            case 'add_tag':
                $tagId = (int)($_POST['tag_id'] ?? 0);
                if ($tagId > 0) $ok = $caseObj->addTag($caseId, $tagId, $agentId);
                break;
            case 'remove_tag':
                $tagId = (int)($_POST['tag_id'] ?? 0);
                if ($tagId > 0) $ok = $caseObj->removeTag($caseId, $tagId);
                break;
        }

        if ($ok && empty($errors)) {
            bofa_flash('Action effectuée avec succès.', 'success');
            $activeTab = $_POST['active_tab'] ?? 'recap';
            bofa_redirect('/bofa/agent/dossier-detail.php?id=' . $caseId . '&tab=' . $activeTab);
        } elseif (!$ok && empty($errors)) {
            $errors[] = 'L\'action a échoué.';
        }
    }

    /* Rechargement après action */
    $dossier = $caseObj->getById($caseId) ?? $dossier;
}

/* -----------------------------------------------------------------------
 * Chargement des données annexes
 * ----------------------------------------------------------------------- */
$timeline     = $caseObj->getTimeline($caseId);
$similaires   = $caseObj->getSimilarCases($caseId);
$documents    = $docObj->getByCaseId($caseId);
$auditLogs    = $auditObj->getForCase($caseId);
$tags         = $caseObj->getTags($caseId);

/* Checklist */
$checklist = [];
try {
    $db = bofa_db();
    $stmtCl = $db->prepare(
        "SELECT id, label, type, is_completed FROM checklist_items
         WHERE case_id = :cid ORDER BY position ASC"
    );
    $stmtCl->execute([':cid' => $caseId]);
    $checklist = $stmtCl->fetchAll();
} catch (PDOException $e) { /* silencieux */ }

/* Messages — marquer lus */
$messages = [];
try {
    $db->prepare("UPDATE messages SET is_read=1, read_at=NOW()
                  WHERE case_id=:cid AND sender_id != :aid AND is_read=0")
       ->execute([':cid' => $caseId, ':aid' => $agentId]);

    $stmtMsg = $db->prepare(
        "SELECT m.*, u.prenom, u.nom, u.role
         FROM messages m JOIN users u ON u.id = m.sender_id
         WHERE m.case_id = :cid ORDER BY m.created_at ASC LIMIT 200"
    );
    $stmtMsg->execute([':cid' => $caseId]);
    $messages = $stmtMsg->fetchAll();
} catch (PDOException $e) { error_log('[dossier-detail] messages : ' . $e->getMessage()); }

/* Notes internes */
$notes = [];
try {
    $stmtNotes = $db->prepare(
        "SELECT n.*, u.prenom, u.nom FROM internal_notes n
         JOIN users u ON u.id = n.agent_id
         WHERE n.case_id = :cid ORDER BY n.created_at DESC LIMIT 50"
    );
    $stmtNotes->execute([':cid' => $caseId]);
    $notes = $stmtNotes->fetchAll();
} catch (PDOException $e) { /* silencieux */ }

/* Tous les tags disponibles */
$allTags = [];
try {
    $allTags = $db->query("SELECT id, label, color FROM tags ORDER BY label")->fetchAll();
} catch (PDOException $e) { /* silencieux */ }

$activeTab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'recap');

$statusBadge = [
    'nouveau'            => ['bg-secondary',         'Nouveau'],
    'en_cours'           => ['bg-primary',            'En cours'],
    'en_attente_doc'     => ['bg-warning text-dark',  'Docs demandés'],
    'pret_transfert'     => ['bg-success',            'Prêt au virement'],
    'transfert_effectue' => ['bg-success',            'Transféré'],
    'gele'               => ['bg-danger',             'Gelé'],
    'rejete'             => ['bg-danger',             'Rejeté'],
    'archive'            => ['bg-light text-dark',    'Archivé'],
];
$badge = $statusBadge[$dossier['status']] ?? ['bg-secondary', $dossier['status']];

$pageTitle   = 'Dossier ' . ($dossier['case_number'] ?? '');
$currentPage = 'dossiers';
require_once BOFA_ROOT . '/templates/header.php';
?>

<div class="container-fluid py-4">

    <!-- En-tête dossier -->
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/bofa/agent/dossiers.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <h1 class="h4 mb-0 fw-bold font-monospace text-bofa-bleu">
                    <?= bofa_sanitize($dossier['case_number'] ?? '') ?>
                </h1>
                <span class="badge <?= $badge[0] ?>"><?= htmlspecialchars($badge[1], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($dossier['sanction_flag'])): ?>
                <span class="badge bg-danger">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>SANCTION
                </span>
                <?php endif; ?>
            </div>
            <small class="text-muted">
                Client : <?= bofa_sanitize(($dossier['client_prenom'] ?? '') . ' ' . ($dossier['client_nom'] ?? '')) ?>
                — Créé le <?= isset($dossier['created_at']) ? date('d/m/Y', strtotime($dossier['created_at'])) : '—' ?>
            </small>
        </div>

        <!-- Actions rapides en-tête -->
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($dossier['status'] === 'pret_transfert'): ?>
            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalValidateTransfer">
                <i class="fa-solid fa-check me-1"></i>Valider transfert
            </button>
            <?php endif; ?>
            <?php if ($dossier['status'] !== 'gele'): ?>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action"     value="freeze">
                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                <input type="hidden" name="active_tab" value="<?= $activeTab ?>">
                <button type="submit" class="btn btn-sm btn-warning">
                    <i class="fa-solid fa-snowflake me-1"></i>Geler
                </button>
            </form>
            <?php else: ?>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action"     value="unfreeze">
                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                <input type="hidden" name="active_tab" value="<?= $activeTab ?>">
                <button type="submit" class="btn btn-sm btn-outline-warning">
                    <i class="fa-solid fa-fire me-1"></i>Dégeler
                </button>
            </form>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalRejectCase">
                <i class="fa-solid fa-xmark me-1"></i>Rejeter
            </button>
        </div>
    </div>

    <?php foreach ($errors as $err): ?>
        <?php $alertType = 'danger'; $alertMessage = $err; require BOFA_ROOT . '/templates/partials/alert.php'; ?>
    <?php endforeach; ?>

    <!-- ----------------------------------------------------------------
         Frise chronologique (F37)
         ---------------------------------------------------------------- -->
    <?php if (!empty($timeline)): ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3 overflow-auto">
            <div class="d-flex align-items-center gap-0" style="min-width:max-content;">
            <?php foreach ($timeline as $i => $tl):
                $tDate = isset($tl['created_at']) ? date('d/m H:i', strtotime($tl['created_at'])) : '—';
            ?>
            <?php if ($i > 0): ?>
            <div style="width:40px;height:2px;background:var(--bs-border-color);flex-shrink:0;"></div>
            <?php endif; ?>
            <div class="d-flex flex-column align-items-center text-center" style="min-width:90px;">
                <div class="rounded-circle d-flex align-items-center justify-content-center mb-1"
                     style="width:28px;height:28px;background:var(--bofa-bleu);color:#fff;font-size:.6rem;">
                    <?= $i + 1 ?>
                </div>
                <small class="fw-semibold" style="font-size:.65rem;line-height:1.2;">
                    <?= bofa_sanitize($tl['status_new'] ?? $tl['action'] ?? '?') ?>
                </small>
                <small class="text-muted" style="font-size:.6rem;"><?= $tDate ?></small>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ----------------------------------------------------------------
         Onglets
         ---------------------------------------------------------------- -->
    <ul class="nav nav-tabs mb-4" id="caseTabs" role="tablist">
        <?php
        $tabs = [
            'recap'    => ['fa-clipboard-list',  'Récapitulatif'],
            'docs'     => ['fa-folder-open',     'Documents (' . count($documents) . ')'],
            'checklist'=> ['fa-list-check',      'Checklist'],
            'messages' => ['fa-envelope',        'Messagerie'],
            'audit'    => ['fa-scroll',          'Journal d\'audit'],
        ];
        foreach ($tabs as $key => [$ico, $label]):
            $isActive = $activeTab === $key || ($activeTab === '' && $key === 'recap');
        ?>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $isActive ? 'active' : '' ?>"
               href="/bofa/agent/dossier-detail.php?id=<?= $caseId ?>&tab=<?= $key ?>"
               role="tab">
                <i class="fa-solid <?= $ico ?> me-1"></i><?= $label ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">

        <!-- ============================================================
             TAB 1 — Récapitulatif (F14)
             ============================================================ -->
        <div class="tab-pane fade <?= ($activeTab === 'recap' || $activeTab === '') ? 'show active' : '' ?>" id="pane-recap">
            <div class="row g-4">

                <!-- Informations du dossier -->
                <div class="col-12 col-lg-8">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-transparent py-3">
                            <h2 class="h6 mb-0 fw-semibold">
                                <i class="fa-solid fa-circle-info me-2 text-bofa-bleu"></i>Informations du dossier
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <dl class="row mb-0 small">
                                        <dt class="col-sm-6 text-muted">Émetteur</dt>
                                        <dd class="col-sm-6"><?= bofa_sanitize($dossier['emetteur'] ?? '—') ?></dd>
                                        <dt class="col-sm-6 text-muted">Bénéficiaire</dt>
                                        <dd class="col-sm-6"><?= bofa_sanitize($dossier['beneficiaire'] ?? '—') ?></dd>
                                        <dt class="col-sm-6 text-muted">Banque émettrice</dt>
                                        <dd class="col-sm-6"><?= bofa_sanitize($dossier['banque_emettrice'] ?? '—') ?></dd>
                                        <dt class="col-sm-6 text-muted">Banque bénéficiaire</dt>
                                        <dd class="col-sm-6"><?= bofa_sanitize($dossier['banque_beneficiaire'] ?? '—') ?></dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <dl class="row mb-0 small">
                                        <dt class="col-sm-6 text-muted">Pays émetteur</dt>
                                        <dd class="col-sm-6"><?= bofa_sanitize($dossier['pays_emetteur'] ?? '—') ?></dd>
                                        <dt class="col-sm-6 text-muted">Pays bénéficiaire</dt>
                                        <dd class="col-sm-6"><?= bofa_sanitize($dossier['pays_beneficiaire'] ?? '—') ?></dd>
                                        <dt class="col-sm-6 text-muted">Type d'actif</dt>
                                        <dd class="col-sm-6"><?= bofa_sanitize($dossier['type_actif'] ?? '—') ?></dd>
                                        <dt class="col-sm-6 text-muted">Montant</dt>
                                        <dd class="col-sm-6 fw-bold">
                                            <?= number_format((float)($dossier['montant']??0),2,',',' ') ?>
                                            <?= bofa_sanitize($dossier['devise'] ?? 'EUR') ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Score de risque -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-transparent py-3">
                            <h2 class="h6 mb-0 fw-semibold">
                                <i class="fa-solid fa-gauge me-2 text-bofa-bleu"></i>Score de risque
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-4 flex-wrap">
                                <?php
                                $score = (float)($dossier['score_risque'] ?? 0);
                                $score_display = $score;
                                $showLabel = true; $showIcon = true;
                                require BOFA_ROOT . '/templates/partials/score-badge.php';
                                ?>
                                <div class="flex-grow-1">
                                    <div class="progress" style="height:12px;">
                                        <?php
                                        $barClass = $score >= 75 ? 'bg-danger' : ($score >= 41 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <div class="progress-bar <?= $barClass ?>"
                                             style="width:<?= $score ?>%"
                                             title="<?= $score ?>/100"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-muted">0 – Faible</small>
                                        <small class="fw-bold"><?= $score ?>/100</small>
                                        <small class="text-muted">100 – Élevé</small>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($dossier['sanction_flag'])): ?>
                            <div class="alert alert-danger mt-3 mb-0 py-2 small">
                                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                                <strong>Alerte sanctions :</strong> Ce dossier est signalé dans les listes de sanctions internationales.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Notes internes -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between">
                            <h2 class="h6 mb-0 fw-semibold">
                                <i class="fa-regular fa-note-sticky me-2 text-bofa-bleu"></i>
                                Notes internes <small class="text-muted">(invisibles du client)</small>
                            </h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action"     value="add_note">
                                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                                <input type="hidden" name="active_tab" value="recap">
                                <div class="d-flex gap-2">
                                    <textarea name="note" class="form-control form-control-sm" rows="2"
                                              placeholder="Ajouter une note interne…" maxlength="2000" required></textarea>
                                    <button type="submit" class="btn btn-sm btn-outline-primary align-self-end">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                            </form>
                            <?php foreach ($notes as $n): ?>
                            <div class="border rounded-2 p-2 mb-2 bg-light small">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong><?= bofa_sanitize(($n['prenom'] ?? '') . ' ' . ($n['nom'] ?? '')) ?></strong>
                                    <span class="text-muted" style="font-size:.7rem;">
                                        <?= isset($n['created_at']) ? date('d/m/Y H:i', strtotime($n['created_at'])) : '—' ?>
                                    </span>
                                </div>
                                <p class="mb-0" style="white-space:pre-wrap;"><?= bofa_sanitize($n['contenu'] ?? '') ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div><!-- /.col-8 -->

                <!-- Colonne latérale récap -->
                <div class="col-12 col-lg-4">

                    <!-- Changement de statut -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-transparent py-3">
                            <h2 class="h6 mb-0 fw-semibold">Changer le statut</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action"     value="update_status">
                                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                                <input type="hidden" name="active_tab" value="recap">
                                <div class="mb-2">
                                    <select name="new_status" class="form-select form-select-sm mb-2">
                                        <?php foreach (['en_cours','en_attente_doc','pret_transfert','gele','rejete','archive'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $dossier['status'] === $s ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($statusBadge[$s][1], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <textarea name="comment" class="form-control form-control-sm" rows="2"
                                              placeholder="Commentaire (optionnel)" maxlength="500"></textarea>
                                </div>
                                <button type="submit" class="btn btn-sm btn-bofa w-100">
                                    <i class="fa-solid fa-floppy-disk me-1"></i>Appliquer
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-transparent py-3">
                            <h2 class="h6 mb-0 fw-semibold"><i class="fa-solid fa-tags me-2"></i>Tags</h2>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <?php foreach ($tags as $t): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action"     value="remove_tag">
                                    <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                                    <input type="hidden" name="active_tab" value="recap">
                                    <input type="hidden" name="tag_id"     value="<?= (int)$t['id'] ?>">
                                    <button type="submit" class="badge text-decoration-none border-0"
                                            style="background:<?= bofa_sanitize($t['color'] ?? '#6c757d') ?>;color:#fff;cursor:pointer;">
                                        <?= bofa_sanitize($t['label']) ?> ×
                                    </button>
                                </form>
                                <?php endforeach; ?>
                            </div>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="action"     value="add_tag">
                                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                                <input type="hidden" name="active_tab" value="recap">
                                <select name="tag_id" class="form-select form-select-sm">
                                    <option value="">Ajouter un tag…</option>
                                    <?php
                                    $existingTagIds = array_column($tags, 'id');
                                    foreach ($allTags as $t):
                                        if (in_array((int)$t['id'], array_map('intval', $existingTagIds), true)) continue;
                                    ?>
                                    <option value="<?= (int)$t['id'] ?>"><?= bofa_sanitize($t['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Déclarer conflit (F44) -->
                    <div class="card shadow-sm border-0 mb-4 border-warning">
                        <div class="card-body py-2">
                            <form method="POST">
                                <input type="hidden" name="action"     value="declare_conflict">
                                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                                <input type="hidden" name="active_tab" value="recap">
                                <button type="submit" class="btn btn-sm btn-outline-warning w-100"
                                        onclick="return confirm('Confirmer la déclaration de conflit d\'intérêts ?')">
                                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Déclarer conflit d'intérêts
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Dossiers similaires (F45) -->
                    <?php if (!empty($similaires)): ?>
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-transparent py-3">
                            <h2 class="h6 mb-0 fw-semibold">
                                <i class="fa-solid fa-copy me-2 text-bofa-bleu"></i>Dossiers similaires
                            </h2>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($similaires, 0, 5) as $sim): ?>
                            <a href="/bofa/agent/dossier-detail.php?id=<?= (int)$sim['id'] ?>"
                               class="list-group-item list-group-item-action small py-2">
                                <span class="font-monospace"><?= bofa_sanitize($sim['case_number'] ?? '') ?></span>
                                <span class="badge bg-secondary ms-1">
                                    <?= number_format((float)($sim['montant']??0),0,',',' ') ?> <?= bofa_sanitize($sim['devise']??'EUR') ?>
                                </span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- /.col-4 -->
            </div><!-- /.row recap -->
        </div><!-- /#pane-recap -->

        <!-- ============================================================
             TAB 2 — Documents (F15)
             ============================================================ -->
        <div class="tab-pane fade <?= $activeTab === 'docs' ? 'show active' : '' ?>" id="pane-docs">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($documents)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-folder-open fa-3x opacity-25 mb-3"></i>
                        <p>Aucun document pour ce dossier.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Fichier</th>
                                    <th>Type</th>
                                    <th>Téléversé par</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th class="pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $docBadge = [
                                'en_attente' => ['bg-secondary', 'En attente'],
                                'valide'     => ['bg-success',   'Validé'],
                                'rejete'     => ['bg-danger',    'Rejeté'],
                                'demande'    => ['bg-warning text-dark', 'Demandé'],
                            ];
                            foreach ($documents as $doc):
                                $dbg = $docBadge[$doc['status'] ?? 'en_attente'] ?? ['bg-secondary', '?'];
                                $ext = strtolower(pathinfo($doc['original_name'] ?? '', PATHINFO_EXTENSION));
                                $iconFile = match($ext) {
                                    'pdf'         => 'fa-file-pdf text-danger',
                                    'jpg', 'jpeg' => 'fa-file-image text-warning',
                                    'png'         => 'fa-file-image text-info',
                                    'docx', 'doc' => 'fa-file-word text-primary',
                                    default       => 'fa-file text-secondary',
                                };
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <i class="fa-solid <?= $iconFile ?> me-1"></i>
                                    <?= bofa_sanitize($doc['original_name'] ?? 'Document') ?>
                                </td>
                                <td><?= bofa_sanitize($doc['mime_type'] ?? '—') ?></td>
                                <td><?= bofa_sanitize(($doc['uploader_prenom'] ?? '') . ' ' . ($doc['uploader_nom'] ?? '')) ?></td>
                                <td class="text-muted">
                                    <?= isset($doc['created_at']) ? date('d/m/Y H:i', strtotime($doc['created_at'])) : '—' ?>
                                </td>
                                <td>
                                    <span class="badge <?= $dbg[0] ?>"><?= htmlspecialchars($dbg[1], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if (!empty($doc['rejection_reason'])): ?>
                                    <i class="fa-solid fa-circle-info ms-1 text-muted"
                                       title="<?= bofa_sanitize($doc['rejection_reason']) ?>"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3">
                                    <div class="d-flex gap-1">
                                        <?php if (($doc['status'] ?? '') !== 'valide'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action"     value="validate_doc">
                                            <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                                            <input type="hidden" name="doc_id"     value="<?= (int)$doc['id'] ?>">
                                            <input type="hidden" name="active_tab" value="docs">
                                            <button type="submit" class="btn btn-xs btn-success" title="Valider">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <?php if (($doc['status'] ?? '') !== 'rejete'): ?>
                                        <button class="btn btn-xs btn-outline-danger" title="Rejeter"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalRejectDoc"
                                                data-doc-id="<?= (int)$doc['id'] ?>">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /#pane-docs -->

        <!-- ============================================================
             TAB 3 — Checklist (F16)
             ============================================================ -->
        <div class="tab-pane fade <?= $activeTab === 'checklist' ? 'show active' : '' ?>" id="pane-checklist">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3">
                    <h2 class="h6 mb-0 fw-semibold">
                        <i class="fa-solid fa-list-check me-2 text-bofa-bleu"></i>Checklist de conformité
                    </h2>
                </div>
                <div class="card-body">
                    <form method="POST" id="checklistForm">
                        <input type="hidden" name="action"     value="save_checklist">
                        <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                        <input type="hidden" name="active_tab" value="checklist">

                        <div id="clItems">
                            <?php foreach ($checklist as $i => $item): ?>
                            <div class="d-flex align-items-center gap-2 mb-2 cl-row">
                                <input type="checkbox" name="cl_done[<?= $i ?>]"
                                       class="form-check-input mt-0"
                                       <?= (int)$item['is_completed'] ? 'checked' : '' ?>>
                                <input type="text" name="cl_label[<?= $i ?>]"
                                       class="form-control form-control-sm flex-grow-1"
                                       value="<?= bofa_sanitize($item['label']) ?>"
                                       maxlength="255" required>
                                <select name="cl_type[<?= $i ?>]" class="form-select form-select-sm" style="width:120px;">
                                    <option value="required" <?= ($item['type'] ?? '') !== 'document' ? 'selected' : '' ?>>Requis</option>
                                    <option value="document" <?= ($item['type'] ?? '') === 'document' ? 'selected' : '' ?>>Document</option>
                                </select>
                                <button type="button" class="btn btn-xs btn-outline-danger cl-remove"
                                        onclick="this.closest('.cl-row').remove()">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="button" id="btnAddCl" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-plus me-1"></i>Ajouter un élément
                            </button>
                            <button type="submit" class="btn btn-sm btn-bofa">
                                <i class="fa-solid fa-floppy-disk me-1"></i>Sauvegarder la checklist
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div><!-- /#pane-checklist -->

        <!-- ============================================================
             TAB 4 — Messagerie (F07)
             ============================================================ -->
        <div class="tab-pane fade <?= $activeTab === 'messages' ? 'show active' : '' ?>" id="pane-messages">
            <div class="card shadow-sm border-0 d-flex flex-column" style="height:500px;">
                <div class="card-body overflow-auto flex-grow-1 p-3" id="msgThread">
                    <?php if (empty($messages)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fa-regular fa-comments fa-3x opacity-25 mb-2"></i>
                        <p class="small">Aucun message.</p>
                    </div>
                    <?php endif; ?>
                    <?php foreach ($messages as $msg):
                        $isMe       = (int)$msg['sender_id'] === $agentId;
                        $senderName = bofa_sanitize(($msg['prenom'] ?? '') . ' ' . ($msg['nom'] ?? ''));
                        $dateFmt    = isset($msg['created_at']) ? date('d/m/Y H:i', strtotime($msg['created_at'])) : '—';
                        $roleLabel  = match($msg['role'] ?? '') {
                            'agent' => '<span class="badge bg-primary ms-1" style="font-size:.6rem;">Agent</span>',
                            'client' => '<span class="badge bg-secondary ms-1" style="font-size:.6rem;">Client</span>',
                            default => '',
                        };
                    ?>
                    <div class="d-flex mb-3 <?= $isMe ? 'justify-content-end' : 'justify-content-start' ?>">
                        <?php if (!$isMe): ?>
                        <div class="rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0"
                             style="width:32px;height:32px;background:#6c757d;color:#fff;font-size:.75rem;">
                            <?= mb_strtoupper(mb_substr($msg['prenom'] ?? '?', 0, 1)) ?>
                        </div>
                        <?php endif; ?>
                        <div style="max-width:70%;">
                            <div class="mb-1 small">
                                <?= $senderName ?><?= $roleLabel ?>
                            </div>
                            <div class="rounded-3 p-3 shadow-sm <?= $isMe ? 'text-white' : 'bg-light' ?>"
                                 style="<?= $isMe ? 'background:var(--bofa-bleu);' : '' ?>">
                                <p class="mb-0 small" style="white-space:pre-wrap;">
                                    <?= bofa_sanitize($msg['contenu'] ?? '') ?>
                                </p>
                            </div>
                            <small class="text-muted d-block mt-1 <?= $isMe ? 'text-end' : '' ?>"
                                   style="font-size:.65rem;"><?= $dateFmt ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer bg-transparent p-3">
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="action"     value="send_message">
                        <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                        <input type="hidden" name="active_tab" value="messages">
                        <textarea name="message" class="form-control form-control-sm" rows="2"
                                  placeholder="Rédigez votre message… (Ctrl+Entrée pour envoyer)"
                                  maxlength="4000" required id="agentMsgInput"></textarea>
                        <button type="submit" class="btn btn-bofa align-self-end">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div><!-- /#pane-messages -->

        <!-- ============================================================
             TAB 5 — Journal d'audit (F20)
             ============================================================ -->
        <div class="tab-pane fade <?= $activeTab === 'audit' ? 'show active' : '' ?>" id="pane-audit">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($auditLogs)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-scroll fa-3x opacity-25 mb-3"></i>
                        <p>Aucune entrée dans le journal.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Date</th>
                                    <th>Utilisateur</th>
                                    <th>Action</th>
                                    <th>Avant</th>
                                    <th>Après</th>
                                    <th class="pe-3">IP</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td class="ps-3 text-muted text-nowrap">
                                    <?= isset($log['created_at']) ? date('d/m/Y H:i:s', strtotime($log['created_at'])) : '—' ?>
                                </td>
                                <td><?= bofa_sanitize(($log['user_prenom'] ?? '') . ' ' . ($log['user_nom'] ?? '')) ?></td>
                                <td>
                                    <code class="small"><?= bofa_sanitize($log['action'] ?? '—') ?></code>
                                </td>
                                <td class="text-muted">
                                    <small><?= bofa_sanitize(mb_substr($log['old_value'] ?? '—', 0, 60)) ?></small>
                                </td>
                                <td>
                                    <small><?= bofa_sanitize(mb_substr($log['new_value'] ?? '—', 0, 60)) ?></small>
                                </td>
                                <td class="pe-3 font-monospace text-muted">
                                    <?= bofa_sanitize($log['ip_address'] ?? '—') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /#pane-audit -->

    </div><!-- /.tab-content -->
</div><!-- /.container-fluid -->

<!-- Modale rejet document -->
<div class="modal fade" id="modalRejectDoc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action"     value="reject_doc">
                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                <input type="hidden" name="doc_id"     id="rejectDocId" value="">
                <input type="hidden" name="active_tab" value="docs">
                <div class="modal-header" style="background:var(--bofa-rouge);color:#fff;">
                    <h5 class="modal-title">Rejeter le document</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold small">Motif de rejet <span class="text-danger">*</span></label>
                    <textarea name="reject_reason" class="form-control" rows="3" maxlength="500" required
                              placeholder="Expliquez pourquoi ce document est rejeté…"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Rejeter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modale validation transfert -->
<div class="modal fade" id="modalValidateTransfer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action"     value="validate_transfer">
                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                <input type="hidden" name="active_tab" value="recap">
                <div class="modal-header" style="background:var(--bofa-bleu);color:#fff;">
                    <h5 class="modal-title">Valider le transfert</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-0">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <strong>Confirmez-vous</strong> la validation du transfert pour le dossier
                        <strong><?= bofa_sanitize($dossier['case_number'] ?? '') ?></strong> —
                        <strong><?= number_format((float)($dossier['montant']??0),2,',',' ') ?> <?= bofa_sanitize($dossier['devise']??'EUR') ?></strong> ?
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modale rejet dossier -->
<div class="modal fade" id="modalRejectCase" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action"     value="reject_case">
                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                <input type="hidden" name="active_tab" value="recap">
                <div class="modal-header" style="background:var(--bofa-rouge);color:#fff;">
                    <h5 class="modal-title">Rejeter le dossier</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold small">Motif de rejet <span class="text-danger">*</span></label>
                    <textarea name="reject_reason" class="form-control" rows="3" maxlength="500" required
                              placeholder="Expliquez pourquoi ce dossier est rejeté…" minlength="5"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Rejeter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* Alimentation modale rejet doc */
document.getElementById('modalRejectDoc')?.addEventListener('show.bs.modal', function (e) {
    document.getElementById('rejectDocId').value = e.relatedTarget.dataset.docId;
});

/* Défilement messagerie */
const msgThread = document.getElementById('msgThread');
if (msgThread) msgThread.scrollTop = msgThread.scrollHeight;

/* Ctrl+Entrée pour envoyer message */
document.getElementById('agentMsgInput')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.ctrlKey) e.target.closest('form').requestSubmit();
});

/* Ajout dynamique d'item checklist */
let clIdx = <?= count($checklist) ?>;
document.getElementById('btnAddCl')?.addEventListener('click', () => {
    const div = document.createElement('div');
    div.className = 'd-flex align-items-center gap-2 mb-2 cl-row';
    div.innerHTML = `
        <input type="checkbox" name="cl_done[${clIdx}]" class="form-check-input mt-0">
        <input type="text" name="cl_label[${clIdx}]" class="form-control form-control-sm flex-grow-1"
               maxlength="255" required placeholder="Libellé de l'élément…">
        <select name="cl_type[${clIdx}]" class="form-select form-select-sm" style="width:120px;">
            <option value="required">Requis</option>
            <option value="document">Document</option>
        </select>
        <button type="button" class="btn btn-xs btn-outline-danger" onclick="this.closest('.cl-row').remove()">
            <i class="fa-solid fa-trash"></i>
        </button>`;
    document.getElementById('clItems').appendChild(div);
    clIdx++;
});
</script>

<style>.btn-xs { padding:.2rem .45rem; font-size:.75rem; }</style>

<?php require_once BOFA_ROOT . '/templates/footer.php'; ?>
