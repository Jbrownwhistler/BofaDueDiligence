<?php
/**
 * Page d'accès interdit (403) — BofaDueDiligence
 */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';

http_response_code(403);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès interdit — BofaDueDiligence</title>

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous">
    <!-- Styles BofA -->
    <link rel="stylesheet" href="<?= BOFA_URL ?>/assets/css/bofa.css">
</head>
<body>

<div class="login-page">
    <div class="login-card fade-in text-center" style="max-width:480px;">

        <!-- Icône -->
        <div class="mb-3">
            <div style="
                width:80px; height:80px;
                background: rgba(227,24,55,.12);
                border-radius:50%;
                display:inline-flex;
                align-items:center;
                justify-content:center;
                font-size:2.5rem;
                color: var(--bofa-rouge);
            ">
                <i class="fa-solid fa-ban"></i>
            </div>
        </div>

        <!-- Code erreur -->
        <h1 style="font-size:4rem; font-weight:800; color:var(--bofa-rouge); line-height:1;">403</h1>
        <h2 class="h5 fw-bold mb-2" style="color:var(--bofa-bleu);">Accès interdit</h2>
        <p class="text-secondary mb-4">
            Vous ne disposez pas des autorisations nécessaires pour accéder à cette ressource.
            Si vous pensez qu'il s'agit d'une erreur, contactez votre administrateur.
        </p>

        <hr class="divider-bofa">

        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="<?= BOFA_URL ?>/index.php" class="btn btn-bofa">
                <i class="fa-solid fa-house me-1"></i>
                Tableau de bord
            </a>
            <?php else: ?>
            <a href="<?= BOFA_URL ?>/login.php" class="btn btn-bofa">
                <i class="fa-solid fa-right-to-bracket me-1"></i>
                Se connecter
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                <i class="fa-solid fa-arrow-left me-1"></i>
                Retour
            </button>
        </div>

        <p class="text-secondary small mt-4 mb-0">
            BofaDueDiligence v<?= BOFA_VERSION ?> &mdash; Conformité AML/EDD
        </p>
    </div>
</div>

<!-- Bootstrap 5.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmzFVBJZv09+ooHRHCg1Zr5G8GNI"
        crossorigin="anonymous"></script>
<script src="<?= BOFA_URL ?>/assets/js/bofa.js"></script>
</body>
</html>
