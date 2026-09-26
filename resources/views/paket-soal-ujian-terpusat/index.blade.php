@extends('layouts.app')

@section('title', $tampilanHasil ? 'Hasil & Analisis Ujian - NUSA' : 'Paket Soal Terpusat - NUSA')

@section('content')
    <style>
        .package-context { display:grid; grid-template-columns:minmax(0,1.5fr) minmax(260px,.6fr); overflow:hidden; margin-bottom:18px; background:var(--primary); color:#fff; }
        .package-context-main,.package-context-side { padding:22px 24px; }
        .package-context-side { border-left:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.08); }
        .package-context h2 { margin:0; color:#fff; font-size:1.45rem; }
        .package-context p { margin:7px 0 0; color:rgba(255,255,255,.82); }
        .package-context-side strong,.package-context-side span { display:block; }
        .package-context-side strong { font-size:1rem; }
        .package-context-side span { margin-top:8px; color:rgba(255,255,255,.82); font-size:.86rem; }
        .package-flow { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-bottom:22px; }
        .package-flow-step { display:grid; grid-template-columns:34px minmax(0,1fr); gap:10px; align-items:center; min-height:66px; padding:11px 12px; border:1px solid var(--line); border-left:4px solid var(--primary); border-radius:7px; background:#fff; }
        .package-flow-number { display:grid; width:34px; height:34px; margin:0; place-items:center; align-self:center; border-radius:50%; background:var(--primary-soft); color:var(--primary-dark); font-size:.78rem; font-weight:900; line-height:1; }
        .package-flow-step > div > strong, .package-flow-step > div > span { display:block; }
        .package-flow-step > div > span { margin-top:2px; color:var(--muted); font-size:.74rem; font-weight:700; }
        .package-event { margin-bottom:18px; overflow:hidden; }
        .package-event-head { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; padding:17px 18px; border-bottom:1px solid var(--line); background:#f8fafc; }
        .package-event-head h2 { margin:2px 0 0; font-size:1rem; }
        .package-event-head p { margin:5px 0 0; color:var(--muted); font-size:.76rem; }
        .package-event-status { display:flex; flex-wrap:wrap; justify-content:flex-end; align-items:center; gap:8px; }
        .package-event-status .badge { min-height:42px; align-items:center; justify-content:center; padding:10px 14px; }
        .package-schedule { display:grid; grid-template-columns:130px 56px minmax(0,1.2fr) minmax(0,.8fr) 130px 150px; gap:14px; align-items:center; padding:14px 18px; border-bottom:1px solid var(--line); }
        .package-schedule:last-child { border-bottom:0; }
        .package-schedule-date strong, .package-schedule-date span, .package-schedule-main strong, .package-schedule-main span, .package-schedule-class strong, .package-schedule-class span { display:block; }
        .package-schedule-date strong, .package-schedule-main strong, .package-schedule-class strong { font-size:.8rem; }
        .package-schedule-date span, .package-schedule-main span, .package-schedule-class span { margin-top:3px; color:var(--muted); font-size:.72rem; font-weight:700; }
        .package-level { display:grid; width:48px; height:40px; place-items:center; border-radius:6px; background:var(--accent-soft); color:var(--accent-text); font-weight:900; }
        .package-status strong, .package-status span { display:block; }
        .package-status strong { font-size:.78rem; color:var(--primary-dark); }
        .package-status span { margin-top:3px; color:var(--muted); font-size:.7rem; font-weight:700; }
        .package-schedule > div { min-width:0; overflow-wrap:anywhere; }
        .package-schedule-action { display:grid; gap:8px; min-width:150px; }
        .package-schedule-action .button { text-align:center; }
        .package-view-nav { display:flex; gap:8px; flex-wrap:wrap; padding-bottom:16px; margin-bottom:20px; border-bottom:1px solid var(--line); }
        .package-empty { padding:34px 18px; text-align:center; color:var(--muted); }
        .central-wizard-actions { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:20px; }
        @media (max-width:1180px) {
            .package-schedule { grid-template-columns:110px 52px minmax(0,1fr) auto; }
            .package-schedule-class { grid-column:3; }
            .package-status { grid-column:4; grid-row:1 / span 2; }
            .package-schedule-action { grid-column:1 / -1; }
            .package-schedule-action .button { width:100%; }
        }
        @media (max-width:680px) {
            .package-context { grid-template-columns:1fr; }
            .package-context-side { border-top:1px solid rgba(255,255,255,.18); border-left:0; }
            .package-flow { grid-template-columns:1fr; }
            .package-event-head { display:grid; }
            .package-event-status { justify-content:flex-start; }
            .package-schedule { grid-template-columns:52px minmax(0,1fr); padding:14px; }
            .package-schedule-date { grid-column:1 / -1; }
            .package-level { grid-column:1; }
            .package-schedule-main { grid-column:2; }
            .package-schedule-class, .package-status, .package-schedule-action { grid-column:1 / -1; grid-row:auto; }
            .central-wizard-actions { align-items:stretch; flex-direction:column-reverse; }
            .central-wizard-actions .button { width:100%; text-align:center; }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">{{ $tampilanHasil ? 'Ujian Terpusat' : 'Ujian Terpusat · Tahap 8' }}</p>
            <h1 class="page-title">{{ $tampilanHasil ? 'Hasil & analisis ujian' : 'Paket soal terpusat' }}</h1>
            @unless ($tampilanHasil)
                <p class="page-subtitle">Guru cukup memilih jadwal yang diampu, kemudian menentukan soal dari Bank Soal.</p>
            @endunless
        </div>
        <div class="actions">
            <a href="{{ route('soal-cbt.index') }}" class="button button-muted">Bank Soal</a>
            <a href="{{ route('pusat-cbt.index') }}" class="button button-primary">Pusat CBT</a>
        </div>
    </div>

    @if (session('berhasil')) <div class="alert">{{ session('berhasil') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <nav class="package-view-nav" aria-label="Tampilan ujian terpusat">
        <a href="{{ route('paket-soal-terpusat.index', request()->only('kegiatan')) }}" class="button {{ $tampilanHasil ? 'button-muted' : 'button-primary' }}" @if (! $tampilanHasil) aria-current="page" @endif>Paket soal</a>
        <a href="{{ route('paket-soal-terpusat.index', [...request()->only('kegiatan'), 'tampilan' => 'hasil']) }}" class="button {{ $tampilanHasil ? 'button-primary' : 'button-muted' }}" @if ($tampilanHasil) aria-current="page" @endif>Hasil & analisis</a>
    </nav>

    @if ($kegiatanAlur)
        <section class="panel package-context">
            <div class="package-context-main">
                <p class="eyebrow" style="color:var(--accent);">{{ $kegiatanAlur->jenisUjianCbt?->nama ?: 'Ujian Terpusat' }}</p>
                <h2>{{ $kegiatanAlur->nama }}</h2>
                <p>{{ $kegiatanAlur->tahunPelajaran?->nama }} · Semester {{ ucfirst($kegiatanAlur->semester) }} · {{ $kegiatanAlur->labelPeriode() }}</p>
            </div>
            <div class="package-context-side">
                <strong>{{ $tampilanHasil ? $jumlahHasilTersedia.' paket tersedia' : $jumlahSiap.' dari '.$jumlahJadwal.' paket siap' }}</strong>
                <span>{{ $jumlahBelumDisusun > 0 ? $jumlahBelumDisusun.' jadwal belum memiliki paket soal' : 'Seluruh jadwal sudah memiliki paket soal' }}</span>
            </div>
        </section>
    @endif

    @if (! $tampilanHasil && $kegiatanAlur?->dapatDiaksesOleh(auth()->user()))
        @include('ujian-terpusat.partials.alur', ['kegiatan' => $kegiatanAlur, 'tahapAktif' => 8])
    @endif

    @unless ($tampilanHasil)
        <div class="package-flow" aria-label="Alur penyusunan paket soal">
            <div class="package-flow-step"><span class="package-flow-number">1</span><div><strong>Buka jadwal</strong><span>Mapel, tingkat, kelas, dan waktu sudah diisi panitia.</span></div></div>
            <div class="package-flow-step"><span class="package-flow-number">2</span><div><strong>Pilih soal</strong><span>Centang soal siap yang sesuai dari Bank Soal.</span></div></div>
            <div class="package-flow-step"><span class="package-flow-number">3</span><div><strong>Terbitkan</strong><span>NUSA menyiapkan paket dan komponen nilai secara otomatis.</span></div></div>
        </div>
    @endunless

    <div class="stats-grid">
        <div class="panel stat"><p class="stat-label">Jadwal dalam cakupan</p><p class="stat-value">{{ $jumlahJadwal }}</p></div>
        @if ($tampilanHasil)
            <div class="panel stat active"><p class="stat-label">Paket tersedia</p><p class="stat-value">{{ $jumlahHasilTersedia }}</p></div>
            <div class="panel stat"><p class="stat-label">Pengerjaan selesai</p><p class="stat-value">{{ $jumlahPesertaSelesai }}</p></div>
        @else
            <div class="panel stat active"><p class="stat-label">Paket siap</p><p class="stat-value">{{ $jumlahSiap }}</p></div>
            <div class="panel stat"><p class="stat-label">Masih draf</p><p class="stat-value">{{ $jumlahDraf }}</p></div>
            <div class="panel stat warning"><p class="stat-label">Belum disusun</p><p class="stat-value">{{ $jumlahBelumDisusun }}</p></div>
        @endif
    </div>

    @forelse ($jadwalPerKegiatan as $daftarJadwal)
        @php
            $kegiatan = $daftarJadwal->first()->kegiatanUjianCbt;
            $siap = $daftarJadwal->filter(fn ($item) => $item->ujianCbt && in_array($item->ujianCbt->status, ['terjadwal', 'berlangsung', 'selesai'], true))->count();
        @endphp
        <section class="panel package-event">
            <header class="package-event-head">
                <div>
                    <p class="eyebrow">{{ $kegiatan?->jenisUjianCbt?->nama ?: 'Ujian Terpusat' }}</p>
                    <h2>{{ $kegiatan?->nama }}</h2>
                    <p>{{ $kegiatan?->tahunPelajaran?->nama }} · {{ $kegiatan?->labelPeriode() }}</p>
                </div>
                <div class="package-event-status">
                    @if ($tampilanHasil)
                        <span class="badge badge-muted">{{ $daftarJadwal->count() }} jadwal</span>
                    @else
                        <span class="badge {{ $siap === $daftarJadwal->count() ? 'badge-active' : 'badge-warning' }}">{{ $siap }}/{{ $daftarJadwal->count() }} paket siap</span>
                    @endif
                    @if (auth()->user()?->memilikiIzin(['cbt.panitia', 'cbt.terpusat_lihat', 'cbt.kelola']))
                        <a href="{{ route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 7]) }}" class="button button-muted">Jadwal ujian</a>
                    @endif
                </div>
            </header>

            @foreach ($daftarJadwal as $jadwal)
                @php
                    $paket = $jadwal->ujianCbt;
                    $paketSiap = $paket && in_array($paket->status, ['terjadwal', 'berlangsung', 'selesai'], true);
                @endphp
                <div class="package-schedule">
                    <div class="package-schedule-date"><strong>{{ $jadwal->tanggal?->locale('id')->translatedFormat('D, d M Y') }}</strong><span>{{ $jadwal->labelWaktu() }} · {{ $jadwal->durasiMenit() }} menit</span></div>
                    <span class="package-level">T{{ $jadwal->tingkat }}</span>
                    <div class="package-schedule-main"><strong>{{ $jadwal->mataPelajaran?->nama }}</strong><span>{{ $jadwal->sesiKegiatanUjianCbt?->nama }}</span></div>
                    <div class="package-schedule-class"><strong>{{ $jadwal->kelas->pluck('nama')->join(', ') }}</strong><span>{{ $jadwal->kelas->count() }} kelas peserta</span></div>
                    <div class="package-status">
                        @if ($tampilanHasil)
                            <strong>{{ $paket ? ($paket->hasil_difinalisasi_pada ? ($paket->tampilkan_hasil ? 'Dipublikasikan' : 'Final') : ($paket->peserta_selesai_count > 0 ? 'Hasil sementara' : 'Belum ada hasil')) : 'Paket belum dibuat' }}</strong>
                            <span>{{ $paket?->peserta_selesai_count ?? 0 }} / {{ $paket?->peserta_ujian_cbt_count ?? 0 }} peserta selesai</span>
                        @else
                            <strong>{{ $paketSiap ? 'Siap digunakan' : ($paket ? 'Masih draf' : 'Belum disusun') }}</strong><span>{{ $paket?->soal_ujian_cbt_count ?? 0 }} soal</span>
                        @endif
                    </div>
                    <div class="package-schedule-action">
                        @unless ($tampilanHasil)
                            <a href="{{ route('paket-soal-terpusat.show', $jadwal) }}" class="button {{ $jadwal->boleh_kelola_paket ? 'button-primary' : 'button-muted' }}">{{ $jadwal->boleh_kelola_paket ? ($paket ? 'Buka paket' : 'Susun paket') : 'Lihat paket' }}</a>
                        @endunless
                        @if ($jadwal->boleh_lihat_hasil)
                            <a href="{{ route('ujian-cbt.hasil.index', $paket) }}" class="button {{ $tampilanHasil ? 'button-primary' : 'button-muted' }}">Lihat nilai</a>
                            @if ($tampilanHasil)
                                <a href="{{ route('ujian-cbt.hasil.analisis-soal', $paket) }}" class="button button-muted">Analisis soal</a>
                            @endif
                        @elseif ($tampilanHasil)
                            <span class="help-text">{{ $paket ? 'Hasil tidak dapat diakses.' : 'Menunggu paket soal.' }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    @empty
        <section class="panel package-empty">
            <strong>Belum ada jadwal yang dapat ditampilkan.</strong>
            <p class="help-text" style="margin-top:6px;">Panitia perlu menyusun jadwal terlebih dahulu, atau akun guru belum memiliki penugasan mapel pada jadwal tersebut.</p>
        </section>
    @endforelse

    @if (! $tampilanHasil && $kegiatanAlur?->dapatDiaksesOleh(auth()->user()))
        <div class="central-wizard-actions">
            <a href="{{ route('ujian-terpusat.pelaksanaan.index', [$kegiatanAlur, 'tahap' => 7]) }}" class="button button-muted">Kembali ke Jadwal Ujian</a>
            <a href="{{ route('ujian-terpusat.pelaksanaan-nilai.index', $kegiatanAlur) }}" class="button button-primary">Lanjut ke Pelaksanaan</a>
        </div>
    @endif
@endsection
