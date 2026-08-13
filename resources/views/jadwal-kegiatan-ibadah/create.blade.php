@extends('layouts.app')

@section('title', 'Atur Jadwal Ibadah - NUSA')

@section('content')
    <style>
        .day-picker { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; }
        .day-option { display:flex; align-items:center; gap:10px; border:1px solid var(--line); border-radius:8px; padding:13px; background:#fff; cursor:pointer; }
        .day-option:has(input:checked) { border-color:var(--primary); background:var(--primary-soft); color:var(--primary-dark); font-weight:900; }
        @media(max-width:560px){.day-picker{grid-template-columns:repeat(2,minmax(0,1fr));}}
    </style>
    <div class="page-header"><div><p class="eyebrow">Kehadiran Siswa</p><h1 class="page-title">Atur Jadwal Ibadah</h1><p class="page-subtitle">Pilih beberapa hari sekaligus bila menggunakan jam yang sama.</p></div><a href="{{ route('jadwal-kegiatan-ibadah.index',['tahun_pelajaran_id'=>$tahunPelajaranId,'kegiatan_ibadah_id'=>$kegiatanIbadahId]) }}" class="button button-muted">Kembali</a></div>
    @if($errors->any())<div class="alert alert-danger"><strong>Ada data yang perlu diperbaiki.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form action="{{ route('jadwal-kegiatan-ibadah.store') }}" method="POST">@csrf
        <div class="form-shell">
            <aside class="panel panel-pad"><h2 class="panel-title">Status jadwal</h2><p class="help-text">Jadwal aktif digunakan untuk menentukan kapan kamera dapat menerima presensi.</p><label class="status-toggle"><span><span class="form-label" style="margin-bottom:0;">Jadwal aktif</span><span class="help-text">Siap digunakan</span></span><input type="hidden" name="aktif" value="0"><input type="checkbox" name="aktif" value="1" @checked(old('aktif',true))></label></aside>
            <div class="section-stack">
                <section class="panel panel-pad"><h2 class="panel-title">Kegiatan dan Tahun</h2><div class="form-grid"><div class="field"><label for="kegiatan_ibadah_id">Kegiatan ibadah</label><select id="kegiatan_ibadah_id" name="kegiatan_ibadah_id" class="select" required>@foreach($kegiatanIbadah as $item)<option value="{{ $item->id }}" @selected((int)old('kegiatan_ibadah_id',$kegiatanIbadahId)===(int)$item->id)>{{ $item->nama }}</option>@endforeach</select></div><div class="field"><label for="tahun_pelajaran_id">Tahun pelajaran</label><select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select" required>@foreach($tahunPelajaran as $item)<option value="{{ $item->id }}" @selected((int)old('tahun_pelajaran_id',$tahunPelajaranId)===(int)$item->id)>{{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}</option>@endforeach</select></div></div></section>
                <section class="panel panel-pad"><h2 class="panel-title">Hari Pelaksanaan</h2><p class="help-text" style="margin-top:7px;">Jadwal hari yang sudah ada akan diperbarui dengan waktu di bawah ini.</p><div class="day-picker" style="margin-top:15px;">@foreach($daftarHari as $kode=>$item)<label class="day-option"><input type="checkbox" name="hari[]" value="{{ $kode }}" @checked(in_array($kode,old('hari',request('hari')?[request('hari')]:[])))><span>{{ $item['label'] }}</span></label>@endforeach</div>@error('hari')<p class="error-text">{{ $message }}</p>@enderror</section>
                <section class="panel panel-pad"><h2 class="panel-title">Waktu Kegiatan</h2><div class="form-grid"><div class="field"><label for="jam_scan_mulai">Mulai scan</label><input id="jam_scan_mulai" name="jam_scan_mulai" type="time" class="input" value="{{ old('jam_scan_mulai','11:45') }}" required><p class="help-text">Kamera mulai menerima QR.</p></div><div class="field"><label for="jam_pelaksanaan">Waktu pelaksanaan</label><input id="jam_pelaksanaan" name="jam_pelaksanaan" type="time" class="input" value="{{ old('jam_pelaksanaan','12:15') }}" required><p class="help-text">Waktu utama kegiatan dimulai.</p></div><div class="field"><label for="jam_scan_selesai">Batas akhir scan</label><input id="jam_scan_selesai" name="jam_scan_selesai" type="time" class="input" value="{{ old('jam_scan_selesai','13:15') }}" required><p class="help-text">QR tidak diterima setelah waktu ini.</p></div><div class="field span-2"><label for="keterangan">Keterangan</label><textarea id="keterangan" name="keterangan" class="textarea" rows="3" placeholder="Opsional, misalnya lokasi pelaksanaan">{{ old('keterangan') }}</textarea></div></div></section>
                <div class="form-actions"><a href="{{ route('jadwal-kegiatan-ibadah.index',['tahun_pelajaran_id'=>$tahunPelajaranId,'kegiatan_ibadah_id'=>$kegiatanIbadahId]) }}" class="button button-muted">Batal</a><button type="submit" class="button button-primary">Terapkan jadwal</button></div>
            </div>
        </div>
    </form>
@endsection
