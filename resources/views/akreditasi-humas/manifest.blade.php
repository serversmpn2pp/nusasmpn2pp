<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Indeks Bukti Akreditasi</title><style>
    * { box-sizing:border-box; } body { font:14px/1.6 Arial,sans-serif; color:#233342; background:#fff; margin:32px auto; padding:0 20px; max-width:1000px; }
    h1 { font-size:24px; } h2 { font-size:18px; border-top:1px solid #cad2da; padding-top:20px; margin-top:28px; overflow-wrap:anywhere; }
    p,td { overflow-wrap:anywhere; } table { width:100%; border-collapse:collapse; } th,td { text-align:left; padding:10px; border-bottom:1px solid #cad2da; vertical-align:top; } th { background:#f1f5f7; } a { color:#174677; } small { font-size:12px; }
    @page { size:A4 portrait; margin:12mm; } @media print { body { margin:0; padding:0; font-size:10pt; } h1 { font-size:16pt; } h2 { font-size:12pt; } thead { display:table-header-group; } tr { break-inside:avoid; } a { color:#233342; text-decoration:none; } }
</style></head><body>
    <h1>PORTOFOLIO AKREDITASI HUMAS</h1><p><strong>{{ $portofolio->nama }}</strong><br>Instrumen: {{ $portofolio->instrumen }}<br>Tahun pelajaran: {{ $portofolio->tahunPelajaran->nama }}<br>Penanggung jawab: {{ $portofolio->penanggung_jawab }}<br>Versi portofolio: {{ $portofolio->versi }} &middot; Diekspor {{ now()->format('d-m-Y H:i') }} WIB</p>
    @foreach($portofolio->butir as $b)<section><h2>{{ $b->kode }} - {{ $b->judul }}</h2><p>{{ \App\Models\ButirAkreditasiHumas::STATUS[$b->status] }}<br>{{ $b->catatan_pemeriksaan }}</p>
        @if($b->status !== 'tidak_berlaku')<table><thead><tr><th>No.</th><th>Dokumen</th><th>Versi</th><th>Catatan</th></tr></thead><tbody>
            @foreach(collect($daftar)->filter(fn($d) => $d['butir']->id === $b->id) as $item)<tr><td>{{ $loop->iteration }}</td><td><a href="{{ $item['nama'] }}">{{ $item['bukti']->judul }}</a></td><td>{{ $item['bukti']->berkas->versi }}</td><td>{{ $item['bukti']->catatan ?? '-' }}</td></tr>@endforeach
        </tbody></table>@endif
    </section>@endforeach
    <p><small>Bundel memuat versi dokumen yang ditautkan. Butir tidak berlaku dicantumkan dengan alasan, tanpa menyertakan berkasnya. Portofolio bukan penetapan hasil akreditasi.</small></p>
</body></html>
