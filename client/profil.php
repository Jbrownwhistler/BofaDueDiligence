<?php
/**
 * Profil utilisateur client (F10) — BofaDueDiligence
 * Modification des informations personnelles et changement de mot de passe.
 */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';

bofa_auth_check(['client']);

require_once BOFA_ROOT . '/src/User.php';
require_once BOFA_ROOT . '/src/Notification.php';

$userId   = (int) $_SESSION['user_id'];
$userObj  = new User();
$notifObj = new Notification();
$errors   = [];
$notifCount = $notifObj->getUnreadCount($userId);

$user = $userObj->getById($userId);
if (!$user) {
    bofa_flash('Utilisateur introuvable.', 'error');
    bofa_redirect(BOFA_URL . '/login.php');
}

/* -----------------------------------------------------------------------
 * Traitement POST — mise à jour du profil
 * ----------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action']     ?? '';

    if (!bofa_csrf_validate($token)) {
        $errors[] = 'Jeton de sécurité invalide. Veuillez réessayer.';
    } elseif ($action === 'profil') {

        /* --- Mise à jour informations personnelles --- */
        $prenom    = trim($_POST['prenom']    ?? '');
        $nom       = trim($_POST['nom']       ?? '');
        $email     = trim($_POST['email']     ?? '');
        $telephone = trim($_POST['telephone'] ?? '');

        if (mb_strlen($prenom) < 2 || mb_strlen($prenom) > 50) {
            $errors[] = 'Le prénom doit contenir entre 2 et 50 caractères.';
        }
        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 50) {
            $errors[] = 'Le nom doit contenir entre 2 et 50 caractères.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $errors[] = 'Adresse e-mail invalide.';
        }
        if ($telephone !== '' && !preg_match('/^\+?[\d\s\-().]{7,20}$/', $telephone)) {
            $errors[] = 'Numéro de téléphone invalide.';
        }

        /* Vérification unicité e-mail */
        if (empty($errors)) {
            $existing = $userObj->getByEmail($email);
            if ($existing && (int)$existing['id'] !== $userId) {
                $errors[] = 'Cette adresse e-mail est déjà utilisée.';
            }
        }

        if (empty($errors)) {
            $ok = $userObj->update($userId, [
                'prenom'    => $prenom,
                'nom'       => $nom,
                'email'     => $email,
                'telephone' => $telephone,
            ]);

            if ($ok) {
                /* Mise à jour des données de session */
                $_SESSION['user_prenom'] = $prenom;
                $_SESSION['user_nom']    = $nom;
                $_SESSION['user_email']  = $email;

                bofa_flash('Profil mis à jour avec succès.', 'success');
                bofa_redirect('/bofa/client/profil.php');
            } else {
                $errors[] = 'Erreur lors de la mise à jour du profil.';
            }
        }

    } elseif ($action === 'password') {

        /* --- Changement de mot de passe --- */
        $oldPassword  = $_POST['old_password']   ?? '';
        $newPassword  = $_POST['new_password']   ?? '';
        $confirmPass  = $_POST['confirm_password'] ?? '';

        if (empty($oldPassword)) {
            $errors[] = 'L\'ancien mot de passe est requis.';
        }
        if (mb_strlen($newPassword) < 8) {
            $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        }
        if (!preg_match('/[A-Z]/', $newPassword)) {
            $errors[] = 'Le nouveau mot de passe doit contenir au moins une majuscule.';
        }
        if (!preg_match('/[0-9]/', $newPassword)) {
            $errors[] = 'Le nouveau mot de passe doit contenir au moins un chiffre.';
        }
        if ($newPassword !== $confirmPass) {
            $errors[] = 'Les nouveaux mots de passe ne correspondent pas.';
        }

        if (empty($errors)) {
            $ok = $userObj->changePassword($userId, $oldPassword, $newPassword);
            if ($ok) {
                bofa_flash('Mot de passe modifié avec succès.', 'success');
                bofa_redirect('/bofa/client/profil.php');
            } else {
                $errors[] = 'L\'ancien mot de passe est incorrect.';
            }
        }
    }

    /* Rechargement des données après modification potentielle */
    $user = $userObj->getById($userId) ?? $user;
}

