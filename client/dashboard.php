<?php
/**
 * Tableau de bord client (F01) — BofaDueDiligence
 * Affiche le solde, les fonds bloqués, les fonds disponibles
 * et les 5 derniers dossiers AML de l'utilisateur connecté.
 */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';

bofa_auth_check(['client']);

/* -----------------------------------------------------------------------
 * Chargement des classes
 * ----------------------------------------------------------------------- */
require_once BOFA_ROOT . '/src/User.php';
require_once BOFA_ROOT . '/src/CaseAML.php';
require_once BOFA_ROOT . '/src/Notification.php';

$userId       = (int) $_SESSION['user_id'];
$userObj      = new User();
$caseObj      = new CaseAML();
$notifObj     = new Notification();

/* -----------------------------------------------------------------------
 * Récupération des données
 * ----------------------------------------------------------------------- */
$user         = $userObj->getById($userId);
$notifCount   = $notifObj->getUnreadCount($userId);

/* Dossiers du client (page 1, 5 récents) */
$dossiersData = $caseObj->getByClient($userId, 1, 5);
$dossiers     = $dossiersData['cases'] ?? $dossiersData;

/* Calcul des agrégats financiers */
$soldePrincipal  = 0.0;
$fondsBlockes    = 0.0;
$fondsDispos     = 0.0;
$nbBlockes       = 0;

try {
    $db = bofa_db();

    /* Solde principal depuis la table users */
    $stmtSolde = $db->prepare("SELECT solde_principal FROM users WHERE id = :id LIMIT 1");
    $stmtSolde->execute([':id' => $userId]);
    $soldePrincipal = (float) ($stmtSolde->fetchColumn() ?: 0.0);

    /* Total des fonds bloqués (dossiers actifs) */
    $stmtBloque = $db->prepare(
        "SELECT COUNT(*) AS nb, COALESCE(SUM(montant), 0) AS total
         FROM cases
         WHERE client_id = :id
           AND status NOT IN ('transfert_effectue', 'rejete', 'archive')"
    );
    $stmtBloque->execute([':id' => $userId]);
    $rowBloque    = $stmtBloque->fetch();
    $nbBlockes    = (int)   ($rowBloque['nb']    ?? 0);
    $fondsBlockes = (float) ($rowBloque['total'] ?? 0.0);

    /* Fonds disponibles = solde - fonds bloqués */
    $fondsDispos = max(0.0, $soldePrincipal - $fondsBlockes);

} catch (PDOException $e) {
    error_log('[BofaDueDiligence] dashboard client agrégats : ' . $e->getMessage());
}

/* -----------------------------------------------------------------------
 * Variables de vue
 * ----------------------------------------------------------------------- */
$pageTitle   = 'Tableau de bord';
$currentPage = 'dashboard';

/* Correspondance statut → badge Bootstrap */
$statusBadge = [
    'nouveau'            => ['bg-secondary',  'Nouveau'],
    'en_cours'           => ['bg-primary',    'En cours'],
    'en_attente_doc'     => ['bg-warning text-dark', 'Docs demandés'],
    'pret_transfert'     => ['bg-success',    'Prêt au virement'],
    'transfert_effectue' => ['bg-success',    'Transféré'],
    'gele'               => ['bg-danger',     'Gelé'],
    'rejete'             => ['bg-danger',     'Rejeté'],
    'archive'            => ['bg-light text-dark', 'Archivé'],
];

require_once BOFA_ROOT . '/templates/header.php';
?>

