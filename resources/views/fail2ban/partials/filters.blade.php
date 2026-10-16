<!-- FILTRY + PRZYCISK ODŚWIEŻ -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-start">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Jail</label>
                <select id="filterJail" class="form-select form-select-sm">
                    <option value="all" {{ $selectedJail === 'all' ? 'selected' : '' }}>-- Wszystkie --</option>
                    @foreach($jails as $jail)
                        <option value="{{ $jail->name }}" {{ $selectedJail === $jail->name ? 'selected' : '' }}>
                            {{ $jail->name }} ({{ $jail->currently_banned }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Data (dla historii)</label>
                <select id="filterDate" class="form-select form-select-sm">
                    <option value="all" {{ $dateFilter === 'all' ? 'selected' : '' }}>Wszystko</option>
                    <option value="24h" {{ $dateFilter === '24h' ? 'selected' : '' }}>Ostatnie 24h</option>
                    <option value="7d"  {{ $dateFilter === '7d'  ? 'selected' : '' }}>Ostatnie 7 dni</option>
                    <option value="30d" {{ $dateFilter === '30d' ? 'selected' : '' }}>Ostatnie 30 dni</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Min. liczba banów <span class="fw-normal">(okno {{ $retentionDays }} dni)</span></label>
                <input type="number" id="filterMinBans" class="form-control form-control-sm"
                       value="{{ $minBans }}" min="0" step="1">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1 d-block">&nbsp;</label>
                <button type="button" id="btnApplyFilters" class="btn btn-sm btn-primary w-100">
                    <i class="fa-solid fa-filter me-1"></i> Zastosuj
                </button>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1 d-block">&nbsp;</label>
                <a href="{{ route('fail2ban.index') }}" class="btn btn-sm btn-outline-primary w-100">
                    <i class="fa-solid fa-rotate-left me-1"></i> Reset
                </a>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1 d-block">&nbsp;</label>
                <button type="button" id="refresh-fail2ban-btn" class="btn btn-sm btn-outline-primary w-100" onclick="startSync()">
                    <i class="fa-solid fa-arrows-rotate me-1"></i> Odśwież
                </button>
                <div id="sync-status-text" class="text-muted small mt-1 text-center" style="font-size: 0.75rem; min-height: 18px;"></div>
            </div>
        </div>

        <!-- WYSZUKIWARKA SERWEROWA -->
        <div class="row g-2 mt-2">
            <div class="col-md-6">
                <form method="GET" action="{{ route('fail2ban.index') }}">
                    @foreach(request()->except('q', 'active_page', 'history_page', 'repeat_page', 'fortigate_page', 'tab') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <input type="hidden" name="tab" value="{{ $activeTab }}">

                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="q" class="form-control"
                               placeholder="Szukaj po IP / jail (cała baza)..."
                               value="{{ $search }}"
                               autocomplete="off">
                        <button class="btn btn-outline-primary" type="submit">
                            Szukaj
                        </button>
                        @if($search !== '')
                            <a href="{{ route('fail2ban.index', array_merge(
                                    request()->except('q', 'active_page', 'history_page', 'repeat_page', 'fortigate_page', 'tab'),
                                    ['tab' => $activeTab]
                                )) }}"
                               class="btn btn-outline-secondary" title="Wyczyść">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
            @if($search !== '')
                <div class="col-md-6 d-flex align-items-center">
                    <span class="text-muted small">
                        <i class="fa-solid fa-filter me-1"></i>
                        Filtr: <strong>{{ $search }}</strong> — znaleziono
                        <strong>{{ $activeBans->total() + $historyIps->total() + $repeatOffenders->total() + $fortigateBlocked->total() }}</strong> pasujących
                    </span>
                </div>
            @endif
        </div>
    </div>
</div>
