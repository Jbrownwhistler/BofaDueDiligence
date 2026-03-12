<?php
/**
 * Composant pagination réutilisable — BofaDueDiligence
 * Variables attendues :
 *   $pagination (array)  — tableau retourné par bofa_paginate()
 *   $baseUrl    (string) — URL de base sans paramètre 'page' (ex. '/bofa/admin/users.php?role=admin')
 *
 * Le paramètre 'page' est ajouté automatiquement à $baseUrl.
 */
defined('BOFA_APP') || die('Accès direct interdit.');

/* Vérification des données requises */
if (empty($pagination) || !is_array($pagination)) return;
if (($pagination['totalPages'] ?? 1) <= 1) return;

$baseUrl     = isset($baseUrl)     ? rtrim($baseUrl, '&') : '';
$currentPage = (int) ($pagination['currentPage'] ?? 1);
$totalPages  = (int) ($pagination['totalPages']  ?? 1);
$total       = (int) ($pagination['total']       ?? 0);
$perPage     = (int) ($pagination['perPage']     ?? 20);

/* Séparateur d'URL */
$sep = str_contains($baseUrl, '?') ? '&' : '?';

/**
 * Construit l'URL d'une page donnée.
 */
$pageUrl = fn(int $p): string => htmlspecialchars(
    $baseUrl . $sep . 'page=' . $p,
    ENT_QUOTES,
    'UTF-8'
);

/* Fenêtre de pagination : max 5 pages autour de la page courante */
$fenetre = 2;
$debut   = max(1, $currentPage - $fenetre);
$fin     = min($totalPages, $currentPage + $fenetre);
?>
<nav aria-label="Pagination" class="mt-3">
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2">

        <!-- Résumé des résultats -->
        <p class="text-secondary small mb-0">
            Affichage de
            <strong><?= number_format(($currentPage - 1) * $perPage + 1) ?></strong>
            à
            <strong><?= number_format(min($currentPage * $perPage, $total)) ?></strong>
            sur <strong><?= number_format($total) ?></strong> résultats
        </p>

        <!-- Contrôles de pagination -->
        <ul class="pagination pagination-sm mb-0">

            <!-- Première page -->
            <li class="page-item<?= $currentPage === 1 ? ' disabled' : '' ?>">
                <a class="page-link" href="<?= $pageUrl(1) ?>" aria-label="Première page">
                    <i class="fa-solid fa-angles-left"></i>
                </a>
            </li>

            <!-- Page précédente -->
            <li class="page-item<?= !$pagination['hasPrev'] ? ' disabled' : '' ?>">
                <a class="page-link"
                   href="<?= $pageUrl($pagination['prevPage']) ?>"
                   aria-label="Page précédente">
                    <i class="fa-solid fa-angle-left"></i>
                </a>
            </li>

            <!-- Ellipse début si nécessaire -->
            <?php if ($debut > 1): ?>
            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
            <?php endif; ?>

            <!-- Pages de la fenêtre -->
            <?php for ($p = $debut; $p <= $fin; $p++): ?>
            <li class="page-item<?= $p === $currentPage ? ' active' : '' ?>"
                <?= $p === $currentPage ? 'aria-current="page"' : '' ?>>
                <a class="page-link" href="<?= $pageUrl($p) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>

            <!-- Ellipse fin si nécessaire -->
            <?php if ($fin < $totalPages): ?>
            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
            <?php endif; ?>

            <!-- Page suivante -->
            <li class="page-item<?= !$pagination['hasNext'] ? ' disabled' : '' ?>">
                <a class="page-link"
                   href="<?= $pageUrl($pagination['nextPage']) ?>"
                   aria-label="Page suivante">
                    <i class="fa-solid fa-angle-right"></i>
                </a>
            </li>

            <!-- Dernière page -->
            <li class="page-item<?= $currentPage === $totalPages ? ' disabled' : '' ?>">
                <a class="page-link" href="<?= $pageUrl($totalPages) ?>" aria-label="Dernière page">
                    <i class="fa-solid fa-angles-right"></i>
                </a>
            </li>

        </ul>
    </div>
</nav>
