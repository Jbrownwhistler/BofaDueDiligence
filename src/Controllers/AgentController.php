<?php
class AgentController {
    public function dashboard(): void {
        $agentId = Auth::isAdmin() ? null : Auth::id();
        $statusCounts = CaseModel::countByStatus($agentId);
        $totalAmount = CaseModel::totalAmount($agentId);
        $overdue = CaseModel::getOverdue($agentId);
        $totalCases = array_sum($statusCounts);

        $pageTitle = 'Tableau de bord Agent';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/agent/dashboard.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function caseList(): void {
        $agentId = Auth::isAdmin() ? null : Auth::id();
        $filters = [
            'statut' => $_GET['statut'] ?? '',
            'pays' => $_GET['pays'] ?? '',
        ];

        $cases = $agentId
            ? CaseModel::getByAgent($agentId, $filters)
            : CaseModel::getAll($filters);

        $pageTitle = 'Dossiers AML';
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/agent/case_list.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function caseDetail(): void {
        $id = (int)($_GET['id'] ?? 0);
        $case = CaseModel::findById($id);

        if (!$case) {
            Session::setFlash('error', 'Dossier introuvable.');
            header('Location: ' . BASE_URL . 'agent/cases');
            exit;
        }

        // Agent can only see their assigned cases (admin sees all)
        if (!Auth::isAdmin() && $case['agent_assigne_id'] != Auth::id()) {
            Session::setFlash('error', 'Accès refusé.');
            header('Location: ' . BASE_URL . 'agent/cases');
            exit;
        }

        $documents = Document::getByCaseId($id);
        $checklist = ChecklistItem::getByCaseId($id);
        $messages = Message::getByCaseId($id);
        $history = CaseModel::getStatusHistory($id);
        $auditLogs = self::getCaseAuditLogs($id);
        Message::markAsRead($id, Auth::id());

        $pageTitle = 'Dossier ' . $case['case_id_unique'];
        include __DIR__ . '/../../templates/layouts/header.php';
        include __DIR__ . '/../../templates/agent/case_detail.php';
        include __DIR__ . '/../../templates/layouts/footer.php';
    }

    public function updateStatus(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirectBack(); }

        $caseId = (int)($_POST['case_id'] ?? 0);
        $newStatus = $_POST['statut'] ?? '';
        $comment = trim($_POST['commentaire'] ?? '');

        $case = CaseModel::findById($caseId);
        if (!$case) { $this->redirectBack(); }

        CaseModel::updateStatus($caseId, $newStatus, $comment);

        // Notify client
        $clientMsg = 'Votre dossier ' . $case['case_id_unique'] . ' a changé de statut: ' . CaseModel::getStatusLabel($newStatus);
        Notify::send($case['client_user_id'], $clientMsg, 'info', '/client/case?id=' . $caseId);

        Session::setFlash('success', 'Statut mis à jour.');
        header('Location: ' . BASE_URL . 'agent/case?id=' . $caseId);
        exit;
    }

    public function freezeFunds(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirectBack(); }

        $caseId = (int)($_POST['case_id'] ?? 0);
        $case = CaseModel::findById($caseId);
        if (!$case) { $this->redirectBack(); }

        CaseModel::updateFundsStatus($caseId, 'gele');
        CaseModel::updateStatus($caseId, 'gele', 'Fonds gelés par l\'agent');

        Notify::send($case['client_user_id'], 'Les fonds de votre dossier ' . $case['case_id_unique'] . ' ont été gelés.', 'danger', '/client/case?id=' . $caseId);

        Session::setFlash('success', 'Fonds gelés.');
        header('Location: ' . BASE_URL . 'agent/case?id=' . $caseId);
        exit;
    }

    public function unfreezeFunds(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirectBack(); }

        $caseId = (int)($_POST['case_id'] ?? 0);
        $case = CaseModel::findById($caseId);
        if (!$case) { $this->redirectBack(); }

