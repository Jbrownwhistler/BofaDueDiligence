<?php
/**
 * Liste des dossiers AML — agent (F12) — BofaDueDiligence
 * Filtres avancés, actions rapides, tableau riche.
 */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';

bofa_auth_check(['agent']);

require_once BOFA_ROOT . '/src/CaseAML.php';
require_once BOFA_ROOT . '/src/Notification.php';

$agentId  = (int) $_SESSION['user_id'];
$caseObj  = new CaseAML();
$notifObj = new Notification();
$notifCount = $notifObj->getUnreadCount($agentId);
$errors   = [];

/* -----------------------------------------------------------------------
 * Traitement POST — actions rapides
 * ----------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action']     ?? '';
    $caseId = (int) ($_POST['case_id'] ?? 0);

    if (!bofa_csrf_validate($token)) {
        $errors[] = 'Jeton de sécurité invalide.';
    } elseif ($caseId <= 0) {
        $errors[] = 'Dossier invalide.';
    } else {
        $ok = false;
        if ($action === 'assign') {
            $ok = $caseObj->assignAgent($caseId, $agentId);
        } elseif ($action === 'status') {
            $newStatus = $_POST['new_status'] ?? '';
            $allowed   = ['en_cours','en_attente_doc','pret_transfert','gele','rejete'];
            if (in_array($newStatus, $allowed, true)) {
                $ok = $caseObj->updateStatus($caseId, $newStatus, $agentId);
            } else {
                $errors[] = 'Statut invalide.';
            }
        }
        if ($ok && empty($errors)) {
            bofa_flash('Action effectuée avec succès.', 'success');
            bofa_redirect('/bofa/agent/dossiers.php?' . http_build_query($_GET));
        } elseif (!$ok && empty($errors)) {
            $errors[] = 'L\'action a échoué.';
        }
    }
}

/* -----------------------------------------------------------------------
 * Filtres GET
 * ----------------------------------------------------------------------- */
$filters = [];
$filterKeys = ['status','pays','montant_min','montant_max','date_debut','date_fin','score_min','score_max','agent','tags'];
foreach ($filterKeys as $k) {
    $v = trim($_GET[$k] ?? '');
    if ($v !== '') $filters[$k] = $v;
}
$page = max(1, (int) ($_GET['page'] ?? 1));

/* -----------------------------------------------------------------------
 * Chargement des dossiers
 * ----------------------------------------------------------------------- */
$dossiersData = $caseObj->getAll($filters, $page, 20);
$dossiers     = $dossiersData['cases'] ?? $dossiersData;
$total        = $dossiersData['total'] ?? count($dossiers);
$pagination   = bofa_paginate($total, 20, $page);

/* Liste des agents pour le filtre */
try {
    $db = bofa_db();
    $stmtAgents = $db->query("SELECT id, prenom, nom FROM users WHERE role='agent' ORDER BY nom");
    $allAgents  = $stmtAgents->fetchAll();
} catch (PDOException $e) {
    $allAgents = [];
}

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

