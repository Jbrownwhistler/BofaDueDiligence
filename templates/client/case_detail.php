<meta name="base-url" content="<?= BASE_URL ?>">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-folder-open me-2 text-bofa-red"></i>Dossier <?= htmlspecialchars($case['case_id_unique']) ?></h4>
    <a href="<?= BASE_URL ?>client/pending" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour</a>
</div>

<!-- Case Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div class="text-muted small">Montant</div>
            <div class="fw-bold fs-5">$<?= number_format($case['montant'], 2) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div class="text-muted small">Statut</div>
            <span class="badge-status status-<?= $case['statut'] ?>"><?= CaseModel::getStatusLabel($case['statut']) ?></span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div class="text-muted small">Fonds</div>
            <span class="badge-status fonds-<?= $case['statut_fonds'] ?>"><?= CaseModel::getFondsLabel($case['statut_fonds']) ?></span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div class="text-muted small">Score de risque</div>
            <span class="risk-badge bg-<?= RiskCalculator::getScoreClass($case['score_risque']) ?>">
                <?= $case['score_risque'] ?> - <?= RiskCalculator::getScoreLabel($case['score_risque']) ?>
            </span>
        </div>
    </div>
</div>

<!-- Transfer Button -->
<?php if ($case['statut'] === 'pret_pour_transfert' && $case['statut_fonds'] === 'disponible'): ?>
<div class="alert alert-success d-flex align-items-center">
    <i class="fas fa-check-circle me-2 fs-4"></i>
    <div class="flex-grow-1">
        <strong>Vos fonds sont prêts !</strong> Vous pouvez transférer $<?= number_format($case['montant'], 2) ?> vers votre compte principal.
    </div>
    <form method="POST" action="<?= BASE_URL ?>client/transfer">
        <?= CSRF::field() ?>
        <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
        <button type="submit" class="btn btn-success btn-lg" data-confirm="Confirmer le transfert ?">
            <i class="fas fa-arrow-right me-1"></i> Transférer maintenant
        </button>
    </form>
</div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-info">Informations</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-docs">Documents <span class="badge bg-secondary"><?= count($documents) ?></span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-checklist">Formalités <span class="badge bg-secondary"><?= count($checklist) ?></span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-messages">Messages <span class="badge bg-secondary"><?= count($messages) ?></span></a></li>
</ul>

