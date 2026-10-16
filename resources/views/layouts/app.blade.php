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

    <!-- Bootstrap 5 CSS (lokalnie: public/assets, bez CDN) -->
    <link href="{{ asset('assets/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <!-- FontAwesome Icons (lokalnie) -->
    <link href="{{ asset('assets/font-awesome/css/all.min.css') }}" rel="stylesheet">
    <!-- Inter (lokalnie, self-hosted woff2) -->
    <link href="{{ asset('assets/inter/inter.css') }}" rel="stylesheet">
    <!-- Tom Select CSS (Bootstrap 5 Theme, lokalnie) -->
    <link href="{{ asset('assets/tom-select/css/tom-select.bootstrap5.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/panel.css') }}?v={{ filemtime(public_path('assets/css/panel.css')) }}" rel="stylesheet">
    @stack('styles')
</head>
<body>

    <div class="pcoded-container">

        @include('layouts.partials.sidebar')

        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <div class="pcoded-content">

            @include('layouts.partials.topbar')

            <div class="pcoded-main-container">
                @yield('content')
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS (lokalnie) -->
    <script src="{{ asset('assets/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

    <!-- Tom Select JS (lokalnie) -->
    <script src="{{ asset('assets/tom-select/js/tom-select.complete.min.js') }}"></script>

    <!-- Globalne funkcje JavaScript dostępne dla widoków -->
    <script src="{{ asset('assets/js/panel.js') }}?v={{ filemtime(public_path('assets/js/panel.js')) }}"></script>

    @stack('scripts')
</body>
</html>