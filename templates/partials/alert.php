<?php
/**
 * Composant alerte réutilisable — BofaDueDiligence
 * Variables attendues :
 *   $alertType    (string) — 'success' | 'danger' | 'warning' | 'info'
 *   $alertMessage (string) — Message à afficher (chaîne brute, sera assainie ici)
 *   $alertDismiss (bool)   — Afficher le bouton de fermeture (défaut : true)
 */
defined('BOFA_APP') || die('Accès direct interdit.');

/* Normalisation des variables */
$alertType    = isset($alertType)    ? $alertType    : 'info';
$alertMessage = isset($alertMessage) ? $alertMessage : '';
$alertDismiss = isset($alertDismiss) ? (bool) $alertDismiss : true;

/* Types Bootstrap valides */
$typesValides = ['success', 'danger', 'warning', 'info', 'primary', 'secondary'];
if (!in_array($alertType, $typesValides, true)) {
    $alertType = 'info';
}

/* Icône FontAwesome selon le type */
$icones = [
    'success'   => 'fa-circle-check',
    'danger'    => 'fa-triangle-exclamation',
    'warning'   => 'fa-triangle-exclamation',
    'info'      => 'fa-circle-info',
    'primary'   => 'fa-circle-info',
    'secondary' => 'fa-circle-info',
];
$icone = $icones[$alertType] ?? 'fa-circle-info';

if (empty($alertMessage)) return;
?>
<div class="alert alert-<?= htmlspecialchars($alertType, ENT_QUOTES, 'UTF-8') ?> d-flex align-items-start gap-2<?= $alertDismiss ? ' alert-dismissible' : '' ?> fade show"
     role="alert"
     data-auto-dismiss="5000">
    <i class="fa-solid <?= $icone ?> mt-1 flex-shrink-0"></i>
    <span><?= htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8') ?></span>
    <?php if ($alertDismiss): ?>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Fermer"></button>
    <?php endif; ?>
</div>