<div class="tab-content">
    <!-- Info Tab -->
    <div class="tab-pane fade show active" id="tab-info">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-bofa-navy fw-bold mb-3">Transaction</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="text-muted">Émetteur</td><td class="fw-semibold"><?= htmlspecialchars($case['emetteur_nom']) ?></td></tr>
                            <tr><td class="text-muted">Banque émettrice</td><td><?= htmlspecialchars($case['emetteur_banque'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted">Bénéficiaire</td><td class="fw-semibold"><?= htmlspecialchars($case['beneficiaire_nom']) ?></td></tr>
                            <tr><td class="text-muted">Banque bénéficiaire</td><td><?= htmlspecialchars($case['beneficiaire_banque'] ?? '-') ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-bofa-navy fw-bold mb-3">Détails</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="text-muted">Pays d'origine</td><td><i class="fas fa-globe me-1"></i><?= htmlspecialchars($case['pays_origine']) ?></td></tr>
                            <tr><td class="text-muted">Pays destination</td><td><i class="fas fa-globe me-1"></i><?= htmlspecialchars($case['pays_destination']) ?></td></tr>
                            <tr><td class="text-muted">Type d'actif</td><td><?= htmlspecialchars($case['type_actif']) ?></td></tr>
                            <tr><td class="text-muted">Agent assigné</td><td><?= htmlspecialchars($case['agent_name'] ?? 'Non assigné') ?></td></tr>
                            <tr><td class="text-muted">Sous-compte</td><td><code><?= htmlspecialchars($case['numero_sous_compte']) ?></code></td></tr>
                            <tr><td class="text-muted">Date de création</td><td><?= date('d/m/Y H:i', strtotime($case['date_creation'])) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Documents Tab -->
    <div class="tab-pane fade" id="tab-docs">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-file-alt me-2"></i>Documents
            </div>
            <div class="card-body">
                <?php if (!empty($documents)): ?>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm">
                            <thead><tr><th>Fichier</th><th>Type</th><th>Statut</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td><i class="fas fa-file me-1"></i><?= htmlspecialchars($doc['nom_fichier']) ?></td>
                                    <td><?= htmlspecialchars($doc['type_document']) ?></td>
                                    <td>
                                        <?php if ($doc['statut_validation'] === 'valide'): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Validé</span>
                                        <?php elseif ($doc['statut_validation'] === 'rejete'): ?>
                                            <span class="badge bg-danger" title="<?= htmlspecialchars($doc['motif_rejet'] ?? '') ?>"><i class="fas fa-times"></i> Rejeté</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> En attente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($doc['date_upload'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Upload Form -->
                <h6 class="fw-bold">Téléverser un document</h6>
                <form method="POST" action="<?= BASE_URL ?>client/documents" enctype="multipart/form-data">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <select name="type_document" class="form-select form-select-sm">
                                <option>Justificatif d'identité</option>
                                <option>Relevé bancaire</option>
                                <option>Preuve d'origine des fonds</option>
                                <option>Facture</option>
                                <option>Contrat</option>
                                <option>Autre</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <input type="file" name="document" class="form-control form-control-sm" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-sm btn-bofa w-100"><i class="fas fa-upload me-1"></i>Téléverser</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Checklist Tab -->
    <div class="tab-pane fade" id="tab-checklist">
        <div class="card">
            <div class="card-header"><i class="fas fa-tasks me-2"></i>Formalités à compléter</div>
            <div class="card-body">
                <?php if (empty($checklist)): ?>
                    <p class="text-muted">Aucune formalité demandée pour ce dossier.</p>
                <?php else: ?>
                    <?php foreach ($checklist as $item): ?>
                        <div class="checklist-item <?= $item['est_coche'] ? 'completed' : '' ?>">
                            <?php if ($item['type_exigence'] === 'case'): ?>
                                <form method="POST" action="<?= BASE_URL ?>client/checklist" class="d-inline">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent">
                                        <i class="fas <?= $item['est_coche'] ? 'fa-check-square text-success' : 'fa-square text-muted' ?> fa-lg"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <i class="fas <?= $item['est_coche'] ? 'fa-file-circle-check text-success' : 'fa-file-circle-exclamation text-warning' ?> fa-lg"></i>
                            <?php endif; ?>
                            <span class="<?= $item['est_coche'] ? 'text-decoration-line-through text-muted' : '' ?>">
                                <?= htmlspecialchars($item['libelle']) ?>
                            </span>
                            <?php if ($item['type_exigence'] === 'document'): ?>
                                <span class="badge bg-info ms-auto">Document requis</span>
                                <?php if ($item['doc_name']): ?>
                                    <span class="text-success ms-1"><i class="fas fa-paperclip"></i> <?= htmlspecialchars($item['doc_name']) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Messages Tab -->
    <div class="tab-pane fade" id="tab-messages">
        <div class="card">
            <div class="card-header"><i class="fas fa-comments me-2"></i>Messagerie</div>
            <div class="card-body">
                <div class="mb-3" style="max-height:400px;overflow-y:auto">
                    <?php if (empty($messages)): ?>
                        <p class="text-muted text-center">Aucun message.</p>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="d-flex mb-2">
                                <div class="message-bubble <?= $msg['expediteur_id'] == Auth::id() ? 'message-sent' : 'message-received' ?>">
                                    <div class="fw-bold small"><?= htmlspecialchars($msg['sender_name']) ?></div>
                                    <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                    <div class="text-end mt-1" style="font-size:0.7rem;opacity:0.7"><?= date('d/m H:i', strtotime($msg['date'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="POST" action="<?= BASE_URL ?>client/send-message">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                    <div class="input-group">
                        <input type="text" name="message" class="form-control" placeholder="Écrire un message..." required>
                        <button type="submit" class="btn btn-bofa"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
