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

        .question-actions .button:disabled {
            cursor: not-allowed;
            opacity: .42;
        }

        .nav-number {
            cursor: pointer;
        }

        .nav-number.is-answered {
            border-color: #70b394;
            background: #e1f3e9;
            color: #116644;
        }

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

            .question-actions .button-finish {
                grid-column: 1 / -1;
            }

            .save-bar {
                margin-right: -12px;
                margin-left: -12px;
                padding: 10px 12px 8px;
                border: 1px solid var(--line);
                border-bottom: 0;
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
        }
    </style>
@endpush

@section('body')
    @php
        $siswa = $peserta->anggotaKelas?->siswa;
        $ujian = $peserta->ujianCbt;
        $kelas = $peserta->kelasUjianCbt?->kelas;

        $opsiPilihan = function ($soal) {
            $opsi = $soal?->opsi ?? [];
            $pilihan = $opsi['pilihan'] ?? $opsi;

            return collect($pilihan)
                ->mapWithKeys(function ($item, $key) {
                    if (is_array($item)) {
                        $kode = $item['kode'] ?? $key;
                        $teks = $item['teks'] ?? $item['label'] ?? '';
                    } else {
                        $kode = $key;
                        $teks = $item;
                    }

                    return [mb_strtoupper((string) $kode) => (string) $teks];
                })
                ->filter(fn ($teks) => filled($teks))
                ->sortKeys()
                ->all();
        };
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

        <form id="formUjian" action="{{ route('cbt.ujian.simpan') }}" method="POST">
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
                            $raguSaatIni = (bool) ($jawabanModel?->ragu ?? false);
                            $jawabanAssoc = is_array($jawabanSaatIni) ? $jawabanSaatIni : [];
                            $terjawabSaatIni = collect((array) $jawabanSaatIni)->contains(fn ($nilai) => filled($nilai));
                            $pilihan = $opsiPilihan($soal);
                        @endphp

                        <article
                            id="soal-{{ $nomor }}"
                            class="panel panel-pad question-card"
                            data-question-id="{{ $relasiSoal->id }}"
                            data-question-index="{{ $index }}"
                            data-answered="{{ $terjawabSaatIni ? '1' : '0' }}"
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

                            @if (filled($soal?->stimulus))
                                <div class="stimulus">{{ $soal->stimulus }}</div>
                            @endif

                            @if ($soal)
                                <x-media-soal :media="$soal->media" />
                            @endif

                            <h2 class="question-title">{{ $soal?->pertanyaan ?: 'Soal tidak ditemukan.' }}</h2>

                            @if (in_array($soal?->jenis_soal, ['pilihan_ganda', 'pilihan_ganda_kompleks'], true))
                                <div class="option-list">
                                    @foreach ($pilihan as $kode => $teks)
                                        <label class="option-card">
                                            @if ($soal->jenis_soal === 'pilihan_ganda')
                                                <input type="radio" name="jawaban[{{ $relasiSoal->id }}]" value="{{ $kode }}" @checked(in_array($kode, (array) $jawabanSaatIni, true))>
                                            @else
                                                <input type="checkbox" name="jawaban[{{ $relasiSoal->id }}][]" value="{{ $kode }}" @checked(in_array($kode, (array) $jawabanSaatIni, true))>
                                            @endif
                                            <span>
                                                <span class="option-code">{{ $kode }}</span>
                                                <span class="option-text">{{ $teks }}</span>
                                            </span>
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
                                            </div>
                                            <input type="text" name="jawaban[{{ $relasiSoal->id }}][{{ $nomorPasangan }}]" value="{{ $nilaiPasangan }}" class="input" placeholder="Tulis pasangan jawaban">
                                        </div>
                                    @endforeach
                                </div>
                            @elseif (in_array($soal?->jenis_soal, ['isian_singkat', 'numerik'], true))
                                <div class="field" style="margin-top: 14px;">
                                    <label for="jawaban-{{ $relasiSoal->id }}">Jawaban</label>
                                    <input id="jawaban-{{ $relasiSoal->id }}" type="{{ $soal->jenis_soal === 'numerik' ? 'number' : 'text' }}" name="jawaban[{{ $relasiSoal->id }}]" value="{{ collect((array) $jawabanSaatIni)->first() }}" class="input">
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
                            <span id="saveStatus" class="answer-save-state" role="status" aria-live="polite">Jawaban disimpan otomatis</span>
                        </div>
                        <div class="question-actions">
                            <button id="previousQuestion" type="button" class="button button-muted">Sebelumnya</button>
                            <button id="nextQuestion" type="button" class="button button-muted">Berikutnya</button>
                            <button id="openFinishDialog" type="button" class="button button-primary button-finish">Selesai Ujian</button>
                        </div>
                    </div>

                    <noscript>
                        <div class="alert alert-danger">JavaScript tidak aktif. Semua soal tetap dapat dikerjakan, tetapi penyimpanan otomatis dan navigasi satu soal tidak tersedia.</div>
                        <div class="save-bar">
                            <button type="submit" class="button button-muted" onclick="document.getElementById('aksiInput').value='simpan'">Simpan Jawaban</button>
                            <button type="submit" class="button button-primary" onclick="document.getElementById('aksiInput').value='selesai'">Selesai Ujian</button>
                        </div>
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
                        <h2>Akhiri ujian sekarang?</h2>
                        <p class="muted" style="margin-top: 7px;">Pastikan seluruh jawaban sudah diperiksa sebelum ujian diakhiri.</p>
                    </div>
                    <div class="finish-summary">
                        <div class="finish-summary-item">
                            <strong id="finishAnswered">0</strong>
                            <span>Terjawab</span>
                        </div>
                        <div class="finish-summary-item">
                            <strong id="finishUnanswered">0</strong>
                            <span>Belum dijawab</span>
                        </div>
                        <div class="finish-summary-item">
                            <strong id="finishDoubt">0</strong>
                            <span>Ragu-ragu</span>
                        </div>
                    </div>
                    <div id="finishWarning" class="finish-warning" hidden></div>
                    <div class="actions" style="justify-content: flex-end;">
                        <button id="cancelFinish" type="button" class="button button-muted">Periksa Lagi</button>
                        <button id="confirmFinish" type="submit" class="button button-primary">Ya, Selesaikan</button>
                    </div>
                </div>
            </dialog>
        </form>
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
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const autosaveUrl = @json(route('cbt.ujian.jawaban'));
            const finishedUrl = @json(route('cbt.ujian.selesai'));
            const targetTime = Date.now() + ({{ (int) $sisaDetik }} * 1000);
            const saveTimers = new Map();
            const saveQueues = new Map();
            let currentQuestion = 0;
            let automaticSubmitStarted = false;
            let finalSubmitStarted = false;

            function formatTwoDigits(value) {
                return String(value).padStart(2, '0');
            }

            function updateTimer() {
                const remaining = Math.max(0, Math.floor((targetTime - Date.now()) / 1000));
                const hours = Math.floor(remaining / 3600);
                const minutes = Math.floor((remaining % 3600) / 60);
                const seconds = remaining % 60;
                const label = `${formatTwoDigits(hours)}:${formatTwoDigits(minutes)}:${formatTwoDigits(seconds)}`;

                if (timerValue) timerValue.textContent = label;
                if (timerValueCompact) timerValueCompact.textContent = label;

                if (remaining <= 300) {
                    if (timerValue) timerValue.style.color = '#b91c1c';
                    if (timerValueCompact) timerValueCompact.style.color = '#b91c1c';
                }

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

            function doubtFromCard(card) {
                const questionId = card.dataset.questionId;
                return Boolean(card.querySelector(`[name="ragu[${questionId}]"]`)?.checked);
            }

            function refreshCardState(index) {
                const card = questionCards[index];
                if (!card) return;

                card.dataset.answered = hasAnswer(answerFromCard(card)) ? '1' : '0';
                card.dataset.doubt = doubtFromCard(card) ? '1' : '0';
                refreshNavigation();
            }

            function refreshNavigation() {
                navigationButtons.forEach((button, index) => {
                    const card = questionCards[index];
                    const isCurrent = index === currentQuestion;

                    button.classList.toggle('is-current', isCurrent);
                    button.classList.toggle('is-answered', card?.dataset.answered === '1' && card?.dataset.doubt !== '1');
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
                if (!card || card.dataset.dirty !== '1') return true;

                const revision = Number(card.dataset.revision || 0);
                setSaveStatus('saving', `Menyimpan soal ${index + 1}...`);

                try {
                    const response = await fetch(autosaveUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
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

                    card.dataset.answered = result.terjawab ? '1' : '0';
                    card.dataset.doubt = result.ragu ? '1' : '0';

                    if (Number(card.dataset.revision || 0) === revision) {
                        card.dataset.dirty = '0';
                        setSaveStatus('saved', `Tersimpan ${result.tersimpan_pada}`);
                    } else {
                        scheduleSave(index, 150);
                    }

                    refreshNavigation();
                    return true;
                } catch (error) {
                    setSaveStatus('failed', error.message || 'Gagal menyimpan. Periksa koneksi.');
                    refreshNavigation();
                    return false;
                }
            }

            function queueSave(index) {
                clearTimeout(saveTimers.get(index));
                const previousQueue = saveQueues.get(index) || Promise.resolve();
                const nextQueue = previousQueue.catch(() => false).then(() => performSave(index));
                saveQueues.set(index, nextQueue);
                return nextQueue;
            }

            function markDirty(index, delay) {
                const card = questionCards[index];
                card.dataset.dirty = '1';
                card.dataset.revision = String(Number(card.dataset.revision || 0) + 1);
                refreshCardState(index);
                setSaveStatus('unsaved', 'Belum tersimpan');
                scheduleSave(index, delay);
            }

            function refreshFinishSummary() {
                questionCards.forEach((card, index) => refreshCardState(index));
                const answered = questionCards.filter((card) => card.dataset.answered === '1').length;
                const doubt = questionCards.filter((card) => card.dataset.doubt === '1').length;
                const unanswered = questionCards.length - answered;

                finishAnswered.textContent = answered;
                finishUnanswered.textContent = unanswered;
                finishDoubt.textContent = doubt;
                finishWarning.hidden = unanswered === 0 && doubt === 0;
                finishWarning.textContent = unanswered > 0
                    ? `Masih ada ${unanswered} soal yang belum dijawab${doubt > 0 ? ` dan ${doubt} soal ditandai ragu-ragu` : ''}.`
                    : `${doubt} soal masih ditandai ragu-ragu.`;
            }

            questionCards.forEach((card, index) => {
                card.querySelectorAll('input, textarea, select').forEach((input) => {
                    const delay = ['text', 'number'].includes(input.type) || input.tagName === 'TEXTAREA' ? 850 : 180;
                    const eventName = delay === 850 ? 'input' : 'change';
                    input.addEventListener(eventName, () => markDirty(index, delay));
                });
            });

            navigationButtons.forEach((button) => {
                button.addEventListener('click', async () => {
                    await queueSave(currentQuestion);
                    showQuestion(Number(button.dataset.questionIndex));
                });
            });

            previousButton.addEventListener('click', async () => {
                await queueSave(currentQuestion);
                showQuestion(currentQuestion - 1);
            });

            nextButton.addEventListener('click', async () => {
                await queueSave(currentQuestion);
                showQuestion(currentQuestion + 1);
            });

            openFinishDialogButton.addEventListener('click', () => {
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
            confirmFinishButton.addEventListener('click', () => {
                finalSubmitStarted = true;
                aksiInput.value = 'selesai';
            });

            formUjian.addEventListener('submit', (event) => {
                if (aksiInput.value !== 'selesai') {
                    event.preventDefault();
                    queueSave(currentQuestion);
                    return;
                }

                finalSubmitStarted = true;
            });

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) queueSave(currentQuestion);
            });

            window.addEventListener('online', () => {
                questionCards.forEach((card, index) => {
                    if (card.dataset.dirty === '1') queueSave(index);
                });
            });

            window.addEventListener('beforeunload', (event) => {
                if (!finalSubmitStarted && !automaticSubmitStarted) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });

            showQuestion(0, false);
            setSaveStatus('saved', 'Jawaban disimpan otomatis');
            updateTimer();
            setInterval(updateTimer, 1000);
        })();
    </script>
@endpush
