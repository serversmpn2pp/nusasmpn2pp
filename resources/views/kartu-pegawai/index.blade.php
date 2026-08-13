@extends('layouts.app')

@section('title', 'Kartu Pegawai - NUSA')

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

        .employee-card-sheet {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            align-items: flex-start;
        }

        .employee-card-pair {
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

        .employee-id-card {
            position: relative;
            width: 53.98mm;
            height: 85.6mm;
            overflow: hidden;
            border-radius: 3mm;
            background-color: var(--primary);
            background-position: center;
            background-size: cover;
            box-shadow: inset 0 0 0 .35mm rgba(255, 255, 255, .36);
            color: #fff;
            isolation: isolate;
        }

        .employee-card-front {
            background-image: url("{{ asset('images/kartu-pegawai/teacher-front.jpg') }}");
        }

        .employee-card-back {
            background-image: url("{{ asset('images/kartu-pegawai/teacher-back.jpg') }}");
        }

        .employee-card-content {
            position: relative;
            display: grid;
            height: 100%;
            padding: 4.2mm;
        }

        .employee-card-front .employee-card-content {
            grid-template-rows: auto auto 1fr auto;
            gap: 2.2mm;
            align-items: center;
            text-align: center;
        }

        .employee-front-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2mm;
            min-width: 0;
        }

        .employee-logo-row {
            display: flex;
            align-items: center;
            gap: 1.4mm;
        }

        .employee-logo {
            display: grid;
            width: 9.5mm;
            height: 9.5mm;
            place-items: center;
            overflow: hidden;
            border: .35mm solid rgba(241, 196, 15, .9);
            border-radius: 50%;
            background: rgba(255, 255, 255, .96);
            padding: .8mm;
            box-shadow: 0 1.8mm 4.5mm rgba(4, 18, 35, .18);
        }

        .employee-logo img,
        .employee-back-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .employee-kicker {
            margin: 0;
            color: rgba(255, 255, 255, .86);
            font-size: 4.8pt;
            font-weight: 900;
            line-height: 1.1;
            text-align: left;
            text-transform: uppercase;
        }

        .employee-card-label {
            border: .35mm solid rgba(255, 255, 255, .82);
            border-radius: 999px;
            background: rgba(241, 196, 15, .95);
            padding: 1mm 2.2mm;
            color: var(--primary);
            font-size: 5.2pt;
            font-weight: 950;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .employee-photo-ring {
            display: grid;
            width: 29mm;
            height: 34mm;
            justify-self: center;
            place-items: center;
            border-radius: 5mm;
            background: rgba(241, 196, 15, .95);
            padding: .9mm;
            box-shadow: 0 5mm 12mm rgba(4, 18, 35, .24);
        }

        .employee-photo-card {
            width: 100%;
            height: 100%;
            overflow: hidden;
            border: .55mm solid #fff;
            border-radius: 4.2mm;
            background: #fff;
        }

        .employee-photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .employee-identity-card {
            align-self: center;
            display: grid;
            gap: .9mm;
            min-width: 0;
        }

        .employee-name-card {
            margin: 0;
            max-width: 100%;
            overflow: hidden;
            color: #fff;
            font-weight: 950;
            line-height: 1.05;
            text-transform: uppercase;
            white-space: nowrap;
            text-shadow: 0 1mm 2.6mm rgba(0, 0, 0, .42);
        }

        .employee-nip-card {
            justify-self: center;
            margin: 0;
            border-radius: 999px;
            background: rgba(255, 255, 255, .94);
            padding: .8mm 2.4mm;
            color: var(--primary);
            font-size: 6.3pt;
            font-weight: 950;
        }

        .employee-role-card {
            margin: 0;
            color: rgba(255, 255, 255, .94);
            font-size: 6pt;
            font-weight: 850;
            line-height: 1.16;
            text-shadow: 0 .8mm 1.8mm rgba(0, 0, 0, .34);
        }

        .employee-footer-card {
            align-self: end;
            border-radius: 2mm;
            background: rgba(255, 255, 255, .94);
            padding: 1.35mm 2.2mm;
            color: var(--primary);
            font-size: 5.8pt;
            font-weight: 950;
            line-height: 1.15;
            text-transform: uppercase;
            box-shadow: 0 2mm 6mm rgba(4, 18, 35, .18);
        }

        .employee-card-back .employee-card-content {
            grid-template-rows: auto 1fr auto;
            gap: 3mm;
            align-items: center;
            text-align: center;
            padding: 5mm 4.4mm;
        }

        .employee-back-header {
            display: grid;
            justify-items: center;
            gap: 1.6mm;
        }

        .employee-back-logo {
            display: grid;
            width: 17mm;
            height: 17mm;
            place-items: center;
            border-radius: 4mm;
            background: rgba(255, 255, 255, .96);
            padding: 1.5mm;
            box-shadow: 0 3mm 8mm rgba(4, 18, 35, .22);
        }

        .employee-back-title {
            margin: 0;
            color: #fff;
            font-size: 7.2pt;
            font-weight: 950;
            line-height: 1.15;
            text-shadow: 0 1mm 2.5mm rgba(0, 0, 0, .35);
            text-transform: uppercase;
        }

        .employee-qr-shell {
            display: grid;
            justify-self: center;
            width: 36mm;
            min-height: 41mm;
            align-content: center;
            justify-items: center;
            border-radius: 4mm;
            background: rgba(255, 255, 255, .96);
            padding: 2.8mm;
            color: var(--primary);
            box-shadow: 0 6mm 16mm rgba(4, 18, 35, .24);
        }

        .employee-qr-code {
            width: 29.5mm;
            height: 29.5mm;
        }

        .employee-qr-code svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .employee-qr-nip {
            margin: 1.6mm 0 0;
            color: var(--primary);
            font-size: 5.8pt;
            font-weight: 950;
            line-height: 1.1;
        }

        .employee-back-note {
            margin: 0;
            color: rgba(255, 255, 255, .92);
            font-size: 6pt;
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

        .employee-filter-grid {
            display: grid;
            grid-template-columns: 200px minmax(260px, 1fr) 150px auto;
            gap: 12px;
            align-items: end;
        }

        @media (max-width: 1100px) {
            .employee-filter-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .employee-card-pair {
                width: 100%;
                justify-content: center;
            }
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        @media print {
            .app-sidebar,
            .app-topbar,
            .page-header,
            .card-toolbar,
            .kartu-filter,
            .side-label {
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

            .employee-card-sheet {
                gap: 6mm;
            }

            .employee-card-pair {
                break-inside: avoid;
                page-break-inside: avoid;
                border: 0;
                border-radius: 0;
                box-shadow: none;
                padding: 0;
                gap: 5mm;
            }

            .employee-id-card {
                box-shadow: inset 0 0 0 .25mm rgba(255, 255, 255, .45);
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Pegawai</p>
            <h1 class="page-title">Kartu pegawai</h1>
        </div>

        <div class="actions">
            <button type="button" class="button button-primary" onclick="window.print()">Cetak kartu</button>
            @izin('pegawai.kelola')
                <a href="{{ route('foto-identitas.index', ['tab' => 'pegawai']) }}" class="button button-dark">Kelola foto</a>
            @endizin
            @izin('pegawai.lihat', 'pegawai.kelola')
                <a href="{{ route('pegawai.index') }}" class="button button-muted">Data pegawai</a>
            @endizin
        </div>
    </div>

    <form action="{{ route('kartu-pegawai.index') }}" method="GET" class="panel panel-pad kartu-filter" style="margin-bottom: 24px;">
        <div class="employee-filter-grid">
            <div class="field">
                <label for="jenis_pegawai">Jenis pegawai</label>
                <select id="jenis_pegawai" name="jenis_pegawai" class="select">
                    <option value="">Semua jenis</option>
                    @foreach ($daftarJenisPegawai as $item)
                        <option value="{{ $item }}" @selected($jenisPegawai === $item)>{{ $item }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="pegawai_id">Pegawai</label>
                <select id="pegawai_id" name="pegawai_id" class="select">
                    <option value="">Semua pegawai</option>
                    @foreach ($daftarPegawai as $pegawai)
                        <option value="{{ $pegawai->id }}" @selected((string) $pegawaiId === (string) $pegawai->id)>
                            {{ $pegawai->nama_lengkap }} - {{ $pegawai->nip ?: 'NIP kosong' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="aktif" @selected($status === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
                    <option value="semua" @selected($status === 'semua')>Semua</option>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('kartu-pegawai.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <div class="card-toolbar">
        <div>
            <h2 class="panel-title">{{ $kartuPegawai->count() }} kartu siap cetak</h2>
            <p class="help-text" style="margin-top: 4px;">Ukuran kartu: 53,98mm x 85,60mm, posisi portrait.</p>
        </div>
        <button type="button" class="button button-primary" onclick="window.print()">Cetak kartu</button>
    </div>

    @if ($kartuPegawai->isEmpty())
        <section class="panel panel-pad">
            <h2 class="panel-title">Belum ada pegawai</h2>
            <p class="help-text" style="margin-top: 8px;">Pilih pegawai aktif atau periksa kembali data NIP pegawai.</p>
        </section>
    @else
        <section class="employee-card-sheet">
            @foreach ($kartuPegawai as $kartu)
                @php
                    $pegawai = $kartu['pegawai'];
                @endphp

                <article class="employee-card-pair">
                    <div>
                        <div class="employee-id-card employee-card-front" aria-label="Kartu pegawai depan {{ $pegawai->nama_lengkap }}">
                            <div class="employee-card-content">
                                <div class="employee-front-header">
                                    <div class="employee-logo-row">
                                        <div class="employee-logo">
                                            <img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo SMP Negeri 2 Padang Panjang">
                                        </div>
                                        <p class="employee-kicker">SMP Negeri 2<br>Padang Panjang</p>
                                    </div>
                                    <div class="employee-card-label">Kartu Pegawai</div>
                                </div>

                                <div class="employee-photo-ring">
                                    <div class="employee-photo-card">
                                        <img src="{{ $kartu['foto_url'] }}" alt="Foto {{ $pegawai->nama_lengkap }}">
                                    </div>
                                </div>

                                <div class="employee-identity-card">
                                    <h2 class="employee-name-card" style="font-size: {{ $kartu['ukuran_font_nama'] }}pt;">{{ $pegawai->nama_lengkap }}</h2>
                                    <p class="employee-nip-card">NIP {{ $teks($pegawai->nip) }}</p>
                                    <p class="employee-role-card">{{ $teks($kartu['jabatan']) }}</p>
                                </div>

                                <div class="employee-footer-card">SMP Negeri 2 Padang Panjang</div>
                            </div>
                        </div>
                        <p class="side-label">Depan</p>
                    </div>

                    <div>
                        <div class="employee-id-card employee-card-back" aria-label="Kartu pegawai belakang {{ $pegawai->nama_lengkap }}">
                            <div class="employee-card-content">
                                <div class="employee-back-header">
                                    <div class="employee-back-logo">
                                        <img src="{{ asset('images/kartu-pelajar/logo-nusa.png') }}" alt="Logo NUSA">
                                    </div>
                                    <p class="employee-back-title">Presensi Pegawai NUSA</p>
                                </div>

                                <div class="employee-qr-shell">
                                    @if ($kartu['qr_svg'])
                                        <div class="employee-qr-code">{!! $kartu['qr_svg'] !!}</div>
                                        <p class="employee-qr-nip">NIP {{ $teks($pegawai->nip) }}</p>
                                    @else
                                        <p class="employee-qr-nip">QR belum tersedia</p>
                                        <p class="help-text" style="margin-top: 1mm; font-size: 5.2pt;">NIP harus berupa angka.</p>
                                    @endif
                                </div>

                                <p class="employee-back-note">
                                    Scan QR ini untuk layanan presensi pegawai SMP Negeri 2 Padang Panjang.
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
