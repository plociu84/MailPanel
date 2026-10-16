<!-- Statystyki -->
<div class="row g-3 mb-3">
    <x-stat-card col="col-md-2 col-sm-6" label="Wszystkie" icon="fa-solid fa-envelope">
        {{ $stats['total'] ?? 0 }}
    </x-stat-card>

    <x-stat-card col="col-md-2 col-sm-6" label="Czyste" icon="fa-solid fa-circle-check">
        {{ $stats['clean'] ?? 0 }}
    </x-stat-card>

    <x-stat-card col="col-md-2 col-sm-6" label="SPAMMY" icon="fa-solid fa-triangle-exclamation">
        {{ $stats['spammy'] ?? 0 }}
    </x-stat-card>

    <x-stat-card col="col-md-2 col-sm-6" label="Spam" icon="fa-solid fa-ban">
        {{ $stats['spam'] ?? 0 }}
    </x-stat-card>

    <x-stat-card col="col-md-2 col-sm-6" label="Wirusy" icon="fa-solid fa-virus">
        {{ $stats['virus'] ?? 0 }}
    </x-stat-card>

    <x-stat-card col="col-md-2 col-sm-6" label="W kwarantannie" icon="fa-solid fa-box-archive">
        {{ count($quarantined) }}
    </x-stat-card>
</div>
