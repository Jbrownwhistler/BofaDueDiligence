<meta name="base-url" content="<?= BASE_URL ?>">
<h4 class="mb-4"><i class="fas fa-life-ring me-2 text-bofa-red"></i>Aide & Support</h4>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-question-circle me-2"></i>Questions fréquentes (FAQ)</div>
            <div class="card-body">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">Comment soumettre des documents de conformité ?</button></h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body small">Rendez-vous dans votre dossier concerné via « Fonds en attente », puis utilisez le formulaire de téléversement dans l'onglet « Documents ». Les formats acceptés sont PDF, JPG, PNG, DOC et DOCX (max 10 Mo).</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">Combien de temps prend la vérification de conformité ?</button></h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body small">Le délai varie selon le niveau de risque. Les dossiers à faible risque sont généralement traités en 3-5 jours ouvrables. Les dossiers à haut risque nécessitant une double validation peuvent prendre 7-15 jours ouvrables.</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">Pourquoi mes fonds sont-ils bloqués ?</button></h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body small">Les fonds sont bloqués pendant le processus de vérification AML/KYC. C'est une mesure standard de conformité. Dès que votre dossier est validé, les fonds deviennent disponibles pour transfert vers votre compte principal.</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">Que signifie un score de risque élevé ?</button></h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body small">Le score de risque est calculé automatiquement en fonction du montant, du pays d'origine et du type d'actif. Un score élevé (≥ 7) déclenche une validation par un superviseur. Cela ne signifie pas que votre transaction est refusée, mais qu'elle nécessite une vérification approfondie (EDD).</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">Comment contacter mon agent de conformité ?</button></h2>
                        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body small">Utilisez la messagerie sécurisée intégrée à chaque dossier. Votre agent assigné recevra immédiatement une notification et vous répondra dans les meilleurs délais.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-headset me-2"></i>Contactez-nous</div>
            <div class="card-body">
                <div class="mb-3">
                    <h6><i class="fas fa-phone me-2 text-bofa-navy"></i>Téléphone</h6>
                    <p class="small text-muted mb-0">+1 (800) 432-1000<br>Lun-Ven : 8h-20h EST</p>
                </div>
                <hr>
                <div class="mb-3">
                    <h6><i class="fas fa-envelope me-2 text-bofa-navy"></i>Email</h6>
                    <p class="small text-muted mb-0">compliance@bankofamerica.com</p>
                </div>
                <hr>
                <div class="mb-3">
                    <h6><i class="fas fa-comments me-2 text-bofa-navy"></i>Messagerie sécurisée</h6>
                    <p class="small text-muted">Utilisez la messagerie intégrée à vos dossiers de conformité pour une communication sécurisée.</p>
                    <a href="<?= BASE_URL ?>client/secure-messages" class="btn btn-sm btn-bofa-outline"><i class="fas fa-envelope me-1"></i> Messagerie</a>
                </div>
                <hr>
                <div>
                    <h6><i class="fas fa-building me-2 text-bofa-navy"></i>Adresse</h6>
                    <p class="small text-muted mb-0">Bank of America<br>100 N Tryon St<br>Charlotte, NC 28255<br>United States</p>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-link me-2"></i>Liens utiles</div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2"><a href="<?= BASE_URL ?>client/compliance-center" class="text-decoration-none"><i class="fas fa-shield-halved me-1"></i> Centre de conformité</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>client/compliance-training" class="text-decoration-none"><i class="fas fa-graduation-cap me-1"></i> Formation AML</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>client/declarations" class="text-decoration-none"><i class="fas fa-file-signature me-1"></i> Déclarations</a></li>
                    <li class="mb-0"><a href="<?= BASE_URL ?>client/security-settings" class="text-decoration-none"><i class="fas fa-lock me-1"></i> Sécurité</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
