<!-- KARTY STATYSTYK -->
<div class="row g-3 mb-3">
    <x-stat-card label="Aktywne bany" icon="fa-solid fa-ban">
        {{ number_format($stats['currently_banned']) }}
    </x-stat-card>

    <x-stat-card label="IP w bazie" icon="fa-solid fa-database">
        {{ number_format($stats['total_ips']) }}
    </x-stat-card>

    <x-stat-card label="Bany 24h / 7d" icon="fa-solid fa-clock-rotate-left">
        {{ number_format($stats['bans_last_24h']) }}
        <span class="fs-6 text-muted">/ {{ number_format($stats['bans_last_7d']) }}</span>
    </x-stat-card>

    <x-stat-card
        label="Na FortiGate"
        icon="fa-solid fa-shield"
        :badge="'feed: '.$fortigateFeedCount"
        badge-title="Liczba IP w pliku f2b-ips.txt">
        {{ number_format($stats['fortigate_banned']) }}
    </x-stat-card>
</div>
