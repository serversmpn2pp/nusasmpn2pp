<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $dataSurat['jenis_surat'] === 'pemanggilan' ? 'Surat Pemanggilan' : 'Surat Pemberitahuan Poin' }} - {{ $siswa->nama_lengkap }}</title>
    <style>
        :root{--primary:#15477a;--secondary:#f1c40f;--ink:#172536;--muted:#5f6f7e;--line:#aeb9c3}
        *{box-sizing:border-box}
        body{background:#edf1f5;color:var(--ink);font-family:"Times New Roman",serif;font-size:12pt;line-height:1.5;margin:0}
        .print-toolbar{align-items:center;background:#fff;border-bottom:1px solid #d5dce2;display:flex;font-family:Arial,sans-serif;gap:18px;justify-content:space-between;padding:14px 22px}
        .button{background:#fff;border:1px solid #c7d0d8;border-radius:6px;color:var(--primary);cursor:pointer;font:700 13px Arial;padding:9px 13px;text-decoration:none}
        .button-primary{background:var(--primary);border-color:var(--primary);color:#fff}
        .letter{background:#fff;margin:22px auto;min-height:297mm;padding:17mm 18mm;width:min(210mm,calc(100% - 24px))}
        .letter-head{align-items:center;border-bottom:4px double #111;display:grid;gap:12px;grid-template-columns:76px 1fr 76px;padding-bottom:9px;text-align:center}
        .letter-logo{height:68px;object-fit:contain;width:68px}
        .letter-head h1{font-size:17pt;letter-spacing:0;margin:0;text-transform:uppercase}
        .letter-head h2{font-size:15pt;margin:0;text-transform:uppercase}
        .letter-head p{font-size:10pt;margin:2px 0}
        .letter-meta{display:grid;font-size:11pt;grid-template-columns:78px 10px 1fr;margin:17px 0}
        .letter-meta div{display:contents}
        .recipient{margin:16px 0}
        .recipient p{margin:0}
        .letter-body{text-align:justify}
        .letter-body p{margin:11px 0}
        .student-data{border-collapse:collapse;margin:12px 0 14px;width:100%}
        .student-data td{padding:3px 6px;vertical-align:top}
        .student-data td:first-child{width:145px}
        .violation-table{border-collapse:collapse;font-size:10pt;margin:12px 0;width:100%}
        .violation-table th,.violation-table td{border:1px solid var(--line);padding:5px 6px;text-align:left;vertical-align:top}
        .violation-table th{background:#eef3f7}
        .meeting{border-left:4px solid var(--secondary);margin:14px 0;padding:5px 12px}
        .meeting-grid{display:grid;grid-template-columns:120px 10px 1fr}
        .meeting-grid div{display:contents}
        .signatures{display:grid;gap:42px;grid-template-columns:repeat(2,minmax(0,1fr));margin-top:28px;text-align:center}
        .signature-space{height:62px}
        .signature-name{font-weight:700;text-decoration:underline}
        .copy{font-size:9.5pt;margin-top:30px}
        .copy ol{margin:4px 0;padding-left:22px}
        .footer{border-top:1px solid #ccd3da;color:var(--muted);font:8.5pt Arial,sans-serif;margin-top:22px;padding-top:7px;text-align:center}
        @media(max-width:600px){.letter{padding:18px}.letter-head{grid-template-columns:50px 1fr 50px}.letter-logo{height:46px;width:46px}.letter-head h1{font-size:13pt}.letter-head h2{font-size:12pt}.signatures{grid-template-columns:1fr}.meeting-grid{grid-template-columns:95px 10px 1fr}}
        @media print{@page{size:A4 portrait;margin:12mm}body{background:#fff}.print-toolbar{display:none}.letter{margin:0;min-height:0;padding:0;width:auto}.violation-table tr,.signatures{break-inside:avoid}}
    </style>
</head>
<body>
    <div class="print-toolbar">
        <strong>Surat siap dicetak</strong>
        <button class="button button-primary" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <main class="letter">
        <header class="letter-head">
            <img class="letter-logo" src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo sekolah">
            <div>
                <h1>Pemerintah Kota Padang Panjang</h1>
                <h2>SMP Negeri 2 Padang Panjang</h2>
                <p>Jl. Sutan Syahrir No. 1, Silaing Bawah, Padang Panjang Barat</p>
            </div>
            <img class="letter-logo" src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
        </header>

        <section class="letter-meta">
            <div><span>Nomor</span><span>:</span><strong>{{ $dataSurat['nomor_surat'] ?: '........................................' }}</strong></div>
            <div><span>Lampiran</span><span>:</span><span>-</span></div>
            <div><span>Perihal</span><span>:</span><strong>{{ $dataSurat['jenis_surat'] === 'pemanggilan' ? 'Pemanggilan Orang Tua/Wali Siswa' : 'Pemberitahuan Perkembangan Poin Siswa' }}</strong></div>
        </section>

        <section class="recipient">
            <p>Yth. Bapak/Ibu <strong>{{ $dataSurat['nama_penerima'] }}</strong></p>
            <p>Orang Tua/Wali dari {{ $siswa->nama_lengkap }}</p>
            @if($dataSurat['alamat_penerima'])<p>di {{ $dataSurat['alamat_penerima'] }}</p>@endif
        </section>

        <section class="letter-body">
            <p>Dengan hormat,</p>
            <p>
                Berdasarkan catatan pembinaan siswa SMP Negeri 2 Padang Panjang pada Tahun Pelajaran
                {{ $tahunPelajaran->nama }}, kami menyampaikan perkembangan poin atas nama:
            </p>
            <table class="student-data">
                <tr><td>Nama siswa</td><td>:</td><td><strong>{{ $siswa->nama_lengkap }}</strong></td></tr>
                <tr><td>NIS / NISN</td><td>:</td><td>{{ $siswa->nis ?: '-' }} / {{ $siswa->nisn ?: '-' }}</td></tr>
                <tr><td>Kelas</td><td>:</td><td>{{ $anggotaKelas?->kelas?->nama ?: '-' }}</td></tr>
                <tr><td>Total poin resmi</td><td>:</td><td><strong>{{ $totalPoinTerkini }} poin</strong></td></tr>
            </table>

            <p>Catatan pelanggaran terakhir yang telah melalui proses verifikasi:</p>
            <table class="violation-table">
                <thead><tr><th>No.</th><th>Tanggal</th><th>Pelanggaran</th><th>Poin</th></tr></thead>
                <tbody>
                    @forelse($pelanggaranTerakhir as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item->tanggal_kejadian?->locale('id')->translatedFormat('d M Y') }}</td>
                            <td>{{ $item->butirPelanggaranLaporan->pluck('nama_pelanggaran')->filter()->implode(', ') ?: 'Pelanggaran siswa' }}</td>
                            <td>{{ $item->total_poin }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center">Belum ada pelanggaran yang disahkan.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if($dataSurat['jenis_surat'] === 'pemanggilan')
                <p>Sehubungan dengan hal tersebut, kami mengharapkan kehadiran Bapak/Ibu pada:</p>
                <div class="meeting">
                    <div class="meeting-grid">
                        <div><span>Hari/Tanggal</span><span>:</span><strong>{{ $tanggalPertemuan?->locale('id')->translatedFormat('l, d F Y') }}</strong></div>
                        <div><span>Pukul</span><span>:</span><strong>{{ $dataSurat['jam_pertemuan'] }} WIB</strong></div>
                        <div><span>Tempat</span><span>:</span><strong>{{ $dataSurat['tempat_pertemuan'] }}</strong></div>
                        <div><span>Keperluan</span><span>:</span><span>{{ $dataSurat['keperluan'] }}</span></div>
                    </div>
                </div>
                <p>Kehadiran dan kerja sama Bapak/Ibu sangat kami harapkan untuk mendukung proses pembinaan siswa.</p>
            @else
                <p>
                    Surat ini disampaikan sebagai bagian dari kerja sama sekolah dan keluarga dalam mendampingi perkembangan
                    sikap serta kedisiplinan siswa. Kami mengharapkan perhatian dan pendampingan Bapak/Ibu di rumah.
                </p>
            @endif

            @if($dataSurat['catatan_tambahan'])
                <p>{{ $dataSurat['catatan_tambahan'] }}</p>
            @endif

            <p>Demikian surat ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>
        </section>

        <section class="signatures">
            <div>
                <p>Mengetahui,<br>Wakil Kepala Sekolah Bidang Kesiswaan</p>
                <div class="signature-space"></div>
                <p class="signature-name">{{ $wakilKesiswaan?->nama_lengkap ?: 'Belum ditentukan' }}</p>
                <p>NIP. {{ $wakilKesiswaan?->nip ?: '-' }}</p>
            </div>
            <div>
                <p>Padang Panjang, {{ $tanggalSurat->locale('id')->translatedFormat('d F Y') }}<br>Kepala SMP Negeri 2 Padang Panjang</p>
                <div class="signature-space"></div>
                <p class="signature-name">{{ $kepalaSekolah?->nama_lengkap ?: 'Belum ditentukan' }}</p>
                <p>NIP. {{ $kepalaSekolah?->nip ?: '-' }}</p>
            </div>
        </section>

        <section class="copy">
            <strong>Tembusan:</strong>
            <ol>
                <li>Guru BK: {{ $guruBk?->nama_lengkap ?: '-' }}</li>
                <li>Wali Kelas: {{ $waliKelas?->nama_lengkap ?: '-' }}</li>
                <li>Guru Wali: {{ $guruWali?->nama_lengkap ?: '-' }}</li>
            </ol>
        </section>

        <p class="footer">Dokumen dibuat melalui NUSA - Sistem Data Sekolah Terpadu SMP Negeri 2 Padang Panjang.</p>
    </main>
</body>
</html>
