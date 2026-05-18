<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>NUSA | SMPN 2 Padang Panjang</title>

        @fonts
        <link rel="icon" href="{{ asset('images/logo-nusa.png') }}" type="image/png">

        <style>
            :root {
                --primary: #15477A;
                --primary-dark: #0d2f52;
                --accent: #F1C40F;
                --text: #18181b;
                --muted: #6b7280;
                --line: #dfe7f0;
                --danger: #b91c1c;
                --danger-soft: #fee2e2;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                min-height: 100svh;
                color: var(--text);
                font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                background: var(--primary);
            }

            button,
            input {
                font: inherit;
            }

            .login-page {
                position: relative;
                display: grid;
                min-height: 100vh;
                min-height: 100svh;
                overflow: hidden;
                background-image:
                    linear-gradient(90deg, rgba(13, 47, 82, .92), rgba(13, 47, 82, .66) 48%, rgba(13, 47, 82, .2)),
                    url("{{ asset('images/login-sekolah.jpg') }}");
                background-position: center;
                background-size: cover;
            }

            .login-page::after {
                content: "";
                position: absolute;
                inset: auto 0 0;
                height: 8px;
                background: var(--accent);
            }

            .login-shell {
                position: relative;
                z-index: 1;
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(360px, 430px);
                gap: 48px;
                width: min(100%, 1180px);
                margin: 0 auto;
                padding: 42px 24px;
                align-items: center;
            }

            .login-footer {
                position: absolute;
                right: 24px;
                bottom: 18px;
                z-index: 1;
                color: rgba(255, 255, 255, .82);
                font-size: .84rem;
                font-weight: 700;
                text-shadow: 0 1px 10px rgba(0, 0, 0, .35);
            }

            .login-brand {
                max-width: 640px;
                color: #fff;
            }

            .brand-lockup {
                display: flex;
                align-items: center;
                gap: 14px;
                margin-bottom: 28px;
            }

            .brand-mark {
                display: grid;
                width: 64px;
                height: 64px;
                place-items: center;
                border: 1px solid rgba(241, 196, 15, .78);
                border-radius: 8px;
                background: rgba(255, 255, 255, .96);
                padding: 6px;
                box-shadow: 0 16px 40px rgba(0, 0, 0, .2);
            }

            .brand-mark img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            .brand-name {
                display: block;
                font-size: 1.35rem;
                font-weight: 900;
                line-height: 1.1;
            }

            .brand-school {
                display: block;
                margin-top: 3px;
                color: rgba(255, 255, 255, .82);
                font-size: .95rem;
                font-weight: 700;
            }

            .login-title {
                max-width: 620px;
                margin: 0;
                font-size: clamp(2rem, 5vw, 4rem);
                line-height: 1.02;
                letter-spacing: 0;
            }

            .login-copy {
                max-width: 560px;
                margin: 18px 0 0;
                color: rgba(255, 255, 255, .84);
                font-size: 1.04rem;
                line-height: 1.7;
            }

            .login-panel {
                border: 1px solid rgba(255, 255, 255, .7);
                border-radius: 8px;
                background: rgba(255, 255, 255, .96);
                box-shadow: 0 24px 70px rgba(0, 0, 0, .28);
                padding: 28px;
            }

            .panel-heading {
                margin-bottom: 24px;
            }

            .eyebrow {
                margin: 0 0 6px;
                color: var(--primary);
                font-size: .82rem;
                font-weight: 900;
                text-transform: uppercase;
            }

            .panel-title {
                margin: 0;
                color: var(--text);
                font-size: 1.55rem;
                line-height: 1.15;
            }

            .field {
                display: grid;
                gap: 7px;
                margin-bottom: 16px;
            }

            .field label {
                color: #3f3f46;
                font-size: .92rem;
                font-weight: 800;
            }

            .input {
                width: 100%;
                min-height: 46px;
                border: 1px solid #d4d4d8;
                border-radius: 8px;
                background: #fff;
                padding: 11px 12px;
                color: var(--text);
                outline: none;
            }

            .input:focus {
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(21, 71, 122, .16);
            }

            .option-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin: 4px 0 20px;
            }

            .remember {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--muted);
                font-size: .92rem;
                font-weight: 700;
            }

            .remember input {
                width: 18px;
                height: 18px;
                accent-color: var(--primary);
            }

            .button {
                display: inline-flex;
                width: 100%;
                min-height: 46px;
                align-items: center;
                justify-content: center;
                border: 1px solid var(--primary);
                border-radius: 8px;
                background: var(--primary);
                color: #fff;
                cursor: pointer;
                font-size: .96rem;
                font-weight: 900;
            }

            .button:hover {
                background: var(--primary-dark);
            }

            .button:focus-visible {
                outline: 3px solid rgba(241, 196, 15, .65);
                outline-offset: 2px;
            }

            .alert {
                margin: 0 0 18px;
                border: 1px solid #fecaca;
                border-radius: 8px;
                background: var(--danger-soft);
                padding: 12px 14px;
                color: var(--danger);
                font-size: .9rem;
                font-weight: 800;
            }

            .help-text {
                margin: 16px 0 0;
                color: var(--muted);
                font-size: .84rem;
                line-height: 1.5;
            }

            @media (max-width: 900px) {
                .login-page {
                    background-image:
                        linear-gradient(180deg, rgba(13, 47, 82, .86), rgba(13, 47, 82, .74)),
                        url("{{ asset('images/login-sekolah.jpg') }}");
                }

                .login-shell {
                    grid-template-columns: 1fr;
                    gap: 28px;
                    padding: 28px 18px 36px;
                    align-items: start;
                }

                .login-brand {
                    max-width: none;
                }

                .brand-lockup {
                    margin-bottom: 22px;
                }

                .login-copy {
                    font-size: .98rem;
                }

                .login-panel {
                    width: min(100%, 460px);
                    padding: 22px;
                }

                .login-footer {
                    position: relative;
                    right: auto;
                    bottom: auto;
                    align-self: end;
                    padding: 0 18px 18px;
                    text-align: center;
                }
            }

            @media (max-width: 520px) {
                .login-shell {
                    padding: 22px 14px 32px;
                }

                .brand-mark {
                    width: 56px;
                    height: 56px;
                }

                .login-title {
                    font-size: 2rem;
                }

                .login-copy {
                    display: none;
                }

                .login-panel {
                    width: 100%;
                    padding: 20px;
                }

                .login-footer {
                    padding: 0 14px 18px;
                    font-size: .8rem;
                }
            }
        </style>
    </head>
    <body>
        <main class="login-page">
            <div class="login-shell">
                <section class="login-brand" aria-label="NUSA SMP Negeri 2 Padang Panjang">
                    <div class="brand-lockup">
                        <span class="brand-mark">
                            <img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
                        </span>
                        <span>
                            <span class="brand-name">NUSA</span>
                            <span class="brand-school">SMP Negeri 2 Padang Panjang</span>
                        </span>
                    </div>
                    <h1 class="login-title">Satu pintu data sekolah.</h1>
                    <p class="login-copy">Data yang tertata dengan baik akan melahirkan keputusan yang lebih tepat.</p>
                </section>

                <section class="login-panel" aria-label="Form login">
                    <div class="panel-heading">
                        <p class="eyebrow">Akses NUSA</p>
                        <h2 class="panel-title">Masuk ke aplikasi</h2>
                    </div>

                    @if ($errors->any())
                        <div class="alert">{{ $errors->first() }}</div>
                    @endif

                    <form action="{{ route('login.store') }}" method="POST">
                        @csrf

                        <div class="field">
                            <label for="username">Username</label>
                            <input id="username" name="username" type="text" value="{{ old('username') }}" class="input" autocomplete="username" autofocus required>
                        </div>

                        <div class="field">
                            <label for="password">Kata sandi</label>
                            <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
                        </div>

                        <div class="option-row">
                            <label class="remember">
                                <input type="checkbox" name="ingat" value="1">
                                Ingat saya
                            </label>
                        </div>

                        <button type="submit" class="button">Masuk</button>
                    </form>

                    <p class="help-text">Gunakan akun yang diberikan oleh administrator sekolah.</p>
                </section>
            </div>
            <footer class="login-footer">
                &copy; {{ date('Y') }} SMPN 2 Padang Panjang
            </footer>
        </main>
    </body>
</html>
