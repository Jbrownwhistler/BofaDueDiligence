<?php
/**
 * Gestion des documents du client (F05) — BofaDueDiligence
 * Téléversement, liste et statut des documents par dossier.
 */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';

bofa_auth_check(['client']);

require_once BOFA_ROOT . '/src/Document.php';
require_once BOFA_ROOT . '/src/CaseAML.php';
require_once BOFA_ROOT . '/src/Notification.php';

$userId   = (int) $_SESSION['user_id'];
$docObj   = new Document();
$caseObj  = new CaseAML();
$notifObj = new Notification();
$errors   = [];
$notifCount = $notifObj->getUnreadCount($userId);

/* -----------------------------------------------------------------------
 * Récupération des dossiers du client (pour le sélecteur)
 * ----------------------------------------------------------------------- */
$dossiersData = $caseObj->getByClient($userId, 1, 100);
$dossiers     = $dossiersData['cases'] ?? $dossiersData;

/* -----------------------------------------------------------------------
 * Traitement POST — téléversement de document
 * ----------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {

    $token   = $_POST['csrf_token'] ?? '';
    $caseId  = (int) ($_POST['case_id'] ?? 0);

    if (!bofa_csrf_validate($token)) {
        $errors[] = 'Jeton de sécurité invalide. Veuillez réessayer.';
    } elseif ($caseId <= 0) {
        $errors[] = 'Veuillez sélectionner un dossier.';
    } elseif (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['document']['error'] ?? -1;
        $errors[] = match($errCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée (10 Mo).',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier sélectionné.',
            default            => 'Erreur lors du téléversement (code ' . $errCode . ').',
        };
    } else {
        /* Vérification taille */
        if ($_FILES['document']['size'] > BOFA_UPLOAD_MAX) {
            $errors[] = 'Le fichier dépasse la limite de ' . round(BOFA_UPLOAD_MAX / 1048576) . ' Mo.';
        }

        /* Vérification MIME réel */
        $allowedMimes = $docObj->getAllowedMimes();
        $finfo        = new finfo(FILEINFO_MIME_TYPE);
        $realMime     = $finfo->file($_FILES['document']['tmp_name']);

        if (!in_array($realMime, $allowedMimes, true)) {
            $errors[] = 'Type de fichier non autorisé. Formats acceptés : PDF, JPEG, PNG, DOCX.';
        }

        /* Vérification que le dossier appartient au client */
        $dossierOk = false;
        foreach ($dossiers as $d) {
            if ((int)$d['id'] === $caseId) { $dossierOk = true; break; }
        }
        if (!$dossierOk) {
            $errors[] = 'Dossier non autorisé.';
        }

        if (empty($errors)) {
            $docId = $docObj->upload($caseId, $userId, $_FILES['document']);
            if ($docId) {
                bofa_flash('Document téléversé avec succès.', 'success');
                bofa_redirect('/bofa/client/documents.php?case_id=' . $caseId . '&uploaded=1');
            } else {
                $errors[] = 'Erreur lors de l\'enregistrement du document.';
            }
        }
    }
}

/* -----------------------------------------------------------------------
 * Filtre par dossier (GET)
 * ----------------------------------------------------------------------- */
$filterCaseId = (int) ($_GET['case_id'] ?? 0);
$allDocuments = [];

try {
    $db = bofa_db();
    if ($filterCaseId > 0) {
        $allDocuments = $docObj->getByCaseId($filterCaseId);
    } else {
        /* Tous les documents du client */
        $stmtDocs = $db->prepare(
            "SELECT d.*, c.case_number, c.client_id
             FROM documents d
             JOIN cases c ON c.id = d.case_id
             WHERE c.client_id = :uid
             ORDER BY d.created_at DESC
             LIMIT 100"
        );
        $stmtDocs->execute([':uid' => $userId]);
        $allDocuments = $stmtDocs->fetchAll();
    }
} catch (PDOException $e) {
    error_log('[documents.php] Erreur chargement documents : ' . $e->getMessage());
}

/* Correspondance statut document → badge */
$docStatusBadge = [
    'en_attente' => ['bg-secondary', 'En attente'],
    'valide'     => ['bg-success',   'Validé'],
    'rejete'     => ['bg-danger',    'Rejeté'],
    'demande'    => ['bg-warning text-dark', 'Demandé'],
];

