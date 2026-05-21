<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Scan Absensi Pegawai - NUSA</title>

        @fonts
        <link rel="icon" href="{{ asset('images/logo-nusa.png') }}" type="image/png">

        <style>
            :root {
                --primary: #15477A;
                --primary-dark: #0c2f55;
                --primary-soft: #e8f0f8;
                --accent: #F1C40F;
                --accent-soft: #fff6cc;
                --text: #102033;
                --muted: #64748b;
                --line: #dbe6f2;
                --panel: #ffffff;
                --success: #15803d;
                --success-soft: #dcfce7;
                --danger: #b91c1c;
                --danger-soft: #fee2e2;
                --warning: #92400e;
                --warning-soft: #fef3c7;
                --shadow: 0 18px 50px rgba(21, 71, 122, .12);
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
                background: #eef4fa;
                color: var(--text);
                font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                line-height: 1.5;
            }

            button,
            input {
                font: inherit;
            }

            .office-scan-page {
                min-height: 100vh;
                background:
                    linear-gradient(90deg, var(--primary) 0 34%, transparent 34%),
                    #eef4fa;
            }

            .office-shell {
                display: grid;
                min-height: 100vh;
                grid-template-columns: minmax(300px, 36vw) minmax(0, 1fr);
            }

            .office-side {
                display: grid;
                align-content: space-between;
                gap: 28px;
                min-width: 0;
                padding: clamp(18px, 3vw, 34px);
                color: #fff;
            }

            .brand {
                display: flex;
                min-width: 0;
                align-items: center;
                gap: 14px;
            }

            .brand-mark {
                display: grid;
                width: clamp(54px, 6vw, 74px);
                aspect-ratio: 1;
                place-items: center;
                border: 1px solid rgba(241, 196, 15, .82);
                border-radius: 8px;
                background: #fff;
                padding: 7px;
                box-shadow: 0 16px 34px rgba(4, 18, 36, .22);
            }

            .brand-mark img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            .brand-title {
                display: block;
                font-size: clamp(1.25rem, 2.4vw, 1.9rem);
                font-weight: 950;
                line-height: 1.05;
            }

            .brand-subtitle {
                display: block;
                margin-top: 2px;
                color: rgba(255, 255, 255, .75);
                font-size: .92rem;
                font-weight: 750;
            }

            .clock-panel {
                display: grid;
                gap: 12px;
            }

            .label {
                margin: 0;
                color: rgba(255, 255, 255, .68);
                font-size: .88rem;
                font-weight: 900;
                text-transform: uppercase;
            }

            .clock {
                margin: 0;
                color: #fff;
                font-size: clamp(3.4rem, 8vw, 6.6rem);
                font-weight: 950;
                letter-spacing: 0;
                line-height: .92;
                white-space: nowrap;
            }

            .date-line {
                margin: 0;
                color: rgba(255, 255, 255, .76);
                font-size: clamp(1rem, 2vw, 1.2rem);
                font-weight: 850;
            }

            .mode-panel {
                display: grid;
                gap: 10px;
                border: 1px solid rgba(255, 255, 255, .2);
                border-radius: 8px;
                background: rgba(255, 255, 255, .1);
                padding: 16px;
            }

            .mode-value {
                margin: 0;
                color: var(--accent);
                font-size: clamp(1.2rem, 2.7vw, 1.8rem);
                font-weight: 950;
                line-height: 1.08;
            }

            .status-line {
                margin: 0;
                color: rgba(255, 255, 255, .78);
                font-weight: 800;
            }

            .office-main {
                display: grid;
                min-width: 0;
                grid-template-rows: auto minmax(0, 1fr);
                gap: 16px;
                padding: clamp(16px, 3vw, 32px);
            }

            .top-strip {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
            }

            .page-title {
                margin: 0;
                color: var(--primary-dark);
                font-size: clamp(1.4rem, 3vw, 2rem);
                font-weight: 950;
                line-height: 1.12;
            }

            .queue-badge {
                display: inline-flex;
                min-height: 42px;
                align-items: center;
                justify-content: center;
                border: 1px solid rgba(21, 71, 122, .18);
                border-radius: 999px;
                background: #fff;
                padding: 9px 14px;
                color: var(--primary-dark);
                font-weight: 950;
                white-space: nowrap;
                box-shadow: 0 8px 22px rgba(21, 71, 122, .08);
            }

            .work-grid {
                display: grid;
                min-height: 0;
                grid-template-columns: minmax(360px, .98fr) minmax(0, 1.02fr);
                gap: 16px;
            }

            .scanner-panel,
            .schedule-panel,
            .result-card {
                min-width: 0;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: var(--panel);
                box-shadow: var(--shadow);
            }

            .scanner-panel {
                display: grid;
                align-content: start;
                gap: 16px;
                padding: clamp(16px, 2.4vw, 24px);
            }

            .scan-label {
                display: block;
                margin-bottom: 8px;
                color: var(--muted);
                font-size: .88rem;
                font-weight: 900;
            }

            .scan-capture {
                width: 100%;
                min-height: 56px;
                border: 2px solid #c8d8e8;
                border-radius: 8px;
                background: #fff;
                padding: 13px 14px;
                color: var(--text);
                font-size: 1.08rem;
                font-weight: 900;
                outline: none;
            }

            .scan-capture:focus {
                border-color: var(--accent);
                box-shadow: 0 0 0 4px rgba(241, 196, 15, .2);
            }

            .result-card {
                display: grid;
                grid-template-columns: minmax(124px, 165px) minmax(0, 1fr);
                gap: clamp(14px, 2vw, 22px);
                align-items: center;
                min-height: 286px;
                padding: clamp(16px, 2.5vw, 24px);
            }

            .result-card.is-success {
                border-color: rgba(21, 128, 61, .3);
                background: #f0fdf4;
            }

            .result-card.is-error {
                border-color: rgba(185, 28, 28, .26);
                background: #fef2f2;
            }

            .employee-photo {
                display: grid;
                width: 100%;
                aspect-ratio: 3 / 4;
                place-items: center;
                overflow: hidden;
                border: 4px solid #fff;
                border-radius: 8px;
                background: var(--primary-soft);
                color: var(--primary);
                font-size: clamp(2rem, 4vw, 3.2rem);
                font-weight: 950;
                box-shadow: 0 12px 30px rgba(21, 71, 122, .16);
            }

            .employee-photo img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .result-status {
                display: inline-flex;
                margin-bottom: 12px;
                border-radius: 999px;
                background: var(--primary-soft);
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

            .result-card.is-error .result-status {
                background: var(--danger-soft);
                color: var(--danger);
            }

            .result-name {
                margin: 0;
                overflow-wrap: anywhere;
                color: #0f172a;
                font-size: clamp(1.45rem, 3.3vw, 2.1rem);
                font-weight: 950;
                line-height: 1.1;
            }

            .result-nip,
            .result-message {
                margin: 10px 0 0;
                color: #475569;
                font-weight: 850;
            }

            .result-message {
                color: #334155;
            }

            .result-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 16px;
            }

            .meta-pill {
                border-radius: 999px;
                background: #f1f5f9;
                padding: 6px 10px;
                color: #334155;
                font-size: .84rem;
                font-weight: 900;
            }

            .schedule-panel {
                display: grid;
                min-height: 0;
                grid-template-rows: auto minmax(0, 1fr) auto;
                gap: 12px;
                padding: clamp(16px, 2.4vw, 24px);
            }

            .panel-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }

            .panel-title {
                margin: 0;
                color: var(--primary-dark);
                font-size: 1rem;
                font-weight: 950;
            }

            .schedule-list {
                display: grid;
                align-content: start;
                max-height: 330px;
                overflow: auto;
                gap: 10px;
                padding-right: 4px;
            }

            .schedule-item {
                display: grid;
                gap: 8px;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: #f8fafc;
                padding: 12px;
            }

            .schedule-item.is-active {
                border-color: rgba(241, 196, 15, .9);
                background: var(--accent-soft);
            }

            .schedule-item-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px;
            }

            .schedule-name {
                margin: 0;
                color: #0f172a;
                font-size: .95rem;
                font-weight: 950;
                line-height: 1.2;
            }

            .schedule-target {
                margin: 3px 0 0;
                color: var(--muted);
                font-size: .84rem;
                font-weight: 800;
            }

            .schedule-mode {
                border-radius: 999px;
                background: #e2e8f0;
                padding: 4px 8px;
                color: #334155;
                font-size: .72rem;
                font-weight: 950;
                white-space: nowrap;
            }

            .schedule-item.is-active .schedule-mode {
                background: var(--accent);
                color: #1f2937;
            }

            .schedule-time-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .schedule-time {
                border-radius: 8px;
                background: #fff;
                padding: 9px;
            }

            .schedule-time span {
                display: block;
                color: var(--muted);
                font-size: .74rem;
                font-weight: 900;
            }

            .schedule-time strong {
                display: block;
                margin-top: 2px;
                color: var(--primary-dark);
                font-size: .92rem;
                font-weight: 950;
            }

            .history-list {
                display: grid;
                gap: 8px;
            }

            .history-item {
                display: grid;
                grid-template-columns: 70px minmax(0, 1fr) auto;
                gap: 10px;
                align-items: center;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: #fff;
                padding: 9px 10px;
            }

            .history-time,
            .history-name,
            .history-kind {
                font-size: .84rem;
                font-weight: 900;
            }

            .history-name {
                overflow: hidden;
                color: #0f172a;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .history-kind {
                border-radius: 999px;
                background: var(--primary-soft);
                padding: 4px 8px;
                color: var(--primary);
                white-space: nowrap;
            }

            .empty-note {
                border: 1px dashed #cbd5e1;
                border-radius: 8px;
                padding: 14px;
                color: var(--muted);
                font-size: .9rem;
                font-weight: 850;
                text-align: center;
            }

            .warning-note {
                border: 1px solid rgba(241, 196, 15, .8);
                border-radius: 8px;
                background: var(--accent-soft);
                padding: 12px;
                color: #5f4700;
                font-weight: 850;
            }

            @media (max-width: 1100px) {
                .office-scan-page {
                    background: #eef4fa;
                }

                .office-shell,
                .work-grid {
                    grid-template-columns: 1fr;
                }

                .office-side {
                    background: var(--primary);
                }

                .clock-panel {
                    grid-template-columns: minmax(0, 1fr) auto;
                    align-items: end;
                }
            }

            @media (max-width: 720px) {
                .office-side,
                .office-main {
                    padding: 14px;
                }

                .top-strip,
                .schedule-item-head,
                .clock-panel {
                    align-items: stretch;
                    grid-template-columns: 1fr;
                    flex-direction: column;
                }

                .queue-badge {
                    width: 100%;
                }

                .result-card,
                .schedule-time-grid {
                    grid-template-columns: 1fr;
                }

                .employee-photo {
                    width: min(158px, 44vw);
                    justify-self: center;
                }

                .history-item {
                    grid-template-columns: 64px minmax(0, 1fr);
                }

                .history-kind {
                    grid-column: 1 / -1;
                    justify-self: start;
                }
            }
        </style>
    </head>
    <body>
        <div class="office-scan-page">
            <div class="office-shell">
                <aside class="office-side">
                    <div class="brand">
                        <span class="brand-mark">
                            <img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
                        </span>
                        <span>
                            <span class="brand-title">NUSA Pegawai</span>
                            <span class="brand-subtitle">SMP Negeri 2 Padang Panjang</span>
                        </span>
                    </div>

                    <div class="clock-panel">
                        <div>
                            <p class="label">Waktu server</p>
                            <p class="clock" id="clockText">--:--:--</p>
                            <p class="date-line">{{ $hariLabel }}, {{ $tanggalHariIni }}</p>
                        </div>
                        <div class="mode-panel">
                            <p class="label">Mode aktif</p>
                            <p class="mode-value" id="modeText">Memuat</p>
                            <p class="status-line">Status: <span id="statusText">Siap</span></p>
                        </div>
                    </div>
                </aside>

                <main class="office-main">
                    <div class="top-strip">
                        <h1 class="page-title">Scan Absensi Pegawai</h1>
                        <span class="queue-badge">Antrean: <span id="queueCount">0</span></span>
                    </div>

                    <div class="work-grid">
                        <section class="scanner-panel" aria-label="Scanner absensi pegawai">
                            <div>
                                <label class="scan-label" for="scanInput">Area scan aktif</label>
                                <input
                                    id="scanInput"
                                    class="scan-capture"
                                    type="text"
                                    name="isi_scan"
                                    autocomplete="off"
                                    autofocus
                                    placeholder="Scan kartu pegawai"
                                >
                            </div>

                            <div class="result-card" id="resultCard">
                                <div class="employee-photo" id="employeePhoto">P</div>
                                <div>
                                    <span class="result-status" id="resultStatus">Siap scan</span>
                                    <h2 class="result-name" id="resultName">Tempelkan kartu pegawai</h2>
                                    <p class="result-nip" id="resultNip">NIP akan tampil setelah scan berhasil</p>
                                    <p class="result-message" id="resultMessage">Scanner kantor siap menerima data.</p>
                                    <div class="result-meta" id="resultMeta"></div>
                                </div>
                            </div>

                            <div class="history-list" id="historyList">
                                <div class="empty-note" id="emptyHistory">Belum ada scan pada sesi ini.</div>
                            </div>
                        </section>

                        <section class="schedule-panel" aria-label="Jadwal absensi pegawai hari ini">
                            <div class="panel-head">
                                <h2 class="panel-title">Jadwal Hari Ini</h2>
                                <span class="queue-badge">{{ $jadwalHariIni->count() }} aktif</span>
                            </div>

                            @if ($jadwalHariIni->isEmpty())
                                <div class="warning-note">
                                    Belum ada jadwal absensi pegawai aktif untuk hari ini.
                                </div>
                            @else
                                <div class="schedule-list" id="scheduleList">
                                    @foreach ($jadwalHariIni as $jadwal)
                                        <article
                                            class="schedule-item"
                                            data-masuk-mulai="{{ $jadwal->formatJam($jadwal->jam_scan_masuk_mulai) }}"
                                            data-masuk-selesai="{{ $jadwal->formatJam($jadwal->jam_scan_masuk_selesai) }}"
                                            data-pulang-mulai="{{ $jadwal->formatJam($jadwal->jam_scan_pulang_mulai) }}"
                                            data-pulang-selesai="{{ $jadwal->formatJam($jadwal->jam_scan_pulang_selesai) }}"
                                        >
                                            <div class="schedule-item-head">
                                                <div>
                                                    <p class="schedule-name">{{ $jadwal->nama_jadwal }}</p>
                                                    <p class="schedule-target">{{ $jadwal->labelCakupan() }} · {{ $jadwal->labelSasaran() }}</p>
                                                </div>
                                                <span class="schedule-mode">Menunggu</span>
                                            </div>
                                            <div class="schedule-time-grid">
                                                <div class="schedule-time">
                                                    <span>Masuk</span>
                                                    <strong>{{ $jadwal->formatJam($jadwal->jam_scan_masuk_mulai) }} - {{ $jadwal->formatJam($jadwal->jam_scan_masuk_selesai) }}</strong>
                                                    <span>Resmi {{ $jadwal->formatJam($jadwal->jam_masuk) }}</span>
                                                </div>
                                                <div class="schedule-time">
                                                    <span>Pulang</span>
                                                    <strong>{{ $jadwal->formatJam($jadwal->jam_scan_pulang_mulai) }} - {{ $jadwal->formatJam($jadwal->jam_scan_pulang_selesai) }}</strong>
                                                    <span>Resmi {{ $jadwal->formatJam($jadwal->jam_pulang) }}</span>
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    </div>
                </main>
            </div>
        </div>

        <script>
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const jadwalHariIni = @json($jadwalJson);
            const serverAwal = new Date(@json(now()->toIso8601String())).getTime();
            const clientAwal = Date.now();
            const scanQueue = [];
            const riwayat = [];
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
                resultNip: document.getElementById('resultNip'),
                resultMessage: document.getElementById('resultMessage'),
                resultMeta: document.getElementById('resultMeta'),
                employeePhoto: document.getElementById('employeePhoto'),
                historyList: document.getElementById('historyList'),
                emptyHistory: document.getElementById('emptyHistory'),
                scheduleItems: Array.from(document.querySelectorAll('.schedule-item')),
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
                if (jadwalHariIni.length === 0) {
                    return { label: 'Belum ada jadwal', aktif: null };
                }

                const bagian = bagianWaktuJakarta(tanggal);
                const menit = (bagian.hour * 60) + bagian.minute;
                const adaMasuk = jadwalHariIni.some((jadwal) => menit >= menitDariJam(jadwal.jam_scan_masuk_mulai) && menit <= menitDariJam(jadwal.jam_scan_masuk_selesai));
                const adaPulang = jadwalHariIni.some((jadwal) => menit >= menitDariJam(jadwal.jam_scan_pulang_mulai) && menit <= menitDariJam(jadwal.jam_scan_pulang_selesai));

                if (adaMasuk && adaPulang) {
                    return { label: 'Masuk & Pulang', aktif: 'campuran' };
                }

                if (adaMasuk) {
                    return { label: 'Masuk aktif', aktif: 'masuk' };
                }

                if (adaPulang) {
                    return { label: 'Pulang aktif', aktif: 'pulang' };
                }

                return { label: 'Di luar jadwal', aktif: null };
            }

            function perbaruiJam() {
                const sekarang = waktuServer();
                const mode = modeSaatIni(sekarang);
                const bagian = bagianWaktuJakarta(sekarang);
                const menit = (bagian.hour * 60) + bagian.minute;

                elements.clockText.textContent = jamDariTanggal(sekarang);
                elements.modeText.textContent = mode.label;

                elements.scheduleItems.forEach((item) => {
                    const masukAktif = menit >= menitDariJam(item.dataset.masukMulai) && menit <= menitDariJam(item.dataset.masukSelesai);
                    const pulangAktif = menit >= menitDariJam(item.dataset.pulangMulai) && menit <= menitDariJam(item.dataset.pulangSelesai);
                    const aktif = masukAktif || pulangAktif;
                    const label = item.querySelector('.schedule-mode');

                    item.classList.toggle('is-active', aktif);
                    label.textContent = masukAktif && pulangAktif
                        ? 'Masuk/Pulang'
                        : (masukAktif ? 'Masuk aktif' : (pulangAktif ? 'Pulang aktif' : 'Menunggu'));
                });
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
                    const response = await fetch(@json(route('scan-absensi-pegawai.store')), {
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
                            pesan: 'Server belum mengembalikan data scan pegawai.',
                            status: 'server_error',
                            pegawai: null,
                        };

                    tampilkanHasil(payload);
                    tambahRiwayat(payload);
                } catch (error) {
                    const payload = {
                        berhasil: false,
                        status: 'koneksi_gagal',
                        pesan: 'Gagal menghubungi server. Periksa koneksi aplikasi NUSA.',
                        pegawai: null,
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

            function tampilkanHasil(payload) {
                elements.resultCard.classList.toggle('is-success', Boolean(payload.berhasil));
                elements.resultCard.classList.toggle('is-error', ! payload.berhasil);
                elements.resultStatus.textContent = payload.berhasil ? 'Scan berhasil' : 'Scan belum diterima';
                elements.resultName.textContent = payload.pegawai?.nama_lengkap || 'Data pegawai belum ditemukan';
                elements.resultNip.textContent = payload.pegawai?.nip
                    ? `NIP ${payload.pegawai.nip}`
                    : (payload.nip ? `NIP ${payload.nip}` : 'NIP tidak tersedia');
                elements.resultMessage.textContent = payload.pesan || 'Scan selesai diproses.';
                tampilkanFoto(payload.pegawai);
                tampilkanMeta(payload);
            }

            function tampilkanFoto(pegawai) {
                elements.employeePhoto.innerHTML = '';

                if (pegawai?.foto_url) {
                    const img = document.createElement('img');
                    img.src = pegawai.foto_url;
                    img.alt = pegawai.nama_lengkap || 'Foto pegawai';
                    elements.employeePhoto.appendChild(img);
                    return;
                }

                elements.employeePhoto.textContent = pegawai?.inisial || 'P';
            }

            function tampilkanMeta(payload) {
                const meta = [];

                if (payload.pegawai?.jabatan) {
                    meta.push(payload.pegawai.jabatan);
                }

                if (payload.jenis_scan) {
                    meta.push(`Jenis: ${kapital(payload.jenis_scan)}`);
                }

                if (payload.waktu_server) {
                    meta.push(`Waktu: ${payload.waktu_server}`);
                }

                if (payload.scanner_id) {
                    meta.push(`Scanner: ${payload.scanner_id}`);
                }

                if (payload.jadwal?.nama_jadwal) {
                    meta.push(payload.jadwal.nama_jadwal);
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
                riwayat.unshift({
                    waktu: payload.waktu_server || jamDariTanggal(waktuServer()),
                    nama: payload.pegawai?.nama_lengkap || payload.pesan || 'Scan tidak diterima',
                    jenis: payload.jenis_scan ? kapital(payload.jenis_scan) : (payload.berhasil ? 'Scan' : 'Gagal'),
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
