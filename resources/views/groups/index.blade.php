@extends('layouts.app')

@section('title', 'Zarządzanie grupami systemowymi')
@section('header-title', 'Zarządzanie grupami systemowymi')

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
                    <input type="text" id="systemGroupSearchInput" class="form-control form-control-sm border-start-0 ps-0" placeholder="Filtruj po nazwie grupy / GID / członkach...">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-layer-group me-2 icon-muted"></i>Grupy systemowe</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
                    <i class="fa-solid fa-plus me-1"></i> Dodaj grupę
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="systemGroupsTable" class="table table-sm table-hover table-condensed align-middle mb-0 tablesorter">
                        <thead>
                            <tr>
                                <th scope="col" class="ps-3">
                                    <div class="d-flex align-items-center">Nazwa&nbsp;&nbsp;<i class="fa-solid fa-sort"></i></div>
                                </th>
                                <th scope="col">
                                    <div class="d-flex align-items-center">GID&nbsp;&nbsp;<i class="fa-solid fa-sort"></i></div>
                                </th>
                                <th scope="col" class="sorter-false">Członkowie</th>
                                <th scope="col" class="sorter-false text-end pe-3">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($groups as $group)
                                <tr>
                                    <td class="ps-3"><strong>{{ $group['name'] }}</strong></td>
                                    <td>{{ $group['gid'] }}</td>
                                    <td>
                                        @if(count($group['members']) > 0)
                                            {{ implode(', ', $group['members']) }}
                                        @else
                                            <span class="text-muted">brak</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-flex justify-content-end gap-1">
                                            <!-- Przycisk EDYTUJ – otwiera modal z pełną edycją -->
                                            <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editGroupModal" 
                                                    data-groupname="{{ $group['name'] }}"
                                                    data-gid="{{ $group['gid'] }}"
                                                    data-members="{{ implode(',', $group['members']) }}"
                                                    title="Edytuj grupę">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>

                                            <form action="{{ route('system.groups.delete', $group['name']) }}" method="POST" class="d-inline" onsubmit="return confirm('Czy na pewno chcesz usunąć grupę {{ $group['name'] }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger py-1 px-2 border-0" title="Usuń grupę">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Brak grup systemowych.</td>
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
<!-- MODAL: Dodaj grupę                                           -->
<!-- ============================================================ -->
<div class="modal fade" id="addGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Dodaj nową grupę</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('system.groups.add') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nazwa grupy</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary">Dodaj</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Edytuj grupę (pełna edycja)                            -->
<!-- ============================================================ -->
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edytuj grupę: <span id="editGroupName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- 1. Zmiana nazwy grupy -->
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0">Zmień nazwę grupy</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="renameGroupForm">
                            @csrf
                            <div class="row g-2">
                                <div class="col">
                                    <input type="text" name="new_name" class="form-control form-control-sm" placeholder="Nowa nazwa" required>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-sm btn-primary">Zmień nazwę</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 2. Członkowie grupy -->
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0">Członkowie grupy</h6>
                    </div>
                    <div class="card-body">
                        <div id="editGroupMembersList">
                            <span class="text-muted">Ładowanie...</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Dodaj użytkownika do grupy -->
                <div class="card">
                    <div class="card-header py-2">
                        <h6 class="mb-0">Dodaj użytkownika do grupy</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="addUserToGroupForm">
                            @csrf
                            <input type="hidden" name="group" id="editGroupNameHidden">
                            <div class="row g-2">
                                <div class="col">
                                    <select name="username" class="form-select form-select-sm" id="editAvailableUsers" required>
                                        <option value="">-- wybierz użytkownika --</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-sm btn-primary">Dodaj</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    /* Tabela – ujednolicona z layoutem */
    #systemGroupsTable thead th {
        cursor: pointer;
        white-space: nowrap;
        font-size: 0.75rem;
        color: var(--text-muted);
        font-weight: var(--font-weight-medium);
        padding: 0.55rem 0.75rem;
        border-bottom: 1px solid var(--border-soft-2);
        vertical-align: middle;
    }
    #systemGroupsTable thead th > div {
        display: flex;
        align-items: center;
    }
    #systemGroupsTable thead th i {
        font-size: 0.7rem !important;
        opacity: 0.5;
        flex-shrink: 0;
    }
    #systemGroupsTable thead th i.fa-sort-up,
    #systemGroupsTable thead th i.fa-sort-down {
        opacity: 1;
        color: var(--text-main);
    }
    #systemGroupsTable tbody td {
        font-size: 0.8rem;
        padding: 0.55rem 0.75rem;
        vertical-align: middle;
    }
    #systemGroupsTable tbody tr {
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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.31.3/js/jquery.tablesorter.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // ============================================================
        // MODAL: Edytuj grupę
        // ============================================================
        const editModal = document.getElementById('editGroupModal');

        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const groupName = button.getAttribute('data-groupname');
            const gid = button.getAttribute('data-gid');
            const members = button.getAttribute('data-members') ? button.getAttribute('data-members').split(',') : [];

            document.getElementById('editGroupName').textContent = groupName;
            document.getElementById('editGroupNameHidden').value = groupName;

            document.getElementById('renameGroupForm').action = '/system-groups/rename/' + groupName;
            document.getElementById('addUserToGroupForm').action = '{{ route("system.users.addToGroup") }}';

            // Członkowie grupy
            const membersContainer = document.getElementById('editGroupMembersList');

            if (members.length > 0 && members[0] !== '') {
                let html = '<ul class="list-unstyled mb-0">';
                members.forEach(function(username) {
                    html += `
                        <li class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <span>${username}</span>
                            <form action="{{ route('system.users.removeFromGroup') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="username" value="${username}">
                                <input type="hidden" name="group" value="${groupName}">
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" 
                                        onclick="return confirm('Usunąć użytkownika ${username} z grupy ${groupName}?');">
                                    ×
                                </button>
                            </form>
                        </li>
                    `;
                });
                html += '</ul>';
                membersContainer.innerHTML = html;
            } else {
                membersContainer.innerHTML = '<span class="text-muted">Brak członków w tej grupie.</span>';
            }

            // Dostępni użytkownicy
            const userSelect = document.getElementById('editAvailableUsers');
            userSelect.innerHTML = '<option value="">-- wybierz użytkownika --</option>';

            fetch('/system-users-json')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Błąd pobierania użytkowników:', data.error);
                        return;
                    }

                    const availableUsers = data.filter(function(user) {
                        return !members.includes(user.username);
                    });

                    if (availableUsers.length === 0) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = '-- brak dostępnych użytkowników --';
                        userSelect.appendChild(opt);
                    } else {
                        availableUsers.forEach(function(user) {
                            const opt = document.createElement('option');
                            opt.value = user.username;
                            opt.textContent = user.username + ' (UID: ' + user.uid + ')';
                            userSelect.appendChild(opt);
                        });
                    }
                })
                .catch(error => {
                    console.error('Błąd AJAX:', error);
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = '-- błąd ładowania --';
                    userSelect.appendChild(opt);
                });
        });

        // Odświeżanie po zamknięciu
        const addGroupModal = document.getElementById('addGroupModal');
        addGroupModal.addEventListener('hidden.bs.modal', function() {
            location.reload();
        });

        editModal.addEventListener('hidden.bs.modal', function() {
            location.reload();
        });

    });

    $(document).ready(function() {
        // Inicjalizacja Tablesorter
        $("#systemGroupsTable").tablesorter({
            headerTemplate: '',
            headers: {
                1: { sorter: 'digit' }, // GID jako liczba
                2: { sorter: false },   // Członkowie
                3: { sorter: false }    // Akcje
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

        // Wyszukiwanie w tabeli
        $("#systemGroupSearchInput").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#systemGroupsTable tbody tr").filter(function() {
                var rowText = $(this).text().toLowerCase();
                $(this).toggle(rowText.indexOf(value) > -1);
            });
        });
    });
</script>
@endpush