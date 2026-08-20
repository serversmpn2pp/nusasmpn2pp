<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Scan Berhalangan Ibadah - NUSA</title>
    <link rel="icon" href="{{ asset('images/logo-nusa.png') }}" type="image/png">
    @vite('resources/js/scan-kegiatan-ibadah.js')
    <style>
        :root { color-scheme:light; --blue:#15477a; --blue-dark:#0c3157; --blue-soft:#e9f2fa; --yellow:#f1c40f; --ink:#102033; --muted:#607083; --line:#d7e1eb; --surface:#fff; --green:#15803d; --green-soft:#edf9f1; --red:#b42318; --red-soft:#fff0ef; --teal:#0f766e; --teal-soft:#e8f8f6; --shadow:0 12px 30px rgba(21,71,122,.10); }
        * { box-sizing:border-box; }
        html,body { margin:0; min-height:100%; }
        body { background:#f3f7fb; color:var(--ink); font-family:"Instrument Sans",Inter,Arial,sans-serif; font-size:15px; letter-spacing:0; }
        button,input,select { font:inherit; letter-spacing:0; }
        button,a { -webkit-tap-highlight-color:transparent; }
        .app-header { position:sticky; top:0; z-index:20; display:flex; align-items:center; justify-content:space-between; gap:14px; min-height:72px; padding:11px max(18px,env(safe-area-inset-right)) 11px max(18px,env(safe-area-inset-left)); border-bottom:1px solid var(--line); background:rgba(255,255,255,.96); }
        .brand { display:flex; align-items:center; gap:11px; min-width:0; color:var(--ink); text-decoration:none; }
        .brand img { width:46px; height:46px; padding:4px; border:1px solid #f1d44d; border-radius:8px; background:#fff; object-fit:contain; }
        .brand strong,.brand span { display:block; }.brand strong { font-size:1.02rem; }.brand span { color:var(--muted); font-size:.77rem; }
        .private-badge { flex:0 0 auto; padding:8px 11px; border:1px solid #9ddbd4; border-radius:999px; background:var(--teal-soft); color:var(--teal); font-size:.74rem; font-weight:900; }
        .page { width:min(1120px,100%); margin:0 auto; padding:24px 20px calc(34px + env(safe-area-inset-bottom)); }
        .page-head { display:flex; align-items:flex-end; justify-content:space-between; gap:18px; margin-bottom:18px; }
        .eyebrow { margin:0 0 5px; color:var(--teal); font-size:.78rem; font-weight:850; }.page-head h1 { margin:0; font-size:clamp(1.55rem,3vw,2.1rem); line-height:1.12; }.page-head p:last-child { max-width:680px; margin:8px 0 0; color:var(--muted); line-height:1.5; }
        .back-link { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:10px 15px; border:1px solid var(--line); border-radius:8px; background:#fff; color:var(--ink); font-weight:800; text-decoration:none; }
        .schedule-strip { display:grid; grid-template-columns:minmax(0,1.5fr) repeat(3,minmax(130px,.7fr)); gap:1px; overflow:hidden; margin-bottom:18px; border:1px solid var(--line); border-radius:8px; background:var(--line); box-shadow:var(--shadow); }
        .schedule-block { min-width:0; padding:15px 17px; background:#fff; }.schedule-block span,.schedule-block strong { display:block; }.schedule-block span { color:var(--muted); font-size:.75rem; font-weight:750; }.schedule-block strong { margin-top:5px; overflow-wrap:anywhere; font-size:1rem; }.schedule-block.highlight { background:#fff8d6; }.schedule-block.highlight strong { color:var(--blue-dark); font-size:1.12rem; }
        .schedule-select { width:100%; margin-top:7px; padding:9px 34px 9px 10px; border:1px solid var(--line); border-radius:7px; background:#fff; color:var(--ink); font-weight:800; }
        .scan-layout { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(300px,.8fr); gap:18px; align-items:start; }
        .panel { overflow:hidden; border:1px solid var(--line); border-radius:8px; background:#fff; box-shadow:var(--shadow); }
        .panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:15px 17px; border-bottom:1px solid var(--line); }.panel-head h2 { margin:0; font-size:1rem; }.panel-head p { margin:4px 0 0; color:var(--muted); font-size:.78rem; }
        .status-pill { flex:0 0 auto; padding:7px 10px; border-radius:999px; font-size:.75rem; font-weight:900; }.status-pill.aktif { background:var(--teal-soft); color:var(--teal); }.status-pill.belum,.status-pill.selesai,.status-pill.tidak_ada { background:#f3f4f6; color:#5d6672; }
        .camera-wrap { position:relative; aspect-ratio:16/10; min-height:330px; overflow:hidden; background:#071525; }
        .camera-wrap video { display:none; width:100%; height:100%; object-fit:cover; }.camera-wrap.camera-on video { display:block; }
        .camera-placeholder { position:absolute; inset:0; display:grid; place-content:center; gap:8px; padding:28px; color:#dbe8f4; text-align:center; }.camera-placeholder strong { color:#fff; font-size:1.15rem; }.camera-placeholder p { max-width:430px; margin:0; color:#a9bdd0; line-height:1.5; }.camera-wrap.camera-on .camera-placeholder { display:none; }
        .scan-frame { position:absolute; left:50%; top:50%; display:none; width:min(54%,260px); aspect-ratio:1; transform:translate(-50%,-50%); border:2px solid rgba(255,255,255,.9); border-radius:8px; box-shadow:0 0 0 999px rgba(2,12,24,.30); }.camera-wrap.camera-on .scan-frame { display:block; }.scan-frame::before,.scan-frame::after { position:absolute; content:""; width:38px; height:38px; }.scan-frame::before { left:-3px; top:-3px; border-left:6px solid var(--yellow); border-top:6px solid var(--yellow); }.scan-frame::after { right:-3px; bottom:-3px; border-right:6px solid var(--yellow); border-bottom:6px solid var(--yellow); }
        .camera-message { position:absolute; left:50%; bottom:15px; display:none; width:max-content; max-width:calc(100% - 28px); transform:translateX(-50%); padding:9px 13px; border-radius:999px; background:rgba(255,255,255,.94); color:var(--blue-dark); text-align:center; font-size:.78rem; font-weight:900; }.camera-wrap.camera-on .camera-message { display:block; }
        .camera-controls { display:flex; flex-wrap:wrap; gap:9px; padding:14px 16px; border-top:1px solid var(--line); }.button { min-height:43px; padding:10px 15px; border:1px solid transparent; border-radius:8px; cursor:pointer; font-weight:900; }.button:disabled { cursor:not-allowed; opacity:.48; }.button-primary { background:var(--blue); color:#fff; }.button-secondary { border-color:var(--line); background:#fff; color:var(--ink); }.button-danger { border-color:#f5b8b3; background:var(--red-soft); color:var(--red); }.button-grow { flex:1 1 180px; }
        .camera-warning { margin:0 16px 16px; padding:11px 13px; border:1px solid #f2d374; border-radius:8px; background:#fff8d6; color:#715d09; line-height:1.45; }.camera-warning[hidden] { display:none; }
        .result { display:none; padding:16px; border:1px solid var(--line); border-left:5px solid var(--blue); border-radius:8px; background:#fff; box-shadow:var(--shadow); }.result.show { display:block; }.result.success { border-left-color:var(--teal); background:var(--teal-soft); }.result.known { border-left-color:var(--blue); background:var(--blue-soft); }.result.error { border-left-color:var(--red); background:var(--red-soft); }
        .result-grid { display:grid; grid-template-columns:82px minmax(0,1fr); gap:14px; align-items:center; }.result-photo { width:82px; height:98px; border:3px solid #fff; border-radius:8px; background:#d8e1ea; object-fit:cover; box-shadow:0 5px 16px rgba(16,32,51,.12); }.result-kicker { margin:0 0 5px; color:var(--teal); font-size:.75rem; font-weight:900; text-transform:uppercase; }.result.known .result-kicker { color:var(--blue); }.result.error .result-kicker { color:var(--red); }.result h3 { margin:0; font-size:1.18rem; line-height:1.18; }.result-meta { display:flex; flex-wrap:wrap; gap:6px; margin-top:9px; }.result-meta span { padding:5px 8px; border-radius:999px; background:rgba(255,255,255,.82); color:#405064; font-size:.74rem; font-weight:800; }.result-text { margin:8px 0 0; color:#405064; line-height:1.45; }
        .side-stack { display:grid; gap:18px; }.summary { padding:18px; }.summary-label { margin:0; color:var(--muted); font-size:.78rem; font-weight:800; }.summary-number { display:block; margin-top:4px; color:var(--blue-dark); font-size:2rem; font-weight:900; }.privacy-box { padding:18px; border-top:4px solid var(--teal); }.privacy-box h2 { margin:0; font-size:1rem; }.privacy-box ul { margin:12px 0 0; padding-left:19px; color:#405064; line-height:1.65; }
        .manual { margin-top:18px; }.manual summary { cursor:pointer; padding:14px 16px; font-weight:900; }.manual-form { display:flex; gap:8px; padding:0 16px 16px; }.manual-form input { min-width:0; flex:1; padding:11px 12px; border:1px solid var(--line); border-radius:8px; }.manual-help { margin:0; padding:0 16px 12px; color:var(--muted); font-size:.77rem; }
        @media(max-width:820px) { .page { padding:18px 14px calc(30px + env(safe-area-inset-bottom)); }.page-head { align-items:flex-start; }.scan-layout { grid-template-columns:1fr; }.schedule-strip { grid-template-columns:1fr 1fr; }.schedule-block:first-child { grid-column:1/-1; }.camera-wrap { aspect-ratio:4/5; min-height:430px; }.side-stack { display:flex; flex-direction:column; }.result { order:-1; } }
        @media(max-width:500px) { .app-header { min-height:64px; }.brand img { width:40px; height:40px; }.brand span span { display:none; }.page-head { display:block; }.back-link { width:100%; margin-top:14px; }.schedule-strip { grid-template-columns:1fr 1fr; }.schedule-block { padding:13px; }.schedule-block:first-child,.schedule-block.highlight { grid-column:1/-1; }.camera-wrap { min-height:0; aspect-ratio:3/4; }.camera-controls .button { flex:1 1 calc(50% - 9px); }.camera-controls .button-grow { flex-basis:100%; }.result-grid { grid-template-columns:70px minmax(0,1fr); }.result-photo { width:70px; height:86px; }.manual-form { flex-direction:column; }.manual-form .button { width:100%; } }
    </style>
</head>
<body>
    <header class="app-header">
        <a class="brand" href="{{ route('beranda') }}"><img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA"><span><strong>NUSA Ibadah</strong><span>SMP Negeri 2 Padang Panjang</span></span></a>
        <span class="private-badge">MODE PRIVAT</span>
    </header>

    <main
        id="scan-worship-app"
        class="page"
        data-endpoint="{{ route('scan-berhalangan-ibadah.store') }}"
        data-schedule-id="{{ $jadwalDipilih?->id }}"
        data-scan-active="{{ $scanDibuka ? '1' : '0' }}"
        data-server-time="{{ $waktuServerIso }}"
        data-fallback-photo="{{ asset('images/kartu-pelajar/default-user.png') }}"
        data-label-new="Berhalangan tercatat"
        data-label-known="Sudah tercatat"
        data-label-error="Belum dapat dicatat"
        data-ready-message="Siap memindai siswi berikutnya."
    >
        <div class="page-head">
            <div><p class="eyebrow">Pendamping Ibadah Siswi</p><h1>Scan Berhalangan Ibadah</h1><p>Halaman khusus petugas pendamping. Catat status secara tenang dan jangan menampilkan informasi ini kepada siswa lain.</p></div>
            <a href="{{ route('beranda') }}" class="back-link">Kembali ke NUSA</a>
        </div>

        <section class="schedule-strip" aria-label="Informasi jadwal">
            <div class="schedule-block">
                <span>Kegiatan hari ini</span>
                @if($daftarJadwal->count() > 1)
                    <form method="GET"><select class="schedule-select" name="jadwal_id" onchange="this.form.submit()">@foreach($daftarJadwal as $jadwal)<option value="{{ $jadwal->id }}" @selected($jadwalDipilih?->id === $jadwal->id)>{{ $jadwal->kegiatanIbadah?->nama }}</option>@endforeach</select></form>
                @else
                    <strong>{{ $jadwalDipilih?->kegiatanIbadah?->nama ?? 'Belum ada jadwal' }}</strong>
                @endif
            </div>
            <div class="schedule-block highlight"><span>Waktu pelaksanaan</span><strong>{{ $jadwalDipilih?->formatJam($jadwalDipilih?->jam_pelaksanaan) ?? '-' }}</strong></div>
            <div class="schedule-block"><span>Waktu scan</span><strong>{{ $jadwalDipilih?->rentangScan() ?? '-' }}</strong></div>
            <div class="schedule-block"><span>Tanggal</span><strong>{{ $tanggalLabel }}</strong></div>
        </section>

        <div class="scan-layout">
            <div>
                <section class="panel">
                    <header class="panel-head"><div><h2>Kamera Pemindai</h2><p id="camera-status-text">{{ $statusJadwal['pesan'] }}</p></div><span class="status-pill {{ $statusJadwal['kode'] }}">{{ $statusJadwal['label'] }}</span></header>
                    <div id="camera-wrap" class="camera-wrap">
                        <video id="camera-video" autoplay muted playsinline></video><canvas id="camera-canvas" hidden></canvas>
                        <div class="camera-placeholder"><strong>{{ $scanDibuka ? 'Kamera belum dinyalakan' : $statusJadwal['label'] }}</strong><p>{{ $scanDibuka ? 'Tekan Mulai kamera, lalu izinkan NUSA menggunakan kamera belakang HP.' : $statusJadwal['pesan'] }}</p></div>
                        <div class="scan-frame" aria-hidden="true"></div><div class="camera-message">Posisikan QR di dalam kotak</div>
                    </div>
                    <div class="camera-controls"><button id="start-camera" class="button button-primary button-grow" type="button" @disabled(!$scanDibuka)>Mulai kamera</button><button id="switch-camera" class="button button-secondary" type="button" disabled>Ganti kamera</button><button id="stop-camera" class="button button-danger" type="button" disabled>Hentikan</button></div>
                    <p id="camera-warning" class="camera-warning" @if($scanDibuka) hidden @endif>{{ $statusJadwal['pesan'] }}</p>
                </section>

                <details class="panel manual">
                    <summary>Gunakan NISN jika kamera bermasalah</summary>
                    <p class="manual-help">Pilihan cadangan khusus petugas pendamping. Pastikan identitas siswi sesuai sebelum mencatat.</p>
                    <form id="manual-form" class="manual-form"><input id="manual-nisn" name="nisn" inputmode="numeric" autocomplete="off" placeholder="Masukkan NISN" @disabled(!$scanDibuka)><button class="button button-primary" type="submit" @disabled(!$scanDibuka)>Catat berhalangan</button></form>
                </details>
            </div>

            <div class="side-stack">
                <section id="scan-result" class="result" aria-live="polite">
                    <div class="result-grid"><img id="result-photo" class="result-photo" src="{{ asset('images/kartu-pelajar/default-user.png') }}" alt="Foto siswi"><div><p id="result-kicker" class="result-kicker">Hasil scan</p><h3 id="result-name">Menunggu QR</h3><div id="result-meta" class="result-meta"></div><p id="result-text" class="result-text"></p></div></div>
                </section>

                <section class="panel summary"><p class="summary-label">Jumlah tercatat hari ini</p><strong id="total-today" class="summary-number">{{ $jumlahHariIni }}</strong><p class="summary-label">Nama pemindai sebelumnya sengaja tidak ditampilkan untuk menjaga privasi.</p></section>
                <section class="panel privacy-box"><h2>Jaga kenyamanan siswi</h2><ul><li>Pastikan proses scan tidak disaksikan siswa lain.</li><li>Tidak meminta bukti pribadi atau melakukan pemeriksaan fisik.</li><li>Gunakan percakapan privat hanya saat tindak lanjut diperlukan.</li></ul></section>
            </div>
        </div>
    </main>
</body>
</html>
