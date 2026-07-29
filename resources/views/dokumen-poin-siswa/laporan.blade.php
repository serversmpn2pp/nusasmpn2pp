<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Poin {{ $siswa->nama_lengkap }} - NUSA</title>
    <style>
        :root{--primary:#15477a;--secondary:#f1c40f;--ink:#172536;--muted:#5f6f7e;--line:#cfd8e1;--soft:#eef4f9}
        *{box-sizing:border-box}
        body{background:#edf1f5;color:var(--ink);font-family:Arial,sans-serif;margin:0}
        .print-toolbar{align-items:center;background:#fff;border-bottom:1px solid var(--line);display:flex;gap:20px;justify-content:space-between;padding:14px 22px;position:sticky;top:0;z-index:5}
        .print-toolbar p{color:var(--muted);font-size:12px;margin:4px 0 0}
        .toolbar-actions{display:flex;flex-wrap:wrap;gap:8px}
        .button{background:#fff;border:1px solid var(--line);border-radius:6px;color:var(--primary);cursor:pointer;font:700 13px Arial;padding:9px 13px;text-decoration:none}
        .button-primary{background:var(--primary);border-color:var(--primary);color:#fff}
        .filter-bar{background:#fff;border-bottom:1px solid var(--line);display:grid;gap:10px;grid-template-columns:repeat(5,minmax(120px,1fr)) auto;padding:12px 22px}
        .field label{color:var(--muted);display:block;font-size:11px;font-weight:700;margin-bottom:5px}
        .input,.select{border:1px solid var(--line);border-radius:5px;font:13px Arial;min-height:36px;padding:7px 9px;width:100%}
        .filter-action{align-self:end}
        .report{background:#fff;border-top:5px solid var(--primary);margin:22px auto;padding:11mm;width:min(210mm,calc(100% - 24px))}
        .report-head{align-items:center;border-bottom:3px double var(--primary);display:grid;gap:15px;grid-template-columns:64px 1fr 64px;padding-bottom:12px;text-align:center}
        .report-logo{height:58px;object-fit:contain;width:58px}
        .report-head h1{color:var(--primary);font-size:20px;letter-spacing:0;margin:0 0 4px;text-transform:uppercase}
        .report-head p{font-size:11px;margin:3px 0}
        .document-title{text-align:center}
        .document-title h2{font-size:16px;margin:18px 0 5px;text-decoration:underline}
        .document-title p{font-size:11px;margin:0}
        .identity{border:1px solid var(--line);display:grid;font-size:11px;grid-template-columns:repeat(2,minmax(0,1fr));margin:16px 0}
        .identity div{display:grid;grid-template-columns:105px 1fr;padding:6px 9px}
        .identity span{color:var(--muted)}
        .summary{display:grid;gap:7px;grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:16px}
        .summary div{background:var(--soft);border-left:3px solid var(--secondary);padding:8px 9px}
        .summary span{color:var(--muted);display:block;font-size:9px;font-weight:700;text-transform:uppercase}
        .summary strong{color:var(--primary);display:block;font-size:18px;margin-top:4px}
        .section{margin-top:16px}
        .section h3{border-bottom:1px solid var(--primary);color:var(--primary);font-size:12px;margin:0 0 7px;padding-bottom:4px;text-transform:uppercase}
        .table-wrap{overflow-x:auto}
        table{border-collapse:collapse;font-size:9.5px;line-height:1.35;width:100%}
        th,td{border:1px solid var(--line);padding:5px 6px;text-align:left;vertical-align:top}
        th{background:var(--primary);color:#fff;font-size:9px;text-transform:uppercase}
        .text-right{text-align:right}
        .empty{color:var(--muted);padding:10px;text-align:center}
        .signatures{display:grid;gap:22px 14px;grid-template-columns:repeat(2,minmax(0,1fr));margin-top:24px;text-align:center}
        .signature{font-size:10px;min-height:105px}
        .signature-space{height:47px}
        .signature-name{font-weight:700;text-decoration:underline}
        .confidential{border-top:1px solid var(--line);color:var(--muted);font-size:8.5px;margin-top:18px;padding-top:7px;text-align:center}
        @media(max-width:820px){.filter-bar{grid-template-columns:repeat(2,minmax(0,1fr))}.report{padding:18px}.summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:560px){.print-toolbar{align-items:flex-start;flex-direction:column}.filter-bar{grid-template-columns:1fr}.report-head{grid-template-columns:46px 1fr 46px}.report-logo{height:42px;width:42px}.report-head h1{font-size:15px}.identity{grid-template-columns:1fr}.summary{grid-template-columns:1fr}.signatures{grid-template-columns:1fr}}
        @media print{@page{size:A4 portrait;margin:9mm}body{background:#fff}.print-toolbar,.filter-bar{display:none}.report{border-top-width:4px;margin:0;padding:0;width:auto}.table-wrap{overflow:visible}tr,.summary div,.signature{break-inside:avoid}}
    </style>
</head>
<body>
    <div class="print-toolbar">
        <div>
            <strong>Laporan poin individual siap dicetak</strong>
            <p>Satu dokumen hanya memuat data {{ $siswa->nama_lengkap }}.</p>
        </div>
        <div class="toolbar-actions">
            <a class="button" href="{{ route('rekap-poin-siswa.show', ['siswa' => $siswa, 'tahun_pelajaran_id' => $tahunPelajaran->id]) }}">Kembali</a>
            <button class="button button-primary" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        </div>
    </div>

    <form method="GET" class="filter-bar">
        <div class="field">
            <label for="tahun_pelajaran_id">Tahun pelajaran</label>
            <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                @foreach($daftarTahunPelajaran as $tahun)
                    <option value="{{ $tahun->id }}" @selected($tahun->id === $tahunPelajaran->id)>{{ $tahun->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="periode">Periode</label>
            <select id="periode" name="periode" class="select">
                <option value="semester" @selected($periode === 'semester')>Semester</option>
                <option value="rentang" @selected($periode === 'rentang')>Rentang Tanggal</option>
            </select>
        </div>
        <div class="field">
            <label for="semester">Semester</label>
            <select id="semester" name="semester" class="select">
                <option value="ganjil" @selected($semester === 'ganjil')>Ganjil</option>
                <option value="genap" @selected($semester === 'genap')>Genap</option>
            </select>
        </div>
        <div class="field">
            <label for="tanggal_mulai">Tanggal mulai</label>
            <input id="tanggal_mulai" type="date" name="tanggal_mulai" class="input" value="{{ $tanggalMulai->format('Y-m-d') }}">
        </div>
        <div class="field">
            <label for="tanggal_selesai">Tanggal selesai</label>
            <input id="tanggal_selesai" type="date" name="tanggal_selesai" class="input" value="{{ $tanggalSelesai->format('Y-m-d') }}">
        </div>
        <div class="filter-action"><button class="button button-primary">Tampilkan</button></div>
    </form>

    <main class="report">
        <header class="report-head">
            <img class="report-logo" src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo SMP Negeri 2 Padang Panjang">
            <div>
                <h1>SMP Negeri 2 Padang Panjang</h1>
                <p>Jl. Sutan Syahrir No. 1, Silaing Bawah, Padang Panjang Barat</p>
                <p>NUSA - Sistem Data Sekolah Terpadu</p>
            </div>
            <img class="report-logo" src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
        </header>

        <div class="document-title">
            <h2>LAPORAN POIN INDIVIDUAL SISWA</h2>
            <p>{{ $labelPeriode }} &middot; Tahun Pelajaran {{ $tahunPelajaran->nama }}</p>
        </div>

        <section class="identity">
            <div><span>Nama siswa</span><strong>{{ $siswa->nama_lengkap }}</strong></div>
            <div><span>Kelas</span><strong>{{ $anggotaKelas?->kelas?->nama ?: '-' }}</strong></div>
            <div><span>NIS / NISN</span><strong>{{ $siswa->nis ?: '-' }} / {{ $siswa->nisn ?: '-' }}</strong></div>
            <div><span>Guru wali</span><strong>{{ $guruWali?->nama_lengkap ?: '-' }}</strong></div>
            <div><span>Wali kelas</span><strong>{{ $waliKelas?->nama_lengkap ?: '-' }}</strong></div>
            <div><span>Dicetak</span><strong>{{ $tanggalCetak }}</strong></div>
        </section>

        <section class="summary">
            <div><span>Pelanggaran periode</span><strong>{{ $ringkasan['jumlah_pelanggaran'] }}</strong></div>
            <div><span>Poin masuk periode</span><strong>{{ $ringkasan['poin_masuk_periode'] }}</strong></div>
            <div><span>Pengurangan periode</span><strong>{{ $ringkasan['poin_dikurangi_periode'] }}</strong></div>
            <div><span>Total poin terkini</span><strong>{{ $ringkasan['total_poin_terkini'] }}</strong></div>
        </section>

        <section class="section">
            <h3>Riwayat Pelanggaran yang Disahkan</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>No.</th><th>Tanggal</th><th>Nomor</th><th>Pelanggaran</th><th>Tempat</th><th class="text-right">Poin</th></tr></thead>
                    <tbody>
                        @forelse($pelanggaran as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $item->tanggal_kejadian?->locale('id')->translatedFormat('d M Y') }}</td>
                                <td>{{ $item->nomor_laporan }}</td>
                                <td>{{ $item->butirPelanggaranLaporan->pluck('nama_pelanggaran')->filter()->implode(', ') ?: ($item->kategoriPembinaanSiswa?->nama ?: 'Pelanggaran siswa') }}</td>
                                <td>{{ $item->tempat_kejadian ?: '-' }}</td>
                                <td class="text-right"><strong>{{ $item->total_poin }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">Tidak ada pelanggaran yang disahkan pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="section">
            <h3>Reward dan Pengurangan Poin</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>No.</th><th>Tanggal</th><th>Kegiatan</th><th>Keterangan</th><th class="text-right">Pengurangan</th></tr></thead>
                    <tbody>
                        @forelse($penguranganPoin as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $item->tanggal_kegiatan?->locale('id')->translatedFormat('d M Y') }}</td>
                                <td>{{ $item->jenis_kegiatan }}</td>
                                <td>{{ $item->deskripsi ?: '-' }}</td>
                                <td class="text-right"><strong>-{{ $item->poin_pengurangan }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">Belum ada reward atau pengurangan poin pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="section">
            <h3>Sanksi dan Tindak Lanjut</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Tanggal</th><th>Jenis</th><th>Penanganan</th><th>Petugas</th><th>Status/Hasil</th></tr></thead>
                    <tbody>
                        @foreach($daftarSanksi as $item)
                            <tr>
                                <td>{{ $item->terpicu_pada?->locale('id')->translatedFormat('d M Y') }}</td>
                                <td>Sanksi</td>
                                <td>{{ $item->aturanSanksiPoin?->nama ?: 'Sanksi poin' }}</td>
                                <td>{{ $item->petugasPegawai?->nama_lengkap ?: '-' }}</td>
                                <td>{{ $item->hasil_pelaksanaan ?: $item->labelStatus() }}</td>
                            </tr>
                        @endforeach
                        @foreach($daftarPendampingan as $item)
                            <tr>
                                <td>{{ $item->tanggal_tindak_lanjut?->locale('id')->translatedFormat('d M Y') }}</td>
                                <td>Tindak lanjut</td>
                                <td>{{ $item->labelJenis() }}</td>
                                <td>{{ $item->petugasPegawai?->nama_lengkap ?: '-' }}</td>
                                <td>{{ $item->hasil ?: $item->labelStatus() }}</td>
                            </tr>
                        @endforeach
                        @if($daftarSanksi->isEmpty() && $daftarPendampingan->isEmpty())
                            <tr><td colspan="5" class="empty">Belum ada sanksi atau tindak lanjut pada periode ini.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </section>

        <section class="signatures">
            @foreach([
                ['label' => 'Guru BK', 'pegawai' => $guruBk],
                ['label' => 'Wali Kelas', 'pegawai' => $waliKelas],
                ['label' => 'Guru Wali', 'pegawai' => $guruWali],
            ] as $tandaTangan)
                <div class="signature">
                    <div>{{ $tandaTangan['label'] }}</div>
                    <div class="signature-space"></div>
                    <div class="signature-name">{{ $tandaTangan['pegawai']?->nama_lengkap ?: 'Belum ditentukan' }}</div>
                    <div>NIP. {{ $tandaTangan['pegawai']?->nip ?: '-' }}</div>
                </div>
            @endforeach
            <div class="signature">
                <div>Orang Tua/Wali Siswa</div>
                <div class="signature-space"></div>
                <div class="signature-name">{{ $namaOrangTua }}</div>
            </div>
        </section>

        <p class="confidential">Dokumen ini bersifat rahasia dan digunakan untuk keperluan pembinaan siswa SMP Negeri 2 Padang Panjang.</p>
    </main>
</body>
</html>