$pageTitle   = 'Dossiers AML/EDD';
$currentPage = 'dossiers';
require_once BOFA_ROOT . '/templates/header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold text-bofa-bleu">
                <i class="fa-solid fa-briefcase me-2"></i>Dossiers AML/EDD
            </h1>
            <p class="text-muted small mb-0"><?= $total ?> dossier<?= $total > 1 ? 's' : '' ?> trouvé<?= $total > 1 ? 's' : '' ?></p>
        </div>
    </div>

    <?php foreach ($errors as $err): ?>
        <?php $alertType = 'danger'; $alertMessage = $err; require BOFA_ROOT . '/templates/partials/alert.php'; ?>
    <?php endforeach; ?>

    <!-- Panneau de filtres -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-transparent py-2 d-flex align-items-center justify-content-between">
            <span class="fw-semibold small"><i class="fa-solid fa-filter me-2"></i>Filtres</span>
            <button class="btn btn-sm btn-link text-decoration-none p-0"
                    data-bs-toggle="collapse" data-bs-target="#filterPanel">
                Afficher/Masquer
            </button>
        </div>
        <div class="collapse <?= !empty($filters) ? 'show' : '' ?>" id="filterPanel">
            <div class="card-body">
                <form method="GET" action="/bofa/agent/dossiers.php" class="row g-2 align-items-end">
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1">Statut</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Tous</option>
                            <?php foreach (array_keys($statusBadge) as $s): ?>
                            <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>>
                                <?= htmlspecialchars($statusBadge[$s][1], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1">Pays émetteur</label>
                        <input type="text" name="pays" class="form-control form-control-sm"
                               value="<?= bofa_sanitize($filters['pays'] ?? '') ?>"
                               placeholder="Ex: FR, MA…" maxlength="2">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1">Montant min (€)</label>
                        <input type="number" name="montant_min" class="form-control form-control-sm"
                               value="<?= bofa_sanitize($filters['montant_min'] ?? '') ?>"
                               min="0" step="1">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1">Montant max (€)</label>
                        <input type="number" name="montant_max" class="form-control form-control-sm"
                               value="<?= bofa_sanitize($filters['montant_max'] ?? '') ?>"
                               min="0" step="1">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1">Score min</label>
                        <input type="number" name="score_min" class="form-control form-control-sm"
                               value="<?= bofa_sanitize($filters['score_min'] ?? '') ?>"
                               min="0" max="100" step="1">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1">Score max</label>
                        <input type="number" name="score_max" class="form-control form-control-sm"
                               value="<?= bofa_sanitize($filters['score_max'] ?? '') ?>"
                               min="0" max="100" step="1">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1">Date début</label>
                        <input type="date" name="date_debut" class="form-control form-control-sm"
                               value="<?= bofa_sanitize($filters['date_debut'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1">Date fin</label>
                        <input type="date" name="date_fin" class="form-control form-control-sm"
                               value="<?= bofa_sanitize($filters['date_fin'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-3 col-xl-2">
                        <label class="form-label small mb-1">Agent</label>
                        <select name="agent" class="form-select form-select-sm">
                            <option value="">Tous les agents</option>
                            <?php foreach ($allAgents as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"
                                <?= (int)($filters['agent'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>>
                                <?= bofa_sanitize($a['prenom'] . ' ' . $a['nom']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-bofa">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Filtrer
                        </button>
                        <a href="/bofa/agent/dossiers.php" class="btn btn-sm btn-outline-secondary ms-1">
                            <i class="fa-solid fa-xmark me-1"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tableau des dossiers -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <?php if (empty($dossiers)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-folder-open fa-4x opacity-25 mb-3"></i>
                <p>Aucun dossier trouvé.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Référence</th>
                            <th>Client</th>
                            <th>Montant</th>
                            <th>Pays</th>
                            <th>Score</th>
                            <th>Documents</th>
                            <th>Statut</th>
                            <th>Agent</th>
                            <th class="pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dossiers as $d):
                        $badge    = $statusBadge[$d['status']] ?? ['bg-secondary', $d['status']];
                        $overdue  = !empty($d['is_overdue']);
                        $score    = (float)($d['score_risque'] ?? 0);
                        $agent    = trim(($d['agent_prenom'] ?? '') . ' ' . ($d['agent_nom'] ?? ''));
                        $pays     = bofa_sanitize($d['pays_emetteur'] ?? '—');
                        /* Drapeaux emoji par code pays */
                        $flag = '';
                        if (strlen($d['pays_emetteur'] ?? '') === 2) {
                            $code  = strtoupper($d['pays_emetteur']);
                            $chars = array_map(fn($c) => mb_chr(0x1F1E0 - 65 + ord($c)), str_split($code));
                            $flag  = implode('', $chars) . ' ';
                        }
                    ?>
                    <tr class="<?= $overdue ? 'table-warning' : '' ?>">
                        <td class="ps-3">
                            <a href="/bofa/agent/dossier-detail.php?id=<?= (int)$d['id'] ?>"
                               class="fw-semibold text-decoration-none text-bofa-bleu font-monospace">
                                <?= bofa_sanitize($d['case_number'] ?? '') ?>
                            </a>
                            <?php if ($overdue): ?>
                            <i class="fa-solid fa-triangle-exclamation text-warning ms-1" title="En retard"></i>
                            <?php endif; ?>
                            <?php if (!empty($d['sanction_flag'])): ?>
                            <span class="badge bg-danger ms-1">SANCTION</span>
                            <?php endif; ?>
                        </td>
                        <td><?= bofa_sanitize(($d['client_prenom'] ?? '') . ' ' . ($d['client_nom'] ?? '')) ?></td>
                        <td class="fw-semibold">
                            <?= number_format((float)($d['montant']??0),0,',',' ') ?> <?= bofa_sanitize($d['devise']??'EUR') ?>
                        </td>
                        <td><?= $flag . $pays ?></td>
                        <td>
                            <?php $score_display = $score; require BOFA_ROOT . '/templates/partials/score-badge.php'; ?>
                        </td>
                        <td>
                            <?php
                            $docStatus = $d['doc_status'] ?? 'inconnu';
                            $docIcon   = match($docStatus) {
                                'complet'    => '<i class="fa-solid fa-circle-check text-success" title="Documents complets"></i>',
                                'incomplet'  => '<i class="fa-solid fa-circle-exclamation text-warning" title="Documents incomplets"></i>',
                                'rejete'     => '<i class="fa-solid fa-circle-xmark text-danger" title="Documents rejetés"></i>',
                                default      => '<i class="fa-regular fa-circle text-muted" title="Aucun document"></i>',
                            };
                            echo $docIcon;
                            ?>
                        </td>
                        <td><span class="badge <?= $badge[0] ?>"><?= htmlspecialchars($badge[1], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td class="text-muted">
                            <?= $agent ? bofa_sanitize($agent) : '<em>Non assigné</em>' ?>
                        </td>
                        <td class="pe-3">
                            <div class="d-flex gap-1 flex-nowrap">
                                <a href="/bofa/agent/dossier-detail.php?id=<?= (int)$d['id'] ?>"
                                   class="btn btn-xs btn-outline-primary"
                                   title="Voir le dossier">
                                    <i class="fa-solid fa-eye"></i>
                                </a>

                                <!-- Action rapide statut -->
                                <div class="dropdown">
                                    <button class="btn btn-xs btn-outline-secondary dropdown-toggle"
                                            data-bs-toggle="dropdown" title="Changer le statut">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php foreach (['en_cours','en_attente_doc','pret_transfert','gele','rejete'] as $s): ?>
                                        <li>
                                            <form method="POST" action="/bofa/agent/dossiers.php" class="d-inline">
                                                <input type="hidden" name="action"     value="status">
                                                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                                                <input type="hidden" name="case_id"    value="<?= (int)$d['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $s ?>">
                                                <button type="submit" class="dropdown-item small
                                                    <?= ($d['status'] ?? '') === $s ? 'active' : '' ?>">
                                                    <span class="badge <?= $statusBadge[$s][0] ?? 'bg-secondary' ?> me-2" style="width:8px;height:8px;border-radius:50%;padding:0;">&nbsp;</span>
                                                    <?= htmlspecialchars($statusBadge[$s][1], ENT_QUOTES, 'UTF-8') ?>
                                                </button>
                                            </form>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>

                                <?php if (empty($d['agent_id'])): ?>
                                <form method="POST" action="/bofa/agent/dossiers.php">
                                    <input type="hidden" name="action"     value="assign">
                                    <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                                    <input type="hidden" name="case_id"    value="<?= (int)$d['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-success" title="M'assigner">
                                        <i class="fa-solid fa-user-plus"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $baseUrl = '/bofa/agent/dossiers.php?' . http_build_query(array_filter($filters));
            require BOFA_ROOT . '/templates/partials/pagination.php';
            ?>

            <?php endif; ?>
        </div>
    </div>

</div>

<style>
.btn-xs { padding: .2rem .45rem; font-size: .75rem; }
</style>

<?php require_once BOFA_ROOT . '/templates/footer.php'; ?>
