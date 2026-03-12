<?php
/**
 * Historique des transferts client (F09) — BofaDueDiligence
 * Liste paginée des virements effectués, avec filtre par date et export PDF.
 */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';

bofa_auth_check(['client']);

require_once BOFA_ROOT . '/src/Notification.php';

$userId     = (int) $_SESSION['user_id'];
$notifObj   = new Notification();
$notifCount = $notifObj->getUnreadCount($userId);

/* -----------------------------------------------------------------------
 * Paramètres de filtre et pagination
 * ----------------------------------------------------------------------- */
$page       = max(1, (int) ($_GET['page']        ?? 1));
$dateDebut  = $_GET['date_debut'] ?? '';
$dateFin    = $_GET['date_fin']   ?? '';
$perPage    = 15;

/* Validation des dates */
$dateDebutClean = '';
$dateFinClean   = '';
if ($dateDebut && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)) {
    $dateDebutClean = $dateDebut;
}
if ($dateFin && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
    $dateFinClean = $dateFin;
}

/* -----------------------------------------------------------------------
 * Requête de l'historique
 * ----------------------------------------------------------------------- */
$historique = [];
$total      = 0;

try {
    $db = bofa_db();

    $where  = "WHERE c.client_id = :uid AND c.status IN ('transfert_effectue')";
    $params = [':uid' => $userId];

    if ($dateDebutClean) {
        $where .= " AND DATE(c.transferred_at) >= :debut";
        $params[':debut'] = $dateDebutClean;
    }
    if ($dateFinClean) {
        $where .= " AND DATE(c.transferred_at) <= :fin";
        $params[':fin'] = $dateFinClean;
    }

    /* Comptage total */
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM cases c $where");
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    $pagination = bofa_paginate($total, $perPage, $page);
    $params[':limit']  = $pagination['perPage'];
    $params[':offset'] = $pagination['offset'];

    /* Données paginées */
    $stmtHisto = $db->prepare(
        "SELECT c.id, c.case_number, c.montant, c.devise,
                c.created_at, c.transferred_at,
                c.pays_emetteur, c.pays_beneficiaire, c.type_actif,
                u.prenom AS agent_prenom, u.nom AS agent_nom
         FROM cases c
         LEFT JOIN users u ON u.id = c.agent_id
         $where
         ORDER BY c.transferred_at DESC
         LIMIT :limit OFFSET :offset"
    );
    $stmtHisto->execute($params);
    $historique = $stmtHisto->fetchAll();

} catch (PDOException $e) {
    error_log('[historique.php] Erreur : ' . $e->getMessage());
    $pagination = bofa_paginate(0, $perPage, 1);
}

/* -----------------------------------------------------------------------
 * Calcul du total des montants transférés
 * ----------------------------------------------------------------------- */
$totalMontant = 0.0;
foreach ($historique as $h) {
    $totalMontant += (float)($h['montant'] ?? 0);
}

