@extends('layouts.app')

@section('title', 'Buat Surat Orang Tua - NUSA')

@section('content')
    <style>
        .letter-student{background:#eef4f9;border:1px solid #d7e3ed;border-radius:8px;display:grid;gap:14px;grid-template-columns:minmax(0,1fr) auto;margin-bottom:20px;padding:16px 18px}
        .letter-student strong{color:var(--primary-dark);display:block;font-size:17px}
        .letter-student span{color:var(--muted);display:block;font-size:13px;line-height:1.5;margin-top:4px}
        .letter-points{align-self:center;background:#fff;border:1px solid var(--border);border-radius:6px;color:var(--primary);font-size:14px;font-weight:800;padding:9px 12px}
        .letter-grid{display:grid;gap:18px;grid-template-columns:repeat(2,minmax(0,1fr))}
        .letter-full{grid-column:1/-1}
        @media(max-width:720px){.letter-student{grid-template-columns:1fr}.letter-grid{grid-template-columns:1fr}.letter-full{grid-column:auto}}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Buat Surat Orang Tua</h1>
            <p class="page-subtitle">Data siswa dan poin diisi otomatis. Lengkapi hanya informasi surat yang diperlukan.</p>
        </div>
        <a class="button button-muted" href="{{ route('rekap-poin-siswa.show', ['siswa' => $siswa, 'tahun_pelajaran_id' => $tahunPelajaran->id]) }}">Kembali</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">Periksa kembali data surat yang belum lengkap.</div>
    @endif

    <div class="letter-student">
        <div>
            <strong>{{ $siswa->nama_lengkap }}</strong>
            <span>{{ $anggotaKelas?->kelas?->nama ?: '-' }} &middot; NISN {{ $siswa->nisn ?: '-' }} &middot; Tahun {{ $tahunPelajaran->nama }}</span>
            <span>Wali Kelas: {{ $waliKelas?->nama_lengkap ?: '-' }} &middot; Guru Wali: {{ $guruWali?->nama_lengkap ?: '-' }}</span>
        </div>
        <div class="letter-points">{{ $totalPoinTerkini }} poin resmi</div>
    </div>

    <form method="GET" action="{{ route('dokumen-poin-siswa.cetak-surat', $siswa) }}" target="_blank" class="panel panel-pad">
        <div class="letter-grid">
            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    @foreach($daftarTahunPelajaran as $tahun)
                        <option value="{{ $tahun->id }}" @selected((int)old('tahun_pelajaran_id', $tahunPelajaran->id) === $tahun->id)>{{ $tahun->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="jenis_surat">Jenis surat</label>
                <select id="jenis_surat" name="jenis_surat" class="select">
                    <option value="pemberitahuan" @selected(old('jenis_surat', $nilaiAwal['jenis_surat']) === 'pemberitahuan')>Surat Pemberitahuan Poin</option>
                    <option value="pemanggilan" @selected(old('jenis_surat', $nilaiAwal['jenis_surat']) === 'pemanggilan')>Surat Pemanggilan Orang Tua</option>
                </select>
            </div>
            <div class="field">
                <label for="nomor_surat">Nomor surat</label>
                <input id="nomor_surat" name="nomor_surat" class="input" value="{{ old('nomor_surat') }}" placeholder="Contoh: 421.3/123/SMPN2-PP/2026">
            </div>
            <div class="field">
                <label for="tanggal_surat">Tanggal surat</label>
                <input id="tanggal_surat" type="date" name="tanggal_surat" class="input" value="{{ old('tanggal_surat', $nilaiAwal['tanggal_surat']) }}" required>
            </div>
            <div class="field">
                <label for="nama_penerima">Nama orang tua/wali</label>
                <input id="nama_penerima" name="nama_penerima" class="input" value="{{ old('nama_penerima', $nilaiAwal['nama_penerima']) }}" required>
            </div>
            <div class="field">
                <label for="alamat_penerima">Alamat</label>
                <input id="alamat_penerima" name="alamat_penerima" class="input" value="{{ old('alamat_penerima', $nilaiAwal['alamat_penerima']) }}">
            </div>
            <div class="field" data-pemanggilan>
                <label for="tanggal_pertemuan">Tanggal pertemuan</label>
                <input id="tanggal_pertemuan" type="date" name="tanggal_pertemuan" class="input" value="{{ old('tanggal_pertemuan') }}">
            </div>
            <div class="field" data-pemanggilan>
                <label for="jam_pertemuan">Jam pertemuan</label>
                <input id="jam_pertemuan" type="time" name="jam_pertemuan" class="input" value="{{ old('jam_pertemuan') }}">
            </div>
            <div class="field letter-full" data-pemanggilan>
                <label for="tempat_pertemuan">Tempat pertemuan</label>
                <input id="tempat_pertemuan" name="tempat_pertemuan" class="input" value="{{ old('tempat_pertemuan', $nilaiAwal['tempat_pertemuan']) }}">
            </div>
            <div class="field letter-full" data-pemanggilan>
                <label for="keperluan">Keperluan</label>
                <textarea id="keperluan" name="keperluan" class="textarea">{{ old('keperluan', $nilaiAwal['keperluan']) }}</textarea>
            </div>
            <div class="field letter-full">
                <label for="catatan_tambahan">Catatan tambahan (opsional)</label>
                <textarea id="catatan_tambahan" name="catatan_tambahan" class="textarea" placeholder="Tambahkan pesan khusus bila diperlukan.">{{ old('catatan_tambahan') }}</textarea>
            </div>
        </div>
        <div class="actions" style="justify-content:flex-end;margin-top:20px">
            <button class="button button-primary">Tampilkan Surat</button>
        </div>
    </form>

    <script>
        (() => {
            const jenis = document.getElementById('jenis_surat');
            const fieldPemanggilan = document.querySelectorAll('[data-pemanggilan]');
            const sinkronkan = () => {
                const tampil = jenis.value === 'pemanggilan';
                fieldPemanggilan.forEach((field) => {
                    field.hidden = !tampil;
                    field.querySelectorAll('input, textarea').forEach((input) => input.required = tampil);
                });
            };
            jenis.addEventListener('change', sinkronkan);
            sinkronkan();
        })();
    </script>
@endsection
