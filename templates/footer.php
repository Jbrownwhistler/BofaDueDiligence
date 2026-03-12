<?php
/**
 * Pied de page commun — BofaDueDiligence
 * Ferme les balises ouvertes par templates/header.php,
 * affiche les messages flash et charge les scripts JS.
 */
defined('BOFA_APP') || die('Accès direct interdit.');
?>
        </main><!-- /.bofa-content -->

        <!-- Pied de page applicatif -->
        <footer class="border-top py-3 px-4 text-center text-secondary small"
                style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
            <span>
                &copy; <?= date('Y') ?>
                <strong class="text-bofa-bleu">BofaDueDiligence</strong>
                &mdash; Plateforme de conformité AML/EDD &mdash; v<?= BOFA_VERSION ?>
            </span>
        </footer>

    </div><!-- /#bofa-main -->
</div><!-- /.bofa-wrapper -->

<!-- Avertissement d'expiration de session -->
<div id="session-warning"
     class="alert alert-warning shadow-lg"
     role="alert"
     style="display:none;">
    <div class="d-flex align-items-start gap-2">
        <i class="fa-solid fa-clock-rotate-left mt-1 text-warning"></i>
        <div class="flex-grow-1">
            <strong>Session bientôt expirée</strong><br>
            <span class="small">Votre session expire dans 2 minutes. Sauvegardez votre travail.</span>
        </div>
        <button type="button" class="btn btn-sm btn-warning"
                onclick="resetSessionTimer(); document.getElementById('session-warning').style.display='none';">
            Rester connecté
        </button>
    </div>
</div>

<!-- Messages flash PHP affichés au chargement -->
<?php
$messagesFlash = bofa_get_flash();
if (!empty($messagesFlash)):
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php foreach ($messagesFlash as $flash):
        $type    = $flash['type'] === 'error' ? 'danger' : htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
        $message = addslashes(htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'));
    ?>
    showAlert('<?= $message ?>', '<?= $type ?>');
    <?php endforeach; ?>
});
</script>
<?php endif; ?>

<!-- Bootstrap 5.3 JS Bundle (Popper inclus) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmzFVBJZv09+ooHRHCg1Zr5G8GNI"
        crossorigin="anonymous"></script>

<!-- Scripts BofA -->
<script src="<?= BOFA_URL ?>/assets/js/bofa.js"></script>

</body>
</html>
