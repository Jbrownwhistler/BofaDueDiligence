<?php
/**
 * Vérification à deux facteurs — BofaDueDiligence
 * Valide le code TOTP ou le code de secours avant de finaliser la connexion.
 */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';

/* Seules les sessions en attente de 2FA peuvent accéder à cette page */
if (empty($_SESSION['2fa_pending'])) {
    bofa_redirect(BOFA_URL . '/login.php');
}

require_once dirname(__DIR__) . '/src/TwoFactor.php';
require_once dirname(__DIR__) . '/src/User.php';

$erreurs   = [];
$csrfToken = bofa_csrf_token();

/* ============================================================
   Traitement POST
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Validation CSRF */
    $tokenSoumis = $_POST['csrf_token'] ?? '';
    if (!bofa_csrf_validate($tokenSoumis)) {
        $erreurs[] = 'Jeton de sécurité invalide. Veuillez réessayer.';
    }

    if (empty($erreurs)) {
        $code          = trim($_POST['code'] ?? '');
        $codeSecours   = trim($_POST['backup_code'] ?? '');
        $secret        = $_SESSION['2fa_secret'] ?? '';
        $userId        = (int) ($_SESSION['2fa_user_id'] ?? 0);
        $estValide     = false;

        if (!empty($codeSecours)) {
            /* Vérification par code de secours */
            $tf = new TwoFactor();
            $estValide = $tf->verifyBackupCode($userId, $codeSecours);
            if (!$estValide) {
                $erreurs[] = 'Code de secours invalide ou déjà utilisé.';
            }
        } elseif (!empty($code)) {
            /* Vérification TOTP */
            if (!preg_match('/^\d{6}$/', $code)) {
                $erreurs[] = 'Le code doit comporter exactement 6 chiffres.';
            } else {
                $tf = new TwoFactor();
                $estValide = $tf->verifyCode($secret, $code);
                if (!$estValide) {
                    $erreurs[] = 'Code incorrect ou expiré. Vérifiez votre application d\'authentification.';
                }
            }
        } else {
            $erreurs[] = 'Veuillez saisir votre code à 6 chiffres ou un code de secours.';
        }

        if ($estValide && empty($erreurs)) {
            /* Finaliser la connexion */
            $userId     = (int) $_SESSION['2fa_user_id'];
            $userRole   = $_SESSION['2fa_user_role'];
            $userEmail  = $_SESSION['2fa_user_email'];
            $userNom    = $_SESSION['2fa_user_nom'];
            $userPrenom = $_SESSION['2fa_user_prenom'];

            /* Effacer les données temporaires 2FA */
            unset(
                $_SESSION['2fa_pending'],
                $_SESSION['2fa_user_id'],
                $_SESSION['2fa_user_role'],
                $_SESSION['2fa_user_email'],
                $_SESSION['2fa_user_nom'],
                $_SESSION['2fa_user_prenom'],
                $_SESSION['2fa_secret']
            );

            session_regenerate_id(true);

            $_SESSION['user_id']       = $userId;
            $_SESSION['user_role']     = $userRole;
            $_SESSION['user_email']    = $userEmail;
            $_SESSION['user_nom']      = $userNom;
            $_SESSION['user_prenom']   = $userPrenom;
            $_SESSION['last_activity'] = time();

            /* Enregistrement de la session en base */
            $token = bin2hex(random_bytes(32));
            $userObj = new User();
            $userObj->logSession(
                $userId,
                $token,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? 'inconnu'
            );
            $_SESSION['session_token'] = $token;

            bofa_flash('Connexion sécurisée établie.', 'success');
            bofa_redirect(BOFA_URL . '/index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Vérification 2FA — BofaDueDiligence</title>

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
    <div class="login-card fade-in">

        <!-- En-tête -->
        <div class="login-logo">
            <div class="login-logo-icon" style="background: var(--bofa-rouge);">
                <i class="fa-solid fa-mobile-screen-button"></i>
            </div>
            <h1 class="login-title">
                <span class="txt-bleu">Vérification</span> <span class="txt-rouge">2FA</span>
            </h1>
            <p class="login-subtitle">Authentification à deux facteurs</p>
        </div>

        <p class="text-secondary text-center small mb-3">
            Saisissez le code à 6 chiffres généré par votre application d'authentification
            (<em>Google Authenticator</em>, <em>Authy</em>, etc.).
        </p>

        <!-- Erreurs -->
        <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            <?php foreach ($erreurs as $err): ?>
                <div><?= bofa_sanitize($err) ?></div>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Formulaire principal : code TOTP -->
        <form method="POST" action="<?= BOFA_URL ?>/deux-facteurs.php" data-validate novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="mb-3">
                <label for="code" class="form-label fw-semibold">
                    <i class="fa-solid fa-key me-1 text-bofa-bleu"></i>
                    Code de vérification
                </label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    class="form-control form-control-lg text-center font-mono"
                    placeholder="000000"
                    maxlength="6"
                    pattern="\d{6}"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                >
                <div class="invalid-feedback text-center">Code à 6 chiffres requis.</div>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-bofa btn-lg">
                    <i class="fa-solid fa-check-double me-2"></i>
                    Vérifier le code
                </button>
            </div>
        </form>

        <hr class="divider-bofa">

        <!-- Section code de secours -->
        <details class="mt-2">
            <summary class="text-secondary small cursor-pointer mb-2">
                <i class="fa-solid fa-life-ring me-1"></i>
                Utiliser un code de secours
            </summary>

            <form method="POST" action="<?= BOFA_URL ?>/deux-facteurs.php" class="mt-2">
                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">

                <div class="mb-3">
                    <label for="backup_code" class="form-label small fw-semibold">
                        Code de secours
                    </label>
                    <input
                        type="text"
                        id="backup_code"
                        name="backup_code"
                        class="form-control font-mono"
                        placeholder="XXXX-XXXX"
                        autocomplete="off"
                        spellcheck="false"
                    >
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-key me-1"></i>
                        Utiliser ce code de secours
                    </button>
                </div>
            </form>
        </details>

        <div class="text-center mt-3">
            <a href="<?= BOFA_URL ?>/logout.php" class="text-secondary small">
                <i class="fa-solid fa-arrow-left me-1"></i>
                Retour à la connexion
            </a>
        </div>
    </div>
</div>

<!-- Bootstrap 5.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmzFVBJZv09+ooHRHCg1Zr5G8GNI"
        crossorigin="anonymous"></script>
<!-- Scripts BofA -->
<script src="<?= BOFA_URL ?>/assets/js/bofa.js"></script>
<script>
/* Formater automatiquement la saisie du code TOTP */
document.getElementById('code')?.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
});
</script>
</body>
</html>
