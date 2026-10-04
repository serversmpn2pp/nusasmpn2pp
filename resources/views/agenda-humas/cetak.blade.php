<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $jenisCetak === 'daftar-hadir' ? 'Daftar Hadir' : 'Notulen' }} - {{ $agendaHumas->judul }}</title>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; background:#e9edf1; color:#202b35; font:10pt Arial,Helvetica,sans-serif; }
        .toolbar { padding:12px 20px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; background:#fff; border-bottom:1px solid #c9d2da; }
        .toolbar a,.toolbar button { font:700 10pt Arial,sans-serif; border:1px solid #b6c4d1; color:#173f69; background:#fff; text-decoration:none; padding:10px 14px; border-radius:4px; cursor:pointer; }
        .toolbar button { background:#173f69; color:#fff; }
        .toolbar span { color:#687582; font-size:9pt; }
        .sheet { width:210mm; min-height:273mm; background:#fff; margin:18px auto; padding:12mm; }
        .header { display:grid; grid-template-columns:18mm 1fr 18mm; gap:4mm; align-items:center; border-bottom:1px solid #23496d; padding-bottom:4mm; margin-bottom:5mm; text-align:center; }
        .header img { width:16mm; height:18mm; object-fit:contain; }
        .header h1 { font-size:13pt; margin:0 0 1mm; }
        .header p { margin:1mm 0; font-size:10pt; }
        .heading { text-align:center; margin:0 0 4mm; font-size:12pt; }
        .draft { color:#825621; text-align:center; font-size:9pt; margin:0 0 3mm; }
        .facts { width:100%; border-collapse:collapse; margin-bottom:4mm; }
        .facts td { padding:.8mm 0; vertical-align:top; font-size:9pt; overflow-wrap:anywhere; }
        .facts td:first-child { width:30mm; }
        .records { width:100%; border-collapse:collapse; table-layout:fixed; font-size:8.5pt; }
        .records th,.records td { border:1px solid #a4b0bc; padding:1.2mm 1.8mm; vertical-align:middle; overflow-wrap:anywhere; }
        .records th { background:#eef2f6; height:8mm; font-size:8pt; }
        .records td { height:6.5mm; }
        .sheet--attendance .records td { height:5.5mm; padding:.8mm 1.5mm; font-size:8pt; line-height:1.15; }
        .sheet--attendance .records th { height:7mm; }
        .sheet--attendance .signatures { margin-top:5mm; }
        .sheet--attendance .signature-space { height:14mm; }
        .center { text-align:center; }
        h3 { margin:5mm 0 2mm; font-size:10pt; break-after:avoid; }
        .text { white-space:pre-wrap; overflow-wrap:anywhere; line-height:1.5; }
        .signatures { display:grid; grid-template-columns:1fr 1fr; gap:20mm; text-align:center; margin-top:7mm; break-inside:avoid; }
        .signatures p { margin:1mm 0; font-size:9pt; }
        .signature-date { margin:5mm 0 0; text-align:right; font-size:9pt; }
        .signature-space { height:18mm; }
        .foot { margin-top:4mm; font-size:8pt; color:#687582; }
        @page { size:A4 portrait; margin:12mm; }
        @media print {
            body { background:#fff; } .toolbar { display:none; } .sheet { width:auto; min-height:0; margin:0; padding:0; break-after:page; } .sheet:last-child { break-after:auto; }
            .sheet--attendance { width:186mm; }
            thead { display:table-header-group; } tr { break-inside:avoid; } a { color:inherit; }
        }
        @media screen and (max-width:820px) { .sheet { width:100%; min-height:0; padding:20px; } .header { grid-template-columns:50px 1fr 50px; gap:8px; } .header img { max-width:100%; } .header h1 { font-size:11pt; } }
    </style>
</head>
<body>
    <div class="toolbar"><a href="{{ route('agenda-humas.show', [$agendaHumas, 'tab' => $jenisCetak === 'daftar-hadir' ? 'peserta' : 'notulen']) }}">Kembali ke agenda</a><button type="button" onclick="window.print()">Cetak / Simpan PDF</button><span>A4 portrait</span></div>
    @php($lembar = $jenisCetak === 'daftar-hadir' ? ($agendaHumas->peserta->isEmpty() ? collect([collect()]) : $agendaHumas->peserta->chunk(24)) : collect([collect()]))
    @foreach ($lembar as $halaman => $pesertaLembar)
        <article class="sheet {{ $jenisCetak === 'daftar-hadir' ? 'sheet--attendance' : '' }}">
            <header class="header"><img src="{{ asset('images/logo-padang-panjang.png') }}" alt="Logo Padang Panjang"><div><h1>SMP NEGERI 2 PADANG PANJANG</h1><p>WAKIL PIMPINAN BIDANG HUMAS</p></div><img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo sekolah"></header>
            <h2 class="heading">{{ $jenisCetak === 'daftar-hadir' ? 'DAFTAR HADIR PERTEMUAN' : 'NOTULEN PERTEMUAN' }}</h2>
            @if ($agendaHumas->status === 'dibatalkan')<p class="draft">AGENDA DIBATALKAN: {{ $agendaHumas->alasan_pembatalan }}</p>@elseif ($jenisCetak === 'notulen' && $agendaHumas->status !== 'selesai')<p class="draft">DRAF NOTULEN</p>@endif
            <table class="facts">
                <tr><td>Agenda</td><td>: {{ $agendaHumas->judul }}</td></tr>
                <tr><td>Hari / tanggal</td><td>: {{ $agendaHumas->waktu_mulai->locale('id')->translatedFormat('l, d F Y') }}</td></tr>
                <tr><td>Waktu</td><td>: {{ $agendaHumas->waktu_mulai->format('H:i') }} - {{ $agendaHumas->waktu_selesai->format($agendaHumas->waktu_mulai->isSameDay($agendaHumas->waktu_selesai) ? 'H:i' : 'd-m-Y H:i') }} WIB</td></tr>
                <tr><td>Tempat</td><td>: {{ $agendaHumas->tempat }}</td></tr>
                @if ($jenisCetak === 'notulen')<tr><td>Pemimpin</td><td>: {{ $agendaHumas->pemimpin ?: '-' }}</td></tr><tr><td>Notulis</td><td>: {{ $agendaHumas->notulis ?: '-' }}</td></tr>@endif
            </table>
            @if ($jenisCetak === 'daftar-hadir')
                <table class="records"><colgroup><col style="width:7%"><col style="width:30%"><col style="width:28%"><col style="width:15%"><col style="width:20%"></colgroup><thead><tr><th>No</th><th>Nama peserta</th><th>Instansi / kelas / peran</th><th>Kehadiran</th><th>Tanda tangan</th></tr></thead><tbody>
                    @forelse ($pesertaLembar as $peserta)<tr><td class="center">{{ $halaman * 24 + $loop->iteration }}</td><td>{{ $peserta->nama }}</td><td>{{ collect([$peserta->instansi, $peserta->peran])->filter()->join(' / ') }}</td><td class="center">{{ $peserta->status_kehadiran === 'belum_dicatat' ? '' : \App\Models\PesertaPertemuanHumas::KEHADIRAN[$peserta->status_kehadiran] }}</td><td></td></tr>@empty
                        @for ($baris = 1; $baris <= 12; $baris++)<tr><td class="center">{{ $baris }}</td><td></td><td></td><td></td><td></td></tr>@endfor
                    @endforelse
                </tbody></table>
                <p class="foot">Lembar {{ $halaman + 1 }} dari {{ $lembar->count() }} &middot; Total {{ $agendaHumas->peserta->count() }} peserta terdaftar.</p>
            @else
                <p style="font-size:9pt">Kehadiran: {{ $agendaHumas->peserta->where('status_kehadiran', 'hadir')->count() }} hadir, {{ $agendaHumas->peserta->where('status_kehadiran', 'izin')->count() }} izin, {{ $agendaHumas->peserta->where('status_kehadiran', 'tidak_hadir')->count() }} tidak hadir, {{ $agendaHumas->peserta->where('status_kehadiran', 'belum_dicatat')->count() }} belum dicatat.</p>
                <h3>Pokok agenda</h3><div class="text">{{ $agendaHumas->topik }}</div>
                <h3>Pembahasan</h3><div class="text">{{ $agendaHumas->pembahasan ?: 'Belum dicatat.' }}</div>
                <h3>Keputusan / kesepakatan</h3><div class="text">{{ $agendaHumas->keputusan ?: 'Belum dicatat.' }}</div>
                @if ($agendaHumas->tindakLanjut->isNotEmpty())
                    <h3>Tindak lanjut</h3><table class="records"><colgroup><col style="width:7%"><col style="width:43%"><col style="width:25%"><col style="width:25%"></colgroup><thead><tr><th>No</th><th>Tindak lanjut</th><th>Penanggung jawab</th><th>Batas / status</th></tr></thead><tbody>
                        @foreach ($agendaHumas->tindakLanjut as $tugas)<tr><td class="center">{{ $loop->iteration }}</td><td>{{ $tugas->uraian }}@if ($tugas->catatan)<br>{{ $tugas->catatan }}@endif</td><td>{{ $tugas->penanggung_jawab }}</td><td>{{ $tugas->batas_tanggal?->format('d-m-Y') ?: '-' }}<br>{{ \App\Models\TindakLanjutAgendaHumas::STATUS[$tugas->status] }}</td></tr>@endforeach
                    </tbody></table>
                @endif
            @endif
            <p class="signature-date">Padang Panjang, {{ $agendaHumas->waktu_mulai->locale('id')->translatedFormat('d F Y') }}</p>
            <div class="signatures"><div><p>Pemimpin pertemuan</p><div class="signature-space"></div><p>{{ $agendaHumas->pemimpin ?: '(................................)' }}</p></div><div><p>Notulis</p><div class="signature-space"></div><p>{{ $agendaHumas->notulis ?: '(................................)' }}</p></div></div>
        </article>
    @endforeach
    @if ($jenisCetak === 'daftar-hadir')
        <script>
            // Measure the actual print layout, reserving space for both signatures on every sheet.
            window.paginateHumasAttendance = () => {
                if (!window.matchMedia('print').matches) return;
                const originals = Array.from(document.querySelectorAll('.sheet--attendance'));
                if (!originals.length) return;
                const template = originals[0].cloneNode(true);
                const rows = originals.flatMap(sheet => Array.from(sheet.querySelectorAll('.records tbody tr')));
                template.querySelector('.records tbody').replaceChildren();
                const staging = document.createElement('div');
                staging.style.cssText = 'position:absolute;left:-10000px;top:0;visibility:hidden;width:186mm;';
                document.body.appendChild(staging);
                const pages = [];
                const maxHeight = 273 * 96 / 25.4 - 4;
                let page;
                let body;
                const newPage = () => {
                    page = template.cloneNode(true);
                    staging.replaceChildren(page);
                    body = page.querySelector('.records tbody');
                    pages.push(page);
                };
                newPage();
                rows.forEach(row => {
                    if (body.children.length >= 24) newPage();
                    body.appendChild(row);
                    if (page.getBoundingClientRect().height > maxHeight && body.children.length > 1) {
                        row.remove();
                        newPage();
                        body.appendChild(row);
                    }
                });
                const fragment = document.createDocumentFragment();
                pages.forEach((sheet, index) => {
                    const foot = sheet.querySelector('.foot');
                    foot.textContent = foot.textContent.replace(/^Lembar \d+ dari \d+/, `Lembar ${index + 1} dari ${pages.length}`);
                    fragment.appendChild(sheet);
                });
                originals[0].before(fragment);
                originals.forEach(sheet => sheet.remove());
                staging.remove();
                window.agendaPrintReady = true;
            };
            window.addEventListener('beforeprint', window.paginateHumasAttendance);
            window.matchMedia('print').addEventListener('change', event => {
                if (event.matches) window.paginateHumasAttendance();
            });
            document.fonts.ready.then(window.paginateHumasAttendance);
        </script>
    @endif
</body>
</html>
