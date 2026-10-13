@extends('layouts.app')

@section('title', 'Aliasy pocztowe Postfix')
@section('header-title', 'Zarządzanie Aliasami (valiastable)')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-semibold text-heading mb-1">Aliasy pocztowe (valiastable)</h5>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">Łącznie aliasów: <strong>{{ count($aliases) }}</strong></p>
        </div>
        <button type="button" class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#addAliasModal">
            <i class="fa-solid fa-plus me-1"></i> Dodaj alias
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
                        <input type="text" id="aliasSearchInput" class="form-control form-control-sm border-start-0 ps-0" placeholder="Filtruj po aliasie lub odbiorcy...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="aliasesTable" class="table table-hover table-condensed align-middle mb-0 tablesorter">
                    <thead>
                        <tr>
                            <th scope="col" class="ps-4">
                                <div class="d-flex align-items-center">LP&nbsp;&nbsp;<i class="fa-solid fa-sort"></i></div>
                            </th>
                            <th scope="col">
                                <div class="d-flex align-items-center">Alias / Nadawca&nbsp;&nbsp;<i class="fa-solid fa-sort"></i></div>
                            </th>
                            <th scope="col">
                                <div class="d-flex align-items-center">Cel / Odbiorca&nbsp;&nbsp;<i class="fa-solid fa-sort"></i></div>
                            </th>
                            <th scope="col" class="sorter-false text-end pe-4">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($aliases as $index => $item)
                        <tr>
                            <td class="ps-4 fw-semibold text-muted" style="width: 70px;">{{ $index + 1 }}</td>
                            <td><strong class="text-dark">{{ $item['alias'] }}</strong></td>
                            <td><span class="font-monospace text-break">{{ $item['destination'] }}</span></td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 border-0" 
                                            onclick="openEditAliasModal('{{ addslashes($item['alias']) }}', '{{ addslashes($item['destination']) }}')"
                                            title="Edytuj">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <form action="{{ route('aliases.destroy') }}" method="POST" class="d-inline" onsubmit="return confirm('Czy na pewno chcesz usunąć alias {{ $item['alias'] }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="alias" value="{{ $item['alias'] }}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2 border-0" title="Usuń">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3">Brak wpisów w pliku valiastable.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Dodawania Aliasu -->
<div class="modal fade" id="addAliasModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form action="{{ route('aliases.store') }}" method="POST">
          @csrf
          <div class="modal-header bg-light">
            <h5 class="modal-title fw-bold fs-6">Dodaj nowy alias</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
                <label for="alias" class="form-label text-muted small mb-1">Alias / Adres źródłowy (np. handlowy lub biuro@telmor.pl)</label>
                <input type="text" class="form-control form-control-sm" id="alias" name="alias" required>
            </div>
            <div class="mb-3">
                <label for="destination" class="form-label text-muted small mb-1">Cel / Odbiorca (np. jan.kowalski lub e-maile rozdzielone przecinkiem)</label>
                <input type="text" class="form-control form-control-sm" id="destination" name="destination" required>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
            <button type="submit" class="btn btn-primary btn-sm px-3">Dodaj alias</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Edycji Aliasu -->
<div class="modal fade" id="editAliasModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form action="{{ route('aliases.update') }}" method="POST">
          @csrf
          @method('PUT')
          <input type="hidden" name="old_alias" id="edit_old_alias">
          <div class="modal-header bg-light">
            <h5 class="modal-title fw-bold fs-6">Edytuj alias</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
                <label for="edit_alias" class="form-label text-muted small mb-1">Alias / Adres źródłowy</label>
                <input type="text" class="form-control form-control-sm" id="edit_alias" name="alias" required>
            </div>
            <div class="mb-3">
                <label for="edit_destination" class="form-label text-muted small mb-1">Cel / Odbiorca</label>
                <input type="text" class="form-control form-control-sm" id="edit_destination" name="destination" required>
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
    /* Tabela – ujednolicona z layoutem */
    #aliasesTable thead th {
        cursor: pointer;
        white-space: nowrap;
        font-size: 0.75rem;
        color: var(--text-muted);
        font-weight: var(--font-weight-medium);
        padding: 0.55rem 0.75rem;
        border-bottom: 1px solid var(--border-soft-2);
        vertical-align: middle;
    }
    #aliasesTable thead th > div {
        display: flex;
        align-items: center;
    }
    #aliasesTable thead th i {
        font-size: 0.7rem !important;
        opacity: 0.5;
        flex-shrink: 0;
    }
    #aliasesTable thead th i.fa-sort-up,
    #aliasesTable thead th i.fa-sort-down {
        opacity: 1;
        color: var(--text-main);
    }
    #aliasesTable tbody td {
        font-size: 0.8rem;
        padding: 0.55rem 0.75rem;
        vertical-align: middle;
    }
    #aliasesTable tbody tr {
        line-height: 1.4;
    }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.31.3/js/jquery.tablesorter.min.js"></script>

<script>
    function openEditAliasModal(alias, destination) {
        document.getElementById('edit_old_alias').value = alias;
        document.getElementById('edit_alias').value = alias;
        document.getElementById('edit_destination').value = destination;

        var editModal = new bootstrap.Modal(document.getElementById('editAliasModal'));
        editModal.show();
    }

    $(document).ready(function() {
        $("#aliasesTable").tablesorter({
            headerTemplate: '',
            headers: {
                0: { sorter: 'digit' },
                3: { sorter: false }
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

        $("#aliasSearchInput").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#aliasesTable tbody tr").filter(function() {
                var rowText = $(this).text().toLowerCase();
                $(this).toggle(rowText.indexOf(value) > -1);
            });
        });
    });
</script>
@endpush