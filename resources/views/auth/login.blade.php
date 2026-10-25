<!DOCTYPE html>
<html lang="id" translate="no" class="notranslate">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <meta name="google" content="notranslate">

        <title>NUSA | SMPN 2 Padang Panjang</title>

        <link rel="icon" href="{{ asset('images/logo-nusa.png') }}" type="image/png">
        @include('partials.pwa-head')
        @include('auth.partials.password-toggle-assets')
        <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
        <script src="{{ asset('js/login.js') }}?v={{ filemtime(public_path('js/login.js')) }}" defer></script>
    </head>
    <body class="nusa-login notranslate" translate="no">
        <div class="login-page">
            <div class="brand-rule" aria-hidden="true"></div>
            <div class="campus" aria-hidden="true">
                <img
                    src="{{ asset('images/nusa/login/sekolah-sketsa.webp') }}"
                    width="1448"
                    height="1086"
                    alt=""
                    fetchpriority="high"
                >
            </div>

            <header class="page-header">
                <div class="brand">
                    <img class="brand-logo" src="{{ asset('images/logo-nusa.png') }}" width="52" height="52" alt="Logo NUSA">
                    <div class="brand-copy">
                        <span class="brand-name">NUSA<span class="brand-dot" aria-hidden="true">.</span></span>
                        <span class="brand-school">SMP Negeri 2 Padang Panjang</span>
                    </div>
                </div>
                <span class="header-caption"><span aria-hidden="true"></span>Ruang digital sekolah</span>
            </header>

            <main class="page-main">
                <section class="login-content" aria-labelledby="login-title">
                    <div class="welcome">
                        <div class="welcome-accent" aria-hidden="true"></div>
                        <h1 id="login-title">Masuk ke NUSA</h1>
                        <p>Selamat datang kembali.<br>Gunakan akun sekolah untuk melanjutkan.</p>
                    </div>

                    @php
                        $kesalahanUmum = collect($errors->getMessages())->except(['username', 'password'])->flatten()->first();
                    @endphp
                    @if ($kesalahanUmum)
                        <div class="login-alert" role="alert">{{ $kesalahanUmum }}</div>
                    @endif
                    @if (session('gagal'))
                        <div class="login-alert" role="alert">{{ session('gagal') }}</div>
                    @endif

                    <form action="{{ route('login.store') }}" method="POST" data-login-form>
                        @csrf

                        <div class="field">
                            <label for="username">Username</label>
                            <div class="input-wrap">
                                <span class="login-icon login-icon-user field-icon" aria-hidden="true"></span>
                                <input
                                    id="username"
                                    name="username"
                                    type="text"
                                    value="{{ old('username') }}"
                                    class="input @error('username') is-invalid @enderror"
                                    autocomplete="username"
                                    autocapitalize="none"
                                    spellcheck="false"
                                    placeholder="NIP/NISN/ORT-NISN"
                                    maxlength="255"
                                    @if ($errors->has('username')) aria-invalid="true" aria-describedby="username_error" @endif
                                    autofocus
                                    required
                                >
                            </div>
                            @error('username')
                                <p id="username_error" class="error-text" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-password-field
                            id="password"
                            name="password"
                            label="Kata sandi"
                            autocomplete="current-password"
                            placeholder="Masukkan kata sandi"
                            container-class="password-field"
                            :aria-invalid="$errors->has('password') ? 'true' : 'false'"
                            required
                        />

                        <div class="remember-row">
                            <label class="remember" for="ingat">
                                <input id="ingat" type="checkbox" name="ingat" value="1" @checked(old('ingat'))>
                                <span>Ingat saya</span>
                            </label>
                        </div>

                        <button type="submit" class="submit-button" data-login-submit>
                            <span data-login-submit-label>Masuk</span>
                            <span class="login-icon login-icon-arrow submit-arrow" aria-hidden="true"></span>
                            <span class="spinner" aria-hidden="true"></span>
                        </button>
                        <span class="sr-only" data-login-status role="status" aria-live="polite"></span>
                    </form>

                    <div class="support">
                        <span class="login-icon login-icon-help" aria-hidden="true"></span>
                        <p>Kesulitan masuk?<br><span>Hubungi operator sekolah.</span></p>
                    </div>

                    <nav class="legal" aria-label="Informasi akun">
                        <a href="{{ route('kebijakan-privasi') }}">Kebijakan privasi</a>
                        <span class="legal-divider" aria-hidden="true"></span>
                        <a href="{{ route('penghapusan-akun') }}">Penghapusan akun/data</a>
                    </nav>

                    <div class="install-action" data-pwa-install-container hidden>
                        <button type="button" class="install-button" data-pwa-install>
                            <span class="login-icon login-icon-download" aria-hidden="true"></span>
                            <span class="install-label">Pasang NUSA</span>
                        </button>
                    </div>
                    <p class="install-status" data-pwa-install-status aria-live="polite" hidden></p>
                </section>
            </main>

            <footer class="page-footer">
                <p class="tagline">Satu Nusa, Terhubung untuk <span>Bertumbuh</span></p>
                <p class="copyright">&copy; {{ date('Y') }} SMP Negeri 2 Padang Panjang</p>
            </footer>
        </div>
    </body>
</html>
