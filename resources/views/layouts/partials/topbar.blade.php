    <header class="pcoded-header" id="appHeader">
        <div class="d-flex align-items-center">
            <button type="button" class="btn-hamburger" id="btnHamburger" aria-label="Otwórz menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h4 class="mb-0 fw-bold fs-5 text-dark">@yield('header-title', 'Przegląd Infrastruktury')</h4>
        </div>
        <div class="d-flex align-items-center">
            <div class="dropdown">
                <button class="badge bg-light text-secondary border host-badge"
                        type="button"
                        id="serverInfoDropdown"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        style="cursor: pointer;">
                    <i class="fa-solid fa-circle text-success"></i>
                    <span>Host: <span class="host-name">{{ $serverInfo['host'] }}</span></span>
                    <i class="fa-solid fa-bars"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="serverInfoDropdown" style="min-width: 260px;">
                    <li class="px-3 py-2 border-bottom">
                        <div class="d-flex align-items-center">
                            <i class="fa-solid fa-circle text-success me-2" style="font-size: 8px;"></i>
                            <strong>{{ $serverInfo['host'] }}</strong>
                            <span class="badge bg-success bg-opacity-10 text-success ms-2">online</span>
                        </div>
                    </li>
                    <li class="px-3 py-2 small text-muted">
                        <div class="mb-1"><i class="fa-regular fa-circle-check me-2"></i>System: <strong class="text-dark">{{ $serverInfo['os'] }}</strong></div>
                        <div class="mb-1"><i class="fa-regular fa-clock me-2"></i>Uptime: <strong class="text-dark">{{ $serverInfo['uptime'] }}</strong></div>
                        <div class="mb-1"><i class="fa-regular fa-circle-check me-2"></i>Postfix: <strong class="text-dark">{{ $serverInfo['postfix'] }}</strong></div>
                        <div class="mb-1"><i class="fa-regular fa-circle-check me-2"></i>Cyrus: <strong class="text-dark">{{ $serverInfo['cyrus'] }}</strong></div>
                        <div class="mb-1"><i class="fa-regular fa-circle-check me-2"></i>Amavis: <strong class="text-dark">{{ $serverInfo['amavis'] }}</strong></div>
                        <div><i class="fa-regular fa-circle-check me-2"></i>SpamAssassin: <strong class="text-dark">{{ $serverInfo['spamassassin'] }}</strong></div>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item small" href="{{ route('dashboard') }}">
                            <i class="fa-regular fa-chart-bar me-2"></i>Szczegóły serwera
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>
