<?php
class AdminController {
    public function dashboard(): void {
        $statusCounts = CaseModel::countByStatus();
        $totalAmount = CaseModel::totalAmount();
        $overdue = CaseModel::getOverdue();
        $totalCases = array_sum($statusCounts);
        $userCounts = User::countByRole();

        $pageTitle = 'Tableau de bord Admin';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/admin/dashboard.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function userList(): void {
        $filters = [
            'role' => $_GET['role'] ?? '',
            'statut' => $_GET['statut'] ?? '',
            'search' => $_GET['search'] ?? '',
        ];
        $users = User::getAll($filters);
        $agents = User::getAgents();

        $pageTitle = 'Gestion des utilisateurs';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/admin/users.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function createUser(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'admin/users');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $role = $_POST['role'] ?? 'client';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($nom) || empty($prenom) || empty($password)) {
            Session::setFlash('error', 'Tous les champs sont requis.');
            header('Location: ' . BASE_URL . 'admin/users');
            exit;
        }

        if (User::findByEmail($email)) {
            Session::setFlash('error', 'Cet email est déjà utilisé.');
            header('Location: ' . BASE_URL . 'admin/users');
            exit;
        }

        $userId = User::create(['email' => $email, 'nom' => $nom, 'prenom' => $prenom, 'role' => $role, 'password' => $password]);

        // Auto-create account for clients
        if ($role === 'client') {
            Account::create($userId);
        }

        AuditLog::log('Création utilisateur: ' . $email . ' (' . $role . ')', 'users', $userId);
        Session::setFlash('success', 'Utilisateur créé avec succès.');
        header('Location: ' . BASE_URL . 'admin/users');
        exit;
    }

    public function editUser(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'admin/users');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'nom' => trim($_POST['nom'] ?? ''),
            'prenom' => trim($_POST['prenom'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'role' => $_POST['role'] ?? '',
        ];

        User::update($id, $data);
        AuditLog::log('Modification utilisateur #' . $id, 'users', $id);
        Session::setFlash('success', 'Utilisateur modifié.');
        header('Location: ' . BASE_URL . 'admin/users');
        exit;
    }

