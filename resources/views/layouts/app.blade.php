<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', 'NUSA')</title>

        @fonts
        <link rel="icon" href="{{ asset('images/logo-nusa.png') }}" type="image/png">
        @include('partials.pwa-head')

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
                gap: 7px;
                padding: 12px 0 18px;
            }

            .sidebar-search {
                padding-top: 14px;
            }

            .sidebar-search-control {
                position: relative;
                display: flex;
                align-items: center;
            }

            .sidebar-search-control > svg {
                position: absolute;
                left: 11px;
                width: 17px;
                height: 17px;
                fill: none;
                stroke: rgba(255, 255, 255, .64);
                stroke-linecap: round;
                stroke-linejoin: round;
                stroke-width: 2;
                pointer-events: none;
            }

            .sidebar-search-control input {
                width: 100%;
                height: 40px;
                border: 1px solid rgba(255, 255, 255, .2);
                border-radius: 8px;
                outline: 0;
                background: rgba(255, 255, 255, .1);
                padding: 8px 38px 8px 36px;
                color: #fff;
                font-size: .82rem;
                font-weight: 750;
            }

            .sidebar-search-control input::placeholder {
                color: rgba(255, 255, 255, .58);
            }

            .sidebar-search-control input:focus {
                border-color: rgba(241, 196, 15, .8);
                background: rgba(255, 255, 255, .14);
                box-shadow: 0 0 0 3px rgba(241, 196, 15, .12);
            }

            .sidebar-search-clear {
                position: absolute;
                right: 5px;
                display: grid;
                width: 30px;
                height: 30px;
                place-items: center;
                border: 0;
                border-radius: 6px;
                background: transparent;
                color: rgba(255, 255, 255, .72);
                cursor: pointer;
            }

            .sidebar-search-clear:hover {
                background: rgba(255, 255, 255, .12);
                color: #fff;
            }

            .sidebar-search-clear svg {
                width: 16px;
                height: 16px;
                fill: none;
                stroke: currentColor;
                stroke-linecap: round;
                stroke-width: 2;
            }

            .sidebar-section {
                min-width: 0;
                border: 1px solid transparent;
                border-radius: 8px;
            }

            .sidebar-section[open] {
                border-color: rgba(255, 255, 255, .1);
                background: rgba(5, 34, 65, .16);
            }

            .sidebar-section > summary {
                list-style: none;
            }

            .sidebar-section > summary::-webkit-details-marker {
                display: none;
            }

            .sidebar-section-summary {
                display: grid;
                min-height: 42px;
                grid-template-columns: minmax(0, 1fr) auto auto;
                align-items: center;
                gap: 7px;
                border-radius: 7px;
                padding: 9px 9px 9px 11px;
                color: rgba(255, 255, 255, .68);
                cursor: pointer;
                transition: background .15s ease, color .15s ease;
            }

            .sidebar-section-summary:hover,
            .sidebar-section[open] > .sidebar-section-summary {
                background: rgba(255, 255, 255, .07);
                color: #fff;
            }

            .sidebar-section-title {
                min-width: 0;
                font-size: .76rem;
                font-weight: 900;
                letter-spacing: 0;
                text-transform: uppercase;
            }

            .sidebar-section.has-active > .sidebar-section-summary .sidebar-section-title {
                color: var(--accent);
            }

            .sidebar-section-count {
                display: grid;
                min-width: 23px;
                height: 22px;
                place-items: center;
                border-radius: 6px;
                background: rgba(255, 255, 255, .1);
                padding: 0 5px;
                color: rgba(255, 255, 255, .72);
                font-size: .66rem;
                font-weight: 900;
            }

            .sidebar-section-chevron {
                width: 15px;
                height: 15px;
                fill: none;
                stroke: currentColor;
                stroke-linecap: round;
                stroke-linejoin: round;
                stroke-width: 2;
                transition: transform .16s ease;
            }

            .sidebar-section[open] .sidebar-section-chevron {
                transform: rotate(90deg);
            }

            .sidebar-section-content {
                display: grid;
                gap: 3px;
                padding: 2px 7px 8px;
            }

            .sidebar-subgroup + .sidebar-subgroup {
                margin-top: 7px;
                border-top: 1px solid rgba(255, 255, 255, .08);
                padding-top: 8px;
            }

            .sidebar-subgroup-title {
                margin: 0 7px 5px;
                color: rgba(255, 255, 255, .48);
                font-size: .65rem;
                font-weight: 900;
                text-transform: uppercase;
            }

            .sidebar-links {
                display: grid;
                gap: 3px;
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

            .sidebar-link-label {
                min-width: 0;
                overflow-wrap: anywhere;
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

            .sidebar-search-empty {
                border: 1px dashed rgba(255, 255, 255, .24);
                border-radius: 8px;
                padding: 18px 12px;
                color: rgba(255, 255, 255, .66);
                font-size: .8rem;
                font-weight: 750;
                text-align: center;
            }

            [hidden] {
                display: none !important;
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

            .topbar-actions {
                display: flex;
                flex: 0 0 auto;
                align-items: center;
                gap: 8px;
            }

            .topbar-menu {
                position: relative;
            }

            .topbar-menu > summary {
                list-style: none;
            }

            .topbar-menu > summary::-webkit-details-marker {
                display: none;
            }

            .topbar-icon-button {
                position: relative;
                display: inline-flex;
                width: 42px;
                height: 42px;
                align-items: center;
                justify-content: center;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: #fff;
                color: var(--primary-dark);
                cursor: pointer;
                transition: border-color .16s ease, background .16s ease;
            }

            .topbar-icon-button:hover,
            .topbar-menu[open] > .topbar-icon-button {
                border-color: rgba(21, 71, 122, .3);
                background: var(--primary-soft);
            }

            .topbar-icon-button svg,
            .account-trigger svg,
            .account-dropdown-link svg {
                width: 19px;
                height: 19px;
                fill: none;
                stroke: currentColor;
                stroke-linecap: round;
                stroke-linejoin: round;
                stroke-width: 2;
            }

            .notification-count {
                position: absolute;
                top: -5px;
                right: -5px;
                display: grid;
                min-width: 21px;
                height: 21px;
                place-items: center;
                border: 2px solid #fff;
                border-radius: 999px;
                background: var(--accent);
                padding: 0 5px;
                color: #3f3100;
                font-size: .68rem;
                font-weight: 900;
                line-height: 1;
            }

            .notification-count[hidden] {
                display: none;
            }

            .topbar-popover {
                position: absolute;
                top: calc(100% + 10px);
                right: 0;
                z-index: 60;
                width: min(390px, calc(100vw - 28px));
                overflow: hidden;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 20px 50px rgba(15, 53, 92, .2);
            }

            .notification-popover-head,
            .notification-popover-foot {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 13px 15px;
            }

            .notification-popover-head {
                border-bottom: 1px solid var(--line);
            }

            .notification-popover-head strong {
                color: var(--primary-dark);
                font-size: .92rem;
            }

            .notification-text-button {
                border: 0;
                background: transparent;
                padding: 2px;
                color: var(--primary);
                cursor: pointer;
                font-size: .76rem;
                font-weight: 850;
            }

            .notification-popover-list {
                display: grid;
                max-height: 410px;
                overflow-y: auto;
            }

            .notification-popover-form {
                margin: 0;
                border-bottom: 1px solid var(--line);
            }

            .notification-popover-form:last-child {
                border-bottom: 0;
            }

            .notification-popover-item {
                display: grid;
                width: 100%;
                grid-template-columns: auto minmax(0, 1fr);
                gap: 11px;
                border: 0;
                background: #fff;
                padding: 13px 15px;
                color: inherit;
                cursor: pointer;
                text-align: left;
            }

            .notification-popover-item:hover {
                background: #f7f9fc;
            }

            .notification-popover-item.unread {
                background: #f3f8fd;
            }

            .notification-dot {
                width: 9px;
                height: 9px;
                margin-top: 5px;
                border: 2px solid #d8e1eb;
                border-radius: 50%;
                background: #fff;
            }

            .notification-popover-item.unread .notification-dot {
                border-color: var(--accent);
                background: var(--primary);
            }

            .notification-popover-copy {
                min-width: 0;
            }

            .notification-popover-title,
            .notification-popover-message,
            .notification-popover-time {
                display: block;
            }

            .notification-popover-title {
                color: var(--text);
                font-size: .86rem;
                font-weight: 850;
                line-height: 1.3;
            }

            .notification-popover-message {
                display: -webkit-box;
                margin-top: 3px;
                overflow: hidden;
                color: var(--muted);
                font-size: .78rem;
                line-height: 1.4;
                overflow-wrap: anywhere;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
            }

            .notification-popover-time {
                margin-top: 6px;
                color: #8a94a3;
                font-size: .7rem;
                font-weight: 700;
            }

            .notification-popover-empty {
                padding: 30px 18px;
                color: var(--muted);
                font-size: .85rem;
                text-align: center;
            }

            .notification-popover-foot {
                justify-content: center;
                border-top: 1px solid var(--line);
                background: #fbfcfe;
                color: var(--primary);
                font-size: .8rem;
                font-weight: 900;
            }

            .account-trigger {
                display: flex;
                min-height: 42px;
                align-items: center;
                gap: 9px;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: #fff;
                padding: 4px 9px 4px 5px;
                color: var(--primary-dark);
                cursor: pointer;
            }

            .topbar-menu[open] > .account-trigger,
            .account-trigger:hover {
                border-color: rgba(21, 71, 122, .3);
                background: var(--primary-soft);
            }

            .account-avatar {
                display: grid;
                overflow: hidden;
                position: relative;
                width: 32px;
                height: 32px;
                flex: 0 0 auto;
                place-items: center;
                border-radius: 6px;
                background: var(--primary);
                color: #fff;
                font-size: .76rem;
                font-weight: 900;
            }

            .account-avatar img {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .account-identity {
                display: grid;
                max-width: 190px;
                min-width: 0;
                text-align: left;
            }

            .account-identity strong,
            .account-identity small {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .account-identity strong {
                font-size: .8rem;
                line-height: 1.25;
            }

            .account-identity small {
                color: var(--muted);
                font-size: .68rem;
                font-weight: 700;
            }

            .account-chevron {
                width: 15px !important;
                height: 15px !important;
                transition: transform .16s ease;
            }

            .topbar-menu[open] .account-chevron {
                transform: rotate(180deg);
            }

            .account-popover {
                width: 260px;
                padding: 7px;
            }

            .account-popover-head {
                border-bottom: 1px solid var(--line);
                padding: 10px 10px 12px;
            }

            .account-popover-head strong,
            .account-popover-head span {
                display: block;
                overflow-wrap: anywhere;
            }

            .account-popover-head strong {
                color: var(--primary-dark);
                font-size: .88rem;
            }

            .account-popover-head span {
                margin-top: 2px;
                color: var(--muted);
                font-size: .74rem;
            }

            .account-dropdown-link {
                display: flex;
                width: 100%;
                min-height: 40px;
                align-items: center;
                gap: 10px;
                border: 0;
                border-radius: 6px;
                background: transparent;
                padding: 9px 10px;
                color: #3f4854;
                cursor: pointer;
                font-size: .82rem;
                font-weight: 800;
                text-align: left;
            }

            .account-dropdown-link[hidden] {
                display: none;
            }

            .account-dropdown-link:hover {
                background: var(--primary-soft);
                color: var(--primary-dark);
            }

            .account-dropdown-link.danger {
                color: var(--danger);
            }

            .account-popover .logout-form {
                border-top: 1px solid var(--line);
                margin-top: 5px;
                padding-top: 5px;
            }

            .sr-only {
                position: absolute;
                width: 1px;
                height: 1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                clip-path: inset(50%);
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

                .account-identity {
                    display: none;
                }

                .account-trigger {
                    width: 42px;
                    justify-content: center;
                    padding: 4px;
                }

                .account-chevron {
                    display: none;
                }
            }

            @media (max-width: 560px) {
                .topbar-current {
                    max-width: 150px;
                }

                .app-topbar .button-muted {
                    display: none;
                }

                .topbar-title {
                    overflow: hidden;
                }

                .topbar-actions {
                    gap: 6px;
                }

                .topbar-popover {
                    position: fixed;
                    top: 64px;
                    right: 10px;
                    left: 10px;
                    width: auto;
                    max-height: calc(100vh - 78px);
                }

                .account-popover {
                    left: auto;
                }
            }
        </style>
        @stack('styles')
    </head>
    <body>
        @php
            $penggunaAktif = auth()->user();
            $penggunaAktif?->loadMissing(['pegawai', 'siswa', 'orangTuaWali', 'daftarPeran.izin']);
            $namaPenggunaAktif = $penggunaAktif?->pegawai?->nama_lengkap
                ?: $penggunaAktif?->siswa?->nama_lengkap
                ?: $penggunaAktif?->orangTuaWali?->nama_lengkap
                ?: $penggunaAktif?->nama
                ?: 'Pengguna NUSA';
            $notifikasiTerbaru = $penggunaAktif
                ? $penggunaAktif->notifikasiPengguna()->orderByDesc('created_at')->orderByDesc('id')->limit(6)->get()
                : collect();
            $jumlahNotifikasiBelumDibaca = $penggunaAktif
                ? $penggunaAktif->notifikasiPengguna()->belumDibaca()->count()
                : 0;
            $labelPeranAktif = $penggunaAktif?->daftarPeran
                ->where('aktif', true)
                ->pluck('nama')
                ->take(2)
                ->implode(', ');
            $labelPeranAktif = $labelPeranAktif ?: str((string) ($penggunaAktif?->peran ?? 'pengguna'))->headline();
            $inisialPengguna = collect(preg_split('/\s+/', trim($namaPenggunaAktif)))
                ->filter()
                ->take(2)
                ->map(fn ($kata) => mb_strtoupper(mb_substr($kata, 0, 1)))
                ->implode('');
            $fotoPenggunaUrl = $penggunaAktif?->pegawai?->foto
                ? asset('storage/'.$penggunaAktif->pegawai->foto)
                : null;

            $bolehMelihatMenu = function (string|array|null $izin) use ($penggunaAktif): bool {
                if (! $penggunaAktif) {
                    return false;
                }

                if (blank($izin)) {
                    return true;
                }

                return $penggunaAktif->memilikiIzin($izin);
            };
            $dapatScanIbadahHariIni = $penggunaAktif
                ? app(\App\Services\Ibadah\AksesScanKegiatanIbadah::class)->dapatMemindai($penggunaAktif)
                : false;
            $dapatRekapIbadahHariIni = $penggunaAktif
                ? app(\App\Services\Ibadah\AksesScanKegiatanIbadah::class)->dapatMelihatRekap($penggunaAktif)
                : false;
            $dapatRingkasanIbadahBulanan = $penggunaAktif
                ? app(\App\Services\Ibadah\AksesScanKegiatanIbadah::class)->dapatMelihatRingkasanBulanan($penggunaAktif)
                : false;
            $dapatScanBerhalanganIbadah = $penggunaAktif
                ? app(\App\Services\Ibadah\AksesBerhalanganIbadah::class)->dapatMemindai($penggunaAktif)
                : false;
            $dapatKonfirmasiBerhalanganIbadah = $penggunaAktif
                ? app(\App\Services\Ibadah\AksesBerhalanganIbadah::class)->dapatMengonfirmasi($penggunaAktif)
                : false;

            $peranMenuLengkap = [
                'pimpinan',
                'wakil_pimpinan_kesiswaan',
                'wakil_pimpinan_sarana_prasarana',
                'wakil_pimpinan_kurikulum',
                'bk',
                'satpam',
                'petugas_kebersihan',
                'petugas_inventaris',
            ];
            $pakaiSidebarPegawai = $penggunaAktif?->akunPegawai()
                && ! $penggunaAktif->administrator()
                && ! $penggunaAktif->memilikiPeran($peranMenuLengkap);
            $pakaiSidebarSiswa = $penggunaAktif?->akunSiswa()
                || $penggunaAktif?->memilikiPeran('siswa');
            $pakaiSidebarOrangTua = $penggunaAktif?->akunOrangTua()
                || $penggunaAktif?->memilikiPeran('orang_tua');

            $semuaSidebarSections = [
                [
                    'id' => 'utama',
                    'title' => 'Utama',
                    'items' => [
                        ['label' => 'Dashboard', 'route' => 'beranda', 'active' => ['beranda'], 'initial' => 'DB', 'izin' => 'beranda.akses'],
                        ['label' => 'Laporkan Kejadian', 'route' => 'laporan-pembinaan-siswa.create', 'active' => ['laporan-pembinaan-siswa.create'], 'initial' => 'LK', 'izin' => 'poin_siswa.lapor', 'pegawai_only' => true],
                        ['label' => 'Laporan Saya', 'route' => 'laporan-saya.index', 'active' => ['laporan-saya.*'], 'initial' => 'LS', 'izin' => 'poin_siswa.lapor', 'pegawai_only' => true],
                    ],
                ],
                [
                    'id' => 'data-sekolah',
                    'title' => 'Data Sekolah',
                    'items' => [
                        ['label' => 'Tahun Pelajaran', 'route' => 'tahun-pelajaran.index', 'active' => ['tahun-pelajaran.*'], 'initial' => 'TP', 'izin' => ['tahun_pelajaran.lihat', 'tahun_pelajaran.kelola'], 'subgroup' => 'Periode Akademik'],
                        ['label' => 'Pegawai', 'route' => 'pegawai.index', 'active' => ['pegawai.*'], 'initial' => 'PG', 'izin' => ['pegawai.lihat', 'pegawai.kelola'], 'subgroup' => 'Pegawai'],
                        ['label' => 'Siswa', 'route' => 'siswa.index', 'active' => ['siswa.*'], 'initial' => 'SW', 'izin' => ['siswa.lihat', 'siswa.kelola'], 'subgroup' => 'Siswa dan Kelas'],
                        ['label' => 'Kelas', 'route' => 'kelas.index', 'active' => ['kelas.*', 'anggota-kelas.*'], 'initial' => 'KL', 'izin' => ['kelas.lihat', 'kelas.kelola'], 'subgroup' => 'Siswa dan Kelas'],
                        ['label' => 'Penempatan Siswa', 'route' => 'penempatan-siswa.index', 'active' => ['penempatan-siswa.*'], 'initial' => 'PS', 'izin' => ['kelas.lihat', 'kelas.kelola'], 'subgroup' => 'Siswa dan Kelas'],
                        ['label' => 'Kenaikan Kelas', 'route' => 'kenaikan-kelas.index', 'active' => ['kenaikan-kelas.*'], 'initial' => 'KK', 'izin' => 'kenaikan_kelas.kelola', 'subgroup' => 'Siswa dan Kelas'],
                        ['label' => 'Foto Identitas', 'route' => 'foto-identitas.index', 'active' => ['foto-identitas.*'], 'initial' => 'FI', 'izin' => ['siswa.kelola', 'pegawai.kelola'], 'subgroup' => 'Identitas dan Kartu'],
                        ['label' => 'Kartu Pegawai', 'route' => 'kartu-pegawai.index', 'active' => ['kartu-pegawai.*'], 'initial' => 'KG', 'izin' => 'pegawai.lihat', 'subgroup' => 'Identitas dan Kartu'],
                        ['label' => 'Kartu Pelajar', 'route' => 'kartu-pelajar.index', 'active' => ['kartu-pelajar.*'], 'initial' => 'KP', 'izin' => ['kartu_pelajar.lihat', 'kartu_pelajar.cetak'], 'subgroup' => 'Identitas dan Kartu'],
                    ],
                ],
                [
                    'id' => 'akademik',
                    'title' => 'Akademik',
                    'items' => [
                        ['label' => 'Mata Pelajaran', 'route' => 'mata-pelajaran.index', 'active' => ['mata-pelajaran.*'], 'initial' => 'MP', 'izin' => ['mata_pelajaran.lihat', 'mata_pelajaran.kelola'], 'subgroup' => 'Pembelajaran'],
                        ['label' => 'Guru Mata Pelajaran', 'route' => 'guru-mata-pelajaran.index', 'active' => ['guru-mata-pelajaran.*'], 'initial' => 'GM', 'izin' => ['guru_mapel.lihat', 'guru_mapel.kelola'], 'subgroup' => 'Pembelajaran'],
                        ['label' => 'Jadwal Mengajar Saya', 'route' => 'jadwal-saya.index', 'active' => ['jadwal-saya.*'], 'initial' => 'JS', 'izin' => 'jadwal.pribadi', 'pegawai_only' => true, 'subgroup' => 'Pembelajaran'],
                        ['label' => 'Jam Pelajaran', 'route' => 'jam-pelajaran.index', 'active' => ['jam-pelajaran.*'], 'initial' => 'JM', 'izin' => ['jadwal.lihat', 'jadwal.kelola'], 'administrator_only' => true, 'subgroup' => 'Pembelajaran'],
                        ['label' => 'Jadwal Pelajaran', 'route' => 'jadwal-pelajaran.index', 'active' => ['jadwal-pelajaran.*'], 'initial' => 'JP', 'izin' => ['jadwal.lihat', 'jadwal.kelola'], 'subgroup' => 'Pembelajaran'],
                        ['label' => 'Skema Bobot Nilai', 'route' => 'skema-bobot-nilai.index', 'active' => ['skema-bobot-nilai.*'], 'initial' => 'BN', 'izin' => 'nilai.skema_kelola', 'subgroup' => 'Penilaian'],
                        ['label' => 'Input Nilai', 'route' => 'input-nilai.index', 'active' => ['input-nilai.*'], 'initial' => 'IN', 'izin' => 'nilai.input', 'subgroup' => 'Penilaian'],
                        ['label' => 'Rekap Nilai Rapor', 'route' => 'rekap-nilai-rapor.index', 'active' => ['rekap-nilai-rapor.*'], 'initial' => 'RR', 'izin' => 'nilai.rekap', 'subgroup' => 'Penilaian'],
                        ['label' => 'Pernyataan Survei', 'route' => 'pertanyaan-survei-pembelajaran.index', 'active' => ['pertanyaan-survei-pembelajaran.*'], 'initial' => 'SV', 'izin' => 'survei.pertanyaan_kelola', 'subgroup' => 'Penilaian'],
                        ['label' => 'Hasil Survei Saya', 'route' => 'hasil-survei-saya.index', 'active' => ['hasil-survei-saya.*'], 'initial' => 'HS', 'izin' => 'survei.hasil_pribadi', 'pegawai_only' => true, 'subgroup' => 'Penilaian'],
                        ['label' => 'Monitoring Survei', 'route' => 'monitoring-survei.index', 'active' => ['monitoring-survei.*'], 'initial' => 'MS', 'izin' => 'survei.monitor', 'subgroup' => 'Penilaian'],
                        ['label' => 'Perangkat Ajar Saya', 'route' => 'perangkat-ajar-saya.index', 'active' => ['perangkat-ajar-saya.*'], 'initial' => 'PS', 'izin' => 'perangkat_ajar.upload', 'pegawai_only' => true, 'subgroup' => 'Perangkat Ajar'],
                        ['label' => 'Pemeriksaan Perangkat Ajar', 'route' => 'pemeriksaan-perangkat-ajar.index', 'active' => ['pemeriksaan-perangkat-ajar.*'], 'initial' => 'PP', 'izin' => ['perangkat_ajar.lihat', 'perangkat_ajar.periksa'], 'subgroup' => 'Perangkat Ajar'],
                        ['label' => 'Jenis Perangkat Ajar', 'route' => 'jenis-perangkat-ajar.index', 'active' => ['jenis-perangkat-ajar.*'], 'initial' => 'PA', 'izin' => 'perangkat_ajar.jenis_kelola', 'subgroup' => 'Perangkat Ajar'],
                    ],
                ],
                [
                    'id' => 'ujian-asesmen',
                    'title' => 'Ujian & Asesmen',
                    'items' => [
                        ['label' => 'Jenis Ujian CBT', 'route' => 'jenis-ujian-cbt.index', 'active' => ['jenis-ujian-cbt.*'], 'initial' => 'JU', 'izin' => ['cbt.lihat', 'cbt.kelola'], 'subgroup' => 'CBT'],
                        ['label' => 'Bank Soal CBT', 'route' => 'soal-cbt.index', 'active' => ['soal-cbt.*'], 'initial' => 'BS', 'izin' => ['cbt.lihat', 'cbt.kelola', 'cbt.soal_kelola'], 'subgroup' => 'CBT'],
                        ['label' => 'Paket Ujian CBT', 'route' => 'ujian-cbt.index', 'active' => ['ujian-cbt.*'], 'initial' => 'PU', 'izin' => ['cbt.lihat', 'cbt.kelola'], 'subgroup' => 'CBT'],
                        ['label' => 'Jadwal Ujian CBT', 'route' => 'jadwal-ujian-cbt.index', 'active' => ['jadwal-ujian-cbt.*'], 'initial' => 'JD', 'izin' => ['cbt.lihat', 'cbt.kelola'], 'subgroup' => 'CBT'],
                        ['label' => 'Status Panitia CBT', 'route' => 'status-kelengkapan-panitia-cbt.index', 'active' => ['status-kelengkapan-panitia-cbt.*'], 'initial' => 'SP', 'izin' => ['cbt.lihat', 'cbt.kelola'], 'subgroup' => 'CBT'],
                        ['label' => 'Presensi Ujian CBT', 'route' => 'presensi-ujian-cbt.index', 'active' => ['presensi-ujian-cbt.*'], 'initial' => 'PR', 'izin' => ['cbt.presensi', 'cbt.kelola'], 'subgroup' => 'CBT'],
                    ],
                ],
                [
                    'id' => 'kehadiran',
                    'title' => 'Kehadiran',
                    'items' => [
                        ['label' => 'Jadwal Guru Piket', 'route' => 'jadwal-piket-guru.index', 'active' => ['jadwal-piket-guru.*'], 'initial' => 'GP', 'izin' => 'piket_guru.kelola', 'subgroup' => 'Guru Piket'],
                        ['label' => 'Kegiatan Ibadah', 'route' => 'kegiatan-ibadah.index', 'active' => ['kegiatan-ibadah.*'], 'initial' => 'KI', 'izin' => 'ibadah.pengaturan_kelola', 'subgroup' => 'Ibadah Siswa'],
                        ['label' => 'Jadwal Ibadah', 'route' => 'jadwal-kegiatan-ibadah.index', 'active' => ['jadwal-kegiatan-ibadah.*'], 'initial' => 'JI', 'izin' => 'ibadah.pengaturan_kelola', 'subgroup' => 'Ibadah Siswa'],
                        ['label' => 'Pengaturan Berhalangan', 'route' => 'pengaturan-berhalangan-ibadah.index', 'active' => ['pengaturan-berhalangan-ibadah.*'], 'initial' => 'PB', 'izin' => 'ibadah.pengaturan_kelola', 'subgroup' => 'Ibadah Siswa'],
                        ['label' => 'Scan Ibadah Siswa', 'route' => 'scan-kegiatan-ibadah.index', 'active' => ['scan-kegiatan-ibadah.*'], 'initial' => 'SI', 'izin' => 'ibadah.scan', 'blank' => true, 'subgroup' => 'Ibadah Siswa'],
                        ['label' => 'Scan Berhalangan', 'route' => 'scan-berhalangan-ibadah.index', 'active' => ['scan-berhalangan-ibadah.*'], 'initial' => 'SB', 'izin' => null, 'blank' => true, 'scan_berhalangan_only' => true, 'subgroup' => 'Ibadah Siswa'],
                        ['label' => 'Konfirmasi Privat', 'route' => 'konfirmasi-berhalangan-ibadah.index', 'active' => ['konfirmasi-berhalangan-ibadah.*'], 'initial' => 'KP', 'izin' => null, 'konfirmasi_berhalangan_only' => true, 'subgroup' => 'Ibadah Siswa'],
                        ['label' => 'Rekap Berhalangan', 'route' => 'rekap-berhalangan-ibadah.index', 'active' => ['rekap-berhalangan-ibadah.*'], 'initial' => 'RB', 'izin' => null, 'konfirmasi_berhalangan_only' => true, 'subgroup' => 'Ibadah Siswa'],
                        ['label' => 'Rekap Ibadah Siswa', 'route' => 'rekap-kegiatan-ibadah.index', 'active' => ['rekap-kegiatan-ibadah.index', 'rekap-kegiatan-ibadah.koreksi.*'], 'initial' => 'RI', 'izin' => 'ibadah.rekap', 'subgroup' => 'Ibadah Siswa'],
                        ['label' => 'Ringkasan Ibadah Bulanan', 'route' => 'rekap-kegiatan-ibadah.bulanan', 'active' => ['rekap-kegiatan-ibadah.bulanan'], 'initial' => 'BI', 'izin' => 'ibadah.rekap', 'subgroup' => 'Ibadah Siswa'],
                        ['label' => 'Pengaturan Presensi Siswa', 'route' => 'pengaturan-absensi.index', 'active' => ['pengaturan-absensi.*'], 'initial' => 'PA', 'izin' => 'absensi.pengaturan_kelola', 'subgroup' => 'Presensi Siswa'],
                        ['label' => 'Scan Presensi Siswa', 'route' => 'scan-absensi.index', 'active' => ['scan-absensi.*'], 'initial' => 'SS', 'izin' => 'absensi.scan', 'blank' => true, 'subgroup' => 'Presensi Siswa'],
                        ['label' => 'Rekap Presensi Siswa', 'route' => 'rekap-absensi-harian.index', 'active' => ['rekap-absensi-harian.*'], 'initial' => 'RS', 'izin' => ['absensi.lihat', 'absensi.koreksi', 'absensi.laporan'], 'subgroup' => 'Presensi Siswa'],
                        ['label' => 'Laporan Presensi Siswa', 'route' => 'laporan-absensi.index', 'active' => ['laporan-absensi.*'], 'initial' => 'LS', 'izin' => 'absensi.laporan', 'subgroup' => 'Presensi Siswa'],
                        ['label' => 'Pengaturan Presensi Pegawai', 'route' => 'pengaturan-absensi-pegawai.index', 'active' => ['pengaturan-absensi-pegawai.*'], 'initial' => 'PA', 'izin' => 'absensi.pengaturan_kelola', 'subgroup' => 'Presensi Pegawai'],
                        ['label' => 'Scan Presensi Pegawai', 'route' => 'scan-absensi-pegawai.index', 'active' => ['scan-absensi-pegawai.*'], 'initial' => 'SP', 'izin' => 'absensi.scan', 'blank' => true, 'subgroup' => 'Presensi Pegawai'],
                        ['label' => 'Rekap Presensi Pegawai', 'route' => 'rekap-absensi-pegawai-harian.index', 'active' => ['rekap-absensi-pegawai-harian.*'], 'initial' => 'RP', 'izin' => ['absensi.lihat', 'absensi.koreksi', 'absensi.laporan', 'absensi_pegawai.pribadi'], 'subgroup' => 'Presensi Pegawai'],
                        ['label' => 'Laporan Presensi Pegawai', 'route' => 'laporan-absensi-pegawai-bulanan.index', 'active' => ['laporan-absensi-pegawai-bulanan.*'], 'initial' => 'LP', 'izin' => ['absensi.laporan', 'absensi_pegawai.pribadi'], 'subgroup' => 'Presensi Pegawai'],
                    ],
                ],
                [
                    'id' => 'kesiswaan-bk',
                    'title' => 'Kesiswaan & BK',
                    'items' => [
                        ['label' => 'Pemeriksaan & Pengesahan', 'route' => 'pusat-verifikasi-pelanggaran.index', 'active' => ['pusat-verifikasi-pelanggaran.*'], 'initial' => 'VP', 'izin' => ['poin_siswa.lihat', 'poin_siswa.verifikasi_bk', 'poin_siswa.sahkan_wakil'], 'subgroup' => 'Operasional'],
                        ['label' => 'Daftar Laporan Siswa', 'route' => 'laporan-pembinaan-siswa.index', 'active' => ['laporan-pembinaan-siswa.*', 'tindak-lanjut-pembinaan-siswa.*'], 'initial' => 'LS', 'izin' => ['bk.lihat', 'bk.kelola', 'poin_siswa.lapor', 'poin_siswa.lihat', 'poin_siswa.verifikasi_bk'], 'subgroup' => 'Operasional'],
                        ['label' => 'Pendampingan Siswa', 'route' => 'pendampingan-siswa.index', 'active' => ['pendampingan-siswa.*'], 'initial' => 'PD', 'izin' => 'poin_siswa.lihat', 'subgroup' => 'Operasional'],
                        ['label' => 'Pelaksanaan Sanksi Siswa', 'route' => 'sanksi-poin-siswa.index', 'active' => ['sanksi-poin-siswa.*', 'bukti-pelaksanaan-sanksi.*'], 'initial' => 'PS', 'izin' => ['poin_siswa.lihat', 'poin_siswa.sanksi_kelola'], 'subgroup' => 'Operasional'],
                        ['label' => 'Peringatan Dini Siswa', 'route' => 'peringatan-dini-siswa.index', 'active' => ['peringatan-dini-siswa.*'], 'initial' => 'PD', 'izin' => 'poin_siswa.lihat', 'subgroup' => 'Monitoring'],
                        ['label' => 'Rekap Poin Siswa', 'route' => 'rekap-poin-siswa.index', 'active' => ['rekap-poin-siswa.*'], 'initial' => 'RP', 'izin' => 'poin_siswa.lihat', 'subgroup' => 'Monitoring'],
                        ['label' => 'Penghargaan & Pengurangan Poin', 'route' => 'pengurangan-poin-siswa.index', 'active' => ['pengurangan-poin-siswa.*'], 'initial' => 'PH', 'izin' => ['poin_siswa.reward_kelola', 'poin_siswa.putus_konflik'], 'subgroup' => 'Operasional'],
                        ['label' => 'Penugasan Guru Wali', 'route' => 'penugasan-guru-wali.index', 'active' => ['penugasan-guru-wali.*'], 'initial' => 'GW', 'izin' => 'guru_wali.kelola', 'subgroup' => 'Guru Wali'],
                        ['label' => 'Jenis Pelanggaran & Poin', 'route' => 'jenis-pelanggaran-siswa.index', 'active' => ['jenis-pelanggaran-siswa.*'], 'initial' => 'JP', 'izin' => 'poin_siswa.pengaturan', 'subgroup' => 'Pengaturan'],
                        ['label' => 'Aturan Sanksi Poin', 'route' => 'aturan-sanksi-poin.index', 'active' => ['aturan-sanksi-poin.*'], 'initial' => 'AS', 'izin' => 'poin_siswa.pengaturan', 'subgroup' => 'Pengaturan'],
                        ['label' => 'Poin Keterlambatan', 'route' => 'pengaturan-poin-keterlambatan.index', 'active' => ['pengaturan-poin-keterlambatan.*'], 'initial' => 'PK', 'izin' => 'poin_siswa.pengaturan', 'subgroup' => 'Pengaturan'],
                        ['label' => 'Peringatan Dini Poin', 'route' => 'pengaturan-peringatan-dini-poin.index', 'active' => ['pengaturan-peringatan-dini-poin.*'], 'initial' => 'PD', 'izin' => 'poin_siswa.pengaturan', 'subgroup' => 'Pengaturan'],
                        ['label' => 'Batas Proses Pelanggaran', 'route' => 'pengaturan-batas-proses-pelanggaran.index', 'active' => ['pengaturan-batas-proses-pelanggaran.*'], 'initial' => 'BP', 'izin' => 'poin_siswa.pengaturan', 'subgroup' => 'Pengaturan'],
                        ['label' => 'Kategori Pembinaan Non-Poin', 'route' => 'kategori-pembinaan-siswa.index', 'active' => ['kategori-pembinaan-siswa.*'], 'initial' => 'KP', 'izin' => 'bk.kelola', 'subgroup' => 'Pengaturan'],
                    ],
                ],
                [
                    'id' => 'sarana-prasarana',
                    'title' => 'Sarana Prasarana',
                    'items' => [
                        ['label' => 'Katalog Barang', 'route' => 'katalog-barang.index', 'active' => ['katalog-barang.*'], 'initial' => 'KB', 'izin' => null, 'subgroup' => 'Layanan Pegawai'],
                        ['label' => 'Pengajuan Saya', 'route' => 'pengajuan-barang-saya.index', 'active' => ['pengajuan-barang-saya.*'], 'initial' => 'PS', 'izin' => null, 'pegawai_only' => true, 'subgroup' => 'Layanan Pegawai'],
                        ['label' => 'Dashboard Sarpras', 'route' => 'dashboard-sarana-prasarana.index', 'active' => ['dashboard-sarana-prasarana.*'], 'initial' => 'DS', 'izin' => ['barang.lihat', 'barang.kelola', 'barang.peminjaman_kelola'], 'subgroup' => 'Ringkasan'],
                        ['label' => 'Inventaris Barang', 'route' => 'barang.index', 'active' => ['barang.*'], 'initial' => 'IB', 'izin' => ['barang.lihat', 'barang.kelola'], 'subgroup' => 'Inventaris'],
                        ['label' => 'Unit Aset', 'route' => 'unit-barang.index', 'active' => ['unit-barang.*'], 'initial' => 'UA', 'izin' => ['barang.lihat', 'barang.kelola'], 'subgroup' => 'Inventaris'],
                        ['label' => 'Label Inventaris', 'route' => 'label-barcode-inventaris.index', 'active' => ['label-barcode-inventaris.*'], 'initial' => 'LI', 'izin' => ['barang.lihat', 'barang.kelola'], 'subgroup' => 'Inventaris'],
                        ['label' => 'Barang Datang', 'route' => 'penerimaan-barang.index', 'active' => ['penerimaan-barang.*'], 'initial' => 'BD', 'izin' => ['barang.lihat', 'barang.kelola'], 'subgroup' => 'Stok Barang'],
                        ['label' => 'Saldo Stok', 'route' => 'saldo-stok-barang.index', 'active' => ['saldo-stok-barang.*'], 'initial' => 'SS', 'izin' => ['barang.lihat', 'barang.kelola'], 'subgroup' => 'Stok Barang'],
                        ['label' => 'Mutasi Stok', 'route' => 'mutasi-stok-barang.index', 'active' => ['mutasi-stok-barang.*'], 'initial' => 'MS', 'izin' => ['barang.lihat', 'barang.kelola'], 'subgroup' => 'Stok Barang'],
                        ['label' => 'Peminjaman Barang', 'route' => 'peminjaman-barang.index', 'active' => ['peminjaman-barang.*'], 'initial' => 'PB', 'izin' => ['barang.lihat', 'barang.peminjaman_kelola'], 'subgroup' => 'Peminjaman'],
                        ['label' => 'Pengajuan Barang', 'route' => 'pengajuan-barang.index', 'active' => ['pengajuan-barang.*'], 'initial' => 'PJ', 'izin' => 'barang.peminjaman_kelola', 'subgroup' => 'Peminjaman'],
                        ['label' => 'Pengembalian Barang', 'route' => 'pengembalian-barang.index', 'active' => ['pengembalian-barang.*'], 'initial' => 'KM', 'izin' => ['barang.peminjaman_kelola'], 'subgroup' => 'Peminjaman'],
                        ['label' => 'Rekap Peminjaman', 'route' => 'rekap-peminjaman-barang.index', 'active' => ['rekap-peminjaman-barang.*'], 'initial' => 'RP', 'izin' => ['barang.lihat', 'barang.peminjaman_kelola'], 'subgroup' => 'Peminjaman'],
                        ['label' => 'Laporan Inventaris', 'route' => 'laporan-inventaris-bulanan.index', 'active' => ['laporan-inventaris-bulanan.*'], 'initial' => 'LI', 'izin' => ['barang.lihat', 'barang.kelola'], 'subgroup' => 'Laporan'],
                        ['label' => 'Kategori Barang', 'route' => 'kategori-barang.index', 'active' => ['kategori-barang.*'], 'initial' => 'KB', 'izin' => ['barang.lihat', 'barang.kelola'], 'subgroup' => 'Pengaturan'],
                        ['label' => 'Satuan Barang', 'route' => 'satuan-barang.index', 'active' => ['satuan-barang.*'], 'initial' => 'SB', 'izin' => ['barang.lihat', 'barang.kelola'], 'subgroup' => 'Pengaturan'],
                        ['label' => 'Lokasi Barang', 'route' => 'lokasi-barang.index', 'active' => ['lokasi-barang.*'], 'initial' => 'LK', 'izin' => ['barang.lihat', 'barang.kelola'], 'subgroup' => 'Pengaturan'],
                        ['label' => 'Sumber Perolehan', 'route' => 'sumber-perolehan-barang.index', 'active' => ['sumber-perolehan-barang.*'], 'initial' => 'SP', 'izin' => ['barang.lihat', 'barang.kelola'], 'subgroup' => 'Pengaturan'],
                        ['label' => 'Pengaturan Inventaris', 'route' => 'pengaturan-inventaris.index', 'active' => ['pengaturan-inventaris.*'], 'initial' => 'PI', 'izin' => 'barang.kelola', 'subgroup' => 'Pengaturan'],
                    ],
                ],
                [
                    'id' => 'sistem',
                    'title' => 'Sistem',
                    'items' => [
                        ['label' => 'Akun Pegawai', 'route' => 'akun-pegawai.index', 'active' => ['akun-pegawai.*'], 'initial' => 'AP', 'izin' => ['akun.lihat', 'akun.kelola']],
                        ['label' => 'Akun Siswa', 'route' => 'akun-siswa.index', 'active' => ['akun-siswa.*'], 'initial' => 'AS', 'izin' => ['akun_siswa.lihat', 'akun_siswa.kelola', 'akun_siswa.cetak']],
                        ['label' => 'Akun Orang Tua', 'route' => 'akun-orang-tua.index', 'active' => ['akun-orang-tua.*'], 'initial' => 'AO', 'izin' => ['akun_orang_tua.lihat', 'akun_orang_tua.kelola', 'akun_orang_tua.cetak']],
                        ['label' => 'Role & Hak Akses', 'route' => 'peran.index', 'active' => ['peran.*'], 'initial' => 'RA', 'izin' => ['peran.lihat', 'peran.kelola']],
                        ['label' => 'Aktivitas Login', 'route' => 'aktivitas-login.index', 'active' => ['aktivitas-login.*'], 'initial' => 'AL', 'izin' => 'aktivitas_login.lihat'],
                        ['label' => 'Backup & Restore', 'route' => 'cadangan-database.index', 'active' => ['cadangan-database.*'], 'initial' => 'BR', 'izin' => 'cadangan_database.kelola'],
                    ],
                ],
            ];

            $sidebarPegawaiSections = [
                [
                    'id' => 'utama',
                    'title' => 'Utama',
                    'items' => [
                        ['label' => 'Dashboard', 'route' => 'beranda', 'active' => ['beranda'], 'initial' => 'DB', 'izin' => 'beranda.akses'],
                        ['label' => 'Laporkan Kejadian', 'route' => 'laporan-pembinaan-siswa.create', 'active' => ['laporan-pembinaan-siswa.create'], 'initial' => 'LK', 'izin' => 'poin_siswa.lapor', 'pegawai_only' => true],
                        ['label' => 'Laporan Saya', 'route' => 'laporan-saya.index', 'active' => ['laporan-saya.*'], 'initial' => 'LS', 'izin' => 'poin_siswa.lapor', 'pegawai_only' => true],
                    ],
                ],
                [
                    'id' => 'verifikasi-poin',
                    'title' => 'Tugas Pembinaan',
                    'items' => [
                        ['label' => 'Pemeriksaan & Pengesahan', 'route' => 'pusat-verifikasi-pelanggaran.index', 'active' => ['pusat-verifikasi-pelanggaran.*'], 'initial' => 'VP', 'izin' => ['poin_siswa.lihat', 'poin_siswa.verifikasi_bk', 'poin_siswa.sahkan_wakil'], 'peran' => ['bk', 'pimpinan', 'wakil_pimpinan_kesiswaan']],
                        ['label' => 'Pelaksanaan Sanksi Siswa', 'route' => 'sanksi-poin-siswa.index', 'active' => ['sanksi-poin-siswa.*', 'bukti-pelaksanaan-sanksi.*'], 'initial' => 'PS', 'izin' => ['poin_siswa.lihat', 'poin_siswa.sanksi_kelola']],
                        ['label' => 'Peringatan Dini Siswa', 'route' => 'peringatan-dini-siswa.index', 'active' => ['peringatan-dini-siswa.*'], 'initial' => 'PD', 'izin' => 'poin_siswa.lihat'],
                        ['label' => 'Pendampingan Siswa', 'route' => 'pendampingan-siswa.index', 'active' => ['pendampingan-siswa.*'], 'initial' => 'PD', 'izin' => 'poin_siswa.lihat'],
                        ['label' => 'Batas Proses Pelanggaran', 'route' => 'pengaturan-batas-proses-pelanggaran.index', 'active' => ['pengaturan-batas-proses-pelanggaran.*'], 'initial' => 'BP', 'izin' => 'poin_siswa.pengaturan'],
                    ],
                ],
                [
                    'id' => 'layanan-sarpras',
                    'title' => 'Layanan Sarpras',
                    'items' => [
                        ['label' => 'Katalog Barang', 'route' => 'katalog-barang.index', 'active' => ['katalog-barang.*'], 'initial' => 'KB', 'izin' => null, 'pegawai_only' => true],
                        ['label' => 'Pengajuan Saya', 'route' => 'pengajuan-barang-saya.index', 'active' => ['pengajuan-barang-saya.*'], 'initial' => 'PS', 'izin' => null, 'pegawai_only' => true],
                        ['label' => 'Pengajuan Barang', 'route' => 'pengajuan-barang.index', 'active' => ['pengajuan-barang.*'], 'initial' => 'PJ', 'izin' => 'barang.peminjaman_kelola'],
                    ],
                ],
                [
                    'id' => 'piket-siswa',
                    'title' => 'Piket Siswa',
                    'items' => [
                        ['label' => 'Kelola Jadwal Piket', 'route' => 'jadwal-piket-guru.index', 'active' => ['jadwal-piket-guru.*'], 'initial' => 'GP', 'izin' => 'piket_guru.kelola'],
                        ['label' => 'Jadwal Piket Saya', 'route' => 'jadwal-piket-saya.index', 'active' => ['jadwal-piket-saya.*', 'piket-kehadiran-siswa.*'], 'initial' => 'JP', 'izin' => 'piket_guru.lihat_pribadi', 'peran' => 'guru_mapel'],
                    ],
                ],
                [
                    'id' => 'tugas-guru-pl',
                    'title' => 'Tugas Guru PL',
                    'items' => [
                        ['label' => 'Presensi Siswa Hari Ini', 'route' => 'rekap-absensi-harian.index', 'active' => ['rekap-absensi-harian.*'], 'initial' => 'PS', 'izin' => 'absensi.koreksi_hari_ini', 'peran' => 'guru_pl'],
                    ],
                ],
                [
                    'id' => 'kegiatan-ibadah',
                    'title' => 'Kegiatan Ibadah',
                    'items' => [
                        ['label' => 'Kegiatan Ibadah', 'route' => 'kegiatan-ibadah.index', 'active' => ['kegiatan-ibadah.*'], 'initial' => 'KI', 'izin' => 'ibadah.pengaturan_kelola'],
                        ['label' => 'Jadwal Ibadah', 'route' => 'jadwal-kegiatan-ibadah.index', 'active' => ['jadwal-kegiatan-ibadah.*'], 'initial' => 'JI', 'izin' => 'ibadah.pengaturan_kelola'],
                        ['label' => 'Pengaturan Berhalangan', 'route' => 'pengaturan-berhalangan-ibadah.index', 'active' => ['pengaturan-berhalangan-ibadah.*'], 'initial' => 'PB', 'izin' => 'ibadah.pengaturan_kelola'],
                        ['label' => 'Scan Ibadah Siswa', 'route' => 'scan-kegiatan-ibadah.index', 'active' => ['scan-kegiatan-ibadah.*'], 'initial' => 'SI', 'izin' => 'ibadah.scan', 'blank' => true, 'scan_ibadah_only' => true],
                        ['label' => 'Scan Berhalangan', 'route' => 'scan-berhalangan-ibadah.index', 'active' => ['scan-berhalangan-ibadah.*'], 'initial' => 'SB', 'izin' => null, 'blank' => true, 'scan_berhalangan_only' => true],
                        ['label' => 'Konfirmasi Privat', 'route' => 'konfirmasi-berhalangan-ibadah.index', 'active' => ['konfirmasi-berhalangan-ibadah.*'], 'initial' => 'KP', 'izin' => null, 'konfirmasi_berhalangan_only' => true],
                        ['label' => 'Rekap Berhalangan', 'route' => 'rekap-berhalangan-ibadah.index', 'active' => ['rekap-berhalangan-ibadah.*'], 'initial' => 'RB', 'izin' => null, 'konfirmasi_berhalangan_only' => true],
                        ['label' => 'Rekap Ibadah Siswa', 'route' => 'rekap-kegiatan-ibadah.index', 'active' => ['rekap-kegiatan-ibadah.index', 'rekap-kegiatan-ibadah.koreksi.*'], 'initial' => 'RI', 'izin' => 'ibadah.rekap', 'rekap_ibadah_only' => true],
                        ['label' => 'Ringkasan Ibadah Bulanan', 'route' => 'rekap-kegiatan-ibadah.bulanan', 'active' => ['rekap-kegiatan-ibadah.bulanan'], 'initial' => 'BI', 'izin' => 'ibadah.rekap', 'ringkasan_ibadah_only' => true],
                    ],
                ],
                [
                    'id' => 'wali-kelas',
                    'title' => 'Wali Kelas',
                    'items' => [
                        ['label' => 'Kelas Wali Saya', 'route' => 'kelas-wali.index', 'active' => ['kelas-wali.*', 'siswa.show'], 'initial' => 'KL', 'izin' => 'kelas.lihat', 'peran' => 'wali_kelas'],
                        ['label' => 'Jadwal Kelas Saya', 'route' => 'jadwal-kelas-saya.index', 'active' => ['jadwal-kelas-saya.*'], 'initial' => 'JK', 'izin' => 'jadwal.lihat', 'peran' => 'wali_kelas'],
                        ['label' => 'Akun Siswa Kelas', 'route' => 'akun-siswa.index', 'active' => ['akun-siswa.*'], 'initial' => 'AS', 'izin' => ['akun_siswa.lihat', 'akun_siswa.cetak'], 'peran' => 'wali_kelas'],
                        ['label' => 'Akun Orang Tua Kelas', 'route' => 'akun-orang-tua.index', 'active' => ['akun-orang-tua.*'], 'initial' => 'AO', 'izin' => ['akun_orang_tua.lihat', 'akun_orang_tua.cetak'], 'peran' => 'wali_kelas'],
                        ['label' => 'Rekap Presensi Siswa', 'route' => 'rekap-absensi-harian.index', 'active' => ['rekap-absensi-harian.*'], 'initial' => 'RA', 'izin' => ['absensi.lihat', 'absensi.koreksi'], 'peran' => 'wali_kelas'],
                        ['label' => 'Laporan Presensi Siswa', 'route' => 'laporan-absensi.index', 'active' => ['laporan-absensi.*'], 'initial' => 'LA', 'izin' => 'absensi.laporan', 'peran' => 'wali_kelas'],
                        ['label' => 'Laporan Siswa Kelas', 'route' => 'laporan-pembinaan-siswa.index', 'active' => ['laporan-pembinaan-siswa.*', 'tindak-lanjut-pembinaan-siswa.*'], 'initial' => 'LS', 'izin' => ['poin_siswa.lihat', 'poin_siswa.lapor'], 'peran' => 'wali_kelas'],
                        ['label' => 'Pendampingan Siswa Kelas', 'route' => 'pendampingan-siswa.index', 'active' => ['pendampingan-siswa.*'], 'initial' => 'PD', 'izin' => 'poin_siswa.lihat', 'peran' => 'wali_kelas'],
                        ['label' => 'Rekap Poin Kelas', 'route' => 'rekap-poin-siswa.index', 'active' => ['rekap-poin-siswa.*'], 'initial' => 'RP', 'izin' => 'poin_siswa.lihat', 'peran' => 'wali_kelas'],
                    ],
                ],
                [
                    'id' => 'guru-wali',
                    'title' => 'Guru Wali',
                    'items' => [
                        ['label' => 'Siswa Wali Saya', 'route' => 'siswa-wali-saya.index', 'active' => ['siswa-wali-saya.*', 'siswa.show'], 'initial' => 'SW', 'izin' => 'guru_wali.lihat', 'peran' => 'guru_wali'],
                        ['label' => 'Laporan Siswa Wali', 'route' => 'pembinaan-siswa-wali.index', 'active' => ['pembinaan-siswa-wali.*'], 'initial' => 'LS', 'izin' => ['guru_wali.lihat', 'poin_siswa.lihat'], 'peran' => 'guru_wali'],
                        ['label' => 'Pendampingan Siswa Wali', 'route' => 'pendampingan-siswa-wali.index', 'active' => ['pendampingan-siswa-wali.*'], 'initial' => 'PD', 'izin' => ['guru_wali.lihat', 'poin_siswa.lihat'], 'peran' => 'guru_wali'],
                        ['label' => 'Rekap Poin Siswa Wali', 'route' => 'rekap-poin-siswa-wali.index', 'active' => ['rekap-poin-siswa-wali.*'], 'initial' => 'RP', 'izin' => ['guru_wali.lihat', 'poin_siswa.lihat'], 'peran' => 'guru_wali'],
                    ],
                ],
                [
                    'id' => 'pembelajaran',
                    'title' => 'Pembelajaran',
                    'items' => [
                        ['label' => 'Jadwal Mengajar Saya', 'route' => 'jadwal-saya.index', 'active' => ['jadwal-saya.*'], 'initial' => 'JP', 'izin' => 'jadwal.pribadi', 'peran' => 'guru_mapel'],
                        ['label' => 'Input Nilai', 'route' => 'input-nilai.index', 'active' => ['input-nilai.*'], 'initial' => 'IN', 'izin' => 'nilai.input', 'peran' => 'guru_mapel'],
                        ['label' => 'Hasil Survei Saya', 'route' => 'hasil-survei-saya.index', 'active' => ['hasil-survei-saya.*'], 'initial' => 'HS', 'izin' => 'survei.hasil_pribadi', 'peran' => 'guru_mapel'],
                        ['label' => 'Perangkat Ajar Saya', 'route' => 'perangkat-ajar-saya.index', 'active' => ['perangkat-ajar-saya.*'], 'initial' => 'PA', 'izin' => 'perangkat_ajar.upload', 'peran' => 'guru_mapel'],
                    ],
                ],
                [
                    'id' => 'kehadiran-saya',
                    'title' => 'Kehadiran Saya',
                    'items' => [
                        ['label' => 'Rekap Presensi Saya', 'route' => 'absensi-pegawai-saya.rekap', 'active' => ['absensi-pegawai-saya.rekap'], 'initial' => 'RS', 'izin' => 'absensi_pegawai.pribadi', 'pegawai_only' => true],
                        ['label' => 'Laporan Presensi Saya', 'route' => 'absensi-pegawai-saya.laporan', 'active' => ['absensi-pegawai-saya.laporan', 'absensi-pegawai-saya.cetak'], 'initial' => 'LS', 'izin' => 'absensi_pegawai.pribadi', 'pegawai_only' => true],
                    ],
                ],
                [
                    'id' => 'akun-saya',
                    'title' => 'Akun Saya',
                    'items' => [
                        ['label' => 'Data Saya', 'route' => 'profil-pegawai.edit', 'active' => ['profil-pegawai.*'], 'initial' => 'DS', 'izin' => 'pegawai.profil', 'pegawai_only' => true],
                    ],
                ],
            ];

            $sidebarSiswaSections = [
                [
                    'id' => 'utama',
                    'title' => 'Utama',
                    'items' => [
                        ['label' => 'Dashboard', 'route' => 'beranda', 'active' => ['beranda'], 'initial' => 'DB', 'izin' => 'beranda.akses'],
                    ],
                ],
                [
                    'id' => 'informasi-saya',
                    'title' => 'Informasi Saya',
                    'items' => [
                        ['label' => 'Ujian Saya', 'route' => 'ujian-saya.index', 'active' => ['ujian-saya.*'], 'initial' => 'US', 'izin' => null],
                        ['label' => 'Nilai Saya', 'route' => 'nilai-saya.index', 'active' => ['nilai-saya.*'], 'initial' => 'NS', 'izin' => null],
                        ['label' => 'Progress Kasus Saya', 'route' => 'progress-kasus-siswa.index', 'active' => ['progress-kasus-siswa.*'], 'initial' => 'PK', 'izin' => null],
                    ],
                ],
                [
                    'id' => 'akun-saya',
                    'title' => 'Akun Saya',
                    'items' => [
                        ['label' => 'Profil & Akun', 'route' => 'profil-siswa.show', 'active' => ['profil-siswa.*', 'kata-sandi.*'], 'initial' => 'PA', 'izin' => null],
                    ],
                ],
            ];

            $sidebarOrangTuaSections = [
                [
                    'id' => 'utama',
                    'title' => 'Utama',
                    'items' => [
                        ['label' => 'Dashboard', 'route' => 'beranda', 'active' => ['beranda'], 'initial' => 'DB', 'izin' => 'beranda.akses'],
                    ],
                ],
                [
                    'id' => 'informasi-anak',
                    'title' => 'Informasi Anak',
                    'items' => [
                        ['label' => 'Presensi Anak', 'route' => 'presensi-anak.index', 'active' => ['presensi-anak.*'], 'initial' => 'PA', 'izin' => null],
                        ['label' => 'Akademik Anak', 'route' => 'akademik-anak.index', 'active' => ['akademik-anak.*'], 'initial' => 'AA', 'izin' => null],
                        ['label' => 'Pembinaan & Poin', 'route' => 'pembinaan-poin-anak.index', 'active' => ['pembinaan-poin-anak.*'], 'initial' => 'PP', 'izin' => null],
                    ],
                ],
                [
                    'id' => 'akun-saya',
                    'title' => 'Akun Saya',
                    'items' => [
                        ['label' => 'Ganti Password', 'route' => 'kata-sandi.edit', 'active' => ['kata-sandi.*'], 'initial' => 'KS', 'izin' => null],
                    ],
                ],
            ];

            if ($pakaiSidebarOrangTua) {
                $semuaSidebarSections = $sidebarOrangTuaSections;
            } elseif ($pakaiSidebarSiswa) {
                $semuaSidebarSections = $sidebarSiswaSections;
            } elseif ($pakaiSidebarPegawai) {
                $semuaSidebarSections = $sidebarPegawaiSections;
            }

            $sidebarSections = collect($semuaSidebarSections)
                ->map(function (array $section) use ($bolehMelihatMenu, $penggunaAktif, $dapatScanIbadahHariIni, $dapatRekapIbadahHariIni, $dapatRingkasanIbadahBulanan, $dapatScanBerhalanganIbadah, $dapatKonfirmasiBerhalanganIbadah) {
                    $section['items'] = collect($section['items'])
                        ->filter(function (array $item) use ($bolehMelihatMenu, $penggunaAktif, $dapatScanIbadahHariIni, $dapatRekapIbadahHariIni, $dapatRingkasanIbadahBulanan, $dapatScanBerhalanganIbadah, $dapatKonfirmasiBerhalanganIbadah) {
                            if (($item['pegawai_only'] ?? false) && ! $penggunaAktif?->pegawai_id) {
                                return false;
                            }

                            if (($item['administrator_only'] ?? false) && ! $penggunaAktif?->administrator()) {
                                return false;
                            }

                            if (isset($item['peran']) && ! $penggunaAktif?->memilikiPeran($item['peran'])) {
                                return false;
                            }

                            if (($item['scan_ibadah_only'] ?? false) && ! $dapatScanIbadahHariIni) {
                                return false;
                            }

                            if (($item['rekap_ibadah_only'] ?? false) && ! $dapatRekapIbadahHariIni) {
                                return false;
                            }

                            if (($item['ringkasan_ibadah_only'] ?? false) && ! $dapatRingkasanIbadahBulanan) {
                                return false;
                            }

                            if (($item['scan_berhalangan_only'] ?? false) && ! $dapatScanBerhalanganIbadah) {
                                return false;
                            }

                            if (($item['konfirmasi_berhalangan_only'] ?? false) && ! $dapatKonfirmasiBerhalanganIbadah) {
                                return false;
                            }

                            return $bolehMelihatMenu($item['izin'] ?? null);
                        })
                        ->map(function (array $item) {
                            $activePatterns = (array) ($item['active'] ?? $item['route']);
                            $item['is_active'] = request()->routeIs(...$activePatterns);

                            return $item;
                        })
                        ->values()
                        ->all();
                    $section['active'] = collect($section['items'])->contains('is_active', true);
                    $section['groups'] = collect($section['items'])
                        ->groupBy(fn (array $item) => $item['subgroup'] ?? '')
                        ->map(fn ($items, $nama) => [
                            'name' => $nama,
                            'items' => $items->values()->all(),
                        ])
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

                @if ($penggunaAktif?->administrator())
                    <div class="sidebar-search" data-sidebar-search>
                        <label for="sidebar-menu-search" class="sr-only">Cari menu</label>
                        <div class="sidebar-search-control">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <circle cx="11" cy="11" r="7"></circle>
                                <path d="m20 20-4-4"></path>
                            </svg>
                            <input
                                id="sidebar-menu-search"
                                type="search"
                                placeholder="Cari menu..."
                                autocomplete="off"
                                data-sidebar-search-input
                            >
                            <button type="button" class="sidebar-search-clear" title="Hapus pencarian" aria-label="Hapus pencarian" data-sidebar-search-clear hidden>
                                <svg aria-hidden="true" viewBox="0 0 24 24">
                                    <path d="M18 6 6 18M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                @endif

                <nav class="sidebar-nav" data-sidebar-nav>
                    @foreach ($sidebarSections as $section)
                        <details
                            class="sidebar-section {{ $section['active'] ? 'has-active' : '' }}"
                            data-sidebar-section
                            data-sidebar-section-id="{{ $section['id'] }}"
                            data-sidebar-section-title="{{ str($section['title'])->lower() }}"
                            @if ($section['active']) open @endif
                        >
                            <summary class="sidebar-section-summary">
                                <span class="sidebar-section-title">{{ $section['title'] }}</span>
                                <span class="sidebar-section-count">{{ count($section['items']) }}</span>
                                <svg class="sidebar-section-chevron" aria-hidden="true" viewBox="0 0 24 24">
                                    <path d="m9 18 6-6-6-6"></path>
                                </svg>
                            </summary>

                            <div class="sidebar-section-content">
                                @foreach ($section['groups'] as $group)
                                    <div
                                        class="sidebar-subgroup"
                                        data-sidebar-subgroup
                                        data-sidebar-subgroup-title="{{ str($group['name'])->lower() }}"
                                    >
                                        @if ($group['name'] !== '')
                                            <p class="sidebar-subgroup-title">{{ $group['name'] }}</p>
                                        @endif

                                        <div class="sidebar-links">
                                            @foreach ($group['items'] as $item)
                                                <a
                                                    href="{{ route($item['route']) }}"
                                                    class="sidebar-link {{ $item['is_active'] ? 'active' : '' }}"
                                                    data-sidebar-item
                                                    data-sidebar-search-text="{{ str($section['title'] . ' ' . ($group['name'] ?? '') . ' ' . $item['label'])->lower() }}"
                                                    @if ($item['is_active']) aria-current="page" @endif
                                                    @if ($item['blank'] ?? false) target="_blank" rel="noopener" @endif
                                                >
                                                    <span class="sidebar-link-initial">{{ $item['initial'] }}</span>
                                                    <span class="sidebar-link-label">{{ $item['label'] }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endforeach

                    <div class="sidebar-search-empty" data-sidebar-search-empty hidden>
                        Menu tidak ditemukan.
                    </div>
                </nav>

                <div class="sidebar-foot">
                    <span>NUSA</span><br>
                    <span>Data sekolah terpadu.</span>
                </div>
            </aside>

            <label for="sidebar-toggle" class="sidebar-backdrop" aria-hidden="true"></label>

            <div class="app-content">
                <header class="app-topbar">
                    <label for="sidebar-toggle" class="mobile-menu-button" title="Buka menu utama" aria-label="Buka menu utama">
                        <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        <span class="sr-only">Menu</span>
                    </label>

                    <div class="topbar-title">
                        <span class="topbar-eyebrow">NUSA</span>
                        <span class="topbar-current">@yield('title', 'NUSA')</span>
                    </div>

                    @auth
                        <div class="topbar-actions">
                            <details class="topbar-menu notification-menu" data-notification-menu>
                                <summary class="topbar-icon-button" title="Notifikasi" aria-label="Notifikasi, {{ $jumlahNotifikasiBelumDibaca }} belum dibaca">
                                    <svg aria-hidden="true" viewBox="0 0 24 24">
                                        <path d="M10.27 21a2 2 0 0 0 3.46 0"></path>
                                        <path d="M3.26 15.33A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.67C19.41 13.82 18 12.24 18 8a6 6 0 0 0-12 0c0 4.24-1.41 5.82-2.74 7.33"></path>
                                    </svg>
                                    <span
                                        class="notification-count"
                                        data-notification-count
                                        @if ($jumlahNotifikasiBelumDibaca === 0) hidden @endif
                                    >{{ $jumlahNotifikasiBelumDibaca > 99 ? '99+' : $jumlahNotifikasiBelumDibaca }}</span>
                                </summary>

                                <div class="topbar-popover notification-popover">
                                    <header class="notification-popover-head">
                                        <strong>Notifikasi</strong>
                                        <form
                                            action="{{ route('notifikasi.baca-semua') }}"
                                            method="POST"
                                            data-notification-read-all
                                            @if ($jumlahNotifikasiBelumDibaca === 0) hidden @endif
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="notification-text-button">Tandai semua dibaca</button>
                                        </form>
                                    </header>

                                    <div class="notification-popover-list" data-notification-list>
                                        @forelse ($notifikasiTerbaru as $item)
                                            <form action="{{ route('notifikasi.buka', $item) }}" method="POST" class="notification-popover-form">
                                                @csrf
                                                <button type="submit" class="notification-popover-item {{ $item->masihBelumDibaca() ? 'unread' : '' }}">
                                                    <span class="notification-dot" aria-hidden="true"></span>
                                                    <span class="notification-popover-copy">
                                                        <span class="notification-popover-title">{{ $item->judul }}</span>
                                                        <span class="notification-popover-message">{{ $item->pesan }}</span>
                                                        <time class="notification-popover-time" datetime="{{ $item->created_at->toIso8601String() }}">
                                                            {{ $item->created_at->diffForHumans() }}
                                                        </time>
                                                    </span>
                                                </button>
                                            </form>
                                        @empty
                                            <div class="notification-popover-empty">Belum ada notifikasi untuk akun ini.</div>
                                        @endforelse
                                    </div>

                                    <a href="{{ route('notifikasi.index') }}" class="notification-popover-foot">Lihat semua notifikasi</a>
                                </div>
                            </details>

                            <details class="topbar-menu account-menu-dropdown">
                                <summary class="account-trigger" title="Menu akun" aria-label="Buka menu akun">
                                    <span class="account-avatar" aria-hidden="true">
                                        <span>{{ $inisialPengguna ?: 'N' }}</span>
                                        @if ($fotoPenggunaUrl)
                                            <img src="{{ $fotoPenggunaUrl }}" alt="" onerror="this.remove()">
                                        @endif
                                    </span>
                                    <span class="account-identity">
                                        <strong>{{ $namaPenggunaAktif }}</strong>
                                        <small>{{ $labelPeranAktif }}</small>
                                    </span>
                                    <svg class="account-chevron" aria-hidden="true" viewBox="0 0 24 24">
                                        <path d="m6 9 6 6 6-6"></path>
                                    </svg>
                                </summary>

                                <div class="topbar-popover account-popover">
                                    <div class="account-popover-head">
                                        <strong>{{ $namaPenggunaAktif }}</strong>
                                        <span>{{ $labelPeranAktif }}</span>
                                        <span>Username: {{ $penggunaAktif->username }}</span>
                                    </div>

                                    @if ($penggunaAktif->pegawai_id && $penggunaAktif->memilikiIzin('pegawai.profil'))
                                        <a href="{{ route('profil-pegawai.edit') }}" class="account-dropdown-link">
                                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                                <path d="M20 21a8 8 0 0 0-16 0"></path>
                                                <circle cx="12" cy="7" r="4"></circle>
                                            </svg>
                                            <span>Profil Saya</span>
                                        </a>
                                    @elseif ($penggunaAktif->akunSiswa() || $penggunaAktif->memilikiPeran('siswa'))
                                        <a href="{{ route('profil-siswa.show') }}" class="account-dropdown-link">
                                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                                <path d="M20 21a8 8 0 0 0-16 0"></path>
                                                <circle cx="12" cy="7" r="4"></circle>
                                            </svg>
                                            <span>Profil & Akun</span>
                                        </a>
                                    @elseif ($penggunaAktif->akunOrangTua() || $penggunaAktif->memilikiPeran('orang_tua'))
                                        <a href="{{ route('profil-orang-tua.edit') }}" class="account-dropdown-link">
                                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                                <path d="M20 21a8 8 0 0 0-16 0"></path>
                                                <circle cx="12" cy="7" r="4"></circle>
                                            </svg>
                                            <span>Profil & Akun</span>
                                        </a>
                                    @endif

                                    <a href="{{ route('kata-sandi.edit') }}" class="account-dropdown-link">
                                        <svg aria-hidden="true" viewBox="0 0 24 24">
                                            <circle cx="7.5" cy="15.5" r="5.5"></circle>
                                            <path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"></path>
                                        </svg>
                                        <span>Ganti Kata Sandi</span>
                                    </a>

                                    <button type="button" class="account-dropdown-link" data-pwa-install hidden>
                                        <svg aria-hidden="true" viewBox="0 0 24 24">
                                            <path d="M12 3v12"></path>
                                            <path d="m7 10 5 5 5-5"></path>
                                            <path d="M5 21h14"></path>
                                        </svg>
                                        <span>Pasang NUSA</span>
                                    </button>

                                    <form action="{{ route('logout') }}" method="POST" class="logout-form">
                                        @csrf
                                        <button type="submit" class="account-dropdown-link danger">
                                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                                <path d="M10 17l5-5-5-5M15 12H3"></path>
                                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                            </svg>
                                            <span>Keluar</span>
                                        </button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    @endauth
                </header>

                <main class="content-shell">
                    @yield('content')
                </main>
            </div>
        </div>
        @auth
            <script>
                (() => {
                    const sidebarSections = [...document.querySelectorAll('[data-sidebar-section]')];
                    const sidebarSearchInput = document.querySelector('[data-sidebar-search-input]');
                    const sidebarSearchClear = document.querySelector('[data-sidebar-search-clear]');
                    const sidebarSearchEmpty = document.querySelector('[data-sidebar-search-empty]');
                    const sidebarStorageKey = @js('nusa.sidebar.terbuka.' . $penggunaAktif->id);
                    let sedangMencariMenu = false;
                    let sedangMengaturSidebar = false;

                    const normalisasiPencarian = (nilai) => nilai
                        .toLocaleLowerCase('id-ID')
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .trim();

                    const ambilBagianTersimpan = () => {
                        try {
                            return window.localStorage.getItem(sidebarStorageKey);
                        } catch (error) {
                            return null;
                        }
                    };

                    const simpanBagianTerbuka = (id) => {
                        try {
                            if (id) {
                                window.localStorage.setItem(sidebarStorageKey, id);
                            } else {
                                window.localStorage.removeItem(sidebarStorageKey);
                            }
                        } catch (error) {
                            // Sidebar tetap dapat digunakan saat penyimpanan browser dibatasi.
                        }
                    };

                    const bukaSatuBagian = (bagian) => {
                        sedangMengaturSidebar = true;
                        sidebarSections.forEach((item) => {
                            item.open = item === bagian;
                        });
                        window.requestAnimationFrame(() => {
                            sedangMengaturSidebar = false;
                        });
                    };

                    if (sidebarSections.length > 0) {
                        const bagianAktif = sidebarSections.find((item) => item.classList.contains('has-active'));
                        const idTersimpan = ambilBagianTersimpan();
                        const bagianTersimpan = sidebarSections.find((item) => item.dataset.sidebarSectionId === idTersimpan);
                        const bagianAwal = bagianAktif || bagianTersimpan || sidebarSections[0];

                        bukaSatuBagian(bagianAwal);

                        sidebarSections.forEach((bagian) => {
                            bagian.addEventListener('toggle', () => {
                                if (sedangMencariMenu || sedangMengaturSidebar || !bagian.open) return;

                                sedangMengaturSidebar = true;
                                sidebarSections.forEach((item) => {
                                    if (item !== bagian) item.removeAttribute('open');
                                });
                                simpanBagianTerbuka(bagian.dataset.sidebarSectionId);
                                window.requestAnimationFrame(() => {
                                    sedangMengaturSidebar = false;
                                });
                            });
                        });
                    }

                    const saringMenu = () => {
                        const kataKunci = normalisasiPencarian(sidebarSearchInput?.value || '');
                        sedangMencariMenu = kataKunci !== '';
                        let jumlahCocok = 0;

                        sidebarSections.forEach((bagian) => {
                            const judulBagianCocok = normalisasiPencarian(bagian.dataset.sidebarSectionTitle || '')
                                .includes(kataKunci);
                            let jumlahCocokBagian = 0;

                            bagian.querySelectorAll('[data-sidebar-subgroup]').forEach((subgroup) => {
                                const judulSubgroupCocok = normalisasiPencarian(subgroup.dataset.sidebarSubgroupTitle || '')
                                    .includes(kataKunci);
                                let jumlahCocokSubgroup = 0;

                                subgroup.querySelectorAll('[data-sidebar-item]').forEach((item) => {
                                    const itemCocok = kataKunci === ''
                                        || judulBagianCocok
                                        || judulSubgroupCocok
                                        || normalisasiPencarian(item.dataset.sidebarSearchText || '').includes(kataKunci);

                                    item.hidden = !itemCocok;
                                    if (itemCocok) {
                                        jumlahCocokSubgroup++;
                                        jumlahCocokBagian++;
                                        jumlahCocok++;
                                    }
                                });

                                subgroup.hidden = jumlahCocokSubgroup === 0;
                            });

                            bagian.hidden = jumlahCocokBagian === 0;
                            if (kataKunci !== '' && jumlahCocokBagian > 0) {
                                bagian.open = true;
                            }
                        });

                        if (kataKunci === '') {
                            const bagianAktif = sidebarSections.find((item) => item.classList.contains('has-active'));
                            const idTersimpan = ambilBagianTersimpan();
                            const bagianTersimpan = sidebarSections.find((item) => item.dataset.sidebarSectionId === idTersimpan);
                            bukaSatuBagian(bagianAktif || bagianTersimpan || sidebarSections[0]);
                        }

                        if (sidebarSearchClear) sidebarSearchClear.hidden = kataKunci === '';
                        if (sidebarSearchEmpty) sidebarSearchEmpty.hidden = jumlahCocok > 0;
                    };

                    sidebarSearchInput?.addEventListener('input', saringMenu);
                    sidebarSearchClear?.addEventListener('click', () => {
                        sidebarSearchInput.value = '';
                        saringMenu();
                        sidebarSearchInput.focus();
                    });

                    document.addEventListener('keydown', (event) => {
                        if ((event.ctrlKey || event.metaKey) && event.key.toLocaleLowerCase('id-ID') === 'k' && sidebarSearchInput) {
                            event.preventDefault();
                            sidebarSearchInput.focus();
                            sidebarSearchInput.select();
                        }
                    });

                    const menus = [...document.querySelectorAll('.topbar-menu')];

                    menus.forEach((menu) => {
                        menu.addEventListener('toggle', () => {
                            if (!menu.open) return;

                            menus.forEach((item) => {
                                if (item !== menu) item.removeAttribute('open');
                            });
                        });
                    });

                    document.addEventListener('click', (event) => {
                        menus.forEach((menu) => {
                            if (menu.open && !menu.contains(event.target)) {
                                menu.removeAttribute('open');
                            }
                        });
                    });

                    const menuNotifikasi = document.querySelector('[data-notification-menu]');
                    const daftarNotifikasi = document.querySelector('[data-notification-list]');
                    const tombolBacaSemua = document.querySelector('[data-notification-read-all]');
                    const tokenCsrf = @js(csrf_token());
                    let sedangMemuatNotifikasi = false;

                    const buatElemenNotifikasi = (item) => {
                        const form = document.createElement('form');
                        form.action = item.url_buka;
                        form.method = 'POST';
                        form.className = 'notification-popover-form';

                        const csrf = document.createElement('input');
                        csrf.type = 'hidden';
                        csrf.name = '_token';
                        csrf.value = tokenCsrf;

                        const tombol = document.createElement('button');
                        tombol.type = 'submit';
                        tombol.className = `notification-popover-item${item.belum_dibaca ? ' unread' : ''}`;

                        const titik = document.createElement('span');
                        titik.className = 'notification-dot';
                        titik.setAttribute('aria-hidden', 'true');

                        const salinan = document.createElement('span');
                        salinan.className = 'notification-popover-copy';

                        const judul = document.createElement('span');
                        judul.className = 'notification-popover-title';
                        judul.textContent = item.judul;

                        const pesan = document.createElement('span');
                        pesan.className = 'notification-popover-message';
                        pesan.textContent = item.pesan;

                        const waktu = document.createElement('time');
                        waktu.className = 'notification-popover-time';
                        waktu.dateTime = item.dibuat_pada;
                        waktu.textContent = item.waktu_relatif;

                        salinan.append(judul, pesan, waktu);
                        tombol.append(titik, salinan);
                        form.append(csrf, tombol);

                        return form;
                    };

                    const perbaruiDaftarNotifikasi = (notifikasi) => {
                        if (!daftarNotifikasi) return;

                        daftarNotifikasi.replaceChildren();

                        if (!Array.isArray(notifikasi) || notifikasi.length === 0) {
                            const kosong = document.createElement('div');
                            kosong.className = 'notification-popover-empty';
                            kosong.textContent = 'Belum ada notifikasi untuk akun ini.';
                            daftarNotifikasi.append(kosong);
                            return;
                        }

                        daftarNotifikasi.append(...notifikasi.map(buatElemenNotifikasi));
                    };

                    const perbaruiNotifikasi = async () => {
                        if (sedangMemuatNotifikasi || document.visibilityState !== 'visible') return;

                        sedangMemuatNotifikasi = true;

                        try {
                            const respons = await fetch(@js(route('notifikasi.ringkasan')), {
                                headers: { 'Accept': 'application/json' },
                                cache: 'no-store',
                            });

                            if (!respons.ok) return;

                            const data = await respons.json();
                            const jumlah = Number(data.jumlah_belum_dibaca || 0);
                            const badge = document.querySelector('[data-notification-count]');
                            const tombol = badge?.closest('summary');

                            if (!badge || !tombol) return;

                            badge.textContent = jumlah > 99 ? '99+' : String(jumlah);
                            badge.hidden = jumlah === 0;
                            tombol.setAttribute('aria-label', `Notifikasi, ${jumlah} belum dibaca`);
                            if (tombolBacaSemua) tombolBacaSemua.hidden = jumlah === 0;
                            perbaruiDaftarNotifikasi(data.notifikasi);
                        } catch (error) {
                            // Kegagalan pembaruan notifikasi tidak mengganggu penggunaan halaman.
                        } finally {
                            sedangMemuatNotifikasi = false;
                        }
                    };

                    menuNotifikasi?.addEventListener('toggle', () => {
                        if (menuNotifikasi.open) perbaruiNotifikasi();
                    });

                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible') perbaruiNotifikasi();
                    });

                    perbaruiNotifikasi();
                    window.setInterval(perbaruiNotifikasi, 30000);
                })();
            </script>
            <script>
                (() => {
                    const polaTombolFilter = /^(terapkan|tampilkan)(\s|$)/i;
                    const formulir = [...document.forms].filter((form) => {
                        if (form.method.toLowerCase() !== 'get' || form.target === '_blank') {
                            return false;
                        }

                        const tombolFilter = [...form.querySelectorAll('button:not([type]), button[type="submit"], input[type="submit"]')]
                            .filter((tombol) => polaTombolFilter.test((tombol.value || tombol.textContent || '').trim()));
                        const dipaksaOtomatis = form.hasAttribute('data-auto-filter');
                        const kontrolRentang = form.querySelectorAll('input[type="date"], input[type="month"], input[type="week"]');
                        const halamanBertahap = new URL(form.action, window.location.href).pathname.includes('/kenaikan-kelas');

                        if (! dipaksaOtomatis && (tombolFilter.length === 0 || kontrolRentang.length > 1 || halamanBertahap)) {
                            return false;
                        }

                        form._tombolFilterOtomatis = tombolFilter;

                        return true;
                    });

                    formulir.forEach((form) => {
                        if (form.dataset.autoFilterReady === '1') {
                            return;
                        }

                        form.dataset.autoFilterReady = '1';
                        let pencarianTimer;
                        const tombolFilter = form._tombolFilterOtomatis || [];
                        const tombolPengirim = tombolFilter[0];

                        tombolFilter.forEach((tombol) => {
                            tombol.hidden = true;
                            tombol.setAttribute('aria-hidden', 'true');
                            tombol.tabIndex = -1;
                        });

                        const kirimFilter = () => {
                            if (form.dataset.submitting === '1' || ! form.checkValidity()) {
                                return;
                            }

                            form.dataset.submitting = '1';
                            form.setAttribute('aria-busy', 'true');
                            document.documentElement.style.cursor = 'progress';
                            form.requestSubmit(tombolPengirim);
                        };

                        form.querySelectorAll('select, input[type="checkbox"], input[type="radio"], input[type="date"], input[type="month"], input[type="week"]')
                            .forEach((kontrol) => kontrol.addEventListener('change', kirimFilter));

                        form.querySelectorAll('input[type="search"], input[type="text"], input[type="number"]')
                            .forEach((kontrol) => kontrol.addEventListener('input', () => {
                                clearTimeout(pencarianTimer);
                                pencarianTimer = window.setTimeout(kirimFilter, 650);
                            }));
                    });
                })();
            </script>
        @endauth
        @stack('scripts')
    </body>
</html>
