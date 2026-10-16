@extends('layouts.app')

@section('title', 'Zarządzanie użytkownikami systemowymi')
@section('header-title', 'Zarządzanie użytkownikami systemowymi')

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card mb-4">
    <div class="card-body py-3">
        <div class="row align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="systemUserSearchInput" class="form-control form-control-sm border-start-0 ps-0" placeholder="Filtruj po użytkowniku / UID / GECOS / powłoce...">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-transparent py-3">
                <h5 class="mb-0"><i class="fa-solid fa-users me-2 icon-muted"></i>Użytkownicy systemowi</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="systemUsersTable" class="table table-sm table-hover table-condensed align-middle mb-0 tablesorter">
                        <thead>
                            <tr>
                                <th scope="col" class="ps-3">
                                    <div class="d-flex align-items-center">Użytkownik&nbsp;&nbsp;<i class="fa-solid fa-sort"></i></div>
                                </th>
                                <th scope="col">
                                    <div class="d-flex align-items-center">UID&nbsp;&nbsp;<i class="fa-solid fa-sort"></i></div>
                                </th>
                                <th scope="col">
                                    <div class="d-flex align-items-center">Nazwa / GECOS&nbsp;&nbsp;<i class="fa-solid fa-sort"></i></div>
                                </th>
                                <th scope="col">
                                    <div class="d-flex align-items-center">Powłoka&nbsp;&nbsp;<i class="fa-solid fa-sort"></i></div>
                                </th>
                                <th scope="col">
                                    <div class="d-flex align-items-center">Status&nbsp;&nbsp;<i class="fa-solid fa-sort"></i></div>
                                </th>
                                <th scope="col">
                                    <div class="d-flex align-items-center">Wygaśnięcie&nbsp;&nbsp;<i class="fa-solid fa-sort"></i></div>
                                </th>
                                <th scope="col" class="sorter-false">Grupy</th>
                                <th scope="col" class="sorter-false text-end pe-3">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                                @php
                                    $userGroups = [];
                                    foreach($groups as $g) {
                                        if(in_array($user['username'], $g['members'])) {
                                            $userGroups[] = $g;
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td class="ps-3"><strong>{{ $user['username'] }}</strong></td>
                                    <td>{{ $user['uid'] }}</td>
                                    <td>{{ $user['gecos'] ?: '-' }}</td>
                                    <td><span class="font-monospace">{{ $user['shell'] ?: '-' }}</span></td>
                                    <td>
                                        @if($user['status']['locked'])
                                            <span class="badge bg-danger">Zablokowane</span>
                                        @else
                                            <span class="badge bg-success">Aktywne</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($user['status']['expires'])
                                            @php
                                                $expireDate = \Carbon\Carbon::parse($user['status']['expires']);
                                                $daysLeft = ceil(now()->startOfDay()->diffInDays($expireDate->startOfDay(), false));
                                            @endphp
                                            <span class="badge bg-warning text-dark" title="Data wygaśnięcia: {{ $expireDate->format('d.m.Y') }}">
                                                {{ $expireDate->format('d.m.Y') }}
                                                @if($daysLeft > 0)
                                                    <span class="text-muted">({{ $daysLeft }} dni)</span>
                                                @elseif($daysLeft == 0)
                                                    <span class="text-danger fw-bold">(DZIŚ!)</span>
                                                @else
                                                    <span class="text-danger fw-bold">(WYGASŁO!)</span>
                                                @endif
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">Brak</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(count($userGroups) > 0)
                                            @foreach($userGroups as $group)
                                                <span class="badge bg-primary" style="display: inline-flex; align-items: center; gap: 4px; margin: 2px;">
                                                    {{ $group['name'] }}
                                                    <form action="{{ route('system.users.removeFromGroup') }}" method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Usunąć użytkownika {{ $user['username'] }} z grupy {{ $group['name'] }}?');">
                                                        @csrf
                                                        <input type="hidden" name="username" value="{{ $user['username'] }}">
                                                        <input type="hidden" name="group" value="{{ $group['name'] }}">
                                                        <button type="submit" class="btn btn-sm p-0 text-white" 
                                                                style="background: none; border: none; font-size: 14px; line-height: 1;" 
                                                                title="Usuń z grupy">×</button>
                                                    </form>
                                                </span>
                                            @endforeach
                                        @else
                                            <span class="text-muted">brak</span>
                                        @endif

                                        <button class="btn btn-sm btn-outline-primary py-0 px-1 ms-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#addToGroupModal" 
                                                data-username="{{ $user['username'] }}"
                                                title="Dodaj do grupy">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-flex justify-content-end gap-1">
                                            <form action="{{ route('system.users.lock', $user['username']) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" title="Zablokuj konto">
                                                    <i class="fa-solid fa-lock"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('system.users.unlock', $user['username']) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" title="Odblokuj konto">
                                                    <i class="fa-solid fa-lock-open"></i>
                                                </button>
                                            </form>
                                            <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#expireModal" 
                                                    data-username="{{ $user['username'] }}"
                                                    title="Ustaw datę wygaśnięcia">
                                                <i class="fa-solid fa-calendar"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-3">Brak użytkowników systemowych.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Ustaw datę wygaśnięcia                                -->
<!-- ============================================================ -->
<div class="modal fade" id="expireModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ustaw datę wygaśnięcia konta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="expireForm">
                @csrf
                <div class="modal-body">
                    <p>Użytkownik: <strong id="expireUsername"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Data wygaśnięcia</label>
                        <input type="date" name="expire_date" id="expireDateInput" class="form-control">
                        <small class="text-muted">Wybierz datę lub kliknij "Usuń wygaśnięcie" aby usunąć.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="removeExpireBtn">Usuń wygaśnięcie</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary">Zapisz</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Dodaj użytkownika do grupy                            -->
<!-- ============================================================ -->
<div class="modal fade" id="addToGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Dodaj użytkownika do grupy</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('system.users.addToGroup') }}">
                @csrf
                <div class="modal-body">
                    <p class="mb-1">Użytkownik: <strong id="addToGroupUsername"></strong></p>
                    <input type="hidden" name="username" id="addToGroupUsernameInput">
                    <div class="mb-2">
                        <label class="form-label">Wybierz grupę</label>
                        <select name="group" class="form-select" required>
                            <option value="">-- wybierz --</option>
                            @foreach($groups as $group)
                                <option value="{{ $group['name'] }}">{{ $group['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-sm btn-primary">Dodaj</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    /* Tabela – ujednolicona z layoutem */
    #systemUsersTable thead th {
        cursor: pointer;
        white-space: nowrap;
        font-size: 0.75rem;
        color: var(--text-muted);
        font-weight: var(--font-weight-medium);
        padding: 0.55rem 0.75rem;
        border-bottom: 1px solid var(--border-soft-2);
        vertical-align: middle;
    }
    #systemUsersTable thead th > div {
        display: flex;
        align-items: center;
    }
    #systemUsersTable thead th i {
        font-size: 0.7rem !important;
        opacity: 0.5;
        flex-shrink: 0;
    }
    #systemUsersTable thead th i.fa-sort-up,
    #systemUsersTable thead th i.fa-sort-down {
        opacity: 1;
        color: var(--text-main);
    }
    #systemUsersTable tbody td {
        font-size: 0.8rem;
        padding: 0.55rem 0.75rem;
        vertical-align: middle;
    }
    #systemUsersTable tbody tr {
        line-height: 1.4;
    }

    /* Ikony w kartach – ten sam kolor co w menu */
    .icon-muted,
    i.icon-muted,
    i.icon-muted::before {
        color: var(--icon-muted) !important;
    }
