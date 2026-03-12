<?php
class ApiController {
    public function getNotifications(): void {
        header('Content-Type: application/json');

        if (!Auth::check()) {
            echo json_encode(['count' => 0, 'notifications' => []]);
            return;
        }

        $notifications = Notify::getUnread(Auth::id());
        $count = Notify::countUnread(Auth::id());

        echo json_encode([
            'count' => $count,
            'notifications' => $notifications,
        ]);
    }

    public function markNotificationRead(): void {
        header('Content-Type: application/json');

        if (!Auth::check()) {
            echo json_encode(['ok' => false]);
            return;
        }

        if (isset($_POST['all'])) {
            Notify::markAllRead(Auth::id());
        } elseif (isset($_POST['id'])) {
            Notify::markRead((int)$_POST['id'], Auth::id());
        }

        echo json_encode(['ok' => true]);
    }

    public function search(): void {
        if (!Auth::check()) {
            header('Location: ' . BASE_URL . 'login');
            exit;
        }

        $q = trim($_GET['q'] ?? '');
        if (empty($q)) {
            Auth::redirectToDashboard();
        }

        $results = CaseModel::getAll(['search' => $q]);

        // Also search users if admin
        $userResults = [];
        if (Auth::isAdmin()) {
            $userResults = User::getAll(['search' => $q]);
        }

        $pageTitle = 'Recherche: ' . $q;
        include __DIR__ . '/../../templates/layouts/header.php';
        ?>
        <meta name="base-url" content="<?= BASE_URL ?>">
        <h4 class="mb-4"><i class="fas fa-search me-2 text-bofa-red"></i>Résultats pour "<?= htmlspecialchars($q) ?>"</h4>

        <?php if (!empty($results)): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-folder-open me-2"></i>Dossiers (<?= count($results) ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>ID</th><th>Client</th><th>Montant</th><th>Statut</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($results as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['case_id_unique']) ?></strong></td>
                                <td><?= htmlspecialchars($c['client_name'] ?? '-') ?></td>
                                <td>$<?= number_format($c['montant'], 2) ?></td>
                                <td><span class="badge-status status-<?= $c['statut'] ?>"><?= CaseModel::getStatusLabel($c['statut']) ?></span></td>
                                <td>
                                    <?php $link = Auth::isAdmin() ? 'admin/case' : 'agent/case'; ?>
                                    <a href="<?= BASE_URL . $link ?>?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($userResults)): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-users me-2"></i>Utilisateurs (<?= count($userResults) ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th></tr></thead>
                        <tbody>
                            <?php foreach ($userResults as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><span class="badge bg-primary"><?= ucfirst($u['role']) ?></span></td>
                                <td><span class="badge bg-<?= $u['statut'] === 'actif' ? 'success' : 'secondary' ?>"><?= ucfirst($u['statut']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($results) && empty($userResults)): ?>
            <div class="text-center text-muted p-5">
                <i class="fas fa-search fa-3x mb-3"></i>
                <p>Aucun résultat pour "<?= htmlspecialchars($q) ?>"</p>
            </div>
        <?php endif; ?>

        <?php
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function exportCSV(): void {
        if (!Auth::check() || (!Auth::isAgent() && !Auth::isAdmin())) {
            http_response_code(403);
            die('Accès interdit.');
        }

        $type = $_GET['type'] ?? 'cases';

        if ($type === 'cases') {
            $cases = Auth::isAdmin()
                ? CaseModel::getAll()
                : CaseModel::getByAgent(Auth::id());

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="dossiers_aml_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            // BOM for Excel
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($output, ['ID Dossier', 'Montant', 'Devise', 'Emetteur', 'Beneficiaire', 'Pays Origine', 'Pays Destination', 'Type Actif', 'Score Risque', 'Statut', 'Statut Fonds', 'Date Creation'], ';');

            foreach ($cases as $c) {
                fputcsv($output, [
                    $c['case_id_unique'],
                    $c['montant'],
                    $c['devise'],
                    $c['emetteur_nom'],
                    $c['beneficiaire_nom'],
                    $c['pays_origine'],
                    $c['pays_destination'],
                    $c['type_actif'],
                    $c['score_risque'],
                    CaseModel::getStatusLabel($c['statut']),
                    CaseModel::getFondsLabel($c['statut_fonds']),
                    $c['date_creation'],
                ], ';');
            }

            fclose($output);

            AuditLog::log('Export CSV des dossiers', 'cases');
        }

        exit;
    }
}
