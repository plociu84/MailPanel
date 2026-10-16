const CSRF = window.F2B.csrf;
let currentNoteIp = null;
let pollInterval = null;
let pollDots = '';

/**
 * Escapuje HTML w wartościach wstawianych do innerHTML.
 * Chroni przed stored XSS przez pola z bazy (notes, jail, unban_reason itp.).
 */
function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value).replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[c]));
}

function startSync() {
    const btn = document.getElementById('refresh-fail2ban-btn');
    const statusText = document.getElementById('sync-status-text');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Synchronizacja...';
    if (statusText) {
        statusText.className = 'text-muted small mt-1 text-center';
        statusText.innerText = 'Przetwarzanie w tle...';
    }

    fetch(window.F2B.routes.refresh, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json'
        },
    })
    .then(response => response.json())
    .then(data => {
        pollInterval = setInterval(checkSyncStatus, 1500);
    })
    .catch(error => {
        console.error('Błąd:', error);
        alert('Wystąpił błąd sieciowy.');
        resetButton();
    });
}

function checkSyncStatus() {
    const statusText = document.getElementById('sync-status-text');

    if (statusText) {
        pollDots = (pollDots.length >= 3) ? '' : pollDots + '.';
        statusText.innerText = 'Trwa przetwarzanie w tle' + pollDots;
    }

    fetch(window.F2B.routes.status, {
        headers: { 'Accept': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.syncing) {
            clearInterval(pollInterval);
            resetButton();
            if (statusText) {
                statusText.innerText = 'Zsynchronizowano! (' + (data.last_synced || 'teraz') + ')';
                statusText.classList.remove('text-muted');
                statusText.classList.add('text-success', 'fw-bold');
            }

            setTimeout(() => window.location.reload(), 1200);
        }
    })
    .catch(err => {
        console.error('Błąd statusu:', err);
    });
}

function resetButton() {
    const btn = document.getElementById('refresh-fail2ban-btn');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-arrows-rotate me-1"></i> Odśwież';
    }
}

document.getElementById('btnApplyFilters').addEventListener('click', function () {
    const jail = getTomValue('filterJail');
    const date = getTomValue('filterDate');
    const minBans = document.getElementById('filterMinBans').value;
    const tab = window.F2B.activeTab;
    const params = new URLSearchParams({ jail, date, min_bans: minBans, tab });
    window.location = window.F2B.routes.index + '?' + params.toString();
});

document.querySelectorAll('#f2bTabs button[data-bs-toggle="tab"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', function (e) {
        const tab = e.target.getAttribute('data-bs-target').replace('#tab-', '');
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        url.searchParams.delete('active_page');
        url.searchParams.delete('history_page');
        url.searchParams.delete('repeat_page');
        url.searchParams.delete('fortigate_page');
        window.history.replaceState({}, '', url);
    });
});

function copyIpList(btn) {
    const pre = document.getElementById('ipListPre');
    if (!pre) return;

    const text = pre.textContent;
    if (!text.trim()) {
        alert('Lista jest pusta.');
        return;
    }

    navigator.clipboard.writeText(text)
        .then(() => {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Skopiowano!';
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-success');
            setTimeout(() => {
                btn.innerHTML = original;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-primary');
            }, 1500);
        })
        .catch(err => {
            alert('Nie udało się skopiować: ' + err.message);
        });
}

function copyFeed() {
    const pre = document.getElementById('feedPre');
    if (!pre) return;

    const text = pre.textContent;
    if (!text.trim()) {
        alert('Feed jest pusty.');
        return;
    }

    navigator.clipboard.writeText(text)
        .then(() => alert('Skopiowano ' + text.trim().split('\n').length + ' linii.'))
        .catch(err => alert('Nie udało się skopiować: ' + err.message));
}

function previewFeed() {
    const modal = new bootstrap.Modal(document.getElementById('modalFeedPreview'));
    document.getElementById('feedPre').textContent = 'Ładowanie...';
    document.getElementById('feedCountLabel').textContent = '0';
    document.getElementById('feedPathLabel').textContent = '-';
    modal.show();

    fetch(window.F2B.routes.feedPreview, {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('feedCountLabel').textContent = data.count;
            document.getElementById('feedPathLabel').textContent = data.path;
            document.getElementById('feedPre').textContent = data.ips.join('\n') || '(pusty)';
        } else {
            document.getElementById('feedPre').textContent = 'Błąd: ' + (data.message || 'nieznany');
        }
    })
    .catch(err => {
        document.getElementById('feedPre').textContent = 'Błąd: ' + err.message;
    });
}

