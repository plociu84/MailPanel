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
