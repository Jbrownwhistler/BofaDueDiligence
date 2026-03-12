<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bofa-navy: #012169;
            --bofa-red: #E31837;
            --bofa-dark: #0a1628;
        }
        body {
            background: linear-gradient(135deg, var(--bofa-navy) 0%, var(--bofa-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            max-width: 420px;
            width: 100%;
            margin: auto;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-header {
            background: var(--bofa-navy);
            color: white;
            padding: 2rem;
            text-align: center;
            border-radius: 12px 12px 0 0;
        }
        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }
        .login-header .logo-icon {
            font-size: 2.5rem;
            color: var(--bofa-red);
            margin-bottom: 0.5rem;
        }
        .login-header small {
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
        }
        .login-body {
            padding: 2rem;
            background: white;
            border-radius: 0 0 12px 12px;
        }
        .btn-bofa {
            background: var(--bofa-red);
            border-color: var(--bofa-red);
            color: white;
            font-weight: 600;
            padding: 0.6rem;
        }
        .btn-bofa:hover {
            background: #c41530;
            border-color: #c41530;
            color: white;
        }
        .form-control:focus {
            border-color: var(--bofa-navy);
            box-shadow: 0 0 0 0.2rem rgba(1, 33, 105, 0.15);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card mx-auto">
            <div class="login-header">
                <div class="logo-icon"><i class="fas fa-shield-halved"></i></div>
                <h1><?= APP_NAME ?></h1>
                <small>AML Compliance & Due Diligence Platform</small>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>login">
                    <?= CSRF::field() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Adresse email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email"
                                   placeholder="votre@email.com" required autofocus
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold">Mot de passe</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="••••••••" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-bofa w-100">
                        <i class="fas fa-sign-in-alt me-1"></i> Se connecter
                    </button>
                </form>

                <hr class="my-3">
                <div class="text-center text-muted small">
                    <i class="fas fa-lock me-1"></i> Connexion sécurisée — <?= APP_NAME ?> v<?= APP_VERSION ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
