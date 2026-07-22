@extends('layouts.app')

@section('title', 'Detail Pembinaan & Poin - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $pengguna = auth()->user();
        $statusFinal = in_array($laporanPembinaanSiswa->status_verifikasi, ['disahkan','tidak_terbukti','dibatalkan'], true);
        $bolehEdit = !$statusFinal && ($pengguna?->memilikiIzin(['bk.kelola','poin_siswa.lapor']) ?? false);
        $bolehVerifikasiBk = $pengguna?->memilikiIzin('poin_siswa.verifikasi_bk') ?? false;
        $pegawaiId = (int) ($pengguna?->pegawai_id ?? 0);
        $adalahWaliKelas = $pegawaiId > 0 && $pegawaiId === (int) $laporanPembinaanSiswa->wali_kelas_pegawai_id;
        $adalahGuruWali = $pegawaiId > 0 && $pegawaiId === (int) $laporanPembinaanSiswa->guru_wali_pegawai_id;
        $bolehPutusKonflik = $pengguna?->memilikiIzin('poin_siswa.putus_konflik') ?? false;
        $sudahDiperiksaTerbukti = $laporanPembinaanSiswa->verifikasiBkPelanggaran->first()?->hasil === 'terbukti';
        $persetujuan = $laporanPembinaanSiswa->persetujuanPelanggaran->keyBy('jenis_persetujuan');
        $badgeVerifikasi = fn(string $status) => match($status){'disahkan'=>'badge badge-active','tidak_terbukti','dibatalkan'=>'badge badge-inactive','perlu_musyawarah','perlu_klarifikasi'=>'badge badge-danger',default=>'badge badge-warning'};
    @endphp

    <style>
        .point-summary { background:var(--primary); color:#fff; display:grid; gap:14px; grid-template-columns:1fr auto; }
        .point-summary strong { color:var(--secondary); font-size:38px; }
        .violation-detail-list,.decision-list,.follow-up-list { display:grid; gap:12px; margin-top:16px; }
        .violation-detail,.decision-item,.follow-up-item { background:#fff; border:1px solid var(--line); border-radius:8px; padding:14px; }
        .violation-detail { align-items:start; display:grid; gap:12px; grid-template-columns:1fr auto; }
        .decision-grid { display:grid; gap:16px; grid-template-columns:repeat(3,minmax(0,1fr)); }
        .decision-form { border-top:1px solid var(--line); margin-top:12px; padding-top:12px; }
        .follow-up-body { display:grid; gap:12px; grid-template-columns:repeat(2,minmax(0,1fr)); margin-top:12px; }
        @media(max-width:900px){.decision-grid,.follow-up-body{grid-template-columns:1fr}.point-summary{grid-template-columns:1fr}.violation-detail{grid-template-columns:1fr}}
    </style>

    <div class="page-header">
        <div><p class="eyebrow">Kesiswaan & BK</p><h1 class="page-title">Detail {{ mb_strtolower($laporanPembinaanSiswa->labelJenisLaporan()) }}</h1><p class="page-subtitle">{{ $laporanPembinaanSiswa->nomor_laporan }}</p></div>
        <div class="actions"><a href="{{ route('laporan-pembinaan-siswa.index') }}" class="button button-muted">Kembali</a>@if($bolehEdit)<a href="{{ route('laporan-pembinaan-siswa.edit',$laporanPembinaanSiswa) }}" class="button button-dark">Edit</a>@endif @izin('bk.kelola')@if($laporanPembinaanSiswa->status!=='dibatalkan')<a href="{{ route('tindak-lanjut-pembinaan-siswa.create',$laporanPembinaanSiswa) }}" class="button button-primary">Tambah tindak lanjut</a>@endif @endizin</div>
    </div>
    @if(session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif
    @if(session('gagal'))<div class="alert alert-danger">{{ session('gagal') }}</div>@endif

    @if($laporanPembinaanSiswa->jenis_laporan==='pelanggaran')
        <section class="panel panel-pad point-summary" style="margin-bottom:20px;">
            <div><p class="eyebrow" style="color:#d9e8f7">Status penetapan</p><h2 style="margin:4px 0 8px">{{ $laporanPembinaanSiswa->labelStatusVerifikasi() }}</h2><span class="{{ $badgeVerifikasi($laporanPembinaanSiswa->status_verifikasi) }}">{{ $laporanPembinaanSiswa->labelTingkat() }}</span></div>
            <div><p style="margin:0">Total laporan</p><strong>{{ $laporanPembinaanSiswa->total_poin }}</strong><span> poin</span></div>
        </section>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile"><div class="avatar avatar-lg">{{ str($laporanPembinaanSiswa->siswa?->nama_lengkap)->substr(0,2)->upper() }}</div><h2>{{ $laporanPembinaanSiswa->siswa?->nama_lengkap }}</h2><p>NISN {{ $laporanPembinaanSiswa->siswa?->nisn ?: '-' }}</p></div>
            <dl class="quick-facts" style="margin-top:20px"><div><dt>Kelas</dt><dd>{{ $laporanPembinaanSiswa->kelas?->nama ?: '-' }}</dd></div><div><dt>Tahun</dt><dd>{{ $laporanPembinaanSiswa->tahunPelajaran?->nama ?: '-' }}</dd></div><div><dt>Wali kelas</dt><dd>{{ $laporanPembinaanSiswa->waliKelasPegawai?->nama_lengkap ?: 'Belum ditentukan' }}</dd></div><div><dt>Guru wali</dt><dd>{{ $laporanPembinaanSiswa->guruWaliPegawai?->nama_lengkap ?: 'Belum ditugaskan' }}</dd></div></dl>
            @izin('bk.kelola')@if($laporanPembinaanSiswa->status!=='dibatalkan')<form action="{{ route('laporan-pembinaan-siswa.destroy',$laporanPembinaanSiswa) }}" method="POST" style="margin-top:20px" onsubmit="return confirm('Batalkan laporan dan koreksi poinnya?')">@csrf @method('DELETE')<button class="button button-danger button-full">Batalkan laporan</button></form>@endif @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad"><h2 class="panel-title">Informasi Kejadian</h2><dl class="detail-grid"><div class="detail-item"><dt>Jenis laporan</dt><dd>{{ $laporanPembinaanSiswa->labelJenisLaporan() }}</dd></div><div class="detail-item"><dt>Tanggal dan waktu</dt><dd>{{ $laporanPembinaanSiswa->tanggal_kejadian?->format('d/m/Y') }} {{ $laporanPembinaanSiswa->waktuKejadianRingkas()?'pukul '.$laporanPembinaanSiswa->waktuKejadianRingkas():'' }}</dd></div><div class="detail-item"><dt>Tempat</dt><dd>{{ $teks($laporanPembinaanSiswa->tempat_kejadian) }}</dd></div><div class="detail-item"><dt>Kategori</dt><dd>{{ $laporanPembinaanSiswa->kategoriPembinaanSiswa?->nama ?: '-' }}</dd></div><div class="detail-item"><dt>Pelapor</dt><dd>{{ $laporanPembinaanSiswa->pelaporPegawai?->nama_lengkap ?: '-' }}</dd></div><div class="detail-item"><dt>Dibuat pada</dt><dd>{{ $laporanPembinaanSiswa->created_at?->format('d/m/Y H:i') }}</dd></div></dl></section>

            @if($laporanPembinaanSiswa->jenis_laporan==='pelanggaran')
                <section class="panel panel-pad"><h2 class="panel-title">Butir Pelanggaran</h2><div class="violation-detail-list">@foreach($laporanPembinaanSiswa->butirPelanggaranLaporan as $butir)<article class="violation-detail"><div><p class="person-meta">{{ $butir->kode_pelanggaran }} · {{ str($butir->tingkat)->headline() }}</p><p class="person-name">{{ $butir->nama_pelanggaran }}</p></div><span class="violation-points"><strong>{{ $butir->poin }}</strong> poin</span></article>@endforeach</div></section>

                @if(!$laporanPembinaanSiswa->wali_kelas_pegawai_id || !$laporanPembinaanSiswa->guru_wali_pegawai_id)
                    <div class="alert alert-danger">Persetujuan belum dapat lengkap karena {{ !$laporanPembinaanSiswa->wali_kelas_pegawai_id?'wali kelas':'' }}{{ !$laporanPembinaanSiswa->wali_kelas_pegawai_id && !$laporanPembinaanSiswa->guru_wali_pegawai_id?' dan ':'' }}{{ !$laporanPembinaanSiswa->guru_wali_pegawai_id?'guru wali':'' }} belum ditentukan.</div>
                @endif

                <section class="panel panel-pad"><h2 class="panel-title">Pemeriksaan dan Persetujuan</h2><p class="help-text">BK memastikan fakta. Poin berlaku setelah disetujui oleh dua pegawai berbeda.</p>
                    <div class="decision-grid" style="margin-top:16px">
                        <article class="decision-item"><p class="person-meta">Pemeriksa Fakta</p><h3>BK</h3>@if($laporanPembinaanSiswa->verifikasiBkPelanggaran->isNotEmpty())@foreach($laporanPembinaanSiswa->verifikasiBkPelanggaran as $verifikasi)<div style="margin-top:10px"><span class="badge {{ $verifikasi->hasil==='terbukti'?'badge-active':($verifikasi->hasil==='tidak_terbukti'?'badge-inactive':'badge-warning') }}">{{ $verifikasi->labelHasil() }}</span><p class="person-meta">{{ $verifikasi->bkPegawai?->nama_lengkap ?: $verifikasi->pengguna?->nama }} · {{ $verifikasi->diverifikasi_pada?->format('d/m/Y H:i') }}</p><p>{{ $teks($verifikasi->catatan) }}</p></div>@endforeach @else<p class="help-text">Belum diperiksa.</p>@endif
                            @if($bolehVerifikasiBk && !$statusFinal)<form method="POST" action="{{ route('verifikasi-pelanggaran.bk',$laporanPembinaanSiswa) }}" class="decision-form">@csrf<div class="field"><label>Hasil pemeriksaan</label><select name="hasil" class="select">@foreach(\App\Models\VerifikasiBkPelanggaran::DAFTAR_HASIL as $kode=>$label)<option value="{{ $kode }}">{{ $label }}</option>@endforeach</select></div><div class="field" style="margin-top:8px"><label>Catatan</label><textarea name="catatan" class="textarea"></textarea></div><button class="button button-primary button-full" style="margin-top:10px">Simpan pemeriksaan</button></form>@endif
                        </article>
                        @foreach(['wali_kelas'=>'Wali Kelas','guru_wali'=>'Guru Wali'] as $jenis=>$label)
                            @php $putusan=$persetujuan->get($jenis);$boleh=($jenis==='wali_kelas'&&$adalahWaliKelas)||($jenis==='guru_wali'&&$adalahGuruWali); @endphp
                            <article class="decision-item"><p class="person-meta">Pemberi Persetujuan</p><h3>{{ $label }}</h3><p class="person-name">{{ $jenis==='wali_kelas'?($laporanPembinaanSiswa->waliKelasPegawai?->nama_lengkap?:'Belum ditentukan'):($laporanPembinaanSiswa->guruWaliPegawai?->nama_lengkap?:'Belum ditugaskan') }}</p>@if($putusan)<span class="badge {{ $putusan->keputusan==='setuju'?'badge-active':'badge-danger' }}">{{ \App\Models\PersetujuanPelanggaran::DAFTAR_KEPUTUSAN[$putusan->keputusan] }}</span><p>{{ $teks($putusan->catatan) }}</p>@else<p class="help-text">Belum memberi keputusan.</p>@endif
                                @if($boleh && $sudahDiperiksaTerbukti && !$statusFinal)<form method="POST" action="{{ route('verifikasi-pelanggaran.persetujuan',$laporanPembinaanSiswa) }}" class="decision-form">@csrf<input type="hidden" name="jenis_persetujuan" value="{{ $jenis }}"><div class="field"><label>Keputusan</label><select name="keputusan" class="select"><option value="setuju">Setuju</option><option value="tidak_setuju">Tidak setuju</option></select></div><div class="field" style="margin-top:8px"><label>Pertimbangan</label><textarea name="catatan" class="textarea" required></textarea></div><button class="button button-primary button-full" style="margin-top:10px">Kirim keputusan</button></form>@endif
                            </article>
                        @endforeach
                    </div>
                    @if($bolehPutusKonflik && $sudahDiperiksaTerbukti && !$statusFinal)
                        @php $putusanWakil=$persetujuan->get('wakil_kesiswaan'); @endphp
                        <article class="decision-item" style="margin-top:16px"><p class="person-meta">Musyawarah/Pengganti</p><h3>Wakil Kesiswaan</h3>@if($putusanWakil)<span class="badge {{ $putusanWakil->keputusan==='setuju'?'badge-active':'badge-danger' }}">{{ \App\Models\PersetujuanPelanggaran::DAFTAR_KEPUTUSAN[$putusanWakil->keputusan] }}</span><p>{{ $teks($putusanWakil->catatan) }}</p>@endif<form method="POST" action="{{ route('verifikasi-pelanggaran.persetujuan',$laporanPembinaanSiswa) }}" class="decision-form">@csrf<input type="hidden" name="jenis_persetujuan" value="wakil_kesiswaan"><div class="form-grid"><div class="field"><label>Keputusan pengganti</label><select name="keputusan" class="select"><option value="setuju">Setuju</option><option value="tidak_setuju">Tidak setuju</option></select></div><div class="field"><label>Catatan musyawarah</label><textarea name="catatan" class="textarea" required></textarea></div></div><button class="button button-dark" style="margin-top:10px">Simpan keputusan musyawarah</button></form></article>
                    @endif
                </section>
            @endif

            <section class="panel panel-pad"><h2 class="panel-title">Kronologi</h2><p style="white-space:pre-line;margin-bottom:0">{{ $laporanPembinaanSiswa->kronologi }}</p></section>
            <section class="panel panel-pad"><h2 class="panel-title">Tindakan Awal</h2><p style="white-space:pre-line;margin-bottom:0">{{ $teks($laporanPembinaanSiswa->tindakan_awal) }}</p></section>
            @izin('bk.kelola')<section class="panel panel-pad"><h2 class="panel-title">Catatan Rahasia BK</h2><p style="white-space:pre-line;margin-bottom:0">{{ $teks($laporanPembinaanSiswa->catatan_rahasia) }}</p></section>@endizin

            <section class="panel panel-pad"><div class="page-header" style="margin-bottom:0"><div><h2 class="panel-title">Riwayat Tindak Lanjut</h2><p class="help-text">Konseling, pemanggilan, mediasi, dan keputusan akhir.</p></div>@izin('bk.kelola')@if($laporanPembinaanSiswa->status!=='dibatalkan')<a href="{{ route('tindak-lanjut-pembinaan-siswa.create',$laporanPembinaanSiswa) }}" class="button button-primary">Tambah</a>@endif @endizin</div><div class="follow-up-list">@forelse($laporanPembinaanSiswa->tindakLanjutPembinaanSiswa as $tindak)<article class="follow-up-item"><div class="mobile-card-head"><div><p class="person-name">{{ $tindak->labelJenis() }}</p><p class="person-meta">{{ $tindak->tanggal_tindak_lanjut?->format('d/m/Y') }} · {{ $tindak->petugasPegawai?->nama_lengkap ?: '-' }}</p></div><span class="badge badge-muted">{{ $tindak->labelStatusLaporan() }}</span></div><div class="follow-up-body"><div><p class="person-meta">Ringkasan</p><p style="white-space:pre-line">{{ $tindak->ringkasan }}</p></div><div><p class="person-meta">Hasil</p><p style="white-space:pre-line">{{ $teks($tindak->hasil) }}</p></div></div>@izin('bk.kelola')<div class="actions"><a href="{{ route('tindak-lanjut-pembinaan-siswa.edit',$tindak) }}" class="button button-muted button-sm">Edit</a></div>@endizin</article>@empty<div class="empty-state">Belum ada tindak lanjut.</div>@endforelse</div></section>
        </div>
    </div>
@endsection
