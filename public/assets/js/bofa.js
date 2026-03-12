/**
 * bofa.js — Scripts JavaScript principaux BofaDueDiligence
 * Application de conformité AML/EDD bancaire
 */

'use strict';

/* ============================================================
   Utilitaires cookies
   ============================================================ */

/**
 * Lit la valeur d'un cookie par son nom.
 * @param {string} name
 * @returns {string|null}
 */
function getCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Définit ou met à jour un cookie.
 * @param {string} name
 * @param {string} value
 * @param {number} days  Durée de vie en jours (défaut : 365)
 */
function setCookie(name, value, days = 365) {
    const expires = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Strict`;
}

/* ============================================================
   Mode sombre
   ============================================================ */

/**
 * Initialise le mode sombre à partir du cookie 'bofa_dark_mode'.
 * Doit être appelé au chargement de la page.
 */
function initDarkMode() {
    if (getCookie('bofa_dark_mode') === '1') {
        document.body.classList.add('dark-mode');
        updateDarkModeIcon(true);
    }
}

/**
 * Bascule le mode sombre, sauvegarde la préférence dans un cookie
 * et met à jour l'icône du bouton.
 */
function toggleDarkMode() {
    const enabled = document.body.classList.toggle('dark-mode');
    setCookie('bofa_dark_mode', enabled ? '1' : '0');
    updateDarkModeIcon(enabled);
}

/**
 * Met à jour l'icône des boutons de bascule du mode sombre.
 * @param {boolean} isDark
 */
function updateDarkModeIcon(isDark) {
    document.querySelectorAll('[data-dark-toggle]').forEach(btn => {
        const icon = btn.querySelector('i, .icon');
        if (icon) {
            icon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        }
        btn.title = isDark ? 'Mode clair' : 'Mode sombre';
        btn.setAttribute('aria-label', btn.title);
    });
}

/* ============================================================
   Jeton CSRF — inclusion automatique dans les requêtes AJAX
   ============================================================ */

/**
 * Retourne le jeton CSRF depuis la balise meta ou le premier
 * champ caché nommé 'csrf_token' du document.
 * @returns {string}
 */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    const field = document.querySelector('input[name="csrf_token"]');
    return field ? field.value : '';
}

/**
 * Enveloppe fetch() pour inclure automatiquement le jeton CSRF
 * dans les en-têtes et le corps des requêtes POST/PUT/PATCH.
 * @param {string} url
 * @param {RequestInit} options
 * @returns {Promise<Response>}
 */
function bofaFetch(url, options = {}) {
    const token = getCsrfToken();
    options.headers = Object.assign({}, options.headers, {
        'X-CSRF-Token': token,
        'X-Requested-With': 'XMLHttpRequest',
    });

    // Pour les requêtes avec corps FormData, injecter le token
    if (options.body instanceof FormData) {
        options.body.set('csrf_token', token);
    }

    return fetch(url, options);
}

/* ============================================================
   Sondage de notifications (polling toutes les 30 secondes)
   ============================================================ */

let _notifPollingTimer = null;

/**
 * Met à jour le badge de notifications en interrogeant l'API.
 */
async function refreshNotifCount() {
    try {
        /* URL depuis la config injectée par header.php, avec repli */
        const apiUrl = (window.bofaConfig && window.bofaConfig.notifApiUrl)
            ? window.bofaConfig.notifApiUrl
            : '/bofa/public/api/notifications.php';
        const resp = await bofaFetch(apiUrl);
        if (!resp.ok) return;
        const data = await resp.json();
        const count = parseInt(data.count, 10) || 0;
        document.querySelectorAll('.notif-badge').forEach(badge => {
            badge.textContent = count > 99 ? '99+' : (count > 0 ? String(count) : '');
            badge.setAttribute('data-count', String(count));
            badge.style.display = count > 0 ? '' : 'none';
        });
    } catch (_) {
        /* Silencieux — pas de perturbation de l'interface en cas d'erreur réseau */
    }
}

/**
 * Démarre le sondage de notifications.
 * Effectue une première requête immédiate puis toutes les 30 secondes.
 */
function startNotifPolling() {
    refreshNotifCount();
    _notifPollingTimer = setInterval(refreshNotifCount, 30000);
}

/** Arrête le sondage de notifications. */
function stopNotifPolling() {
    if (_notifPollingTimer) {
        clearInterval(_notifPollingTimer);
        _notifPollingTimer = null;
    }
}

/* ============================================================
   Progression d'upload de fichier
   ============================================================ */

/**
 * Attache un suivi de progression à un formulaire contenant un upload de fichier.
 * @param {HTMLFormElement} form     Formulaire cible
 * @param {HTMLElement}     barEl   Élément <div> de la barre de progression
 * @param {HTMLElement}     wrapEl  Conteneur à afficher/masquer
 */
function attachUploadProgress(form, barEl, wrapEl) {
    if (!form || !barEl || !wrapEl) return;

    form.addEventListener('submit', function (e) {
        const hasFile = Array.from(form.querySelectorAll('input[type="file"]'))
            .some(f => f.files && f.files.length > 0);
        if (!hasFile) return;

        e.preventDefault();
        wrapEl.classList.add('active');
        barEl.style.width = '0%';

        const formData = new FormData(form);
        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', ev => {
            if (ev.lengthComputable) {
                const pct = Math.round((ev.loaded / ev.total) * 100);
                barEl.style.width = pct + '%';
                barEl.textContent = pct + '%';
            }
        });

        xhr.addEventListener('load', () => {
            barEl.style.width = '100%';
            barEl.textContent = '100%';
            setTimeout(() => {
                wrapEl.classList.remove('active');
                /* Rediriger ou recharger selon la réponse */
                if (xhr.responseURL) window.location.href = xhr.responseURL;
                else window.location.reload();
            }, 600);
        });

        xhr.addEventListener('error', () => {
            wrapEl.classList.remove('active');
            showAlert('Erreur lors de l\'envoi du fichier.', 'danger');
        });

        xhr.open(form.method || 'POST', form.action || window.location.href);
        xhr.setRequestHeader('X-CSRF-Token', getCsrfToken());
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(formData);
    });
}

/* ============================================================
   Validation côté client (complément — non substitut du serveur)
   ============================================================ */

/**
 * Valide les champs requis d'un formulaire et retourne vrai si tout est valide.
 * Marque les champs invalides avec la classe Bootstrap 'is-invalid'.
 * @param {HTMLFormElement} form
 * @returns {boolean}
 */
function validateForm(form) {
    if (!form) return false;
    let valid = true;

    form.querySelectorAll('[required]').forEach(field => {
        field.classList.remove('is-invalid', 'is-valid');

        const value = field.value.trim();
        if (!value) {
            field.classList.add('is-invalid');
            valid = false;
            return;
        }

        /* Validation email */
        if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            field.classList.add('is-invalid');
            valid = false;
            return;
        }

        /* Validation mot de passe minimum 8 caractères */
        if (field.type === 'password' && field.dataset.minlength) {
            if (value.length < parseInt(field.dataset.minlength, 10)) {
                field.classList.add('is-invalid');
                valid = false;
                return;
            }
        }

        field.classList.add('is-valid');
    });

    return valid;
}

/* ============================================================
   Alertes Bootstrap auto-dismiss
   ============================================================ */

/**
 * Affiche une alerte Bootstrap dans le conteneur #alert-container.
 * @param {string} message
 * @param {string} type  'success' | 'danger' | 'warning' | 'info'
 */
function showAlert(message, type = 'info') {
    const container = document.getElementById('alert-container');
    if (!container) return;

    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show`;
    div.setAttribute('role', 'alert');
    div.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    container.prepend(div);

    /* Suppression automatique après 5 secondes */
    setTimeout(() => {
        if (div.parentNode) {
            div.classList.remove('show');
            setTimeout(() => div.remove(), 300);
        }
    }, 5000);
}

