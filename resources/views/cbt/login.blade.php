@extends('cbt.layout')

@section('title', 'Masuk CBT NUSA')
@section('body_class', 'cbt-auth')

@section('body')
    <main class="auth-wrap">
        <section class="auth-card" aria-label="Login CBT NUSA">
            <div class="auth-hero">
                <div class="brand">
                    <span class="brand-mark">
                        <img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
                    </span>
                    <span>
                        <span class="brand-title">CBT NUSA</span>
                        <span class="brand-subtitle">SMP Negeri 2 Padang Panjang</span>
                    </span>
                </div>

                <div class="auth-heading">
                    <h1>Masuk ruang ujian.</h1>
                    <p>Gunakan username dan password pada kartu peserta. Token ujian diberikan oleh pengawas saat ujian dimulai.</p>
                </div>

                <p style="margin: 0; color: rgba(255,255,255,.74); font-size: .86rem; font-weight: 800;">
                    Pastikan nama, kelas, dan paket ujian sesuai sebelum mulai mengerjakan.
                </p>
            </div>

            <form class="auth-form" action="{{ route('cbt.login.store') }}" method="POST">
                @csrf

                <div>
                    <p class="eyebrow">Akses peserta</p>
                    <h2 class="panel-title">Login ujian</h2>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <div class="field">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" value="{{ old('username') }}" class="input" autocomplete="username" autofocus required>
                </div>

                <div class="field">
                    <label for="kata_sandi">Password</label>
                    <input id="kata_sandi" name="kata_sandi" type="password" class="input" autocomplete="current-password" required>
                </div>

                <div class="field">
                    <label for="token">Token ujian</label>
                    <input id="token" name="token" type="text" value="{{ old('token') }}" class="input" autocomplete="off" inputmode="latin" required>
                </div>

                <button type="submit" class="button button-primary button-full">Masuk</button>
            </form>
        </section>
    </main>
@endsection
