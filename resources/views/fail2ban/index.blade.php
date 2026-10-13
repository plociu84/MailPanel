@extends('layouts.app')

@section('title', 'Zarządzanie Fail2Ban')
@section('header-title', 'Zarządzanie Fail2Ban')

@section('content')
    <!-- Powiadomienia -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- KARTY STATYSTYK -->
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Aktywne bany</div>
                            <div class="fs-3 fw-bold text-primary">{{ number_format($stats['currently_banned']) }}</div>
                        </div>
                        <i class="fa-solid fa-ban fs-4 icon-muted" style="opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">IP w bazie</div>
                            <div class="fs-3 fw-bold text-primary">{{ number_format($stats['total_ips']) }}</div>
                        </div>
                        <i class="fa-solid fa-database fs-4 icon-muted" style="opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Bany 24h / 7d</div>
                            <div class="fs-3 fw-bold text-primary">
                                {{ number_format($stats['bans_last_24h']) }}
                                <span class="fs-6 text-muted">/ {{ number_format($stats['bans_last_7d']) }}</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-clock-rotate-left fs-4 icon-muted" style="opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">
                                Na FortiGate
                                <span class="badge bg-secondary ms-1" title="Liczba IP w pliku f2b-ips.txt">
                                    feed: {{ $fortigateFeedCount }}
                                </span>
                            </div>
                            <div class="fs-3 fw-bold text-primary">{{ number_format($stats['fortigate_banned']) }}</div>
                        </div>
                        <i class="fa-solid fa-shield fs-4 icon-muted" style="opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                    <label class="form-label small text-muted mb-1">Min. liczba banów</label>
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

    <!-- ZAKŁADKI -->
    <ul class="nav nav-tabs mb-3" id="f2bTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'active' ? 'active' : '' }}"
                    data-bs-toggle="tab" data-bs-target="#tab-active" type="button">
                <i class="fa-solid fa-ban icon-muted me-1"></i> Aktywne
                <span class="badge bg-primary ms-1">{{ $activeBans->total() }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'history' ? 'active' : '' }}"
                    data-bs-toggle="tab" data-bs-target="#tab-history" type="button">
                <i class="fa-solid fa-clock-rotate-left icon-muted me-1"></i> Historia
                <span class="badge bg-secondary ms-1">{{ $historyIps->total() }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'repeat' ? 'active' : '' }}"
                    data-bs-toggle="tab" data-bs-target="#tab-repeat" type="button">
                <i class="fa-solid fa-repeat icon-muted me-1"></i> Recydywiści
                <span class="badge bg-secondary ms-1">{{ $repeatOffenders->total() }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'fortigate' ? 'active' : '' }}"
                    data-bs-toggle="tab" data-bs-target="#tab-fortigate" type="button">
                <i class="fa-solid fa-shield icon-muted me-1"></i> Fortigate Blocks
                <span class="badge bg-dark ms-1">{{ $fortigateBlocked->total() }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'logs' ? 'active' : '' }}"
                    data-bs-toggle="tab" data-bs-target="#tab-logs" type="button">
                <i class="fa-solid fa-file-lines icon-muted me-1"></i> Logi (100)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'list' ? 'active' : '' }}"
                    data-bs-toggle="tab" data-bs-target="#tab-list" type="button">
                <i class="fa-solid fa-list icon-muted me-1"></i> Lista IP
                @if($listBans > 0)
                    <span class="badge bg-info ms-1">{{ $ipList->count() }}</span>
                @endif
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- ZAKŁADKA: AKTYWNE -->
        <div class="tab-pane fade {{ $activeTab === 'active' ? 'show active' : '' }}" id="tab-active" role="tabpanel">
            <div class="card">
                <div class="card-body p-2">
                    <div style="border: 1px solid var(--border-soft-2); border-radius: 8px; overflow: hidden;">
                        <table class="table table-sm table-hover table-condensed align-middle mb-0" id="tblActive">
                            <thead>
                                <tr>
                                    <th>IP</th>
                                    <th>Jail</th>
                                    <th class="text-center">Razy</th>
                                    <th>Ostatni ban</th>
                                    <th>Notatka</th>
                                    <th class="text-end">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($activeBans as $stat)
                                    <tr class="f2b-row">
                                        <td class="fw-bold font-monospace">{{ $stat->ip }}</td>
                                        <td>
                                            @foreach($stat->jails ?? [] as $j)
                                                <span class="badge bg-secondary">{{ $j }}</span>
                                            @endforeach
                                        </td>
                                        <td class="text-center">
                                            @if($stat->total_bans > 1)
                                                <span class="badge bg-warning text-dark">{{ $stat->total_bans }}×</span>
                                            @else
                                                <span class="text-muted">1</span>
                                            @endif
                                        </td>
                                        <td class="small text-muted">
                                            {{ $stat->last_banned_at?->format('Y-m-d H:i') }}
                                        </td>
                                        <td class="small text-muted" style="max-width: 200px;">
                                            @if($stat->notes)
                                                <span title="{{ $stat->notes }}">{{ Str::limit($stat->notes, 40) }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                            @if($stat->fortigate_banned)
                                                <i class="fa-solid fa-shield icon-muted ms-1" title="W feedzie FortiGate od {{ $stat->fortigate_banned_at?->format('Y-m-d H:i') }}"></i>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" onclick="showIpHistory('{{ $stat->ip }}')" title="Historia">
                                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" onclick="showNoteModal('{{ $stat->ip }}', @js($stat->notes))" title="Notatka">
                                                    <i class="fa-solid fa-note-sticky"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0 {{ $stat->fortigate_banned ? 'active' : '' }}"
                                                        onclick="toggleFortigate('{{ $stat->ip }}', this)"
                                                        title="{{ $stat->fortigate_banned ? 'Usuń z feedu FortiGate' : 'Dodaj do feedu FortiGate' }}">
                                                    <i class="fa-solid fa-shield"></i>
                                                </button>
                                                @if($stat->currently_banned && !empty($stat->jails))
                                                    <form action="{{ route('fail2ban.unban') }}" method="POST" class="d-inline"
                                                          onsubmit="return confirm('Odblokować IP {{ $stat->ip }} w jailu {{ $stat->jails[0] }}?');">
                                                        @csrf
                                                        <input type="hidden" name="ip" value="{{ $stat->ip }}">
                                                        <input type="hidden" name="jail" value="{{ $stat->jails[0] }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2 border-0" title="Odblokuj">
                                                            <i class="fa-solid fa-unlock"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            @if($search !== '')
                                                Brak aktywnych banów pasujących do „{{ $search }}”.
                                            @else
                                                Brak aktywnych banów.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $activeBans->links() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- ZAKŁADKA: HISTORIA -->
        <div class="tab-pane fade {{ $activeTab === 'history' ? 'show active' : '' }}" id="tab-history" role="tabpanel">
            <div class="card">
                <div class="card-body p-2">
                    <div style="border: 1px solid var(--border-soft-2); border-radius: 8px; overflow: hidden;">
                        <table class="table table-sm table-hover table-condensed align-middle mb-0" id="tblHistory">
                            <thead>
                                <tr>
                                    <th>IP</th>
                                    <th>Jail</th>
                                    <th class="text-center">Razy</th>
                                    <th>Ostatni ban</th>
                                    <th>Ostatni unban</th>
                                    <th>Notatka</th>
                                    <th class="text-end">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($historyIps as $stat)
                                    <tr class="f2b-row">
                                        <td class="fw-bold font-monospace">{{ $stat->ip }}</td>
                                        <td>
                                            @foreach($stat->jails ?? [] as $j)
                                                <span class="badge bg-secondary">{{ $j }}</span>
                                            @endforeach
                                        </td>
                                        <td class="text-center">
                                            @if($stat->total_bans > 1)
                                                <span class="badge bg-warning text-dark">{{ $stat->total_bans }}×</span>
                                            @else
                                                <span class="text-muted">1</span>
                                            @endif
                                        </td>
                                        <td class="small text-muted">{{ $stat->last_banned_at?->format('Y-m-d H:i') }}</td>
                                        <td class="small text-muted">{{ $stat->last_unbanned_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td class="small text-muted" style="max-width: 200px;">
                                            @if($stat->notes)
                                                <span title="{{ $stat->notes }}">{{ Str::limit($stat->notes, 40) }}</span>
                                            @endif
                                            @if($stat->fortigate_banned)
                                                <i class="fa-solid fa-shield icon-muted ms-1" title="W feedzie FortiGate"></i>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" onclick="showIpHistory('{{ $stat->ip }}')" title="Historia">
                                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" onclick="showNoteModal('{{ $stat->ip }}', @js($stat->notes))" title="Notatka">
                                                    <i class="fa-solid fa-note-sticky"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0 {{ $stat->fortigate_banned ? 'active' : '' }}"
                                                        onclick="toggleFortigate('{{ $stat->ip }}', this)"
                                                        title="{{ $stat->fortigate_banned ? 'Usuń z feedu FortiGate' : 'Dodaj do feedu FortiGate' }}">
                                                    <i class="fa-solid fa-shield"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            @if($search !== '')
                                                Brak historii pasujących do „{{ $search }}”.
                                            @else
                                                Brak historii.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $historyIps->links() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- ZAKŁADKA: RECYDYWIŚCI (niezablokowani na FortiGate) -->
        <div class="tab-pane fade {{ $activeTab === 'repeat' ? 'show active' : '' }}" id="tab-repeat" role="tabpanel">
            <div class="card">
                <div class="card-body p-2">
                    <div style="border: 1px solid var(--border-soft-2); border-radius: 8px; overflow: hidden;">
                        <table class="table table-sm table-hover table-condensed align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>IP</th>
                                    <th>Jail(e)</th>
                                    <th class="text-center">Razy banowany</th>
                                    <th>Pierwszy ban</th>
                                    <th>Ostatni ban</th>
                                    <th>Status</th>
                                    <th class="text-end">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($repeatOffenders as $i => $stat)
                                    <tr>
                                        <td class="text-muted">{{ $repeatOffenders->firstItem() + $i }}</td>
                                        <td class="fw-bold font-monospace">{{ $stat->ip }}</td>
                                        <td>
                                            @foreach($stat->jails ?? [] as $j)
                                                <span class="badge bg-secondary">{{ $j }}</span>
                                            @endforeach
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark fs-6">{{ $stat->total_bans }}×</span>
                                        </td>
                                        <td class="small text-muted">{{ $stat->first_banned_at?->format('Y-m-d H:i') }}</td>
                                        <td class="small text-muted">{{ $stat->last_banned_at?->format('Y-m-d H:i') }}</td>
                                        <td>
                                            @if($stat->currently_banned)
                                                <span class="badge bg-danger">Zbanowany</span>
                                            @else
                                                <span class="badge bg-success">Odbanowany</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" onclick="showIpHistory('{{ $stat->ip }}')" title="Historia">
                                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" onclick="showNoteModal('{{ $stat->ip }}', @js($stat->notes))" title="Notatka">
                                                    <i class="fa-solid fa-note-sticky"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-dark py-1 px-2 border-0"
                                                        onclick="toggleFortigate('{{ $stat->ip }}', this)"
                                                        title="Zablokuj na FortiGate">
                                                    <i class="fa-solid fa-shield"></i> Zablokuj
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            @if($search !== '')
                                                Brak recydywistów pasujących do „{{ $search }}”.
                                            @else
                                                Brak recydywistów (wszyscy zablokowani na FortiGate).
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $repeatOffenders->links() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- ZAKŁADKA: FORTIGATE BLOCKS (zablokowani na FortiGate) -->
        <div class="tab-pane fade {{ $activeTab === 'fortigate' ? 'show active' : '' }}" id="tab-fortigate" role="tabpanel">
            <div class="card">
                <div class="card-body p-2">
                    <div style="border: 1px solid var(--border-soft-2); border-radius: 8px; overflow: hidden;">
                        <table class="table table-sm table-hover table-condensed align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>IP</th>
                                    <th>Jail(e)</th>
                                    <th class="text-center">Razy banowany</th>
                                    <th>Data blokady na Forti</th>
                                    <th>Notatka</th>
                                    <th class="text-end">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($fortigateBlocked as $i => $stat)
                                    <tr>
                                        <td class="text-muted">{{ $fortigateBlocked->firstItem() + $i }}</td>
                                        <td class="fw-bold font-monospace">{{ $stat->ip }}</td>
                                        <td>
                                            @foreach($stat->jails ?? [] as $j)
                                                <span class="badge bg-secondary">{{ $j }}</span>
                                            @endforeach
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark">{{ $stat->total_bans }}×</span>
                                        </td>
                                        <td class="small text-muted">{{ $stat->fortigate_banned_at?->format('Y-m-d H:i') }}</td>
                                        <td class="small text-muted" style="max-width: 200px;">
                                            @if($stat->notes)
                                                <span title="{{ $stat->notes }}">{{ Str::limit($stat->notes, 40) }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" onclick="showIpHistory('{{ $stat->ip }}')" title="Historia">
                                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" onclick="showNoteModal('{{ $stat->ip }}', @js($stat->notes))" title="Notatka">
                                                    <i class="fa-solid fa-note-sticky"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success py-1 px-2 border-0"
                                                        onclick="toggleFortigate('{{ $stat->ip }}', this)"
                                                        title="Odblokuj na FortiGate">
                                                    <i class="fa-solid fa-unlock"></i> Odblokuj
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            @if($search !== '')
                                                Brak zablokowanych na FortiGate pasujących do „{{ $search }}”.
                                            @else
                                                Brak zablokowanych na FortiGate.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $fortigateBlocked->links() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- ZAKŁADKA: LOGI -->
        <div class="tab-pane fade {{ $activeTab === 'logs' ? 'show active' : '' }}" id="tab-logs" role="tabpanel">
            <div class="card">
                <div class="card-body p-2">
                    <div style="background-color: #1e1e2e; color: #cdd6f4; border: 1px solid #313244; font-family: Consolas, Monaco, monospace; font-size: 0.82rem; padding: 12px; border-radius: 6px;">
                        @forelse($recentEvents as $ev)
                            <div>
                                <span class="text-secondary">{{ $ev->banned_at->format('Y-m-d H:i:s') }}</span>
                                <span class="text-warning">[{{ $ev->jail }}]</span>
                                <span class="text-danger">Ban</span>
                                <span class="text-info fw-bold">{{ $ev->ip }}</span>
                                @if($ev->unbanned_at)
                                    <span class="text-secondary">→ Unban {{ $ev->unbanned_at->format('Y-m-d H:i:s') }}
                                        ({{ $ev->ban_duration ? gmdate('H:i:s', $ev->ban_duration) : '?' }})
                                    </span>
                                @else
                                    <span class="text-success">→ aktywny</span>
                                @endif
                            </div>
                        @empty
                            <div class="text-muted">Brak zdarzeń w bazie.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

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
                            <label class="form-label small text-muted mb-1">Min. liczba banów</label>
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
                                Recydywiści z <strong>≥ {{ $listBans }}</strong> banami
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
                            Podaj minimalną liczbę banów (np. <strong>8</strong>), aby wyświetlić listę adresów IP recydywistów.
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

    </div>

    <!-- MODAL: Historia IP -->
    <div class="modal fade" id="modalIpHistory" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Historia banów: <span id="histIp" class="font-monospace text-primary"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="histSummary" class="mb-3"></div>
                    <div id="histTableWrapper">
                        <table class="table table-sm table-condensed table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Jail</th>
                                    <th>Ban od</th>
                                    <th>Unban</th>
                                    <th>Czas trwania</th>
                                    <th>Powód</th>
                                </tr>
                            </thead>
                            <tbody id="histTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Notatka -->
    <div class="modal fade" id="modalNote" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Notatka dla IP: <span id="noteIp" class="font-monospace text-primary"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <textarea id="noteText" class="form-control" rows="5" maxlength="2000" placeholder="Wpisz notatkę..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="button" class="btn btn-primary" onclick="saveNote()">Zapisz</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Podgląd feedu FortiGate -->
    <div class="modal fade" id="modalFeedPreview" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-shield me-1"></i>
                        Podgląd feedu FortiGate (<span id="feedCountLabel">0</span>)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2">
                        Plik: <code id="feedPathLabel">-</code>
                    </div>
                    <pre id="feedPre" class="border rounded p-3 mb-0"
                         style="background:#f8f9fa; font-size:0.85rem; max-height:500px; overflow:auto; white-space:pre;"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                    <button type="button" class="btn btn-primary" onclick="copyFeed()">
                        <i class="fa-solid fa-copy me-1"></i> Kopiuj
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    /* Sticky header tylko w modalach (nie w głównych tabelach) */
    .modal .table thead.sticky-top th {
        background-color: #fff;
        position: sticky;
        top: 0;
        z-index: 10;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.06);
    }

    .tab-content table thead th {
        cursor: default;
        white-space: nowrap;
        font-size: 0.75rem;
        color: var(--text-muted);
        font-weight: var(--font-weight-medium);
        padding: 0.55rem 0.75rem;
        border-bottom: 1px solid var(--border-soft-2);
        vertical-align: middle;
        background-color: #fff;
    }
    .tab-content table tbody td {
        font-size: 0.8rem;
        padding: 0.55rem 0.75rem;
        vertical-align: middle;
    }
    .tab-content table tbody tr {
        line-height: 1.4;
    }

    .nav-tabs .nav-link {
        color: var(--text-main);
        font-weight: var(--font-weight-medium);
        font-size: 0.875rem;
        border: none;
        border-bottom: 2px solid transparent;
        transition: color 0.2s ease-in-out, border-color 0.2s ease-in-out;
    }
    .nav-tabs .nav-link:hover {
        color: var(--primary-color);
        border-bottom-color: transparent;
    }
    .nav-tabs .nav-link.active {
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
        background-color: transparent;
    }

    .icon-muted,
    i.icon-muted,
    i.icon-muted::before {
        color: var(--icon-muted) !important;
    }
</style>
@endpush

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';
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

    fetch('{{ route("fail2ban.refresh") }}', {
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

    fetch('{{ route("fail2ban.status") }}', {
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
    const tab = '{{ $activeTab }}';
    const params = new URLSearchParams({ jail, date, min_bans: minBans, tab });
    window.location = '{{ route('fail2ban.index') }}?' + params.toString();
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

    fetch('{{ route("fail2ban.fortigate-feed.preview") }}', {
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

    fetch('{{ route("fail2ban.fortigate-feed.sync") }}', {
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
</script>
@endpush