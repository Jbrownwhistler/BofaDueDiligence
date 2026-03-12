<meta name="base-url" content="<?= BASE_URL ?>">

<h4 class="mb-4"><i class="fas fa-user me-2 text-bofa-red"></i>Mon profil</h4>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-id-card me-2"></i>Informations personnelles</div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>client/profile/update">
                    <?= CSRF::field() ?>
                    <div class="mb-3">
                        <label class="form-label">Prénom</label>
                        <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($user['prenom']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nom</label>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($user['nom']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        <small class="text-muted">Contactez l'administration pour modifier votre email.</small>
                    </div>
                    <button type="submit" class="btn btn-bofa"><i class="fas fa-save me-1"></i>Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-university me-2"></i>Informations bancaires</div>
            <div class="card-body">
                <?php if ($account): ?>
                    <table class="table table-sm table-borderless">
                        <tr><td class="text-muted">Numéro de compte</td><td class="fw-bold"><?= htmlspecialchars($account['numero_compte_principal']) ?></td></tr>
                        <tr><td class="text-muted">Solde</td><td class="fw-bold text-success fs-5">$<?= number_format($account['solde'], 2) ?></td></tr>
                        <tr><td class="text-muted">Devise</td><td><?= htmlspecialchars($account['devise']) ?></td></tr>
                    </table>
                <?php else: ?>
                    <p class="text-muted">Aucun compte bancaire associé.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-shield-alt me-2"></i>Sécurité</div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr><td class="text-muted">Rôle</td><td><span class="badge bg-primary"><?= ucfirst($user['role']) ?></span></td></tr>
                    <tr><td class="text-muted">Dernière connexion</td><td><?= $user['dernier_login'] ? date('d/m/Y H:i', strtotime($user['dernier_login'])) : 'N/A' ?></td></tr>
                    <tr><td class="text-muted">Membre depuis</td><td><?= date('d/m/Y', strtotime($user['date_creation'])) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
