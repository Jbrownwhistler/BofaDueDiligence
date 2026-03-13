<?php
class ClientController {

    /**
     * Calculate a compliance score (0-100) based on case statuses
     */
    private function calculateComplianceScore(array $cases): int {
        if (empty($cases)) return 100;
        $totalWeight = 0;
        $completedWeight = 0;
        foreach ($cases as $c) {
            $weight = 1;
            $totalWeight += $weight;
            if (in_array($c['statut'], ['valide', 'pret_pour_transfert'])) {
                $completedWeight += $weight;
            } elseif ($c['statut'] === 'en_analyse') {
                $completedWeight += 0.5 * $weight;
            } elseif ($c['statut'] === 'documents_demandes') {
                $completedWeight += 0.3 * $weight;
            } elseif ($c['statut'] === 'en_attente_validation') {
                $completedWeight += 0.7 * $weight;
            }
            // rejete and gele contribute 0
        }
        return $totalWeight > 0 ? (int)round(($completedWeight / $totalWeight) * 100) : 100;
    }

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

        $complianceScore = $this->calculateComplianceScore($cases);
        $unreadMessages = Message::countUnread($userId);
        $alertCount = 0;
        $stmtAlerts = getDB()->prepare('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND lu = 0');
        $stmtAlerts->execute([$userId]);
        $alertCount = (int)$stmtAlerts->fetch()['cnt'];

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

    // ========================================================================
    // 20 Online Banking Features
    // ========================================================================

