<meta name="base-url" content="<?= BASE_URL ?>">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="fas fa-folder-open me-2 text-bofa-red"></i><?= htmlspecialchars($case['case_id_unique']) ?></h4>
    <a href="<?= BASE_URL ?>agent/cases" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour</a>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-3">
    <div class="col-md-2">
        <div class="card p-2 text-center">
            <div class="text-muted small">Montant</div>
            <div class="fw-bold">$<?= number_format($case['montant'], 2) ?></div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-2 text-center">
            <div class="text-muted small">Score</div>
            <span class="risk-badge bg-<?= RiskCalculator::getScoreClass($case['score_risque']) ?> text-white">
                <?= $case['score_risque'] ?> (<?= RiskCalculator::getScoreLabel($case['score_risque']) ?>)
            </span>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-2 text-center">
            <div class="text-muted small">Statut</div>
            <span class="badge-status status-<?= $case['statut'] ?>"><?= CaseModel::getStatusLabel($case['statut']) ?></span>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-2 text-center">
            <div class="text-muted small">Fonds</div>
            <span class="badge-status fonds-<?= $case['statut_fonds'] ?>"><?= CaseModel::getFondsLabel($case['statut_fonds']) ?></span>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-2 text-center">
            <div class="text-muted small">Origine</div>
            <span><i class="fas fa-globe me-1"></i><?= htmlspecialchars($case['pays_origine']) ?></span>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-2 text-center">
            <div class="text-muted small">Échéance</div>
            <?php if ($case['date_limite']): ?>
                <?php $isOverdue = strtotime($case['date_limite']) < time(); ?>
                <span class="<?= $isOverdue ? 'text-danger fw-bold' : '' ?>"><?= date('d/m/Y', strtotime($case['date_limite'])) ?></span>
            <?php else: ?>
                <span class="text-muted">-</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-3">
    <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
        <strong class="me-2"><i class="fas fa-bolt"></i> Actions:</strong>

        <?php if (!in_array($case['statut'], ['rejete', 'pret_pour_transfert'])): ?>
            <!-- Validate -->
            <form method="POST" action="<?= BASE_URL ?>agent/case/validate" class="d-inline">
                <?= CSRF::field() ?>
                <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                <button type="submit" class="btn btn-sm btn-success" data-confirm="Valider ce dossier ?">
                    <i class="fas fa-check me-1"></i>Valider
                </button>
            </form>

            <!-- Validate + supervisor -->
            <form method="POST" action="<?= BASE_URL ?>agent/case/validate" class="d-inline">
                <?= CSRF::field() ?>
                <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                <input type="hidden" name="superviseur" value="1">
                <button type="submit" class="btn btn-sm btn-outline-purple" style="border-color:#6f42c1;color:#6f42c1" data-confirm="Soumettre à validation superviseur ?">
                    <i class="fas fa-user-shield me-1"></i>Soumettre superviseur
                </button>
            </form>

            <!-- Request docs -->
            <form method="POST" action="<?= BASE_URL ?>agent/case/request-docs" class="d-inline">
                <?= CSRF::field() ?>
                <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-file-medical me-1"></i>Demander documents</button>
            </form>

            <!-- Reject -->
            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                <i class="fas fa-times me-1"></i>Rejeter
            </button>
        <?php endif; ?>

        <?php if ($case['statut_fonds'] !== 'gele' && $case['statut'] !== 'rejete'): ?>
            <form method="POST" action="<?= BASE_URL ?>agent/case/freeze" class="d-inline">
                <?= CSRF::field() ?>
                <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary" data-confirm="Geler les fonds ?"><i class="fas fa-snowflake me-1"></i>Geler</button>
            </form>
        <?php endif; ?>

        <?php if ($case['statut_fonds'] === 'gele'): ?>
            <form method="POST" action="<?= BASE_URL ?>agent/case/unfreeze" class="d-inline">
                <?= CSRF::field() ?>
                <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                <button type="submit" class="btn btn-sm btn-info text-white" data-confirm="Dégeler les fonds ?"><i class="fas fa-fire me-1"></i>Dégeler</button>
            </form>
        <?php endif; ?>

        <?php if ($case['superviseur_requis']): ?>
            <span class="badge bg-purple text-white ms-2" style="background:#6f42c1"><i class="fas fa-user-shield me-1"></i>Double validation requise</span>
        <?php endif; ?>
    </div>
</div>

<!-- 5 Tabs -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-recap">Récapitulatif</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-docs">Documents <span class="badge bg-secondary"><?= count($documents) ?></span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-checklist">Checklist <span class="badge bg-secondary"><?= count($checklist) ?></span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-messages">Historique & Messages <span class="badge bg-secondary"><?= count($messages) ?></span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-audit">Journal d'audit</a></li>
</ul>

