@extends('layouts.app')

@section('title', 'Ganti Kata Sandi - NUSA')

@section('content')
    @include('auth.partials.password-toggle-assets')

    <div class="page-header">
        <div>
            <p class="eyebrow">Akun</p>
            <h1 class="page-title">Ganti kata sandi</h1>
        </div>

        <a href="{{ route('beranda') }}" class="button button-muted">Kembali</a>
    </div>

    @if (session('perlu_ganti_kata_sandi'))
        <div class="alert alert-danger">{{ session('perlu_ganti_kata_sandi') }}</div>
    @endif

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Data belum bisa disimpan.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('kata-sandi.update') }}" method="POST" class="panel panel-pad" style="max-width: 620px;">
        @csrf
        @method('PUT')

        <h2 class="panel-title">Keamanan akun</h2>

        <div class="form-grid">
            <x-password-field
                id="kata_sandi_lama"
                name="kata_sandi_lama"
                label="Kata sandi lama"
                autocomplete="current-password"
                container-class="span-2"
                required
            />

            <x-password-field
                id="kata_sandi_baru"
                name="kata_sandi_baru"
                label="Kata sandi baru"
                autocomplete="new-password"
                container-class="span-2"
                required
            />

            <x-password-field
                id="kata_sandi_baru_confirmation"
                name="kata_sandi_baru_confirmation"
                label="Konfirmasi kata sandi baru"
                autocomplete="new-password"
                container-class="span-2"
                required
            />
        </div>

        <div class="form-actions" style="margin-top: 20px;">
            <button type="submit" class="button button-primary">Simpan kata sandi</button>
        </div>
    </form>
@endsection
