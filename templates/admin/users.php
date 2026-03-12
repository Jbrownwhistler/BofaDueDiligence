<meta name="base-url" content="<?= BASE_URL ?>">
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-users me-2 text-bofa-red"></i>Gestion des utilisateurs</h4>
    <button class="btn btn-bofa" data-bs-toggle="modal" data-bs-target="#createUserModal"><i class="fas fa-plus me-1"></i>Nouvel utilisateur</button>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="role" class="form-select form-select-sm">
                    <option value="">Tous les rôles</option>
                    <option value="client" <?= ($_GET['role'] ?? '') === 'client' ? 'selected' : '' ?>>Client</option>
                    <option value="agent" <?= ($_GET['role'] ?? '') === 'agent' ? 'selected' : '' ?>>Agent</option>
                    <option value="admin" <?= ($_GET['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="statut" class="form-select form-select-sm">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?= ($_GET['statut'] ?? '') === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactif" <?= ($_GET['statut'] ?? '') === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                </select>
            </div>
            <div class="col-md-3"><input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Rechercher..."></div>
            <div class="col-md-3"><button type="submit" class="btn btn-sm btn-bofa w-100"><i class="fas fa-filter me-1"></i>Filtrer</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>#</th><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Dernier login</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'agent' ? 'info' : 'primary') ?>"><?= ucfirst($u['role']) ?></span></td>
                        <td><span class="badge bg-<?= $u['statut'] === 'actif' ? 'success' : 'secondary' ?>"><?= ucfirst($u['statut']) ?></span></td>
                        <td><?= $u['dernier_login'] ? date('d/m/Y H:i', strtotime($u['dernier_login'])) : '-' ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $u['id'] ?>"><i class="fas fa-edit"></i></button>
                            <form method="POST" action="<?= BASE_URL ?>admin/users/toggle" class="d-inline">
                                <?= CSRF::field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-<?= $u['statut'] === 'actif' ? 'warning' : 'success' ?>" data-confirm="<?= $u['statut'] === 'actif' ? 'Désactiver' : 'Activer' ?> cet utilisateur ?">
                                    <i class="fas fa-<?= $u['statut'] === 'actif' ? 'ban' : 'check' ?>"></i>
                                </button>
                            </form>
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetModal<?= $u['id'] ?>"><i class="fas fa-key"></i></button>
                        </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editModal<?= $u['id'] ?>"><div class="modal-dialog"><div class="modal-content">
                        <form method="POST" action="<?= BASE_URL ?>admin/users/edit">
                            <?= CSRF::field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <div class="modal-header"><h6 class="modal-title">Modifier l'utilisateur</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <div class="row g-2">
                                    <div class="col-6"><label class="form-label">Prénom</label><input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($u['prenom']) ?>" required></div>
                                    <div class="col-6"><label class="form-label">Nom</label><input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($u['nom']) ?>" required></div>
                                </div>
                                <div class="mt-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required></div>
                                <div class="mt-2"><label class="form-label">Rôle</label>
                                    <select name="role" class="form-select">
                                        <option value="client" <?= $u['role'] === 'client' ? 'selected' : '' ?>>Client</option>
                                        <option value="agent" <?= $u['role'] === 'agent' ? 'selected' : '' ?>>Agent</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer"><button type="submit" class="btn btn-bofa">Enregistrer</button></div>
                        </form>
                    </div></div></div>

                    <!-- Reset Password Modal -->
                    <div class="modal fade" id="resetModal<?= $u['id'] ?>"><div class="modal-dialog"><div class="modal-content">
                        <form method="POST" action="<?= BASE_URL ?>admin/users/reset-password">
                            <?= CSRF::field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <div class="modal-header"><h6 class="modal-title">Réinitialiser le mot de passe</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <p class="text-muted">Utilisateur: <strong><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></strong></p>
                                <label class="form-label">Nouveau mot de passe</label>
                                <input type="password" name="new_password" class="form-control" minlength="6" required>
                            </div>
                            <div class="modal-footer"><button type="submit" class="btn btn-warning">Réinitialiser</button></div>
                        </form>
                    </div></div></div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="<?= BASE_URL ?>admin/users/create">
        <?= CSRF::field() ?>
        <div class="modal-header bg-bofa-navy text-white"><h6 class="modal-title"><i class="fas fa-user-plus me-1"></i>Nouvel utilisateur</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-2">
                <div class="col-6"><label class="form-label">Prénom</label><input type="text" name="prenom" class="form-control" required></div>
                <div class="col-6"><label class="form-label">Nom</label><input type="text" name="nom" class="form-control" required></div>
            </div>
            <div class="mt-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
            <div class="mt-2"><label class="form-label">Mot de passe</label><input type="password" name="password" class="form-control" minlength="6" required></div>
            <div class="mt-2"><label class="form-label">Rôle</label>
                <select name="role" class="form-select">
                    <option value="client">Client</option>
                    <option value="agent">Agent</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-bofa">Créer l'utilisateur</button></div>
    </form>
</div></div></div>
