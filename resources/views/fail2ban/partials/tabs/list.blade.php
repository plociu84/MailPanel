    <!-- ZAKŁADKA: LISTA IP (recydywiści) -->
    <div class="tab-pane fade {{ $activeTab === 'list' ? 'show active' : '' }}" id="tab-list" role="tabpanel">
        <div class="card">
            <div class="card-body p-3">

                <!-- Formularz: min. liczba banów -->
                <form method="GET" action="{{ route('fail2ban.index') }}" class="row g-2 align-items-end mb-3">
                    @foreach(request()->except('list_bans', 'active_page', 'history_page', 'repeat_page', 'fortigate_page', 'tab', 'download', 'download_feed') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <input type="hidden" name="tab" value="list">

                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Min. liczba banów <span class="fw-normal">(okno {{ $retentionDays }} dni)</span></label>
                        <input type="number" name="list_bans" class="form-control form-control-sm"
                               value="{{ $listBans > 0 ? $listBans : 2 }}" min="2" step="1">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="fa-solid fa-list me-1"></i> Pokaż listę
                        </button>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('fail2ban.index', array_merge(
                                request()->except('list_bans', 'active_page', 'history_page', 'repeat_page', 'fortigate_page', 'tab', 'download', 'download_feed'),
                                ['tab' => 'list']
                            )) }}"
                           class="btn btn-sm btn-outline-secondary w-100">
                            <i class="fa-solid fa-rotate-left me-1"></i> Wyczyść listę
                        </a>
                    </div>
                </form>

                @if($listBans > 0)
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="small text-muted">
                            <i class="fa-solid fa-repeat me-1"></i>
                            Recydywiści z <strong>≥ {{ $listBans }}</strong> banami w oknie {{ $retentionDays }} dni
                            @if($selectedJail !== 'all')
                                w jailu <strong>{{ $selectedJail }}</strong>
                            @endif
                            @if($search !== '')
                                (filtr: <strong>{{ $search }}</strong>)
                            @endif
                            — znaleziono <strong>{{ $ipList->count() }}</strong>
                        </div>
                        @if($ipList->isNotEmpty())
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyIpList(this)">
                                    <i class="fa-solid fa-copy me-1"></i> Kopiuj
                                </button>
                                <a href="{{ route('fail2ban.index', array_merge(
                                        request()->except('active_page', 'history_page', 'repeat_page', 'fortigate_page', 'download', 'download_feed'),
                                        ['tab' => 'list', 'list_bans' => $listBans, 'download' => 1]
                                    )) }}"
                                   class="btn btn-sm btn-outline-success">
                                    <i class="fa-solid fa-download me-1"></i> Pobierz .txt
                                </a>
                            </div>
                        @endif
                    </div>

                    @if($ipList->isEmpty())
                        <div class="alert alert-info mb-0">
                            Brak adresów IP spełniających kryteria.
                        </div>
                    @else
                        <pre id="ipListPre" class="border rounded p-3 mb-0"
                             style="background:#f8f9fa; font-size:0.85rem; white-space:pre; overflow-x:auto;">{{ $ipList->implode("\n") }}</pre>
                    @endif
                @else
                    <div class="alert alert-secondary mb-0">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Podaj minimalną liczbę banów w oknie ostatnich {{ $retentionDays }} dni (np. <strong>8</strong>), aby wyświetlić listę adresów IP recydywistów.
                    </div>
                @endif

                <hr class="my-4">

                <!-- Sekcja: FEED FORTIGATE -->
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="small text-muted">
                        <i class="fa-solid fa-shield me-1"></i>
                        Feed FortiGate:
                        <strong>{{ $fortigateFeedCount }}</strong> wpisów w
                        <code>{{ basename(app(\App\Services\Fail2ban\FortigateFeedService::class)->path()) }}</code>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="previewFeed()">
                            <i class="fa-solid fa-eye me-1"></i> Podgląd feedu
                        </button>
                        <a href="{{ route('fail2ban.index', array_merge(
                                request()->except('active_page', 'history_page', 'repeat_page', 'fortigate_page', 'download', 'download_feed'),
                                ['tab' => 'list', 'download_feed' => 1]
                            )) }}"
                           class="btn btn-sm btn-outline-dark">
                            <i class="fa-solid fa-download me-1"></i> Pobierz feed .txt
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="syncFeed()">
                            <i class="fa-solid fa-arrows-rotate me-1"></i> Synchronizuj z bazy
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