<div class="container-fluid py-4">

    <!-- En-tête de bienvenue -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold text-bofa-bleu">
                Bonjour, <?= bofa_sanitize(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?> 👋
            </h1>
            <p class="text-muted small mb-0">
                <i class="fa-regular fa-calendar me-1"></i>
                <?= date('l d F Y') ?>
                <?php if ($nbBlockes > 0): ?>
                — <span class="text-warning fw-semibold">
                    <?= $nbBlockes ?> dossier<?= $nbBlockes > 1 ? 's' : '' ?> en cours de traitement
                  </span>
                <?php endif; ?>
            </p>
        </div>
        <a href="/bofa/client/fonds.php" class="btn btn-bofa">
            <i class="fa-solid fa-circle-dollar-to-slot me-2"></i>Mes fonds
        </a>
    </div>

    <!-- ----------------------------------------------------------------
         Cartes de synthèse financière
         ---------------------------------------------------------------- -->
    <div class="row g-4 mb-4">

        <!-- Solde principal -->
        <div class="col-12 col-md-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 flex-shrink-0"
                         style="background:rgba(1,33,105,.1);">
                        <i class="fa-solid fa-piggy-bank fa-lg" style="color:var(--bofa-bleu);"></i>
                    </div>
                    <div>
                        <p class="text-muted small mb-1">Solde principal</p>
                        <h2 class="h4 mb-0 fw-bold">
                            <?= number_format($soldePrincipal, 2, ',', ' ') ?> €
                        </h2>
                    </div>
                </div>
                <div class="card-footer border-0 bg-transparent pt-0">
                    <small class="text-muted">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Solde de référence du compte principal
                    </small>
                </div>
            </div>
        </div>

        <!-- Fonds bloqués -->
        <div class="col-12 col-md-4">
            <div class="card h-100 shadow-sm border-0 border-start border-4 border-warning">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 flex-shrink-0" style="background:rgba(227,24,55,.1);">
                        <i class="fa-solid fa-lock fa-lg text-danger"></i>
                    </div>
                    <div>
                        <p class="text-muted small mb-1">Fonds bloqués (<?= $nbBlockes ?> dossier<?= $nbBlockes > 1 ? 's' : '' ?>)</p>
                        <h2 class="h4 mb-0 fw-bold text-danger">
                            <?= number_format($fondsBlockes, 2, ',', ' ') ?> €
                        </h2>
                    </div>
                </div>
                <div class="card-footer border-0 bg-transparent pt-0">
                    <small class="text-muted">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Montants en cours d'examen AML/EDD
                    </small>
                </div>
            </div>
        </div>

        <!-- Fonds disponibles -->
        <div class="col-12 col-md-4">
            <div class="card h-100 shadow-sm border-0 border-start border-4 border-success">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 flex-shrink-0" style="background:rgba(25,135,84,.1);">
                        <i class="fa-solid fa-circle-check fa-lg text-success"></i>
                    </div>
                    <div>
                        <p class="text-muted small mb-1">Fonds disponibles</p>
                        <h2 class="h4 mb-0 fw-bold text-success">
                            <?= number_format($fondsDispos, 2, ',', ' ') ?> €
                        </h2>
                    </div>
                </div>
                <div class="card-footer border-0 bg-transparent pt-0">
                    <small class="text-muted">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Solde moins les fonds bloqués
                    </small>
                </div>
            </div>
        </div>

    </div><!-- /.row cartes -->

    <!-- ----------------------------------------------------------------
         Tableau des dossiers récents
         ---------------------------------------------------------------- -->
    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent d-flex align-items-center justify-content-between py-3">
                    <h2 class="h6 mb-0 fw-semibold">
                        <i class="fa-solid fa-briefcase me-2 text-bofa-bleu"></i>
                        Dossiers récents
                    </h2>
                    <a href="/bofa/client/fonds.php" class="btn btn-sm btn-outline-primary">
                        Voir tout <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($dossiers)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-folder-open fa-3x mb-3 opacity-25"></i>
                        <p class="mb-0">Aucun dossier en cours.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Référence</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Agent</th>
                                    <th class="pe-3">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($dossiers as $d):
                                $badge   = $statusBadge[$d['status']] ?? ['bg-secondary', $d['status']];
                                $montant = number_format((float)($d['montant'] ?? 0), 2, ',', ' ');
                                $devise  = bofa_sanitize($d['devise'] ?? 'EUR');
                                $date    = isset($d['created_at'])
                                    ? date('d/m/Y', strtotime($d['created_at']))
                                    : '—';
                                $agent   = trim(($d['agent_prenom'] ?? '') . ' ' . ($d['agent_nom'] ?? ''));
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <a href="/bofa/client/fonds.php?id=<?= (int)$d['id'] ?>"
                                       class="fw-semibold text-decoration-none text-bofa-bleu">
                                        <?= bofa_sanitize($d['case_number'] ?? 'AML-EDD-?') ?>
                                    </a>
                                </td>
                                <td class="fw-semibold"><?= $montant ?> <?= $devise ?></td>
                                <td>
                                    <span class="badge <?= $badge[0] ?>">
                                        <?= htmlspecialchars($badge[1], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?= $agent ? bofa_sanitize($agent) : '<em>Non assigné</em>' ?>
                                </td>
                                <td class="pe-3 text-muted small"><?= $date ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ----------------------------------------------------------------
             Liens rapides
             ---------------------------------------------------------------- -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent py-3">
                    <h2 class="h6 mb-0 fw-semibold">
                        <i class="fa-solid fa-bolt me-2 text-bofa-rouge"></i>Accès rapides
                    </h2>
                </div>
                <div class="card-body d-grid gap-2">
                    <a href="/bofa/client/documents.php"
                       class="btn btn-outline-primary d-flex align-items-center gap-2">
                        <i class="fa-solid fa-folder-open fa-fw"></i>
                        <span>Mes documents</span>
                    </a>
                    <a href="/bofa/client/messages.php"
                       class="btn btn-outline-secondary d-flex align-items-center gap-2 position-relative">
                        <i class="fa-regular fa-envelope fa-fw"></i>
                        <span>Messagerie sécurisée</span>
                        <?php if ($notifCount > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-auto"><?= $notifCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/bofa/client/historique.php"
                       class="btn btn-outline-secondary d-flex align-items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left fa-fw"></i>
                        <span>Historique des virements</span>
                    </a>
                    <a href="/bofa/client/profil.php"
                       class="btn btn-outline-secondary d-flex align-items-center gap-2">
                        <i class="fa-regular fa-id-card fa-fw"></i>
                        <span>Mon profil</span>
                    </a>
                </div>
            </div>

            <!-- Alerte info réglementaire -->
            <div class="alert alert-info border-0 shadow-sm small" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i>
                <strong>Conformité AML/EDD :</strong> Tous les mouvements de fonds sont soumis
                à une vérification réglementaire avant libération.
                <a href="/bofa/client/fonds.php" class="alert-link">En savoir plus</a>
            </div>
        </div>

    </div><!-- /.row dossiers + liens -->
</div><!-- /.container-fluid -->

<?php require_once BOFA_ROOT . '/templates/footer.php'; ?>