/**
 * Active l'auto-dismiss sur les alertes existantes dans la page.
 */
function autoDismissAlerts() {
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
        const delay = parseInt(alert.dataset.autoDismiss, 10) || 5000;
        setTimeout(() => {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 300);
        }, delay);
    });
}

/* ============================================================
   Dialogues de confirmation
   ============================================================ */

/**
 * Affiche un dialogue de confirmation Bootstrap modal.
 * @param {string}   message  Texte de la question
 * @param {Function} onOk     Rappel exécuté si l'utilisateur confirme
 * @param {string}   [title]  Titre du modal (défaut : 'Confirmation')
 */
function confirmDialog(message, onOk, title = 'Confirmation') {
    let modal = document.getElementById('bofa-confirm-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'bofa-confirm-modal';
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bofa-confirm-title"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="bofa-confirm-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-bofa-danger" id="bofa-confirm-ok">Confirmer</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(modal);
    }

    modal.querySelector('#bofa-confirm-title').textContent = title;
    modal.querySelector('#bofa-confirm-body').textContent = message;

    const bsModal = new bootstrap.Modal(modal);
    const okBtn   = modal.querySelector('#bofa-confirm-ok');

    const handleOk = () => {
        bsModal.hide();
        onOk();
    };

    /* Nettoyer les anciens écouteurs */
    const freshOk = okBtn.cloneNode(true);
    okBtn.parentNode.replaceChild(freshOk, okBtn);
    freshOk.addEventListener('click', handleOk, { once: true });

    bsModal.show();
}

/* ============================================================
   Formatage monétaire
   ============================================================ */

/**
 * Formate un montant numérique selon la devise indiquée.
 * @param {number} amount
 * @param {string} currency  Code ISO 4217 (ex. 'EUR', 'USD', 'GBP')
 * @returns {string}
 */