    /** 1. Account Summary */
    public function accountSummary(): void {
        $userId = Auth::id();
        $account = Account::getByUser($userId);
        $cases = CaseModel::getByClient($userId);
        $transfers = CaseModel::getTransferred($userId);

        $totalIncoming = 0;
        $totalPending = 0;
        foreach ($cases as $c) {
            if ($c['statut_fonds'] === 'transfere') {
                $totalIncoming += $c['montant'];
            } else {
                $totalPending += $c['montant'];
            }
        }

        $subAccounts = [];
        if ($account) {
            $stmt = getDB()->prepare('SELECT * FROM sub_accounts WHERE account_id = ? ORDER BY date_creation DESC');
            $stmt->execute([$account['id']]);
            $subAccounts = $stmt->fetchAll();
        }

        $pageTitle = 'Synthèse du compte';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/account_summary.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 4. Compliance Center */
    public function complianceCenter(): void {
        $userId = Auth::id();
        $cases = CaseModel::getByClient($userId);
        $complianceScore = $this->calculateComplianceScore($cases);

        $statusBreakdown = [];
        foreach ($cases as $c) {
            $statusBreakdown[$c['statut']] = ($statusBreakdown[$c['statut']] ?? 0) + 1;
        }

        $user = User::findById($userId);

        $pageTitle = 'Centre de conformité';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/compliance_center.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 5. KYC Verification */
    public function kycVerification(): void {
        $userId = Auth::id();
        $user = User::findById($userId);
        $cases = CaseModel::getByClient($userId);

        // Gather all documents from all cases
        $allDocuments = [];
        foreach ($cases as $c) {
            $docs = Document::getByCaseId($c['id']);
            foreach ($docs as $d) {
                $d['case_id_unique'] = $c['case_id_unique'];
                $allDocuments[] = $d;
            }
        }

        $pageTitle = 'Vérification KYC';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/kyc_verification.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 6. Risk Profile */
    public function riskProfile(): void {
        $userId = Auth::id();
        $cases = CaseModel::getByClient($userId);

        $avgRisk = 0;
        $maxRisk = 0;
        $riskByCountry = [];
        if (!empty($cases)) {
            $totalRisk = 0;
            foreach ($cases as $c) {
                $totalRisk += $c['score_risque'];
                if ($c['score_risque'] > $maxRisk) $maxRisk = $c['score_risque'];
                $country = $c['pays_origine'];
                if (!isset($riskByCountry[$country])) {
                    $riskByCountry[$country] = ['count' => 0, 'total_risk' => 0];
                }
                $riskByCountry[$country]['count']++;
                $riskByCountry[$country]['total_risk'] += $c['score_risque'];
            }
            $avgRisk = $totalRisk / count($cases);
        }

        $pageTitle = 'Profil de risque';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/risk_profile.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 7. Document Vault */
    public function documentVault(): void {
        $userId = Auth::id();
        $cases = CaseModel::getByClient($userId);

        $allDocuments = [];
        foreach ($cases as $c) {
            $docs = Document::getByCaseId($c['id']);
            foreach ($docs as $d) {
                $d['case_id_unique'] = $c['case_id_unique'];
                $d['case_db_id'] = $c['id'];
                $allDocuments[] = $d;
            }
        }

        $pageTitle = 'Coffre-fort documents';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/document_vault.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 8. Secure Messages Hub */
    public function secureMessages(): void {
        $userId = Auth::id();
        $cases = CaseModel::getByClient($userId);

        $caseMessages = [];
        foreach ($cases as $c) {
            $msgs = Message::getByCaseId($c['id']);
            if (!empty($msgs)) {
                $unread = 0;
                foreach ($msgs as $m) {
                    if ($m['destinataire_id'] == $userId && !$m['lu']) $unread++;
                }
                $caseMessages[] = [
                    'case' => $c,
                    'messages' => $msgs,
                    'unread' => $unread,
                    'last_message' => end($msgs),
                ];
            }
        }

        $pageTitle = 'Messagerie sécurisée';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/secure_messages.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 9. Beneficiaries */
    public function beneficiaries(): void {
        $userId = Auth::id();
        $cases = CaseModel::getByClient($userId);

        // Extract unique beneficiaries from cases
        $beneficiaries = [];
        foreach ($cases as $c) {
            $key = $c['beneficiaire_nom'] . '|' . ($c['beneficiaire_banque'] ?? '');
            if (!isset($beneficiaries[$key])) {
                $beneficiaries[$key] = [
                    'nom' => $c['beneficiaire_nom'],
                    'banque' => $c['beneficiaire_banque'] ?? 'N/A',
                    'pays' => $c['pays_destination'],
                    'transactions' => 0,
                    'total_montant' => 0,
                ];
            }
            $beneficiaries[$key]['transactions']++;
            $beneficiaries[$key]['total_montant'] += $c['montant'];
        }

        // Also extract senders (emetteurs)
        $emetteurs = [];
        foreach ($cases as $c) {
            $key = $c['emetteur_nom'] . '|' . ($c['emetteur_banque'] ?? '');
            if (!isset($emetteurs[$key])) {
                $emetteurs[$key] = [
                    'nom' => $c['emetteur_nom'],
                    'banque' => $c['emetteur_banque'] ?? 'N/A',
                    'pays' => $c['pays_origine'],
                    'transactions' => 0,
                    'total_montant' => 0,
                ];
            }
            $emetteurs[$key]['transactions']++;
            $emetteurs[$key]['total_montant'] += $c['montant'];
        }

        $pageTitle = 'Gestion des bénéficiaires';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/beneficiaries.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 10. Account Statements */
    public function statements(): void {
        $userId = Auth::id();
        $account = Account::getByUser($userId);
        $transfers = CaseModel::getTransferred($userId);
        $cases = CaseModel::getByClient($userId);

        $pageTitle = 'Relevés de compte';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/statements.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 11. Regulatory Alerts */
    public function regulatoryAlerts(): void {
        $userId = Auth::id();
        $notifications = getDB()->prepare(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY date DESC LIMIT 50'
        );
        $notifications->execute([$userId]);
        $notifications = $notifications->fetchAll();

        $pageTitle = 'Alertes réglementaires';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/regulatory_alerts.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 12. Transaction Monitoring */
    public function transactionMonitoring(): void {
        $userId = Auth::id();
        $cases = CaseModel::getByClient($userId);

        $pageTitle = 'Suivi des transactions';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/transaction_monitoring.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 13. Activity Log */
    public function activityLog(): void {
        $userId = Auth::id();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $stmtTotal = getDB()->prepare('SELECT COUNT(*) FROM audit_log WHERE utilisateur_id = ?');
        $stmtTotal->execute([$userId]);
        $total = (int)$stmtTotal->fetchColumn();

        $stmt = getDB()->prepare(
            'SELECT * FROM audit_log WHERE utilisateur_id = ? ORDER BY date DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $limit, $offset]);
        $logs = $stmt->fetchAll();
        $totalPages = ceil($total / $limit);

        $pageTitle = 'Journal d\'activité';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/activity_log.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 14. Tax Documents */
    public function taxDocuments(): void {
        $userId = Auth::id();
        $account = Account::getByUser($userId);
        $transfers = CaseModel::getTransferred($userId);

        $totalTransferred = 0;
        foreach ($transfers as $t) {
            $totalTransferred += $t['montant'];
        }

        $pageTitle = 'Documents fiscaux';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/tax_documents.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 15. Declaration Center */
    public function declarations(): void {
        $userId = Auth::id();
        $cases = CaseModel::getByClient($userId);
        $user = User::findById($userId);

        $pageTitle = 'Centre de déclarations';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/declarations.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 16. Reports */
    public function reports(): void {
        $userId = Auth::id();
        $account = Account::getByUser($userId);
        $cases = CaseModel::getByClient($userId);
        $transfers = CaseModel::getTransferred($userId);

        $pageTitle = 'Rapports';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/reports.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 17. Compliance Training */
    public function complianceTraining(): void {
        $userId = Auth::id();

        $pageTitle = 'Formation conformité';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/compliance_training.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 18. Security Settings */
    public function securitySettings(): void {
        $userId = Auth::id();
        $user = User::findById($userId);

        $stmt = getDB()->prepare(
            'SELECT * FROM audit_log WHERE utilisateur_id = ? AND action LIKE "%connexion%" ORDER BY date DESC LIMIT 10'
        );
        $stmt->execute([$userId]);
        $loginHistory = $stmt->fetchAll();

        $pageTitle = 'Sécurité du compte';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/security_settings.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    /** 20. Help & Support */
    public function helpSupport(): void {
        $userId = Auth::id();

        $pageTitle = 'Aide & Support';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/client/help_support.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }
}
