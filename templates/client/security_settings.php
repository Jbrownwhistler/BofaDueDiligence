<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-lock me-2 text-bofa-red"></i>Sécurité du compte</h4>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-shield-halved me-2"></i>État de la sécurité</div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div><i class="fas fa-key me-2 text-bofa-navy"></i> Mot de passe</div>
                        <span class="badge bg-success">Configuré</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div><i class="fas fa-mobile-alt me-2 text-bofa-navy"></i> Authentification 2FA</div>
                        <span class="badge bg-warning">Non activée</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div><i class="fas fa-envelope me-2 text-bofa-navy"></i> Email vérifié</div>
                        <span class="badge bg-success">Vérifié</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div><i class="fas fa-fingerprint me-2 text-bofa-navy"></i> Biométrie</div>
                        <span class="badge bg-secondary">Non disponible</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div><i class="fas fa-clock me-2 text-bofa-navy"></i> Expiration de session</div>
                        <span class="badge bg-info">1 heure</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-user-shield me-2"></i>Informations du compte</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Email</td><td><?= htmlspecialchars($user['email']) ?></td></tr>
                    <tr><td class="text-muted">Rôle</td><td><span class="badge bg-primary"><?= ucfirst($user['role']) ?></span></td></tr>
                    <tr><td class="text-muted">Statut</td><td><span class="badge bg-<?= $user['statut'] === 'actif' ? 'success' : 'danger' ?>"><?= ucfirst($user['statut']) ?></span></td></tr>
                    <tr><td class="text-muted">Dernière connexion</td><td><?= $user['dernier_login'] ? date('d/m/Y H:i', strtotime($user['dernier_login'])) : 'N/A' ?></td></tr>
                    <tr><td class="text-muted">Membre depuis</td><td><?= date('d/m/Y', strtotime($user['date_creation'])) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-history me-2"></i>Historique des connexions</div>
            <div class="card-body p-0">
                <?php if (empty($loginHistory)): ?>
                    <div class="text-center text-muted p-4">Aucun historique disponible.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($loginHistory as $lh): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <i class="fas fa-sign-in-alt me-1 text-bofa-navy"></i>
                                    <small><?= htmlspecialchars($lh['action']) ?></small>
                                </div>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($lh['date'])) ?></small>
                            </div>
                            <?php if ($lh['ip']): ?>
                                <small class="text-muted">IP: <?= htmlspecialchars($lh['ip']) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-exclamation-triangle me-2"></i>Recommandations de sécurité</div>
            <div class="card-body">
                <div class="alert alert-warning mb-2 py-2">
                    <small><i class="fas fa-exclamation-triangle me-1"></i> Activez l'authentification à deux facteurs (2FA) pour une sécurité renforcée.</small>
                </div>
                <div class="alert alert-info mb-2 py-2">
                    <small><i class="fas fa-info-circle me-1"></i> Changez votre mot de passe régulièrement (tous les 90 jours recommandé).</small>
                </div>
                <div class="alert alert-info mb-0 py-2">
                    <small><i class="fas fa-info-circle me-1"></i> Ne partagez jamais vos identifiants de connexion.</small>
                </div>
            </div>
        </div>
    </div>
</div>
