<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Label Meja {{ $ruang->nama }} - NUSA</title>
    <style>
        :root { --primary:#15477A; --primary-dark:#0C3158; --accent:#F1C40F; --paper:#fff; --ink:#102238; --muted:#5E7187; }
        * { box-sizing:border-box; }
        body { margin:0; background:#eaf0f6; color:var(--ink); font-family:Arial,Helvetica,sans-serif; letter-spacing:0; }
        .toolbar { position:sticky; top:0; z-index:3; display:flex; justify-content:space-between; gap:16px; align-items:center; padding:14px 20px; border-bottom:1px solid #d7e1eb; background:#fff; box-shadow:0 5px 18px rgba(21,71,122,.08); }
        .toolbar strong,.toolbar span { display:block; }
        .toolbar span { margin-top:3px; color:var(--muted); font-size:13px; }
        .toolbar-actions { display:flex; gap:9px; }
        .button { display:inline-flex; min-height:40px; align-items:center; justify-content:center; padding:0 16px; border:1px solid #cbd7e3; border-radius:7px; background:#fff; color:var(--primary); font-weight:800; text-decoration:none; cursor:pointer; }
        .button-primary { border-color:var(--primary); background:var(--primary); color:#fff; }
        .print-area { padding:10mm; }
        .sheet { display:grid; width:190mm; min-height:267mm; margin:0 auto 10mm; grid-template-columns:repeat(2,92mm); grid-auto-rows:62mm; gap:4mm 6mm; align-content:start; }
        .desk-label { position:relative; display:grid; min-width:0; grid-template-rows:auto 1fr auto; overflow:hidden; border:1px solid var(--primary); border-radius:3mm; background:var(--paper); box-shadow:0 4px 14px rgba(21,71,122,.12); break-inside:avoid; }
        .desk-label::after { position:absolute; right:-13mm; bottom:-16mm; width:34mm; height:34mm; border:7mm solid var(--accent); border-radius:50%; content:""; opacity:.95; }
        .label-head { display:grid; grid-template-columns:12mm minmax(0,1fr) auto; gap:3mm; align-items:center; padding:3mm 4mm; background:var(--primary); color:#fff; }
        .label-logo { width:11mm; height:11mm; padding:.7mm; border-radius:2mm; background:#fff; object-fit:contain; }
        .label-school { min-width:0; }
        .label-school strong { display:block; overflow:hidden; font-size:7.4pt; line-height:1.1; text-overflow:ellipsis; white-space:nowrap; }
        .label-school span { display:block; margin-top:1mm; color:#dbe8f5; font-size:6.8pt; font-weight:800; }
        .label-room { padding:1.5mm 2.5mm; border-radius:2mm; background:var(--accent); color:var(--primary-dark); font-size:7.5pt; font-weight:900; }
        .label-body { display:grid; grid-template-columns:27mm minmax(0,1fr); gap:4mm; align-items:center; padding:4mm; }
        .desk-number { display:grid; height:25mm; place-items:center; border:1px solid #d6e1eb; border-radius:2.5mm; background:#f3f7fb; color:var(--primary); text-align:center; }
        .desk-number span { display:block; font-size:6.5pt; font-weight:800; text-transform:uppercase; }
        .desk-number strong { display:block; margin-top:1mm; font-size:20pt; line-height:1; }
        .student { min-width:0; }
        .student-name { margin:0; overflow:hidden; color:var(--primary-dark); font-size:10.5pt; font-weight:900; line-height:1.13; text-transform:uppercase; }
        .student-meta { display:grid; grid-template-columns:12mm minmax(0,1fr); gap:1mm 2mm; align-items:baseline; margin:2.5mm 0 0; font-size:7pt; line-height:1.15; }
        .student-meta dt { margin:0; color:var(--muted); }
        .student-meta dd { min-width:0; margin:0; overflow-wrap:anywhere; font-variant-numeric:tabular-nums; font-weight:800; }
        .desk-code { position:relative; z-index:1; padding:2.4mm 4mm; border-top:1px dashed #c7d5e2; background:#f8fafc; color:var(--primary-dark); font-size:8pt; font-weight:900; text-align:center; }
        @page { size:A4 portrait; margin:10mm; }
        @media print {
            body { background:#fff; print-color-adjust:exact; -webkit-print-color-adjust:exact; }
            .toolbar { display:none; }
            .print-area { padding:0; }
            .sheet { width:190mm; min-height:267mm; margin:0; page-break-after:always; }
            .sheet:last-child { page-break-after:auto; }
            .desk-label { box-shadow:none; }
        }
        @media screen and (max-width:850px) {
            .toolbar { align-items:flex-start; flex-direction:column; }
            .print-area { overflow:auto; }
        }
    </style>
</head>
<body>
    <header class="toolbar">
        <div>
            <strong>Label meja {{ $ruang->nama }}</strong>
            <span>{{ $daftar->count() }} label · 8 label per lembar A4</span>
        </div>
        <div class="toolbar-actions">
            <a href="{{ route('ujian-terpusat.peserta.show', [$kegiatan, $kelompok]) }}" class="button">Kembali</a>
            <button type="button" class="button button-primary" onclick="window.print()">Cetak / Simpan PDF</button>
        </div>
    </header>

    <main class="print-area">
        @forelse($daftar->chunk(8) as $halaman)
            <section class="sheet">
                @foreach($halaman as $penempatan)
                    @php($siswa = $penempatan->anggotaKelas?->siswa)
                    <article class="desk-label">
                        <div class="label-head">
                            <img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo SMP Negeri 2 Padang Panjang" class="label-logo">
                            <div class="label-school">
                                <strong>SMP NEGERI 2 PADANG PANJANG</strong>
                                <span>NUSA</span>
                            </div>
                            <span class="label-room">{{ $ruang->kode }}</span>
                        </div>
                        <div class="label-body">
                            <div class="desk-number"><div><span>Nomor meja</span><strong>{{ str_pad((string) $penempatan->nomor_meja, 3, '0', STR_PAD_LEFT) }}</strong></div></div>
                            <div class="student">
                                <p class="student-name">{{ $siswa?->nama_lengkap ?: 'Nama siswa belum tersedia' }}</p>
                                <dl class="student-meta">
                                    <dt>NISN</dt><dd>{{ $siswa?->nisn ?: '-' }}</dd>
                                    <dt>Kelas</dt><dd>{{ $penempatan->anggotaKelas?->kelas?->nama ?: '-' }}</dd>
                                    <dt>Sesi</dt><dd>{{ $kelompok->sesiKegiatanUjianCbt?->nama ?: '-' }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="desk-code">{{ $penempatan->kode_meja }}</div>
                    </article>
                @endforeach
            </section>
        @empty
            <p>Belum ada peserta di ruang ini.</p>
        @endforelse
    </main>
</body>
</html>
