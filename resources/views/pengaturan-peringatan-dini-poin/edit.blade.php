@extends('layouts.app')

@section('title', 'Atur Peringatan Dini - NUSA')

@section('content')
    <style>
        .setting-section{border-bottom:1px solid var(--border);padding:0 0 20px;margin-bottom:20px}
        .setting-section:last-child{border-bottom:0;margin-bottom:0;padding-bottom:0}
        .setting-section h2{font-size:18px;margin:0 0 5px}
        .setting-section p{color:var(--muted);font-size:13px;margin:0 0 16px}
        .switch-row{align-items:center;display:flex;gap:12px}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Pengaturan Kesiswaan</p>
            <h1 class="page-title">Atur Peringatan Dini</h1>
            <p class="page-subtitle">Tahun pelajaran {{ $tahunPelajaran->nama }}</p>
        </div>
        <a class="button button-muted" href="{{ route('pengaturan-peringatan-dini-poin.index') }}">Kembali</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('pengaturan-peringatan-dini-poin.update', $tahunPelajaran) }}" class="panel panel-pad">
        @csrf
        @method('PUT')

        <section class="setting-section">
            <h2>Status otomatisasi</h2>
            <p>Menonaktifkan proses akan menyelesaikan seluruh peringatan aktif pada tahun pelajaran ini.</p>
            <label class="switch-row">
                <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $pengaturan->aktif))>
                <span>Aktifkan deteksi peringatan dini</span>
            </label>
            <label class="switch-row" style="margin-top:12px">
                <input type="checkbox" name="notifikasi_aktif" value="1" @checked(old('notifikasi_aktif', $pengaturan->notifikasi_aktif))>
                <span>Kirim notifikasi kepada BK, Wali Kelas, Guru Wali, dan pimpinan terkait</span>
            </label>
        </section>

        <section class="setting-section">
            <h2>Mendekati ambang sanksi</h2>
            <p>Peringatan muncul ketika saldo poin mencapai persentase ini terhadap ambang sanksi berikutnya.</p>
            <div class="form-grid">
                <div class="field">
                    <label for="persentase_mendekati_ambang">Persentase ambang</label>
                    <div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:9px;align-items:center">
                        <input id="persentase_mendekati_ambang" class="input" type="number" min="50" max="99" name="persentase_mendekati_ambang" value="{{ old('persentase_mendekati_ambang', $pengaturan->persentase_mendekati_ambang) }}" required>
                        <strong>%</strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="setting-section">
            <h2>Pelanggaran berulang</h2>
            <p>Hanya laporan yang sudah disahkan yang dihitung.</p>
            <div class="form-grid">
                <div class="field">
                    <label for="jumlah_pelanggaran_berulang">Jumlah pelanggaran minimum</label>
                    <input id="jumlah_pelanggaran_berulang" class="input" type="number" min="2" max="20" name="jumlah_pelanggaran_berulang" value="{{ old('jumlah_pelanggaran_berulang', $pengaturan->jumlah_pelanggaran_berulang) }}" required>
                </div>
                <div class="field">
                    <label for="periode_pelanggaran_hari">Dalam periode (hari)</label>
                    <input id="periode_pelanggaran_hari" class="input" type="number" min="7" max="365" name="periode_pelanggaran_hari" value="{{ old('periode_pelanggaran_hari', $pengaturan->periode_pelanggaran_hari) }}" required>
                </div>
            </div>
        </section>

        <section class="setting-section">
            <h2>Keterlambatan berulang</h2>
            <p>Penghitungan berasal langsung dari rekap presensi siswa.</p>
            <div class="form-grid">
                <div class="field">
                    <label for="jumlah_keterlambatan_berulang">Jumlah keterlambatan minimum</label>
                    <input id="jumlah_keterlambatan_berulang" class="input" type="number" min="2" max="30" name="jumlah_keterlambatan_berulang" value="{{ old('jumlah_keterlambatan_berulang', $pengaturan->jumlah_keterlambatan_berulang) }}" required>
                </div>
                <div class="field">
                    <label for="periode_keterlambatan_hari">Dalam periode (hari)</label>
                    <input id="periode_keterlambatan_hari" class="input" type="number" min="7" max="365" name="periode_keterlambatan_hari" value="{{ old('periode_keterlambatan_hari', $pengaturan->periode_keterlambatan_hari) }}" required>
                </div>
            </div>
        </section>

        <div class="actions" style="justify-content:flex-end">
            <a class="button button-muted" href="{{ route('pengaturan-peringatan-dini-poin.index') }}">Batal</a>
            <button class="button button-primary">Simpan Pengaturan</button>
        </div>
    </form>
@endsection
