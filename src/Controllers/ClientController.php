<?php
class ClientController {
    public function dashboard(): void {
        $userId = Auth::id();
        $account = Account::getByUser($userId);
        $cases = CaseModel::getByClient($userId);

        $pendingCount = 0;
        $pendingAmount = 0;
        $readyCount = 0;
        foreach ($cases as $c) {
            if ($c['statut'] !== 'pret_pour_transfert' && $c['statut_fonds'] !== 'transfere') {
                $pendingCount++;
                $pendingAmount += $c['montant'];
            }
            if ($c['statut'] === 'pret_pour_transfert') {
                $readyCount++;
            }
        }

        $pageTitle = 'Tableau de bord';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/dashboard.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function pendingFunds(): void {
        $cases = CaseModel::getByClient(Auth::id());
        $pending = array_filter($cases, fn($c) => $c['statut_fonds'] !== 'transfere');

        $pageTitle = 'Fonds en attente';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/pending.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function viewCase(): void {
        $id = (int)($_GET['id'] ?? 0);
        $case = CaseModel::findById($id);

        if (!$case || $case['client_user_id'] != Auth::id()) {
            Session::setFlash('error', 'Dossier introuvable.');
            header('Location: ' . BASE_URL . 'client/pending');
            exit;
        }

        $documents = Document::getByCaseId($id);
        $checklist = ChecklistItem::getByCaseId($id);
        $messages = Message::getByCaseId($id);
        Message::markAsRead($id, Auth::id());

        $pageTitle = 'Dossier ' . $case['case_id_unique'];
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/case_detail.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function transfer(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'client/pending');
            exit;
        }

        $caseId = (int)($_POST['case_id'] ?? 0);
        $case = CaseModel::findById($caseId);

        if (!$case || $case['client_user_id'] != Auth::id()) {
            Session::setFlash('error', 'Dossier introuvable.');
            header('Location: ' . BASE_URL . 'client/pending');
            exit;
        }

        if ($case['statut'] !== 'pret_pour_transfert' || $case['statut_fonds'] !== 'disponible') {
            Session::setFlash('error', 'Ce dossier n\'est pas prêt pour le transfert.');
            header('Location: ' . BASE_URL . 'client/case?id=' . $caseId);
            exit;
        }

        // Transfer funds
        $account = Account::getByUser(Auth::id());
        Account::addToBalance($account['id'], $case['montant']);
        CaseModel::updateFundsStatus($caseId, 'transfere');
        CaseModel::updateStatus($caseId, 'valide', 'Fonds transférés vers le compte principal');

        AuditLog::log('Transfert de fonds: ' . number_format($case['montant'], 2) . ' ' . $case['devise'], 'cases', $caseId);
        Notify::send(Auth::id(), 'Transfert de ' . number_format($case['montant'], 2) . ' $ effectué avec succès.', 'success', '/client/history');

        if ($case['agent_assigne_id']) {
            Notify::send($case['agent_assigne_id'], 'Le client a transféré les fonds du dossier ' . $case['case_id_unique'], 'info', '/agent/case?id=' . $caseId);
        }

        Session::setFlash('success', 'Transfert de ' . number_format($case['montant'], 2) . ' $ effectué avec succès !');
        header('Location: ' . BASE_URL . 'client/dashboard');
        exit;
    }

    public function uploadDocument(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'client/pending');
            exit;
        }

        $caseId = (int)($_POST['case_id'] ?? 0);
        $case = CaseModel::findById($caseId);

        if (!$case || $case['client_user_id'] != Auth::id()) {
            Session::setFlash('error', 'Dossier introuvable.');
            header('Location: ' . BASE_URL . 'client/pending');
            exit;
        }