function syncFeed() {
    if (!confirm('Nadpisać plik feedu listą IP z bazy (fortigate_banned = true)?')) {
        return;
    }

    fetch(window.F2B.routes.feedSync, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Zsynchronizowano (' + data.count + ' IP).');
            location.reload();
        } else {
            alert('Błąd: ' + (data.message || 'nieznany'));
        }
    })
    .catch(err => alert('Błąd: ' + err.message));
}

function showIpHistory(ip) {
    document.getElementById('histIp').textContent = ip;
    document.getElementById('histTableBody').innerHTML = '<tr><td colspan="5" class="text-center text-muted">Ładowanie...</td></tr>';
    document.getElementById('histSummary').innerHTML = '';

    const modal = new bootstrap.Modal(document.getElementById('modalIpHistory'));
    modal.show();

    fetch(`/fail2ban/ip/${ip}`)
        .then(r => r.json())
        .then(data => {
            let summary = `
                <div class="d-flex gap-3 flex-wrap">
                    <div><strong>Łącznie banów:</strong> <span class="badge bg-warning text-dark">${data.total_bans}×</span></div>
                    <div><strong>Jaile:</strong> ${(data.jails || []).map(j => `<span class="badge bg-secondary">${escapeHtml(j)}</span>`).join(' ')}</div>
                    <div><strong>FortiGate:</strong> ${data.fortigate_banned ? '<span class="badge bg-dark">TAK (' + escapeHtml(data.fortigate_banned_at || '') + ')</span>' : '<span class="badge bg-light text-muted">NIE</span>'}</div>
                </div>
                ${data.notes ? '<div class="mt-2 small"><strong>Notatka:</strong> ' + escapeHtml(data.notes) + '</div>' : ''}
            `;
            document.getElementById('histSummary').innerHTML = summary;

            const tbody = document.getElementById('histTableBody');
            if (data.bans.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Brak wpisów.</td></tr>';
                return;
            }
            tbody.innerHTML = data.bans.map(b => `
                <tr>
                    <td><span class="badge bg-secondary">${escapeHtml(b.jail)}</span></td>
                    <td class="small">${escapeHtml(b.banned_at)}</td>
                    <td class="small">${b.unbanned_at ? escapeHtml(b.unbanned_at) : '<span class="text-success">aktywny</span>'}</td>
                    <td class="small">${b.ban_duration ? formatDuration(b.ban_duration) : '—'}</td>
                    <td class="small text-muted">${escapeHtml(b.unban_reason || '—')}</td>
                </tr>
            `).join('');
        })
        .catch(err => {
            document.getElementById('histTableBody').innerHTML = `<tr><td colspan="5" class="text-danger">Błąd: ${escapeHtml(err.message)}</td></tr>`;
        });
}

function formatDuration(sec) {
    if (!sec) return '—';
    const d = Math.floor(sec / 86400);
    const h = Math.floor((sec % 86400) / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    let out = [];
    if (d) out.push(d + 'd');
    if (h) out.push(h + 'h');
    if (m) out.push(m + 'm');
    if (s && !d) out.push(s + 's');
    return out.join(' ') || sec + 's';
}

function showNoteModal(ip, notes) {
    currentNoteIp = ip;
    document.getElementById('noteIp').textContent = ip;
    document.getElementById('noteText').value = notes || '';
    new bootstrap.Modal(document.getElementById('modalNote')).show();
}

function saveNote() {
    if (!currentNoteIp) return;
    const notes = document.getElementById('noteText').value;

    fetch(`/fail2ban/ip/${currentNoteIp}/note`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ notes }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalNote')).hide();
            location.reload();
        } else {
            alert('Błąd zapisu notatki');
        }
    })
    .catch(err => alert('Błąd: ' + err.message));
}

/**
 * Przełącza blokadę na FortiGate.
 *
 * ZMIANA: po sukcesie robi location.reload(), żeby wpis "przeniósł się"
 * między zakładkami (Recydywiści ↔ Fortigate Blocks).
 */
function toggleFortigate(ip, btn) {
    fetch(`/fail2ban/ip/${ip}/fortigate`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
        },
    })
    .then(r => r.json().then(data => ({ status: r.status, data })))
    .then(({ status, data }) => {
        if (data.success) {
            // Krótkie opóźnienie, żeby użytkownik zobaczył feedback (opcjonalnie)
            location.reload();
        } else {
            alert('Błąd: ' + (data.message || 'nieznany'));
        }
    })
    .catch(err => alert('Błąd: ' + err.message));
}
