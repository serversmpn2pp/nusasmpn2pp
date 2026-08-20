<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Rekap Berhalangan {{ $bulanLabel }} - NUSA</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#edf2f7;color:#15243a;font-family:Arial,sans-serif;font-size:11px}.toolbar{position:sticky;top:0;z-index:5;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 20px;background:#15477a;color:#fff}.toolbar .actions{display:flex;gap:8px}.button{border:0;border-radius:5px;padding:9px 13px;background:#f1c40f;color:#173451;font-weight:700;cursor:pointer;text-decoration:none}.button.secondary{background:#fff}.sheet{width:297mm;min-height:210mm;margin:16px auto;padding:12mm;background:#fff;box-shadow:0 10px 28px rgba(21,71,122,.15)}.header{display:grid;grid-template-columns:55px 1fr auto;gap:12px;align-items:center;padding-bottom:10px;border-bottom:3px solid #15477a}.header img{width:50px;height:50px;object-fit:contain}.header h1{margin:0;color:#15477a;font-size:19px}.header p{margin:4px 0 0}.confidential{padding:8px 11px;border:1px solid #d6b100;background:#fff8d6;color:#5f4900;font-weight:700}.meta,.summary{display:grid;gap:8px;margin-top:12px}.meta{grid-template-columns:repeat(4,1fr)}.summary{grid-template-columns:repeat(5,1fr)}.meta div,.summary div{padding:8px;border:1px solid #cfdae5;border-radius:4px}.meta span,.summary span{display:block;color:#607087;font-size:9px}.meta strong,.summary strong{display:block;margin-top:3px}.summary strong{color:#15477a;font-size:16px}table{width:100%;margin-top:13px;border-collapse:collapse}th,td{padding:7px;border:1px solid #aebdcb;text-align:left;vertical-align:top}th{background:#15477a;color:#fff;font-size:9px;text-transform:uppercase}td.center,th.center{text-align:center}.muted{color:#66758a;font-size:9px}.status{font-weight:700}.foot{display:flex;justify-content:space-between;gap:20px;margin-top:12px;color:#68778b;font-size:9px}@media print{@page{size:A4 landscape;margin:8mm}body{background:#fff}.toolbar{display:none}.sheet{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}thead{display:table-header-group}tr{break-inside:avoid}.confidential,.summary{break-inside:avoid}}
    </style>
</head>
<body>
    <div class="toolbar"><strong>Pratinjau rekap privat</strong><div class="actions"><a href="{{ route('rekap-berhalangan-ibadah.index', request()->query()) }}" class="button secondary">Kembali</a><button type="button" class="button" onclick="window.print()">Cetak / Simpan PDF</button></div></div>
    <main class="sheet">
        <header class="header">
            <img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
            <div><h1>Rekap Privat Berhalangan Ibadah</h1><p>NUSA · SMP Negeri 2 Padang Panjang</p></div>
            <div class="confidential">DOKUMEN INTERNAL · PRIVAT</div>
        </header>

        <section class="meta">
            <div><span>Tahun pelajaran</span><strong>{{ $tahunPelajaran->nama }}</strong></div>
            <div><span>Periode laporan</span><strong>{{ $bulanLabel }}</strong></div>
            <div><span>Kelas</span><strong>{{ $kelasDipilih?->nama ?? 'Semua kelas dalam cakupan' }}</strong></div>
            <div><span>Status</span><strong>{{ $daftarStatus[$status] ?? 'Semua status' }}</strong></div>
        </section>
        <section class="summary">
            <div><span>Periode</span><strong>{{ $ringkasan['periode'] }}</strong></div>
            <div><span>Siswi</span><strong>{{ $ringkasan['siswi'] }}</strong></div>
            <div><span>Dipantau</span><strong>{{ $ringkasan['aktif'] }}</strong></div>
            <div><span>Perlu konfirmasi</span><strong>{{ $ringkasan['perlu_konfirmasi'] }}</strong></div>
            <div><span>Selesai</span><strong>{{ $ringkasan['selesai'] }}</strong></div>
        </section>

        <table>
            <thead><tr><th class="center">No.</th><th>Siswi</th><th>Kelas</th><th>Mulai</th><th>Selesai</th><th class="center">Durasi</th><th class="center">Scan bulan ini</th><th>Konfirmasi terakhir</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($daftarPeriode as $periode)
                    @php
                        $akhirDurasi = $periode->tanggal_selesai ?: now();
                        $durasi = $periode->tanggal_mulai->copy()->startOfDay()->diffInDays($akhirDurasi->copy()->startOfDay()) + 1;
                        $label = match ($periode->status) { 'aktif' => 'Sedang dipantau', 'perlu_konfirmasi' => 'Perlu konfirmasi', default => 'Selesai' };
                    @endphp
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td><strong>{{ $periode->siswa?->nama_lengkap ?? '-' }}</strong><div class="muted">NISN {{ $periode->siswa?->nisn ?? '-' }}</div></td>
                        <td>{{ $periode->kelas?->nama ?? '-' }}</td>
                        <td>{{ $periode->tanggal_mulai->format('d/m/Y') }}</td>
                        <td>{{ $periode->tanggal_selesai?->format('d/m/Y') ?? '-' }}</td>
                        <td class="center">{{ $durasi }} hari</td>
                        <td class="center">{{ $periode->presensi_bulan_count }} hari</td>
                        <td>@if($periode->konfirmasiTerakhir){{ $periode->konfirmasiTerakhir->dikonfirmasi_pada->format('d/m/Y') }}<div class="muted">{{ $hasilKonfirmasi[$periode->konfirmasiTerakhir->hasil] ?? '-' }}</div>@else-@endif</td>
                        <td class="status">{{ $label }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="center">Belum ada periode berhalangan pada pilihan ini.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="foot"><span>Catatan percakapan privat tidak dicantumkan dalam rekap ini.</span><span>Dicetak {{ $tanggalCetak }}</span></div>
    </main>
</body>
</html>
