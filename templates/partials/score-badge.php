<?php
/**
 * Composant badge de score de risque — BofaDueDiligence
 * Variables attendues :
 *   $score      (float)  — Score de risque entre 0 et 100
 *   $showLabel  (bool)   — Afficher le libellé textuel (défaut : true)
 *   $showIcon   (bool)   — Afficher l'icône (défaut : true)
 *
 * Seuils :
 *   0  – 40  : Faible   (vert)
 *   41 – 74  : Moyen    (orange)
 *   75 – 100 : Élevé    (rouge)
 */
defined('BOFA_APP') || die('Accès direct interdit.');

/* Normalisation */
$score     = isset($score)     ? round(max(0.0, min(100.0, (float) $score)), 1) : 0.0;
$showLabel = isset($showLabel) ? (bool) $showLabel : true;
$showIcon  = isset($showIcon)  ? (bool) $showIcon  : true;

/* Détermination du niveau */
if ($score <= 40) {
    $classe  = 'badge-score-faible';
    $libelle = 'Faible';
    $icone   = 'fa-shield-halved';
} elseif ($score <= 74) {
    $classe  = 'badge-score-moyen';
    $libelle = 'Moyen';
    $icone   = 'fa-triangle-exclamation';
} else {
    $classe  = 'badge-score-eleve';
    $libelle = 'Élevé';
    $icone   = 'fa-circle-exclamation';
}
?>
<span class="badge-score <?= $classe ?>"
      title="Score de risque : <?= $score ?>/100"
      aria-label="Score de risque <?= $libelle ?> (<?= $score ?>/100)">
    <?php if ($showIcon): ?>
    <i class="fa-solid <?= $icone ?>"></i>
    <?php endif; ?>
    <?php if ($showLabel): ?>
    <span><?= $libelle ?></span>
    <?php endif; ?>
    <strong><?= number_format($score, 1) ?></strong>
</span>
