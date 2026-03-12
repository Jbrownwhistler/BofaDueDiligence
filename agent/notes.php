<?php
/* Notes internes des agents — BofaDueDiligence */
define('BOFA_APP', true);
require_once dirname(__DIR__) . '/config.php';
session_start();
bofa_auth_check(['agent']);

require_once BOFA_ROOT . '/src/CaseAML.php';
require_once BOFA_ROOT . '/src/Notification.php';
require_once BOFA_ROOT . '/src/AuditLog.php';

$userId = $_SESSION['user_id'];
$caseAML = new CaseAML();
$notif = new Notification();
$audit = new AuditLog();
$errors = [];
$success = '';

/* Traitement ajout de note */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bofa_csrf_validate($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF invalide.';
    } else {
        $caseId = (int)($_POST['case_id'] ?? 0);
        $note = bofa_sanitize($_POST['note'] ?? '');
        if ($caseId > 0 && strlen($note) >= 3) {
            /* Vérification mention @agent dans la note */
            if (preg_match_all('/@(\w+)/', $note, $matches)) {
                foreach ($matches[1] as $prenom) {
                    $db = bofa_db();
                    $stmt = $db->prepare("SELECT id FROM users WHERE prenom = ? AND role IN ('agent','admin') LIMIT 1");
                    $stmt->execute([$prenom]);
                    $mentioned = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($mentioned) {
                        $notif->send((int)$mentioned['id'], "Vous avez été mentionné dans une note du dossier #{$caseId}", 'alerte', $caseId);
                    }
                }
            }
            $caseAML->addInternalNote($caseId, $userId, $note);
            $audit->log($userId, 'ajout_note', 'cases', $caseId, null, ['note' => substr($note, 0, 100)], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
            bofa_flash('Note ajoutée avec succès.', 'success');
            bofa_redirect('http://localhost:8888/bofa/agent/notes.php');
        } else {
            $errors[] = 'Note invalide ou dossier non spécifié.';
        }
    }
}

/* Récupération des dossiers assignés */
$dossiers = $caseAML->getByAgent($userId, [], 1);
$cases = $dossiers['items'] ?? [];

/* Récupération des notes récentes via audit_log */
$db = bofa_db();
$stmt = $db->prepare("SELECT al.*, u.prenom, u.nom FROM audit_log al
    JOIN users u ON u.id = al.utilisateur_id
    WHERE al.action = 'ajout_note' AND al.utilisateur_id = ?
    ORDER BY al.created_at DESC LIMIT 30");
$stmt->execute([$userId]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Notes internes';
$unreadCount = $notif->getUnreadCount($userId);
require_once BOFA_ROOT . '/templates/header.php';
require_once BOFA_ROOT . '/templates/sidebar-agent.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h2 class="mb-4"><i class="fas fa-sticky-note me-2" style="color:var(--bofa-rouge)"></i>Notes internes</h2>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="row">
      <div class="col-md-4">
        <div class="card shadow-sm">
          <div class="card-header" style="background:var(--bofa-bleu);color:#fff">
            <i class="fas fa-plus me-1"></i> Ajouter une note
          </div>
          <div class="card-body">
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= bofa_csrf_token() ?>">
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
                <label class="form-label">Note <small class="text-muted">(utilisez @prenom pour mentionner)</small></label>
                <textarea name="note" class="form-control" rows="4" required minlength="3" placeholder="Note interne invisible du client..."></textarea>
              </div>
              <button type="submit" class="btn btn-primary w-100" style="background:var(--bofa-bleu);border-color:var(--bofa-bleu)">
                <i class="fas fa-save me-1"></i> Enregistrer
              </button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-8">
        <div class="card shadow-sm">
          <div class="card-header" style="background:var(--bofa-bleu);color:#fff">
            <i class="fas fa-list me-1"></i> Notes récentes
          </div>
          <div class="card-body p-0">
            <table class="table table-hover mb-0">
              <thead class="table-light"><tr><th>Dossier #</th><th>Note</th><th>Date</th></tr></thead>
              <tbody>
                <?php foreach ($notes as $n): 
                  $val = json_decode($n['nouvelle_valeur'] ?? '{}', true);
                  $noteText = $val['note'] ?? '—';
                ?>
                <tr>
                  <td><a href="dossier-detail.php?id=<?= (int)$n['enregistrement_id'] ?>">#<?= (int)$n['enregistrement_id'] ?></a></td>
                  <td><?= htmlspecialchars(substr($noteText, 0, 120)) ?></td>
                  <td><?= htmlspecialchars($n['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($notes)): ?>
                  <tr><td colspan="3" class="text-center text-muted py-3">Aucune note.</td></tr>
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
