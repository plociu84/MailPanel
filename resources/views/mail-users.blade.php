@extends('layouts.app')

@section('title', 'Użytkownicy Poczty Roundcube')
@section('header-title', 'Zarządzanie Użytkownikami Poczty')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-semibold text-heading mb-1">Użytkownicy poczty (Roundcube)</h5>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">Pełna baza użytkowników w systemie (Łącznie: <strong>{{ count($users) }}</strong>)</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fa-solid fa-user-plus me-1"></i> Dodaj użytkownika
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
        </div>
    @endif

    @if(session('server_log'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-terminal me-2"></i> <strong>Log serwera:</strong>
            <pre class="mb-0 mt-2">{{ session('server_log') }}</pre>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
        </div>
    @endif
    
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="userSearchInput" class="form-control form-control-sm border-start-0 ps-0" placeholder="Filtruj po nazwie użytkownika / emailu / imieniu...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="mailUsersTable" class="table table-hover table-condensed align-middle mb-0 tablesorter">
                    <thead>
                        <tr>
                            <th scope="col" class="ps-4">
                                <div class="d-flex align-items-center">LP&nbsp;&nbsp;<i class="fa-solid fa-sort"></i></div>
                            </th>
                            @foreach($columns as $col)
                                <th scope="col">
                                    <div class="d-flex align-items-center">{{ $col }}&nbsp;&nbsp;<i class="fa-solid fa-sort"></i></div>
                                </th>
                            @endforeach
                            <th scope="col" class="sorter-false text-end pe-4">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $index => $row)
                        <tr>
                            <td class="ps-4 fw-semibold text-muted">{{ $index + 1 }}</td>
                            
                            @php
                                $rowArr       = (array) $row;
                                $values       = array_values($rowArr);
                                
                                $userId       = $rowArr['user_id'] ?? ($rowArr['id'] ?? ($values[0] ?? ''));
                                $username     = $rowArr['username'] ?? ($rowArr['login'] ?? ($values[1] ?? ''));
                                $fullName     = $rowArr['name'] ?? ($rowArr['imie'] ?? ($values[2] ?? ''));
                                $primaryEmail = $rowArr['email'] ?? ($rowArr['mail'] ?? ($values[3] ?? ''));
                                $mailHost     = $rowArr['mail_host'] ?? ($values[4] ?? 'localhost');
                            @endphp

                            @foreach($columns as $colIndex => $colName)
                                @php
                                    $cellValue = $rowArr[$colName] ?? ($values[$colIndex] ?? '');
                                @endphp

                                @if(strtolower($colName) === 'preferences')
                                    <td class="pref-cell">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-truncate" style="max-width: 170px;">{{ $cellValue }}</span>
                                            <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 border-0 flex-shrink-0" 
                                                    onclick="showPreferencesModal('{{ addslashes($cellValue) }}')"
                                                    title="Pokaż preferences">
                                                <i class="fa-solid fa-up-right-and-down-left-from-center"></i>
                                            </button>
                                        </div>
                                    </td>
                                @else
                                    <td>{{ $cellValue }}</td>
                                @endif
                            @endforeach

                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 border-0" 
                                            onclick="openEditUserModal('{{ $userId }}', '{{ addslashes($username) }}', '{{ addslashes($fullName) }}', '{{ addslashes($primaryEmail) }}', '{{ addslashes($mailHost) }}')"
                                            title="Edytuj">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    
                                    <form action="/mail-users/{{ $userId }}" method="POST" class="d-inline" onsubmit="return confirm('Czy na pewno chcesz usunąć użytkownika {{ $username }}? Zostanie usunięty z bazy Roundcube, Postfixa i konta Linux.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2 border-0" title="Usuń">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Preferences -->
<div class="modal fade" id="prefModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold fs-6">Zawartość pola preferences</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body bg-dark">
        <pre id="prefModalContent" class="text-light p-3 rounded mb-0" style="white-space: pre-wrap; word-break: break-all; max-height: 60vh; overflow-y: auto; font-size: 0.85rem;"></pre>
      </div>
    </div>
  </div>
</div>

<!-- Modal Wyników Operacji -->
<div class="modal fade" id="logModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fs-6"><i class="fa-solid fa-terminal me-2"></i> Raport z wykonania na serwerze</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body bg-dark text-light">
        <pre id="logContent" class="mb-0" style="white-space: pre-wrap; word-break: break-all; max-height: 60vh; overflow-y: auto; font-family: monospace; font-size: 0.85rem;"></pre>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-primary btn-sm px-3" data-bs-dismiss="modal">Zamknij i odśwież</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Dodawania Użytkownika -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form action="/mail-users" method="POST">
          @csrf
          <div class="modal-header bg-light">
            <h5 class="modal-title fw-bold fs-6">Dodaj pełne konto pocztowe (Linux + Cyrus + Postfix + Roundcube)</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
          </div>
          <div class="modal-body">
            <div class="row mb-3">
                <div class="col">
                    <label for="login" class="form-label text-muted small mb-1">Login w systemie (np. jkowalski)</label>
                    <input type="text" class="form-control form-control-sm" id="login" name="login" required>
                </div>
                <div class="col">
                    <label for="mail_host" class="form-label text-muted small mb-1">Host pocztowy</label>
                    <input type="text" class="form-control form-control-sm" id="mail_host" name="mail_host" value="localhost" required>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col">
                    <label for="imie" class="form-label text-muted small mb-1">Imię</label>
                    <input type="text" class="form-control form-control-sm" id="imie" name="imie" required>
                </div>
                <div class="col">
                    <label for="nazwisko" class="form-label text-muted small mb-1">Nazwisko</label>
                    <input type="text" class="form-control form-control-sm" id="nazwisko" name="nazwisko" required>
                </div>
            </div>
            <div class="mb-3">
                <label for="email_local_part" class="form-label text-muted small mb-1">Przedrostek e-mail</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="email_local_part" name="email_local_part" required>
                    <span class="input-group-text bg-light text-muted">@telmor.pl / .com.pl</span>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label text-muted small mb-1">Hasło użytkownika:</label>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control font-monospace" id="password" name="password" required>
                    <button type="button" class="btn btn-outline-secondary" onclick="generateRandomPassword('password', 'pwContainer')"><i class="fa-solid fa-rotate"></i> Losuj nowe</button>
                </div>
                <div id="pwContainer" class="d-flex gap-2 flex-wrap"></div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
            <button type="submit" class="btn btn-primary btn-sm px-3">Utwórz kompletne konto</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Edycji Użytkownika -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form id="editUserForm" method="POST">
          @csrf
          @method('PUT')
          <div class="modal-header bg-light">
            <h5 class="modal-title fw-bold fs-6">Edytuj użytkownika & Zmień hasło</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
                <label class="form-label text-muted small mb-1">Login (username) <span class="text-muted">– tylko do odczytu</span></label>
                <input type="text" class="form-control form-control-sm bg-light" id="edit_login" disabled>
                <input type="hidden" name="login" id="edit_login_hidden">
            </div>
            <div class="row mb-3">
                <div class="col">
                    <label for="edit_imie" class="form-label text-muted small mb-1">Imię</label>
                    <input type="text" class="form-control form-control-sm" id="edit_imie" name="imie" required>
                </div>
                <div class="col">
                    <label for="edit_nazwisko" class="form-label text-muted small mb-1">Nazwisko</label>
                    <input type="text" class="form-control form-control-sm" id="edit_nazwisko" name="nazwisko" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small mb-1">E-mail główny (@telmor.pl) <span class="text-muted">– tylko do odczytu</span></label>
                <input type="email" class="form-control form-control-sm bg-light" id="edit_email_pl" disabled>
                <input type="hidden" name="email_pl" id="edit_email_pl_hidden">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small mb-1">E-mail dodatkowy (@telmor.com.pl) <span class="text-muted">– tylko do odczytu</span></label>
                <input type="email" class="form-control form-control-sm bg-light" id="edit_email_com_pl" disabled>
                <input type="hidden" name="email_com_pl" id="edit_email_com_pl_hidden">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small mb-1">Host pocztowy <span class="text-muted">– tylko do odczytu</span></label>
                <input type="text" class="form-control form-control-sm bg-light" id="edit_mail_host" disabled>
                <input type="hidden" name="mail_host" id="edit_mail_host_hidden">
            </div>
            <div class="mb-2 border-top pt-3 bg-light p-3 rounded">
                <label class="form-label fw-bold text-dark small mb-1"><i class="fa-solid fa-key text-warning me-1"></i> Zmiana / Reset hasła systemowego:</label>
                <div class="text-muted mb-2" style="font-size: 0.75rem;">
                    Pozostaw puste, aby nie zmieniać obecnego hasła.
                </div>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control font-monospace" id="edit_password" name="password" placeholder="Nowe hasło">
                    <button type="button" class="btn btn-outline-secondary" onclick="generateRandomPassword('edit_password', 'editPwContainer')"><i class="fa-solid fa-rotate"></i> Losuj nowe</button>
                </div>
                <div id="editPwContainer" class="d-flex gap-2 flex-wrap"></div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
            <button type="submit" class="btn btn-primary btn-sm px-3">Zapisz zmiany</button>
          </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
    /* Tabela condensed */
    #mailUsersTable thead th {
        cursor: pointer;
        white-space: nowrap;
        font-size: 0.75rem;
        color: var(--text-muted);
        font-weight: var(--font-weight-medium);
        padding: 0.55rem 0.75rem;
        border-bottom: 1px solid var(--border-soft-2);
        vertical-align: middle;
    }
    #mailUsersTable thead th > div {
        display: flex;
        align-items: center;
    }
    #mailUsersTable thead th i {
        font-size: 0.7rem !important;
        opacity: 0.5;
        flex-shrink: 0;
    }
    #mailUsersTable thead th i.fa-sort-up,
    #mailUsersTable thead th i.fa-sort-down {
        opacity: 1;
        color: var(--text-main);
    }
    #mailUsersTable tbody td {
        font-size: 0.8rem;
        padding: 0.55rem 0.75rem;
        vertical-align: middle;
    }
    #mailUsersTable tbody tr {
        line-height: 1.4;
    }
    .pref-cell {
        max-width: 280px;
    }
