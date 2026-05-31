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
                height: 100%;
                overflow-x: hidden;
            }

            body {
                margin: 0;
                height: 100vh;
                min-height: 100vh;
                background: var(--bg);
                color: var(--text);
                font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                line-height: 1.5;
                overflow: hidden;
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

            .badge-danger {
                background: var(--danger-soft);
                color: var(--danger);
            }

            .badge-warning {
                background: var(--accent-soft);
                color: var(--accent-text);
            }

            .badge-muted {
                background: var(--soft);
                color: #52525b;
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

            .sidebar-toggle {
                position: fixed;
                width: 1px;
                height: 1px;
                opacity: 0;
                pointer-events: none;
            }

            .app-shell {
                display: grid;
                height: 100vh;
                min-height: 0;
                grid-template-columns: 280px minmax(0, 1fr);
                overflow: hidden;
            }

            .app-sidebar {
                position: relative;
                z-index: 40;
                display: flex;
                height: 100vh;
                max-height: 100vh;
                flex-direction: column;
                overflow-y: auto;
                overscroll-behavior: contain;
                border-right: 1px solid rgba(255, 255, 255, .16);
                background: var(--primary);
                color: #fff;
                padding: 18px;
                box-shadow: 10px 0 30px rgba(21, 71, 122, .16);
            }

            .sidebar-brand {
                display: flex;
                align-items: center;
                gap: 12px;
                border-bottom: 1px solid rgba(255, 255, 255, .16);
                padding: 4px 2px 18px;
            }

            .sidebar-brand-mark {
                display: grid;
                width: 50px;
                height: 50px;
                flex: 0 0 auto;
                place-items: center;
                border: 1px solid rgba(241, 196, 15, .72);
                border-radius: 8px;
                background: #fff;
                padding: 5px;
                box-shadow: 0 10px 24px rgba(0, 0, 0, .16);
            }

            .sidebar-brand-mark img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            .sidebar-brand-title {
                display: block;
                font-size: 1.15rem;
                font-weight: 900;
                line-height: 1.1;
            }

            .sidebar-brand-subtitle {
                display: block;
                margin-top: 3px;
                color: rgba(255, 255, 255, .78);
                font-size: .78rem;
                line-height: 1.25;
            }

            .sidebar-nav {
                display: grid;
                gap: 18px;
                padding: 18px 0;
            }

            .sidebar-section-title {
                margin: 0 0 8px;
                color: rgba(255, 255, 255, .58);
                font-size: .72rem;
                font-weight: 900;
                letter-spacing: 0;
                text-transform: uppercase;
            }

            .sidebar-links {
                display: grid;
                gap: 6px;
            }

            .sidebar-link {
                display: flex;
                min-height: 42px;
                align-items: center;
                gap: 10px;
                border: 1px solid transparent;
                border-radius: 8px;
                padding: 9px 10px;
                color: rgba(255, 255, 255, .86);
                font-size: .9rem;
                font-weight: 800;
                transition: background .15s ease, border-color .15s ease, color .15s ease;
            }

            .sidebar-link:hover,
            .sidebar-link.active {
                border-color: rgba(241, 196, 15, .5);
                background: rgba(241, 196, 15, .15);
                color: #fff;
            }

            .sidebar-link.active {
                box-shadow: inset 4px 0 0 var(--accent);
            }

            .sidebar-link-initial {
                display: grid;
                width: 28px;
                height: 28px;
                flex: 0 0 auto;
                place-items: center;
                border-radius: 8px;
                background: rgba(255, 255, 255, .12);
                color: var(--accent);
                font-size: .7rem;
                font-weight: 900;
            }

            .sidebar-foot {
                margin-top: auto;
                border-top: 1px solid rgba(255, 255, 255, .16);
                padding-top: 14px;
                color: rgba(255, 255, 255, .7);
                font-size: .78rem;
                font-weight: 700;
            }

            .app-content {
                display: flex;
                min-height: 0;
                min-width: 0;
                height: 100vh;
                flex-direction: column;
                background: var(--bg);
                overflow-y: auto;
            }

            .app-topbar {
                position: sticky;
                top: 0;
                z-index: 25;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                border-bottom: 1px solid var(--line);
                background: rgba(255, 255, 255, .94);
                padding: 12px 24px;
                backdrop-filter: blur(14px);
            }

            .mobile-menu-button {
                display: none;
                align-items: center;
                justify-content: center;
                min-height: 40px;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: #fff;
                padding: 9px 12px;
                color: var(--primary-dark);
                cursor: pointer;
                font-size: .9rem;
                font-weight: 900;
            }

            .topbar-title {
                display: grid;
                min-width: 0;
                margin-right: auto;
            }

            .topbar-eyebrow {
                color: var(--primary);
                font-size: .72rem;
                font-weight: 900;
                line-height: 1.1;
                text-transform: uppercase;
            }

            .topbar-current {
                overflow: hidden;
                color: var(--text);
                font-size: .98rem;
                font-weight: 900;
                line-height: 1.2;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .content-shell {
                width: min(100%, 1180px);
                flex: 0 0 auto;
                margin: 0 auto;
                padding: 24px 24px 44px;
            }

            .sidebar-backdrop {
                display: none;
            }

            .app-topbar .account-menu {
                flex-wrap: nowrap;
            }

            .app-topbar .button {
                width: auto;
            }

            @media (max-width: 980px) {
                body {
                    height: auto;
                    min-height: 100vh;
                    overflow-x: hidden;
                    overflow-y: auto;
                }

                .app-shell {
                    display: block;
                    height: auto;
                    min-height: 100vh;
                    overflow: visible;
                }

                .app-sidebar {
                    position: fixed;
                    left: 0;
                    top: 0;
                    width: min(84vw, 310px);
                    height: 100vh;
                    max-height: 100vh;
                    transform: translateX(-100%);
                    transition: transform .2s ease;
                }

                .app-content {
                    display: block;
                    height: auto;
                    min-height: 100vh;
                    overflow: visible;
                }

                .sidebar-toggle:checked ~ .app-shell .app-sidebar {
                    transform: translateX(0);
                }

                .sidebar-toggle:checked ~ .app-shell .sidebar-backdrop {
                    display: block;
                    position: fixed;
                    inset: 0;
                    z-index: 35;
                    background: rgba(15, 23, 42, .48);
                }

                .app-topbar {
                    padding: 10px 14px;
                }

                .mobile-menu-button {
                    display: inline-flex;
                    flex: 0 0 auto;
                }

                .topbar-title {
                    margin-right: 0;
                }

                .content-shell {
                    padding: 18px 14px 34px;
                }

                .app-topbar .account-menu {
                    align-items: flex-end;
                    flex-direction: row;
                    gap: 6px;
                }

                .app-topbar .account-name {
                    display: none;
                }

                .app-topbar .button {
                    min-height: 38px;
                    padding: 8px 10px;
                    width: auto;
                }
            }

            @media (max-width: 560px) {
                .topbar-current {
                    max-width: 150px;
                }

                .app-topbar .button-muted {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        @php
            $penggunaAktif = auth()->user();
            $penggunaAktif?->loadMissing('daftarPeran.izin');

            $bolehMelihatMenu = function (string|array|null $izin) use ($penggunaAktif): bool {
                if (! $penggunaAktif) {
                    return false;
                }

                if (blank($izin)) {
                    return true;
                }

                return $penggunaAktif->memilikiIzin($izin);
            };

            $semuaSidebarSections = [
                [
                    'title' => 'Utama',
                    'items' => [
                        ['label' => 'Dashboard', 'route' => 'beranda', 'active' => ['beranda'], 'initial' => 'DB', 'izin' => 'beranda.akses'],
                    ],
                ],
                [
                    'title' => 'Data Master',
                    'items' => [
                        ['label' => 'Pegawai', 'route' => 'pegawai.index', 'active' => ['pegawai.*'], 'initial' => 'PG', 'izin' => ['pegawai.lihat', 'pegawai.kelola']],
                        ['label' => 'Siswa', 'route' => 'siswa.index', 'active' => ['siswa.*'], 'initial' => 'SW', 'izin' => ['siswa.lihat', 'siswa.kelola']],
                        ['label' => 'Kelas', 'route' => 'kelas.index', 'active' => ['kelas.*', 'anggota-kelas.*'], 'initial' => 'KL', 'izin' => ['kelas.lihat', 'kelas.kelola']],
                        ['label' => 'Penempatan Siswa', 'route' => 'penempatan-siswa.index', 'active' => ['penempatan-siswa.*'], 'initial' => 'PS', 'izin' => ['kelas.lihat', 'kelas.kelola']],
                        ['label' => 'Tahun Pelajaran', 'route' => 'tahun-pelajaran.index', 'active' => ['tahun-pelajaran.*'], 'initial' => 'TP', 'izin' => ['tahun_pelajaran.lihat', 'tahun_pelajaran.kelola']],
                        ['label' => 'Mata Pelajaran', 'route' => 'mata-pelajaran.index', 'active' => ['mata-pelajaran.*'], 'initial' => 'MP', 'izin' => ['mata_pelajaran.lihat', 'mata_pelajaran.kelola']],
                        ['label' => 'Jam Pelajaran', 'route' => 'jam-pelajaran.index', 'active' => ['jam-pelajaran.*'], 'initial' => 'JM', 'izin' => ['jadwal.lihat', 'jadwal.kelola']],
                    ],
                ],
                [
                    'title' => 'Akademik',
                    'items' => [
                        ['label' => 'Guru Mapel', 'route' => 'guru-mata-pelajaran.index', 'active' => ['guru-mata-pelajaran.*'], 'initial' => 'GM', 'izin' => ['guru_mapel.lihat', 'guru_mapel.kelola']],
                        ['label' => 'Jadwal Saya', 'route' => 'jadwal-saya.index', 'active' => ['jadwal-saya.*'], 'initial' => 'JS', 'izin' => 'jadwal.pribadi', 'pegawai_only' => true],
                        ['label' => 'Jadwal Pelajaran', 'route' => 'jadwal-pelajaran.index', 'active' => ['jadwal-pelajaran.*'], 'initial' => 'JP', 'izin' => ['jadwal.lihat', 'jadwal.kelola']],
                        ['label' => 'Bobot Nilai', 'route' => 'skema-bobot-nilai.index', 'active' => ['skema-bobot-nilai.*'], 'initial' => 'BN', 'izin' => 'nilai.skema_kelola'],
                        ['label' => 'Komponen Nilai', 'route' => 'komponen-nilai.index', 'active' => ['komponen-nilai.*'], 'initial' => 'KN', 'izin' => 'nilai.komponen_kelola'],
                        ['label' => 'Input Nilai', 'route' => 'input-nilai.index', 'active' => ['input-nilai.*'], 'initial' => 'IN', 'izin' => 'nilai.input'],
                        ['label' => 'Rekap Rapor', 'route' => 'rekap-nilai-rapor.index', 'active' => ['rekap-nilai-rapor.*'], 'initial' => 'RR', 'izin' => 'nilai.rekap'],
                    ],
                ],
                [
                    'title' => 'Kurikulum',
                    'items' => [
                        ['label' => 'Perangkat Ajar Saya', 'route' => 'perangkat-ajar-saya.index', 'active' => ['perangkat-ajar-saya.*'], 'initial' => 'PS', 'izin' => 'perangkat_ajar.upload', 'pegawai_only' => true],
                        ['label' => 'Pemeriksaan Perangkat', 'route' => 'pemeriksaan-perangkat-ajar.index', 'active' => ['pemeriksaan-perangkat-ajar.*'], 'initial' => 'PP', 'izin' => ['perangkat_ajar.lihat', 'perangkat_ajar.periksa']],
                        ['label' => 'Jenis Perangkat Ajar', 'route' => 'jenis-perangkat-ajar.index', 'active' => ['jenis-perangkat-ajar.*'], 'initial' => 'PA', 'izin' => 'perangkat_ajar.jenis_kelola'],
                    ],
                ],
                [
                    'title' => 'Absensi',
                    'items' => [
                        ['label' => 'Jam Absensi', 'route' => 'pengaturan-absensi.index', 'active' => ['pengaturan-absensi.*'], 'initial' => 'JA', 'izin' => 'absensi.pengaturan_kelola'],
                        ['label' => 'Jam Pegawai', 'route' => 'pengaturan-absensi-pegawai.index', 'active' => ['pengaturan-absensi-pegawai.*'], 'initial' => 'JP', 'izin' => 'absensi.pengaturan_kelola'],
                        ['label' => 'Scan Absensi', 'route' => 'scan-absensi.index', 'active' => ['scan-absensi.*'], 'initial' => 'SA', 'izin' => 'absensi.scan', 'blank' => true],
                        ['label' => 'Scan Pegawai', 'route' => 'scan-absensi-pegawai.index', 'active' => ['scan-absensi-pegawai.*'], 'initial' => 'SP', 'izin' => 'absensi.scan', 'blank' => true],
                        ['label' => 'Rekap Absensi', 'route' => 'rekap-absensi-harian.index', 'active' => ['rekap-absensi-harian.*'], 'initial' => 'RA', 'izin' => ['absensi.lihat', 'absensi.koreksi', 'absensi.laporan']],
                        ['label' => 'Rekap Pegawai', 'route' => 'rekap-absensi-pegawai-harian.index', 'active' => ['rekap-absensi-pegawai-harian.*'], 'initial' => 'RP', 'izin' => ['absensi.lihat', 'absensi.koreksi', 'absensi.laporan', 'absensi_pegawai.pribadi']],
                        ['label' => 'Laporan Absensi', 'route' => 'laporan-absensi.index', 'active' => ['laporan-absensi.*'], 'initial' => 'LA', 'izin' => 'absensi.laporan'],
                        ['label' => 'Notifikasi WA Siswa', 'route' => 'notifikasi-absensi-siswa.index', 'active' => ['notifikasi-absensi-siswa.*'], 'initial' => 'WA', 'izin' => 'absensi.laporan'],
                        ['label' => 'Laporan Pegawai', 'route' => 'laporan-absensi-pegawai-bulanan.index', 'active' => ['laporan-absensi-pegawai-bulanan.*'], 'initial' => 'LP', 'izin' => ['absensi.laporan', 'absensi_pegawai.pribadi']],
                    ],
                ],
                [
                    'title' => 'Pembinaan',
                    'items' => [
                        ['label' => 'Kategori Pembinaan', 'route' => 'kategori-pembinaan-siswa.index', 'active' => ['kategori-pembinaan-siswa.*'], 'initial' => 'KB', 'izin' => 'bk.kelola'],
                        ['label' => 'Laporan Pembinaan', 'route' => 'laporan-pembinaan-siswa.index', 'active' => ['laporan-pembinaan-siswa.*'], 'initial' => 'LP', 'izin' => ['bk.lihat', 'bk.kelola']],
                    ],
                ],
                [
                    'title' => 'Sarana Prasarana',
                    'items' => [
                        ['label' => 'Inventaris Barang', 'route' => 'barang.index', 'active' => ['barang.*'], 'initial' => 'IB', 'izin' => ['barang.lihat', 'barang.kelola']],
                        ['label' => 'Unit Aset', 'route' => 'unit-barang.index', 'active' => ['unit-barang.*'], 'initial' => 'UA', 'izin' => ['barang.lihat', 'barang.kelola']],
                        ['label' => 'Label Barcode', 'route' => 'label-barcode-inventaris.index', 'active' => ['label-barcode-inventaris.*'], 'initial' => 'BC', 'izin' => ['barang.lihat', 'barang.kelola']],
                        ['label' => 'Saldo Stok', 'route' => 'saldo-stok-barang.index', 'active' => ['saldo-stok-barang.*'], 'initial' => 'SS', 'izin' => ['barang.lihat', 'barang.kelola']],
                        ['label' => 'Mutasi Stok', 'route' => 'mutasi-stok-barang.index', 'active' => ['mutasi-stok-barang.*'], 'initial' => 'MS', 'izin' => ['barang.lihat', 'barang.kelola']],
                        ['label' => 'Peminjaman Barang', 'route' => 'peminjaman-barang.index', 'active' => ['peminjaman-barang.*', 'pengembalian-barang.*'], 'initial' => 'PB', 'izin' => ['barang.lihat', 'barang.peminjaman_kelola']],
                        ['label' => 'Kategori Barang', 'route' => 'kategori-barang.index', 'active' => ['kategori-barang.*'], 'initial' => 'KB', 'izin' => ['barang.lihat', 'barang.kelola']],
                        ['label' => 'Satuan Barang', 'route' => 'satuan-barang.index', 'active' => ['satuan-barang.*'], 'initial' => 'SB', 'izin' => ['barang.lihat', 'barang.kelola']],
                        ['label' => 'Lokasi Barang', 'route' => 'lokasi-barang.index', 'active' => ['lokasi-barang.*'], 'initial' => 'LB', 'izin' => ['barang.lihat', 'barang.kelola']],
                    ],
                ],
                [
                    'title' => 'Administrasi',
                    'items' => [
                        ['label' => 'Kartu Pelajar', 'route' => 'kartu-pelajar.index', 'active' => ['kartu-pelajar.*'], 'initial' => 'KP', 'izin' => ['kartu_pelajar.lihat', 'kartu_pelajar.cetak']],
                        ['label' => 'Kartu Pegawai', 'route' => 'kartu-pegawai.index', 'active' => ['kartu-pegawai.*'], 'initial' => 'KG', 'izin' => 'pegawai.lihat'],
                        ['label' => 'Kenaikan Kelas', 'route' => 'kenaikan-kelas.index', 'active' => ['kenaikan-kelas.*'], 'initial' => 'KK', 'izin' => 'kenaikan_kelas.kelola'],
                    ],
                ],
                [
                    'title' => 'Sistem',
                    'items' => [
                        ['label' => 'Profil Saya', 'route' => 'profil-pegawai.edit', 'active' => ['profil-pegawai.*'], 'initial' => 'PR', 'izin' => 'pegawai.profil', 'pegawai_only' => true],
                        ['label' => 'Akun Pegawai', 'route' => 'akun-pegawai.index', 'active' => ['akun-pegawai.*'], 'initial' => 'AP', 'izin' => ['akun.lihat', 'akun.kelola']],
                        ['label' => 'Role', 'route' => 'peran.index', 'active' => ['peran.*'], 'initial' => 'RL', 'izin' => ['peran.lihat', 'peran.kelola']],
                    ],
                ],
            ];

            $sidebarSections = collect($semuaSidebarSections)
                ->map(function (array $section) use ($bolehMelihatMenu, $penggunaAktif) {
                    $section['items'] = collect($section['items'])
                        ->filter(function (array $item) use ($bolehMelihatMenu, $penggunaAktif) {
                            if (($item['pegawai_only'] ?? false) && ! $penggunaAktif?->pegawai_id) {
                                return false;
                            }

                            return $bolehMelihatMenu($item['izin'] ?? null);
                        })
                        ->values()
                        ->all();

                    return $section;
                })
                ->filter(fn (array $section) => count($section['items']) > 0)
                ->values()
                ->all();
        @endphp

        <input id="sidebar-toggle" type="checkbox" class="sidebar-toggle">

        <div class="app-shell">
            <aside class="app-sidebar" aria-label="Menu utama NUSA">
                <a href="{{ route('beranda') }}" class="sidebar-brand">
                    <span class="sidebar-brand-mark">
                        <img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
                    </span>
                    <span>
                        <span class="sidebar-brand-title">NUSA</span>
                        <span class="sidebar-brand-subtitle">SMP Negeri 2 Padang Panjang</span>
                    </span>
                </a>

                <nav class="sidebar-nav">
                    @foreach ($sidebarSections as $section)
                        <section>
                            <p class="sidebar-section-title">{{ $section['title'] }}</p>
                            <div class="sidebar-links">
                                @foreach ($section['items'] as $item)
                                    @php
                                        $activePatterns = (array) ($item['active'] ?? $item['route']);
                                        $aktif = request()->routeIs(...$activePatterns);
                                    @endphp

                                    <a
                                        href="{{ route($item['route']) }}"
                                        class="sidebar-link {{ $aktif ? 'active' : '' }}"
                                        @if ($item['blank'] ?? false) target="_blank" rel="noopener" @endif
                                    >
                                        <span class="sidebar-link-initial">{{ $item['initial'] }}</span>
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </nav>

                <div class="sidebar-foot">
                    <span>NUSA</span><br>
                    <span>Data sekolah terpadu.</span>
                </div>
            </aside>

            <label for="sidebar-toggle" class="sidebar-backdrop" aria-hidden="true"></label>

            <div class="app-content">
                <header class="app-topbar">
                    <label for="sidebar-toggle" class="mobile-menu-button">Menu</label>

                    <div class="topbar-title">
                        <span class="topbar-eyebrow">NUSA</span>
                        <span class="topbar-current">@yield('title', 'NUSA')</span>
                    </div>

                    @auth
                        <div class="account-menu">
                            <span class="account-name">{{ auth()->user()->nama }}</span>
                            @if (auth()->user()->pegawai_id && auth()->user()->memilikiIzin('pegawai.profil'))
                                <a href="{{ route('profil-pegawai.edit') }}" class="button button-muted button-sm">Profil Saya</a>
                            @endif
                            <a href="{{ route('kata-sandi.edit') }}" class="button button-muted button-sm">Ganti Password</a>
                            <form action="{{ route('logout') }}" method="POST" class="logout-form">
                                @csrf
                                <button type="submit" class="button button-danger button-sm">Keluar</button>
                            </form>
                        </div>
                    @endauth
                </header>

                <main class="content-shell">
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