$pageTitle   = 'Mes documents';
$currentPage = 'documents';
require_once BOFA_ROOT . '/templates/header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold text-bofa-bleu">
                <i class="fa-solid fa-folder-open me-2"></i>Mes documents
            </h1>
            <p class="text-muted small mb-0">Téléversez et suivez vos documents de conformité.</p>
        </div>
    </div>

    <?php foreach ($errors as $err): ?>
        <?php $alertType = 'danger'; $alertMessage = $err; require BOFA_ROOT . '/templates/partials/alert.php'; ?>
    <?php endforeach; ?>

    <?php if (isset($_GET['uploaded'])): ?>
        <?php $alertType = 'success'; $alertMessage = 'Document téléversé avec succès.'; require BOFA_ROOT . '/templates/partials/alert.php'; ?>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ----------------------------------------------------------------
             Formulaire de téléversement
             ---------------------------------------------------------------- -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 sticky-top" style="top:80px;">
                <div class="card-header bg-transparent py-3">
                    <h2 class="h6 mb-0 fw-semibold">
                        <i class="fa-solid fa-upload me-2 text-bofa-bleu"></i>Téléverser un document
                    </h2>
                </div>
                <div class="card-body">
                    <form method="POST"
                          action="/bofa/client/documents.php"
                          enctype="multipart/form-data"
                          id="uploadForm">
                        <input type="hidden" name="action"     value="upload">
                        <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">

                        <!-- Sélecteur de dossier -->
                        <div class="mb-3">
                            <label for="case_id" class="form-label fw-semibold small">
                                Dossier concerné <span class="text-danger">*</span>
                            </label>
                            <select name="case_id" id="case_id"
                                    class="form-select form-select-sm" required>
                                <option value="">— Sélectionner un dossier —</option>
                                <?php foreach ($dossiers as $d): ?>
                                <option value="<?= (int)$d['id'] ?>"
                                    <?= (int)$d['id'] === $filterCaseId ? 'selected' : '' ?>>
                                    <?= bofa_sanitize($d['case_number'] ?? 'AML-EDD-?') ?>
                                    — <?= number_format((float)($d['montant'] ?? 0), 0, ',', ' ') ?> <?= bofa_sanitize($d['devise'] ?? 'EUR') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Zone de dépôt de fichier -->
                        <div class="mb-3">
                            <label for="document" class="form-label fw-semibold small">
                                Fichier <span class="text-danger">*</span>
                            </label>
                            <div class="border rounded-2 p-3 text-center bg-light"
                                 id="dropZone"
                                 style="cursor:pointer; border-style:dashed !important; transition:background .2s;">
                                <i class="fa-solid fa-file-arrow-up fa-2x text-muted mb-2"></i>
                                <p class="small text-muted mb-1">Glissez un fichier ici ou cliquez pour parcourir</p>
                                <p class="text-muted" style="font-size:.75rem;">PDF, JPEG, PNG, DOCX — max 10 Mo</p>
                                <input type="file"
                                       name="document"
                                       id="document"
                                       class="d-none"
                                       accept=".pdf,.jpg,.jpeg,.png,.docx"
                                       required>
                            </div>
                            <!-- Nom du fichier sélectionné -->
                            <p class="small text-muted mt-1 mb-0" id="fileName">Aucun fichier sélectionné</p>
                        </div>

                        <!-- Barre de progression JS -->
                        <div class="mb-3 d-none" id="progressWrap">
                            <div class="progress" style="height:8px;">
                                <div id="progressBar"
                                     class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                     style="width:0%">
                                </div>
                            </div>
                            <p class="small text-muted mt-1 mb-0" id="progressText">Téléversement en cours…</p>
                        </div>

                        <button type="submit" class="btn btn-bofa w-100" id="btnUpload">
                            <i class="fa-solid fa-upload me-2"></i>Téléverser
                        </button>
                    </form>
                </div>
                <div class="card-footer bg-transparent border-0 small text-muted">
                    <i class="fa-solid fa-shield-halved me-1 text-success"></i>
                    Transfert chiffré — vos documents sont stockés de manière sécurisée.
                </div>
            </div>
        </div>

        <!-- ----------------------------------------------------------------
             Liste des documents
             ---------------------------------------------------------------- -->
        <div class="col-12 col-lg-8">

            <!-- Filtre par dossier -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body py-2">
                    <form method="GET" action="/bofa/client/documents.php" class="d-flex gap-2 align-items-center flex-wrap">
                        <label class="small text-muted mb-0">Filtrer :</label>
                        <select name="case_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                            <option value="">Tous les dossiers</option>
                            <?php foreach ($dossiers as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"
                                <?= (int)$d['id'] === $filterCaseId ? 'selected' : '' ?>>
                                <?= bofa_sanitize($d['case_number'] ?? 'AML-EDD-?') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($filterCaseId > 0): ?>
                        <a href="/bofa/client/documents.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-xmark me-1"></i>Réinitialiser
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if (empty($allDocuments)): ?>
            <div class="card shadow-sm border-0 text-center py-5">
                <div class="card-body">
                    <i class="fa-solid fa-file-circle-question fa-4x text-muted opacity-25 mb-3"></i>
                    <h3 class="h5 text-muted">Aucun document trouvé</h3>
                    <p class="text-muted small">Utilisez le formulaire pour téléverser vos premiers documents.</p>
                </div>
            </div>
            <?php else: ?>

            <!-- Grille de documents -->
            <div class="row g-3">
            <?php foreach ($allDocuments as $doc):
                $badge    = $docStatusBadge[$doc['status'] ?? 'en_attente'] ?? ['bg-secondary', 'Inconnu'];
                $ext      = strtolower(pathinfo($doc['original_name'] ?? '', PATHINFO_EXTENSION));
                $iconFile = match($ext) {
                    'pdf'         => 'fa-file-pdf text-danger',
                    'jpg', 'jpeg' => 'fa-file-image text-warning',
                    'png'         => 'fa-file-image text-info',
                    'docx', 'doc' => 'fa-file-word text-primary',
                    default       => 'fa-file text-secondary',
                };
                $dateDoc  = isset($doc['created_at']) ? date('d/m/Y H:i', strtotime($doc['created_at'])) : '—';
            ?>
            <div class="col-12 col-sm-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body d-flex gap-3 align-items-start">
                        <!-- Icône / vignette -->
                        <div class="rounded-2 p-3 bg-light flex-shrink-0 text-center" style="width:56px;">
                            <i class="fa-solid <?= $iconFile ?> fa-lg"></i>
                        </div>

                        <div class="flex-grow-1 min-width-0">
                            <p class="fw-semibold mb-1 text-truncate small">
                                <?= bofa_sanitize($doc['original_name'] ?? 'Document') ?>
                            </p>
                            <p class="text-muted mb-1" style="font-size:.75rem;">
                                <?= isset($doc['case_number']) ? bofa_sanitize($doc['case_number']) : '' ?>
                                — <?= $dateDoc ?>
                            </p>
                            <span class="badge <?= $badge[0] ?> small">
                                <?= htmlspecialchars($badge[1], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <?php if (!empty($doc['rejection_reason']) && $doc['status'] === 'rejete'): ?>
                            <p class="text-danger small mt-1 mb-0">
                                <i class="fa-solid fa-circle-exclamation me-1"></i>
                                <?= bofa_sanitize($doc['rejection_reason']) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>

            <!-- Légende statuts -->
            <div class="mt-3 d-flex flex-wrap gap-2 small text-muted">
                <span><span class="badge bg-secondary">En attente</span> En cours d'examen</span>
                <span><span class="badge bg-success">Validé</span> Document accepté</span>
                <span><span class="badge bg-danger">Rejeté</span> À remplacer</span>
                <span><span class="badge bg-warning text-dark">Demandé</span> Requis par l'agent</span>
            </div>

            <?php endif; ?>
        </div><!-- /.col documents -->
    </div><!-- /.row -->
</div><!-- /.container-fluid -->

<script>
/* Gestion de la zone de dépôt et de la barre de progression */
(function () {
    const dropZone  = document.getElementById('dropZone');
    const fileInput = document.getElementById('document');
    const fileNameEl = document.getElementById('fileName');
    const form      = document.getElementById('uploadForm');
    const progWrap  = document.getElementById('progressWrap');
    const progBar   = document.getElementById('progressBar');
    const progText  = document.getElementById('progressText');
    const btnUpload = document.getElementById('btnUpload');

    /* Clic sur la zone déclenche l'input */
    dropZone.addEventListener('click', () => fileInput.click());

    /* Drag & drop */
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.style.background = '#e8f0fe'; });
    dropZone.addEventListener('dragleave', () => { dropZone.style.background = ''; });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.background = '';
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            fileNameEl.textContent = e.dataTransfer.files[0].name;
        }
    });

    fileInput.addEventListener('change', () => {
        fileNameEl.textContent = fileInput.files[0]?.name ?? 'Aucun fichier sélectionné';
    });

    /* Simulation de la barre de progression lors de la soumission */
    form.addEventListener('submit', (e) => {
        if (fileInput.files.length === 0) return;
        e.preventDefault();

        progWrap.classList.remove('d-none');
        btnUpload.disabled = true;

        let pct = 0;
        const iv = setInterval(() => {
            pct = Math.min(pct + Math.random() * 20, 90);
            progBar.style.width = pct + '%';
            progText.textContent = 'Téléversement en cours… ' + Math.round(pct) + '%';
        }, 200);

        /* Soumettre via XHR pour progression réelle */
        const fd = new FormData(form);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action);
        xhr.upload.onprogress = (ev) => {
            if (ev.lengthComputable) {
                clearInterval(iv);
                const real = Math.round(ev.loaded / ev.total * 100);
                progBar.style.width = real + '%';
                progText.textContent = 'Téléversement en cours… ' + real + '%';
            }
        };
        xhr.onload = () => {
            clearInterval(iv);
            progBar.style.width = '100%';
            progText.textContent = 'Téléversement terminé !';
            /* Redirection vers la réponse PHP */
            document.open();
            document.write(xhr.responseText);
            document.close();
            history.pushState({}, '', form.action);
        };
        xhr.onerror = () => {
            clearInterval(iv);
            progWrap.classList.add('d-none');
            btnUpload.disabled = false;
            alert('Erreur réseau lors du téléversement.');
        };
        xhr.send(fd);
    });
})();
</script>

<?php require_once BOFA_ROOT . '/templates/footer.php'; ?>
