<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Orang Tua {{ $kelas->nama }} - NUSA</title>
    <style>
        :root{--primary:#15477a;--secondary:#f1c40f;--ink:#172536;--muted:#5f6f7e}
        *{box-sizing:border-box}
        body{background:#edf1f5;color:var(--ink);font-family:Arial,sans-serif;margin:0}
        .print-toolbar{align-items:center;background:#fff;border-bottom:1px solid #d5dce2;display:flex;gap:18px;justify-content:space-between;padding:14px 22px}
        .button{background:var(--primary);border:1px solid var(--primary);border-radius:6px;color:#fff;cursor:pointer;font-size:13px;font-weight:700;padding:9px 13px}
        .sheet{background:#fff;margin:22px auto;min-height:297mm;padding:12mm 14mm 10mm;width:min(210mm,calc(100% - 24px))}
        .sheet-head{align-items:center;border-bottom:3px double var(--primary);display:grid;gap:12px;grid-template-columns:62px 1fr 62px;padding-bottom:8px;text-align:center}
        .logo{height:56px;object-fit:contain;width:56px}
        .sheet-head h1{font-family:Georgia,serif;font-size:15pt;margin:0;text-transform:uppercase}
        .sheet-head h2{color:var(--primary);font-size:12pt;margin:2px 0}
        .sheet-head p{font-size:8.5pt;margin:2px 0}
        .document-title{margin:16px 0 12px;text-align:center}
        .document-title h3{font-size:13pt;margin:0;text-decoration:underline;text-transform:uppercase}
        .document-title p{font-size:9pt;margin:4px 0}
        .class-info{display:grid;font-size:9pt;grid-template-columns:95px 10px 1fr 95px 10px 1fr;margin-bottom:10px}
        .class-info div{display:contents}
        .credentials{border-collapse:collapse;font-size:8.2pt;width:100%}
        .credentials th,.credentials td{border:1px solid #8798a8;padding:4px 5px;vertical-align:middle}
        .credentials th{background:var(--primary);color:#fff;text-align:left}
        .credentials th:first-child,.credentials td:first-child{width:34px;text-align:center}
        .credentials .number{font-variant-numeric:tabular-nums;white-space:nowrap}
        .credentials .password{font-size:9.2pt;font-weight:800;letter-spacing:1px}
        .credentials .muted{color:#6b7280;font-size:8pt;font-style:italic}
        .notice{border-left:4px solid var(--secondary);color:#374151;font-size:8pt;line-height:1.45;margin-top:12px;padding:7px 10px}
        .signature{font-size:9pt;margin-left:auto;margin-top:18px;text-align:center;width:235px}
        .signature-space{height:48px}
        .signature-name{font-weight:700;text-decoration:underline}
        .footer{border-top:1px solid #ccd3da;color:var(--muted);font-size:7.5pt;margin-top:15px;padding-top:6px;text-align:center}
        @media(max-width:600px){.sheet{padding:16px}.sheet-head{grid-template-columns:46px 1fr 46px}.logo{height:42px;width:42px}.sheet-head h1{font-size:11pt}.sheet-head h2{font-size:10pt}.class-info{grid-template-columns:80px 8px 1fr}.class-info div:nth-child(n+4){display:none}.credentials{font-size:7.2pt}.credentials th,.credentials td{padding:3px}.credentials th:nth-child(3),.credentials td:nth-child(3){display:none}}
        @media print{@page{size:A4 portrait;margin:8mm}body{background:#fff}.print-toolbar{display:none}.sheet{margin:0;min-height:0;padding:0;width:auto}.credentials thead{display:table-header-group}.credentials tr{break-inside:avoid}.notice,.signature{break-inside:avoid}}
    </style>
</head>
<body>
    <div class="print-toolbar">
        <strong>Daftar akun orang tua kelas {{ $kelas->nama }} siap dicetak</strong>
        <button class="button" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <main class="sheet">
        <header class="sheet-head">
            <img class="logo" src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo sekolah">
            <div>
                <h1>SMP Negeri 2 Padang Panjang</h1>
                <h2>NUSA - Sistem Data Sekolah Terpadu</h2>
                <p>Jl. Sutan Syahrir No. 1, Silaing Bawah, Padang Panjang Barat</p>
            </div>
            <img class="logo" src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA">
        </header>

        <section class="document-title">
            <h3>Daftar Akun Orang Tua/Wali</h3>
            <p>Dokumen pembagian username dan password awal NUSA</p>
        </section>

        <section class="class-info">
            <div><span>Tahun pelajaran</span><span>:</span><strong>{{ $kelas->tahunPelajaran?->nama ?: '-' }}</strong></div>
            <div><span>Kelas</span><span>:</span><strong>{{ $kelas->nama }}</strong></div>
            <div><span>Wali kelas</span><span>:</span><strong>{{ $kelas->waliKelas?->nama_lengkap ?: 'Belum ditentukan' }}</strong></div>
            <div><span>Tanggal cetak</span><span>:</span><strong>{{ now()->locale('id')->translatedFormat('d F Y') }}</strong></div>
        </section>

        <table class="credentials">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama siswa</th>
                    <th>Orang tua/wali</th>
                    <th>Username</th>
                    <th>Password awal</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($anggotaKelas as $anggota)
                    @php($siswa = $anggota->siswa)
                    @php($orangTua = $siswa?->orangTuaWali->first(fn ($item) => (bool) $item->pivot?->utama) ?: $siswa?->orangTuaWali->first())
                    @php($akun = $orangTua?->pengguna)
                    <tr>
                        <td>{{ $anggota->nomor_absen ?: $loop->iteration }}</td>
                        <td><strong>{{ $siswa?->nama_lengkap ?: '-' }}</strong></td>
                        <td>{{ $orangTua?->nama_lengkap ?: '-' }}</td>
                        <td class="number">{{ $akun?->username ?: ($siswa?->nisn ? 'ORT-'.$siswa->nisn : '-') }}</td>
                        <td class="password">
                            @if ($akun?->kata_sandi_awal)
                                {{ $akun->kata_sandi_awal }}
                            @elseif ($akun)
                                <span class="muted">Sudah diganti</span>
                            @else
                                <span class="muted">Belum dibuat</span>
                            @endif
                        </td>
                        <td>{{ $akun ? ($akun->aktif ? 'Aktif' : 'Nonaktif') : ($siswa?->nisn ? 'Belum ada akun' : 'NISN kosong') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:18px;text-align:center">Belum ada siswa aktif pada kelas ini.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="notice">
            <strong>Rahasia.</strong> Berikan setiap username dan password hanya kepada orang tua/wali siswa yang bersangkutan.
            Password awal wajib diganti setelah login pertama. Setelah diganti, password tidak dapat dilihat kembali dan hanya dapat direset oleh administrator.
        </div>

        <section class="signature">
            <p>Padang Panjang, {{ now()->locale('id')->translatedFormat('d F Y') }}<br>Wali Kelas {{ $kelas->nama }}</p>
            <div class="signature-space"></div>
            <p class="signature-name">{{ $kelas->waliKelas?->nama_lengkap ?: '........................................' }}</p>
            <p>NIP. {{ $kelas->waliKelas?->nip ?: '................................' }}</p>
        </section>

        <p class="footer">Dokumen rahasia dibuat melalui NUSA - SMP Negeri 2 Padang Panjang.</p>
    </main>
</body>
</html>
