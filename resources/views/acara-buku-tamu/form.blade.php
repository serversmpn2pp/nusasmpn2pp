@extends('layouts.app')
@section('admin-module', 'humas')
@section('title', ($acara->exists ? 'Ubah' : 'Buat').' Acara - NUSA')
@section('content')
@include('humas._operasional-style')
<link rel="stylesheet" href="{{ asset('css/acara-buku-tamu-admin.css') }}?v={{ filemtime(public_path('css/acara-buku-tamu-admin.css')) }}">
<div class="event-admin"><div class="page-header"><div><p class="eyebrow">Humas / Buku Tamu / Acara & Pameran</p><h1 class="page-title">{{ $acara->exists ? 'Pengaturan acara' : 'Buat acara baru' }}</h1></div><a class="button button-muted" href="{{ $acara->exists ? route('acara-buku-tamu.show', $acara) : route('acara-buku-tamu.index') }}"><i data-lucide="arrow-left" aria-hidden="true"></i>Kembali</a></div>
@include('agenda-humas._messages')
<form method="POST" enctype="multipart/form-data" action="{{ $acara->exists ? route('acara-buku-tamu.update', $acara) : route('acara-buku-tamu.store') }}">@csrf @if($acara->exists)@method('PUT')<input type="hidden" name="versi" value="{{ $acara->versi }}">@else<input type="hidden" name="kunci_pembuatan" value="{{ old('kunci_pembuatan', $kunci) }}">@endif
    <section class="ho-band ho-band--blue"><h2><i data-lucide="ticket" aria-hidden="true"></i>Identitas acara</h2><div class="ho-grid"><div class="field span-2"><label for="nama">Nama acara</label><input class="input" name="nama" id="nama" value="{{ old('nama', $acara->nama) }}" required maxlength="180" placeholder="Contoh: Pameran Literasi Kota Padang Panjang"></div><div class="field span-2"><label for="lokasi">Lokasi stand</label><input class="input" name="lokasi" id="lokasi" value="{{ old('lokasi', $acara->lokasi) }}" required maxlength="200" placeholder="Lokasi acara dan nomor stand"></div><div class="field span-2"><label for="pengantar">Sambutan untuk pengunjung</label><textarea class="input" rows="3" name="pengantar" id="pengantar" maxlength="1200" placeholder="Selamat datang di stand SMP Negeri 2 Padang Panjang...">{{ old('pengantar', $acara->pengantar) }}</textarea></div></div></section>
    <section class="ho-band"><h2><i data-lucide="calendar-clock" aria-hidden="true"></i>Periode pengisian</h2><div class="ho-grid"><div class="field"><label for="mulai_pada">Mulai (WIB)</label><input class="input" type="datetime-local" id="mulai_pada" name="mulai_pada" value="{{ old('mulai_pada', $acara->mulai_pada?->format('Y-m-d\TH:i')) }}" required></div><div class="field"><label for="selesai_pada">Selesai (WIB)</label><input class="input" type="datetime-local" id="selesai_pada" name="selesai_pada" value="{{ old('selesai_pada', $acara->selesai_pada?->format('Y-m-d\TH:i')) }}" required></div><div class="field"><label for="status">Status acara</label><select class="select" name="status" id="status">@foreach(\App\Models\AcaraBukuTamu::STATUS as $key => $label)<option value="{{ $key }}" @selected(old('status', $acara->status) === $key)>{{ $label }}</option>@endforeach</select></div></div></section>
    @include('acara-buku-tamu._banner-form')
    <section class="ho-band event-interest-editor" data-interest-editor>
        <div class="event-interest-heading"><div><h2><i data-lucide="list-checks" aria-hidden="true"></i>Pilihan minat pengunjung</h2><p>Pilihan nonaktif tidak tampil di formulir. Riwayatnya tetap tercatat dalam laporan.</p></div><button type="button" class="button button-muted" data-interest-add><i data-lucide="plus" aria-hidden="true"></i>Tambah pilihan</button></div>
        @php
            $pilihanForm = old('pilihan_minat', $acara->pilihanMinat());
            $pilihanForm = is_array($pilihanForm) ? array_values(array_filter($pilihanForm, 'is_array')) : $acara->pilihanMinat();
        @endphp
        <div data-interest-list>
            @foreach($pilihanForm as $indeks => $pilihan)
                @include('acara-buku-tamu._baris-minat', ['indeks' => $indeks, 'pilihan' => $pilihan, 'terpakai' => in_array($pilihan['kode'] ?? null, $minatTerpakai ?? [], true)])
            @endforeach
        </div>
        <p class="event-interest-notice" data-interest-notice role="status" aria-live="polite"></p>
        <template data-interest-template>@include('acara-buku-tamu._baris-minat', ['indeks' => '__INDEX__', 'pilihan' => ['kode' => '', 'nama' => '', 'ikon' => 'sparkles', 'aktif' => true], 'terpakai' => false])</template>
    </section>
    <div class="actions"><button class="button button-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i>Simpan acara</button><a class="button button-muted" href="{{ route('acara-buku-tamu.index') }}">Batal</a></div>
</form></div>
<script defer src="{{ asset('js/acara-buku-tamu-minat.js') }}?v={{ filemtime(public_path('js/acara-buku-tamu-minat.js')) }}"></script>
<script defer src="{{ asset('js/acara-buku-tamu-banner.js') }}?v={{ filemtime(public_path('js/acara-buku-tamu-banner.js')) }}"></script>
@endsection
