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
