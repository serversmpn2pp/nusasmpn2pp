@extends('layouts.app')
@section('title', 'Profil Siswa Terpadu - NUSA')
@include('profil-siswa-terpadu._styles')

@section('content')
    @php
        $urlTab = fn ($target, $extra = []) => route('profil-siswa-terpadu.show', array_merge([
            'siswa' => $siswa, 'tahun' => $tahun->id, 'semester' => $semester, 'tab' => $target,
        ], $extra));
    @endphp
    <div class="siswa-terpadu">
        <div class="actions">
            <a class="button button-muted" href="{{ route('profil-siswa-terpadu.index', ['tahun' => $tahun->id]) }}"><i data-lucide="arrow-left" aria-hidden="true"></i> Daftar siswa</a>
        </div>
        <header class="terpadu-header">
            @include('profil-siswa-terpadu._avatar')
            <div>
                <p class="eyebrow">Profil Siswa Terpadu</p>
                <h1 class="page-title">{{ $siswa->nama_lengkap }}</h1>
                <ul class="terpadu-meta">
                    <li>{{ $anggota->pluck('kelas.nama')->filter()->unique()->join(', ') ?: 'Kelas tidak ditampilkan' }}</li>
                    <li>{{ $tahun->nama }}</li>
                    <li>NIS {{ $siswa->nis ?: '-' }}</li>
                    <li>NISN {{ $siswa->nisn ?: '-' }}</li>
                    <li><span class="terpadu-pill">{{ $siswa->aktif ? 'Siswa aktif' : 'Siswa tidak aktif' }}</span></li>
                </ul>
            </div>
        </header>

        <form method="get" action="{{ route('profil-siswa-terpadu.show', $siswa) }}" class="terpadu-filters">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label class="terpadu-field">
                Tahun pelajaran
                <select class="select" name="tahun" onchange="this.form.elements.tab.value = 'ringkasan'">
                    @foreach ($daftarTahun as $pilihan)
                        <option value="{{ $pilihan->id }}" @selected($tahun->id === $pilihan->id)>{{ $pilihan->nama }}{{ $pilihan->aktif ? ' (aktif)' : '' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="terpadu-field">
                Semester akademik
                <select class="select" name="semester">
                    <option value="ganjil" @selected($semester === 'ganjil')>Ganjil</option>
                    <option value="genap" @selected($semester === 'genap')>Genap</option>
                </select>
            </label>
            @if ($kosongSaja && $tab === 'akademik')
                <input type="hidden" name="kosong" value="1">
            @endif
            <button type="submit" class="button button-primary"><i data-lucide="list-filter" aria-hidden="true"></i> Pilih periode</button>
        </form>

        <nav class="terpadu-tabs" aria-label="Bagian profil siswa">
            @foreach ($tabs as $kode => $label)
                <a href="{{ $urlTab($kode) }}" @if ($kode === $tab) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if (! empty($isi['statistik']))
            <div class="terpadu-stats">
                @foreach ($isi['statistik'] as $statistik)
                    <div class="terpadu-stat">
                        <span>{{ $statistik[0] }}</span>
                        <strong>{{ $statistik[1] }}</strong>
                        @if (isset($statistik[2]))
                            <a href="{{ $urlTab($statistik[2]) }}">Lihat rincian</a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($tab === 'ringkasan')
            <section class="terpadu-section" aria-labelledby="kelas-periode">
                <header><h2 id="kelas-periode">Penempatan kelas {{ $tahun->nama }}</h2></header>
                @forelse ($anggota as $row)
                    <dl class="terpadu-facts">
                        <div><dt>Kelas</dt><dd>{{ $row->kelas?->nama ?? '-' }}</dd></div>
                        <div><dt>Nomor absen</dt><dd>{{ $row->nomor_absen ?? '-' }}</dd></div>
                        <div><dt>Status keanggotaan</dt><dd>{{ str($row->status_keanggotaan)->headline() }}</dd></div>
                        <div><dt>Tanggal masuk</dt><dd>{{ $row->tanggal_masuk?->format('d/m/Y') ?? '-' }}</dd></div>
                    </dl>
                @empty
                    <p class="terpadu-empty">Tidak ada penempatan kelas yang tersedia untuk periode ini.</p>
                @endforelse
            </section>
        @endif

        @if ($tab === 'akademik')
            <div class="terpadu-actions" aria-label="Filter nilai">
                <a class="button {{ $kosongSaja ? 'button-muted' : 'button-primary' }}" href="{{ $urlTab('akademik') }}" @if (! $kosongSaja) aria-current="page" @endif>Semua komponen</a>
                <a class="button {{ $kosongSaja ? 'button-primary' : 'button-muted' }}" href="{{ $urlTab('akademik', ['kosong' => 1]) }}" @if ($kosongSaja) aria-current="page" @endif>Belum ada nilai</a>
            </div>
        @endif

        @if ($tab === 'data')
            @foreach (['biodata' => 'Biodata', 'keluarga' => 'Data keluarga'] as $kunci => $judul)
                @if ($isi[$kunci]->isNotEmpty())
                    <section class="terpadu-section" aria-labelledby="terpadu-{{ $kunci }}">
                        <header><h2 id="terpadu-{{ $kunci }}">{{ $judul }}</h2></header>
                        <dl class="terpadu-facts">
                            @foreach ($isi[$kunci] as $label => $nilai)
                                <div><dt>{{ $label }}</dt><dd>{{ filled($nilai) ? $nilai : '-' }}</dd></div>
                            @endforeach
                        </dl>
                    </section>
                @endif
            @endforeach
        @endif

        @foreach ($isi['tabel'] ?? [] as $tabel)
            @include('profil-siswa-terpadu._tabel', $tabel + ['id' => 'tabel-terpadu-'.$loop->index])
        @endforeach
    </div>
@endsection
