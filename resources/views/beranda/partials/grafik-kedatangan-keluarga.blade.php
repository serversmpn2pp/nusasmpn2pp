<section class="ph-section" aria-labelledby="fh-chart-title" data-grafik-kedatangan>
    <header class="ph-section-head">
        <h2 id="fh-chart-title"><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i>Riwayat kedatangan</h2>
        @if ($modeOrangTua)<a class="ph-link" href="{{ route('presensi-anak.index') }}">Rincian<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>@endif
    </header>
    <div class="ph-chart-body">
        <div class="ph-chart-toolbar">
            <div class="ph-segment" role="group" aria-label="Rentang riwayat kedatangan"><button type="button" data-chart-range="minggu" aria-pressed="true">7 hari</button><button type="button" data-chart-range="bulan" aria-pressed="false">Bulan ini</button></div>
            <div class="ph-segment ph-chart-modes" role="group" aria-label="Jenis grafik"><button type="button" data-chart-type="bar" aria-pressed="true" title="Grafik batang" aria-label="Grafik batang"><i data-lucide="chart-column" aria-hidden="true"></i></button><button type="button" data-chart-type="line" aria-pressed="false" title="Grafik garis" aria-label="Grafik garis"><i data-lucide="chart-line" aria-hidden="true"></i></button></div>
        </div>
        <p class="ph-chart-summary" data-chart-summary aria-live="polite"></p>
        <div class="ph-canvas"><canvas data-chart-canvas role="img" aria-label="Grafik keterlambatan {{ $modeOrangTua ? 'anak' : 'siswa' }} dalam menit"></canvas><div class="ph-chart-empty" data-chart-empty hidden>Belum ada scan kedatangan pada rentang ini.</div></div>
        <div class="ph-chart-legend"><span><b class="ph-dot teal"></b>Tepat waktu</span><span><b class="ph-dot amber"></b>Terlambat</span><span>Tanpa catatan tidak dihitung hadir</span></div>
        <details class="ph-chart-data"><summary>Data kedatangan</summary><ul data-chart-table></ul></details>
        <noscript><p class="ph-muted">Grafik memerlukan JavaScript. Ringkasan presensi tetap tersedia di halaman ini.</p></noscript>
    </div>
    <script type="application/json" data-chart-source>@json($grafikKedatanganSiswa)</script>
</section>
