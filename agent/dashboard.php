<?php
/**
 * Tableau de bord agent (F11) — BofaDueDiligence
 * KPIs, dossiers récents, graphiques Chart.js.
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

/* KPIs */
$kpis = $caseObj->getKPIs($agentId);

/* Dossiers récents de l'agent */
$recentsData = $caseObj->getByAgent($agentId, [], 1);
$recents     = array_slice($recentsData['cases'] ?? $recentsData, 0, 10);

/* Données graphiques */
$chartStatus  = [];
$chartPays    = [];
try {
    $db = bofa_db();
    $stmtStatus = $db->prepare(
        "SELECT status, COUNT(*) AS nb FROM cases WHERE agent_id = :aid GROUP BY status"
    );
    $stmtStatus->execute([':aid' => $agentId]);
    $chartStatus = $stmtStatus->fetchAll();

    $stmtPays = $db->prepare(
        "SELECT pays_emetteur, COUNT(*) AS nb FROM cases WHERE agent_id = :aid
         GROUP BY pays_emetteur ORDER BY nb DESC LIMIT 8"
    );
    $stmtPays->execute([':aid' => $agentId]);
    $chartPays = $stmtPays->fetchAll();
} catch (PDOException $e) {
    error_log('[agent/dashboard] graphiques : ' . $e->getMessage());
}

$statusBadge = [
    'nouveau'            => ['bg-secondary',        'Nouveau'],
    'en_cours'           => ['bg-primary',           'En cours'],
    'en_attente_doc'     => ['bg-warning text-dark', 'Docs demandés'],
    'pret_transfert'     => ['bg-success',           'Prêt au virement'],
    'transfert_effectue' => ['bg-success',           'Transféré'],
    'gele'               => ['bg-danger',            'Gelé'],
    'rejete'             => ['bg-danger',            'Rejeté'],
    'archive'            => ['bg-light text-dark',   'Archivé'],
];

