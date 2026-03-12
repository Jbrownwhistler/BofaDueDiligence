<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-cog me-2 text-bofa-red"></i>Paramètres</h4>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-sliders-h me-2"></i>Configuration globale</div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>admin/settings/save">
                    <?= CSRF::field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Seuil de double validation (score de risque)</label>
                        <input type="number" name="settings[seuil_double_validation]" class="form-control" step="0.1" min="0"
                               value="<?= htmlspecialchars($settings['seuil_double_validation']['valeur'] ?? '7.5') ?>">
                        <small class="text-muted">Les dossiers avec un score ≥ ce seuil nécessitent une validation superviseur.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Délai d'escalade (jours)</label>
                        <input type="number" name="settings[delai_escalade_jours]" class="form-control" min="1"
                               value="<?= htmlspecialchars($settings['delai_escalade_jours']['valeur'] ?? '5') ?>">
                        <small class="text-muted">Nombre de jours sans action avant escalade automatique au superviseur.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Devise par défaut</label>
                        <select name="settings[devise_par_defaut]" class="form-select">
                            <?php $currentDevise = $settings['devise_par_defaut']['valeur'] ?? 'USD'; ?>
                            <option value="USD" <?= $currentDevise === 'USD' ? 'selected' : '' ?>>USD - Dollar américain</option>
                            <option value="EUR" <?= $currentDevise === 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                            <option value="GBP" <?= $currentDevise === 'GBP' ? 'selected' : '' ?>>GBP - Livre sterling</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-bofa"><i class="fas fa-save me-1"></i>Enregistrer les paramètres</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Informations</div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr><td class="text-muted">Version</td><td><?= APP_VERSION ?></td></tr>
                    <tr><td class="text-muted">PHP</td><td><?= PHP_VERSION ?></td></tr>
                    <tr><td class="text-muted">Base de données</td><td><?= DB_NAME ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