function formatAmount(amount, currency = 'EUR') {
    try {
        return new Intl.NumberFormat('fr-FR', {
            style:                 'currency',
            currency:              currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    } catch (_) {
        return `${parseFloat(amount).toFixed(2)} ${currency}`;
    }
}

/* ============================================================
   Avertissement d'expiration de session
   ============================================================ */

let _sessionTimer    = null;
let _sessionWarned   = false;
/* Durée de session depuis la config injectée par header.php, avec repli */
const SESSION_TIMEOUT = (window.bofaConfig && window.bofaConfig.sessionTimeout)
    ? window.bofaConfig.sessionTimeout
    : 1800;
const WARN_BEFORE     = 120;  // avertir 2 minutes avant expiration

/**
 * Démarre le minuteur d'expiration de session.
 * Un avertissement s'affiche 2 minutes avant la fin.
 */
function startSessionTimer() {
    const warnAt = (SESSION_TIMEOUT - WARN_BEFORE) * 1000;

    _sessionTimer = setTimeout(() => {
        if (_sessionWarned) return;
        _sessionWarned = true;

        const warning = document.getElementById('session-warning');
        if (warning) {
            warning.style.display = 'block';
        } else {
            showAlert('Votre session expire dans 2 minutes. Veuillez sauvegarder votre travail.', 'warning');
        }
    }, warnAt);
}

/**
 * Réinitialise le minuteur de session lors d'une interaction utilisateur.
 */
function resetSessionTimer() {
    if (_sessionTimer) clearTimeout(_sessionTimer);
    _sessionWarned = false;
    const warning = document.getElementById('session-warning');
    if (warning) warning.style.display = 'none';
    startSessionTimer();
}

/* ============================================================
   Aide : échappement HTML
   ============================================================ */

/**
 * Échappe les caractères spéciaux HTML pour affichage sûr.
 * @param {string} str
 * @returns {string}
 */
function escapeHtml(str) {
    return String(str)
        .replace(/&/g,  '&amp;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;')
        .replace(/"/g,  '&quot;')
        .replace(/'/g,  '&#039;');
}

/* ============================================================
   Repli/dépli de la sidebar
   ============================================================ */

function toggleSidebar() {
    const sidebar = document.querySelector('.bofa-sidebar');
    const main    = document.querySelector('.bofa-main');
    if (!sidebar) return;

    const collapsed = sidebar.classList.toggle('collapsed');
    if (main) main.classList.toggle('sidebar-collapsed', collapsed);
    setCookie('bofa_sidebar_collapsed', collapsed ? '1' : '0', 30);
}

/* ============================================================
   Initialisation au chargement du DOM
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
    /* Mode sombre */
    initDarkMode();

    /* Restaurer l'état de la sidebar */
    if (getCookie('bofa_sidebar_collapsed') === '1') {
        const sidebar = document.querySelector('.bofa-sidebar');
        const main    = document.querySelector('.bofa-main');
        if (sidebar) sidebar.classList.add('collapsed');
        if (main)    main.classList.add('sidebar-collapsed');
    }

    /* Boutons de bascule du mode sombre */
    document.querySelectorAll('[data-dark-toggle]').forEach(btn => {
        btn.addEventListener('click', toggleDarkMode);
    });

    /* Boutons de bascule de la sidebar */
    document.querySelectorAll('[data-sidebar-toggle]').forEach(btn => {
        btn.addEventListener('click', toggleSidebar);
    });

    /* Auto-dismiss des alertes flash */
    autoDismissAlerts();

    /* Sondage de notifications (uniquement sur les pages authentifiées) */
    if (document.querySelector('.notif-badge')) {
        startNotifPolling();
    }

    /* Minuteur de session (uniquement sur les pages authentifiées) */
    if (document.querySelector('.bofa-sidebar')) {
        startSessionTimer();
        ['click', 'keydown', 'mousemove', 'touchstart'].forEach(evt => {
            document.addEventListener(evt, resetSessionTimer, { passive: true });
        });
    }

    /* Upload progress — formulaires marqués data-upload-form */
    document.querySelectorAll('form[data-upload-form]').forEach(form => {
        const bar  = form.querySelector('.upload-progress-bar');
        const wrap = form.querySelector('.upload-progress-wrap');
        attachUploadProgress(form, bar, wrap);
    });

    /* Validation côté client des formulaires marqués data-validate */
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', e => {
            if (!validateForm(form)) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    /* Confirmations — liens et boutons avec data-confirm */
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            e.preventDefault();
            const msg   = el.dataset.confirm || 'Êtes-vous sûr de vouloir effectuer cette action ?';
            const title = el.dataset.confirmTitle || 'Confirmation';
            confirmDialog(msg, () => {
                if (el.tagName === 'A') {
                    window.location.href = el.href;
                } else if (el.closest('form')) {
                    el.closest('form').submit();
                }
            }, title);
        });
    });

    /* Overlay mobile sidebar */
    const overlay = document.querySelector('.sidebar-overlay');
    if (overlay) {
        overlay.addEventListener('click', () => {
            document.querySelector('.bofa-sidebar')?.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
    }

    document.querySelectorAll('[data-mobile-sidebar]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelector('.bofa-sidebar')?.classList.add('mobile-open');
            document.querySelector('.sidebar-overlay')?.classList.add('active');
        });
    });
});