$pageTitle   = 'Historique des transferts';
$currentPage = 'historique';
require_once BOFA_ROOT . '/templates/header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold text-bofa-bleu">
                <i class="fa-solid fa-clock-rotate-left me-2"></i>Historique des transferts
            </h1>
            <p class="text-muted small mb-0">
                <?= $total ?> virement<?= $total > 1 ? 's' : '' ?> effectué<?= $total > 1 ? 's' : '' ?>
            </p>
        </div>
        <!-- Export PDF (placeholder) -->
        <a href="/bofa/client/historique.php?export=pdf&date_debut=<?= urlencode($dateDebutClean) ?>&date_fin=<?= urlencode($dateFinClean) ?>"
           class="btn btn-outline-danger"
           title="Exporter en PDF">
            <i class="fa-solid fa-file-pdf me-2"></i>Exporter PDF
        </a>
    </div>

    <!-- ----------------------------------------------------------------
         Formulaire de filtre par date
         ---------------------------------------------------------------- -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="/bofa/client/historique.php" class="row g-3 align-items-end">
                <div class="col-12 col-sm-auto">
                    <label for="date_debut" class="form-label small fw-semibold mb-1">Date de début</label>
                    <input type="date"
                           name="date_debut"
                           id="date_debut"
                           class="form-control form-control-sm"
                           value="<?= htmlspecialchars($dateDebutClean, ENT_QUOTES, 'UTF-8') ?>"
                           max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-sm-auto">
                    <label for="date_fin" class="form-label small fw-semibold mb-1">Date de fin</label>
                    <input type="date"
                           name="date_fin"
                           id="date_fin"
                           class="form-control form-control-sm"
                           value="<?= htmlspecialchars($dateFinClean, ENT_QUOTES, 'UTF-8') ?>"
                           max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-sm-auto">
                    <button type="submit" class="btn btn-sm btn-bofa">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>Filtrer
                    </button>
                    <?php if ($dateDebutClean || $dateFinClean): ?>
                    <a href="/bofa/client/historique.php" class="btn btn-sm btn-outline-secondary ms-1">
                        <i class="fa-solid fa-xmark me-1"></i>Réinitialiser
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ----------------------------------------------------------------
         Carte récapitulative
         ---------------------------------------------------------------- -->
    <?php if (!empty($historique)): ?>
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 flex-shrink-0" style="background:rgba(25,135,84,.1);">
                        <i class="fa-solid fa-check-double fa-lg text-success"></i>
                    </div>
                    <div>
                        <p class="text-muted small mb-0">Total transféré (filtre actuel)</p>
                        <h3 class="h5 fw-bold mb-0 text-success">
                            <?= number_format($totalMontant, 2, ',', ' ') ?> €
                        </h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 flex-shrink-0" style="background:rgba(1,33,105,.1);">
                        <i class="fa-solid fa-list fa-lg" style="color:var(--bofa-bleu);"></i>
                    </div>
                    <div>
                        <p class="text-muted small mb-0">Nombre de virements</p>
                        <h3 class="h5 fw-bold mb-0"><?= $total ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ----------------------------------------------------------------
         Tableau de l'historique
         ---------------------------------------------------------------- -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <?php if (empty($historique)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-clock-rotate-left fa-4x opacity-25 mb-3"></i>
                <h3 class="h5">Aucun transfert trouvé</h3>
                <p class="small">
                    <?php if ($dateDebutClean || $dateFinClean): ?>
                    Aucun transfert sur la période sélectionnée.
                    <a href="/bofa/client/historique.php">Voir tout l'historique</a>
                    <?php else: ?>
                    Vous n'avez pas encore effectué de transfert.
                    <?php endif; ?>
                </p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Référence</th>
                            <th>Montant</th>
                            <th>Pays émetteur</th>
                            <th>Pays bénéficiaire</th>
                            <th>Type d'actif</th>
                            <th>Agent</th>
                            <th>Date ouverture</th>
                            <th class="pe-3">Date transfert</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($historique as $h):
                        $montantFmt = number_format((float)($h['montant'] ?? 0), 2, ',', ' ');
                        $devise     = bofa_sanitize($h['devise'] ?? 'EUR');
                        $agent      = trim(($h['agent_prenom'] ?? '') . ' ' . ($h['agent_nom'] ?? ''));
                        $dateOuv    = isset($h['created_at'])     ? date('d/m/Y', strtotime($h['created_at']))     : '—';
                        $dateTransf = isset($h['transferred_at']) ? date('d/m/Y H:i', strtotime($h['transferred_at'])) : '—';
                    ?>
                    <tr>
                        <td class="ps-3">
                            <span class="badge bg-dark font-monospace">
                                <?= bofa_sanitize($h['case_number'] ?? '') ?>
                            </span>
                        </td>
                        <td class="fw-bold text-success">
                            <?= $montantFmt ?> <?= $devise ?>
                        </td>
                        <td><?= bofa_sanitize($h['pays_emetteur'] ?? '—') ?></td>
                        <td><?= bofa_sanitize($h['pays_beneficiaire'] ?? '—') ?></td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <?= bofa_sanitize($h['type_actif'] ?? '—') ?>
                            </span>
                        </td>
                        <td class="text-muted small">
                            <?= $agent ? bofa_sanitize($agent) : '<em>—</em>' ?>
                        </td>
                        <td class="text-muted small"><?= $dateOuv ?></td>
                        <td class="pe-3">
                            <span class="badge bg-success">
                                <i class="fa-solid fa-check me-1"></i><?= $dateTransf ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td class="ps-3" colspan="2">
                                Total affiché : <?= number_format($totalMontant, 2, ',', ' ') ?> €
                            </td>
                            <td colspan="6" class="pe-3 text-end text-muted small">
                                Page <?= $pagination['currentPage'] ?> / <?= $pagination['totalPages'] ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination -->
            <?php
            $baseUrl = '/bofa/client/historique.php?date_debut=' . urlencode($dateDebutClean) . '&date_fin=' . urlencode($dateFinClean);
            require BOFA_ROOT . '/templates/partials/pagination.php';
            ?>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.container-fluid -->

<?php require_once BOFA_ROOT . '/templates/footer.php'; ?>