        if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            Session::setFlash('error', 'Erreur lors du téléversement.');
            header('Location: ' . BASE_URL . 'client/case?id=' . $caseId);
            exit;
        }

        $file = $_FILES['document'];
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            Session::setFlash('error', 'Type de fichier non autorisé.');
            header('Location: ' . BASE_URL . 'client/case?id=' . $caseId);
            exit;
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            Session::setFlash('error', 'Fichier trop volumineux (max 10 Mo).');
            header('Location: ' . BASE_URL . 'client/case?id=' . $caseId);
            exit;
        }

        $caseDir = UPLOAD_DIR . $caseId . '/';
        if (!is_dir($caseDir)) mkdir($caseDir, 0755, true);

        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $dest = $caseDir . $safeName;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $type = $_POST['type_document'] ?? 'Autre';
            $docId = Document::create($caseId, $file['name'], $dest, $type);

            // Auto-link to checklist item if applicable
            if (!empty($_POST['checklist_item_id'])) {
                ChecklistItem::linkDocument((int)$_POST['checklist_item_id'], $docId);
            }

            AuditLog::log('Document téléversé: ' . $file['name'], 'documents', $docId);

            if ($case['agent_assigne_id']) {
                Notify::send($case['agent_assigne_id'], 'Nouveau document téléversé pour le dossier ' . $case['case_id_unique'], 'info', '/agent/case?id=' . $caseId);
            }

            Session::setFlash('success', 'Document téléversé avec succès.');
        } else {
            Session::setFlash('error', 'Erreur lors de l\'enregistrement du fichier.');
        }

        header('Location: ' . BASE_URL . 'client/case?id=' . $caseId);
        exit;
    }

    public function updateChecklist(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'client/pending');
            exit;
        }

        $caseId = (int)($_POST['case_id'] ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);
        $case = CaseModel::findById($caseId);

        if (!$case || $case['client_user_id'] != Auth::id()) {
            Session::setFlash('error', 'Dossier introuvable.');
            header('Location: ' . BASE_URL . 'client/pending');
            exit;
        }

        ChecklistItem::toggle($itemId);
        AuditLog::log('Checklist mis à jour', 'checklist_items', $itemId);

        Session::setFlash('success', 'Checklist mise à jour.');
        header('Location: ' . BASE_URL . 'client/case?id=' . $caseId);
        exit;
    }

    public function messages(): void {
        $caseId = (int)($_GET['case_id'] ?? 0);
        $case = CaseModel::findById($caseId);

        if (!$case || $case['client_user_id'] != Auth::id()) {
            Session::setFlash('error', 'Dossier introuvable.');
            header('Location: ' . BASE_URL . 'client/pending');
            exit;
        }

        $messages = Message::getByCaseId($caseId);
        Message::markAsRead($caseId, Auth::id());

        $pageTitle = 'Messages - ' . $case['case_id_unique'];
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/messages.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function sendMessage(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'client/pending');
            exit;
        }

        $caseId = (int)($_POST['case_id'] ?? 0);
        $case = CaseModel::findById($caseId);

        if (!$case || $case['client_user_id'] != Auth::id() || empty($_POST['message'])) {
            Session::setFlash('error', 'Erreur.');
            header('Location: ' . BASE_URL . 'client/pending');
            exit;
        }

        $msg = trim($_POST['message']);
        $recipientId = $case['agent_assigne_id'] ?? 1;
        Message::create($caseId, Auth::id(), $recipientId, $msg);

        Notify::send($recipientId, 'Nouveau message du client sur le dossier ' . $case['case_id_unique'], 'info', '/agent/case?id=' . $caseId);

        Session::setFlash('success', 'Message envoyé.');
        header('Location: ' . BASE_URL . 'client/case?id=' . $caseId);
        exit;
    }

    public function transferHistory(): void {
        $transfers = CaseModel::getTransferred(Auth::id());

        $pageTitle = 'Historique des transferts';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/history.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function profile(): void {
        $user = User::findById(Auth::id());
        $account = Account::getByUser(Auth::id());

        $pageTitle = 'Mon profil';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/profile.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function updateProfile(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'client/profile');
            exit;
        }

        $data = [
            'nom' => trim($_POST['nom'] ?? ''),
            'prenom' => trim($_POST['prenom'] ?? ''),
        ];

        if (empty($data['nom']) || empty($data['prenom'])) {
            Session::setFlash('error', 'Veuillez remplir tous les champs.');
            header('Location: ' . BASE_URL . 'client/profile');
            exit;
        }

        User::update(Auth::id(), $data);
        Session::set('user_name', $data['prenom'] . ' ' . $data['nom']);
        AuditLog::log('Profil mis à jour', 'users', Auth::id());

        Session::setFlash('success', 'Profil mis à jour avec succès.');
        header('Location: ' . BASE_URL . 'client/profile');
        exit;
    }
}
