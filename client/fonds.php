<?php
/**
 * Fonds en cours d'examen de conformité (F02, F03, F04, F06) — BofaDueDiligence
 * Liste tous les dossiers AML du client avec checklist et bouton de transfert.
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
$success  = '';

/* -----------------------------------------------------------------------
 * Traitement POST — confirmation de transfert (F04)
 * ----------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfert') {

    $token   = $_POST['csrf_token'] ?? '';
    $caseId  = (int) ($_POST['case_id'] ?? 0);

    if (!bofa_csrf_validate($token)) {
        $errors[] = 'Jeton de sécurité invalide. Veuillez réessayer.';
    } elseif ($caseId <= 0) {
        $errors[] = 'Dossier invalide.';
    } else {
        /* Vérification du statut et de la checklist */
        $dossier = $caseObj->getById($caseId);

        if (!$dossier || (int)$dossier['client_id'] !== $userId) {
            $errors[] = 'Dossier introuvable ou non autorisé.';
        } elseif ($dossier['status'] !== 'pret_transfert') {
            $errors[] = 'Ce dossier n\'est pas encore prêt pour le transfert.';
        } else {
            /* Vérifier que toutes les cases de la checklist sont cochées */
            try {
                $db = bofa_db();
                $stmtCheck = $db->prepare(
                    "SELECT COUNT(*) FROM checklist_items
                     WHERE case_id = :cid AND is_completed = 0 AND type = 'required'"
                );
                $stmtCheck->execute([':cid' => $caseId]);
                $pending = (int) $stmtCheck->fetchColumn();
            } catch (PDOException $e) {
                error_log('[fonds.php] Erreur checklist : ' . $e->getMessage());
                $pending = 0;
            }

            if ($pending > 0) {
                $errors[] = 'La checklist de conformité n\'est pas entièrement complétée.';
            } else {
                $ok = $caseObj->executeTransfer($caseId, $userId);
                if ($ok) {
                    bofa_flash('Demande de transfert soumise avec succès.', 'success');
                    bofa_redirect(BOFA_URL . '/client/fonds.php' . '?success=1');
                } else {
                    $errors[] = 'Une erreur est survenue lors de la soumission du transfert.';
                }
            }
        }
    }
}

/* -----------------------------------------------------------------------
 * Chargement des dossiers (GET)
 * ----------------------------------------------------------------------- */
$page         = max(1, (int) ($_GET['page'] ?? 1));
$dossiersData = $caseObj->getByClient($userId, $page, 10);
$dossiers     = $dossiersData['cases']  ?? $dossiersData;
$total        = $dossiersData['total']  ?? count($dossiers);
$pagination   = bofa_paginate($total, 10, $page);
$notifCount   = $notifObj->getUnreadCount($userId);

/* Checklist par dossier */
$checklists = [];
try {
    $db = bofa_db();
    foreach ($dossiers as $d) {
        $stmtCl = $db->prepare(
            "SELECT id, label, type, is_completed FROM checklist_items
             WHERE case_id = :cid ORDER BY position ASC"
        );
        $stmtCl->execute([':cid' => (int)$d['id']]);
        $checklists[(int)$d['id']] = $stmtCl->fetchAll();
    }
} catch (PDOException $e) {
    error_log('[fonds.php] Erreur chargement checklists : ' . $e->getMessage());
}

/* Correspondance statut → badge */
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

