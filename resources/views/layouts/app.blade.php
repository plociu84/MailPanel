<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'Panel Poczta')</title>

    <!-- js-enabled – musi być jak najszybciej, przed CSS -->
    <script>
        document.documentElement.classList.add('js-enabled');
    </script>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Tom Select CSS (Bootstrap 5 Theme) -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 70px;
            --primary-color: #448aff;
            --bg-body: #f4f7f6;
            --text-main: #2b3245;
            --text-heading: #1f2637;
            --text-muted: #7a839a;
            --icon-muted: #7e879c;
            --border-soft: #f1f1f1;
            --border-soft-2: #e9ecef;
            --card-radius: 12px;

            /* Typografia */
            --font-family-base: "Inter", "Open Sans", system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            --font-size-sm: 0.875rem;
            --font-size-base: 0.9375rem;
            --font-size-md: 1rem;
            --font-size-lg: 1.15rem;
            --line-height-base: 1.6;
            --font-weight-regular: 400;
            --font-weight-medium: 500;
            --font-weight-semibold: 600;
        }
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-family-base);
            font-size: var(--font-size-base);
            line-height: var(--line-height-base);
            font-weight: var(--font-weight-regular);
            margin: 0;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .pcoded-container {
            display: flex;
            width: 100vw;
            min-height: 100vh;
        }
        .pcoded-navbar {
            width: var(--sidebar-width);
            background: #fff;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1026;
            box-shadow: 1px 0 20px 0 rgba(69, 90, 100, 0.07);
            display: flex;
            flex-direction: column;
        }
        .navbar-logo {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 28px;
            font-weight: var(--font-weight-semibold);
            font-size: var(--font-size-lg);
            color: var(--primary-color);
            border-bottom: 1px solid var(--border-soft);
            flex-shrink: 0;
            letter-spacing: 0.2px;
            text-decoration: none;
            transition: opacity 0.2s ease;
        }
        .navbar-logo:hover {
            color: var(--primary-color);
            opacity: 0.85;
        }
        .pcoded-inner-navbar {
            list-style: none;
            padding: 28px 20px;
            margin: 0;
            flex: 1;
            overflow-y: auto;
        }
        .pcoded-item {
            padding-bottom: 5px;
        }
        .pcoded-item .pcoded-mtext {
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            color: var(--text-muted);
            font-weight: var(--font-weight-semibold);
            margin-bottom: 14px;
            display: block;
        }
        .pcoded-inner-navbar li a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: var(--text-main);
            text-decoration: none;
            border-radius: 8px;
            font-weight: var(--font-weight-medium);
            font-size: var(--font-size-sm);
            line-height: var(--line-height-base);
            transition: all 0.2s ease-in-out;
            margin-bottom: 4px;
        }
        .pcoded-inner-navbar li a i {
            margin-right: 14px;
            font-size: 1rem;
            color: var(--icon-muted);
            width: 18px;
            text-align: center;
            transition: color 0.2s ease-in-out;
        }
        .pcoded-inner-navbar li a .nav-icon-rustdesk {
            width: 18px;
            height: 18px;
            margin-right: 14px;
            display: inline-block;
            vertical-align: middle;
            background-color: var(--icon-muted);
            -webkit-mask-image: url("{{ asset('images/rustdesk-mono.svg') }}");
            mask-image: url("{{ asset('images/rustdesk-mono.svg') }}");
            -webkit-mask-size: contain;
            mask-size: contain;
            -webkit-mask-repeat: no-repeat;
            mask-repeat: no-repeat;
            -webkit-mask-position: center;
            mask-position: center;
            transition: background-color 0.2s ease-in-out;
        }
        .pcoded-inner-navbar li.active a {
            background-color: transparent;
            color: var(--primary-color);
        }
        .pcoded-inner-navbar li.active a i {
            color: var(--primary-color);
        }
        .pcoded-inner-navbar li.active a .nav-icon-rustdesk {
            background-color: var(--primary-color);
        }
        .pcoded-inner-navbar li a:hover {
            background-color: transparent;
            color: var(--primary-color);
        }
        .pcoded-inner-navbar li a:hover i {
            color: var(--primary-color);
        }
        .pcoded-inner-navbar li a:hover .nav-icon-rustdesk {
            background-color: var(--primary-color);
        }
        .sidebar-footer {
            padding: 18px 20px;
            border-top: 1px solid var(--border-soft);
            flex-shrink: 0;
        }
        .sidebar-footer .btn-logout {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 12px 16px;
            color: var(--text-main);
            background: transparent;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: var(--font-weight-medium);
            font-size: var(--font-size-sm);
            line-height: var(--line-height-base);
            transition: color 0.2s ease-in-out;
            cursor: pointer;
            text-align: left;
        }
        .sidebar-footer .btn-logout i {
            margin-right: 14px;
            font-size: 1rem;
            color: var(--icon-muted);
            width: 18px;
            text-align: center;
            transition: color 0.2s ease-in-out;
        }
        .sidebar-footer .btn-logout:hover {
            color: #dc3545;
        }
        .sidebar-footer .btn-logout:hover i {
            color: #dc3545;
        }
        .pcoded-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            display: flex;
            flex-direction: column;
        }
        .pcoded-header {
            height: var(--header-height);
            background: #fff;
            border-bottom: 1px solid var(--border-soft);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: sticky;
            top: 0;
            z-index: 1024;
            transition: box-shadow 0.2s ease, backdrop-filter 0.2s ease;
        }
        .pcoded-header.scrolled {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            backdrop-filter: blur(8px);
            background-color: rgba(255, 255, 255, 0.92);
        }
        .pcoded-main-container {
            padding: 30px;
        }
        .btn-hamburger {
            display: none;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: none;
            background: transparent;
            color: var(--text-main);
            font-size: 1.15rem;
            border-radius: 8px;
            cursor: pointer;
            margin-right: 12px;
            transition: color 0.2s ease-in-out;
        }
        .btn-hamburger:hover {
            color: var(--primary-color);
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1040;
            opacity: 0;
            transition: opacity 0.25s ease-in-out;
        }
        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }
        .card {
            border: 1px solid var(--border-soft-2);
            border-radius: var(--card-radius);
            box-shadow: none;
            margin-bottom: 30px;
            background-color: #fff;
        }
        .card .card-header {
            background-color: transparent;
            border-bottom: 1px solid var(--border-soft-2);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card .card-header h5 {
            margin: 0;
            font-size: var(--font-size-md);
            font-weight: var(--font-weight-medium);
            color: var(--text-heading);
            line-height: var(--line-height-base);
        }
        .card .card-body {
            padding: 20px;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            font-weight: var(--font-weight-medium);
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-soft-2);
            font-size: 0.85rem;
            letter-spacing: 0;
            padding: 0.875rem 1rem;
            background-color: transparent;
        }
        .table td {
            vertical-align: middle;
            color: var(--text-main);
            font-size: var(--font-size-sm);
            border-bottom: 1px solid var(--border-soft-2);
            padding: 0.875rem 1rem;
        }
        .table > :not(caption) > * > * {
            padding: 0.875rem 1rem;
        }
        .table thead.sticky-top th {
            background-color: #fff;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.06);
        }
        .form-control,
        .form-select,
        .input-group-text {
            border-color: var(--border-soft-2);
            border-radius: 8px;
            color: var(--text-main);
            font-size: 0.875rem;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(68, 138, 255, 0.15);
            outline: none;
        }
        .form-control:hover,
        .form-select:hover {
            border-color: #d0d5dd;
        }
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%237e879c' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.6rem center;
            background-size: 12px 12px;
            padding-right: 2rem;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }
        .form-select:focus {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23448aff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        }
        .form-control-sm,
        .form-select-sm {
            height: calc(1.5em + 0.5rem + 2px);
            padding: 0.25rem 0.5rem;
            font-size: 0.8125rem;
        }
        .form-select-sm {
            padding-right: 1.75rem;
            background-position: right 0.5rem center;
        }
        .input-group > .form-control:not(:first-child),
        .input-group > .form-select:not(:first-child),
        .input-group > .input-group-text:not(:first-child) {
            border-left: 1px solid var(--border-soft-2);
        }
        .input-group > .form-control:not(:last-child),
        .input-group > .form-select:not(:last-child),
        .input-group > .input-group-text:not(:last-child) {
            border-right: 1px solid var(--border-soft-2);
        }
        .dropdown-menu {
            border: 1px solid var(--border-soft-2);
            border-radius: 10px;
        }
        .host-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.95rem;
            padding: 0.55rem 0.85rem;
            border-radius: 10px;
            line-height: 1.2;
        }
        .host-badge .fa-circle {
            font-size: 8px;
        }
        .host-badge .host-name {
            font-weight: 600;
        }
        .host-badge .fa-bars {
            font-size: 0.95em;
            opacity: 0.7;
            margin-left: 0.15rem;
        }
        .card,
        .alert,
        .callout,
        [class*="border-start"],
        [class*="border-end"] {
            border-color: var(--border-soft-2) !important;
        }
        .card {
            border-left-color: var(--border-soft-2) !important;
            border-right-color: var(--border-soft-2) !important;
            border-top-color: var(--border-soft-2) !important;
            border-bottom-color: var(--border-soft-2) !important;
        }

        /* ============================================================ */
        /* TOM SELECT – DOSTOSOWANIE DO LAYOUTU                         */
        /* ============================================================ */

        .ts-wrapper.form-select .ts-control {
            border: 1px solid var(--border-soft-2) !important;
            border-radius: 8px !important;
            padding: 0.25rem 0.5rem !important;
            font-size: 0.8125rem !important;
            color: var(--text-main) !important;
            background-color: #fff !important;
            box-shadow: none !important;
            min-height: calc(1.5em + 0.5rem + 2px);
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .ts-wrapper.form-select .ts-control:hover {
            border-color: #d0d5dd !important;
        }
        .ts-wrapper.form-select.focus .ts-control {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 0.2rem rgba(68, 138, 255, 0.15) !important;
        }
        .ts-wrapper.form-select::after {
            display: none !important;
        }

        /* Dropdown TomSelect — renderowany na body, wysoki z-index */
        .ts-dropdown {
            border: 1px solid var(--border-soft-2) !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            margin-top: 4px !important;
            overflow: hidden;
            background-color: #fff;
            z-index: 9999 !important;
            width: auto !important;
            min-width: 200px;
            max-width: 400px;
        }
        .ts-dropdown .option {
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
            color: var(--text-main);
            transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out;
        }
        .ts-dropdown .option:hover,
        .ts-dropdown .option.active {
            background-color: rgba(68, 138, 255, 0.08) !important;
            color: var(--primary-color) !important;
        }
        .ts-dropdown .option.selected {
            background-color: var(--primary-color) !important;
            color: #fff !important;
        }
        .ts-dropdown .optgroup-header {
            font-weight: var(--font-weight-semibold);
            color: var(--text-muted);
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        /* ============================================================ */
        /* ANIMACJA KART – scroll reveal                                */
        /* ============================================================ */
        html.js-enabled .card {
            opacity: 0;
            transform: translateY(15px);
            transition: opacity 0.4s ease-out, transform 0.4s ease-out;
        }
        html.js-enabled .card.card-visible {
            opacity: 1;
            transform: translateY(0);
        }
        html.js-enabled .tab-pane .card {
            opacity: 1 !important;
            transform: none !important;
            transition: none !important;
        }
        html.js-enabled .modal .card,
        html.js-enabled .modal .card.card-visible {
            opacity: 1 !important;
            transform: none !important;
            transition: none !important;
        }
        @media (prefers-reduced-motion: reduce) {
            html.js-enabled .card {
                opacity: 1;
                transform: none;
                transition: none;
            }
        }
        @media (max-width: 991.98px) {
            .pcoded-navbar {
                width: min(280px, 85vw);
                transform: translateX(-100%);
                transition: transform 0.25s ease-in-out;
                z-index: 1050;
            }
            .pcoded-navbar.show {
                transform: translateX(0);
            }
            .pcoded-content {
                margin-left: 0;
                width: 100%;
            }
            .pcoded-main-container {
                padding: 20px;
            }
            .pcoded-header {
                padding: 0 20px;
            }
            .btn-hamburger {
                display: inline-flex;
            }
        }
    </style>
    @stack('styles')
</head>
<body>

    <div class="pcoded-container">

        <!-- Sidebar Menu -->
        <nav class="pcoded-navbar" id="appSidebar">
            <a href="{{ route('dashboard') }}" class="navbar-logo">
                <i class="fa-regular fa-envelope me-2"></i> MailPanel
            </a>
            <ul class="pcoded-inner-navbar">
                <li class="pcoded-item">
                    <span class="pcoded-mtext">Menu</span>
                </li>

                <li class="{{ Request::routeIs('dashboard') ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
                </li>

                <li class="{{ Request::is('mail-users*') ? 'active' : '' }}">
                    <a href="{{ url('/mail-users') }}"><i class="fa-regular fa-user"></i><span>Użytkownicy poczty</span></a>
                </li>

                <li class="{{ Request::routeIs('system.users.*') ? 'active' : '' }}">
                    <a href="{{ route('system.users.index') }}">
                        <i class="fa-solid fa-user-gear"></i>
                        <span>Użytkownicy systemowi</span>
                    </a>
                </li>

                <li class="{{ Request::routeIs('system.groups.*') ? 'active' : '' }}">
                    <a href="{{ route('system.groups.index') }}">
                        <i class="fa-solid fa-layer-group"></i>
                        <span>Grupy systemowe</span>
                    </a>
                </li>

                <li class="{{ Request::routeIs('fail2ban.*') ? 'active' : '' }}">
                    <a href="{{ route('fail2ban.index') }}">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Fail2Ban</span>
                    </a>
                </li>

                <li class="{{ Request::routeIs('amavis.*') ? 'active' : '' }}">
                    <a href="{{ route('amavis.index') }}">
                        <i class="fa-regular fa-envelope"></i>
                        <span>Amavis</span>
                    </a>
                </li>

                <li class="{{ Request::routeIs('aliases.*') ? 'active' : '' }}">
                    <a href="{{ route('aliases.index') }}">
                        <i class="fa-regular fa-at"></i>
                        <span>Aliasy Postfix</span>
                    </a>
                </li>

                <li class="{{ Request::routeIs('sender-rules.*') ? 'active' : '' }}">
                    <a href="{{ route('sender-rules.index') }}">
                        <i class="fa-regular fa-paper-plane"></i>
                        <span>Nadawcy Postfix</span>
                    </a>
                </li>

                <li class="{{ Request::routeIs('internal-rcpts.*') ? 'active' : '' }}">
                    <a href="{{ route('internal-rcpts.index') }}">
                        <i class="fa-regular fa-user"></i>
                        <span>Odbiorcy Wewnętrzni</span>
                    </a>
                </li>

                <li class="{{ Request::routeIs('rustdesk.*') ? 'active' : '' }}">
                    <a href="{{ route('rustdesk.index') }}">
                        <span class="nav-icon-rustdesk"></span>
                        <span>Ewidencja RustDesk</span>
                    </a>
                </li>

            </ul>

            <div class="sidebar-footer">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-logout">
                        <i class="fa-solid fa-sign-out-alt"></i> Wyloguj
                    </button>
                </form>
            </div>

        </nav>

        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <div class="pcoded-content">

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

            <div class="pcoded-main-container">
                @yield('content')
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Tom Select JS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <!-- Globalne funkcje JavaScript dostępne dla widoków -->
    <script>
        /* ============================================================ */
        /* TOM SELECT – inicjalizacja i naprawa dla modalów             */
        /* ============================================================ */

        /**
         * Inicjalizuje TomSelect dla danego <select>.
         *
         * Kluczowe: dropdownParent = 'body' (nie modal!).
         * Dzięki temu dropdown renderuje się poza modalem i TomSelect
         * może poprawnie zmierzyć szerokość.
         */
        function initTomSelect(el) {
            if (!el || el.tomselect) return;

            new TomSelect(el, {
                create: false,
                maxOptions: 500,
                sortField: {
                    field: "text",
                    direction: "asc"
                },
                dropdownParent: 'body',
                render: {
                    option: function(data, escape) {
                        return '<div>' + escape(data.text) + '</div>';
                    },
                    item: function(data, escape) {
                        return '<div>' + escape(data.text) + '</div>';
                    }
                }
            });
        }

        function refreshTomSelectInModal(modal) {
            modal.querySelectorAll('select.form-select').forEach(function(el) {
                if (el.tomselect) {
                    const value = el.tomselect.getValue();
                    el.tomselect.destroy();
                    initTomSelect(el);
                    if (value !== null && value !== undefined && value !== '') {
                        el.tomselect.setValue(value, true);
                    }
                } else {
                    initTomSelect(el);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('select.form-select').forEach(function(el) {
                if (el.closest('.modal')) return;
                initTomSelect(el);
            });

            document.querySelectorAll('.modal').forEach(function(modal) {
                modal.addEventListener('shown.bs.modal', function() {
                    setTimeout(function() {
                        refreshTomSelectInModal(modal);
                    }, 100);
                });
            });
        });

        /* ============================================================ */
        /* SCROLL REVEAL                                                */
        /* ============================================================ */
        (function() {
            document.querySelectorAll('.tab-pane .card, .modal .card').forEach(function(el) {
                el.classList.add('card-visible');
            });

            if (!('IntersectionObserver' in window)) {
                document.querySelectorAll('.card').forEach(function(el) {
                    el.classList.add('card-visible');
                });
                return;
            }

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('card-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.05,
                rootMargin: '0px 0px -30px 0px'
            });

            document.querySelectorAll('.card').forEach(function(el, index) {
                if (el.closest('.tab-pane') || el.closest('.modal')) {
                    return;
                }
                if (index < 6) {
                    el.style.transitionDelay = (index * 0.05) + 's';
                }
                observer.observe(el);
            });

            setTimeout(function() {
                document.querySelectorAll('.card:not(.card-visible)').forEach(function(el) {
                    el.classList.add('card-visible');
                });
            }, 1000);
        })();

        /* ============================================================ */
        /* RESPONSYWNOŚĆ – sidebar off-canvas                           */
        /* ============================================================ */
        (function() {
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const btnHamburger = document.getElementById('btnHamburger');

            function openSidebar() {
                if (!sidebar || !overlay) return;
                sidebar.classList.add('show');
                overlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                if (!sidebar || !overlay) return;
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }

            if (btnHamburger) {
                btnHamburger.addEventListener('click', function() {
                    if (sidebar.classList.contains('show')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });
            }

            if (overlay) {
                overlay.addEventListener('click', closeSidebar);
            }

            if (sidebar) {
                sidebar.querySelectorAll('a').forEach(function(link) {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 992) {
                            closeSidebar();
                        }
                    });
                });
            }

            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    closeSidebar();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeSidebar();
                }
            });
        })();

        /* ============================================================ */
        /* HEADER – efekt "scrolled"                                    */
        /* ============================================================ */
        (function() {
            const header = document.getElementById('appHeader');
            if (!header) return;

            function updateHeader() {
                if (window.scrollY > 10) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            }

            window.addEventListener('scroll', updateHeader, { passive: true });
            updateHeader();
        })();

        /* ============================================================ */
        /* Globalne funkcje dostępne dla widoków                        */
        /* ============================================================ */

        function getTomValue(id) {
            const el = document.getElementById(id);
            if (!el) return '';
            if (el.tomselect) return el.tomselect.getValue();
            return el.value;
        }

        function showPreferencesModal(content) {
            let el = document.getElementById('prefModalContent');
            if (el) el.innerText = content;
            let modalEl = document.getElementById('prefModal');
            if (modalEl) {
                var myModal = new bootstrap.Modal(modalEl);
                myModal.show();
            }
        }

        function generateRandomPassword(targetInputId, containerId) {
            const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_!@#$";
            const container = document.getElementById(containerId);
            if (!container) return;
            container.innerHTML = '';

            const hasCrypto = typeof window.crypto !== 'undefined'
                && typeof window.crypto.getRandomValues === 'function';

            function randomIndex(max) {
                if (hasCrypto) {
                    const array = new Uint32Array(1);
                    const maxValid = Math.floor(0xFFFFFFFF / max) * max;
                    let value;
                    do {
                        window.crypto.getRandomValues(array);
                        value = array[0];
                    } while (value >= maxValid);
                    return value % max;
                }
                return Math.floor(Math.random() * max);
            }

            for (let i = 0; i < 5; i++) {
                let pass = "";
                for (let j = 0; j < 20; j++) {
                    pass += chars.charAt(randomIndex(chars.length));
                }
                const badge = document.createElement('span');
                badge.className = 'badge bg-secondary font-monospace';
                badge.style.cursor = 'pointer';
                badge.innerText = pass;
                badge.addEventListener('click', function() {
                    const input = document.getElementById(targetInputId);
                    if (input) input.value = pass;
                });
                container.appendChild(badge);
                if (i === 0 && targetInputId === 'password') {
                    const input = document.getElementById(targetInputId);
                    if (input) input.value = pass;
                }
            }
        }

        function openEditUserModal(userId, username, fullName, primaryEmail, mailHost) {
            let form = document.getElementById('editUserForm');
            if (form) form.action = '/mail-users/' + userId;

            let loginInput = document.getElementById('edit_login');
            if (loginInput) loginInput.value = username;

            let nameParts = fullName ? fullName.trim().split(' ') : [];
            let imieInput = document.getElementById('edit_imie');
            let nazwiskoInput = document.getElementById('edit_nazwisko');
            if (imieInput) imieInput.value = nameParts[0] || '';
            if (nazwiskoInput) nazwiskoInput.value = nameParts.slice(1).join(' ') || '';

            let emailPlInput = document.getElementById('edit_email_pl');
            if (emailPlInput) emailPlInput.value = primaryEmail;

            let localPart = primaryEmail ? primaryEmail.split('@')[0] : '';
            let emailComPlInput = document.getElementById('edit_email_com_pl');
            if (emailComPlInput) emailComPlInput.value = localPart ? localPart + '@telmor.com.pl' : '';

            let mailHostInput = document.getElementById('edit_mail_host');
            if (mailHostInput) mailHostInput.value = mailHost;

            let editPasswordInput = document.getElementById('edit_password');
            if (editPasswordInput) editPasswordInput.value = '';

            let editPwContainer = document.getElementById('editPwContainer');
            if (editPwContainer) editPwContainer.innerHTML = '';

            let modalEl = document.getElementById('editUserModal');
            if (modalEl) {
                var editModal = new bootstrap.Modal(modalEl);
                editModal.show();
            }
        }
    </script>

    @stack('scripts')
</body>
</html>