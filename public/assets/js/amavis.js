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
