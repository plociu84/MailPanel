@extends('layouts.app')

@section('title', 'Zarządzanie Amavis')
@section('header-title', 'Zarządzanie Amavis')

@section('content')
    <!-- Powiadomienia -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Statystyki -->
    <div class="row g-3 mb-3">
        <div class="col-md-2 col-sm-6">
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Wszystkie</div>
                            <div class="fs-3 fw-bold text-primary">{{ $stats['total'] ?? 0 }}</div>
                        </div>
                        <i class="fa-solid fa-envelope fs-4 icon-muted" style="opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Czyste</div>
                            <div class="fs-3 fw-bold text-primary">{{ $stats['clean'] ?? 0 }}</div>
                        </div>
                        <i class="fa-solid fa-circle-check fs-4 icon-muted" style="opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">SPAMMY</div>
                            <div class="fs-3 fw-bold text-primary">{{ $stats['spammy'] ?? 0 }}</div>
                        </div>
                        <i class="fa-solid fa-triangle-exclamation fs-4 icon-muted" style="opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Spam</div>
                            <div class="fs-3 fw-bold text-primary">{{ $stats['spam'] ?? 0 }}</div>
                        </div>
                        <i class="fa-solid fa-ban fs-4 icon-muted" style="opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Wirusy</div>
                            <div class="fs-3 fw-bold text-primary">{{ $stats['virus'] ?? 0 }}</div>
                        </div>
                        <i class="fa-solid fa-virus fs-4 icon-muted" style="opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">W kwarantannie</div>
                            <div class="fs-3 fw-bold text-primary">{{ count($quarantined) }}</div>
                        </div>
                        <i class="fa-solid fa-box-archive fs-4 icon-muted" style="opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Informacja o przechowywaniu -->
    <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
        <i class="fa-solid fa-info-circle me-2"></i>
        Wiadomości w kwarantannie są przechowywane przez <strong>30 dni</strong>, po czym są automatycznie usuwane.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    @if(count($spammyMessages ?? []) > 0)
        <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            Znaleziono {{ count($spammyMessages) }} wiadomości oznaczonych jako SPAMMY w logach!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row" id="amavisRow">
        <!-- Lewa kolumna: Kwarantanna i SPAMMY -->
        <div class="col-xl-7 mb-4 mb-xl-0">
            <div class="card h-100" id="quarantineCard">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-envelope-circle-exclamation icon-muted me-2"></i> 
                        Kwarantanna i SPAMMY
                    </h5>
                    <div>
                        <span class="badge bg-primary px-2 py-1 me-2">{{ count($quarantined) }}</span>
                        @if(count($quarantined) > 0)
                            <form action="{{ route('amavis.release-all') }}" method="POST" class="d-inline" 
                                  onsubmit="return confirm('Czy na pewno chcesz uwolnić WSZYSTKIE wiadomości z kwarantanny?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-box-open me-1"></i> Uwolnij wszystkie
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="card-body p-2 d-flex flex-column">
                    <div class="mb-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">
                                <i class="fa-solid fa-search"></i>
                            </span>
                            <input type="text" 
                                   id="quarantineSearchInput" 
                                   class="form-control" 
                                   placeholder="Filtruj wiadomości (nadawca / odbiorca / temat)..."
                                   autocomplete="off">
                            <button class="btn btn-outline-primary" type="button" id="quarantineClearSearch" title="Wyczyść filtr">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex-grow-1" id="quarantineContainer" style="height: 500px; overflow-y: auto; overflow-x: hidden; border: 1px solid var(--border-soft-2); border-radius: 8px;">
                        <!-- Sekcja SPAMMY z logów -->
                        @if(count($spammyMessages ?? []) > 0)
                            <div class="border-bottom">
                                <h6 class="amavis-section-heading">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    Wiadomości SPAMMY (oznaczone, nie zablokowane):
                                </h6>
                                <div class="list-group list-group-flush">
                                    @foreach($spammyMessages as $msg)
                                        <div class="list-group-item quarantine-item" 
                                             data-search="{{ strtolower(($msg['from'] ?? '') . ' ' . ($msg['to'] ?? '') . ' ' . ($msg['date'] ?? '')) }}">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1 me-2">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <strong class="text-primary">
                                                            <i class="fa-solid fa-envelope icon-muted me-1"></i>
                                                            SPAMMY
                                                        </strong>
                                                        <span class="badge bg-warning text-dark ms-2">
                                                            Hits: {{ $msg['spam_score'] ?? 'N/A' }}
                                                        </span>
                                                    </div>
                                                    <div class="small text-muted mb-1">
                                                        <strong>Od:</strong> {{ $msg['from'] ?? 'Nieznany' }}
                                                    </div>
                                                    <div class="small text-muted mb-1">
                                                        <strong>Do:</strong> {{ $msg['to'] ?? 'Nieznany' }}
                                                    </div>
                                                    <div class="small text-muted">
                                                        <strong>Data:</strong> {{ $msg['date'] ?? 'Nieznana' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Sekcja kwarantanny -->
                        @if(count($quarantined) > 0)
                            <div>
                                <h6 class="amavis-section-heading">
                                    <i class="fa-solid fa-box-archive"></i>
                                    W kwarantannie:
                                </h6>
                                <div class="list-group list-group-flush">
                                    @foreach($quarantined as $email)
                                        <div class="list-group-item quarantine-item" 
                                             data-search="{{ strtolower(($email['info']['from'] ?? '') . ' ' . ($email['info']['to'] ?? '') . ' ' . ($email['info']['subject'] ?? '') . ' ' . ($email['file_date'] ?? '')) }}">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <strong class="text-primary flex-grow-1 me-2">
                                                    <i class="fa-solid fa-envelope icon-muted me-1"></i>
                                                    {{ $email['info']['subject'] ?? 'Brak tematu' }}
                                                </strong>
                                                <span class="badge bg-warning text-dark flex-shrink-0">
                                                    Score: {{ $email['info']['spam_score'] ?? 'N/A' }}
                                                </span>
                                            </div>
                                            <div class="small text-muted mb-1">
                                                <strong>Od:</strong> {{ $email['info']['from'] ?? 'Nieznany' }}
                                            </div>
                                            <div class="small text-muted mb-1">
                                                <strong>Do:</strong> {{ $email['info']['to'] ?? 'Nieznany' }}
                                            </div>
                                            <div class="small text-muted mb-1">
                                                <strong>Data wiadomości:</strong> {{ $email['info']['date'] ?? 'Nieznana' }}
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="small text-muted">
                                                    <strong>W kwarantannie od:</strong> {{ $email['file_date'] ?? 'Nieznana' }}
                                                    <span class="badge bg-light text-muted ms-1">{{ $email['file_size'] ?? '0 B' }}</span>
                                                </div>
                                                <div class="d-flex gap-2 amavis-actions">
                                                    <button type="button" class="btn btn-link p-0 preview-email" 
                                                            data-id="{{ $email['id'] }}" title="Podgląd wiadomości">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </button>
                                                    <form action="{{ route('amavis.release', $email['id']) }}" method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Czy na pewno chcesz uwolnić tę wiadomość i dostarczyć do adresata?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-link p-0" title="Uwolnij i dostarcz">
                                                            <i class="fa-solid fa-paper-plane"></i>
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('amavis.delete', $email['id']) }}" method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Czy na pewno chcesz usunąć tę wiadomość z kwarantanny? Ta operacja jest nieodwracalna!');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-link p-0" title="Usuń wiadomość">
                                                            <i class="fa-solid fa-trash" style="color: #dc3545;"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(count($quarantined) == 0 && count($spammyMessages ?? []) == 0)
                            <div class="text-center text-muted py-5">
                                <i class="fa-solid fa-inbox fa-3x mb-3"></i>
                                <p class="mb-0">Brak wiadomości w kwarantannie lub oznaczonych jako SPAMMY.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Prawa kolumna: Logi Amavis -->
        <div class="col-xl-5">
            <div class="card h-100" id="amavisLogsCard">
                <div class="card-header py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-file-lines icon-muted me-2"></i> 
                        Ostatnie 200 wpisów (amavis.log)
                    </h5>
                </div>
                <div class="card-body p-2 d-flex flex-column">
                    <div class="flex-grow-1" id="amavisLogsContainer" 
                         style="height: 500px; overflow-y: auto; overflow-x: hidden; background-color: #1e1e2e; color: #cdd6f4; border: 1px solid #313244; font-family: Consolas, Monaco, 'Courier New', monospace; font-size: 0.82rem; padding: 12px; border-radius: 6px;">
                        {!! nl2br(e($logs)) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal podglądu wiadomości -->
    <div class="modal fade" id="emailPreviewModal" tabindex="-1" aria-labelledby="emailPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="emailPreviewModalLabel">
                        <i class="fa-solid fa-envelope-open-text icon-muted me-2"></i>
                        Podgląd wiadomości
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="emailPreviewContent">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Ładowanie...</span>
                            </div>
                            <p class="mt-3 text-muted">Ładowanie wiadomości...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    /* Ikony w kartach – ten sam kolor co w menu */
    .icon-muted,
    i.icon-muted,
    i.icon-muted::before {
        color: var(--icon-muted) !important;
    }

    /* Lista kwarantanny – spójne obramowania */
    #quarantineContainer .list-group-item {
        background-color: transparent;
        border-color: var(--border-soft-2);
        padding: 0.75rem 0.5rem;
    }
    #quarantineContainer .list-group-item:last-child {
        border-bottom: none;
    }

    /* ============================================================ */
    /* NAGŁÓWEK SEKCJI W KWARANTANNIE / SPAMMY                      */
    /* ============================================================ */
    .amavis-section-heading {
        display: flex !important;
        align-items: center;
        gap: 0.5rem;
        background-color: var(--primary-color) !important;
        color: #fff !important;
        padding: 0.6rem 0.75rem !important;
        margin: 0 !important;
        font-weight: var(--font-weight-semibold) !important;
        font-size: 0.9rem;
        letter-spacing: 0.3px;
    }
    .amavis-section-heading i,
    .amavis-section-heading i::before {
        color: #fff !important;
    }

    /* ============================================================ */
    /* IKONY AKCJI W LIŚCIE KWARANTANNY                            */
    /* ============================================================ */
    .amavis-actions .btn-link {
        color: var(--icon-muted);
        text-decoration: none;
        border: none;
        font-size: 0.9rem;
        padding: 0;
        line-height: 1;
        transition: color 0.2s ease-in-out;
    }
    .amavis-actions .btn-link:hover {
        color: var(--primary-color);
    }

    /* Ikona kosza – awaryjnie, gdyby inline style został usunięty */
    .amavis-actions .btn-link .fa-trash {
        color: #dc3545;
    }
    .amavis-actions .btn-link:hover .fa-trash {
        color: #b02a37;
    }

    /* Modal – spójny wygląd nagłówków */
    #emailPreviewModal .card-header {
        background-color: #f8f9fa !important;
        border-bottom: 1px solid var(--border-soft-2);
        font-size: 0.875rem;
    }
    #emailPreviewModal .table {
        font-size: 0.875rem;
    }
    #emailPreviewModal .table td {
        padding: 0.5rem 0.75rem;
        vertical-align: top;
    }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Dodaj CSRF token do wszystkich żądań AJAX
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'X-Requested-With': 'XMLHttpRequest'
        }
    });
    
    // Funkcja do wyrównywania wysokości kart
    function equalizeCardHeights() {
        $('#quarantineCard, #amavisLogsCard').css('height', 'auto');
        
        setTimeout(function() {
            var quarantineCardHeight = $('#quarantineCard').outerHeight();
            var logsCardHeight = $('#amavisLogsCard').outerHeight();
            
            var maxHeight = Math.max(quarantineCardHeight, logsCardHeight);
            
            $('#quarantineCard, #amavisLogsCard').css('height', maxHeight + 'px');
        }, 100);
    }
    
    equalizeCardHeights();
    
    $(window).on('resize', function() {
        clearTimeout(window.equalizeTimeout);
        window.equalizeTimeout = setTimeout(equalizeCardHeights, 250);
    });
    
    // Funkcja filtrowania kwarantanny
    function filterQuarantine() {
        var filter = $('#quarantineSearchInput').val().toLowerCase().trim();
        var items = $('.quarantine-item');
        var visibleCount = 0;
        
        items.each(function() {
            var searchData = $(this).attr('data-search') || '';
            var matches = filter === '' || searchData.indexOf(filter) !== -1;
            
            if (matches) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });
        
        equalizeCardHeights();
    }
    
    $('#quarantineSearchInput').on('input keyup change search', filterQuarantine);
    
    $('#quarantineClearSearch').on('click', function() {
        $('#quarantineSearchInput').val('');
        filterQuarantine();
        $('#quarantineSearchInput').focus();
    });
    
    // Obsługa podglądu wiadomości — delegacja zdarzeń
    $(document).on('click', '.preview-email', function(e) {
        e.preventDefault();
        
        var emailId = $(this).data('id');
        console.log('=== PREVIEW CLICK ===');
        console.log('emailId:', emailId);
        console.log('bootstrap:', typeof bootstrap);
        
        if (!emailId) {
            alert('BŁĄD: data-id jest puste!');
            return;
        }
        
        if (typeof bootstrap === 'undefined') {
            alert('BŁĄD: Bootstrap JS nie jest załadowany!');
            return;
        }
        
        var modalEl = document.getElementById('emailPreviewModal');
        if (!modalEl) {
            alert('BŁĄD: Modal #emailPreviewModal nie istnieje w DOM!');
            return;
        }
        
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        
        $('#emailPreviewContent').html(
            '<div class="text-center py-5">' +
            '<div class="spinner-border text-primary"></div>' +
            '<p class="mt-3 text-muted">Ładowanie wiadomości...</p>' +
            '</div>'
        );
        
        var url = '/amavis/preview/' + encodeURIComponent(emailId);
        
        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('=== PREVIEW RESPONSE ===', response);
                
                if (!response || response.error) {
                    $('#emailPreviewContent').html(
                        '<div class="alert alert-danger m-3">' +
                        '<strong>Błąd:</strong> ' + (response && response.error ? response.error : 'Nieznany błąd') +
                        '</div>'
                    );
                    return;
                }
                
                var html = '<div class="p-3">';
                
                // === NAGŁÓWKI ===
                html += '<div class="card mb-3">';
                html += '<div class="card-header bg-light py-2"><strong>Nagłówki:</strong></div>';
                html += '<div class="card-body py-2">';
                html += '<table class="table table-sm mb-0">';
                
                var importantHeaders = ['From', 'To', 'Cc', 'Subject', 'Date', 'X-Spam-Score', 'X-Spam-Status', 'Message-ID'];
                var hasHeaders = false;
                
                importantHeaders.forEach(function(header) {
                    if (response.headers && response.headers[header]) {
                        hasHeaders = true;
                        var val = String(response.headers[header] || '');
                        html += '<tr>';
                        html += '<td class="fw-bold" style="width: 150px;">' + header + ':</td>';
                        html += '<td>' + $('<div>').text(val).html() + '</td>';
                        html += '</tr>';
                    }
                });
                
                if (!hasHeaders) {
                    html += '<tr><td colspan="2" class="text-muted">Brak nagłówków do wyświetlenia.</td></tr>';
                }
                
                html += '</table></div></div>';
                
                // === TREŚĆ ===
                var bodyText = response.body ? String(response.body) : '';
                var htmlBody = response.html_body ? String(response.html_body) : '';
                
                if (bodyText.trim().length > 0) {
                    html += '<div class="card mb-3">';
                    html += '<div class="card-header bg-light py-2"><strong>Treść (text):</strong></div>';
                    html += '<div class="card-body">';
                    html += '<pre class="mb-0" style="white-space: pre-wrap; word-wrap: break-word; max-height: 500px; overflow: auto;">' +
                            $('<div>').text(bodyText).html() + '</pre>';
                    html += '</div></div>';
                }
                
                if (htmlBody.trim().length > 0) {
                    html += '<div class="card mb-3">';
                    html += '<div class="card-header bg-light py-2"><strong>Treść HTML:</strong></div>';
                    html += '<div class="card-body p-0">';
                    html += '<iframe id="previewHtmlFrame" sandbox="allow-same-origin" ' +
                            'style="width: 100%; height: 500px; border: 0;"></iframe>';
                    html += '</div></div>';
                }
                
                if (!bodyText.trim() && !htmlBody.trim()) {
                    html += '<div class="alert alert-info m-3">Brak treści do wyświetlenia (wiadomość może być tylko nagłówkiem lub binarna).</div>';
                }
                
                // === RAW ===
                if (response.raw) {
                    html += '<div class="card">';
                    html += '<div class="card-header bg-light py-2">';
                    html += '<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#rawEmailContent">';
                    html += '<i class="fa-solid fa-code me-1"></i> Pokaż surową treść';
                    html += '</button></div>';
                    html += '<div id="rawEmailContent" class="collapse">';
                    html += '<div class="card-body">';
                    html += '<pre class="mb-0" style="white-space: pre-wrap; word-wrap: break-word; font-size: 0.8rem; max-height: 400px; overflow: auto;">' +
                            $('<div>').text(String(response.raw)).html() + '</pre>';
                    html += '</div></div></div>';
                }
                
                html += '</div>';
                
                $('#emailPreviewContent').html(html);
                
                // Wstaw HTML do iframe przez Blob (bezpieczne dla cudzysłowów)
                if (htmlBody.trim().length > 0) {
                    var iframe = document.getElementById('previewHtmlFrame');
                    if (iframe) {
                        var blob = new Blob([htmlBody], { type: 'text/html;charset=utf-8' });
                        iframe.src = URL.createObjectURL(blob);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr.status, error);
                console.error('Response:', xhr.responseText);
                
                var msg = 'Błąd podczas pobierania wiadomości: ' + error;
                if (xhr.status === 404) msg = 'Nie znaleziono wiadomości (404)';
                if (xhr.status === 419) msg = 'CSRF token wygasł — odśwież stronę';
                if (xhr.status === 500) msg = 'Błąd serwera — sprawdź logi Laravel';
                
                $('#emailPreviewContent').html(
                    '<div class="alert alert-danger m-3">' +
                    '<i class="fa-solid fa-circle-exclamation me-2"></i>' +
                    '<strong>' + msg + '</strong><br>' +
                    '<small>Status: ' + xhr.status + '</small>' +
                    '</div>'
                );
            }
        });
    });
    
    filterQuarantine();
});
</script>
@endpush