$pageTitle   = 'Mes fonds en attente';
$currentPage = 'fonds';
require_once BOFA_ROOT . '/templates/header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold text-bofa-bleu">
                <i class="fa-solid fa-circle-dollar-to-slot me-2"></i>Fonds en cours d'examen
            </h1>
            <p class="text-muted small mb-0">
                <?= $total ?> dossier<?= $total > 1 ? 's' : '' ?> — conformité AML/EDD
            </p>
        </div>
    </div>

    <!-- Alertes erreurs / succès -->
    <?php foreach ($errors as $err): ?>
        <?php $alertType = 'danger'; $alertMessage = $err; require BOFA_ROOT . '/templates/partials/alert.php'; ?>
    <?php endforeach; ?>

    <?php if (isset($_GET['success'])): ?>
        <?php $alertType = 'success'; $alertMessage = 'Votre demande de transfert a bien été soumise.'; require BOFA_ROOT . '/templates/partials/alert.php'; ?>
    <?php endif; ?>

    <?php if (empty($dossiers)): ?>
    <div class="card shadow-sm border-0 text-center py-5">
        <div class="card-body">
            <i class="fa-solid fa-folder-open fa-4x text-muted opacity-25 mb-3"></i>
            <h3 class="h5 text-muted">Aucun dossier en cours</h3>
            <p class="text-muted">Vous n'avez pas de fonds bloqués pour le moment.</p>
        </div>
    </div>
    <?php else: ?>

    <!-- Liste des dossiers -->
    <div class="d-flex flex-column gap-4">
    <?php foreach ($dossiers as $d):
        $dId      = (int) $d['id'];
        $badge    = $statusBadge[$d['status']] ?? ['bg-secondary', $d['status']];
        $montant  = number_format((float)($d['montant'] ?? 0), 2, ',', ' ');
        $devise   = bofa_sanitize($d['devise'] ?? 'EUR');
        $agent    = trim(($d['agent_prenom'] ?? '') . ' ' . ($d['agent_nom'] ?? ''));
        $dateCreat = isset($d['created_at']) ? date('d/m/Y', strtotime($d['created_at'])) : '—';
        $cl        = $checklists[$dId] ?? [];
        $clTotal   = count($cl);
        $clDone    = count(array_filter($cl, fn($i) => (int)$i['is_completed'] === 1));
        $pret      = ($d['status'] === 'pret_transfert') && ($clTotal === 0 || $clDone === $clTotal);

        /* Délai estimé (en jours depuis création) */
        $delay = isset($d['created_at'])
            ? max(0, (int) round((time() - strtotime($d['created_at'])) / 86400))
            : '?';
    ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-dark font-monospace">
                    <?= bofa_sanitize($d['case_number'] ?? 'AML-EDD-?') ?>
                </span>
                <span class="badge <?= $badge[0] ?>"><?= htmlspecialchars($badge[1], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($d['status'] === 'gele'): ?>
                <span class="badge bg-danger">
                    <i class="fa-solid fa-snowflake me-1"></i>Gelé
                </span>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <span class="fw-bold fs-5 text-bofa-bleu"><?= $montant ?> <?= $devise ?></span>
                <br>
                <small class="text-muted">Créé le <?= $dateCreat ?> — J+<?= $delay ?></small>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-3">

                <!-- Informations du dossier -->
                <div class="col-12 col-md-6">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5 text-muted">Agent référent</dt>
                        <dd class="col-sm-7">
                            <?= $agent ? bofa_sanitize($agent) : '<em class="text-muted">Non assigné</em>' ?>
                        </dd>
                        <dt class="col-sm-5 text-muted">Pays émetteur</dt>
                        <dd class="col-sm-7"><?= bofa_sanitize($d['pays_emetteur'] ?? '—') ?></dd>
                        <dt class="col-sm-5 text-muted">Type d'actif</dt>
                        <dd class="col-sm-7"><?= bofa_sanitize($d['type_actif'] ?? '—') ?></dd>
                    </dl>
                </div>

                <!-- Checklist (F06) -->
                <div class="col-12 col-md-6">
                    <?php if ($clTotal > 0): ?>
                    <p class="small fw-semibold mb-2">
                        <i class="fa-solid fa-list-check me-1 text-bofa-bleu"></i>
                        Checklist de conformité
                        <span class="badge bg-secondary ms-1"><?= $clDone ?>/<?= $clTotal ?></span>
                    </p>
                    <div class="progress mb-2" style="height:6px;" title="<?= $clDone ?>/<?= $clTotal ?> éléments complétés">
                        <div class="progress-bar bg-success"
                             style="width:<?= $clTotal > 0 ? round($clDone / $clTotal * 100) : 0 ?>%">
                        </div>
                    </div>
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach ($cl as $item): ?>
                        <li class="d-flex align-items-center gap-2 mb-1">
                            <?php if ((int)$item['is_completed']): ?>
                            <i class="fa-solid fa-circle-check text-success flex-shrink-0"></i>
                            <?php else: ?>
                            <i class="fa-regular fa-circle text-muted flex-shrink-0"></i>
                            <?php endif; ?>
                            <span class="<?= (int)$item['is_completed'] ? 'text-decoration-line-through text-muted' : '' ?>">
                                <?= bofa_sanitize($item['label']) ?>
                            </span>
                            <?php if ($item['type'] === 'document'): ?>
                            <span class="badge bg-light text-dark border">Doc</span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-muted small mb-0">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Aucune checklist associée.
                    </p>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- Bouton de transfert -->
        <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
            <a href="/bofa/client/documents.php?case_id=<?= $dId ?>"
               class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-folder-open me-1"></i>Documents
            </a>
            <a href="/bofa/client/messages.php?case_id=<?= $dId ?>"
               class="btn btn-sm btn-outline-secondary">
                <i class="fa-regular fa-envelope me-1"></i>Messages
            </a>

            <?php if ($pret): ?>
            <!-- Bouton ouvrant la modale de confirmation -->
            <button type="button"
                    class="btn btn-sm btn-success"
                    data-bs-toggle="modal"
                    data-bs-target="#modalTransfert"
                    data-case-id="<?= $dId ?>"
                    data-case-number="<?= bofa_sanitize($d['case_number'] ?? '') ?>"
                    data-montant="<?= $montant ?>"
                    data-devise="<?= $devise ?>">
                <i class="fa-solid fa-paper-plane me-1"></i>Confirmer le transfert
            </button>
            <?php else: ?>
            <button type="button"
                    class="btn btn-sm btn-outline-secondary"
                    disabled
                    title="Le dossier n'est pas encore prêt pour le transfert">
                <i class="fa-solid fa-paper-plane me-1"></i>Transfert non disponible
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php
    $baseUrl = '/bofa/client/fonds.php';
    require BOFA_ROOT . '/templates/partials/pagination.php';
    ?>

    <?php endif; ?>
</div><!-- /.container-fluid -->

<!-- -----------------------------------------------------------------------
     Modale de confirmation de transfert
     ----------------------------------------------------------------------- -->
<div class="modal fade" id="modalTransfert" tabindex="-1" aria-labelledby="modalTransfertLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/bofa/client/fonds.php">
                <input type="hidden" name="action"     value="transfert">
                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                <input type="hidden" name="case_id"    id="modalCaseId" value="">

                <div class="modal-header" style="background:var(--bofa-bleu);color:#fff;">
                    <h5 class="modal-title" id="modalTransfertLabel">
                        <i class="fa-solid fa-paper-plane me-2"></i>Confirmer le virement
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <strong>Attention :</strong> Cette action est irréversible.
                        En confirmant, vous autorisez l'exécution du virement.
                    </div>

                    <dl class="row mb-0">
                        <dt class="col-sm-5">Référence dossier</dt>
                        <dd class="col-sm-7 fw-semibold" id="modalCaseNumber">—</dd>
                        <dt class="col-sm-5">Montant</dt>
                        <dd class="col-sm-7 fw-bold text-success fs-5" id="modalMontant">—</dd>
                    </dl>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-check me-1"></i>Confirmer le transfert
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* Alimentation de la modale avec les données du bouton cliqué */
document.getElementById('modalTransfert').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('modalCaseId').value       = btn.dataset.caseId;
    document.getElementById('modalCaseNumber').textContent = btn.dataset.caseNumber;
    document.getElementById('modalMontant').textContent    = btn.dataset.montant + ' ' + btn.dataset.devise;
});
</script>

<?php require_once BOFA_ROOT . '/templates/footer.php'; ?>
