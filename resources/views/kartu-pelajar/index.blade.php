@extends('layouts.app')

@section('title', 'Kartu Pelajar - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <style>
        .card-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
        }

        .card-sheet {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            align-items: flex-start;
        }

        .student-card-pair {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-start;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 12px;
            box-shadow: var(--shadow);
        }

        .id-card {
            position: relative;
            width: 53.98mm;
            height: 85.6mm;
            overflow: hidden;
            border-radius: 3mm;
            background-color: var(--primary);
            background-position: center;
            background-size: cover;
            box-shadow: inset 0 0 0 .35mm rgba(255, 255, 255, .34);
            color: #fff;
            isolation: isolate;
        }

        .id-card::after {
            position: absolute;
            inset: 0;
            z-index: -1;
            background: linear-gradient(180deg, rgba(7, 28, 54, .12), rgba(7, 28, 54, .36));
            content: "";
        }

        .card-front {
            background-image: url("{{ asset('images/kartu-pelajar/front.jpg') }}");
        }

        .card-back {
            background-image: url("{{ asset('images/kartu-pelajar/back.jpg') }}");
        }

        .card-content {
            position: relative;
            display: grid;
            height: 100%;
            padding: 5.5mm 4.6mm;
        }

        .card-front .card-content {
            grid-template-rows: auto auto 1fr auto;
            gap: 2mm;
            align-items: center;
            padding: 4.2mm;
            text-align: center;
        }

        .front-header-card,
        .back-header-card {
            display: grid;
            justify-items: center;
            gap: 1.6mm;
        }

        .school-logo-card {
            display: grid;
            width: 10.5mm;
            height: 10.5mm;
            place-items: center;
            overflow: hidden;
            border: .45mm solid rgba(241, 196, 15, .88);
            border-radius: 50%;
            background: rgba(255, 255, 255, .96);
            padding: .9mm;
            box-shadow: 0 2mm 5mm rgba(4, 18, 35, .18);
        }

        .school-logo-card img,
        .back-logo-card img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .card-label {
            justify-self: center;
            border: .35mm solid rgba(241, 196, 15, .92);
            border-radius: 999px;
            background: rgba(21, 71, 122, .72);
            padding: 1.1mm 3mm;
            color: #fff;
            font-size: 5.6pt;
            font-weight: 900;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .student-photo-card {
            width: 24.5mm;
            height: 28.8mm;
            justify-self: center;
            overflow: hidden;
            border: .9mm solid var(--accent);
            border-radius: 4mm;
            background: #fff;
            box-shadow: 0 5mm 12mm rgba(4, 18, 35, .22);
        }

        .student-photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .student-identity-card {
            align-self: center;
            display: grid;
            gap: .8mm;
            min-width: 0;
        }

        .student-name-card {
            margin: 0;
            max-width: 100%;
            overflow: hidden;
            overflow-wrap: anywhere;
            color: #fff;
            font-weight: 950;
            line-height: 1.05;
            white-space: nowrap;
            text-transform: uppercase;
            text-shadow: 0 1mm 2.5mm rgba(0, 0, 0, .42);
        }

        .student-nisn-card {
            justify-self: center;
            margin: 0;
            border-radius: 999px;
            background: rgba(255, 255, 255, .92);
            padding: .8mm 2.6mm;
            color: var(--primary);
            font-size: 6.8pt;
            font-weight: 950;
        }

        .student-birth-card {
            margin: 0;
            color: rgba(255, 255, 255, .94);
            font-size: 6.2pt;
            font-weight: 850;
            line-height: 1.16;
            text-shadow: 0 .8mm 1.8mm rgba(0, 0, 0, .34);
        }

        .front-brand-card {
            align-self: end;
            border-radius: 2mm;
            background: var(--accent);
            padding: 1.35mm 2.2mm;
            color: var(--primary);
            font-size: 6.1pt;
            font-weight: 950;
            line-height: 1.15;
            text-transform: uppercase;
            box-shadow: 0 2mm 6mm rgba(4, 18, 35, .18);
        }

        .card-back .card-content {
            grid-template-rows: auto 1fr auto;
            gap: 3mm;
            align-items: center;
            text-align: center;
        }

        .back-logo-card {
            display: grid;
            width: 18mm;
            height: 18mm;
            place-items: center;
            border-radius: 4mm;
            background: rgba(255, 255, 255, .96);
            padding: 1.5mm;
            box-shadow: 0 3mm 8mm rgba(4, 18, 35, .2);
        }

        .back-title-card {
            margin: 0;
            color: #fff;
            font-size: 7.6pt;
            font-weight: 950;
            line-height: 1.15;
            text-shadow: 0 1mm 2.5mm rgba(0, 0, 0, .35);
            text-transform: uppercase;
        }

        .qr-shell-card {
            display: grid;
            justify-self: center;
            width: 35mm;
            min-height: 37mm;
            align-content: center;
            justify-items: center;
            border-radius: 4mm;
            background: rgba(255, 255, 255, .96);
            padding: 3mm;
            color: var(--primary);
            box-shadow: 0 6mm 16mm rgba(4, 18, 35, .24);
        }

        .qr-code-card {
            width: 30mm;
            height: 30mm;
        }

        .qr-code-card svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .back-note-card {
            margin: 0;
            color: rgba(255, 255, 255, .92);
            font-size: 6.4pt;
            font-weight: 800;
            line-height: 1.25;
            text-shadow: 0 1mm 2mm rgba(0, 0, 0, .28);
        }

        .side-label {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: .82rem;
            font-weight: 800;
            text-align: center;
        }

        @media (max-width: 760px) {
            .student-card-pair {
                width: 100%;
                justify-content: center;
            }
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        @media print {
            .app-header,
            .page-header,
            .card-toolbar,
            .kartu-filter,
            .side-label {
                display: none !important;
            }

            body {
                background: #fff;
            }

            main.container {
                width: auto;
                max-width: none;
                padding: 0;
            }

            .card-sheet {
                gap: 6mm;
            }

            .student-card-pair {
                break-inside: avoid;
                page-break-inside: avoid;
                border: 0;
                border-radius: 0;
                box-shadow: none;
                padding: 0;
                gap: 5mm;
            }

            .id-card {
                box-shadow: inset 0 0 0 .25mm rgba(255, 255, 255, .45);
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Siswa</p>
            <h1 class="page-title">Kartu pelajar</h1>
        </div>

        <div class="actions">
            @izin('kartu_pelajar.cetak')
                <button type="button" class="button button-primary" onclick="window.print()">Cetak kartu</button>
            @endizin
            @izin('siswa.kelola')
                <a href="{{ route('foto-identitas.index', array_filter([
                    'tab' => 'siswa',
                    'tahun_pelajaran_id' => $tahunPelajaranId,
                    'kelas_id' => $kelasId,
                ])) }}" class="button button-dark">Kelola foto</a>
            @endizin
            @izin('siswa.lihat', 'siswa.kelola')
                <a href="{{ route('siswa.index') }}" class="button button-muted">Data siswa</a>
            @endizin
        </div>
    </div>

    <form action="{{ route('kartu-pelajar.index') }}" method="GET" class="panel panel-pad kartu-filter" style="margin-bottom: 24px;">
        <div class="filter-grid filter-grid-wide">
            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    @forelse ($daftarTahunPelajaran as $tahunPelajaran)
                        <option value="{{ $tahunPelajaran->id }}" @selected((string) $tahunPelajaranId === (string) $tahunPelajaran->id)>
                            {{ $tahunPelajaran->nama }}{{ $tahunPelajaran->aktif ? ' - aktif' : '' }}
                        </option>
                    @empty
                        <option value="">Belum ada tahun pelajaran</option>
                    @endforelse
                </select>
            </div>

            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select">
                    <option value="">Semua kelas</option>
                    @foreach ($daftarKelas as $kelas)
                        <option value="{{ $kelas->id }}" @selected((string) $kelasId === (string) $kelas->id)>
                            {{ $kelas->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="siswa_id">Siswa</label>
                <select id="siswa_id" name="siswa_id" class="select">
                    <option value="">Semua siswa</option>
                    @foreach ($daftarSiswa as $siswa)
                        <option value="{{ $siswa->id }}" @selected((string) $siswaId === (string) $siswa->id)>
                            {{ $siswa->nama_lengkap }} - {{ $siswa->nisn ?: 'NISN kosong' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('kartu-pelajar.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <div class="card-toolbar">
        <div>
            <h2 class="panel-title">{{ $kartuPelajar->count() }} kartu siap cetak</h2>
            <p class="help-text" style="margin-top: 4px;">Ukuran kartu: 53,98mm x 85,60mm, posisi portrait.</p>
        </div>
        @izin('kartu_pelajar.cetak')
            <button type="button" class="button button-primary" onclick="window.print()">Cetak kartu</button>
        @endizin
    </div>

    @if ($kartuPelajar->isEmpty())
        <section class="panel panel-pad">
            <h2 class="panel-title">Belum ada siswa</h2>
            <p class="help-text" style="margin-top: 8px;">Pilih tahun pelajaran dan kelas yang sudah memiliki siswa aktif.</p>
        </section>
    @else
        <section class="card-sheet">
            @foreach ($kartuPelajar as $kartu)
                @php
                    $siswa = $kartu['siswa'];
                    $anggota = $kartu['anggota_kelas'];
                @endphp

                <article class="student-card-pair">
                    <div>
                        <div class="id-card card-front" aria-label="Kartu pelajar depan {{ $siswa?->nama_lengkap }}">
                            <div class="card-content">
                                <div class="front-header-card">
                                    <div class="school-logo-card">
                                        <img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo SMP Negeri 2 Padang Panjang">
                                    </div>
                                    <div class="card-label">Kartu Pelajar</div>
                                </div>

                                <div class="student-photo-card">
                                    <img src="{{ $kartu['foto_url'] }}" alt="Foto {{ $siswa?->nama_lengkap ?: 'siswa' }}">
                                </div>

                                <div class="student-identity-card">
                                    <h2 class="student-name-card" style="font-size: {{ $kartu['ukuran_font_nama'] }}pt;">{{ $siswa?->nama_lengkap ?: '-' }}</h2>
                                    <p class="student-nisn-card">NISN {{ $teks($siswa?->nisn) }}</p>
                                    <p class="student-birth-card">{{ $kartu['tempat_tanggal_lahir'] }}</p>
                                </div>

                                <div class="front-brand-card">SMP Negeri 2 Padang Panjang</div>
                            </div>
                        </div>
                        <p class="side-label">Depan - {{ $anggota->kelas?->nama ?: '-' }}</p>
                    </div>

                    <div>
                        <div class="id-card card-back" aria-label="Kartu pelajar belakang {{ $siswa?->nama_lengkap }}">
                            <div class="card-content">
                                <div class="back-header-card">
                                    <div class="back-logo-card">
                                        <img src="{{ asset('images/kartu-pelajar/logo-nusa.png') }}" alt="Logo NUSA">
                                    </div>
                                    <p class="back-title-card">Absensi Digital NUSA</p>
                                </div>

                                <div class="qr-shell-card">
                                    @if ($kartu['qr_svg'])
                                        <div class="qr-code-card">{!! $kartu['qr_svg'] !!}</div>
                                    @else
                                        <p class="student-nisn-card">QR belum tersedia</p>
                                    @endif
                                </div>

                                <p class="back-note-card">
                                    Scan QR ini untuk layanan absensi siswa SMP Negeri 2 Padang Panjang.
                                </p>
                            </div>
                        </div>
                        <p class="side-label">Belakang</p>
                    </div>
                </article>
            @endforeach
        </section>
    @endif
@endsection
