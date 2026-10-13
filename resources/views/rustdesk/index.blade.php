@extends('layouts.app')

@section('header-title', 'Ewidencja RustDesk')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Przycisk dodawania w prawym górnym rogu -->
    <div class="d-flex justify-content-between align-items-center my-3">
        <h1 class="h4 mb-0 text-gray-800">
            <i class="fa-solid fa-desktop text-primary me-2"></i>Ewidencja RustDesk
        </h1>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
            <i class="fa-solid fa-plus me-1"></i> Dodaj komputer
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show my-2" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Pasek filtra / wyszukiwarki -->
    <div class="card shadow-sm border-0 my-3">
        <div class="card-body py-2 px-3">
            <div class="row align-items-center">
                <div class="col-md-4 col-sm-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" id="rustdeskSearchInput" class="form-control border-start-0 ps-0" placeholder="Szukaj użytkownika, komputera, ID..." onkeyup="filterRustdeskTable()">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela bez pasów (czysto biała) z możliwością sortowania -->
    <div class="card shadow-sm border-0 my-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="rustdeskTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px; cursor: pointer;" onclick="sortTable(0)">
                                LP <span class="sort-icon small text-muted">↕</span>
                            </th>
                            <th style="cursor: pointer;" onclick="sortTable(1)">
                                Użytkownik <span class="sort-icon small text-muted">↕</span>
                            </th>
                            <th style="cursor: pointer;" onclick="sortTable(2)">
                                Nr Komputera <span class="sort-icon small text-muted">↕</span>
                            </th>
                            <th style="cursor: pointer;" onclick="sortTable(3)">
                                ID RustDesk <span class="sort-icon small text-muted">↕</span>
                            </th>
                            <th style="cursor: pointer;" onclick="sortTable(4)">
                                Uwagi <span class="sort-icon small text-muted">↕</span>
                            </th>
                            <th class="text-end" style="width: 100px;">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($devices as $index => $device)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $device->user_name }}</td>
                                <td>{{ $device->computer_number }}</td>
                                <td>
                                    <div class="d-inline-flex align-items-center gap-2">
                                        <span>{{ $device->rustdesk_id }}</span>
                                        <button type="button" 
                                                class="btn btn-sm btn-light border py-0 px-1" 
                                                onclick="copyToClipboard('{{ $device->rustdesk_id }}', this)" 
                                                title="Kopiuj ID do schowka">
                                            <i class="fa-regular fa-copy text-primary"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>{{ $device->notes ?: '-' }}</td>
                                <td class="text-end">
                                    <button type="button" 
                                            class="btn btn-link btn-sm text-primary p-0 me-2" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal{{ $device->id }}"
                                            title="Edytuj">
                                        <i class="fa-solid fa-pen-to-square fs-6"></i>
                                    </button>
                                    <form action="{{ route('rustdesk.destroy', $device) }}" method="POST" class="d-inline" onsubmit="return confirm('Czy na pewno usunąć ten wpis?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm text-danger p-0" title="Usuń">
                                            <i class="fa-solid fa-trash fs-6"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr id="noDataRow">
                                <td colspan="6" class="text-center text-muted py-4">Brak wpisów w ewidencji.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modale Edycji -->
@foreach($devices as $device)
    <div class="modal fade" id="editModal{{ $device->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('rustdesk.update', $device) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edycja wpisu: {{ $device->user_name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Użytkownik</label>
                            <input type="text" name="user_name" class="form-control" value="{{ $device->user_name }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nr Komputera</label>
                            <input type="text" name="computer_number" class="form-control" value="{{ $device->computer_number }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ID RustDesk</label>
                            <input type="text" name="rustdesk_id" class="form-control" value="{{ $device->rustdesk_id }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Uwagi</label>
                            <textarea name="notes" class="form-control" rows="2">{{ $device->notes }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-primary btn-sm">Zapisz zmiany</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

<!-- Modal Dodawania -->
<div class="modal fade" id="addDeviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('rustdesk.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Dodaj komputer RustDesk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Użytkownik</label>
                        <input type="text" name="user_name" class="form-control" placeholder="np. Jan Kowalski" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nr Komputera</label>
                        <input type="text" name="computer_number" class="form-control" placeholder="np. 491-601" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ID RustDesk</label>
                        <input type="text" name="rustdesk_id" class="form-control" placeholder="np. 443 710 821" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Uwagi</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Opcjonalne uwagi..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary btn-sm">Zapisz</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Skrypt filtrowania tabeli
function filterRustdeskTable() {
    const filter = document.getElementById('rustdeskSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#rustdeskTable tbody tr:not(#noDataRow)');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

// Skrypt sortowania tabeli z poprawną obsługą numerów z myślnikami i przeliczaniem LP
let sortDirections = {};
function sortTable(colIndex) {
    const table = document.getElementById('rustdeskTable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr:not(#noDataRow)'));

    const currentDirection = sortDirections[colIndex] === 'asc' ? 'desc' : 'asc';
    sortDirections = {}; 
    sortDirections[colIndex] = currentDirection;

    // Reset ikonek w nagłówkach
    document.querySelectorAll('.sort-icon').forEach(icon => icon.textContent = '↕');
    const currentHeaderIcon = table.querySelectorAll('th')[colIndex].querySelector('.sort-icon');
    if (currentHeaderIcon) {
        currentHeaderIcon.textContent = currentDirection === 'asc' ? '▲' : '▼';
    }

    rows.sort((a, b) => {
        const cellA = a.children[colIndex].textContent.trim();
        const cellB = b.children[colIndex].textContent.trim();

        // Jeśli ciąg zawiera wyłącznie cyfry (np. ID), sortujemy numerycznie
        const isPureNumberA = /^\d+(\.\d+)?$/.test(cellA);
        const isPureNumberB = /^\d+(\.\d+)?$/.test(cellB);

        if (isPureNumberA && isPureNumberB) {
            const numA = parseFloat(cellA);
            const numB = parseFloat(cellB);
            return currentDirection === 'asc' ? numA - numB : numB - numA;
        }

        // Naturalne porównanie tekstowe (obsługuje numery z myślnikami oraz polskie znaki)
        return currentDirection === 'asc'
            ? cellA.localeCompare(cellB, 'pl', { numeric: true, sensitivity: 'base' })
            : cellB.localeCompare(cellA, 'pl', { numeric: true, sensitivity: 'base' });
    });

    // Przeniesienie posortowanych wierszy i ponowne ponumerowanie LP
    rows.forEach((row, index) => {
        tbody.appendChild(row);
        row.children[0].textContent = index + 1;
    });
}

// Funkcja kopiowania ID RustDesk do schowka
function copyToClipboard(text, buttonElement) {
    const cleanText = text.replace(/\s+/g, '');

    navigator.clipboard.writeText(cleanText).then(() => {
        const icon = buttonElement.querySelector('i');
        
        icon.className = 'fa-solid fa-check text-success';
        buttonElement.classList.add('border-success');

        setTimeout(() => {
            icon.className = 'fa-regular fa-copy text-primary';
            buttonElement.classList.remove('border-success');
        }, 1500);
    }).catch(err => {
        console.error('Błąd podczas kopiowania: ', err);
    });
}
</script>
@endsection