</style>
@endpush

@push('scripts')
<!-- Ładowanie biblioteki jQuery wymaganej przez Tablesorter (lokalnie) -->
<script src="{{ asset('assets/jquery/jquery-3.7.1.min.js') }}"></script>
<!-- Wtyczka Tablesorter (lokalnie) -->
<script src="{{ asset('assets/tablesorter/jquery.tablesorter.min.js') }}"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let addUserModalEl = document.getElementById('addUserModal');
        if (addUserModalEl) {
            addUserModalEl.addEventListener('show.bs.modal', function () {
                generateRandomPassword('password', 'pwContainer');
            });
        }

        @if(session('server_log'))
            let logText = @json(session('server_log'));
            document.getElementById('logContent').innerText = logText;
            var logModal = new bootstrap.Modal(document.getElementById('logModal'));
            logModal.show();
        @endif
    });

    $(document).ready(function() {
        var lastColIndex = $("#mailUsersTable thead th").length - 1;
        var headersConfig = {};
        headersConfig[lastColIndex] = { sorter: false };

        $("#mailUsersTable").tablesorter({
            headerTemplate: '',
            headers: headersConfig
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

        $("#userSearchInput").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#mailUsersTable tbody tr").filter(function() {
                var rowText = $(this).text().toLowerCase();
                $(this).toggle(rowText.indexOf(value) > -1);
            });
        });
    });
</script>
@endpush