<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Scan Absensi - NUSA</title>

        @fonts
        <link rel="icon" href="{{ asset('images/logo-nusa.png') }}" type="image/png">

        <style>
            :root {
                --primary: #15477A;
                --primary-dark: #0d3157;
                --accent: #F1C40F;
                --accent-dark: #aa8500;
                --text: #f8fafc;
                --muted: rgba(248, 250, 252, .76);
                --panel: rgba(255, 255, 255, .94);
                --panel-soft: rgba(255, 255, 255, .14);
                --line: rgba(255, 255, 255, .22);
                --success: #15803d;
                --success-soft: #dcfce7;
                --danger: #b91c1c;
                --danger-soft: #fee2e2;
                --warning: #92400e;
                --warning-soft: #fef3c7;
            }

            * {
                box-sizing: border-box;
            }

            html,
            body {
                min-height: 100%;
            }

            body {
                margin: 0;
                background: var(--primary);
                color: var(--text);
                font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                line-height: 1.5;
            }

            button,
            input {
                font: inherit;
            }

            .scan-page {
                position: relative;
                min-height: 100vh;
                overflow: hidden;
                background-image:
                    linear-gradient(rgba(7, 28, 54, .78), rgba(7, 28, 54, .78)),
                    url("{{ asset('images/background-scanner.jpg') }}");
                background-position: center;
                background-size: cover;
            }

            .scan-page::after {
                position: absolute;
                inset: 0;
                background: rgba(21, 71, 122, .18);
                content: "";
                pointer-events: none;
            }

            .scan-shell {
                position: relative;
                z-index: 1;
                display: grid;
                min-height: 100vh;
                grid-template-rows: auto 1fr;
                gap: clamp(16px, 2.4vw, 28px);
                padding: clamp(16px, 3vw, 34px);
            }

            .scan-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 18px;
            }

            .brand {
                display: flex;
                min-width: 0;
                align-items: center;
                gap: 14px;
            }

            .brand-mark {
                display: grid;
                width: clamp(54px, 6vw, 76px);
                aspect-ratio: 1;
                place-items: center;
                border: 1px solid rgba(241, 196, 15, .72);
                border-radius: 8px;
                background: rgba(255, 255, 255, .95);
                padding: 7px;
                box-shadow: 0 16px 40px rgba(3, 16, 32, .22);
            }

            .brand-mark img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            .brand-title {
                display: block;
                font-size: clamp(1.35rem, 2.4vw, 2rem);
                font-weight: 900;
                line-height: 1.05;
            }

            .brand-subtitle {
                display: block;
                color: var(--muted);
                font-size: clamp(.86rem, 1.4vw, 1rem);
                font-weight: 700;
            }

            .status-strip {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: flex-end;
                gap: 10px;
            }

            .status-pill {
                display: inline-flex;
                min-height: 38px;
                align-items: center;
                border: 1px solid rgba(255, 255, 255, .28);
                border-radius: 999px;
                background: rgba(255, 255, 255, .12);
                padding: 8px 12px;
                color: #fff;
                font-size: .9rem;
                font-weight: 900;
                white-space: nowrap;
            }

            .status-pill strong {
                margin-left: 7px;
                color: var(--accent);
            }

            .scan-main {
                display: grid;
                grid-template-columns: minmax(0, .88fr) minmax(360px, 1.12fr);
                gap: clamp(16px, 2.4vw, 28px);
                align-items: stretch;
            }

            .time-panel,
            .scanner-panel {
                min-width: 0;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: rgba(255, 255, 255, .13);
                box-shadow: 0 24px 70px rgba(2, 12, 24, .28);
            }

            .time-panel {
                display: grid;
                align-content: space-between;
                gap: 22px;
                padding: clamp(18px, 3vw, 30px);
            }

            .time-label {
                margin: 0;
                color: var(--muted);
                font-size: clamp(.9rem, 1.6vw, 1.02rem);
                font-weight: 800;
            }

            .clock {
                margin: 4px 0 0;
                color: #fff;
                font-size: clamp(3.2rem, 10vw, 8.2rem);
                font-weight: 950;
                letter-spacing: 0;
                line-height: .94;
                text-shadow: 0 18px 44px rgba(0, 0, 0, .26);
                white-space: nowrap;
            }

            .date-line {
                margin: 10px 0 0;
                color: var(--muted);
                font-size: clamp(1rem, 2vw, 1.28rem);
                font-weight: 800;
            }

            .schedule-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .schedule-item {
                border: 1px solid rgba(255, 255, 255, .22);
                border-radius: 8px;
                background: rgba(255, 255, 255, .12);
                padding: 14px;
            }

            .schedule-item.is-active {
                border-color: rgba(241, 196, 15, .85);
                background: rgba(241, 196, 15, .16);
            }

            .schedule-label {
                margin: 0 0 8px;
                color: var(--accent);
                font-size: .88rem;
                font-weight: 950;
                text-transform: uppercase;
            }

            .schedule-time {
                margin: 0;
                font-size: clamp(1.15rem, 2.3vw, 1.5rem);
                font-weight: 950;
                line-height: 1.1;
            }

            .schedule-note {
                margin: 8px 0 0;
                color: var(--muted);
                font-size: .9rem;
                font-weight: 750;
            }

            .scanner-panel {
                display: grid;
                grid-template-rows: auto auto minmax(0, 1fr) auto;
                gap: 14px;
                padding: clamp(16px, 2.8vw, 26px);
            }

            .scanner-top {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }

            .scanner-title {
                margin: 0;
                font-size: clamp(1.2rem, 2.4vw, 1.65rem);
                font-weight: 950;
                line-height: 1.15;
            }

            .queue-badge {
                display: inline-flex;
                min-height: 40px;
                align-items: center;
                justify-content: center;
                border-radius: 999px;
                background: var(--accent);
                padding: 8px 14px;
                color: #1b2938;
                font-weight: 950;
                white-space: nowrap;
            }

            .scan-capture-wrap {
                display: grid;
                gap: 8px;
            }

            .scan-capture-label {
                color: var(--muted);
                font-size: .86rem;
                font-weight: 850;
            }

            .scan-capture {
                width: 100%;
                min-height: 50px;
                border: 1px solid rgba(255, 255, 255, .28);
                border-radius: 8px;
                background: rgba(255, 255, 255, .94);
                padding: 12px 14px;
                color: #0f172a;
                font-size: 1rem;
                font-weight: 850;
                outline: none;
            }

            .scan-capture:focus {
                border-color: var(--accent);
                box-shadow: 0 0 0 4px rgba(241, 196, 15, .28);
            }

            .result-card {
                display: grid;
                min-height: 300px;
                grid-template-columns: minmax(124px, 170px) minmax(0, 1fr);
                gap: clamp(14px, 2vw, 22px);
                align-items: center;
                border: 1px solid rgba(255, 255, 255, .28);
                border-radius: 8px;
                background: var(--panel);
                padding: clamp(16px, 2.6vw, 24px);
                color: #102035;
            }

            .result-card.is-success {
                border-color: rgba(21, 128, 61, .4);
                background: #f0fdf4;
            }

            .result-card.is-recorded {
                border-color: rgba(21, 71, 122, .42);
                background: #eff6ff;
            }

            .result-card.is-warning {
                border-color: rgba(180, 123, 0, .4);
                background: #fffbeb;
            }

            .result-card.is-error {
                border-color: rgba(185, 28, 28, .3);
                background: #fef2f2;
            }

            .student-photo {
                display: grid;
                width: 100%;
                aspect-ratio: 3 / 4;
                place-items: center;
                overflow: hidden;
                border: 4px solid #fff;
                border-radius: 8px;
                background: #e8f0f8;
                color: var(--primary);
                font-size: clamp(2.2rem, 5vw, 3.6rem);
                font-weight: 950;
                box-shadow: 0 14px 32px rgba(13, 49, 87, .18);
            }

            .student-photo img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .result-status {
                display: inline-flex;
                margin-bottom: 12px;
                border-radius: 999px;
                background: #e8f0f8;
                padding: 6px 10px;
                color: var(--primary);
                font-size: .82rem;
                font-weight: 950;
                text-transform: uppercase;
            }

            .result-card.is-success .result-status {
                background: var(--success-soft);
                color: var(--success);
            }

            .result-card.is-recorded .result-status {
                background: #dbeafe;
                color: var(--primary);
            }

            .result-card.is-warning .result-status {
                background: #fef3c7;
                color: #8a5b00;
            }

            .result-card.is-error .result-status {
                background: var(--danger-soft);
                color: var(--danger);
            }

            .result-name {
                margin: 0;
                overflow-wrap: anywhere;
                font-size: clamp(1.55rem, 4vw, 2.35rem);
                font-weight: 950;
                line-height: 1.1;
            }

            .result-nisn {
                margin: 10px 0 0;
                color: #475569;
                font-size: clamp(1rem, 1.8vw, 1.18rem);
                font-weight: 900;
            }

            .result-message {
                margin: 14px 0 0;
                color: #334155;
                font-size: clamp(.98rem, 1.8vw, 1.08rem);
                font-weight: 760;
            }

            .result-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 18px;
            }

            .meta-pill {
                border-radius: 999px;
                background: #f1f5f9;
                padding: 6px 10px;
                color: #334155;
                font-size: .86rem;
                font-weight: 900;
            }

            .history-list {
                display: grid;
                max-height: 190px;
                overflow: auto;
                gap: 8px;
                padding-right: 4px;
            }

            .history-item {
                display: grid;
                grid-template-columns: 76px minmax(0, 1fr) auto;
                gap: 10px;
                align-items: center;
                border: 1px solid rgba(255, 255, 255, .18);
                border-radius: 8px;
                background: rgba(255, 255, 255, .1);
                padding: 9px 10px;
                color: #fff;
            }

            .history-time,
            .history-kind,
            .history-name {
                font-size: .85rem;
                font-weight: 900;
            }

            .history-name {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .history-kind {
                border-radius: 999px;
                background: rgba(241, 196, 15, .18);
                padding: 4px 8px;
                color: var(--accent);
                white-space: nowrap;
            }

            .empty-note {
                border: 1px dashed rgba(255, 255, 255, .28);
                border-radius: 8px;
                padding: 16px;
                color: var(--muted);
                font-size: .95rem;
                font-weight: 800;
                text-align: center;
            }

            .offline-note {
                border: 1px solid rgba(241, 196, 15, .48);
                border-radius: 8px;
                background: rgba(241, 196, 15, .12);
                padding: 12px 14px;
                color: #fff;
                font-weight: 850;
            }

            @media (max-width: 1000px) {
                .scan-main {
                    grid-template-columns: 1fr;
                }

                .result-card {
                    min-height: 260px;
                }
            }

            @media (max-width: 720px) {
                .scan-shell {
                    padding: 14px;
                }

                .scan-header,
                .scanner-top {
                    align-items: stretch;
                    flex-direction: column;
                }

                .status-strip {
                    justify-content: flex-start;
                }

                .schedule-grid,
                .result-card {
                    grid-template-columns: 1fr;
                }

                .student-photo {
                    width: min(170px, 46vw);
                    justify-self: center;
                }

                .clock {
                    font-size: clamp(3rem, 16vw, 5.2rem);
                }

                .history-item {
                    grid-template-columns: 68px minmax(0, 1fr);
                }

                .history-kind {
                    grid-column: 1 / -1;
                    justify-self: start;
                }
            }
        </style>
    </head>
    <body>
        <div class="scan-page">
            <div class="scan-shell">
                <header class="scan-header">
                    <div class="brand">
                        <span class="brand-mark">
                            <img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
                        </span>
                        <span>
                            <span class="brand-title">NUSA</span>
                            <span class="brand-subtitle">SMP Negeri 2 Padang Panjang</span>
                        </span>
                    </div>

                    <div class="status-strip">
                        <span class="status-pill">Hari <strong>{{ $hariLabel }}</strong></span>
                        <span class="status-pill">Mode <strong id="modeText">Memuat</strong></span>
                        <span class="status-pill">Status <strong id="statusText">Siap</strong></span>
                    </div>
                </header>

                <main class="scan-main">
                    <section class="time-panel" aria-label="Waktu dan jadwal absensi">
                        <div>
                            <p class="time-label">Waktu server</p>
                            <div class="clock" id="clockText">--:--:--</div>
                            <p class="date-line">{{ $tanggalHariIni }}</p>
                        </div>

                        @if ($jadwal)
                            <div class="schedule-grid">
                                <div class="schedule-item" id="jadwalMasuk">
                                    <p class="schedule-label">Masuk</p>
                                    <p class="schedule-time">{{ $jadwal['jam_scan_masuk_mulai'] }} - {{ $jadwal['jam_scan_masuk_selesai'] }}</p>
                                    <p class="schedule-note">Jam masuk {{ $jadwal['jam_masuk'] }}</p>
                                </div>

                                <div class="schedule-item" id="jadwalPulang">
                                    <p class="schedule-label">Pulang</p>
                                    <p class="schedule-time">{{ $jadwal['jam_scan_pulang_mulai'] }} - {{ $jadwal['jam_scan_pulang_selesai'] }}</p>
                                    <p class="schedule-note">Jam pulang {{ $jadwal['jam_pulang'] }}</p>
                                </div>
                            </div>
                        @else
                            <div class="offline-note">
                                Pengaturan absensi hari ini belum aktif.
                            </div>
                        @endif
                    </section>

                    <section class="scanner-panel" aria-label="Scanner absensi">
                        <div class="scanner-top">
                            <h1 class="scanner-title">Scan Absensi</h1>
                            <span class="queue-badge">Antrean: <span id="queueCount">0</span></span>
                        </div>

                        <div class="scan-capture-wrap">
                            <label class="scan-capture-label" for="scanInput">Area scan aktif</label>
                            <input
                                id="scanInput"
                                class="scan-capture"
                                type="text"
                                name="isi_scan"
                                inputmode="numeric"
                                autocomplete="off"
                                autofocus
                                placeholder="Scan kartu siswa"
                            >
                        </div>

                        <div class="result-card" id="resultCard">
                            <div class="student-photo" id="studentPhoto">N</div>
                            <div>
                                <span class="result-status" id="resultStatus">Siap scan</span>
                                <h2 class="result-name" id="resultName">Tempelkan kartu pada scanner</h2>
                                <p class="result-nisn" id="resultNisn">NISN akan tampil setelah scan berhasil</p>
                                <p class="result-message" id="resultMessage">Siap menerima scan berikutnya.</p>
                                <div class="result-meta" id="resultMeta"></div>
                            </div>
                        </div>

                        <div class="history-list" id="historyList">
                            <div class="empty-note" id="emptyHistory">Belum ada scan pada sesi ini.</div>
                        </div>
                    </section>
                </main>
            </div>
        </div>

        <script>
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const jadwal = @json($jadwal);
            const serverAwal = new Date(@json(now()->toIso8601String())).getTime();
            const clientAwal = Date.now();
            const scanQueue = [];
            const riwayat = [];
            const statusSudahTercatat = new Set(['duplikat_cepat', 'sudah_scan_masuk', 'sudah_scan_pulang']);
            let sedangMemproses = false;
            const formatterWaktu = new Intl.DateTimeFormat('id-ID', {
                timeZone: 'Asia/Jakarta',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hourCycle: 'h23',
            });

            const elements = {
                clockText: document.getElementById('clockText'),
                modeText: document.getElementById('modeText'),
                statusText: document.getElementById('statusText'),
                queueCount: document.getElementById('queueCount'),
                scanInput: document.getElementById('scanInput'),
                resultCard: document.getElementById('resultCard'),
                resultStatus: document.getElementById('resultStatus'),
                resultName: document.getElementById('resultName'),
                resultNisn: document.getElementById('resultNisn'),
                resultMessage: document.getElementById('resultMessage'),
                resultMeta: document.getElementById('resultMeta'),
                studentPhoto: document.getElementById('studentPhoto'),
                historyList: document.getElementById('historyList'),
                emptyHistory: document.getElementById('emptyHistory'),
                jadwalMasuk: document.getElementById('jadwalMasuk'),
                jadwalPulang: document.getElementById('jadwalPulang'),
            };

            function waktuServer() {
                return new Date(serverAwal + (Date.now() - clientAwal));
            }

            function duaDigit(nilai) {
                return String(nilai).padStart(2, '0');
            }

            function bagianWaktuJakarta(tanggal) {
                return Object.fromEntries(
                    formatterWaktu
                        .formatToParts(tanggal)
                        .filter((part) => part.type !== 'literal')
                        .map((part) => [part.type, Number.parseInt(part.value, 10)])
                );
            }

            function jamDariTanggal(tanggal) {
                const bagian = bagianWaktuJakarta(tanggal);

                return `${duaDigit(bagian.hour)}:${duaDigit(bagian.minute)}:${duaDigit(bagian.second)}`;
            }

            function menitDariJam(jam) {
                if (! jam || jam === '-') {
                    return null;
                }

                const bagian = jam.split(':').map((item) => Number.parseInt(item, 10));
                return (bagian[0] * 60) + bagian[1];
            }

            function modeSaatIni(tanggal) {
                if (! jadwal) {
                    return { label: 'Belum aktif', aktif: null };
                }

                const bagian = bagianWaktuJakarta(tanggal);
                const menit = (bagian.hour * 60) + bagian.minute;
                const masukMulai = menitDariJam(jadwal.jam_scan_masuk_mulai);
                const masukSelesai = menitDariJam(jadwal.jam_scan_masuk_selesai);
                const pulangMulai = menitDariJam(jadwal.jam_scan_pulang_mulai);
                const pulangSelesai = menitDariJam(jadwal.jam_scan_pulang_selesai);

                if (menit >= masukMulai && menit <= masukSelesai) {
                    return { label: 'Masuk', aktif: 'masuk' };
                }

                if (menit >= pulangMulai && menit <= pulangSelesai) {
                    return { label: 'Pulang', aktif: 'pulang' };
                }

                return { label: 'Di luar jadwal', aktif: null };
            }

            function perbaruiJam() {
                const sekarang = waktuServer();
                const mode = modeSaatIni(sekarang);

                elements.clockText.textContent = jamDariTanggal(sekarang);
                elements.modeText.textContent = mode.label;
                elements.jadwalMasuk?.classList.toggle('is-active', mode.aktif === 'masuk');
                elements.jadwalPulang?.classList.toggle('is-active', mode.aktif === 'pulang');
            }

            function fokusScan() {
                if (document.activeElement !== elements.scanInput) {
                    elements.scanInput.focus({ preventScroll: true });
                }
            }

            function tambahAntrean(nilaiScan) {
                scanQueue.push({
                    isi_scan: nilaiScan,
                    waktu: jamDariTanggal(waktuServer()),
                });

                perbaruiAntrean();
                prosesAntrean();
            }

            function perbaruiAntrean() {
                elements.queueCount.textContent = String(scanQueue.length);
                elements.statusText.textContent = sedangMemproses ? 'Memproses' : 'Siap';
            }

            async function prosesAntrean() {
                if (sedangMemproses || scanQueue.length === 0) {
                    perbaruiAntrean();
                    return;
                }

                sedangMemproses = true;
                perbaruiAntrean();

                const item = scanQueue.shift();
                perbaruiAntrean();

                try {
                    const response = await fetch(@json(route('scan-absensi.store')), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            isi_scan: item.isi_scan,
                        }),
                    });

                    const contentType = response.headers.get('content-type') || '';
                    const payload = contentType.includes('application/json')
                        ? await response.json()
                        : {
                            berhasil: false,
                            pesan: 'Server belum mengembalikan data scan.',
                            status: 'server_error',
                            siswa: null,
                        };

                    tampilkanHasil(payload);
                    tambahRiwayat(payload);
                } catch (error) {
                    const payload = {
                        berhasil: false,
                        status: 'koneksi_gagal',
                        pesan: 'Gagal menghubungi server. Periksa koneksi aplikasi NUSA.',
                        siswa: null,
                        jenis_scan: null,
                    };

                    tampilkanHasil(payload);
                    tambahRiwayat(payload);
                }

                await new Promise((resolve) => setTimeout(resolve, 650));
                sedangMemproses = false;
                perbaruiAntrean();
                prosesAntrean();
            }

            function kategoriHasil(payload) {
                if (payload.berhasil) {
                    return 'success';
                }

                if (statusSudahTercatat.has(payload.status)) {
                    return 'recorded';
                }

                if (payload.status === 'jadwal_absensi_tidak_ada' || payload.status?.startsWith('di_luar_jadwal')) {
                    return 'warning';
                }

                return 'error';
            }

            function labelHasil(payload, kategori) {
                if (kategori === 'success') {
                    return 'Absensi berhasil';
                }

                if (kategori === 'recorded') {
                    return 'Absensi sudah tercatat';
                }

                if (kategori === 'warning') {
                    return payload.status?.startsWith('di_luar_jadwal')
                        ? 'Belum waktunya scan'
                        : 'Jadwal belum aktif';
                }

                return 'Scan gagal';
            }

            function waktuAbsensiTercatat(payload) {
                if (payload.jenis_scan === 'masuk') {
                    return payload.absensi?.jam_masuk;
                }

                if (payload.jenis_scan === 'pulang') {
                    return payload.absensi?.jam_pulang;
                }

                return null;
            }

            function tampilkanHasil(payload) {
                const kategori = kategoriHasil(payload);

                elements.resultCard.classList.toggle('is-success', kategori === 'success');
                elements.resultCard.classList.toggle('is-recorded', kategori === 'recorded');
                elements.resultCard.classList.toggle('is-warning', kategori === 'warning');
                elements.resultCard.classList.toggle('is-error', kategori === 'error');
                elements.resultStatus.textContent = labelHasil(payload, kategori);
                elements.resultName.textContent = payload.siswa?.nama_lengkap || 'Data siswa belum ditemukan';
                elements.resultNisn.textContent = payload.siswa?.nisn
                    ? `NISN ${payload.siswa.nisn}`
                    : (payload.nisn ? `NISN ${payload.nisn}` : 'NISN tidak tersedia');
                elements.resultMessage.textContent = payload.pesan || 'Scan selesai diproses.';
                tampilkanFoto(payload.siswa);
                tampilkanMeta(payload);
            }

            function tampilkanFoto(siswa) {
                elements.studentPhoto.innerHTML = '';

                if (siswa?.foto_url) {
                    const img = document.createElement('img');
                    img.src = siswa.foto_url;
                    img.alt = siswa.nama_lengkap || 'Foto siswa';
                    elements.studentPhoto.appendChild(img);
                    return;
                }

                elements.studentPhoto.textContent = siswa?.inisial || 'N';
            }

            function tampilkanMeta(payload) {
                const meta = [];
                const kategori = kategoriHasil(payload);

                if (payload.jenis_scan) {
                    meta.push(`Jenis: ${kapital(payload.jenis_scan)}`);
                }

                const waktuDitampilkan = kategori === 'recorded'
                    ? waktuAbsensiTercatat(payload)
                    : payload.waktu_server;

                if (waktuDitampilkan) {
                    meta.push(`${kategori === 'recorded' ? 'Tercatat' : 'Waktu'}: ${waktuDitampilkan}`);
                }

                if (payload.scanner_id) {
                    meta.push(`Scanner: ${payload.scanner_id}`);
                }

                if (payload.absensi?.menit_terlambat > 0) {
                    meta.push(`Terlambat: ${payload.absensi.menit_terlambat} menit`);
                }

                if (payload.absensi?.menit_pulang_cepat > 0) {
                    meta.push(`Pulang cepat: ${payload.absensi.menit_pulang_cepat} menit`);
                }

                elements.resultMeta.innerHTML = '';

                meta.forEach((teks) => {
                    const span = document.createElement('span');
                    span.className = 'meta-pill';
                    span.textContent = teks;
                    elements.resultMeta.appendChild(span);
                });
            }

            function tambahRiwayat(payload) {
                const kategori = kategoriHasil(payload);

                riwayat.unshift({
                    waktu: payload.waktu_server || jamDariTanggal(waktuServer()),
                    nama: payload.siswa?.nama_lengkap || payload.pesan || 'Scan gagal diproses',
                    jenis: kategori === 'recorded'
                        ? 'Sudah tercatat'
                        : (payload.jenis_scan ? kapital(payload.jenis_scan) : (kategori === 'warning' ? 'Periksa jadwal' : (payload.berhasil ? 'Scan' : 'Gagal'))),
                    berhasil: Boolean(payload.berhasil),
                });

                if (riwayat.length > 6) {
                    riwayat.pop();
                }

                renderRiwayat();
            }

            function renderRiwayat() {
                elements.historyList.innerHTML = '';

                if (riwayat.length === 0) {
                    elements.historyList.appendChild(elements.emptyHistory);
                    return;
                }

                riwayat.forEach((item) => {
                    const row = document.createElement('div');
                    row.className = 'history-item';

                    const waktu = document.createElement('span');
                    waktu.className = 'history-time';
                    waktu.textContent = item.waktu;

                    const nama = document.createElement('span');
                    nama.className = 'history-name';
                    nama.textContent = item.nama;

                    const jenis = document.createElement('span');
                    jenis.className = 'history-kind';
                    jenis.textContent = item.jenis;

                    row.append(waktu, nama, jenis);
                    elements.historyList.appendChild(row);
                });
            }

            function kapital(teks) {
                return teks.charAt(0).toUpperCase() + teks.slice(1).replaceAll('_', ' ');
            }

            elements.scanInput.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault();

                const nilaiScan = elements.scanInput.value.trim();
                elements.scanInput.value = '';

                if (nilaiScan === '') {
                    return;
                }

                tambahAntrean(nilaiScan);
            });

            document.addEventListener('click', fokusScan);
            document.addEventListener('visibilitychange', () => {
                if (! document.hidden) {
                    fokusScan();
                }
            });

            setInterval(perbaruiJam, 1000);
            setInterval(fokusScan, 2500);
            perbaruiJam();
            fokusScan();
        </script>
    </body>
</html>
