<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', 'NUSA')</title>

        @fonts
        <link rel="icon" href="{{ asset('images/logo-nusa.png') }}" type="image/png">

        <style>
            :root {
                --bg: #f6f8fb;
                --panel: #ffffff;
                --text: #18181b;
                --muted: #71717a;
                --line: #dfe7f0;
                --soft: #f4f4f5;
                --primary: #15477A;
                --primary-dark: #0f355c;
                --primary-soft: #e8f0f8;
                --accent: #F1C40F;
                --accent-soft: #fff7d1;
                --accent-text: #6b5200;
                --dark: #15477A;
                --danger: #b91c1c;
                --danger-soft: #fee2e2;
                --warning-soft: var(--accent-soft);
                --warning-text: var(--accent-text);
                --shadow: 0 1px 2px rgba(21, 71, 122, .07), 0 8px 24px rgba(21, 71, 122, .06);
            }

            * {
                box-sizing: border-box;
            }

            html {
                overflow-x: hidden;
            }

            body {
                margin: 0;
                min-height: 100vh;
                background: var(--bg);
                color: var(--text);
                font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                line-height: 1.5;
                overflow-x: hidden;
            }

            a {
                color: inherit;
                text-decoration: none;
            }

            button,
            input,
            select,
            textarea {
                font: inherit;
            }

            .app-header {
                border-bottom: 1px solid var(--line);
                background: var(--panel);
                box-shadow: inset 0 -3px 0 var(--accent);
            }

            .container {
                width: min(100%, 1180px);
                margin: 0 auto;
                padding: 0 20px;
            }

            .header-inner {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 16px 0;
            }

            .brand {
                display: flex;
                align-items: center;
                gap: 14px;
                min-width: 0;
            }

            .brand-mark {
                display: flex;
                width: 52px;
                height: 52px;
                flex: 0 0 auto;
                align-items: center;
                justify-content: center;
                border: 1px solid rgba(241, 196, 15, .8);
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 6px 18px rgba(21, 71, 122, .12);
                padding: 5px;
            }

            .brand-mark img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            .brand-title {
                display: block;
                color: var(--primary);
                font-size: 1.16rem;
                font-weight: 800;
                line-height: 1.15;
            }

            .brand-subtitle {
                display: block;
                color: var(--muted);
                font-size: .88rem;
            }

            .nav-link {
                display: inline-flex;
                align-items: center;
                min-height: 40px;
                border-radius: 8px;
                padding: 8px 12px;
                color: #52525b;
                font-size: .92rem;
                font-weight: 700;
            }

            .app-header nav {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            .header-tools {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: flex-end;
                gap: 12px;
            }

            .account-menu {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: flex-end;
                gap: 8px;
            }

            .account-name {
                display: inline-flex;
                min-height: 36px;
                align-items: center;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: #fff;
                padding: 7px 10px;
                color: var(--primary-dark);
                font-size: .85rem;
                font-weight: 800;
            }

            .logout-form {
                margin: 0;
            }

            .nav-link.active {
                background: var(--primary-soft);
                color: var(--primary-dark);
            }

            main.container {
                padding-top: 24px;
                padding-bottom: 40px;
            }

            .page-header {
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 24px;
            }

            .eyebrow {
                margin: 0 0 4px;
                color: var(--primary);
                font-size: .88rem;
                font-weight: 800;
            }

            .page-title {
                margin: 0;
                font-size: clamp(1.55rem, 4vw, 2rem);
                letter-spacing: 0;
                line-height: 1.15;
            }

            .actions {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }

            .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 42px;
                border: 1px solid transparent;
                border-radius: 8px;
                padding: 10px 14px;
                cursor: pointer;
                font-size: .92rem;
                font-weight: 800;
                line-height: 1.15;
                transition: background .15s ease, border-color .15s ease, color .15s ease;
                white-space: nowrap;
            }

            .button-primary {
                background: var(--primary);
                border-color: var(--primary);
                color: #fff;
            }

            .button-primary:hover {
                background: var(--primary-dark);
            }

            .button-dark {
                background: var(--dark);
                border-color: var(--dark);
                color: #fff;
            }

            .button-dark:hover {
                background: #27272a;
            }

            .button-muted {
                border-color: #d4d4d8;
                background: #fff;
                color: #3f3f46;
            }

            .button-muted:hover {
                background: #fafafa;
            }

            .button-danger {
                border-color: #fecaca;
                background: #fff;
                color: var(--danger);
            }

            .button-danger:hover {
                background: #fef2f2;
            }

            .button-full {
                width: 100%;
            }

            .button-sm {
                min-height: 36px;
                padding: 8px 10px;
                font-size: .84rem;
            }

            .panel {
                border: 1px solid var(--line);
                border-radius: 8px;
                background: var(--panel);
                box-shadow: var(--shadow);
            }

            .panel-pad {
                padding: 20px;
            }

            .panel-title {
                margin: 0;
                font-size: 1rem;
                font-weight: 800;
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 12px;
                margin-bottom: 24px;
            }

            .stat {
                padding: 16px;
            }

            .stat-label {
                margin: 0;
                color: var(--muted);
                font-size: .88rem;
            }

            .stat-value {
                margin: 4px 0 0;
                font-size: 1.7rem;
                font-weight: 800;
                line-height: 1.1;
            }

            .stat.active {
                border-color: #b9cde2;
                background: var(--primary-soft);
            }

            .stat.inactive {
                border-color: var(--accent);
                background: var(--accent-soft);
            }

            .alert {
                margin-bottom: 20px;
                border: 1px solid #b9cde2;
                border-radius: 8px;
                background: var(--primary-soft);
                padding: 12px 14px;
                color: var(--primary-dark);
                font-size: .92rem;
                font-weight: 700;
            }

            .alert-danger {
                border-color: #fecaca;
                background: #fef2f2;
                color: #991b1b;
            }

            .alert ul {
                margin: 8px 0 0;
                padding-left: 20px;
            }

            .filter-grid {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 180px auto;
                gap: 12px;
                align-items: end;
            }

            .filter-grid-wide {
                grid-template-columns: minmax(0, 1fr) 220px 180px auto;
            }

            .field label,
            .form-label {
                display: block;
                margin-bottom: 6px;
                color: #3f3f46;
                font-size: .9rem;
                font-weight: 800;
            }

            .input,
            .select,
            .textarea {
                width: 100%;
                min-height: 42px;
                border: 1px solid #d4d4d8;
                border-radius: 8px;
                background: #fff;
                padding: 10px 12px;
                color: var(--text);
                font-size: .95rem;
                outline: none;
            }

            .textarea {
                min-height: 96px;
                resize: vertical;
            }

            .input:focus,
            .select:focus,
            .textarea:focus {
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(21, 71, 122, .16);
            }

            .is-invalid {
                border-color: #ef4444;
                background: #fef2f2;
            }

            .error-text {
                margin: 6px 0 0;
                color: #b91c1c;
                font-size: .86rem;
            }

            .form-shell,
            .detail-shell {
                display: grid;
                grid-template-columns: 280px minmax(0, 1fr);
                gap: 24px;
                align-items: start;
            }

            .detail-shell {
                grid-template-columns: 320px minmax(0, 1fr);
            }

            .section-stack {
                display: grid;
                gap: 20px;
            }

            .form-grid,
            .detail-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
                margin-top: 16px;
            }

            .span-2 {
                grid-column: 1 / -1;
            }

            .avatar {
                display: grid;
                overflow: hidden;
                place-items: center;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: var(--soft);
                color: var(--primary-dark);
                font-weight: 800;
            }

            .avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .avatar-sm {
                width: 44px;
                height: 44px;
                font-size: .95rem;
            }

            .avatar-md {
                width: 56px;
                height: 56px;
                font-size: 1.1rem;
            }

            .avatar-lg {
                width: 144px;
                height: 144px;
                margin: 0 auto;
                font-size: 3rem;
            }

            .avatar-upload {
                display: grid;
                gap: 14px;
            }

            .file-input {
                width: 100%;
                font-size: .9rem;
            }

            .input-sm,
            .select-sm {
                min-height: 38px;
                padding: 8px 10px;
                font-size: .9rem;
            }

            .member-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                justify-content: flex-end;
            }

            .help-text {
                margin: 6px 0 0;
                color: var(--muted);
                font-size: .8rem;
            }

            .status-toggle {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 14px;
                margin-top: 20px;
                border: 1px solid var(--line);
                border-radius: 8px;
                padding: 12px;
            }

            .status-toggle input {
                width: 20px;
                height: 20px;
                accent-color: var(--primary);
            }

            .table-wrap {
                overflow-x: auto;
            }

            .employee-table {
                width: 100%;
                min-width: 850px;
                border-collapse: collapse;
            }

            .employee-table th,
            .employee-table td {
                border-bottom: 1px solid var(--line);
                padding: 14px 16px;
                text-align: left;
                vertical-align: middle;
            }

            .employee-table th {
                background: #fafafa;
                color: #52525b;
                font-size: .76rem;
                font-weight: 800;
                letter-spacing: 0;
                text-transform: uppercase;
            }

            .employee-table tr:last-child td {
                border-bottom: 0;
            }

            .employee-table tbody tr:hover {
                background: #fafafa;
            }

            .placement-table {
                min-width: 1120px;
            }

            .person {
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 0;
            }

            .person-name {
                margin: 0;
                font-weight: 800;
            }

            .person-meta {
                margin: 2px 0 0;
                color: var(--muted);
                font-size: .9rem;
            }

            .muted {
                color: var(--muted);
            }

            .badge {
                display: inline-flex;
                border-radius: 999px;
                padding: 4px 10px;
                font-size: .78rem;
                font-weight: 800;
                line-height: 1.2;
            }

            .badge-active {
                background: var(--primary-soft);
                color: var(--primary-dark);
            }

            .badge-inactive {
                background: var(--warning-soft);
                color: var(--warning-text);
            }

            .desktop-only {
                display: block;
            }

            .mobile-only {
                display: none;
            }

            .mobile-list {
                display: grid;
            }

            .mobile-only.mobile-list {
                display: none;
            }

            .mobile-card {
                border-bottom: 1px solid var(--line);
                padding: 16px;
            }

            .mobile-card:last-child {
                border-bottom: 0;
            }

            .mobile-card-main {
                display: flex;
                gap: 12px;
            }

            .mobile-card-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
            }

            .quick-facts {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
                margin-top: 12px;
                font-size: .9rem;
            }

            .quick-facts dt {
                color: var(--muted);
            }

            .quick-facts dd {
                margin: 0;
                font-weight: 700;
            }

            .pagination-simple {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-top: 18px;
                color: var(--muted);
                font-size: .92rem;
            }

            .detail-profile {
                text-align: center;
            }

            .detail-profile h2 {
                margin: 16px 0 4px;
                font-size: 1.25rem;
                line-height: 1.2;
            }

            .detail-profile p {
                margin: 0;
                color: var(--muted);
            }

            .detail-item dt {
                color: var(--muted);
                font-size: .88rem;
            }

            .detail-item dd {
                margin: 4px 0 0;
                overflow-wrap: anywhere;
                font-weight: 800;
            }

            .empty-state {
                padding: 44px 16px;
                text-align: center;
                color: var(--muted);
            }

            .form-actions {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }

            .text-right {
                text-align: right;
            }

            @media (max-width: 900px) {
                .header-inner,
                .page-header {
                    align-items: stretch;
                    flex-direction: column;
                }

                .stats-grid {
                    grid-template-columns: 1fr;
                }

                .filter-grid,
                .filter-grid-wide,
                .form-shell,
                .detail-shell,
                .form-grid,
                .detail-grid {
                    grid-template-columns: 1fr;
                }

                .span-2 {
                    grid-column: auto;
                }

                .desktop-only {
                    display: none;
                }

                .mobile-only {
                    display: block;
                }

                .mobile-only.mobile-list {
                    display: grid;
                }

                .actions,
                .form-actions,
                .member-actions {
                    flex-direction: column;
                }

                .header-tools,
                .account-menu {
                    align-items: stretch;
                    flex-direction: column;
                }

                .account-name {
                    justify-content: center;
                }

                .button {
                    width: 100%;
                }

                .avatar-upload {
                    grid-template-columns: 96px minmax(0, 1fr);
                    align-items: center;
                }

                .avatar-lg {
                    width: 96px;
                    height: 96px;
                    margin: 0;
                    font-size: 2rem;
                }

                .pagination-simple {
                    flex-direction: column;
                    align-items: stretch;
                }

                .placement-table {
                    min-width: 0;
                }

                .placement-table thead {
                    display: none;
                }

                .placement-table,
                .placement-table tbody,
                .placement-table tr,
                .placement-table td {
                    display: block;
                    width: 100%;
                }

                .placement-table tr {
                    border-bottom: 1px solid var(--line);
                    padding: 14px 16px;
                }

                .placement-table tr:last-child {
                    border-bottom: 0;
                }

                .placement-table td {
                    border-bottom: 0;
                    padding: 8px 0;
                }

                .placement-table td::before {
                    content: attr(data-label);
                    display: block;
                    margin-bottom: 5px;
                    color: var(--muted);
                    font-size: .76rem;
                    font-weight: 800;
                    text-transform: uppercase;
                }

                .placement-table tbody tr:hover {
                    background: transparent;
                }
            }

            @media (max-width: 520px) {
                .container {
                    padding-left: 14px;
                    padding-right: 14px;
                }

                .brand-subtitle {
                    font-size: .8rem;
                }

                .brand-mark {
                    width: 48px;
                    height: 48px;
                }

                .panel-pad {
                    padding: 16px;
                }

                .mobile-card-main {
                    align-items: flex-start;
                }

                .mobile-card-head {
                    display: grid;
                    gap: 8px;
                }

                .quick-facts {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body>
        <header class="app-header">
            <div class="container">
                <div class="header-inner">
                    <a href="{{ route('pegawai.index') }}" class="brand">
                        <span class="brand-mark">
                            <img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
                        </span>
                        <span>
                            <span class="brand-title">NUSA</span>
                            <span class="brand-subtitle">SMP Negeri 2 Padang Panjang</span>
                        </span>
                    </a>

                    <div class="header-tools">
                        <nav>
                            <a href="{{ route('pegawai.index') }}" class="nav-link {{ request()->routeIs('pegawai.*') ? 'active' : '' }}">
                                Pegawai
                            </a>
                            <a href="{{ route('siswa.index') }}" class="nav-link {{ request()->routeIs('siswa.*') ? 'active' : '' }}">
                                Siswa
                            </a>
                            <a href="{{ route('tahun-pelajaran.index') }}" class="nav-link {{ request()->routeIs('tahun-pelajaran.*') ? 'active' : '' }}">
                                Tahun Pelajaran
                            </a>
                            <a href="{{ route('kelas.index') }}" class="nav-link {{ request()->routeIs('kelas.*') ? 'active' : '' }}">
                                Kelas
                            </a>
                            <a href="{{ route('mata-pelajaran.index') }}" class="nav-link {{ request()->routeIs('mata-pelajaran.*') ? 'active' : '' }}">
                                Mata Pelajaran
                            </a>
                            <a href="{{ route('kenaikan-kelas.index') }}" class="nav-link {{ request()->routeIs('kenaikan-kelas.*') ? 'active' : '' }}">
                                Kenaikan Kelas
                            </a>
                        </nav>

                        @auth
                            <div class="account-menu">
                                <span class="account-name">{{ auth()->user()->nama }}</span>
                                <a href="{{ route('kata-sandi.edit') }}" class="button button-muted button-sm">Ganti Password</a>
                                <form action="{{ route('logout') }}" method="POST" class="logout-form">
                                    @csrf
                                    <button type="submit" class="button button-danger button-sm">Keluar</button>
                                </form>
                            </div>
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        <main class="container">
            @yield('content')
        </main>
    </body>
</html>
