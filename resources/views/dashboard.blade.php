@extends('layouts.app')

@section('title', 'Dashboard - ' . $machineInfo['hostname'])
@section('header-title', 'Przegląd Infrastruktury Poczty')

@section('content')
    <!-- Kafelki zasobów (CPU, RAM, Dysk) -->
    <div class="row">
        <div class="col-xl-4 col-md-6">
            <div class="card stat-card">
                <div class="card-body">
                    <h6 class="text-muted fw-normal mb-1">System i Procesor</h6>
                    <h5 class="fw-bold mb-1 text-dark" style="font-size: 0.95rem;">{{ Str::limit($machineInfo['os'], 35) }}</h5>
                    <small class="text-muted"><i class="fa-solid fa-microchip me-1"></i> {{ $machineInfo['cpu_model'] }} ({{ $machineInfo['cpu_cores'] }} rdzenie)</small>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card stat-card">
                <div class="card-body">
                    <h6 class="text-muted fw-normal mb-1">Pamięć RAM</h6>
                    <h5 class="fw-bold mb-1 text-dark">{{ $machineInfo['ram']['used'] ?? 0 }} MB / {{ $machineInfo['ram']['total'] ?? 0 }} MB</h5>
                    @php
                        $ramTotal = $machineInfo['ram']['total'] ?? 1;
                        $ramUsed = $machineInfo['ram']['used'] ?? 0;
                        $ramPercent = $ramTotal > 0 ? round(($ramUsed / $ramTotal) * 100) : 0;
                    @endphp
                    <div class="progress mt-2" style="height: 5px;">
                        <div class="progress-bar" role="progressbar" style="width: {{ $ramPercent }}%; background-color: var(--primary-color);"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-12">
            <div class="card stat-card">
                <div class="card-body">
                    <h6 class="text-muted fw-normal mb-1">Przestrzeń Dyskowa</h6>
                    <h5 class="fw-bold mb-1 text-dark">{{ $machineInfo['disk']['used'] }} GB / {{ $machineInfo['disk']['total'] }} GB</h5>
                    @php
                        $diskTotal = $machineInfo['disk']['total'] ?: 1;
                        $diskPercent = round(($machineInfo['disk']['used'] / $diskTotal) * 100);
                    @endphp
                    <div class="progress mt-2" style="height: 5px;">
                        <div class="progress-bar" role="progressbar" style="width: {{ $diskPercent }}%; background-color: var(--primary-color);"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kafelki Fail2Ban -->
    <div class="row mt-3">
        <div class="col-xl-3 col-md-6">
            <a href="{{ route('fail2ban.index') }}" class="text-decoration-none">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted fw-normal mb-1">Aktywne bany</h6>
                                <h4 class="fw-bold mb-0 text-primary">{{ number_format($fail2banStats['active_bans']) }}</h4>
                                <small class="text-muted">Kliknij, aby zarządzać</small>
                            </div>
                            <i class="fa-solid fa-ban fs-4 icon-muted" style="opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="{{ route('fail2ban.index') }}" class="text-decoration-none">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted fw-normal mb-1">IP w bazie</h6>
                                <h4 class="fw-bold mb-0 text-primary">{{ number_format($fail2banStats['total_ips']) }}</h4>
                                <small class="text-muted">Historia banów</small>
                            </div>
                            <i class="fa-solid fa-database fs-4 icon-muted" style="opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="{{ route('fail2ban.index') }}" class="text-decoration-none">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted fw-normal mb-1">Bany 24h</h6>
                                <h4 class="fw-bold mb-0 text-primary">{{ number_format($fail2banStats['bans_24h']) }}</h4>
                                <small class="text-muted">Ostatnie 24 godziny</small>
                            </div>
                            <i class="fa-solid fa-clock-rotate-left fs-4 icon-muted" style="opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="{{ route('fail2ban.index') }}" class="text-decoration-none">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted fw-normal mb-1">Na FortiGate</h6>
                                <h4 class="fw-bold mb-0 text-primary">{{ number_format($fail2banStats['fortigate_banned']) }}</h4>
                                <small class="text-muted">Ręczne blokady</small>
                            </div>
                            <i class="fa-solid fa-shield fs-4 icon-muted" style="opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Karty z logami Fail2ban i Amavis -->
    <div class="row log-row mt-4">
        <!-- Fail2ban -->
        <div class="col-xl-6 d-flex flex-column">
            <div class="card flex-fill">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa-solid fa-shield-halved me-2 icon-muted" style="opacity: 0.85;"></i> Fail2ban — ostatnie zdarzenia</h5>
                    <a href="{{ route('fail2ban.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-arrow-right me-1"></i> Pełny widok
                    </a>
                </div>
                <div class="card-body" style="padding: 15px; flex: 1; overflow: hidden;">
                    <div style="height: 500px !important; overflow-y: auto; background-color: #1e1e2e; color: #cdd6f4; border: 1px solid #313244; font-family: Consolas, Monaco, monospace; font-size: 0.82rem; padding: 15px; border-radius: 6px; line-height: 1.6; scrollbar-width: thin; scrollbar-color: #45475a #181825;">

                        <div class="mb-3" style="color: #f9e2af; font-weight: 600; font-size: 0.9rem;">
                            <i class="fa-solid fa-repeat me-1"></i> TOP 5 RECYDYWIŚCI ({{ $retentionDays }} DNI)
                        </div>

                        @forelse($topRepeatOffenders as $offender)
                            <div style="padding: 6px 0; border-bottom: 1px solid #313244;">
                                <span style="color: #f38ba8; font-weight: bold;">{{ $offender['ip'] }}</span>
                                <span style="color: #a6e3a1;">— {{ $offender['total_bans'] }}×</span>
                                <span style="color: #6c7086;">({{ implode(', ', $offender['jails']) }})</span>
                                @if($offender['currently_banned'])
                                    <span class="badge bg-danger ms-1">aktywny</span>
                                @endif
                                @if($offender['fortigate_banned'])
                                    <span class="badge bg-dark ms-1">FortiGate</span>
                                @endif
                                <div style="color: #6c7086; font-size: 0.75rem;">
                                    ostatni ban: {{ $offender['last_banned_at'] }}
                                </div>
                            </div>
                        @empty
                            <div style="color: #6c7086;">Brak recydywistów.</div>
                        @endforelse

                        <div class="mt-4 mb-2" style="color: #89dceb; font-weight: 600; font-size: 0.9rem;">
                            <i class="fa-solid fa-clock me-1"></i> OSTATNIE 10 BANÓW
                        </div>

                        @forelse($recentBans as $ban)
                            <div style="padding: 3px 0;">
                                <span style="color: #6c7086;">{{ $ban['banned_at'] }}</span>
                                <span style="color: #f9e2af;">[{{ $ban['jail'] }}]</span>
                                <span style="color: #f38ba8;">Ban</span>
                                <span style="color: #89dceb; font-weight: bold;">{{ $ban['ip'] }}</span>
                                @if($ban['unbanned_at'])
                                    <span style="color: #6c7086;">→ odbanowany {{ $ban['unbanned_at'] }}</span>
                                @else
                                    <span style="color: #a6e3a1;">→ aktywny</span>
                                @endif
                            </div>
                        @empty
                            <div style="color: #6c7086;">Brak zdarzeń w bazie.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Amavis Spam -->
        <div class="col-xl-6 d-flex flex-column">
            <div class="card flex-fill">
                <div class="card-header">
                    <h5><i class="fa-solid fa-envelope-circle-check me-2 icon-muted" style="opacity: 0.85;"></i> Filtrowanie Spam (Amavis)</h5>
                    <span class="badge bg-primary-subtle text-primary px-2 py-1" style="font-size: 0.75rem;">Antispam</span>
                </div>
                <div class="card-body d-flex flex-column" style="padding: 15px; flex: 1; overflow: hidden;">
                    <div style="height: 500px !important; max-height: 500px !important; min-height: 500px !important; overflow-y: scroll !important; overflow-x: hidden !important; background-color: #1e1e2e; color: #cdd6f4; border: 1px solid #313244; font-family: Consolas, Monaco, 'Courier New', monospace; font-size: 0.85rem; padding: 15px; border-radius: 6px; white-space: normal; word-break: break-all; line-height: 1.6; box-sizing: border-box; scrollbar-width: thin; scrollbar-color: #45475a #181825;">
                        {!! nl2br(e($amavisSpam)) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sekcja Syslog -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fa-solid fa-terminal me-2 icon-muted" style="opacity: 0.85;"></i> Ostatnie błędy systemu (Syslog)</h5>
                    <span class="badge bg-primary-subtle text-primary px-2 py-1" style="font-size: 0.75rem;">System Logs</span>
                </div>
                <div class="card-body" style="display: block !important;">
                    <div style="height: 400px !important; max-height: 400px !important; min-height: 400px !important; overflow-y: scroll !important; overflow-x: hidden !important; background-color: #1e1e2e; color: #cdd6f4; border: 1px solid #313244; font-family: Consolas, Monaco, 'Courier New', monospace; font-size: 0.85rem; padding: 15px; border-radius: 6px; white-space: normal; word-break: break-all; line-height: 1.6; box-sizing: border-box; scrollbar-width: thin; scrollbar-color: #45475a #181825;">
                        {!! nl2br(e($syslogErrors)) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .log-row > .col-xl-6 {
        display: flex;
    }
    .log-row > .col-xl-6 .card {
        width: 100%;
        display: flex;
        flex-direction: column;
    }
    .log-row > .col-xl-6 .card-body {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
    }
    .log-row [style*="overflow-y: scroll"]::-webkit-scrollbar,
    .log-row [style*="overflow-y: auto"]::-webkit-scrollbar {
        width: 8px;
    }
    .log-row [style*="overflow-y: scroll"]::-webkit-scrollbar-track,
    .log-row [style*="overflow-y: auto"]::-webkit-scrollbar-track {
        background: #181825;
        border-radius: 4px;
    }
    .log-row [style*="overflow-y: scroll"]::-webkit-scrollbar-thumb,
    .log-row [style*="overflow-y: auto"]::-webkit-scrollbar-thumb {
        background: #45475a;
        border-radius: 4px;
    }
    .log-row [style*="overflow-y: scroll"]::-webkit-scrollbar-thumb:hover,
    .log-row [style*="overflow-y: auto"]::-webkit-scrollbar-thumb:hover {
        background: #585b70;
    }
    .log-row [style*="overflow-y: scroll"],
    .log-row [style*="overflow-y: auto"] {
        scrollbar-width: thin;
        scrollbar-color: #45475a #181825;
    }

    /* Ikony w kartach – ten sam kolor co w menu */
    .icon-muted,
    i.icon-muted,
    i.icon-muted::before {
        color: var(--icon-muted) !important;
    }
</style>
@endpush