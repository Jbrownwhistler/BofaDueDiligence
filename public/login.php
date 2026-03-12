<?php
/**
 * Page de connexion — BofaDueDiligence
 * Authentification email / mot de passe avec protection CSRF et 2FA.
 */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';

/* Déjà connecté → rediriger vers l'index */
if (!empty($_SESSION['user_id'])) {
    bofa_redirect(BOFA_URL . '/index.php');
}

require_once dirname(__DIR__) . '/src/User.php';
require_once dirname(__DIR__) . '/src/TwoFactor.php';

$erreurs      = [];
$emailValeur  = '';
$timeout      = isset($_GET['timeout']);

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
        $email    = bofa_sanitize($_POST['email']    ?? '');
        $motDePasse = $_POST['password'] ?? '';
        $emailValeur = $email;

        /* Validation basique côté serveur */
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = 'Adresse e-mail invalide.';
        }
        if (empty($motDePasse)) {
            $erreurs[] = 'Le mot de passe est requis.';
        }
    }

    if (empty($erreurs)) {
        $user = new User();
        $utilisateur = $user->authenticate($email, $motDePasse);

        if ($utilisateur === false) {
            /* Pause anti-timing pour limiter la divulgation d'information */
            usleep(random_int(200000, 400000));
            $erreurs[] = 'Identifiants incorrects ou compte bloqué temporairement.';
        } else {
            /* Vérifier si la 2FA est activée pour cet utilisateur */
            if (!empty($utilisateur['deux_facteurs_actif'])) {
                /* Stocker les données temporaires en attente de la 2FA */
                session_regenerate_id(true);
                $_SESSION['2fa_pending']    = true;
                $_SESSION['2fa_user_id']    = $utilisateur['id'];
                $_SESSION['2fa_user_role']  = $utilisateur['role'];
                $_SESSION['2fa_user_email'] = $utilisateur['email'];
                $_SESSION['2fa_user_nom']   = $utilisateur['nom'];
                $_SESSION['2fa_user_prenom']= $utilisateur['prenom'];
                $_SESSION['2fa_secret']     = $utilisateur['deux_facteurs_secret'];
                bofa_redirect(BOFA_URL . '/deux-facteurs.php');
            }

            /* Connexion directe sans 2FA */
            session_regenerate_id(true);
            $_SESSION['user_id']    = $utilisateur['id'];
            $_SESSION['user_role']  = $utilisateur['role'];
            $_SESSION['user_email'] = $utilisateur['email'];
            $_SESSION['user_nom']   = $utilisateur['nom'];
            $_SESSION['user_prenom']= $utilisateur['prenom'];
            $_SESSION['last_activity'] = time();

            /* Enregistrement de la session en base */
            $token = bin2hex(random_bytes(32));
            $user->logSession(
                $utilisateur['id'],
                $token,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? 'inconnu'
            );
            $_SESSION['session_token'] = $token;

            bofa_flash('Bienvenue, ' . bofa_sanitize($utilisateur['prenom']) . ' !', 'success');
            bofa_redirect(BOFA_URL . '/index.php');
        }
    }
}

/* Récupération du message flash éventuel */
$messagesFlash = bofa_get_flash();
$csrfToken     = bofa_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Connexion — BofaDueDiligence</title>

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

        <!-- Logo BofA -->
        <div class="login-logo">
            <div class="login-logo-icon">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 class="login-title">
                <span class="txt-bleu">BofA</span><span class="txt-rouge">Due</span>Diligence
            </h1>
            <p class="login-subtitle">Conformité AML / EDD</p>
        </div>

        <!-- Message timeout -->
        <?php if ($timeout): ?>
        <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>Votre session a expiré. Veuillez vous reconnecter.</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Messages flash -->
        <?php foreach ($messagesFlash as $flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : bofa_sanitize($flash['type']) ?> alert-dismissible fade show"
             role="alert" data-auto-dismiss="5000">
            <?= bofa_sanitize($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>

        <!-- Erreurs de soumission -->
        <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            <?php foreach ($erreurs as $err): ?>
                <div><?= bofa_sanitize($err) ?></div>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Formulaire de connexion -->
        <form method="POST" action="<?= BOFA_URL ?>/login.php" data-validate novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <!-- E-mail -->
            <div class="mb-3">
                <label for="email" class="form-label fw-semibold">
                    <i class="fa-regular fa-envelope me-1 text-bofa-bleu"></i>
                    Adresse e-mail
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="prenom.nom@exemple.com"
                    value="<?= bofa_sanitize($emailValeur) ?>"
                    required
                    autocomplete="email"
                    autofocus
                >
                <div class="invalid-feedback">Adresse e-mail invalide.</div>
            </div>

            <!-- Mot de passe -->
            <div class="mb-3">
                <label for="password" class="form-label fw-semibold">
                    <i class="fa-solid fa-lock me-1 text-bofa-bleu"></i>
                    Mot de passe
                </label>
                <div class="input-group">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                        data-minlength="8"
                    >
                    <button type="button"
                            class="btn btn-outline-secondary"
                            title="Afficher/masquer le mot de passe"
                            onclick="togglePasswordVisibility()">
                        <i class="fa-regular fa-eye" id="eye-icon"></i>
                    </button>
                </div>
                <div class="invalid-feedback">Mot de passe requis.</div>
            </div>

            <!-- Se souvenir de moi -->
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                <label class="form-check-label text-secondary" for="remember">
                    Se souvenir de moi
                </label>
            </div>

            <!-- Bouton connexion -->
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-bofa btn-lg">
                    <i class="fa-solid fa-right-to-bracket me-2"></i>
                    Se connecter
                </button>
            </div>
        </form>

        <!-- Pied de page -->
        <hr class="divider-bofa mt-4">
        <p class="text-center text-secondary small mb-0">
            <i class="fa-solid fa-lock-open fa-xs me-1"></i>
            Accès réservé aux utilisateurs autorisés
        </p>
    </div>
</div>

<!-- Bootstrap 5.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmzFVBJZv09+ooHRHCg1Zr5G8GNI"
        crossorigin="anonymous"></script>
<!-- Scripts BofA -->
<script src="<?= BOFA_URL ?>/assets/js/bofa.js"></script>
<script>
function togglePasswordVisibility() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eye-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa-regular fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fa-regular fa-eye';
    }
}
</script>
</body>
</html>
