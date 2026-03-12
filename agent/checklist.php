<?php
/**
 * Checklist de conformité — vue simplifiée agent — BofaDueDiligence
 * Liste tous les dossiers avec items de checklist en attente.
 */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';

bofa_auth_check(['agent']);

require_once BOFA_ROOT . '/src/Notification.php';

$agentId    = (int) $_SESSION['user_id'];
$notifObj   = new Notification();
$notifCount = $notifObj->getUnreadCount($agentId);
$errors     = [];

/* -----------------------------------------------------------------------
 * Traitement POST — cocher un item
 * ----------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $done   = isset($_POST['done']) ? 1 : 0;

    if (!bofa_csrf_validate($token)) {
        $errors[] = 'Jeton de sécurité invalide.';
    } elseif ($itemId > 0) {
        try {
            $db = bofa_db();
            $stmt = $db->prepare(
                "UPDATE checklist_items SET is_completed = :done,
                 completed_at = CASE WHEN :done2 = 1 THEN NOW() ELSE NULL END
                 WHERE id = :id"
            );
            $stmt->execute([':done' => $done, ':done2' => $done, ':id' => $itemId]);
            bofa_flash('Checklist mise à jour.', 'success');
        } catch (PDOException $e) {
            error_log('[checklist.php] : ' . $e->getMessage());
            $errors[] = 'Erreur lors de la mise à jour.';
        }
        bofa_redirect('/bofa/agent/checklist.php');
    }
}

/* -----------------------------------------------------------------------
 * Chargement des dossiers avec checklist en attente
 * ----------------------------------------------------------------------- */
$rows = [];
try {
    $db = bofa_db();
    $stmt = $db->prepare(
        "SELECT c.id AS case_id, c.case_number, c.status,
                ci.id AS item_id, ci.label, ci.type, ci.is_completed,
                u.prenom AS client_prenom, u.nom AS client_nom
         FROM checklist_items ci
         JOIN cases c ON c.id = ci.case_id
         JOIN users u ON u.id = c.client_id
         WHERE c.agent_id = :aid
           AND ci.is_completed = 0
         ORDER BY c.case_number, ci.position
         LIMIT 200"
    );
    $stmt->execute([':aid' => $agentId]);
    $rawRows = $stmt->fetchAll();

    /* Grouper par dossier */
    foreach ($rawRows as $r) {
        $cid = (int) $r['case_id'];
        if (!isset($rows[$cid])) {
            $rows[$cid] = [
                'case_id'      => $cid,
                'case_number'  => $r['case_number'],
                'status'       => $r['status'],
                'client_prenom'=> $r['client_prenom'],
                'client_nom'   => $r['client_nom'],
                'items'        => [],
            ];
        }
        $rows[$cid]['items'][] = $r;
    }
} catch (PDOException $e) {
    error_log('[checklist.php] chargement : ' . $e->getMessage());
}

$statusBadge = [
    'nouveau'        => ['bg-secondary',         'Nouveau'],
    'en_cours'       => ['bg-primary',            'En cours'],
    'en_attente_doc' => ['bg-warning text-dark',  'Docs demandés'],
    'pret_transfert' => ['bg-success',            'Prêt au virement'],
    'gele'           => ['bg-danger',             'Gelé'],
    'rejete'         => ['bg-danger',             'Rejeté'],
];

$pageTitle   = 'Checklist de conformité';
$currentPage = 'checklist';
require_once BOFA_ROOT . '/templates/header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold text-bofa-bleu">
                <i class="fa-solid fa-list-check me-2"></i>Checklist de conformité
            </h1>
            <p class="text-muted small mb-0">
                <?= count($rows) ?> dossier<?= count($rows) > 1 ? 's' : '' ?> avec des éléments en attente.
            </p>
        </div>
    </div>

    <?php foreach ($errors as $err): ?>
        <?php $alertType = 'danger'; $alertMessage = $err; require BOFA_ROOT . '/templates/partials/alert.php'; ?>
    <?php endforeach; ?>

    <?php if (empty($rows)): ?>
    <div class="card shadow-sm border-0 text-center py-5">
        <div class="card-body">
            <i class="fa-solid fa-circle-check fa-4x text-success opacity-50 mb-3"></i>
            <h3 class="h5 text-muted">Toutes les checklists sont complétées !</h3>
            <p class="text-muted small">Aucun élément en attente sur vos dossiers.</p>
        </div>
    </div>
    <?php else: ?>

    <div class="d-flex flex-column gap-4">
    <?php foreach ($rows as $dossier): ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between py-3">
            <div class="d-flex align-items-center gap-2">
                <a href="/bofa/agent/dossier-detail.php?id=<?= $dossier['case_id'] ?>&tab=checklist"
                   class="fw-semibold font-monospace text-decoration-none text-bofa-bleu">
                    <?= bofa_sanitize($dossier['case_number']) ?>
                </a>
                <?php $b = $statusBadge[$dossier['status']] ?? ['bg-secondary', $dossier['status']]; ?>
                <span class="badge <?= $b[0] ?>"><?= htmlspecialchars($b[1], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <small class="text-muted">
                <?= bofa_sanitize(($dossier['client_prenom'] ?? '') . ' ' . ($dossier['client_nom'] ?? '')) ?>
                — <strong><?= count($dossier['items']) ?></strong> élément<?= count($dossier['items']) > 1 ? 's' : '' ?> en attente
            </small>
        </div>
        <div class="card-body">
            <div class="d-flex flex-column gap-2">
            <?php foreach ($dossier['items'] as $item): ?>
            <form method="POST" action="/bofa/agent/checklist.php"
                  class="d-flex align-items-center gap-3">
                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                <input type="hidden" name="item_id"    value="<?= (int)$item['item_id'] ?>">
                <input type="hidden" name="done"       value="1">

                <input type="checkbox"
                       class="form-check-input flex-shrink-0"
                       <?= (int)$item['is_completed'] ? 'checked disabled' : '' ?>
                       onchange="if(this.checked){ this.form.submit(); }">

                <span class="flex-grow-1 <?= (int)$item['is_completed'] ? 'text-decoration-line-through text-muted' : '' ?>">
                    <?= bofa_sanitize($item['label']) ?>
                </span>

                <?php if ($item['type'] === 'document'): ?>
                <span class="badge bg-light text-dark border flex-shrink-0">
                    <i class="fa-solid fa-file me-1"></i>Document requis
                </span>
                <?php endif; ?>
            </form>
            <?php endforeach; ?>
            </div>
        </div>
        <div class="card-footer bg-transparent text-end">
            <a href="/bofa/agent/dossier-detail.php?id=<?= $dossier['case_id'] ?>&tab=checklist"
               class="btn btn-sm btn-outline-primary">
                <i class="fa-solid fa-pencil me-1"></i>Gérer la checklist complète
            </a>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<?php require_once BOFA_ROOT . '/templates/footer.php'; ?>
