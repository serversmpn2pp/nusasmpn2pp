@extends('layouts.app')

@section('title', 'Pusat CBT - NUSA')

@section('content')
    @php
        $pengguna = auth()->user();
        $bolehKelola = $pengguna?->memilikiIzin('cbt.kelola') ?? false;
        $bolehAsesmen = $pengguna?->memilikiIzin(['cbt.asesmen_kelola', 'cbt.kelola']) ?? false;
        $bolehSoal = $pengguna?->memilikiIzin(['cbt.lihat', 'cbt.kelola', 'cbt.soal_kelola']) ?? false;
        $bolehPresensi = $pengguna?->memilikiIzin(['cbt.presensi', 'cbt.kelola']) ?? false;
        $bolehTerpusat = $pengguna?->memilikiIzin(['cbt.panitia', 'cbt.terpusat_lihat', 'cbt.kelola']) ?? false;
        $bolehPaketTerpusat = $pengguna?->memilikiIzin(['cbt.soal_kelola', 'cbt.panitia', 'cbt.terpusat_lihat', 'cbt.kelola']) ?? false;
    @endphp

    <style>
        .cbt-choice-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .cbt-choice {
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            gap: 14px;
            min-height: 100%;
            border-top: 4px solid var(--primary);
        }

        .cbt-choice.central {
            border-top-color: var(--accent);
        }

        .cbt-choice-label {
            color: var(--primary);
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .cbt-choice h2 {
            margin: 0;
            font-size: 1.35rem;
        }

        .cbt-flow {
            display: grid;
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .cbt-flow li {
            display: grid;
            grid-template-columns: 30px minmax(0, 1fr);
            gap: 10px;
            align-items: start;
            color: var(--muted);
        }

        .cbt-flow-number {
            display: grid;
            width: 30px;
            height: 30px;
            place-items: center;
            border-radius: 50%;
            background: #eaf2fa;
            color: var(--primary-dark);
            font-size: .78rem;
            font-weight: 850;
        }

        .cbt-tool-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .cbt-tool {
            display: grid;
            gap: 5px;
            min-height: 92px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 14px;
            color: var(--ink);
            text-decoration: none;
        }

        .cbt-tool:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 20px rgba(21, 71, 122, .1);
        }

        .cbt-tool strong {
            color: var(--primary-dark);
        }

        @media (max-width: 920px) {
            .cbt-choice-grid,
            .cbt-tool-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 620px) {
            .cbt-choice-grid,
            .cbt-tool-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian & Asesmen</p>
            <h1 class="page-title">Pusat CBT</h1>
            <p class="help-text" style="margin-top: 8px; max-width: 720px;">Pilih alur sesuai kegiatan. Siswa mengikuti ujian dari akun NUSA mereka, sehingga tidak ada lagi akun atau kartu peserta CBT terpisah.</p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="panel stat active"><p class="stat-label">Soal siap digunakan</p><p class="stat-value">{{ $jumlahSoalSiap }}</p></div>
        <div class="panel stat"><p class="stat-label">Asesmen kelas</p><p class="stat-value">{{ $jumlahAsesmenKelas }}</p></div>
        <div class="panel stat"><p class="stat-label">Paket terpusat siap</p><p class="stat-value">{{ $jumlahPaketTerpusatSiap }}</p></div>
        <div class="panel stat"><p class="stat-label">Kegiatan terpusat</p><p class="stat-value">{{ $jumlahKegiatanTerpusat }}</p></div>
    </div>

    <div class="cbt-choice-grid">
        <section class="panel panel-pad cbt-choice">
            <p class="cbt-choice-label">Dilaksanakan guru saat mengajar</p>
            <div>
                <h2>Asesmen Kelas</h2>
                <p class="help-text" style="margin-top: 6px;">Untuk ulangan bab atau asesmen yang waktunya ditentukan sendiri oleh guru. Tidak memerlukan panitia, ruang ujian, atau pembagian peserta antarkelas.</p>
            </div>
            <ol class="cbt-flow">
                <li><span class="cbt-flow-number">1</span><span>Siapkan soal pada Bank Soal.</span></li>
                <li><span class="cbt-flow-number">2</span><span>Buat paket, pilih kelas yang diajar, waktu, durasi, dan tujuan nilainya.</span></li>
                <li><span class="cbt-flow-number">3</span><span>Siswa membuka menu Ujian Saya dan mengerjakan saat guru membuka ujian.</span></li>
            </ol>
            <div class="actions">
                @if ($bolehSoal)<a href="{{ route('soal-cbt.index') }}" class="button button-muted">Buka Bank Soal</a>@endif
                @if ($bolehAsesmen)<a href="{{ route('asesmen-kelas-cbt.index') }}" class="button button-primary">Kelola Asesmen Kelas</a>@endif
            </div>
        </section>

        <section class="panel panel-pad cbt-choice central">
            <p class="cbt-choice-label">Dikelola panitia sekolah</p>
            <div>
                <h2>Ujian Terpusat</h2>
                <p class="help-text" style="margin-top: 6px;">Untuk STS, SAS, SAJ, atau ujian bersama. Menggunakan jadwal sekolah, sesi, ruang, pembagian peserta, presensi, dan dokumen panitia.</p>
            </div>
            <ol class="cbt-flow">
                <li><span class="cbt-flow-number">1</span><span>Panitia membuat kegiatan, jadwal, sesi, dan tingkat peserta.</span></li>
                <li><span class="cbt-flow-number">2</span><span>Guru menyiapkan paket soal sesuai mata pelajaran dan tingkat.</span></li>
                <li><span class="cbt-flow-number">3</span><span>Panitia membagi ruang serta peserta, lalu memantau pelaksanaan dan hasil.</span></li>
            </ol>
            <div class="actions">
                @if ($bolehTerpusat)<a href="{{ route('ujian-terpusat.index') }}" class="button button-primary">Buka Ujian Terpusat</a>@endif
                @if ($bolehPaketTerpusat)<a href="{{ route('paket-soal-terpusat.index') }}" class="button button-muted">Paket Soal Terpusat</a>@endif
                @if ($bolehPresensi)<a href="{{ route('presensi-ujian-cbt.index') }}" class="button button-muted">Presensi Ujian</a>@endif
            </div>
        </section>
    </div>

    <section>
        <div class="section-heading" style="margin-bottom: 12px;">
            <div><h2 class="panel-title">Peralatan CBT</h2><p class="help-text">Gunakan sesuai tahap kerja, bukan sebagai alur yang terpisah.</p></div>
        </div>
        <div class="cbt-tool-grid">
            @if ($bolehSoal)
                <a class="cbt-tool" href="{{ route('soal-cbt.index') }}"><strong>Bank Soal</strong><span class="help-text">Tulis dan kelola soal yang dapat digunakan kembali.</span></a>
            @endif
            @if ($bolehTerpusat)
                <a class="cbt-tool" href="{{ route('ujian-terpusat.index') }}"><strong>Ujian Terpusat</strong><span class="help-text">Atur kegiatan, panitia, sesi, dan ruang ujian bersama.</span></a>
            @endif
            @if ($bolehPaketTerpusat)
                <a class="cbt-tool" href="{{ route('paket-soal-terpusat.index') }}"><strong>Paket Soal Terpusat</strong><span class="help-text">Pilih soal untuk jadwal yang sudah disusun panitia.</span></a>
            @endif
            @if ($bolehPresensi)
                <a class="cbt-tool" href="{{ route('presensi-ujian-cbt.index') }}"><strong>Presensi Ujian</strong><span class="help-text">Catat kehadiran siswa pada ruang ujian.</span></a>
            @endif
        </div>
    </section>
@endsection