$pageTitle   = 'Tableau de bord agent';
$currentPage = 'dashboard';
require_once BOFA_ROOT . '/templates/header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold text-bofa-bleu">
                Bonjour, <?= bofa_sanitize(($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? '')) ?> 👋
            </h1>
            <p class="text-muted small mb-0"><?= date('l d F Y') ?></p>
        </div>
        <a href="/bofa/agent/dossiers.php" class="btn btn-bofa">
            <i class="fa-solid fa-briefcase me-2"></i>Tous les dossiers
        </a>
    </div>

    <!-- KPI Cards -->
    <div class="row g-4 mb-4">
        <?php
        $kpiCards = [
            ['label'=>'Dossiers actifs',    'val'=>$kpis['actifs']??0,      'icon'=>'fa-briefcase',          'color'=>'var(--bofa-bleu)',  'bg'=>'rgba(1,33,105,.1)'],
            ['label'=>'En retard',          'val'=>$kpis['en_retard']??0,   'icon'=>'fa-clock',              'color'=>'#dc3545',           'bg'=>'rgba(220,53,69,.1)'],
            ['label'=>'À valider',          'val'=>$kpis['a_valider']??0,   'icon'=>'fa-circle-check',       'color'=>'#198754',           'bg'=>'rgba(25,135,84,.1)'],
            ['label'=>'Délai moy. (jours)', 'val'=>round($kpis['delai_moyen']??0,1), 'icon'=>'fa-stopwatch', 'color'=>'#fd7e14',           'bg'=>'rgba(253,126,20,.1)'],
        ];
        foreach ($kpiCards as $k): ?>
        <div class="col-6 col-xl-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 flex-shrink-0" style="background:<?= $k['bg'] ?>;">
                        <i class="fa-solid <?= $k['icon'] ?> fa-lg" style="color:<?= $k['color'] ?>;"></i>
                    </div>
                    <div>
                        <p class="text-muted small mb-0"><?= $k['label'] ?></p>
                        <h3 class="h4 fw-bold mb-0"><?= $k['val'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4">

        <!-- Dossiers récents -->
        <div class="col-12 col-xl-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-transparent d-flex align-items-center justify-content-between py-3">
                    <h2 class="h6 mb-0 fw-semibold">
                        <i class="fa-solid fa-briefcase me-2 text-bofa-bleu"></i>Mes dossiers récents
                    </h2>
                    <a href="/bofa/agent/dossiers.php" class="btn btn-sm btn-outline-primary">Tout voir</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recents)): ?>
                    <div class="text-center py-4 text-muted"><p class="small">Aucun dossier assigné.</p></div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Référence</th>
                                    <th>Client</th>
                                    <th>Montant</th>
                                    <th>Score</th>
                                    <th>Statut</th>
                                    <th class="pe-3">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recents as $d):
                                $badge    = $statusBadge[$d['status']] ?? ['bg-secondary', $d['status']];
                                $overdue  = !empty($d['is_overdue']);
                                $score    = (float)($d['score_risque'] ?? 0);
                                $scoreClass = $score >= 75 ? 'text-danger fw-bold' : ($score >= 41 ? 'text-warning fw-semibold' : 'text-success');
                            ?>
                            <tr class="<?= $overdue ? 'table-warning' : '' ?>">
                                <td class="ps-3">
                                    <a href="/bofa/agent/dossier-detail.php?id=<?= (int)$d['id'] ?>"
                                       class="fw-semibold text-decoration-none text-bofa-bleu font-monospace small">
                                        <?= bofa_sanitize($d['case_number'] ?? '') ?>
                                    </a>
                                    <?php if ($overdue): ?>
                                    <i class="fa-solid fa-triangle-exclamation text-warning ms-1" title="En retard"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?= bofa_sanitize(($d['client_prenom'] ?? '') . ' ' . ($d['client_nom'] ?? '')) ?></td>
                                <td><?= number_format((float)($d['montant']??0),0,',',' ') ?> <?= bofa_sanitize($d['devise']??'EUR') ?></td>
                                <td class="<?= $scoreClass ?>"><?= $score ?></td>
                                <td><span class="badge <?= $badge[0] ?>"><?= htmlspecialchars($badge[1], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="pe-3 text-muted"><?= isset($d['created_at']) ? date('d/m/Y', strtotime($d['created_at'])) : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Graphiques -->
        <div class="col-12 col-xl-5">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent py-3">
                    <h2 class="h6 mb-0 fw-semibold">
                        <i class="fa-solid fa-chart-pie me-2 text-bofa-bleu"></i>Répartition par statut
                    </h2>
                </div>
                <div class="card-body d-flex justify-content-center" style="height:220px;">
                    <canvas id="chartStatus"></canvas>
                </div>
            </div>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3">
                    <h2 class="h6 mb-0 fw-semibold">
                        <i class="fa-solid fa-chart-bar me-2 text-bofa-bleu"></i>Dossiers par pays
                    </h2>
                </div>
                <div class="card-body" style="height:220px;">
                    <canvas id="chartPays"></canvas>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
/* Graphique camembert — statuts */
(function () {
    const statusLabels = <?= json_encode(array_column($chartStatus, 'status')) ?>;
    const statusData   = <?= json_encode(array_map('intval', array_column($chartStatus, 'nb'))) ?>;
    const colors = ['#6c757d','#0d6efd','#ffc107','#198754','#dc3545','#0dcaf0','#fd7e14','#adb5bd'];

    new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: { labels: statusLabels, datasets: [{ data: statusData, backgroundColor: colors, borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { font: { size: 11 } } } } }
    });

    /* Graphique barres — pays */
    const paysLabels = <?= json_encode(array_column($chartPays, 'pays_emetteur')) ?>;
    const paysData   = <?= json_encode(array_map('intval', array_column($chartPays, 'nb'))) ?>;

    new Chart(document.getElementById('chartPays'), {
        type: 'bar',
        data: { labels: paysLabels, datasets: [{ label: 'Dossiers', data: paysData, backgroundColor: '#012169' }] },
        options: {
            responsive: true, maintainAspectRatio: false, indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { ticks: { font: { size: 11 } } }, y: { ticks: { font: { size: 11 } } } }
        }
    });
})();
</script>

<?php require_once BOFA_ROOT . '/templates/footer.php'; ?>