$pageTitle   = 'Mon profil';
$currentPage = 'profil';
require_once BOFA_ROOT . '/templates/header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold text-bofa-bleu">
                <i class="fa-regular fa-id-card me-2"></i>Mon profil
            </h1>
            <p class="text-muted small mb-0">Gérez vos informations personnelles et la sécurité de votre compte.</p>
        </div>
    </div>

    <?php foreach ($errors as $err): ?>
        <?php $alertType = 'danger'; $alertMessage = $err; require BOFA_ROOT . '/templates/partials/alert.php'; ?>
    <?php endforeach; ?>

    <div class="row g-4">

        <!-- ----------------------------------------------------------------
             Avatar / informations de compte
             ---------------------------------------------------------------- -->
        <div class="col-12 col-lg-3">
            <div class="card shadow-sm border-0 text-center">
                <div class="card-body py-4">
                    <!-- Avatar initiales -->
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3"
                         style="width:80px;height:80px;background:var(--bofa-bleu);color:#fff;font-size:1.8rem;font-weight:700;">
                        <?= mb_strtoupper(mb_substr($user['prenom'] ?? '?', 0, 1)) . mb_strtoupper(mb_substr($user['nom'] ?? '?', 0, 1)) ?>
                    </div>
                    <h2 class="h6 fw-bold mb-1">
                        <?= bofa_sanitize(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?>
                    </h2>
                    <p class="text-muted small mb-2"><?= bofa_sanitize($user['email'] ?? '') ?></p>
                    <span class="badge" style="background:var(--bofa-bleu);">
                        <i class="fa-solid fa-user me-1"></i>Client
                    </span>
                </div>
                <div class="card-footer bg-transparent border-0 pb-4">
                    <dl class="text-start small mb-0">
                        <dt class="text-muted">Compte créé le</dt>
                        <dd><?= isset($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : '—' ?></dd>
                        <dt class="text-muted">Téléphone</dt>
                        <dd><?= !empty($user['telephone']) ? bofa_sanitize($user['telephone']) : '<em class="text-muted">Non renseigné</em>' ?></dd>
                        <dt class="text-muted">Authentification 2FA</dt>
                        <dd>
                            <?php if (!empty($user['two_factor_enabled'])): ?>
                            <span class="badge bg-success"><i class="fa-solid fa-shield-halved me-1"></i>Activée</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Non activée</span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- ----------------------------------------------------------------
             Formulaires
             ---------------------------------------------------------------- -->
        <div class="col-12 col-lg-9">

            <!-- Onglets -->
            <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-info"
                            data-bs-toggle="tab" data-bs-target="#pane-info"
                            type="button" role="tab">
                        <i class="fa-regular fa-id-card me-1"></i>Informations
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-password"
                            data-bs-toggle="tab" data-bs-target="#pane-password"
                            type="button" role="tab">
                        <i class="fa-solid fa-key me-1"></i>Mot de passe
                    </button>
                </li>
            </ul>

            <div class="tab-content">

                <!-- Panneau informations personnelles -->
                <div class="tab-pane fade show active" id="pane-info" role="tabpanel">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-transparent py-3">
                            <h2 class="h6 mb-0 fw-semibold">
                                <i class="fa-regular fa-user me-2 text-bofa-bleu"></i>Informations personnelles
                            </h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/bofa/client/profil.php" novalidate>
                                <input type="hidden" name="action"     value="profil">
                                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="prenom" class="form-label fw-semibold small">
                                            Prénom <span class="text-danger">*</span>
                                        </label>
                                        <input type="text"
                                               name="prenom"
                                               id="prenom"
                                               class="form-control"
                                               value="<?= bofa_sanitize($user['prenom'] ?? '') ?>"
                                               maxlength="50"
                                               required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="nom" class="form-label fw-semibold small">
                                            Nom <span class="text-danger">*</span>
                                        </label>
                                        <input type="text"
                                               name="nom"
                                               id="nom"
                                               class="form-control"
                                               value="<?= bofa_sanitize($user['nom'] ?? '') ?>"
                                               maxlength="50"
                                               required>
                                    </div>
                                    <div class="col-md-8">
                                        <label for="email" class="form-label fw-semibold small">
                                            Adresse e-mail <span class="text-danger">*</span>
                                        </label>
                                        <input type="email"
                                               name="email"
                                               id="email"
                                               class="form-control"
                                               value="<?= bofa_sanitize($user['email'] ?? '') ?>"
                                               maxlength="150"
                                               required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="telephone" class="form-label fw-semibold small">Téléphone</label>
                                        <input type="tel"
                                               name="telephone"
                                               id="telephone"
                                               class="form-control"
                                               value="<?= bofa_sanitize($user['telephone'] ?? '') ?>"
                                               maxlength="20"
                                               placeholder="+33 6 12 34 56 78">
                                    </div>
                                </div>

                                <div class="mt-4 d-flex gap-2">
                                    <button type="submit" class="btn btn-bofa">
                                        <i class="fa-solid fa-floppy-disk me-2"></i>Enregistrer
                                    </button>
                                    <a href="/bofa/client/profil.php" class="btn btn-outline-secondary">Annuler</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Panneau changement de mot de passe -->
                <div class="tab-pane fade" id="pane-password" role="tabpanel">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-transparent py-3">
                            <h2 class="h6 mb-0 fw-semibold">
                                <i class="fa-solid fa-key me-2 text-bofa-bleu"></i>Changer le mot de passe
                            </h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/bofa/client/profil.php" novalidate>
                                <input type="hidden" name="action"     value="password">
                                <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="old_password" class="form-label fw-semibold small">
                                            Mot de passe actuel <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="password"
                                                   name="old_password"
                                                   id="old_password"
                                                   class="form-control"
                                                   autocomplete="current-password"
                                                   required>
                                            <button class="btn btn-outline-secondary" type="button"
                                                    data-toggle-password="old_password">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="new_password" class="form-label fw-semibold small">
                                            Nouveau mot de passe <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="password"
                                                   name="new_password"
                                                   id="new_password"
                                                   class="form-control"
                                                   autocomplete="new-password"
                                                   minlength="8"
                                                   required>
                                            <button class="btn btn-outline-secondary" type="button"
                                                    data-toggle-password="new_password">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
                                        <!-- Indicateur de force -->
                                        <div class="mt-1">
                                            <div class="progress" style="height:4px;">
                                                <div id="strengthBar" class="progress-bar" style="width:0%;"></div>
                                            </div>
                                            <small id="strengthText" class="text-muted"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirm_password" class="form-label fw-semibold small">
                                            Confirmer le nouveau mot de passe <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="password"
                                                   name="confirm_password"
                                                   id="confirm_password"
                                                   class="form-control"
                                                   autocomplete="new-password"
                                                   required>
                                            <button class="btn btn-outline-secondary" type="button"
                                                    data-toggle-password="confirm_password">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Critères -->
                                <ul class="list-unstyled small text-muted mt-3 mb-0">
                                    <li id="crit-length"><i class="fa-solid fa-circle me-1" style="font-size:.5rem;"></i>8 caractères minimum</li>
                                    <li id="crit-upper"><i class="fa-solid fa-circle me-1" style="font-size:.5rem;"></i>Une majuscule</li>
                                    <li id="crit-digit"><i class="fa-solid fa-circle me-1" style="font-size:.5rem;"></i>Un chiffre</li>
                                </ul>

                                <div class="mt-4 d-flex gap-2">
                                    <button type="submit" class="btn btn-bofa">
                                        <i class="fa-solid fa-key me-2"></i>Changer le mot de passe
                                    </button>
                                    <a href="/bofa/client/profil.php" class="btn btn-outline-secondary">Annuler</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div><!-- /.tab-content -->
        </div><!-- /.col formulaires -->
    </div><!-- /.row -->
</div><!-- /.container-fluid -->

<script>
/* Afficher/masquer le mot de passe */
document.querySelectorAll('[data-toggle-password]').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.togglePassword);
        const icon  = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
});

