@extends('layouts.app')

@section('title', 'Ganti Kata Sandi - NUSA')

@section('content')
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
            <div class="field span-2">
                <label for="kata_sandi_lama">Kata sandi lama</label>
                <input id="kata_sandi_lama" name="kata_sandi_lama" type="password" class="input @error('kata_sandi_lama') is-invalid @enderror" autocomplete="current-password" required>
                @error('kata_sandi_lama')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div class="field span-2">
                <label for="kata_sandi_baru">Kata sandi baru</label>
                <input id="kata_sandi_baru" name="kata_sandi_baru" type="password" class="input @error('kata_sandi_baru') is-invalid @enderror" autocomplete="new-password" required>
                @error('kata_sandi_baru')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div class="field span-2">
                <label for="kata_sandi_baru_confirmation">Konfirmasi kata sandi baru</label>
                <input id="kata_sandi_baru_confirmation" name="kata_sandi_baru_confirmation" type="password" class="input" autocomplete="new-password" required>
            </div>
        </div>

        <div class="form-actions" style="margin-top: 20px;">
            <button type="submit" class="button button-primary">Simpan kata sandi</button>
        </div>
    </form>
@endsection
