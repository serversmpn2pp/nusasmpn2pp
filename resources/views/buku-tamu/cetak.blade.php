<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Laporan Buku Tamu - {{ $filter['label_periode'] }}</title>
<style>
    @page { size:A4 landscape; margin:12mm; }
    * { box-sizing:border-box; }
    body { margin:0; background:#eef2f5; color:#192e3b; font-family:Arial,sans-serif; font-size:10px; }
    .toolbar { display:flex; align-items:center; gap:12px; padding:16px 24px; background:#fff; border-bottom:1px solid #d0dae2; font-size:13px; }
    button { border:1px solid #184a6f; border-radius:5px; background:#184a6f; color:#fff; padding:10px 18px; font:inherit; cursor:pointer; }
    .report { width:273mm; margin:24px auto; padding:12mm; background:#fff; }
    header { display:grid; grid-template-columns:45px 1fr 45px; align-items:center; gap:20px; border-bottom:1px solid #184a6f; padding-bottom:14px; text-align:center; }
    header img { width:45px; height:55px; object-fit:contain; }
    h1 { font-size:16px; margin:0 0 6px; }
    h2 { font-size:12px; margin:0 0 5px; }
    p { margin:6px 0; }
    .meta { display:flex; flex-wrap:wrap; gap:8px 24px; padding:14px 0; }
    table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:9px; }
    th,td { border:1px solid #b7c6d1; padding:6px; vertical-align:top; overflow-wrap:anywhere; }
    th { text-align:left; background:#eff4f7; }
    small { display:block; margin-top:4px; color:#526774; font-size:8px; }
    thead { display:table-header-group; }
    tr { break-inside:avoid; }
    .foot { border-top:1px solid #d0dae2; margin-top:16px; padding-top:10px; font-size:8px; color:#526774; }
    @media screen and (max-width:1100px) { .report { width:100%; margin:0; padding:20px; } .toolbar { flex-wrap:wrap; } }
    @media print { body { background:#fff; } .toolbar { display:none; } .report { width:100%; margin:0; padding:0; } a { color:inherit; text-decoration:none; } }
</style></head><body>
<div class="toolbar"><button type="button" onclick="window.print()">Cetak / Simpan PDF</button><span>{{ $filter['label_periode'] }}</span></div>
<main class="report"><header><img src="{{ asset('images/logo-padang-panjang.png') }}" alt="Logo Kota Padang Panjang"><div><h1>SMP NEGERI 2 PADANG PANJANG</h1><h2>LAPORAN BUKU TAMU</h2><p>{{ $filter['label_periode'] }}</p></div><img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo sekolah"></header>
    <div class="meta"><span>Periode: <strong>{{ \Illuminate\Support\Carbon::parse($filter['dari'])->format('d-m-Y') }} s.d. {{ \Illuminate\Support\Carbon::parse($filter['sampai'])->format('d-m-Y') }}</strong></span><span>Total: <strong>{{ $ringkasan['total'] }}</strong></span><span>Masih berkunjung: <strong>{{ $ringkasan['berkunjung'] }}</strong></span><span>Sudah pulang: <strong>{{ $ringkasan['selesai'] }}</strong></span><span>Dibatalkan: <strong>{{ $ringkasan['dibatalkan'] }}</strong></span></div>
    @if (!empty($filter['kategori']) || !empty($filter['status']) || !empty($filter['kata_kunci']))<p>Filter: {{ !empty($filter['kategori']) ? \App\Models\KunjunganTamu::KATEGORI[$filter['kategori']].' | ' : '' }}{{ !empty($filter['status']) ? \App\Models\KunjunganTamu::STATUS[$filter['status']].' | ' : '' }}{{ $filter['kata_kunci'] ?? '' }}</p>@endif
    <table><colgroup><col style="width:4%"><col style="width:20%"><col style="width:14%"><col style="width:27%"><col style="width:14%"><col style="width:11%"><col style="width:10%"></colgroup><thead><tr><th>No.</th><th>Tamu / instansi</th><th>Kategori</th><th>Keperluan / tujuan</th><th>Datang (WIB)</th><th>Pulang (WIB)</th><th>Status</th></tr></thead><tbody>
    @forelse ($kunjungan as $item)<tr><td>{{ $loop->iteration }}</td><td><strong>{{ $item->nama_tamu }}</strong><small>{{ $item->instansi ?: 'Perorangan' }}</small></td><td>{{ \App\Models\KunjunganTamu::KATEGORI[$item->kategori] }}</td><td>{{ \Illuminate\Support\Str::limit($item->keperluan, 300) }}<small>Tujuan: {{ $item->nama_tujuan }}</small></td><td>{{ $item->waktu_datang->format('d-m-Y') }}<small>{{ $item->waktu_datang->format('H:i') }}</small></td><td>{{ $item->waktu_pulang?->format('d-m-Y') ?: '-' }}<small>{{ $item->waktu_pulang?->format('H:i') }}</small></td><td>{{ \App\Models\KunjunganTamu::STATUS[$item->status] }}</td></tr>@empty<tr><td colspan="7" style="text-align:center">Tidak ada kunjungan pada filter ini.</td></tr>@endforelse
    </tbody></table>
    <p class="foot">Dicetak {{ now()->format('d-m-Y H:i') }} WIB &middot; NUSA &middot; Nomor kontak, alamat, dan lampiran privat tidak disertakan dalam cetakan.</p>
</main></body></html>
