@extends('cbt.layout')

@section('title', 'Mengerjakan Ujian - CBT NUSA')

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
            <span class="badge badge-primary">{{ $ujian->mataPelajaran?->nama ?: 'CBT' }}</span>
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
                            $pilihan = $opsiPilihan($soal);
                        @endphp

                        <article id="soal-{{ $nomor }}" class="panel panel-pad question-card">
                            <div class="question-head">
                                <div style="display: flex; gap: 12px; align-items: flex-start;">
                                    <span class="question-number">{{ $nomor }}</span>
                                    <div>
                                        <span class="badge badge-muted">{{ $soal?->labelJenis() ?: 'Soal' }}</span>
                                        <h2 class="question-title">{{ $soal?->pertanyaan ?: 'Soal tidak ditemukan.' }}</h2>
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

                    <div class="save-bar">
                        <button type="submit" class="button button-muted" onclick="document.getElementById('aksiInput').value='simpan'">Simpan jawaban</button>
                        <button type="submit" class="button button-primary" onclick="return konfirmasiSelesai()">Selesai ujian</button>
                    </div>
                </section>

                <aside class="exam-side">
                    <section class="timer">
                        <div id="timerValue" class="timer-value">--:--:--</div>
                        <p class="timer-label">Sisa waktu</p>
                    </section>

                    <section class="panel panel-pad">
                        <h2 class="panel-title">Nomor soal</h2>
                        <div class="nav-grid" style="margin-top: 12px;">
                            @foreach ($soalUjian as $index => $relasiSoal)
                                <a class="nav-number" href="#soal-{{ $index + 1 }}">{{ $index + 1 }}</a>
                            @endforeach
                        </div>
                    </section>

                    <section class="panel panel-pad">
                        <p class="info-label">Peserta</p>
                        <p class="info-value">{{ $siswa?->nama_lengkap ?: '-' }}</p>
                        <p class="muted" style="margin: 6px 0 0; font-size: .88rem; font-weight: 800;">{{ $kelas?->nama ?: '-' }}</p>
                    </section>
                </aside>
            </div>
        </form>
    </main>
@endsection

@push('scripts')
    <script>
        const timerValue = document.getElementById('timerValue');
        const aksiInput = document.getElementById('aksiInput');
        const formUjian = document.getElementById('formUjian');
        const targetTime = Date.now() + ({{ (int) $sisaDetik }} * 1000);
        let sudahSubmitOtomatis = false;

        function formatDuaDigit(nilai) {
            return String(nilai).padStart(2, '0');
        }

        function perbaruiTimer() {
            const sisa = Math.max(0, Math.floor((targetTime - Date.now()) / 1000));
            const jam = Math.floor(sisa / 3600);
            const menit = Math.floor((sisa % 3600) / 60);
            const detik = sisa % 60;

            timerValue.textContent = `${formatDuaDigit(jam)}:${formatDuaDigit(menit)}:${formatDuaDigit(detik)}`;

            if (sisa <= 300) {
                timerValue.style.color = '#b91c1c';
            }

            if (sisa <= 0 && !sudahSubmitOtomatis) {
                sudahSubmitOtomatis = true;
                aksiInput.value = 'selesai';
                formUjian.submit();
            }
        }

        function konfirmasiSelesai() {
            if (!confirm('Selesaikan ujian sekarang? Setelah selesai, jawaban tidak dapat diubah.')) {
                return false;
            }

            aksiInput.value = 'selesai';
            return true;
        }

        perbaruiTimer();
        setInterval(perbaruiTimer, 1000);
    </script>
@endpush
