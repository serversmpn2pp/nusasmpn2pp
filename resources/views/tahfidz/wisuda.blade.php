@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Wisuda Tahfidz - NUSA')
@include('tahfidz._assets')

@section('content')
    <div class="tf-page">
        <div class="page-header"><div><p class="eyebrow">Tahfidz / Kelulusan</p><h1 class="page-title">Wisuda Tahfidz</h1></div></div>
        @include('tahfidz._nav')
        @include('tahfidz._pesan')

        <form method="POST" action="{{ $acara ? route('tahfidz.wisuda.update', $acara) : route('tahfidz.wisuda.store') }}" class="tf-surface">
            @csrf
            @if ($acara) @method('PUT') @endif
            <header class="tf-band"><h2>{{ $acara ? 'Edit acara' : 'Tambah acara wisuda' }}</h2></header>
            <div class="tf-form-body form-grid">
                <div class="field"><label for="tf-nama-wisuda">Nama acara</label><input id="tf-nama-wisuda" name="nama" class="input" maxlength="150" required value="{{ old('nama', $acara?->nama) }}"></div>
                <div class="field"><label for="tf-tanggal-wisuda">Tanggal acara</label><input id="tf-tanggal-wisuda" name="tanggal" type="date" class="input" required value="{{ old('tanggal', $acara?->tanggal?->toDateString()) }}"></div>
                <div class="field"><label for="tf-lokasi-wisuda">Lokasi</label><input id="tf-lokasi-wisuda" name="lokasi" class="input" maxlength="120" required value="{{ old('lokasi', $acara?->lokasi ?? 'Padang Panjang') }}"></div>
                <div class="field"><label for="tf-kepala-wisuda">Nama kepala sekolah</label><input id="tf-kepala-wisuda" name="kepala_sekolah" class="input" maxlength="150" required value="{{ old('kepala_sekolah', $acara?->kepala_sekolah) }}"></div>
            </div>
            <div class="tf-save">
                @if ($acara)<a href="{{ route('tahfidz.wisuda.index') }}" class="button button-muted">Batal</a>@endif
                <button type="submit" class="button button-primary"><i data-lucide="save" aria-hidden="true"></i> Simpan acara</button>
            </div>
        </form>

        <section class="tf-surface tf-section" aria-labelledby="tf-acara">
            <header class="tf-band"><h2 id="tf-acara">Daftar acara</h2><span>{{ $acaraList->count() }} acara</span></header>
            <ul class="tf-list">
                @forelse ($acaraList as $item)
                    <li class="tf-student-row"><div><p class="person-name">{{ $item->nama }}</p><p class="person-meta">{{ $item->tanggal->format('d/m/Y') }} &middot; {{ $item->lokasi }} &middot; {{ $item->kelulusan_count }} sertifikat</p></div>
                        @if ($item->kelulusan_count === 0)<a class="button button-muted" href="{{ route('tahfidz.wisuda.index', ['acara' => $item->id]) }}"><i data-lucide="pencil" aria-hidden="true"></i> Edit acara</a>@endif
                    </li>
                @empty
                    <li class="empty-state">Belum ada acara wisuda.</li>
                @endforelse
            </ul>
        </section>

        <section class="tf-surface tf-section" aria-labelledby="tf-calon">
            <header class="tf-band"><h2 id="tf-calon">Siswa terverifikasi</h2><span>{{ $kelulusan->total() }} siswa</span></header>
            <ul class="tf-list">
                @forelse ($kelulusan as $item)
                    <li class="tf-wisuda-row">
                        <div><p class="person-name">{{ $item->siswa->nama_lengkap }}</p><p class="person-meta">{{ $item->nama_pembimbing }} &middot; {{ $item->diverifikasi_pada->format('d/m/Y') }}</p></div>
                        @if ($item->diterbitkan_pada)
                            <div><p class="person-name">{{ $item->nomor_sertifikat }}</p><p class="person-meta">{{ $item->wisuda->nama }} &middot; {{ $item->dibagikan_pada ? 'Dibagikan ke orang tua' : 'Belum dibagikan' }}</p></div>
                            <div class="actions">
                                <a href="{{ route('tahfidz.wisuda.sertifikat', $item) }}" class="button button-muted"><i data-lucide="printer" aria-hidden="true"></i> Sertifikat</a>
                                <form method="POST" action="{{ route('tahfidz.wisuda.publikasi', $item) }}">
                                    @csrf
                                    <button type="submit" name="bagikan" value="{{ $item->dibagikan_pada ? '0' : '1' }}" class="button {{ $item->dibagikan_pada ? 'button-muted' : 'button-primary' }}"><i data-lucide="{{ $item->dibagikan_pada ? 'undo-2' : 'send' }}" aria-hidden="true"></i> {{ $item->dibagikan_pada ? 'Tarik publikasi' : 'Bagikan ke orang tua' }}</button>
                                </form>
                            </div>
                        @elseif ($acaraList->isNotEmpty())
                            <form method="POST" action="{{ route('tahfidz.wisuda.terbitkan', $item) }}" class="tf-issue-form">
                                @csrf
                                <div class="field"><label for="tf-acara-{{ $item->id }}">Acara wisuda</label><select id="tf-acara-{{ $item->id }}" class="select" name="wisuda_tahfidz_id" required>
                                    <option value="">Pilih acara</option>
                                    @foreach ($acaraList as $event)<option value="{{ $event->id }}">{{ $event->tanggal->format('d/m/Y') }} - {{ $event->nama }}</option>@endforeach
                                </select></div>
                                <button type="submit" class="button button-primary"><i data-lucide="award" aria-hidden="true"></i> Terbitkan sertifikat</button>
                            </form>
                        @else
                            <span class="badge badge-muted">Belum ada acara</span>
                        @endif
                    </li>
                @empty
                    <li class="empty-state">Belum ada kelulusan yang diverifikasi pembimbing.</li>
                @endforelse
            </ul>
        </section>
        @if ($kelulusan->hasPages())<div class="tf-pagination">{{ $kelulusan->links() }}</div>@endif
    </div>
@endsection