<div class="tab-content">
    <!-- TAB 1: Récapitulatif -->
    <div class="tab-pane fade show active" id="tab-recap">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-exchange-alt me-2"></i>Transaction</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-muted" width="40%">Émetteur</td><td class="fw-semibold"><?= htmlspecialchars($case['emetteur_nom']) ?></td></tr>
                            <tr><td class="text-muted">Banque émettrice</td><td><?= htmlspecialchars($case['emetteur_banque'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted">Bénéficiaire</td><td class="fw-semibold"><?= htmlspecialchars($case['beneficiaire_nom']) ?></td></tr>
                            <tr><td class="text-muted">Banque bénéf.</td><td><?= htmlspecialchars($case['beneficiaire_banque'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted">Type d'actif</td><td><?= htmlspecialchars($case['type_actif']) ?></td></tr>
                            <tr><td class="text-muted">Devise</td><td><?= htmlspecialchars($case['devise']) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Analyse de risque</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-muted" width="40%">Pays d'origine</td><td><i class="fas fa-globe me-1"></i><?= htmlspecialchars($case['pays_origine']) ?> (coeff: <?= RiskCalculator::getCountryCoefficient($case['pays_origine']) ?>)</td></tr>
                            <tr><td class="text-muted">Pays destination</td><td><i class="fas fa-globe me-1"></i><?= htmlspecialchars($case['pays_destination']) ?></td></tr>
                            <tr><td class="text-muted">Coeff. type actif</td><td><?= RiskCalculator::getAssetCoefficient($case['type_actif']) ?></td></tr>
                            <tr><td class="text-muted">Facteur montant</td><td><?= round($case['montant'] / 10000, 2) ?></td></tr>
                            <tr><td class="text-muted">Score final</td><td><span class="risk-badge bg-<?= RiskCalculator::getScoreClass($case['score_risque']) ?> text-white fs-6"><?= $case['score_risque'] ?></span></td></tr>
                            <tr><td class="text-muted">Seuil superviseur</td><td><?= RiskCalculator::getThreshold() ?> <?= $case['score_risque'] >= RiskCalculator::getThreshold() ? '<i class="fas fa-exclamation-triangle text-danger ms-1"></i>' : '<i class="fas fa-check text-success ms-1"></i>' ?></td></tr>
                        </table>

                        <!-- Internal Note -->
                        <hr>
                        <h6 class="small fw-bold">Notes internes</h6>
                        <form method="POST" action="<?= BASE_URL ?>agent/case/update-status">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                            <input type="hidden" name="statut" value="<?= $case['statut'] ?>">
                            <div class="input-group input-group-sm">
                                <input type="text" name="commentaire" class="form-control" placeholder="Ajouter une note...">
                                <button class="btn btn-outline-secondary"><i class="fas fa-plus"></i></button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header"><i class="fas fa-info-circle me-2"></i>Sous-compte technique</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-muted">N° sous-compte</td><td><code><?= htmlspecialchars($case['numero_sous_compte']) ?></code></td></tr>
                            <tr><td class="text-muted">Ledger</td><td>$<?= number_format($case['ledger'], 2) ?></td></tr>
                            <tr><td class="text-muted">Compte principal</td><td><code><?= htmlspecialchars($case['numero_compte_principal']) ?></code></td></tr>
                            <tr><td class="text-muted">Créé le</td><td><?= date('d/m/Y H:i', strtotime($case['date_creation'])) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: Documents -->
    <div class="tab-pane fade" id="tab-docs">
        <div class="card">
            <div class="card-header"><i class="fas fa-file-alt me-2"></i>Documents du dossier</div>
            <div class="card-body p-0">
                <?php if (empty($documents)): ?>
                    <div class="text-center text-muted p-4">Aucun document.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Fichier</th><th>Type</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead>
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
                                    <td><?= date('d/m/Y H:i', strtotime($doc['date_upload'])) ?></td>
                                    <td>
                                        <?php if ($doc['statut_validation'] === 'en_attente'): ?>
                                            <form method="POST" action="<?= BASE_URL ?>agent/case/validate-doc" class="d-inline">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                                <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                                                <button class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                                            </form>
                                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectDocModal<?= $doc['id'] ?>"><i class="fas fa-times"></i></button>

                                            <!-- Reject Doc Modal -->
                                            <div class="modal fade" id="rejectDocModal<?= $doc['id'] ?>">
                                                <div class="modal-dialog"><div class="modal-content">
                                                    <form method="POST" action="<?= BASE_URL ?>agent/case/reject-doc">
                                                        <?= CSRF::field() ?>
                                                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                                        <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                                                        <div class="modal-header"><h6 class="modal-title">Rejeter le document</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                        <div class="modal-body">
                                                            <label class="form-label">Motif du rejet</label>
                                                            <textarea name="motif" class="form-control" rows="3" required></textarea>
                                                        </div>
                                                        <div class="modal-footer"><button type="submit" class="btn btn-danger">Rejeter</button></div>
                                                    </form>
                                                </div></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB 3: Checklist -->
    <div class="tab-pane fade" id="tab-checklist">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-tasks me-2"></i>Formalités</span>
            </div>
            <div class="card-body">
                <?php if (!empty($checklist)): ?>
                    <?php foreach ($checklist as $item): ?>
                        <div class="checklist-item <?= $item['est_coche'] ? 'completed' : '' ?>">
                            <i class="fas <?= $item['est_coche'] ? 'fa-check-circle text-success' : 'fa-circle text-muted' ?> fa-lg"></i>
                            <span class="<?= $item['est_coche'] ? 'text-decoration-line-through text-muted' : '' ?>"><?= htmlspecialchars($item['libelle']) ?></span>
                            <span class="badge bg-<?= $item['type_exigence'] === 'document' ? 'info' : 'secondary' ?> ms-auto"><?= $item['type_exigence'] === 'document' ? 'Document' : 'Case à cocher' ?></span>
                            <?php if ($item['doc_name']): ?>
                                <span class="text-success"><i class="fas fa-paperclip"></i> <?= htmlspecialchars($item['doc_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Aucune formalité définie.</p>
                <?php endif; ?>

                <hr>
                <h6 class="fw-bold small">Ajouter une formalité</h6>
                <form method="POST" action="<?= BASE_URL ?>agent/case/add-checklist">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <input type="text" name="libelle" class="form-control form-control-sm" placeholder="Libellé de la formalité..." required>
                        </div>
                        <div class="col-md-3">
                            <select name="type_exigence" class="form-select form-select-sm">
                                <option value="case">Case à cocher</option>
                                <option value="document">Document requis</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-sm btn-bofa w-100"><i class="fas fa-plus me-1"></i>Ajouter</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB 4: History & Messaging -->
    <div class="tab-pane fade" id="tab-messages">
        <div class="row g-3">
            <!-- Status History -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header"><i class="fas fa-history me-2"></i>Historique des statuts</div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush" style="max-height:400px;overflow-y:auto">
                            <?php foreach ($history as $h): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span class="badge-status status-<?= $h['nouveau_statut'] ?>"><?= CaseModel::getStatusLabel($h['nouveau_statut']) ?></span>
                                    <small class="text-muted"><?= date('d/m H:i', strtotime($h['date'])) ?></small>
                                </div>
                                <?php if ($h['commentaire']): ?>
                                    <small class="text-muted d-block mt-1"><?= htmlspecialchars($h['commentaire']) ?></small>
                                <?php endif; ?>
                                <small class="text-muted"><i class="fas fa-user me-1"></i><?= htmlspecialchars($h['user_name'] ?? 'Système') ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header"><i class="fas fa-comments me-2"></i>Messagerie client</div>
                    <div class="card-body">
                        <div class="mb-3" style="max-height:350px;overflow-y:auto">
                            <?php if (empty($messages)): ?>
                                <p class="text-muted text-center">Aucun message.</p>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <div class="d-flex mb-2">
                                        <div class="message-bubble <?= $msg['expediteur_id'] == Auth::id() ? 'message-sent' : 'message-received' ?>">
                                            <div class="fw-bold small"><?= htmlspecialchars($msg['sender_name']) ?>
                                                <span class="badge bg-<?= $msg['sender_role'] === 'agent' || $msg['sender_role'] === 'admin' ? 'info' : 'secondary' ?>"><?= ucfirst($msg['sender_role']) ?></span>
                                            </div>
                                            <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                            <div class="text-end mt-1" style="font-size:0.7rem;opacity:0.7"><?= date('d/m H:i', strtotime($msg['date'])) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <form method="POST" action="<?= BASE_URL ?>agent/case/send-message">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                            <div class="input-group">
                                <input type="text" name="message" class="form-control" placeholder="Message au client..." required>
                                <button class="btn btn-bofa"><i class="fas fa-paper-plane"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 5: Audit -->
    <div class="tab-pane fade" id="tab-audit">
        <div class="card">
            <div class="card-header"><i class="fas fa-clipboard-list me-2"></i>Journal d'audit</div>
            <div class="card-body p-0">
                <?php if (empty($auditLogs)): ?>
                    <div class="text-center text-muted p-4">Aucune entrée.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($auditLogs as $log): ?>
                        <div class="list-group-item audit-entry">
                            <div class="d-flex justify-content-between">
                                <span><?= htmlspecialchars($log['action']) ?></span>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($log['date'])) ?></small>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($log['user_name'] ?? 'Système') ?>
                                | <i class="fas fa-network-wired me-1"></i><?= htmlspecialchars($log['ip'] ?? '-') ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="<?= BASE_URL ?>agent/case/reject">
            <?= CSRF::field() ?>
            <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
            <div class="modal-header bg-danger text-white"><h6 class="modal-title"><i class="fas fa-times me-1"></i>Rejeter le dossier</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label fw-bold">Motif du rejet</label>
                <textarea name="motif" class="form-control" rows="3" required placeholder="Expliquez le motif du rejet..."></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-danger">Confirmer le rejet</button></div>
        </form>
    </div></div>
</div>
