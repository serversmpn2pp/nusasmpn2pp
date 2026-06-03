@extends('layouts.app')

@section('title', 'Kartu Peserta CBT - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <style>
        .cbt-card-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
        }

        .cbt-card-filter {
            display: grid;
            grid-template-columns: minmax(170px, .8fr) minmax(150px, .7fr) auto;
            gap: 12px;
            align-items: end;
        }

        .cbt-card-sheet {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90mm, 1fr));
            gap: 8mm 6mm;
            align-items: start;
        }

        .cbt-exam-card {
            position: relative;
            width: 90mm;
            min-height: 62mm;
            overflow: hidden;
            border: .28mm solid rgba(21, 71, 122, .38);
            border-radius: 3mm;
            background: #fff;
            color: #111827;
            box-shadow: var(--shadow);
            isolation: isolate;
        }

        .cbt-exam-card::before {
            position: absolute;
            top: -19mm;
            right: -16mm;
            width: 43mm;
            height: 43mm;
            border-radius: 50%;
            background: var(--accent);
            content: "";
            opacity: .9;
            z-index: -1;
        }

        .cbt-exam-card::after {
            position: absolute;
            right: -18mm;
            bottom: -24mm;
            width: 58mm;
            height: 58mm;
            border: 8mm solid rgba(21, 71, 122, .08);
            border-radius: 50%;
            content: "";
            z-index: -1;
        }

        .cbt-card-top {
            display: grid;
            grid-template-columns: 10mm 1fr auto;
            gap: 2.4mm;
            align-items: center;
            background: var(--primary);
            padding: 3mm 3.5mm;
            color: #fff;
        }

        .cbt-card-logo {
            display: grid;
            width: 9mm;
            height: 9mm;
            place-items: center;
            overflow: hidden;
            border-radius: 50%;
            background: #fff;
            padding: .8mm;
        }

        .cbt-card-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .cbt-card-kicker,
        .cbt-card-title,
        .cbt-card-exam,
        .cbt-card-name,
        .cbt-card-meta,
        .cbt-card-foot {
            margin: 0;
        }

        .cbt-card-kicker {
            color: rgba(255, 255, 255, .82);
            font-size: 5.2pt;
            font-weight: 850;
            line-height: 1.1;
            text-transform: uppercase;
        }

        .cbt-card-title {
            margin-top: .6mm;
            font-size: 8.2pt;
            font-weight: 950;
            line-height: 1.1;
            text-transform: uppercase;
        }

        .cbt-card-badge {
            border-radius: 999px;
            background: var(--accent);
            padding: 1.1mm 2mm;
            color: var(--primary);
            font-size: 5.6pt;
            font-weight: 950;
            white-space: nowrap;
        }

        .cbt-card-body {
            display: grid;
            grid-template-columns: 1fr 25mm;
            gap: 3mm;
            padding: 3.2mm 3.5mm 2.8mm;
        }

        .cbt-card-exam {
            color: var(--primary);
            font-size: 7.2pt;
            font-weight: 950;
            line-height: 1.12;
        }

        .cbt-card-name {
            overflow: hidden;
            margin-top: 2mm;
            color: #0f172a;
            font-weight: 950;
            line-height: 1.05;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .cbt-card-facts {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.1mm 2.2mm;
            margin-top: 2mm;
        }

        .cbt-card-meta {
            color: #334155;
            font-size: 5.9pt;
            font-weight: 780;
            line-height: 1.15;
        }

        .cbt-card-meta strong {
            color: #0f172a;
            font-weight: 950;
        }

        .cbt-login-box {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.2mm;
            margin-top: 2.3mm;
        }

        .cbt-login-item {
            border-radius: 1.6mm;
            background: #eef4fb;
            padding: 1.2mm 1.6mm;
        }

        .cbt-login-item span {
            display: block;
            color: #64748b;
            font-size: 4.9pt;
            font-weight: 850;
            text-transform: uppercase;
        }

        .cbt-login-item strong {
            display: block;
            overflow: hidden;
            color: var(--primary);
            font-size: 6.7pt;
            font-weight: 950;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .cbt-qr-box {
            display: grid;
            align-content: center;
            justify-items: center;
            border: .25mm solid #d8e2ef;
            border-radius: 2.4mm;
            background: #fff;
            padding: 1.6mm;
        }

        .cbt-qr-code {
            width: 21mm;
            height: 21mm;
        }

        .cbt-qr-code svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .cbt-qr-label {
            margin: .9mm 0 0;
            color: var(--primary);
            font-size: 4.7pt;
            font-weight: 950;
            line-height: 1.05;
            text-align: center;
            text-transform: uppercase;
        }

        .cbt-card-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2mm;
            border-top: .25mm solid #d8e2ef;
            padding: 1.8mm 3.5mm 2.3mm;
            color: #475569;
            font-size: 5.2pt;
            font-weight: 780;
            line-height: 1.15;
        }

        .cbt-card-foot strong {
            color: var(--primary);
            font-weight: 950;
            white-space: nowrap;
        }

        @media (max-width: 1100px) {
            .cbt-card-filter {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            .app-sidebar,
            .app-topbar,
            .page-header,
            .cbt-card-filter-panel,
            .cbt-card-toolbar {
                display: none !important;
            }

            body {
                background: #fff;
            }

            .app-shell,
            .app-content {
                display: block;
                min-height: auto;
                background: #fff;
            }

            .content-shell {
                width: auto;
                max-width: none;
                padding: 0;
            }

            .cbt-card-sheet {
                grid-template-columns: repeat(2, 90mm);
                gap: 6mm 6mm;
            }

            .cbt-exam-card {
                break-inside: avoid;
                page-break-inside: avoid;
                box-shadow: none;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Kartu peserta ujian</h1>
        </div>

        <div class="actions">
            <button type="button" class="button button-primary" onclick="window.print()">Cetak kartu</button>
            <a href="{{ route('ujian-cbt.peserta.index', $ujianCbt) }}" class="button button-muted">Peserta & sesi</a>
            <a href="{{ route('ujian-cbt.show', $ujianCbt) }}" class="button button-muted">Detail paket</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ada data yang perlu diperbaiki.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('ujian-cbt.kartu-peserta.index', $ujianCbt) }}" method="GET" class="panel panel-pad cbt-card-filter-panel" style="margin-bottom: 24px;">
        <div class="cbt-card-filter">
            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select">
                    <option value="">Semua kelas</option>
                    @foreach ($kelasPeserta as $kelasUjian)
                        <option value="{{ $kelasUjian->kelas_id }}" @selected((string) $kelasId === (string) $kelasUjian->kelas_id)>{{ $kelasUjian->kelas?->nama ?: '-' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="semua" @selected($status === 'semua')>Semua</option>
                    @foreach ($daftarStatusPeserta as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('ujian-cbt.kartu-peserta.index', $ujianCbt) }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <div class="cbt-card-toolbar">
        <div>
            <h2 class="panel-title">{{ $kartuPeserta->count() }} kartu siap cetak</h2>
            <p class="help-text" style="margin-top: 4px;">Format A4 portrait, 8 kartu per halaman. QR berisi username peserta; token ujian diberikan oleh pengawas.</p>
        </div>
        @if ($kartuPeserta->isNotEmpty())
            <button type="button" class="button button-primary" onclick="window.print()">Cetak kartu</button>
        @endif
    </div>

    @if ($kartuPeserta->isEmpty())
        <section class="panel panel-pad">
            <h2 class="panel-title">Belum ada kartu peserta</h2>
            <p class="help-text" style="margin-top: 8px;">Generate peserta CBT terlebih dahulu, atau ubah filter kelas dan status.</p>
        </section>
    @else
        <section class="cbt-card-sheet">
            @foreach ($kartuPeserta as $kartu)
                @php
                    $peserta = $kartu['peserta'];
                    $akun = $kartu['akun'];
                    $anggota = $peserta->anggotaKelas;
                    $siswa = $anggota?->siswa;
                    $kelas = $peserta->kelasUjianCbt?->kelas;
                @endphp

                <article class="cbt-exam-card" aria-label="Kartu peserta CBT {{ $siswa?->nama_lengkap }}">
                    <header class="cbt-card-top">
                        <div class="cbt-card-logo">
                            <img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="">
                        </div>
                        <div>
                            <p class="cbt-card-kicker">SMP Negeri 2 Padang Panjang</p>
                            <h2 class="cbt-card-title">Kartu Peserta CBT</h2>
                        </div>
                        <span class="cbt-card-badge">Kelas {{ $ujianCbt->tingkat }}</span>
                    </header>

                    <div class="cbt-card-body">
                        <div>
                            <p class="cbt-card-exam">{{ $ujianCbt->jenisUjianCbt?->nama ?: 'Rangkaian CBT' }} - {{ ucfirst($ujianCbt->semester) }}</p>
                            <h3 class="cbt-card-name" style="font-size: {{ $kartu['ukuran_font_nama'] }}pt;">{{ $siswa?->nama_lengkap ?: '-' }}</h3>

                            <div class="cbt-card-facts">
                                <p class="cbt-card-meta">No: <strong>{{ $akun?->nomor_peserta ?: $peserta->nomor_peserta }}</strong></p>
                                <p class="cbt-card-meta">Kelas: <strong>{{ $kelas?->nama ?: '-' }}</strong></p>
                                <p class="cbt-card-meta">NISN: <strong>{{ $teks($siswa?->nisn) }}</strong></p>
                                <p class="cbt-card-meta">Tahun: <strong>{{ $ujianCbt->tahunPelajaran?->nama ?: '-' }}</strong></p>
                                <p class="cbt-card-meta">Semester: <strong>{{ ucfirst($ujianCbt->semester) }}</strong></p>
                                <p class="cbt-card-meta">Status: <strong>{{ $akun?->labelStatus() ?: $peserta->labelStatus() }}</strong></p>
                            </div>

                            <div class="cbt-login-box">
                                <div class="cbt-login-item">
                                    <span>Username</span>
                                    <strong>{{ $akun?->username ?: $peserta->username }}</strong>
                                </div>
                                <div class="cbt-login-item">
                                    <span>Password</span>
                                    <strong>{{ $akun?->kata_sandi ?: $peserta->kata_sandi }}</strong>
                                </div>
                            </div>
                        </div>

                        <aside class="cbt-qr-box">
                            <div class="cbt-qr-code">{!! $kartu['qr_svg'] !!}</div>
                            <p class="cbt-qr-label">QR Identitas CBT</p>
                        </aside>
                    </div>

                    <footer class="cbt-card-foot">
                        <span>Token ujian diberikan oleh pengawas/proktor.</span>
                        <span>NUSA CBT</span>
                    </footer>
                </article>
            @endforeach
        </section>
    @endif
@endsection
