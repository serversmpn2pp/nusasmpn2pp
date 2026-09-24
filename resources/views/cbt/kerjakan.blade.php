@extends('cbt.layout')

@section('title', 'Mengerjakan Ujian - CBT NUSA')

@push('styles')
    <style>
        .exam-top-status {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            gap: 10px;
        }

        .exam-compact-timer {
            display: grid;
            min-width: 104px;
            border: 1px solid rgba(241, 196, 15, .78);
            border-radius: 7px;
            background: #fff9dc;
            padding: 5px 10px;
            text-align: center;
        }

        .exam-compact-timer small {
            color: #6d5a00;
            font-size: .63rem;
            font-weight: 900;
            line-height: 1;
            text-transform: uppercase;
        }

        .exam-compact-timer strong {
            color: var(--primary-dark);
            font-size: 1rem;
            line-height: 1.25;
        }

        .question-card[hidden] {
            display: none !important;
        }

        .question-card {
            min-width: 0;
            min-height: 410px;
        }

        .question-position {
            color: var(--muted);
            font-size: .82rem;
            font-weight: 850;
        }

        .answer-save-state {
            display: inline-flex;
            min-width: 0;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: .84rem;
            font-weight: 850;
        }

        .answer-save-state::before {
            width: 9px;
            height: 9px;
            flex: 0 0 auto;
            border-radius: 50%;
            background: #94a3b8;
            content: '';
        }

        .answer-save-state.is-saving::before {
            background: #d6a600;
            box-shadow: 0 0 0 4px rgba(241, 196, 15, .18);
        }

        .answer-save-state.is-saved::before {
            background: #16815a;
        }

        .answer-save-state.is-unsaved::before,
        .answer-save-state.is-failed::before {
            background: #c0392b;
        }

        .answer-save-state.is-failed {
            color: #a42828;
        }

        .question-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 9px;
        }

        .question-actions .button:disabled,
        .finish-action .button:disabled {
            cursor: not-allowed;
            opacity: .42;
        }

        .finish-action {
            display: grid;
            max-width: 260px;
            gap: 5px;
            border-left: 1px solid var(--line);
            padding-left: 12px;
        }

        .finish-availability {
            color: var(--muted);
            font-size: .72rem;
            font-weight: 750;
            line-height: 1.35;
            text-align: right;
        }

        .button-finish {
            border-color: #9f2f2f;
            background: #9f2f2f;
        }

        .finish-acknowledgement {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            border: 1px solid #e5c84c;
            background: #fffbea;
            padding: 11px 12px;
            color: #594800;
            font-size: .86rem;
            font-weight: 750;
            line-height: 1.45;
        }

        .finish-acknowledgement input {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
            margin-top: 1px;
            accent-color: var(--primary);
        }

        .finish-acknowledgement[hidden] {
            display: none;
        }

        #retrySave[hidden] { display: none !important; }

        .nav-number {
            cursor: pointer;
        }

        .nav-number.is-answered {
            border-color: #70b394;
            background: #e1f3e9;
            color: #116644;
        }

        .nav-number.is-partial {
            border-color: #ca8a04;
            background: #fff7df;
            color: #854d0e;
            border-style: dashed;
        }

        .question-legend .legend-partial { background: #fff7df; border-color: #ca8a04; border-style: dashed; }

        .nav-number.is-doubt {
            border-color: #e1b100;
            background: #fff3bd;
            color: #755800;
        }

        .nav-number.is-unsaved {
            border-color: #d97870;
            box-shadow: inset 0 0 0 1px #d97870;
        }

        .nav-number.is-current {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
            box-shadow: 0 0 0 3px rgba(21, 71, 122, .15);
        }

        .question-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            margin-top: 12px;
            color: var(--muted);
            font-size: .75rem;
            font-weight: 800;
        }

        .question-legend span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .question-legend i {
            width: 10px;
            height: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            background: #fff;
        }

        .question-legend .legend-answered {
            border-color: #70b394;
            background: #e1f3e9;
        }

        .question-legend .legend-doubt {
            border-color: #e1b100;
            background: #fff3bd;
        }

        .question-legend .legend-current {
            border-color: var(--primary);
            background: var(--primary);
        }

        .question-legend .legend-unsaved {
            border-color: #d97870;
            background: #fde8e8;
        }

        .exam-finish-dialog {
            width: min(92vw, 560px);
            border: 0;
            border-radius: 8px;
            background: #fff;
            color: var(--text);
            padding: 0;
            box-shadow: 0 24px 70px rgba(15, 53, 92, .3);
        }

        .exam-finish-dialog::backdrop {
            background: rgba(15, 30, 46, .62);
        }

        .exam-security-dialog {
            width: min(92vw, 500px);
            border: 0;
            border-radius: 8px;
            background: #fff;
            color: var(--text);
            padding: 0;
            box-shadow: 0 24px 70px rgba(71, 38, 0, .28);
        }

        .exam-security-dialog::backdrop {
            background: rgba(15, 30, 46, .68);
        }

        .security-dialog-body {
            display: grid;
            gap: 17px;
            border-top: 5px solid var(--accent);
            padding: 24px;
        }

        .security-dialog-body h2,
        .security-dialog-body p {
            margin: 0;
        }

        .security-warning-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            border: 1px solid #eadb9b;
            background: #fffbea;
        }

        .security-warning-summary > div {
            padding: 13px 14px;
        }

        .security-warning-summary > div + div {
            border-left: 1px solid #eadb9b;
        }

        .security-warning-summary strong,
        .security-warning-summary span {
            display: block;
        }

        .security-warning-summary strong {
            color: #6b5200;
            font-size: 1.35rem;
        }

        .security-warning-summary span {
            color: #6f6441;
            font-size: .75rem;
            font-weight: 800;
        }

        .security-hold-screen[hidden] {
            display: none;
        }

        .security-hold-screen {
            position: fixed;
            z-index: 100;
            inset: 0;
            display: grid;
            overflow-y: auto;
            place-items: center;
            background: #edf3f8;
            padding: 24px;
        }

        .security-hold-panel {
            width: min(100%, 560px);
            border: 1px solid #bdcddd;
            border-top: 6px solid #b91c1c;
            border-radius: 8px;
            background: #fff;
            box-shadow: var(--shadow);
        }

        .security-hold-content {
            display: grid;
            gap: 18px;
            padding: 26px;
        }

        .security-hold-content h2,
        .security-hold-content p {
            margin: 0;
        }

        .security-hold-heading {
            display: grid;
            gap: 6px;
        }

        .security-hold-heading .eyebrow {
            color: var(--danger);
        }

        .security-hold-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            border: 1px solid var(--line);
        }

        .security-hold-info > div {
            padding: 14px;
        }

        .security-hold-info > div + div {
            border-left: 1px solid var(--line);
        }

        .security-hold-info strong,
        .security-hold-info span {
            display: block;
        }

        .security-hold-info strong {
            color: var(--primary-dark);
            font-size: 1.3rem;
        }

        .security-hold-info span {
            color: var(--muted);
            font-size: .75rem;
            font-weight: 800;
        }

        .security-hold-status {
            border-left: 4px solid var(--accent);
            background: var(--accent-soft);
            padding: 12px 14px;
            color: #5f4b00;
            font-size: .86rem;
            font-weight: 800;
        }

        body.security-is-held {
            overflow: hidden;
        }

        .finish-dialog-body {
            display: grid;
            gap: 18px;
            padding: 24px;
        }

        .finish-dialog-body h2,
        .finish-dialog-body p {
            margin: 0;
        }

        .finish-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            border: 1px solid var(--line);
        }

        .finish-summary-item {
            padding: 14px;
            border-right: 1px solid var(--line);
            text-align: center;
        }

        .finish-summary-item:last-child {
            border-right: 0;
        }

        .finish-summary-item strong,
        .finish-summary-item span {
            display: block;
        }

        .finish-summary-item strong {
            color: var(--primary-dark);
            font-size: 1.6rem;
        }

        .finish-summary-item span {
            color: var(--muted);
            font-size: .78rem;
            font-weight: 800;
        }

        .finish-warning {
            border-left: 4px solid var(--accent);
            background: #fff9dc;
            color: #604c00;
            padding: 12px 14px;
            font-size: .88rem;
            font-weight: 750;
        }

        .file-answer-box {
            display: grid;
            gap: 12px;
            margin-top: 14px;
            border: 1px solid #b9cde2;
            border-radius: 8px;
            background: var(--primary-soft);
            padding: 16px;
        }

        .file-answer-status {
            display: grid;
            gap: 3px;
            min-width: 0;
        }

        .file-answer-status strong,
        .file-answer-status span {
            overflow-wrap: anywhere;
        }

        .file-answer-status span {
            color: var(--muted);
            font-size: .82rem;
            font-weight: 750;
        }

        .file-answer-action {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .file-answer-action [aria-disabled="true"] {
            pointer-events: none;
            opacity: .6;
        }

        .file-answer-input {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
        }

        @media (max-width: 900px) {
            .exam-side {
                grid-template-columns: minmax(180px, .7fr) minmax(0, 1.3fr);
            }

            .exam-participant-panel {
                display: none;
            }

            .question-card {
                min-height: 0;
            }
        }

        @media (max-width: 560px) {
            .cbt-shell {
                padding-right: 12px;
                padding-left: 12px;
            }

            .brand {
                overflow: hidden;
            }

            .brand > span:last-child {
                min-width: 0;
            }

            .brand-title {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .exam-top-status .badge,
            .exam-side > .timer {
                display: none;
            }

            .exam-compact-timer {
                min-width: 88px;
                padding: 5px 7px;
            }

            .exam-side {
                grid-template-columns: 1fr;
            }

            .question-card {
                padding: 17px;
            }

            .question-actions {
                display: grid;
                width: 100%;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .question-actions .button {
                min-width: 0;
                width: 100%;
                padding-right: 9px;
                padding-left: 9px;
            }

            .finish-action {
                width: 100%;
                max-width: none;
                border-top: 1px solid var(--line);
                border-left: 0;
                padding-top: 10px;
                padding-left: 0;
            }

            .finish-action .button {
                width: 100%;
            }

            .finish-availability {
                text-align: left;
            }

            .save-bar {
                position: static;
                margin-right: -12px;
                margin-left: -12px;
                padding: 10px 12px 8px;
                border: 1px solid var(--line);
                border-bottom: 0;
                background: transparent;
                backdrop-filter: none;
            }

            .answer-save-state,
            .question-position {
                width: 100%;
            }

            .finish-summary {
                grid-template-columns: 1fr;
            }

            .finish-summary-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                border-right: 0;
                border-bottom: 1px solid var(--line);
                text-align: left;
            }

            .finish-summary-item:last-child {
                border-bottom: 0;
            }

            .security-warning-summary,
            .security-hold-info {
                grid-template-columns: 1fr;
            }

            .security-warning-summary > div + div,
            .security-hold-info > div + div {
                border-top: 1px solid var(--line);
                border-left: 0;
            }

            .security-hold-screen {
                align-items: start;
                padding: 14px;
            }

            .security-hold-content {
                padding: 20px;
            }
        }
    </style>
@endpush

@section('body')
    @php
        $siswa = $peserta->anggotaKelas?->siswa;
        $ujian = $peserta->ujianCbt;
        $kelas = $peserta->kelasUjianCbt?->kelas;

    @endphp

    <header class="cbt-topbar">
        <div class="topbar-inner">
            <div class="brand">
                <span class="brand-mark"><img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA"></span>
                <span>
                    <span class="brand-title">{{ $ujian->nama }}</span>
                    <span class="brand-subtitle">{{ $siswa?->nama_lengkap ?: '-' }} · {{ $kelas?->nama ?: '-' }}</span>
                </span>
            </div>
            <div class="exam-top-status">
                <span class="badge badge-primary">{{ $ujian->mataPelajaran?->nama ?: 'CBT' }}</span>
                <span class="exam-compact-timer" aria-label="Sisa waktu ujian">
                    <small>Sisa waktu</small>
                    <strong id="timerValueCompact">--:--:--</strong>
                </span>
            </div>
        </div>
    </header>

    <main class="cbt-shell">
        @if (session('berhasil'))
            <div class="alert">{{ session('berhasil') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form
            id="formUjian"
            action="{{ route('cbt.ujian.simpan') }}"
            method="POST"
            data-security-endpoint="{{ route('cbt.ujian.aktivitas-keamanan') }}"
            data-security-detection="{{ $peserta->ujianCbt->deteksi_pindah_tab ? '1' : '0' }}"
            data-security-held="{{ $peserta->status === 'terblokir' ? '1' : '0' }}"
            @if ($peserta->status === 'terblokir') inert @endif
        >
            @csrf
            <input id="aksiInput" type="hidden" name="aksi" value="simpan">

            <div class="exam-layout">
                <section class="exam-main">
                    @foreach ($soalUjian as $index => $relasiSoal)
                        @php
                            $soal = $relasiSoal->soalCbt;
                            $nomor = $index + 1;
                            $jawabanModel = $jawabanTersimpan->get($relasiSoal->id);
                            $jawabanSaatIni = $jawabanModel?->jawaban ?? [];
                            $berkasTersimpan = $jawabanModel?->lokasi_file ? $jawabanModel : null;
                            $raguSaatIni = (bool) ($jawabanModel?->ragu ?? false);
                            $jawabanAssoc = is_array($jawabanSaatIni) ? $jawabanSaatIni : [];
                            $terjawabSaatIni = (bool) $berkasTersimpan || collect((array) $jawabanSaatIni)->contains(fn ($nilai) => filled($nilai));
                            $pilihan = $pilihanJawaban->get($relasiSoal->id, collect());
                            $kunciMediaPilihan = function ($kode, $teks) use ($soal) {
                                if (in_array($soal?->jenis_soal, ['pilihan_ganda', 'pilihan_ganda_kompleks'], true)) {
                                    return 'pilihan_' . mb_strtoupper((string) $kode);
                                }

                                $normal = mb_strtolower(trim((string) $teks));
                                $pasangan = collect($soal?->opsi['pasangan'] ?? [])->first(
                                    fn ($item) => mb_strtolower(trim((string) ($item['kanan'] ?? ''))) === $normal,
                                );
                                if ($pasangan) {
                                    return $pasangan['media_kanan_key'] ?? null;
                                }

                                return collect($soal?->opsi['pengecoh_media'] ?? [])->first(
                                    fn ($item) => mb_strtolower(trim((string) ($item['teks'] ?? ''))) === $normal,
                                )['media_key'] ?? null;
                            };
                        @endphp

                        <article
                            id="soal-{{ $nomor }}"
                            class="panel panel-pad question-card"
                            data-question-id="{{ $relasiSoal->id }}"
                            data-question-index="{{ $index }}"
                            data-answered="{{ $terjawabSaatIni ? '1' : '0' }}"
                            data-file-saved="{{ $berkasTersimpan ? '1' : '0' }}"
                            data-doubt="{{ $raguSaatIni ? '1' : '0' }}"
                            data-dirty="0"
                            data-revision="0"
                            tabindex="-1"
                        >
                            <div class="question-head">
                                <div style="display: flex; gap: 12px; align-items: flex-start;">
                                    <span class="question-number">{{ $nomor }}</span>
                                    <div>
                                        <span class="badge badge-muted">{{ $soal?->labelJenis() ?: 'Soal' }}</span>
                                    </div>
                                </div>
                                <label class="check-row">
                                    <input type="checkbox" name="ragu[{{ $relasiSoal->id }}]" value="1" @checked($raguSaatIni)>
                                    Ragu-ragu
                                </label>
                            </div>

                            @if (filled($soal?->stimulus) || filled(data_get($soal?->media, 'konten.stimulus')))
                                <div class="stimulus">
                                    @if (filled($soal?->stimulus))
                                        <div>{{ $soal->stimulus }}</div>
                                    @endif
                                    <x-media-soal :media="data_get($soal?->media, 'konten.stimulus', [])" stimulus />
                                </div>
                            @endif

                            @if ($soal)
                                <x-media-soal :media="$soal->media" />
                            @endif

                            <h2 class="question-title">{{ $soal?->pertanyaan ?: 'Soal tidak ditemukan.' }}</h2>

                            @if (in_array($soal?->jenis_soal, ['pilihan_ganda', 'pilihan_ganda_kompleks'], true))
                                <div class="option-list">
                                    @foreach ($pilihan as $kodeJawaban => $teks)
                                        @php $labelPilihan = chr(65 + $loop->index); @endphp
                                        <label class="option-card">
                                            @if ($soal->jenis_soal === 'pilihan_ganda')
                                                <input type="radio" name="jawaban[{{ $relasiSoal->id }}]" value="{{ $kodeJawaban }}" @checked(in_array($kodeJawaban, (array) $jawabanSaatIni, true))>
                                            @else
                                                <input type="checkbox" name="jawaban[{{ $relasiSoal->id }}][]" value="{{ $kodeJawaban }}" @checked(in_array($kodeJawaban, (array) $jawabanSaatIni, true))>
                                            @endif
                                            <div class="option-card-content">
                                                <span class="option-code">{{ $labelPilihan }}</span>
                                                <span class="option-text">{{ $teks }}</span>
                                                <x-media-soal :media="data_get($soal->media, 'konten.' . $kunciMediaPilihan($kodeJawaban, $teks), [])" compact />
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            @elseif ($soal?->jenis_soal === 'benar_salah')
                                <div class="option-list">
                                    @foreach (($soal->opsi['pernyataan'] ?? []) as $item)
                                        @php
                                            $nomorPernyataan = (string) ($item['nomor'] ?? $loop->iteration);
                                            $nilaiBs = $jawabanAssoc[$nomorPernyataan] ?? $jawabanAssoc[(int) $nomorPernyataan] ?? null;
                                        @endphp
                                        <div class="statement-row">
                                            <div>
                                                 <span class="option-code">{{ $nomorPernyataan }}</span>
                                                 <span class="option-text">{{ $item['teks'] ?? '-' }}</span>
                                                 <x-media-soal :media="data_get($soal->media, 'konten.' . ($item['media_key'] ?? ''), [])" compact />
                                            </div>
                                            <div class="statement-options">
                                                <label class="pill-option">
                                                    <input type="radio" name="jawaban[{{ $relasiSoal->id }}][{{ $nomorPernyataan }}]" value="benar" @checked($nilaiBs === 'benar')>
                                                    Benar
                                                </label>
                                                <label class="pill-option">
                                                    <input type="radio" name="jawaban[{{ $relasiSoal->id }}][{{ $nomorPernyataan }}]" value="salah" @checked($nilaiBs === 'salah')>
                                                    Salah
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif ($soal?->jenis_soal === 'menjodohkan')
                                <div class="matching-answer-bank">
                                    <strong>Pilihan pasangan</strong>
                                    <div>
                                        @foreach ($pilihan as $kodeJawaban => $teks)
                                            <div class="matching-answer-option">
                                                <b>{{ $kodeJawaban }}</b>
                                                <div>
                                                    <span>{{ $teks }}</span>
                                                    <x-media-soal :media="data_get($soal->media, 'konten.' . $kunciMediaPilihan($kodeJawaban, $teks), [])" compact />
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="option-list">
                                    @foreach (($soal->opsi['pasangan'] ?? []) as $item)
                                        @php
                                            $nomorPasangan = (string) ($item['nomor'] ?? $loop->iteration);
                                            $nilaiPasangan = $jawabanAssoc[$nomorPasangan] ?? $jawabanAssoc[(int) $nomorPasangan] ?? '';
                                        @endphp
                                        <div class="matching-row">
                                            <div>
                                                 <span class="option-code">{{ $nomorPasangan }}</span>
                                                 <span class="option-text">{{ $item['kiri'] ?? '-' }}</span>
                                                 <x-media-soal :media="data_get($soal->media, 'konten.' . ($item['media_kiri_key'] ?? ''), [])" compact />
                                            </div>
                                            <select name="jawaban[{{ $relasiSoal->id }}][{{ $nomorPasangan }}]" class="select matching-select" aria-label="Pasangan untuk {{ $item['kiri'] ?? 'pernyataan '.$nomorPasangan }}">
                                                <option value="">Pilih pasangan</option>
                                                @foreach ($pilihan as $kodeJawaban => $teks)
                                                    <option value="{{ $teks }}" @selected(mb_strtolower(trim((string) $nilaiPasangan)) === mb_strtolower(trim((string) $teks)))>@if (str_contains($teks, '\\(') || str_contains($teks, '\\['))Pilihan {{ $kodeJawaban }}@else{{ $kodeJawaban }}. {{ $teks }}@endif</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif (in_array($soal?->jenis_soal, ['isian_singkat', 'numerik'], true))
                                <div class="field" style="margin-top: 14px;">
                                    <label for="jawaban-{{ $relasiSoal->id }}">Jawaban</label>
                                    <input id="jawaban-{{ $relasiSoal->id }}" type="text" @if ($soal->jenis_soal === 'numerik') inputmode="decimal" @endif name="jawaban[{{ $relasiSoal->id }}]" value="{{ collect((array) $jawabanSaatIni)->first() }}" class="input">
                                </div>
                            @elseif ($soal?->jenis_soal === 'upload_file')
                                <div class="file-answer-box" data-file-answer>
                                    <div class="file-answer-status">
                                        <strong data-file-name>{{ $berkasTersimpan?->nama_file_asli ?: 'Belum ada berkas jawaban' }}</strong>
                                        <span data-file-info>
                                            @if ($berkasTersimpan)
                                                {{ number_format(((int) $berkasTersimpan->ukuran_file) / 1024, 1, ',', '.') }} KB · Berkas sudah tersimpan
                                            @else
                                                PDF, gambar, Word, Excel, atau PowerPoint · Maksimal 10 MB
                                            @endif
                                        </span>
                                    </div>
                                    <div class="file-answer-action">
                                        <input id="berkas-jawaban-{{ $relasiSoal->id }}" type="file" class="file-answer-input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx" data-answer-file-input>
                                        <label for="berkas-jawaban-{{ $relasiSoal->id }}" class="button button-primary" data-file-button>{{ $berkasTersimpan ? 'Ganti berkas' : 'Pilih dan unggah berkas' }}</label>
                                        <span class="help-text" data-file-message>Unggahan tersimpan otomatis setelah berkas dipilih.</span>
                                    </div>
                                </div>
                            @else
                                <div class="field" style="margin-top: 14px;">
                                    <label for="jawaban-{{ $relasiSoal->id }}">Jawaban</label>
                                    <textarea id="jawaban-{{ $relasiSoal->id }}" name="jawaban[{{ $relasiSoal->id }}]" class="textarea" placeholder="Tulis jawaban di sini.">{{ collect((array) $jawabanSaatIni)->first() }}</textarea>
                                </div>
                            @endif
                        </article>
                    @endforeach

                    <div class="save-bar js-only">
                        <div style="display: grid; gap: 2px; margin-right: auto; min-width: 0;">
                            <span id="questionPosition" class="question-position">Soal 1 dari {{ $soalUjian->count() }}</span>
                            <button id="retrySave" type="button" class="button button-muted" hidden>Coba simpan lagi</button>
                            <span id="saveStatus" class="answer-save-state" role="status" aria-live="polite">Jawaban disimpan otomatis</span>
                        </div>
                        <div class="question-actions">
                            <button id="previousQuestion" type="button" class="button button-muted">Sebelumnya</button>
                            <button id="nextQuestion" type="button" class="button button-muted">Berikutnya</button>
                        </div>
                        <div class="finish-action">
                            <span id="finishAvailability" class="finish-availability" role="status" aria-live="polite">
                                {{ $kelayakanSelesai['boleh_selesai'] ? 'Ujian sudah dapat dikumpulkan.' : 'Aktif setelah semua soal lengkap atau pada 15 menit terakhir.' }}
                            </span>
                            <button id="openFinishDialog" type="button" class="button button-primary button-finish" @disabled(! $kelayakanSelesai['boleh_selesai'])>Kumpulkan Ujian</button>
                        </div>
                    </div>

                    <noscript>
                        <div class="alert alert-danger">JavaScript tidak aktif. Semua soal tetap dapat dikerjakan, tetapi penyimpanan otomatis dan navigasi satu soal tidak tersedia.</div>
                        <div class="save-bar">
                            <button type="submit" class="button button-muted" onclick="document.getElementById('aksiInput').value='simpan'">Simpan Jawaban</button>
                            <button type="submit" class="button button-primary" onclick="document.getElementById('aksiInput').value='selesai'" @disabled(! $kelayakanSelesai['boleh_selesai'])>Kumpulkan Ujian</button>
                        </div>
                        @unless ($kelayakanSelesai['boleh_selesai'])
                            <p class="muted">Lengkapi seluruh soal atau muat ulang halaman saat waktu tersisa 15 menit untuk mengumpulkan ujian.</p>
                        @endunless
                    </noscript>
                </section>

                <aside class="exam-side">
                    <section class="timer">
                        <div id="timerValue" class="timer-value">--:--:--</div>
                        <p class="timer-label">Sisa waktu</p>
                    </section>

                    <section class="panel panel-pad exam-navigation-panel">
                        <h2 class="panel-title">Nomor soal</h2>
                        <div class="nav-grid" style="margin-top: 12px;">
                            @foreach ($soalUjian as $index => $relasiSoal)
                                @php
                                    $jawabanModel = $jawabanTersimpan->get($relasiSoal->id);
                                    $terjawab = collect((array) $jawabanModel?->jawaban)->contains(fn ($nilai) => filled($nilai));
                                    $ragu = (bool) $jawabanModel?->ragu;
                                @endphp
                                <button
                                    type="button"
                                    class="nav-number {{ $terjawab ? 'is-answered' : '' }} {{ $ragu ? 'is-doubt' : '' }}"
                                    data-question-index="{{ $index }}"
                                    aria-label="Buka soal nomor {{ $index + 1 }}"
                                >{{ $index + 1 }}</button>
                            @endforeach
                        </div>
                        <div class="question-legend" aria-label="Keterangan nomor soal">
                            <span><i></i>Belum</span>
                            <span><i class="legend-answered"></i>Terjawab</span>
                            <span><i class="legend-partial"></i>Belum lengkap</span>
                            <span><i class="legend-doubt"></i>Ragu</span>
                            <span><i class="legend-current"></i>Dibuka</span>
                            <span><i class="legend-unsaved"></i>Belum tersimpan</span>
                        </div>
                    </section>

                    <section class="panel panel-pad exam-participant-panel">
                        <p class="info-label">Peserta</p>
                        <p class="info-value">{{ $siswa?->nama_lengkap ?: '-' }}</p>
                        <p class="muted" style="margin: 6px 0 0; font-size: .88rem; font-weight: 800;">{{ $kelas?->nama ?: '-' }}</p>
                    </section>
                </aside>
            </div>

            <dialog id="finishDialog" class="exam-finish-dialog">
                <div class="finish-dialog-body">
                    <div>
                        <p class="eyebrow">Periksa jawaban</p>
                        <h2>Kumpulkan ujian sekarang?</h2>
                        <p class="muted" style="margin-top: 7px;">Setelah dikumpulkan, jawaban tidak dapat diubah dan ujian tidak dapat dibuka kembali.</p>
                    </div>
                    <div class="finish-summary">
                        <div class="finish-summary-item">
                            <strong id="finishAnswered">0</strong>
                            <span>Terjawab</span>
                        </div>
                        <div class="finish-summary-item">
                            <strong id="finishUnanswered">0</strong>
                            <span>Belum lengkap</span>
                        </div>
                        <div class="finish-summary-item">
                            <strong id="finishDoubt">0</strong>
                            <span>Ragu-ragu</span>
                        </div>
                    </div>
                    <div id="finishWarning" class="finish-warning" hidden></div>
                    <label id="finishAcknowledgementRow" class="finish-acknowledgement" hidden>
                        <input id="finishAcknowledgement" type="checkbox">
                        <span>Saya memahami masih ada jawaban yang belum lengkap atau ditandai ragu-ragu, dan tetap ingin mengumpulkan ujian.</span>
                    </label>
                    <div class="actions" style="justify-content: flex-end;">
                        <button id="cancelFinish" type="button" class="button button-muted">Periksa Lagi</button>
                        <button id="confirmFinish" type="button" class="button button-primary">Ya, Kumpulkan</button>
                    </div>
                </div>
            </dialog>
        </form>

        <dialog id="securityWarningDialog" class="exam-security-dialog" aria-labelledby="securityWarningTitle">
            <div class="security-dialog-body">
                <div>
                    <p class="eyebrow">Mode Aman</p>
                    <h2 id="securityWarningTitle">Peringatan aktivitas ujian</h2>
                    <p id="securityWarningMessage" class="muted" style="margin-top: 7px;">Anda terdeteksi meninggalkan halaman ujian.</p>
                </div>
                <div class="security-warning-summary" aria-label="Ringkasan peringatan Mode Aman">
                    <div>
                        <strong id="securityWarningCount">0 / 0</strong>
                        <span>Peringatan tercatat</span>
                    </div>
                    <div>
                        <strong id="securityWarningRemaining">0</strong>
                        <span>Kesempatan tersisa</span>
                    </div>
                </div>
                <p class="muted">Tetap berada di halaman NUSA selama ujian berlangsung. Waktu ujian terus berjalan ketika halaman ditinggalkan.</p>
                <div class="actions" style="justify-content: flex-end;">
                    <button id="securityWarningConfirm" type="button" class="button button-primary">Saya mengerti</button>
                </div>
            </div>
        </dialog>

        <section
            id="securityHoldScreen"
            class="security-hold-screen"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="securityHoldTitle"
            @if ($peserta->status !== 'terblokir') hidden @endif
        >
            <div class="security-hold-panel">
                <div class="security-hold-content">
                    <div class="security-hold-heading">
                        <p class="eyebrow">Mode Aman</p>
                        <h2 id="securityHoldTitle">Ujian sementara ditahan</h2>
                        <p class="muted">Batas meninggalkan halaman ujian telah tercapai. Minta pengawas membuka kembali akses ujian Anda.</p>
                    </div>
                    <div class="security-hold-info">
                        <div>
                            <strong id="securityHoldCount">{{ (int) $peserta->jumlah_pindah_aplikasi }} / {{ max(1, (int) $ujian->batas_pindah_aplikasi) }}</strong>
                            <span>Aktivitas tercatat</span>
                        </div>
                        <div>
                            <strong id="securityHoldTimer">--:--:--</strong>
                            <span>Sisa waktu ujian</span>
                        </div>
                    </div>
                    <p id="securityHoldStatus" class="security-hold-status" role="status" aria-live="polite">Menunggu pengawas membuka Mode Aman. Status diperiksa otomatis.</p>
                    <button id="checkSecurityStatus" type="button" class="button button-primary">Periksa status</button>
                </div>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        (() => {
            const timerValue = document.getElementById('timerValue');
            const timerValueCompact = document.getElementById('timerValueCompact');
            const aksiInput = document.getElementById('aksiInput');
            const formUjian = document.getElementById('formUjian');
            const questionCards = Array.from(document.querySelectorAll('.question-card'));
            const navigationButtons = Array.from(document.querySelectorAll('.nav-number'));
            const previousButton = document.getElementById('previousQuestion');
            const nextButton = document.getElementById('nextQuestion');
            const questionPosition = document.getElementById('questionPosition');
            const saveStatus = document.getElementById('saveStatus');
            const openFinishDialogButton = document.getElementById('openFinishDialog');
            const finishDialog = document.getElementById('finishDialog');
            const cancelFinishButton = document.getElementById('cancelFinish');
            const confirmFinishButton = document.getElementById('confirmFinish');
            const finishAnswered = document.getElementById('finishAnswered');
            const finishUnanswered = document.getElementById('finishUnanswered');
            const finishDoubt = document.getElementById('finishDoubt');
            const finishWarning = document.getElementById('finishWarning');
            const finishAvailability = document.getElementById('finishAvailability');
            const finishAcknowledgementRow = document.getElementById('finishAcknowledgementRow');
            const finishAcknowledgement = document.getElementById('finishAcknowledgement');
            const securityWarningDialog = document.getElementById('securityWarningDialog');
            const securityWarningMessage = document.getElementById('securityWarningMessage');
            const securityWarningCount = document.getElementById('securityWarningCount');
            const securityWarningRemaining = document.getElementById('securityWarningRemaining');
            const securityWarningConfirm = document.getElementById('securityWarningConfirm');
            const securityHoldScreen = document.getElementById('securityHoldScreen');
            const securityHoldCount = document.getElementById('securityHoldCount');
            const securityHoldTimer = document.getElementById('securityHoldTimer');
            const securityHoldStatus = document.getElementById('securityHoldStatus');
            const checkSecurityStatus = document.getElementById('checkSecurityStatus');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const autosaveUrl = @json(route('cbt.ujian.jawaban'));
            const fileUploadUrl = @json(route('cbt.ujian.jawaban-berkas'));
            const finishedUrl = @json(route('cbt.ujian.selesai'));
            const targetTime = Date.now() + ({{ (int) $sisaDetik }} * 1000);
            const finishThresholdSeconds = 15 * 60;
            const securityEndpoint = formUjian.dataset.securityEndpoint;
            const securityDetectionEnabled = formUjian.dataset.securityDetection === '1';
            const securityHeartbeatIntervalMs = 15000;
            const saveTimers = new Map();
            const saveQueues = new Map();
            const activeUploads = new Set();
            let currentQuestion = 0;
            let automaticSubmitStarted = false;
            let finalSubmitStarted = false;
            let remainingSeconds = {{ (int) $sisaDetik }};
            let securityAway = false;
            let securityAwayRequest = null;
            let securityStopped = false;
            let securityRequestQueue = Promise.resolve();
            let isExamHeld = formUjian.dataset.securityHeld === '1';

            function formatTwoDigits(value) {
                return String(value).padStart(2, '0');
            }

            function isIntentionalExamExit() {
                return finalSubmitStarted || automaticSubmitStarted;
            }

            function securityMetadata(trigger) {
                return {
                    visibility: document.visibilityState || (document.hidden ? 'hidden' : 'visible'),
                    pemicu: trigger,
                    fullscreen: Boolean(document.fullscreenElement),
                    online: navigator.onLine,
                    waktu_klien: new Date().toISOString(),
                };
            }

            async function sendSecurityEvent(eventName, trigger, keepalive = false) {
                if ((!securityDetectionEnabled && !isExamHeld) || securityStopped || isIntentionalExamExit()) return null;

                try {
                    const response = await fetch(securityEndpoint, {
                        method: 'POST',
                        credentials: 'same-origin',
                        keepalive,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            peristiwa: eventName,
                            metadata: securityMetadata(trigger),
                        }),
                    });
                    const result = await response.json().catch(() => ({}));

                    if ([401, 403, 409, 419].includes(response.status)) {
                        securityStopped = true;
                    }
                    if (!response.ok) return null;

                    document.dispatchEvent(new CustomEvent('cbt:security-response', {
                        detail: result.data || null,
                    }));
                    handleSecurityResponse(result.data || null);

                    return result.data || null;
                } catch (error) {
                    return null;
                }
            }

            function queueSecurityEvent(eventName, trigger, keepalive = false) {
                securityRequestQueue = securityRequestQueue
                    .catch(() => null)
                    .then(() => sendSecurityEvent(eventName, trigger, keepalive));

                return securityRequestQueue;
            }

            function reportSecurityAway(trigger, keepalive = false) {
                if (!securityDetectionEnabled || securityAway || isIntentionalExamExit()) return;

                securityAway = true;
                securityAwayRequest = keepalive
                    ? sendSecurityEvent('keluar', trigger, true)
                    : queueSecurityEvent('keluar', trigger);
            }

            function reportSecurityReturn(trigger) {
                if (!securityDetectionEnabled || !securityAway || isIntentionalExamExit()) return;

                securityAway = false;
                const awayRequest = securityAwayRequest;
                securityAwayRequest = null;
                securityRequestQueue = securityRequestQueue
                    .catch(() => null)
                    .then(async () => {
                        if (awayRequest) await awayRequest;
                        return sendSecurityEvent('kembali', trigger);
                    });
            }

            function sendSecurityHeartbeat(trigger = 'timer') {
                if ((!securityDetectionEnabled && !isExamHeld) || securityAway || document.hidden || isIntentionalExamExit()) return;

                return queueSecurityEvent('heartbeat', trigger);
            }

            function setExamHeld(data) {
                const security = data?.keamanan || {};
                const wasHeld = isExamHeld;
                isExamHeld = true;
                formUjian.toggleAttribute('inert', true);
                document.body.classList.add('security-is-held');
                securityHoldScreen.hidden = false;
                securityHoldCount.textContent = `${Number(security.jumlah_kejadian || 0)} / ${Number(security.batas_kejadian || 0)}`;
                securityHoldStatus.textContent = data?.pesan || 'Menunggu pengawas membuka Mode Aman. Status diperiksa otomatis.';
                if (finishDialog.open) finishDialog.close();
                if (securityWarningDialog.open) securityWarningDialog.close();
                if (!wasHeld) checkSecurityStatus.focus({ preventScroll: true });
            }

            function releaseExamHold() {
                if (!isExamHeld) return;

                isExamHeld = false;
                formUjian.toggleAttribute('inert', false);
                document.body.classList.remove('security-is-held');
                securityHoldScreen.hidden = true;
                setSaveStatus('saved', 'Pengawas membuka ujian. Silakan lanjutkan.');
                questionCards[currentQuestion]?.focus({ preventScroll: true });
            }

            function showSecurityWarning(data) {
                const security = data?.keamanan || {};
                securityWarningMessage.textContent = data?.pesan || 'Aktivitas meninggalkan halaman ujian telah dicatat untuk pengawas.';
                securityWarningCount.textContent = `${Number(security.jumlah_kejadian || 0)} / ${Number(security.batas_kejadian || 0)}`;
                securityWarningRemaining.textContent = String(Number(security.sisa_kejadian || 0));
                if (finishDialog.open) finishDialog.close();

                if (typeof securityWarningDialog.showModal === 'function') {
                    if (!securityWarningDialog.open) securityWarningDialog.showModal();
                } else {
                    alert(securityWarningMessage.textContent);
                }
            }

            function handleSecurityResponse(data) {
                if (!data) return;

                const held = data.mode === 'ditahan' || data.keamanan?.ditahan === true;
                if (held) {
                    setExamHeld(data);
                    return;
                }

                releaseExamHold();
                if (data.kejadian_dihitung) showSecurityWarning(data);
            }

            function updateTimer() {
                const remaining = Math.max(0, Math.floor((targetTime - Date.now()) / 1000));
                remainingSeconds = remaining;
                const hours = Math.floor(remaining / 3600);
                const minutes = Math.floor((remaining % 3600) / 60);
                const seconds = remaining % 60;
                const label = `${formatTwoDigits(hours)}:${formatTwoDigits(minutes)}:${formatTwoDigits(seconds)}`;

                if (timerValue) timerValue.textContent = label;
                if (timerValueCompact) timerValueCompact.textContent = label;
                if (securityHoldTimer) securityHoldTimer.textContent = label;

                if (remaining <= 300) {
                    if (timerValue) timerValue.style.color = '#b91c1c';
                    if (timerValueCompact) timerValueCompact.style.color = '#b91c1c';
                }

                updateFinishAvailability();

                if (remaining <= 0 && !automaticSubmitStarted) {
                    automaticSubmitStarted = true;
                    finalSubmitStarted = true;
                    aksiInput.value = 'selesai';
                    formUjian.submit();
                }
            }

            function setSaveStatus(state, text) {
                saveStatus.className = `answer-save-state ${state ? `is-${state}` : ''}`;
                saveStatus.textContent = text;
                document.getElementById('retrySave').hidden = !questionCards.some(card => card.dataset.dirty === '1' && Number(card.dataset.saveFailures || 0) > 0);
            }

            function answerFromCard(card) {
                const questionId = card.dataset.questionId;
                const values = [];
                const keyedValues = {};
                let hasKeyedValues = false;
                const selector = `[name^="jawaban[${questionId}]"]`;

                card.querySelectorAll(selector).forEach((input) => {
                    if ((input.type === 'radio' || input.type === 'checkbox') && !input.checked) return;

                    const value = typeof input.value === 'string' ? input.value.trim() : input.value;
                    if (value === '') return;

                    const keyedMatch = input.name.match(/^jawaban\[\d+\]\[([^\]]+)\]$/);
                    if (keyedMatch) {
                        hasKeyedValues = true;
                        keyedValues[keyedMatch[1]] = value;
                    } else {
                        values.push(value);
                    }
                });

                return hasKeyedValues ? keyedValues : values;
            }

            function hasAnswer(answer) {
                return Object.values(answer || {}).some((value) => String(value).trim() !== '');
            }

            function answerCompletion(card) {
                const statements = [...card.querySelectorAll('.statement-row')];
                const matches = [...card.querySelectorAll('.matching-row select')];
                const parts = statements.length
                    ? statements.map(row => Boolean(row.querySelector('input[type="radio"]:checked')))
                    : matches.map(select => select.value !== '');
                if (parts.length) return { complete: parts.every(Boolean), partial: parts.some(Boolean) && !parts.every(Boolean) };
                return { complete: card.dataset.fileSaved === '1' || hasAnswer(answerFromCard(card)), partial: false };
            }

            function doubtFromCard(card) {
                const questionId = card.dataset.questionId;
                return Boolean(card.querySelector(`[name="ragu[${questionId}]"]`)?.checked);
            }

            function refreshCardState(index) {
                const card = questionCards[index];
                if (!card) return;

                const completion = answerCompletion(card);
                card.dataset.answered = completion.complete ? '1' : '0';
                card.dataset.partial = completion.partial ? '1' : '0';
                card.dataset.doubt = doubtFromCard(card) ? '1' : '0';
                refreshNavigation();
                updateFinishAvailability();
            }

            function finishState() {
                const answered = questionCards.filter((card) => card.dataset.answered === '1').length;

                return {
                    answered,
                    unanswered: questionCards.length - answered,
                    doubt: questionCards.filter((card) => card.dataset.doubt === '1').length,
                    partial: questionCards.filter((card) => card.dataset.partial === '1').length,
                };
            }

            function updateFinishAvailability() {
                const state = finishState();
                const allComplete = questionCards.length > 0 && state.unanswered === 0;
                const finalWindow = remainingSeconds <= finishThresholdSeconds;
                const uploading = activeUploads.size > 0;
                const allowed = (allComplete || finalWindow) && !uploading;
                openFinishDialogButton.disabled = !allowed;

                if (uploading) {
                    finishAvailability.textContent = 'Tunggu hingga unggahan jawaban selesai.';
                } else if (allComplete) {
                    finishAvailability.textContent = 'Semua soal sudah lengkap. Ujian dapat dikumpulkan.';
                } else if (finalWindow) {
                    finishAvailability.textContent = '15 menit terakhir. Ujian dapat dikumpulkan meski masih ada soal yang belum lengkap.';
                } else {
                    finishAvailability.textContent = 'Aktif setelah semua soal lengkap atau pada 15 menit terakhir.';
                }

                return allowed;
            }

            function refreshNavigation() {
                navigationButtons.forEach((button, index) => {
                    const card = questionCards[index];
                    const isCurrent = index === currentQuestion;

                    button.classList.toggle('is-current', isCurrent);
                    button.classList.toggle('is-answered', card?.dataset.answered === '1' && card?.dataset.doubt !== '1');
                    button.classList.toggle('is-partial', card?.dataset.partial === '1' && card?.dataset.doubt !== '1');
                    const completionLabel = card?.dataset.partial === '1' ? 'belum lengkap' : (card?.dataset.answered === '1' ? 'terjawab' : 'belum dijawab');
                    button.setAttribute('aria-label', `Buka soal nomor ${index + 1}, ${completionLabel}`);
                    button.classList.toggle('is-doubt', card?.dataset.doubt === '1');
                    button.classList.toggle('is-unsaved', card?.dataset.dirty === '1');

                    if (isCurrent) {
                        button.setAttribute('aria-current', 'step');
                    } else {
                        button.removeAttribute('aria-current');
                    }
                });
            }

            function showQuestion(index, shouldFocus = true) {
                if (!questionCards.length) return;

                currentQuestion = Math.max(0, Math.min(index, questionCards.length - 1));
                questionCards.forEach((card, cardIndex) => {
                    card.hidden = cardIndex !== currentQuestion;
                });

                previousButton.disabled = currentQuestion === 0;
                nextButton.disabled = currentQuestion === questionCards.length - 1;
                questionPosition.textContent = `Soal ${currentQuestion + 1} dari ${questionCards.length}`;
                refreshNavigation();

                if (shouldFocus) {
                    questionCards[currentQuestion].focus({ preventScroll: true });
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }

            function scheduleSave(index, delay = 700) {
                clearTimeout(saveTimers.get(index));
                saveTimers.set(index, setTimeout(() => queueSave(index), delay));
            }

            async function performSave(index) {
                const card = questionCards[index];
                if (!card || card.dataset.dirty !== '1' || finalSubmitStarted || isExamHeld) return true;

                const revision = Number(card.dataset.revision || 0);
                setSaveStatus('saving', `Menyimpan soal ${index + 1}...`);
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 10000);

                try {
                    const response = await fetch(autosaveUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        signal: controller.signal,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            soal_ujian_cbt_id: Number(card.dataset.questionId),
                            jawaban: answerFromCard(card),
                            ragu: doubtFromCard(card),
                        }),
                    });
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        if (result.ujian_selesai) {
                            finalSubmitStarted = true;
                            window.location.assign(finishedUrl);
                            return false;
                        }

                        throw new Error(result.message || 'Jawaban belum dapat disimpan.');
                    }

                    if (Number(card.dataset.revision || 0) === revision) {
                        card.dataset.dirty = '0';
                        card.dataset.saveFailures = '0';
                        const pending = questionCards.filter(item => item.dataset.dirty === '1').length;
                        setSaveStatus(pending ? 'unsaved' : 'saved', pending ? `${pending} soal belum tersimpan` : `Tersimpan ${result.tersimpan_pada}`);
                    } else {
                        scheduleSave(index, 150);
                    }

                    refreshCardState(index);
                    return true;
                } catch (error) {
                    card.dataset.saveFailures = String(Number(card.dataset.saveFailures || 0) + 1);
                    setSaveStatus('failed', error.name === 'AbortError' ? 'Koneksi lambat. Jawaban belum tersimpan.' : 'Gagal menyimpan. Periksa koneksi.');
                    if (!finalSubmitStarted && Number(card.dataset.saveFailures) <= 3) scheduleSave(index, 5000);
                    refreshNavigation();
                    return false;
                } finally {
                    clearTimeout(timeout);
                }
            }

            function queueSave(index) {
                clearTimeout(saveTimers.get(index));
                if (saveQueues.has(index)) return saveQueues.get(index);
                const nextQueue = performSave(index).finally(() => saveQueues.delete(index));
                saveQueues.set(index, nextQueue);
                return nextQueue;
            }

            function markDirty(index, delay) {
                if (isExamHeld) return;

                const card = questionCards[index];
                card.dataset.dirty = '1';
                card.dataset.saveFailures = '0';
                card.dataset.revision = String(Number(card.dataset.revision || 0) + 1);
                refreshCardState(index);
                setSaveStatus('unsaved', 'Belum tersimpan');
                scheduleSave(index, delay);
            }

            async function uploadAnswerFile(card, index, input) {
                if (isExamHeld || activeUploads.has(card.dataset.questionId)) {
                    return;
                }

                const file = input.files?.[0];
                if (!file) return;

                const message = card.querySelector('[data-file-message]');
                const fileName = card.querySelector('[data-file-name]');
                const fileInfo = card.querySelector('[data-file-info]');
                const fileButton = card.querySelector('[data-file-button]');
                const allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
                const extension = file.name.split('.').pop()?.toLowerCase() || '';

                if (!allowed.includes(extension)) {
                    message.textContent = 'Format berkas belum didukung.';
                    input.value = '';
                    return;
                }
                if (file.size > 10 * 1024 * 1024) {
                    message.textContent = 'Ukuran berkas melebihi batas 10 MB.';
                    input.value = '';
                    return;
                }

                activeUploads.add(card.dataset.questionId);
                openFinishDialogButton.disabled = true;
                fileButton.setAttribute('aria-disabled', 'true');
                message.textContent = 'Mengunggah berkas...';
                setSaveStatus('saving', `Mengunggah soal ${index + 1}...`);

                const formData = new FormData();
                formData.append('soal_ujian_cbt_id', card.dataset.questionId);
                formData.append('berkas', file, file.name);
                formData.append('ragu', doubtFromCard(card) ? '1' : '0');

                try {
                    const response = await fetch(fileUploadUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: formData,
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const validationMessage = Object.values(result.errors || {}).flat()[0];
                        throw new Error(validationMessage || result.message || 'Berkas belum dapat diunggah.');
                    }

                    card.dataset.answered = '1';
                    card.dataset.fileSaved = '1';
                    card.dataset.dirty = '0';
                    fileName.textContent = result.berkas?.nama || file.name;
                    fileInfo.textContent = `${result.berkas?.ukuran_label || ''} · Berkas sudah tersimpan`;
                    fileButton.textContent = 'Ganti berkas';
                    message.textContent = 'Berkas berhasil diunggah dan tersimpan.';
                    setSaveStatus('saved', `Tersimpan ${result.tersimpan_pada}`);
                    refreshNavigation();
                } catch (error) {
                    message.textContent = error.message || 'Unggahan gagal. Periksa koneksi lalu coba lagi.';
                    setSaveStatus('failed', message.textContent);
                } finally {
                    activeUploads.delete(card.dataset.questionId);
                    updateFinishAvailability();
                    fileButton.removeAttribute('aria-disabled');
                    input.value = '';
                }
            }

            function refreshFinishSummary() {
                questionCards.forEach((card, index) => refreshCardState(index));
                const state = finishState();
                const { answered, unanswered, doubt, partial } = state;

                finishAnswered.textContent = answered;
                finishUnanswered.textContent = unanswered;
                finishDoubt.textContent = doubt;
                finishWarning.hidden = unanswered === 0 && doubt === 0;
                finishWarning.textContent = unanswered > 0
                    ? `Masih ada ${unanswered} soal yang belum lengkap${partial > 0 ? ` (${partial} baru terisi sebagian)` : ''}${doubt > 0 ? ` dan ${doubt} soal ditandai ragu-ragu` : ''}.`
                    : `${doubt} soal masih ditandai ragu-ragu.`;
                const needsAcknowledgement = unanswered > 0 || doubt > 0;
                finishAcknowledgementRow.hidden = !needsAcknowledgement;
                finishAcknowledgement.checked = false;
                confirmFinishButton.disabled = needsAcknowledgement;
            }

            questionCards.forEach((card, index) => {
                card.querySelectorAll('input:not([data-answer-file-input]), textarea, select').forEach((input) => {
                    const delay = ['text', 'number'].includes(input.type) || input.tagName === 'TEXTAREA' ? 850 : 180;
                    const eventName = delay === 850 ? 'input' : 'change';
                    input.addEventListener(eventName, () => markDirty(index, delay));
                });
                card.querySelector('[data-answer-file-input]')?.addEventListener('change', (event) => {
                    uploadAnswerFile(card, index, event.currentTarget);
                });
            });

            navigationButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    queueSave(currentQuestion);
                    showQuestion(Number(button.dataset.questionIndex));
                });
            });

            previousButton.addEventListener('click', () => {
                queueSave(currentQuestion);
                showQuestion(currentQuestion - 1);
            });

            nextButton.addEventListener('click', () => {
                queueSave(currentQuestion);
                showQuestion(currentQuestion + 1);
            });

            openFinishDialogButton.addEventListener('click', () => {
                if (isExamHeld || !updateFinishAvailability()) return;
                refreshFinishSummary();

                if (typeof finishDialog.showModal === 'function') {
                    finishDialog.showModal();
                } else if (confirm('Selesaikan ujian sekarang? Setelah selesai, jawaban tidak dapat diubah.')) {
                    finalSubmitStarted = true;
                    aksiInput.value = 'selesai';
                    formUjian.submit();
                }
            });

            cancelFinishButton.addEventListener('click', () => finishDialog.close());
            finishAcknowledgement.addEventListener('change', () => {
                confirmFinishButton.disabled = !finishAcknowledgement.checked;
            });
            confirmFinishButton.addEventListener('click', () => {
                if (isExamHeld || confirmFinishButton.disabled || !updateFinishAvailability()) return;
                finalSubmitStarted = true;
                aksiInput.value = 'selesai';
                formUjian.requestSubmit();
            });

            formUjian.addEventListener('submit', (event) => {
                if (isExamHeld && remainingSeconds > 0) {
                    event.preventDefault();
                    return;
                }

                if (aksiInput.value !== 'selesai') {
                    event.preventDefault();
                    queueSave(currentQuestion);
                    return;
                }

                if (!updateFinishAvailability()) {
                    event.preventDefault();
                    aksiInput.value = 'simpan';
                    return;
                }

                finalSubmitStarted = true;
            });

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    questionCards.forEach((card, index) => {
                        if (card.dataset.dirty === '1') queueSave(index);
                    });
                    reportSecurityAway('visibilitychange', true);
                } else {
                    reportSecurityReturn('visibilitychange');
                }
            });

            window.addEventListener('pagehide', () => reportSecurityAway('pagehide', true));
            window.addEventListener('pageshow', () => {
                if (!document.hidden) reportSecurityReturn('pageshow');
            });
            window.addEventListener('focus', () => {
                if (!document.hidden) reportSecurityReturn('focus');
            });

            securityWarningDialog.addEventListener('cancel', event => event.preventDefault());
            securityWarningConfirm.addEventListener('click', () => securityWarningDialog.close());
            checkSecurityStatus.addEventListener('click', async () => {
                checkSecurityStatus.disabled = true;
                securityHoldStatus.textContent = 'Memeriksa status kepada server...';
                const result = await sendSecurityHeartbeat('periksa_status');
                if (!result && isExamHeld) {
                    securityHoldStatus.textContent = 'Status belum dapat diperiksa. Pastikan perangkat terhubung ke jaringan.';
                }
                checkSecurityStatus.disabled = false;
            });

            window.addEventListener('online', () => {
                questionCards.forEach((card, index) => {
                    if (card.dataset.dirty === '1') queueSave(index);
                });
                if (securityAway && document.hidden) {
                    queueSecurityEvent('keluar', 'online', true);
                } else {
                    sendSecurityHeartbeat('online');
                }
            });

            document.getElementById('retrySave').addEventListener('click', () => {
                questionCards.forEach((card, index) => {
                    if (card.dataset.dirty === '1') {
                        card.dataset.saveFailures = '0';
                        queueSave(index);
                    }
                });
            });

            window.addEventListener('beforeunload', (event) => {
                if (!finalSubmitStarted && !automaticSubmitStarted) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });

            questionCards.forEach((card, index) => refreshCardState(index));
            showQuestion(0, false);
            setSaveStatus('saved', 'Jawaban disimpan otomatis');
            if (isExamHeld) {
                setExamHeld({
                    mode: 'ditahan',
                    pesan: 'Ujian ditahan karena batas keluar aplikasi tercapai. Minta pengawas membuka ujian.',
                    keamanan: {
                        jumlah_kejadian: {{ (int) $peserta->jumlah_pindah_aplikasi }},
                        batas_kejadian: {{ max(1, (int) $ujian->batas_pindah_aplikasi) }},
                        ditahan: true,
                    },
                });
            }
            updateTimer();
            setInterval(updateTimer, 1000);
            setTimeout(() => {
                if (document.hidden) {
                    reportSecurityAway('awal', true);
                    return;
                }
                queueSecurityEvent('kembali', 'awal');
                sendSecurityHeartbeat('awal');
            }, 1000);
            setInterval(sendSecurityHeartbeat, securityHeartbeatIntervalMs);
        })();
    </script>
@endpush
