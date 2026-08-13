@extends('layouts.app')

@section('title', 'Edit Guru Piket - NUSA')

@section('content')
    <style>
        .field-full { grid-column:1 / -1; }
        .check-row { display:flex; align-items:flex-start; gap:10px; border:1px solid var(--line); border-radius:8px; padding:13px; background:var(--soft); }
        .check-row input { margin-top:3px; }.check-row strong,.check-row small { display:block; }.check-row small { margin-top:3px; color:var(--muted); }
    </style>
    <div class="page-header">
        <div><p class="eyebrow">Kehadiran Siswa</p><h1 class="page-title">Edit Guru Piket</h1><p class="page-subtitle">Perbarui hari, guru, atau status jadwal.</p></div>
        <a href="{{ route('jadwal-piket-guru.index', ['tahun_pelajaran_id' => $jadwalPiketGuru->tahun_pelajaran_id]) }}" class="button button-muted">Kembali</a>
    </div>

    @if ($errors->any())<div class="alert alert-danger"><strong>Data belum dapat disimpan.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form action="{{ route('jadwal-piket-guru.update', $jadwalPiketGuru) }}" method="POST">
        @csrf
        @method('PUT')
        <section class="panel panel-pad" style="max-width:820px;">
            <div class="form-grid">
                <div class="field">
                    <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                    <input type="hidden" id="tahun_pelajaran_id" name="tahun_pelajaran_id" value="{{ $jadwalPiketGuru->tahun_pelajaran_id }}">
                    <input type="text" class="input" value="{{ $tahunPelajaran->firstWhere('id', $jadwalPiketGuru->tahun_pelajaran_id)?->nama ?? '-' }}" readonly>
                    <p class="help-text">Tahun pelajaran tidak dipindahkan saat mengedit. Buat jadwal baru untuk tahun lainnya.</p>
                </div>
                <div class="field"><label for="hari">Hari</label><select id="hari" name="hari" class="select" required>@foreach ($daftarHari as $kode => $label)<option value="{{ $kode }}" @selected(old('hari', $jadwalPiketGuru->hari) === $kode)>{{ $label }}</option>@endforeach</select></div>
                <div class="field field-full"><label for="pegawai_id">Guru mata pelajaran</label><select id="pegawai_id" name="pegawai_id" class="select" required>@foreach ($guruMapel as $guru)<option value="{{ $guru->id }}" @selected((int) old('pegawai_id', $jadwalPiketGuru->pegawai_id) === (int) $guru->id)>{{ $guru->nama_lengkap }}{{ $guru->nip ? ' - '.$guru->nip : '' }}</option>@endforeach</select></div>
                <div class="field field-full"><label for="keterangan">Keterangan</label><textarea id="keterangan" name="keterangan" class="textarea" rows="3">{{ old('keterangan', $jadwalPiketGuru->keterangan) }}</textarea></div>
                <label class="check-row field-full"><input type="checkbox" name="aktif" value="1" @checked(old('aktif', $jadwalPiketGuru->aktif))><span><strong>Jadwal aktif</strong><small>Nonaktifkan jika penugasan dihentikan sementara.</small></span></label>
            </div>
        </section>
        <div class="actions" style="margin-top:18px;"><button type="submit" class="button button-primary">Simpan perubahan</button><a href="{{ route('jadwal-piket-guru.index', ['tahun_pelajaran_id' => $jadwalPiketGuru->tahun_pelajaran_id]) }}" class="button button-muted">Batal</a></div>
    </form>
@endsection