        // Unfreeze triggers dual validation if score is high
        if (RiskCalculator::requiresSupervisor($case['score_risque'])) {
            CaseModel::updateFundsStatus($caseId, 'bloque');
            CaseModel::updateStatus($caseId, 'en_attente_validation', 'Dégel soumis à validation superviseur');
            getDB()->prepare('UPDATE cases SET superviseur_requis = 1 WHERE id = ?')->execute([$caseId]);

            Session::setFlash('warning', 'Le dégel requiert une validation superviseur (score élevé).');
        } else {
            CaseModel::updateFundsStatus($caseId, 'disponible');
            CaseModel::updateStatus($caseId, 'pret_pour_transfert', 'Fonds dégelés et disponibles');

            Notify::send($case['client_user_id'], 'Vos fonds du dossier ' . $case['case_id_unique'] . ' sont maintenant disponibles.', 'success', '/client/case?id=' . $caseId);

            Session::setFlash('success', 'Fonds dégelés et disponibles pour le client.');
        }

        header('Location: ' . BASE_URL . 'agent/case?id=' . $caseId);
        exit;
    }

    public function validateCase(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirectBack(); }

        $caseId = (int)($_POST['case_id'] ?? 0);
        $case = CaseModel::findById($caseId);
        if (!$case) { $this->redirectBack(); }

        $submitToSupervisor = isset($_POST['superviseur']);
        $threshold = RiskCalculator::getThreshold();

        if ($submitToSupervisor || $case['score_risque'] >= $threshold) {
            CaseModel::updateStatus($caseId, 'en_attente_validation', 'Soumis à validation superviseur');
            getDB()->prepare('UPDATE cases SET superviseur_requis = 1 WHERE id = ?')->execute([$caseId]);

            // Notify admins
            $admins = getDB()->query("SELECT id FROM users WHERE role = 'admin' AND statut = 'actif'")->fetchAll();
            foreach ($admins as $admin) {
                Notify::send($admin['id'], 'Dossier ' . $case['case_id_unique'] . ' en attente de votre validation.', 'warning', '/admin/case?id=' . $caseId);
            }

            Session::setFlash('warning', 'Dossier soumis à validation superviseur.');
        } else {
            CaseModel::updateFundsStatus($caseId, 'disponible');
            CaseModel::updateStatus($caseId, 'pret_pour_transfert', 'Dossier validé - fonds disponibles');

            Notify::send($case['client_user_id'], 'Votre dossier ' . $case['case_id_unique'] . ' a été validé. Vos fonds sont prêts pour le transfert.', 'success', '/client/case?id=' . $caseId);

            Session::setFlash('success', 'Dossier validé. Fonds disponibles pour le client.');
        }

        header('Location: ' . BASE_URL . 'agent/case?id=' . $caseId);
        exit;
    }

    public function rejectCase(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirectBack(); }

        $caseId = (int)($_POST['case_id'] ?? 0);
        $motif = trim($_POST['motif'] ?? 'Dossier rejeté');
        $case = CaseModel::findById($caseId);
        if (!$case) { $this->redirectBack(); }

        CaseModel::updateStatus($caseId, 'rejete', $motif);

        Notify::send($case['client_user_id'], 'Votre dossier ' . $case['case_id_unique'] . ' a été rejeté. Motif: ' . $motif, 'danger', '/client/case?id=' . $caseId);

        Session::setFlash('success', 'Dossier rejeté.');
        header('Location: ' . BASE_URL . 'agent/case?id=' . $caseId);
        exit;
    }

    public function requestDocuments(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirectBack(); }

        $caseId = (int)($_POST['case_id'] ?? 0);
        $case = CaseModel::findById($caseId);
        if (!$case) { $this->redirectBack(); }

        CaseModel::updateStatus($caseId, 'documents_demandes', 'Documents supplémentaires requis');

        Notify::send($case['client_user_id'], 'Des documents supplémentaires sont requis pour votre dossier ' . $case['case_id_unique'], 'warning', '/client/case?id=' . $caseId);

        Session::setFlash('success', 'Demande de documents envoyée au client.');
        header('Location: ' . BASE_URL . 'agent/case?id=' . $caseId);
        exit;
    }

    public function validateDocument(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirectBack(); }

        $docId = (int)($_POST['doc_id'] ?? 0);
        $caseId = (int)($_POST['case_id'] ?? 0);

        Document::updateStatus($docId, 'valide');
        AuditLog::log('Document validé', 'documents', $docId);

        Session::setFlash('success', 'Document validé.');
        header('Location: ' . BASE_URL . 'agent/case?id=' . $caseId);
        exit;
    }

    public function rejectDocument(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirectBack(); }

        $docId = (int)($_POST['doc_id'] ?? 0);
        $caseId = (int)($_POST['case_id'] ?? 0);
        $motif = trim($_POST['motif'] ?? '');

        Document::updateStatus($docId, 'rejete', $motif);
        AuditLog::log('Document rejeté: ' . $motif, 'documents', $docId);

        $case = CaseModel::findById($caseId);
        if ($case) {
            Notify::send($case['client_user_id'], 'Un document de votre dossier ' . $case['case_id_unique'] . ' a été rejeté.', 'warning', '/client/case?id=' . $caseId);
        }

        Session::setFlash('success', 'Document rejeté.');
        header('Location: ' . BASE_URL . 'agent/case?id=' . $caseId);
        exit;
    }

    public function addChecklistItem(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirectBack(); }

        $caseId = (int)($_POST['case_id'] ?? 0);
        $libelle = trim($_POST['libelle'] ?? '');
        $type = $_POST['type_exigence'] ?? 'case';

        if (empty($libelle)) {
            Session::setFlash('error', 'Le libellé est requis.');
            header('Location: ' . BASE_URL . 'agent/case?id=' . $caseId);
            exit;
        }

        ChecklistItem::create($caseId, $libelle, $type);
        AuditLog::log('Checklist ajouté: ' . $libelle, 'checklist_items', $caseId);

        $case = CaseModel::findById($caseId);
        if ($case) {
            Notify::send($case['client_user_id'], 'Nouvelle formalité ajoutée à votre dossier ' . $case['case_id_unique'], 'info', '/client/case?id=' . $caseId);
        }

        Session::setFlash('success', 'Formalité ajoutée.');
        header('Location: ' . BASE_URL . 'agent/case?id=' . $caseId);
        exit;
    }

    public function sendMessage(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirectBack(); }

        $caseId = (int)($_POST['case_id'] ?? 0);
        $case = CaseModel::findById($caseId);
        if (!$case || empty($_POST['message'])) { $this->redirectBack(); }

        $msg = trim($_POST['message']);
        Message::create($caseId, Auth::id(), $case['client_user_id'], $msg);

        Notify::send($case['client_user_id'], 'Nouveau message de votre agent sur le dossier ' . $case['case_id_unique'], 'info', '/client/case?id=' . $caseId);

        Session::setFlash('success', 'Message envoyé.');
        header('Location: ' . BASE_URL . 'agent/case?id=' . $caseId);
        exit;
    }

    private function redirectBack(): void {
        header('Location: ' . BASE_URL . 'agent/cases');
        exit;
    }

    private static function getCaseAuditLogs(int $caseId): array {
        $stmt = getDB()->prepare(
            'SELECT al.*, CONCAT(u.prenom, " ", u.nom) as user_name
             FROM audit_log al
             LEFT JOIN users u ON al.utilisateur_id = u.id
             WHERE al.table_concernee IN ("cases","documents","checklist_items","messages")
             AND al.enregistrement_id = ?
             ORDER BY al.date DESC
             LIMIT 50'
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }
}
