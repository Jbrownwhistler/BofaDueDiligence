/**
 * BofaDueDiligence - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // ===== Notifications Polling =====
    const notifCount = document.querySelector('.notif-count');
    const notifList = document.getElementById('notif-list');

    function loadNotifications() {
        fetch(BASE_URL + 'api/notifications')
            .then(r => r.json())
            .then(data => {
                if (data.count > 0) {
                    notifCount.textContent = data.count;
                    notifCount.style.display = 'inline-block';
                    let html = '';
                    data.notifications.forEach(n => {
                        const typeIcon = {
                            'info': 'fa-info-circle text-primary',
                            'success': 'fa-check-circle text-success',
                            'warning': 'fa-exclamation-triangle text-warning',
                            'danger': 'fa-exclamation-circle text-danger'
                        };
                        const icon = typeIcon[n.type] || typeIcon['info'];
                        html += `<div class="notif-item unread" data-id="${n.id}" data-link="${n.lien || '#'}">
                            <i class="fas ${icon} me-2"></i>
                            <span>${n.message}</span>
                            <div class="text-muted mt-1" style="font-size:0.75rem">${n.date}</div>
                        </div>`;
                    });
                    notifList.innerHTML = html;

                    // Click handler for notifications
                    document.querySelectorAll('.notif-item').forEach(item => {
                        item.addEventListener('click', function() {
                            const id = this.dataset.id;
                            const link = this.dataset.link;
                            fetch(BASE_URL + 'api/notifications/read', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                body: 'id=' + id
                            }).then(() => {
                                if (link && link !== '#') window.location.href = link;
                            });
                        });
                    });
                } else {
                    notifCount.style.display = 'none';
                    notifList.innerHTML = '<div class="text-center text-muted p-3"><small>Aucune notification</small></div>';
                }
            })
            .catch(() => {});
    }

    // Load notifications immediately and poll every 30s
    if (notifCount) {
        loadNotifications();
        setInterval(loadNotifications, 30000);
    }

    // Mark all as read
    const markAllBtn = document.getElementById('mark-all-read');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            fetch(BASE_URL + 'api/notifications/read', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'all=1'
            }).then(() => loadNotifications());
        });
    }

    // ===== Mobile Sidebar Toggle =====
    const toggler = document.querySelector('.navbar-toggler');
    const sidebar = document.getElementById('sidebar');
    if (toggler && sidebar) {
        toggler.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    // ===== Auto-dismiss alerts after 5s =====
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // ===== Confirm dialogs =====
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // ===== Format currency =====
    document.querySelectorAll('.currency').forEach(el => {
        const val = parseFloat(el.textContent);
        if (!isNaN(val)) {
            el.textContent = new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: el.dataset.currency || 'USD'
            }).format(val);
        }
    });
});

// Global BASE_URL (set from PHP)
const BASE_URL = document.querySelector('meta[name="base-url"]')?.content || '/';
