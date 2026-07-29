<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak LJK - {{ $ujianOmr->nama }} - NUSA</title>
    <style>
        :root {
            --primary: #15477a;
            --accent: #f1c40f;
            --ink: #111827;
            --muted: #52606d;
            --line: #cbd5e1;
            --paper: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e8eef5;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
        }

        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid #d7e0ea;
            background: #fff;
            padding: 14px 20px;
            box-shadow: 0 4px 18px rgba(21, 71, 122, .12);
        }

        .toolbar-title {
            margin: 0;
            color: var(--primary);
            font-size: 1rem;
            font-weight: 900;
        }

        .toolbar-note {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: .82rem;
        }

        .button {
            border: 0;
            border-radius: 6px;
            background: var(--primary);
            padding: 10px 14px;
            color: #fff;
            cursor: pointer;
            font-size: .88rem;
            font-weight: 800;
        }

        .screen-empty {
            max-width: 720px;
            margin: 32px auto;
            border: 1px solid #d7e0ea;
            border-radius: 8px;
            background: #fff;
            padding: 24px;
            text-align: center;
        }

        .sheet-stack {
            display: grid;
            gap: 14px;
            justify-content: center;
            padding: 16px;
        }

        .a4-sheet {
            display: grid;
            width: 297mm;
            height: 210mm;
            grid-template-columns: repeat(2, 148.5mm);
            overflow: hidden;
            background: #fff;
            box-shadow: 0 6px 24px rgba(15, 23, 42, .14);
        }

        .ljk {
            position: relative;
            width: 148.5mm;
            height: 210mm;
            overflow: hidden;
            border-right: .25mm dashed #94a3b8;
            background: var(--paper);
            padding: 7.5mm 7mm 6.5mm;
        }

        .ljk:nth-child(2) {
            border-right: 0;
        }

        .ljk::before {
            position: absolute;
            inset: 3.8mm;
            border: .25mm solid #d7dee8;
            content: "";
            pointer-events: none;
        }

        .marker {
            position: absolute;
            z-index: 2;
            width: 4.7mm;
            height: 4.7mm;
            background: #000;
        }

        .marker-tl {
            top: 3.8mm;
            left: 3.8mm;
        }

        .marker-tr {
            top: 3.8mm;
            right: 3.8mm;
        }

        .marker-bl {
            bottom: 3.8mm;
            left: 3.8mm;
        }

        .marker-br {
            right: 3.8mm;
            bottom: 3.8mm;
        }

        .ljk-header {
            display: grid;
            min-height: 31mm;
            grid-template-columns: 15mm 1fr 24mm;
            gap: 3mm;
            align-items: start;
            border-bottom: .7mm solid var(--primary);
            padding: 0 1.5mm 2.3mm;
        }

        .school-logo {
            width: 13.5mm;
            height: 13.5mm;
            object-fit: contain;
        }

        .school-name,
        .ljk-title,
        .exam-name,
        .meta-line,
        .instruction,
        .footer-note {
            margin: 0;
        }

        .school-name {
            color: var(--primary);
            font-size: 6.8pt;
            font-weight: 900;
            text-transform: uppercase;
        }

        .ljk-title {
            margin-top: 1.1mm;
            color: var(--ink);
            font-size: 10.2pt;
            font-weight: 900;
            text-transform: uppercase;
        }

        .exam-name {
            margin-top: 1mm;
            color: var(--primary);
            font-size: 7.7pt;
            font-weight: 850;
            line-height: 1.12;
        }

        .qr-code {
            width: 22mm;
            height: 22mm;
            border: .25mm solid #d7dee8;
            background: #fff;
        }

        .qr-code svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .identity-panel {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 2mm;
            border-bottom: .3mm solid var(--line);
            padding: 2.1mm 1.5mm 2mm;
        }

        .student-name {
            overflow: hidden;
            margin: 0;
            color: var(--ink);
            font-size: 8.2pt;
            font-weight: 900;
            line-height: 1.14;
            text-overflow: ellipsis;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .7mm 3mm;
            margin-top: 1mm;
        }

        .meta-line {
            color: #273548;
            font-size: 6.6pt;
            font-weight: 750;
            line-height: 1.15;
        }

        .version-box {
            display: grid;
            min-width: 16mm;
            align-content: center;
            justify-items: center;
            border-radius: 2mm;
            background: var(--accent);
            color: var(--primary);
            padding: 1.2mm 2mm;
        }

        .version-box span {
            font-size: 5.7pt;
            font-weight: 900;
            text-transform: uppercase;
        }

        .version-box strong {
            font-size: 12pt;
            line-height: 1;
        }

        .answer-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6mm;
            padding: 2mm 1.5mm 1.2mm;
        }

        .answer-heading strong {
            color: var(--primary);
            font-size: 7pt;
            text-transform: uppercase;
        }

        .instruction {
            color: var(--muted);
            font-size: 5.8pt;
            font-weight: 700;
        }

        .answer-grid {
            position: absolute;
            top: 61mm;
            right: 9.4mm;
            left: 9.4mm;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 4.8mm;
        }

        .answer-column {
            display: grid;
            grid-template-rows: repeat(25, 4.65mm);
        }

        .answer-row {
            display: grid;
            grid-template-columns: 7mm repeat(4, 1fr);
            align-items: center;
            border-bottom: .2mm solid #e6ebf0;
            color: var(--ink);
            font-size: 6.4pt;
            font-weight: 800;
        }

        .question-number {
            color: var(--primary);
            font-size: 6.8pt;
            font-weight: 900;
        }

        .answer-option {
            display: inline-flex;
            align-items: center;
            gap: 1mm;
        }

        .bubble {
            display: inline-block;
            width: 3.2mm;
            height: 3.2mm;
            flex: 0 0 auto;
            border: .38mm solid #111827;
            border-radius: 50%;
            background: #fff;
        }

        .ljk-footer {
            position: absolute;
            right: 7.8mm;
            bottom: 6.8mm;
            left: 7.8mm;
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 5mm;
            border-top: .45mm solid var(--primary);
            padding-top: 1.5mm;
        }

        .footer-note {
            max-width: 88mm;
            color: var(--muted);
            font-size: 5.7pt;
            font-weight: 700;
            line-height: 1.15;
        }

        .token-label {
            color: var(--primary);
            font-size: 5.7pt;
            font-weight: 900;
            text-align: right;
        }

        .blank-slot {
            background: #fff;
        }

        @page {
            size: A4 landscape;
            margin: 0;
        }

        @media print {
            body {
                background: #fff;
            }

            .print-toolbar,
            .screen-empty {
                display: none !important;
            }

            .sheet-stack {
                display: block;
                padding: 0;
            }

            .a4-sheet {
                break-after: page;
                page-break-after: always;
                box-shadow: none;
            }

            .a4-sheet:last-child {
                break-after: auto;
                page-break-after: auto;
            }

            .ljk {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <header class="print-toolbar">
        <div>
            <h1 class="toolbar-title">LJK siap dicetak - {{ $ujianOmr->nama }}</h1>
            <p class="toolbar-note">{{ $lembarJawab->count() }} lembar personal. Gunakan A4 landscape dengan skala cetak 100%.</p>
        </div>
        @if ($lembarJawab->isNotEmpty())
            <button type="button" class="button" onclick="window.print()">Cetak / Simpan PDF</button>
        @endif
    </header>

    @if ($lembarJawab->isEmpty())
        <section class="screen-empty">
            <h2>Belum ada LJK untuk dicetak</h2>
            <p>Kembali ke detail ujian dan tekan tombol Generate LJK terlebih dahulu.</p>
        </section>
    @else
        <main class="sheet-stack">
            @foreach ($lembarJawab->chunk(2) as $pasangan)
                <section class="a4-sheet">
                    @foreach ($pasangan as $data)
                        @php
                            $lembar = $data['lembar'];
                            $anggota = $lembar->anggotaKelas;
                            $siswa = $anggota?->siswa;
                            $nomorSoal = collect(range(1, $ujianOmr->jumlah_soal))->chunk(25);
                        @endphp
                        <article class="ljk">
                            <span class="marker marker-tl"></span>
                            <span class="marker marker-tr"></span>
                            <span class="marker marker-bl"></span>
                            <span class="marker marker-br"></span>

                            <header class="ljk-header">
                                <img class="school-logo" src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="">
                                <div>
                                    <p class="school-name">SMP Negeri 2 Padang Panjang</p>
                                    <h2 class="ljk-title">Lembar Jawab Komputer</h2>
                                    <p class="exam-name">{{ $ujianOmr->nama }}</p>
                                </div>
                                <div class="qr-code">{!! $data['qr_svg'] !!}</div>
                            </header>

                            <section class="identity-panel">
                                <div>
                                    <h3 class="student-name">{{ $siswa?->nama_lengkap ?: '-' }}</h3>
                                    <div class="meta-grid">
                                        <p class="meta-line">NISN: {{ $siswa?->nisn ?: '-' }}</p>
                                        <p class="meta-line">Kelas: {{ $anggota?->kelas?->nama ?: '-' }}</p>
                                        <p class="meta-line">No. absen: {{ $anggota?->nomor_absen ?: '-' }}</p>
                                        @php
                                            $pengaturanMapel = $ujianOmr->mataPelajaran?->pengaturanUntuk(
                                                (int) $ujianOmr->tahun_pelajaran_id,
                                                (int) $anggota?->kelas?->tingkat,
                                            );
                                        @endphp
                                        <p class="meta-line">Mapel: {{ $pengaturanMapel?->kode ?: $ujianOmr->mataPelajaran?->nama ?: '-' }}</p>
                                    </div>
                                </div>
                                <div class="version-box">
                                    <span>Versi</span>
                                    <strong>{{ $lembar->versiSoalUjianOmr?->kode ?: '-' }}</strong>
                                </div>
                            </section>

                            <div class="answer-heading">
                                <strong>Pilihan Jawaban A-D</strong>
                                <p class="instruction">Hitamkan satu bulatan dengan pensil 2B.</p>
                            </div>

                            <section class="answer-grid">
                                @foreach ($nomorSoal as $kolom)
                                    <div class="answer-column">
                                        @foreach ($kolom as $nomor)
                                            <div class="answer-row">
                                                <span class="question-number">{{ str_pad((string) $nomor, 2, '0', STR_PAD_LEFT) }}</span>
                                                @foreach (['A', 'B', 'C', 'D'] as $pilihan)
                                                    <span class="answer-option"><span class="bubble"></span>{{ $pilihan }}</span>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </section>

                            <footer class="ljk-footer">
                                <p class="footer-note">Jangan melipat kertas. Pastikan bulatan terisi penuh dan tidak melewati batas.</p>
                                <span class="token-label">LJK {{ substr($lembar->token, -6) }}</span>
                            </footer>
                        </article>
                    @endforeach
                    @if ($pasangan->count() === 1)
                        <div class="blank-slot"></div>
                    @endif
                </section>
            @endforeach
        </main>
    @endif
</body>
</html>
