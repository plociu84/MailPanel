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