/* Indicateur de force du mot de passe */
document.getElementById('new_password')?.addEventListener('input', function () {
    const val      = this.value;
    const bar      = document.getElementById('strengthBar');
    const text     = document.getElementById('strengthText');
    const critLen  = document.getElementById('crit-length');
    const critUp   = document.getElementById('crit-upper');
    const critDig  = document.getElementById('crit-digit');

    const hasLen   = val.length >= 8;
    const hasUpper = /[A-Z]/.test(val);
    const hasDigit = /[0-9]/.test(val);
    const hasSpec  = /[^A-Za-z0-9]/.test(val);

    /* Mise à jour des critères */
    const checkCrit = (el, ok) => {
        el.style.color = ok ? 'var(--bs-success)' : '';
    };
    checkCrit(critLen, hasLen);
    checkCrit(critUp,  hasUpper);
    checkCrit(critDig, hasDigit);

    let score = 0;
    if (hasLen)   score++;
    if (hasUpper) score++;
    if (hasDigit) score++;
    if (hasSpec)  score++;

    const levels = [
        { pct: 0,   cls: 'bg-secondary', label: '' },
        { pct: 25,  cls: 'bg-danger',    label: 'Très faible' },
        { pct: 50,  cls: 'bg-warning',   label: 'Faible' },
        { pct: 75,  cls: 'bg-info',      label: 'Moyen' },
        { pct: 100, cls: 'bg-success',   label: 'Fort' },
    ];
    const lvl = levels[score] ?? levels[0];

    bar.style.width      = lvl.pct + '%';
    bar.className        = 'progress-bar ' + lvl.cls;
    text.textContent     = lvl.label;
});

/* Basculer vers l'onglet mot de passe si erreur de type password */
<?php if (!empty($errors) && isset($_POST['action']) && $_POST['action'] === 'password'): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('tab-password')?.click();
});
<?php endif; ?>
</script>

<?php require_once BOFA_ROOT . '/templates/footer.php'; ?>
