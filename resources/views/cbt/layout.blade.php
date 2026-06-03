<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'CBT NUSA')</title>

        @fonts
        <link rel="icon" href="{{ asset('images/logo-nusa.png') }}" type="image/png">

        <style>
            :root {
                --bg: #f6f8fb;
                --panel: #ffffff;
                --text: #18181b;
                --muted: #667085;
                --line: #dfe7f0;
                --primary: #15477A;
                --primary-dark: #0f355c;
                --primary-soft: #e8f0f8;
                --accent: #F1C40F;
                --accent-soft: #fff7d1;
                --danger: #b91c1c;
                --danger-soft: #fee2e2;
                --success: #047857;
                --success-soft: #d1fae5;
                --warning: #92400e;
                --warning-soft: #fef3c7;
                --shadow: 0 1px 2px rgba(21, 71, 122, .08), 0 18px 44px rgba(21, 71, 122, .11);
            }

            * {
                box-sizing: border-box;
            }

            html {
                min-height: 100%;
                scroll-behavior: smooth;
            }

            body {
                margin: 0;
                min-height: 100vh;
                min-height: 100svh;
                background: var(--bg);
                color: var(--text);
                font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                line-height: 1.5;
            }

            body.cbt-auth {
                background:
                    linear-gradient(135deg, rgba(21, 71, 122, .96), rgba(15, 53, 92, .9)),
                    var(--primary);
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

            .cbt-shell {
                width: min(100%, 1180px);
                margin: 0 auto;
                padding: 24px 18px 40px;
            }

            .cbt-topbar {
                position: sticky;
                top: 0;
                z-index: 20;
                border-bottom: 1px solid var(--line);
                background: rgba(255, 255, 255, .96);
                box-shadow: inset 0 -3px 0 var(--accent);
                backdrop-filter: blur(10px);
            }

            .topbar-inner {
                display: flex;
                width: min(100%, 1180px);
                min-height: 74px;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                margin: 0 auto;
                padding: 10px 18px;
            }

            .brand {
                display: flex;
                min-width: 0;
                align-items: center;
                gap: 12px;
            }

            .brand-mark {
                display: grid;
                width: 48px;
                height: 48px;
                flex: 0 0 auto;
                place-items: center;
                border: 1px solid rgba(241, 196, 15, .75);
                border-radius: 8px;
                background: #fff;
                padding: 5px;
                box-shadow: 0 8px 20px rgba(21, 71, 122, .14);
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
                font-size: 1.08rem;
                font-weight: 900;
                line-height: 1.1;
            }

            .brand-subtitle {
                display: block;
                color: var(--muted);
                font-size: .82rem;
                font-weight: 700;
            }

            .panel {
                border: 1px solid var(--line);
                border-radius: 8px;
                background: var(--panel);
                box-shadow: var(--shadow);
            }

            .panel-pad {
                padding: 22px;
            }

            .page-header {
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 20px;
            }

            .eyebrow {
                margin: 0 0 5px;
                color: var(--primary);
                font-size: .82rem;
                font-weight: 900;
                text-transform: uppercase;
            }

            .page-title,
            .panel-title {
                margin: 0;
                color: var(--text);
                letter-spacing: 0;
                line-height: 1.15;
            }

            .page-title {
                font-size: clamp(1.5rem, 4vw, 2.15rem);
            }

            .panel-title {
                font-size: 1.2rem;
            }

            .muted {
                color: var(--muted);
            }

            .button {
                display: inline-flex;
                min-height: 42px;
                align-items: center;
                justify-content: center;
                border: 1px solid transparent;
                border-radius: 8px;
                padding: 10px 14px;
                cursor: pointer;
                font-size: .93rem;
                font-weight: 900;
                line-height: 1.2;
                text-align: center;
            }

            .button-primary {
                border-color: var(--primary);
                background: var(--primary);
                color: #fff;
            }

            .button-primary:hover {
                background: var(--primary-dark);
            }

            .button-accent {
                border-color: var(--accent);
                background: var(--accent);
                color: #1f2937;
            }

            .button-muted {
                border-color: var(--line);
                background: #fff;
                color: var(--primary-dark);
            }

            .button-danger {
                border-color: #fecaca;
                background: var(--danger-soft);
                color: var(--danger);
            }

            .button-full {
                width: 100%;
            }

            .actions {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 10px;
            }

            .field {
                display: grid;
                gap: 7px;
            }

            .field label,
            .label {
                color: #344054;
                font-size: .9rem;
                font-weight: 900;
            }

            .input,
            .textarea {
                width: 100%;
                border: 1px solid #cfd8e3;
                border-radius: 8px;
                background: #fff;
                color: var(--text);
                outline: none;
            }

            .input {
                min-height: 46px;
                padding: 10px 12px;
            }

            .textarea {
                min-height: 116px;
                resize: vertical;
                padding: 11px 12px;
            }

            .input:focus,
            .textarea:focus {
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(21, 71, 122, .16);
            }

            .alert {
                margin: 0 0 16px;
                border: 1px solid #bfdbfe;
                border-radius: 8px;
                background: #eff6ff;
                padding: 12px 14px;
                color: var(--primary-dark);
                font-size: .9rem;
                font-weight: 800;
            }

            .alert-danger {
                border-color: #fecaca;
                background: var(--danger-soft);
                color: var(--danger);
            }

            .badge {
                display: inline-flex;
                min-height: 30px;
                align-items: center;
                border-radius: 999px;
                padding: 6px 10px;
                font-size: .78rem;
                font-weight: 900;
                white-space: nowrap;
            }

            .badge-primary {
                background: var(--primary-soft);
                color: var(--primary-dark);
            }

            .badge-success {
                background: var(--success-soft);
                color: var(--success);
            }

            .badge-warning {
                background: var(--warning-soft);
                color: var(--warning);
            }

            .badge-muted {
                background: #f4f4f5;
                color: #52525b;
            }

            .info-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 12px;
            }

            .info-item {
                border: 1px solid var(--line);
                border-radius: 8px;
                background: #fff;
                padding: 14px;
            }

            .info-label {
                margin: 0 0 4px;
                color: var(--muted);
                font-size: .78rem;
                font-weight: 900;
                text-transform: uppercase;
            }

            .info-value {
                margin: 0;
                color: var(--text);
                font-size: 1rem;
                font-weight: 900;
                line-height: 1.25;
                overflow-wrap: anywhere;
            }

            .auth-wrap {
                display: grid;
                min-height: 100vh;
                min-height: 100svh;
                place-items: center;
                padding: 24px 16px;
            }

            .auth-card {
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(320px, 420px);
                width: min(100%, 980px);
                overflow: hidden;
                border: 1px solid rgba(255, 255, 255, .35);
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 28px 80px rgba(0, 0, 0, .3);
            }

            .auth-hero {
                display: flex;
                min-height: 520px;
                flex-direction: column;
                justify-content: space-between;
                padding: 34px;
                background:
                    linear-gradient(135deg, rgba(21, 71, 122, .94), rgba(21, 71, 122, .72)),
                    var(--primary);
                color: #fff;
            }

            .auth-hero .brand-title,
            .auth-hero .brand-subtitle {
                color: #fff;
            }

            .auth-heading {
                max-width: 520px;
            }

            .auth-heading h1 {
                margin: 0;
                font-size: clamp(2rem, 5vw, 3.3rem);
                line-height: 1.05;
                letter-spacing: 0;
            }

            .auth-heading p {
                margin: 14px 0 0;
                color: rgba(255, 255, 255, .82);
                font-size: 1rem;
                line-height: 1.7;
            }

            .auth-form {
                display: grid;
                align-content: center;
                gap: 16px;
                padding: 34px;
            }

            .exam-layout {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 290px;
                gap: 18px;
                align-items: start;
            }

            .exam-main {
                display: grid;
                gap: 16px;
            }

            .exam-side {
                position: sticky;
                top: 94px;
                display: grid;
                gap: 12px;
            }

            .timer {
                display: grid;
                place-items: center;
                border: 1px solid rgba(241, 196, 15, .72);
                border-radius: 8px;
                background: linear-gradient(180deg, #fffbe7, #fff);
                padding: 16px;
                text-align: center;
            }

            .timer-value {
                color: var(--primary-dark);
                font-size: 2rem;
                font-weight: 950;
                line-height: 1;
                letter-spacing: 0;
            }

            .timer-label {
                margin: 7px 0 0;
                color: var(--muted);
                font-size: .8rem;
                font-weight: 900;
                text-transform: uppercase;
            }

            .question-card {
                scroll-margin-top: 96px;
            }

            .question-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 14px;
            }

            .question-number {
                display: inline-flex;
                min-width: 42px;
                height: 42px;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                background: var(--primary);
                color: #fff;
                font-weight: 950;
            }

            .question-title {
                margin: 0;
                font-size: 1.05rem;
                line-height: 1.45;
                white-space: pre-line;
            }

            .stimulus {
                margin: 12px 0 16px;
                border-left: 4px solid var(--accent);
                border-radius: 8px;
                background: #fffaf0;
                padding: 12px 14px;
                color: #334155;
                white-space: pre-line;
            }

            .option-list {
                display: grid;
                gap: 10px;
                margin-top: 14px;
            }

            .option-card {
                display: grid;
                grid-template-columns: auto minmax(0, 1fr);
                gap: 10px;
                align-items: start;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: #fff;
                padding: 12px;
                cursor: pointer;
            }

            .option-card:hover {
                border-color: rgba(21, 71, 122, .45);
                background: #fbfdff;
            }

            .option-card input {
                width: 19px;
                height: 19px;
                margin-top: 2px;
                accent-color: var(--primary);
            }

            .option-code {
                display: inline-flex;
                min-width: 28px;
                height: 28px;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                background: var(--primary-soft);
                color: var(--primary-dark);
                font-weight: 950;
            }

            .option-text {
                color: #344054;
                font-weight: 760;
                white-space: pre-line;
            }

            .statement-row,
            .matching-row {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 12px;
                align-items: center;
                border: 1px solid var(--line);
                border-radius: 8px;
                padding: 12px;
            }

            .statement-options {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            .pill-option {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                border: 1px solid var(--line);
                border-radius: 999px;
                padding: 7px 10px;
                font-size: .88rem;
                font-weight: 900;
            }

            .pill-option input {
                accent-color: var(--primary);
            }

            .nav-grid {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 8px;
            }

            .nav-number {
                display: inline-flex;
                min-height: 38px;
                align-items: center;
                justify-content: center;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: #fff;
                color: var(--primary-dark);
                font-weight: 950;
            }

            .nav-number:hover {
                border-color: var(--primary);
                background: var(--primary-soft);
            }

            .check-row {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--muted);
                font-size: .9rem;
                font-weight: 900;
            }

            .check-row input {
                width: 18px;
                height: 18px;
                accent-color: var(--accent);
            }

            .save-bar {
                position: sticky;
                bottom: 0;
                z-index: 10;
                display: flex;
                flex-wrap: wrap;
                justify-content: flex-end;
                gap: 10px;
                border-top: 1px solid var(--line);
                background: rgba(246, 248, 251, .94);
                padding: 14px 0 0;
                backdrop-filter: blur(8px);
            }

            @media (max-width: 900px) {
                .auth-card {
                    grid-template-columns: 1fr;
                }

                .auth-hero {
                    min-height: auto;
                    gap: 60px;
                }

                .exam-layout {
                    grid-template-columns: 1fr;
                }

                .exam-side {
                    position: static;
                    order: -1;
                }

                .info-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .statement-row,
                .matching-row {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 560px) {
                .topbar-inner {
                    min-height: 66px;
                }

                .brand-mark {
                    width: 42px;
                    height: 42px;
                }

                .brand-subtitle {
                    display: none;
                }

                .auth-wrap {
                    padding: 0;
                }

                .auth-card {
                    min-height: 100vh;
                    min-height: 100svh;
                    border: 0;
                    border-radius: 0;
                }

                .auth-hero,
                .auth-form,
                .panel-pad {
                    padding: 20px;
                }

                .info-grid {
                    grid-template-columns: 1fr;
                }

                .page-header,
                .question-head {
                    align-items: stretch;
                    flex-direction: column;
                }

                .actions,
                .save-bar {
                    width: 100%;
                }

                .button {
                    flex: 1 1 160px;
                }
            }
        </style>

        @stack('styles')
    </head>
    <body class="@yield('body_class')">
        @yield('body')

        @stack('scripts')
    </body>
</html>
