<?php
/* Rappels programmables — F50 — BofaDueDiligence */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';
session_start();
bofa_auth_check(['agent']);

require_once BOFA_ROOT . '/src/CaseAML.php';
require_once BOFA_ROOT . '/src/Notification.php';

$userId = $_SESSION['user_id'];
$notif = new Notification();
$caseAML = new CaseAML();
$errors = [];

/* Traitement création de rappel */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bofa_csrf_validate($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $caseId = (int)($_POST['case_id'] ?? 0);
            $message = bofa_sanitize($_POST['message'] ?? '');
            $dateRappel = bofa_sanitize($_POST['date_rappel'] ?? '');
            if ($caseId > 0 && strlen($message) >= 3 && $dateRappel) {
                /* Insertion rappel dans la table notifications */
                $db = bofa_db();
                $stmt = $db->prepare("INSERT INTO notifications (user_id, message, type, case_id, lu, created_at)
                    VALUES (?, ?, 'rappel', ?, 0, ?)");
                $stmt->execute([$userId, 'RAPPEL|' . $dateRappel . '|' . $message, $caseId, date('Y-m-d H:i:s')]);
                bofa_flash('Rappel programmé pour le ' . htmlspecialchars($dateRappel) . '.', 'success');
            } else {
                $errors[] = 'Données invalides.';
            }
        } elseif ($action === 'done') {
            $id = (int)($_POST['notif_id'] ?? 0);
            $notif->markRead($id);
            bofa_flash('Rappel marqué comme traité.', 'success');
        }
        if (empty($errors)) bofa_redirect('http://localhost:8888/bofa/agent/rappels.php');
    }
}

/* Récupération des rappels de l'agent */
$db = bofa_db();
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND type = 'rappel' ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$userId]);
$rappels = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Dossiers pour le sélecteur */
$dossiers = $caseAML->getByAgent($userId, [], 1);
$cases = $dossiers['items'] ?? [];

$pageTitle = 'Rappels';
$unreadCount = $notif->getUnreadCount($userId);
require_once BOFA_ROOT . '/templates/header.php';
require_once BOFA_ROOT . '/templates/sidebar-agent.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h2 class="mb-4"><i class="fas fa-bell me-2" style="color:var(--bofa-rouge)"></i>Rappels programmables</h2>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="row">
      <div class="col-md-4">
        <div class="card shadow-sm">
          <div class="card-header" style="background:var(--bofa-bleu);color:#fff">
            <i class="fas fa-plus me-1"></i> Nouveau rappel
          </div>
          <div class="card-body">
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
              <input type="hidden" name="action" value="create">
              <div class="mb-3">
                <label class="form-label">Dossier</label>
                <select name="case_id" class="form-select" required>
                  <option value="">— Sélectionner —</option>
                  <?php foreach ($cases as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['case_id_unique']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Date du rappel</label>
                <input type="datetime-local" name="date_rappel" class="form-control" required min="<?= date('Y-m-d\TH:i') ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Message du rappel</label>
                <textarea name="message" class="form-control" rows="3" required minlength="3" placeholder="Ex: Vérifier si le client a uploadé ses documents..."></textarea>
              </div>
              <button type="submit" class="btn btn-primary w-100" style="background:var(--bofa-bleu);border-color:var(--bofa-bleu)">
                <i class="fas fa-clock me-1"></i> Programmer
              </button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-8">
        <div class="card shadow-sm">
          <div class="card-header" style="background:var(--bofa-bleu);color:#fff">
            <i class="fas fa-list me-1"></i> Rappels programmés
          </div>
          <div class="card-body p-0">
            <table class="table table-hover mb-0">
              <thead class="table-light"><tr><th>Dossier</th><th>Date rappel</th><th>Message</th><th>Statut</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($rappels as $r):
                  $parts = explode('|', $r['message'], 3);
                  $dateRappel = $parts[1] ?? '—';
                  $msg = $parts[2] ?? $r['message'];
                  $isPast = strtotime($dateRappel) < time();
                  $isDone = (bool)$r['lu'];
                ?>
                <tr class="<?= $isPast && !$isDone ? 'table-warning' : '' ?>">
                  <td><a href="dossier-detail.php?id=<?= (int)$r['case_id'] ?>">#<?= (int)$r['case_id'] ?></a></td>
                  <td><?= htmlspecialchars($dateRappel) ?></td>
                  <td><?= htmlspecialchars(substr($msg, 0, 80)) ?></td>
                  <td><?= $isDone ? '<span class="badge bg-success">Traité</span>' : ($isPast ? '<span class="badge bg-warning text-dark">En retard</span>' : '<span class="badge bg-info">Programmé</span>') ?></td>
                  <td>
                    <?php if (!$isDone): ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
                      <input type="hidden" name="action" value="done">
                      <input type="hidden" name="notif_id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-success" title="Marquer traité"><i class="fas fa-check"></i></button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($rappels)): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">Aucun rappel programmé.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once BOFA_ROOT . '/templates/footer.php'; ?>