</style>
@endpush

@push('scripts')
<!-- Ładowanie jQuery i Tablesorter -->
<script src="{{ asset('assets/jquery/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('assets/tablesorter/jquery.tablesorter.min.js') }}"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // ============================================================
        // MODAL: Ustaw datę wygaśnięcia
        // ============================================================
        const expireModal = document.getElementById('expireModal');
        expireModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const username = button.getAttribute('data-username');
            const form = document.getElementById('expireForm');
            
            const oldHidden = form.querySelector('input[name="expire_date"][type="hidden"]');
            if (oldHidden) oldHidden.remove();
            
            form.action = '/system-users/expire/' + username;
            document.getElementById('expireUsername').textContent = username;
            document.getElementById('expireDateInput').value = '';
        });

        // ============================================================
        // PRZYCISK: Usuń wygaśnięcie
        // ============================================================
        document.getElementById('removeExpireBtn').addEventListener('click', function() {
            const username = document.getElementById('expireUsername').textContent;
            if (confirm('Czy na pewno chcesz usunąć datę wygaśnięcia dla użytkownika ' + username + '?')) {
                const form = document.getElementById('expireForm');
                const oldHidden = form.querySelector('input[name="expire_date"][type="hidden"]');
                if (oldHidden) oldHidden.remove();
                
                let input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'expire_date';
                input.value = '-1';
                form.appendChild(input);
                form.submit();
            }
        });

        // ============================================================
        // MODAL: Dodaj do grupy
        // ============================================================
        const addModal = document.getElementById('addToGroupModal');
        addModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const username = button.getAttribute('data-username');
            document.getElementById('addToGroupUsername').textContent = username;
            document.getElementById('addToGroupUsernameInput').value = username;
        });

    });

    $(document).ready(function() {
        // Inicjalizacja Tablesorter z wyłączeniem kolumn Grupy i Akcje
        $("#systemUsersTable").tablesorter({
            headerTemplate: '',
            headers: {
                1: { sorter: 'digit' }, // UID
                6: { sorter: false },   // Grupy
                7: { sorter: false }    // Akcje
            }
        }).bind('sortEnd', function(e, table) {
            $(table).find('th i').attr('class', 'fa-solid fa-sort');
            var sortList = table.config.sortList;
            if (sortList.length > 0) {
                let colIndex = sortList[0][0];
                let order = sortList[0][1];
                let iconClass = (order === 0) ? 'fa-solid fa-sort-up' : 'fa-solid fa-sort-down';
                $(table).find('th').eq(colIndex).find('i').attr('class', iconClass);
            }
        });

        // Wyszukiwanie / filtrowanie w tabeli
        $("#systemUserSearchInput").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#systemUsersTable tbody tr").filter(function() {
                var rowText = $(this).text().toLowerCase();
                $(this).toggle(rowText.indexOf(value) > -1);
            });
        });
    });
</script>
@endpush