    public function toggleUser(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'admin/users');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        User::toggleStatus($id);
        AuditLog::log('Activation/Désactivation utilisateur #' . $id, 'users', $id);
        Session::setFlash('success', 'Statut de l\'utilisateur modifié.');
        header('Location: ' . BASE_URL . 'admin/users');
        exit;
    }

    public function resetPassword(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'admin/users');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';

        if (strlen($newPass) < 6) {
            Session::setFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
            header('Location: ' . BASE_URL . 'admin/users');
            exit;
        }

        User::updatePassword($id, $newPass);
        AuditLog::log('Réinitialisation mot de passe utilisateur #' . $id, 'users', $id);
        Session::setFlash('success', 'Mot de passe réinitialisé.');
        header('Location: ' . BASE_URL . 'admin/users');
        exit;
    }

    public function assignAgent(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'admin/users');
            exit;
        }

        $caseId = (int)($_POST['case_id'] ?? 0);
        $agentId = (int)($_POST['agent_id'] ?? 0);

        getDB()->prepare('UPDATE cases SET agent_assigne_id = ? WHERE id = ?')->execute([$agentId, $caseId]);
        AuditLog::log("Assignation agent #$agentId au dossier #$caseId", 'cases', $caseId);

        Notify::send($agentId, 'Nouveau dossier assigné.', 'info', '/agent/case?id=' . $caseId);

        Session::setFlash('success', 'Agent assigné.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . 'admin/cases'));
        exit;
    }

    public function riskCountries(): void {
        $countries = getDB()->query('SELECT * FROM risk_countries ORDER BY coefficient_risque DESC')->fetchAll();

        $pageTitle = 'Pays à risque';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/admin/risk_countries.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function saveRiskCountry(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'admin/risk-countries');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $nom = trim($_POST['nom_pays'] ?? '');
        $code = strtoupper(trim($_POST['code_pays'] ?? ''));
        $coeff = (float)($_POST['coefficient_risque'] ?? 1.00);

        if ($id > 0) {
            getDB()->prepare('UPDATE risk_countries SET nom_pays=?, code_pays=?, coefficient_risque=? WHERE id=?')
                   ->execute([$nom, $code, $coeff, $id]);
            AuditLog::log("Modification pays: $nom ($code) → $coeff", 'risk_countries', $id);
        } else {
            getDB()->prepare('INSERT INTO risk_countries (nom_pays, code_pays, coefficient_risque) VALUES (?,?,?)')
                   ->execute([$nom, $code, $coeff]);
            AuditLog::log("Ajout pays: $nom ($code) → $coeff", 'risk_countries');
        }

        Session::setFlash('success', 'Pays enregistré.');
        header('Location: ' . BASE_URL . 'admin/risk-countries');
        exit;
    }

    public function deleteRiskCountry(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'admin/risk-countries');
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        getDB()->prepare('DELETE FROM risk_countries WHERE id = ?')->execute([$id]);
        AuditLog::log("Suppression pays #$id", 'risk_countries', $id);
        Session::setFlash('success', 'Pays supprimé.');
        header('Location: ' . BASE_URL . 'admin/risk-countries');
        exit;
    }

    public function riskAssets(): void {
        $assets = getDB()->query('SELECT * FROM risk_asset_types ORDER BY coefficient_risque DESC')->fetchAll();

        $pageTitle = 'Types d\'actifs';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/admin/risk_assets.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function saveRiskAsset(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'admin/risk-assets');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $nom = trim($_POST['nom_type'] ?? '');
        $coeff = (float)($_POST['coefficient_risque'] ?? 1.00);

        if ($id > 0) {
            getDB()->prepare('UPDATE risk_asset_types SET nom_type=?, coefficient_risque=? WHERE id=?')->execute([$nom, $coeff, $id]);
        } else {
            getDB()->prepare('INSERT INTO risk_asset_types (nom_type, coefficient_risque) VALUES (?,?)')->execute([$nom, $coeff]);
        }

        AuditLog::log("Enregistrement type actif: $nom → $coeff", 'risk_asset_types', $id ?: null);
        Session::setFlash('success', 'Type d\'actif enregistré.');
        header('Location: ' . BASE_URL . 'admin/risk-assets');
        exit;
    }

    public function deleteRiskAsset(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'admin/risk-assets');
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        getDB()->prepare('DELETE FROM risk_asset_types WHERE id = ?')->execute([$id]);
        AuditLog::log("Suppression type actif #$id", 'risk_asset_types', $id);
        Session::setFlash('success', 'Type d\'actif supprimé.');
        header('Location: ' . BASE_URL . 'admin/risk-assets');
        exit;
    }

    public function settings(): void {
        $settings = [];
        $rows = getDB()->query('SELECT * FROM settings')->fetchAll();
        foreach ($rows as $r) { $settings[$r['cle']] = $r; }

        $pageTitle = 'Paramètres';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/admin/settings.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function saveSettings(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'admin/settings');
            exit;
        }

        $settingsData = $_POST['settings'] ?? [];
        foreach ($settingsData as $key => $value) {
            getDB()->prepare('UPDATE settings SET valeur = ? WHERE cle = ?')->execute([trim($value), $key]);
        }

        AuditLog::log('Modification des paramètres globaux', 'settings');
        Session::setFlash('success', 'Paramètres enregistrés.');
        header('Location: ' . BASE_URL . 'admin/settings');
        exit;
    }

    public function auditLog(): void {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $total = (int)getDB()->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
        $logs = getDB()->prepare(
            'SELECT al.*, CONCAT(u.prenom, " ", u.nom) as user_name
             FROM audit_log al LEFT JOIN users u ON al.utilisateur_id = u.id
             ORDER BY al.date DESC LIMIT ? OFFSET ?'
        );
        $logs->execute([$limit, $offset]);
        $logs = $logs->fetchAll();
        $totalPages = ceil($total / $limit);

        $pageTitle = 'Journal d\'audit';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/admin/audit.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function allCases(): void {
        $filters = [
            'statut' => $_GET['statut'] ?? '',
            'agent_id' => $_GET['agent_id'] ?? '',
            'search' => $_GET['search'] ?? '',
        ];
        $cases = CaseModel::getAll($filters);
        $agents = User::getAgents();

        $pageTitle = 'Tous les dossiers';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/admin/cases.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function caseDetail(): void {
        $id = (int)($_GET['id'] ?? 0);
        $case = CaseModel::findById($id);

        if (!$case) {
            Session::setFlash('error', 'Dossier introuvable.');
            header('Location: ' . BASE_URL . 'admin/cases');
            exit;
        }

        // Admin uses agent's case detail view
        $documents = Document::getByCaseId($id);
        $checklist = ChecklistItem::getByCaseId($id);
        $messages = Message::getByCaseId($id);
        $history = CaseModel::getStatusHistory($id);
        $auditLogs = [];
        $stmt = getDB()->prepare(
            'SELECT al.*, CONCAT(u.prenom, " ", u.nom) as user_name
             FROM audit_log al LEFT JOIN users u ON al.utilisateur_id = u.id
             WHERE al.enregistrement_id = ? ORDER BY al.date DESC LIMIT 50'
        );
        $stmt->execute([$id]);
        $auditLogs = $stmt->fetchAll();

        $pageTitle = 'Dossier ' . $case['case_id_unique'];
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/agent/case_detail.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }
}
