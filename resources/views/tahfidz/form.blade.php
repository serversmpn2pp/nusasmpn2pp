@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', $kelompok ? 'Edit Kelompok Tahfidz - NUSA' : 'Tambah Kelompok Tahfidz - NUSA')
@include('tahfidz._assets')

@section('content')
    @php($kembali = $kelompok ? route('tahfidz.show', $kelompok) : route('tahfidz.index'))
    <div class="tf-page">
        <div class="page-header">
            <div><p class="eyebrow">Tahfidz / Kelompok</p><h1 class="page-title">{{ $kelompok ? 'Edit kelompok' : 'Tambah kelompok' }}</h1></div>
            <div class="actions"><a href="{{ $kembali }}" class="button button-muted"><i data-lucide="arrow-left" aria-hidden="true"></i> Kembali</a></div>
        </div>
        @include('tahfidz._pesan')

        <form method="POST" action="{{ $kelompok ? route('tahfidz.update', $kelompok) : route('tahfidz.store') }}" class="tf-surface">
            @csrf
            @if ($kelompok)
                @method('PUT')
                <input type="hidden" name="versi" value="{{ old('versi', $kelompok->versi) }}">
            @endif
            <header class="tf-band"><h2>Data kelompok</h2></header>
            <div class="tf-form-body form-grid">
                <div class="field"><label for="tf-nama">Nama kelompok</label>
                    <input id="tf-nama" name="nama" class="input" maxlength="120" required value="{{ old('nama', $kelompok?->nama) }}" placeholder="Contoh: Kelompok Al-Ikhlas">
                </div>
                <div class="field"><label for="tf-tahun">Tahun pelajaran</label>
                    @if ($kelompok)
                        <input id="tf-tahun" class="input" readonly value="{{ $kelompok->tahunPelajaran->nama }}">
                    @else
                        <select id="tf-tahun" name="tahun_pelajaran_id" class="select" required>
                            <option value="">Pilih tahun pelajaran</option>
                            @foreach ($tahun as $item)
                                <option value="{{ $item->id }}" @selected((int) old('tahun_pelajaran_id', $tahun->firstWhere('aktif', true)?->id) === $item->id)>{{ $item->nama }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="field"><label for="tf-pembimbing">Guru pembimbing</label>
                    <select id="tf-pembimbing" name="pembimbing_pegawai_id" class="select" required>
                        <option value="">Pilih guru pembimbing</option>
                        @foreach ($pembimbing as $guru)
                            <option value="{{ $guru->id }}" @selected((int) old('pembimbing_pegawai_id', $kelompok?->pembimbing_pegawai_id) === $guru->id)>{{ $guru->nama_lengkap }}{{ $guru->aktif ? '' : ' (nonaktif)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label for="tf-aktif">Status kelompok</label>
                    <select id="tf-aktif" name="aktif" class="select" required>
                        <option value="1" @selected((int) old('aktif', $kelompok?->aktif ?? true) === 1)>Aktif</option>
                        <option value="0" @selected((int) old('aktif', $kelompok?->aktif ?? true) === 0)>Nonaktif</option>
                    </select>
                </div>
            </div>
            @if ($pembimbing->isEmpty() || (! $kelompok && $tahun->isEmpty()))
                <p class="tf-form-note">{{ $pembimbing->isEmpty() ? 'Belum ada guru aktif di data pegawai.' : 'Belum ada tahun pelajaran.' }}</p>
            @endif
            <div class="tf-save"><a href="{{ $kembali }}" class="button button-muted">Batal</a><button type="submit" class="button button-primary" @disabled($pembimbing->isEmpty() || (! $kelompok && $tahun->isEmpty()))><i data-lucide="save" aria-hidden="true"></i> {{ $kelompok ? 'Simpan perubahan' : 'Buat kelompok' }}</button></div>
        </form>
    </div>
@endsection
