/* ============================================================ */
/* TOM SELECT – inicjalizacja i naprawa dla modalów             */
/* ============================================================ */

/**
 * Inicjalizuje TomSelect dla danego <select>.
 *
 * Kluczowe: dropdownParent = 'body' (nie modal!).
 * Dzięki temu dropdown renderuje się poza modalem i TomSelect
 * może poprawnie zmierzyć szerokość.
 */
function initTomSelect(el) {
    if (!el || el.tomselect) return;

    new TomSelect(el, {
        create: false,
        maxOptions: 500,
        sortField: {
            field: "text",
            direction: "asc"
        },
        dropdownParent: 'body',
        render: {
            option: function(data, escape) {
                return '<div>' + escape(data.text) + '</div>';
            },
            item: function(data, escape) {
                return '<div>' + escape(data.text) + '</div>';
            }
        }
    });
}

function refreshTomSelectInModal(modal) {
    modal.querySelectorAll('select.form-select').forEach(function(el) {
        if (el.tomselect) {
            const value = el.tomselect.getValue();
            el.tomselect.destroy();
            initTomSelect(el);
            if (value !== null && value !== undefined && value !== '') {
                el.tomselect.setValue(value, true);
            }
        } else {
            initTomSelect(el);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('select.form-select').forEach(function(el) {
        if (el.closest('.modal')) return;
        initTomSelect(el);
    });

    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('shown.bs.modal', function() {
            setTimeout(function() {
                refreshTomSelectInModal(modal);
            }, 100);
        });
    });
});

/* ============================================================ */
/* SCROLL REVEAL                                                */
/* ============================================================ */
(function() {
    document.querySelectorAll('.tab-pane .card, .modal .card').forEach(function(el) {
        el.classList.add('card-visible');
    });

    if (!('IntersectionObserver' in window)) {
        document.querySelectorAll('.card').forEach(function(el) {
            el.classList.add('card-visible');
        });
        return;
    }

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('card-visible');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.05,
        rootMargin: '0px 0px -30px 0px'
    });

    document.querySelectorAll('.card').forEach(function(el, index) {
        if (el.closest('.tab-pane') || el.closest('.modal')) {
            return;
        }
        if (index < 6) {
            el.style.transitionDelay = (index * 0.05) + 's';
        }
        observer.observe(el);
    });

    setTimeout(function() {
        document.querySelectorAll('.card:not(.card-visible)').forEach(function(el) {
            el.classList.add('card-visible');
        });
    }, 1000);
})();

/* ============================================================ */
/* RESPONSYWNOŚĆ – sidebar off-canvas                           */
/* ============================================================ */
(function() {
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const btnHamburger = document.getElementById('btnHamburger');

    function openSidebar() {
        if (!sidebar || !overlay) return;
        sidebar.classList.add('show');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (!sidebar || !overlay) return;
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (btnHamburger) {
        btnHamburger.addEventListener('click', function() {
            if (sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    closeSidebar();
                }
            });
        });
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });
})();

/* ============================================================ */
/* HEADER – efekt "scrolled"                                    */
/* ============================================================ */
(function() {
    const header = document.getElementById('appHeader');
    if (!header) return;

    function updateHeader() {
        if (window.scrollY > 10) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    }

    window.addEventListener('scroll', updateHeader, { passive: true });
    updateHeader();
})();

/* ============================================================ */
/* Globalne funkcje dostępne dla widoków                        */
/* ============================================================ */

function getTomValue(id) {
    const el = document.getElementById(id);
    if (!el) return '';
    if (el.tomselect) return el.tomselect.getValue();
    return el.value;
}

function showPreferencesModal(content) {
    let el = document.getElementById('prefModalContent');
    if (el) el.innerText = content;
    let modalEl = document.getElementById('prefModal');
    if (modalEl) {
        var myModal = new bootstrap.Modal(modalEl);
        myModal.show();
    }
}

function generateRandomPassword(targetInputId, containerId) {
    const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_!@#$";
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = '';

    const hasCrypto = typeof window.crypto !== 'undefined'
        && typeof window.crypto.getRandomValues === 'function';

    function randomIndex(max) {
        if (hasCrypto) {
            const array = new Uint32Array(1);
            const maxValid = Math.floor(0xFFFFFFFF / max) * max;
            let value;
            do {
                window.crypto.getRandomValues(array);
                value = array[0];
            } while (value >= maxValid);
            return value % max;
        }
        return Math.floor(Math.random() * max);
    }

    for (let i = 0; i < 5; i++) {
        let pass = "";
        for (let j = 0; j < 20; j++) {
            pass += chars.charAt(randomIndex(chars.length));
        }
        const badge = document.createElement('span');
        badge.className = 'badge bg-secondary font-monospace';
        badge.style.cursor = 'pointer';
        badge.innerText = pass;
        badge.addEventListener('click', function() {
            const input = document.getElementById(targetInputId);
            if (input) input.value = pass;
        });
        container.appendChild(badge);
        if (i === 0 && targetInputId === 'password') {
            const input = document.getElementById(targetInputId);
            if (input) input.value = pass;
        }
    }
}

function openEditUserModal(userId, username, fullName, primaryEmail, mailHost) {
    let form = document.getElementById('editUserForm');
    if (form) form.action = '/mail-users/' + userId;

    let loginInput = document.getElementById('edit_login');
    if (loginInput) loginInput.value = username;

    let loginHiddenInput = document.getElementById('edit_login_hidden');
    if (loginHiddenInput) loginHiddenInput.value = username;

    let nameParts = fullName ? fullName.trim().split(' ') : [];
    let imieInput = document.getElementById('edit_imie');
    let nazwiskoInput = document.getElementById('edit_nazwisko');
    if (imieInput) imieInput.value = nameParts[0] || '';
    if (nazwiskoInput) nazwiskoInput.value = nameParts.slice(1).join(' ') || '';

    let emailPlInput = document.getElementById('edit_email_pl');
    if (emailPlInput) emailPlInput.value = primaryEmail;

    let emailPlHiddenInput = document.getElementById('edit_email_pl_hidden');
    if (emailPlHiddenInput) emailPlHiddenInput.value = primaryEmail;

    let localPart = primaryEmail ? primaryEmail.split('@')[0] : '';
    let secondaryEmail = localPart ? localPart + '@telmor.com.pl' : '';

    let emailComPlInput = document.getElementById('edit_email_com_pl');
    if (emailComPlInput) emailComPlInput.value = secondaryEmail;

    let emailComPlHiddenInput = document.getElementById('edit_email_com_pl_hidden');
    if (emailComPlHiddenInput) emailComPlHiddenInput.value = secondaryEmail;

    let mailHostInput = document.getElementById('edit_mail_host');
    if (mailHostInput) mailHostInput.value = mailHost;

    let mailHostHiddenInput = document.getElementById('edit_mail_host_hidden');
    if (mailHostHiddenInput) mailHostHiddenInput.value = mailHost;

    let editPasswordInput = document.getElementById('edit_password');
    if (editPasswordInput) editPasswordInput.value = '';

    let editPwContainer = document.getElementById('editPwContainer');
    if (editPwContainer) editPwContainer.innerHTML = '';

    let modalEl = document.getElementById('editUserModal');
    if (modalEl) {
        var editModal = new bootstrap.Modal(modalEl);
        editModal.show();
    }
}
