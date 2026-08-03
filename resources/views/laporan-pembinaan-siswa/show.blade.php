@extends('layouts.app')

@section('title', 'Detail Pembinaan & Poin - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $pengguna = auth()->user();
        $statusFinal = in_array($laporanPembinaanSiswa->status_verifikasi, ['disahkan','ditetapkan_pembinaan','tidak_terbukti','dibatalkan'], true);
        $bolehEdit = !$statusFinal && !$laporanPembinaanSiswa->berasalDariAbsensi() && ($pengguna?->memilikiIzin(['bk.kelola','poin_siswa.lapor']) ?? false);
        $bolehVerifikasiBk = $pengguna?->memilikiIzin('poin_siswa.verifikasi_bk') ?? false;
        $melaluiPemeriksaanBk = $laporanPembinaanSiswa->status_verifikasi !== 'tidak_perlu';
        $butirKeputusan = collect(old('jenis_pelanggaran_ids', $laporanPembinaanSiswa->butirPelanggaranLaporan->pluck('jenis_pelanggaran_siswa_id')->all()))->map(fn($id)=>(int)$id)->all();
        $badgeVerifikasi = fn(string $status) => match($status){'disahkan','ditetapkan_pembinaan'=>'badge badge-active','tidak_terbukti','dibatalkan'=>'badge badge-inactive','perlu_klarifikasi'=>'badge badge-danger',default=>'badge badge-warning'};
        $labelTahapBatas = 'Keputusan BK';
    @endphp

    <style>
        .point-summary { background:var(--primary); color:#fff; display:grid; gap:14px; grid-template-columns:1fr auto; }
        .point-summary strong { color:var(--secondary); font-size:38px; }
        .violation-detail-list,.decision-list,.follow-up-list { display:grid; gap:12px; margin-top:16px; }
        .violation-detail,.decision-item,.follow-up-item { background:#fff; border:1px solid var(--line); border-radius:8px; padding:14px; }
        .violation-detail { align-items:start; display:grid; gap:12px; grid-template-columns:1fr auto; }
        .decision-grid { display:grid; gap:16px; grid-template-columns:minmax(0,1fr); }
        .decision-form { border-top:1px solid var(--line); margin-top:12px; padding-top:12px; }
        .bk-violation-list { border:1px solid var(--line); border-radius:8px; display:grid; margin-top:8px; max-height:360px; overflow-y:auto; }
        .bk-violation-group-title { background:#f4f7fa; color:var(--primary-dark); font-size:12px; font-weight:800; margin:0; padding:9px 11px; text-transform:uppercase; }
        .bk-violation-option { align-items:start; border-top:1px solid var(--line); cursor:pointer; display:grid; gap:9px; grid-template-columns:18px minmax(0,1fr) auto; padding:10px 11px; }
        .bk-violation-option input { margin-top:3px; }
        .bk-violation-option small { color:var(--muted); display:block; margin-bottom:2px; }
        .bk-point-total { color:var(--primary-dark); font-size:16px; font-weight:800; margin:10px 0 0; }
        .follow-up-body { display:grid; gap:12px; grid-template-columns:repeat(2,minmax(0,1fr)); margin-top:12px; }
        .fact-list { display:grid; gap:10px; margin-top:14px; }
        .fact-item { align-items:start; border:1px solid var(--line); border-radius:8px; display:grid; gap:12px; grid-template-columns:minmax(0,1fr) auto; padding:13px; }
        .fact-item p { overflow-wrap:anywhere; }
        .fact-form { border-top:1px solid var(--line); margin-top:16px; padding-top:16px; }
        .timeline-list { border-left:2px solid #d7e2ee; display:grid; gap:0; margin:16px 0 0 8px; padding-left:20px; }
        .timeline-item { padding:0 0 18px; position:relative; }
        .timeline-item::before { background:var(--secondary); border:3px solid #fff; border-radius:50%; box-shadow:0 0 0 1px #b8c9da; content:""; height:12px; left:-27px; position:absolute; top:3px; width:12px; }
        .timeline-item:last-child { padding-bottom:0; }
        .timeline-status { color:var(--muted); font-size:12px; font-weight:700; }
        @media(max-width:900px){.decision-grid,.follow-up-body{grid-template-columns:1fr}.point-summary{grid-template-columns:1fr}.violation-detail{grid-template-columns:1fr}}
        @media(max-width:640px){.fact-item{grid-template-columns:1fr}.fact-item .actions{justify-content:flex-start}}
    </style>

    <div class="page-header">
        <div><p class="eyebrow">Kesiswaan & BK</p><h1 class="page-title">Detail {{ mb_strtolower($laporanPembinaanSiswa->labelJenisLaporan()) }}</h1><p class="page-subtitle">{{ $laporanPembinaanSiswa->nomor_laporan }}</p></div>
        <div class="actions"><a href="{{ route('laporan-pembinaan-siswa.index') }}" class="button button-muted">Kembali</a>@izin('poin_siswa.lihat','poin_siswa.verifikasi_bk')<a href="{{ route('pusat-verifikasi-pelanggaran.index') }}" class="button button-muted">Pusat Verifikasi</a>@endizin @if($bolehEdit)<a href="{{ route('laporan-pembinaan-siswa.edit',$laporanPembinaanSiswa) }}" class="button button-dark">Edit</a>@endif @izin('bk.kelola')@if($laporanPembinaanSiswa->status!=='dibatalkan')<a href="{{ route('tindak-lanjut-pembinaan-siswa.create',$laporanPembinaanSiswa) }}" class="button button-primary">Tambah tindak lanjut</a>@endif @endizin</div>
    </div>
    @if(session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif
    @if(session('gagal'))<div class="alert alert-danger">{{ session('gagal') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><strong>Ada data yang perlu diperbaiki.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($laporanPembinaanSiswa->berasalDariAbsensi())
        <div class="alert"><strong>Laporan otomatis dari absensi.</strong> Tercatat terlambat {{ $laporanPembinaanSiswa->menit_terlambat_tercatat }} menit. Perubahan waktu dilakukan melalui koreksi rekap absensi.</div>
    @endif
    @if($laporanMirip->isNotEmpty())
        <div class="alert alert-danger"><strong>Periksa kemungkinan laporan ganda.</strong> Ada {{ $laporanMirip->count() }} laporan lain untuk siswa ini pada tanggal yang sama: @foreach($laporanMirip as $mirip)<a href="{{ route('laporan-pembinaan-siswa.show',$mirip) }}">{{ $mirip->nomor_laporan }}</a>{{ !$loop->last?', ':'.' }}@endforeach</div>
    @endif

    @if($melaluiPemeriksaanBk)
        <section class="panel panel-pad point-summary" style="margin-bottom:20px;">
            <div><p class="eyebrow" style="color:#d9e8f7">Status keputusan BK</p><h2 style="margin:4px 0 8px">{{ $laporanPembinaanSiswa->labelStatusVerifikasi() }}</h2>@if($laporanPembinaanSiswa->jenis_laporan==='kejadian' && !$statusFinal)<span class="badge badge-muted">Belum diklasifikasikan</span>@else<span class="{{ $badgeVerifikasi($laporanPembinaanSiswa->status_verifikasi) }}">{{ $laporanPembinaanSiswa->labelTingkat() }}</span>@endif @if(!$statusFinal && $laporanPembinaanSiswa->batas_proses_pada)<p style="margin:12px 0 0;color:#d9e8f7">Batas {{ $labelTahapBatas }}: <strong style="color:#fff;font-size:inherit">{{ $laporanPembinaanSiswa->batas_proses_pada->format('d/m/Y H:i') }}</strong></p>@endif</div>
            <div><p style="margin:0">Hasil poin</p>@if($laporanPembinaanSiswa->status_verifikasi==='disahkan')<strong>{{ $laporanPembinaanSiswa->total_poin }}</strong><span> poin</span>@elseif($statusFinal)<strong style="font-size:24px">Tanpa poin</strong>@else<strong style="font-size:22px">Belum ditentukan</strong>@endif</div>
        </section>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile"><div class="avatar avatar-lg">{{ str($laporanPembinaanSiswa->siswa?->nama_lengkap)->substr(0,2)->upper() }}</div><h2>{{ $laporanPembinaanSiswa->siswa?->nama_lengkap }}</h2><p>NISN {{ $laporanPembinaanSiswa->siswa?->nisn ?: '-' }}</p></div>
            <dl class="quick-facts" style="margin-top:20px"><div><dt>Kelas</dt><dd>{{ $laporanPembinaanSiswa->kelas?->nama ?: '-' }}</dd></div><div><dt>Tahun</dt><dd>{{ $laporanPembinaanSiswa->tahunPelajaran?->nama ?: '-' }}</dd></div><div><dt>Wali kelas</dt><dd>{{ $laporanPembinaanSiswa->waliKelasPegawai?->nama_lengkap ?: 'Belum ditentukan' }}</dd></div><div><dt>Guru wali</dt><dd>{{ $laporanPembinaanSiswa->guruWaliPegawai?->nama_lengkap ?: 'Belum ditugaskan' }}</dd></div></dl>
            @izin('bk.kelola')@if($laporanPembinaanSiswa->status!=='dibatalkan')<form action="{{ route('laporan-pembinaan-siswa.destroy',$laporanPembinaanSiswa) }}" method="POST" style="margin-top:20px" onsubmit="return confirm('Batalkan laporan dan koreksi poinnya?')">@csrf @method('DELETE')<button class="button button-danger button-full">Batalkan laporan</button></form>@endif @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad"><h2 class="panel-title">Informasi Kejadian</h2><dl class="detail-grid"><div class="detail-item"><dt>Jenis laporan</dt><dd>{{ $laporanPembinaanSiswa->labelJenisLaporan() }}</dd></div><div class="detail-item"><dt>Sumber</dt><dd>{{ $laporanPembinaanSiswa->berasalDariAbsensi() ? 'Rekap absensi otomatis' : 'Laporan manual' }}</dd></div><div class="detail-item"><dt>Tanggal dan waktu</dt><dd>{{ $laporanPembinaanSiswa->tanggal_kejadian?->format('d/m/Y') }} {{ $laporanPembinaanSiswa->waktuKejadianRingkas()?'pukul '.$laporanPembinaanSiswa->waktuKejadianRingkas():'' }}</dd></div><div class="detail-item"><dt>Tempat</dt><dd>{{ $teks($laporanPembinaanSiswa->tempat_kejadian) }}</dd></div><div class="detail-item"><dt>Kategori</dt><dd>{{ $laporanPembinaanSiswa->kategoriPembinaanSiswa?->nama ?: '-' }}</dd></div><div class="detail-item"><dt>Pelapor</dt><dd>{{ $laporanPembinaanSiswa->pelaporPegawai?->nama_lengkap ?: ($laporanPembinaanSiswa->berasalDariAbsensi() ? 'Sistem NUSA' : '-') }}</dd></div><div class="detail-item"><dt>Dibuat pada</dt><dd>{{ $laporanPembinaanSiswa->created_at?->format('d/m/Y H:i') }}</dd></div></dl></section>

            @if($laporanPembinaanSiswa->jenis_laporan==='pelanggaran' && $laporanPembinaanSiswa->butirPelanggaranLaporan->isNotEmpty())
                <section class="panel panel-pad"><h2 class="panel-title">Butir Pelanggaran</h2><div class="violation-detail-list">@foreach($laporanPembinaanSiswa->butirPelanggaranLaporan as $butir)<article class="violation-detail"><div><p class="person-meta">{{ $butir->kode_pelanggaran }} · {{ str($butir->tingkat)->headline() }}</p><p class="person-name">{{ $butir->nama_pelanggaran }}</p></div><span class="violation-points"><strong>{{ $butir->poin }}</strong> poin</span></article>@endforeach</div></section>
            @endif

            @if($melaluiPemeriksaanBk)
                <section class="panel panel-pad"><h2 class="panel-title">Pemeriksaan dan Keputusan BK</h2><p class="help-text">BK memeriksa fakta lalu menetapkan pembinaan tanpa poin atau sanksi poin. Tidak diperlukan persetujuan pihak lain.</p>
                    <div class="decision-grid" style="margin-top:16px">
                        <article class="decision-item"><p class="person-meta">Pemeriksa dan Pemberi Keputusan</p><h3>Guru BK</h3>@if($laporanPembinaanSiswa->verifikasiBkPelanggaran->isNotEmpty())@foreach($laporanPembinaanSiswa->verifikasiBkPelanggaran as $verifikasi)<div style="margin-top:10px"><span class="badge {{ in_array($verifikasi->hasil,['sanksi_poin','pembinaan','terbukti'],true)?'badge-active':($verifikasi->hasil==='tidak_terbukti'?'badge-inactive':'badge-warning') }}">{{ $verifikasi->labelHasil() }}</span><p class="person-meta">{{ $verifikasi->bkPegawai?->nama_lengkap ?: $verifikasi->pengguna?->nama }} &middot; {{ $verifikasi->diverifikasi_pada?->format('d/m/Y H:i') }}</p><p>{{ $teks($verifikasi->catatan) }}</p></div>@endforeach @else<p class="help-text">Belum diperiksa.</p>@endif
                            @if($bolehVerifikasiBk && !$statusFinal)
                                <form method="POST" action="{{ route('verifikasi-pelanggaran.bk',$laporanPembinaanSiswa) }}" class="decision-form" data-bk-decision-form>
                                    @csrf
                                    <div class="field"><label for="hasil_keputusan_bk">Keputusan penanganan</label><select id="hasil_keputusan_bk" name="hasil" class="select" data-bk-decision required><option value="">Pilih keputusan</option>@foreach(\App\Models\VerifikasiBkPelanggaran::DAFTAR_HASIL as $kode=>$label)<option value="{{ $kode }}" @selected(old('hasil')===$kode)>{{ $label }}</option>@endforeach</select><p class="help-text">BK menentukan klasifikasi setelah memeriksa kronologi, bukti, saksi, dan klarifikasi.</p></div>
                                    <div class="field" style="margin-top:12px" data-bk-point-options>
                                        <label>Butir pelanggaran dan poin</label>
                                        <input type="search" class="input" placeholder="Cari kode atau nama pelanggaran" data-bk-violation-search>
                                        <div class="bk-violation-list">
                                            @foreach(['ringan'=>'Ringan','sedang'=>'Sedang','berat'=>'Berat'] as $tingkatKode=>$tingkatLabel)
                                                <div data-bk-violation-group>
                                                    <p class="bk-violation-group-title">Pelanggaran {{ $tingkatLabel }}</p>
                                                    @foreach($daftarJenisPelanggaranKeputusan->where('tingkat',$tingkatKode) as $jenis)
                                                        <label class="bk-violation-option" data-bk-violation-option data-search="{{ str($jenis->kode.' '.$jenis->nama)->lower() }}"><input type="checkbox" name="jenis_pelanggaran_ids[]" value="{{ $jenis->id }}" data-bk-points="{{ $jenis->poin }}" @checked(in_array($jenis->id,$butirKeputusan,true))><span><small>{{ $jenis->kode }}</small><strong>{{ $jenis->nama }}</strong></span><span class="violation-points">{{ $jenis->poin }} poin</span></label>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>
                                        <p class="bk-point-total">Total: <span data-bk-point-total>0</span> poin</p>
                                    </div>
                                    <div class="field" style="margin-top:12px"><label for="catatan_keputusan_bk">Catatan keputusan</label><textarea id="catatan_keputusan_bk" name="catatan" class="textarea" placeholder="Tuliskan pertimbangan atau arahan tindak lanjut.">{{ old('catatan') }}</textarea></div>
                                    <button class="button button-primary button-full" style="margin-top:10px">Simpan keputusan BK</button>
                                </form>
                            @endif
                        </article>
                    </div>
                </section>
            @endif

            <section class="panel panel-pad">
                <div class="page-header" style="margin-bottom:0"><div><h2 class="panel-title">Bukti Pendukung</h2><p class="help-text">File tersimpan privat dan hanya dapat dibuka oleh pengguna yang berhak melihat laporan.</p></div><span class="badge badge-muted">{{ $laporanPembinaanSiswa->buktiLaporanPembinaanSiswa->count() }} file</span></div>
                <div class="fact-list">
                    @forelse($laporanPembinaanSiswa->buktiLaporanPembinaanSiswa as $bukti)
                        @php $bolehHapusBukti=$bolehKelolaFakta && ($pengguna?->administrator() || $pengguna?->memilikiIzin(['bk.kelola','poin_siswa.verifikasi_bk']) || (int)$bukti->diunggah_oleh_pengguna_id===(int)$pengguna?->id); @endphp
                        <article class="fact-item"><div><p class="person-name">{{ $bukti->nama_file_asli }}</p><p class="person-meta">{{ str($bukti->jenis)->headline() }} &middot; {{ $bukti->ukuranRingkas() }} &middot; {{ $bukti->diunggah_pada?->format('d/m/Y H:i') }}</p>@if($bukti->keterangan)<p style="margin:7px 0 0">{{ $bukti->keterangan }}</p>@endif</div><div class="actions"><a class="button button-muted button-sm" href="{{ route('bukti-laporan-pembinaan.download',$bukti) }}">Unduh</a>@if($bolehHapusBukti)<form method="POST" action="{{ route('bukti-laporan-pembinaan.destroy',$bukti) }}" onsubmit="return confirm('Hapus bukti ini?')">@csrf @method('DELETE')<button class="button button-danger button-sm">Hapus</button></form>@endif</div></article>
                    @empty<div class="empty-state">Belum ada bukti pendukung.</div>@endforelse
                </div>
                @if($bolehKelolaFakta)
                    <form class="fact-form" method="POST" enctype="multipart/form-data" action="{{ route('bukti-laporan-pembinaan.store',$laporanPembinaanSiswa) }}">@csrf<div class="form-grid"><div class="field"><label for="bukti_laporan_detail">Tambah foto/PDF</label><input id="bukti_laporan_detail" class="input" type="file" name="bukti_laporan[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple required><p class="help-text">Maksimal 5 file, masing-masing 10 MB.</p></div><div class="field"><label for="keterangan_bukti_detail">Keterangan</label><input id="keterangan_bukti_detail" class="input" name="keterangan_bukti" placeholder="Sumber atau konteks bukti"></div></div><button class="button button-primary" style="margin-top:12px">Unggah bukti</button></form>
                @endif
            </section>

            <section class="panel panel-pad">
                <div class="page-header" style="margin-bottom:0"><div><h2 class="panel-title">Saksi Kejadian</h2><p class="help-text">Pernyataan saksi dicatat terpisah dari kronologi pelapor.</p></div><span class="badge badge-muted">{{ $laporanPembinaanSiswa->saksiLaporanPembinaanSiswa->count() }} saksi</span></div>
                <div class="fact-list">
                    @forelse($laporanPembinaanSiswa->saksiLaporanPembinaanSiswa as $saksi)
                        @php $bolehHapusSaksi=$bolehKelolaFakta && ($pengguna?->administrator() || $pengguna?->memilikiIzin(['bk.kelola','poin_siswa.verifikasi_bk']) || (int)$saksi->dibuat_oleh_pengguna_id===(int)$pengguna?->id); @endphp
                        <article class="fact-item"><div><p class="person-name">{{ $saksi->nama_saksi }}</p><p class="person-meta">{{ $saksi->labelJenis() }} &middot; dicatat {{ $saksi->created_at?->format('d/m/Y H:i') }}</p><p style="white-space:pre-line;margin:7px 0 0">{{ $saksi->pernyataan }}</p></div>@if($bolehHapusSaksi)<form method="POST" action="{{ route('saksi-laporan-pembinaan.destroy',$saksi) }}" onsubmit="return confirm('Hapus pernyataan saksi ini?')">@csrf @method('DELETE')<button class="button button-danger button-sm">Hapus</button></form>@endif</article>
                    @empty<div class="empty-state">Belum ada saksi yang dicatat.</div>@endforelse
                </div>
                @if($bolehKelolaFakta)
                    <form class="fact-form" method="POST" action="{{ route('saksi-laporan-pembinaan.store',$laporanPembinaanSiswa) }}" data-witness-form>@csrf<div class="form-grid"><div class="field"><label for="jenis_saksi">Jenis saksi</label><select id="jenis_saksi" name="jenis_saksi" class="select" data-witness-type><option value="siswa">Siswa</option><option value="pegawai">Pegawai</option><option value="lainnya">Lainnya</option></select></div><div class="field" data-witness-student><label for="saksi_siswa_id">Pilih siswa</label><select id="saksi_siswa_id" name="siswa_id" class="select"><option value="">Pilih siswa</option>@foreach($daftarSiswaSaksi as $siswaSaksi)<option value="{{ $siswaSaksi->id }}">{{ $siswaSaksi->nama_lengkap }}{{ $siswaSaksi->nisn?' - '.$siswaSaksi->nisn:'' }}</option>@endforeach</select></div><div class="field" data-witness-employee hidden><label for="saksi_pegawai_id">Pilih pegawai</label><select id="saksi_pegawai_id" name="pegawai_id" class="select"><option value="">Pilih pegawai</option>@foreach($daftarPegawaiSaksi as $pegawaiSaksi)<option value="{{ $pegawaiSaksi->id }}">{{ $pegawaiSaksi->nama_lengkap }}{{ $pegawaiSaksi->nip?' - '.$pegawaiSaksi->nip:'' }}</option>@endforeach</select></div><div class="field" data-witness-other hidden><label for="nama_saksi">Nama saksi</label><input id="nama_saksi" name="nama_saksi" class="input"></div><div class="field span-2"><label for="pernyataan_saksi">Pernyataan faktual</label><textarea id="pernyataan_saksi" name="pernyataan" class="textarea" required placeholder="Tuliskan hal yang dilihat atau didengar langsung oleh saksi."></textarea></div></div><button class="button button-primary" style="margin-top:12px">Simpan saksi</button></form>
                @endif
            </section>

            <section class="panel panel-pad">
                <div class="page-header" style="margin-bottom:0"><div><h2 class="panel-title">Klarifikasi Siswa</h2><p class="help-text">Keterangan siswa tidak mengganti kronologi pelapor dan menjadi bagian pemeriksaan fakta.</p></div></div>
                <div class="fact-list">
                    @forelse($laporanPembinaanSiswa->klarifikasiSiswaPembinaan as $klarifikasi)
                        <article class="fact-item"><div><p class="person-name">{{ $klarifikasi->labelMetode() }}</p><p class="person-meta">{{ $klarifikasi->disampaikan_pada?->format('d/m/Y H:i') }} &middot; dicatat oleh {{ $klarifikasi->dicatatOlehPengguna?->nama ?? '-' }}</p>@if($klarifikasi->pendamping)<p class="person-meta">Pendamping: {{ $klarifikasi->pendamping }}</p>@endif<p style="white-space:pre-line;margin:8px 0 0">{{ $klarifikasi->isi_klarifikasi }}</p></div></article>
                    @empty<div class="empty-state">Belum ada klarifikasi siswa.</div>@endforelse
                </div>
                @if($bolehMencatatKlarifikasi)
                    <form class="fact-form" method="POST" action="{{ route('klarifikasi-siswa-pembinaan.store',$laporanPembinaanSiswa) }}">@csrf<div class="form-grid"><div class="field"><label for="metode_klarifikasi">Metode</label><select id="metode_klarifikasi" name="metode" class="select">@foreach(\App\Models\KlarifikasiSiswaPembinaan::DAFTAR_METODE as $kode=>$label)<option value="{{ $kode }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label for="waktu_klarifikasi">Disampaikan pada</label><input id="waktu_klarifikasi" name="disampaikan_pada" type="datetime-local" class="input" value="{{ now()->format('Y-m-d\TH:i') }}" required></div><div class="field span-2"><label for="pendamping_klarifikasi">Pendamping (opsional)</label><input id="pendamping_klarifikasi" name="pendamping" class="input" placeholder="Nama orang tua, guru, atau pendamping"></div><div class="field span-2"><label for="isi_klarifikasi">Isi klarifikasi</label><textarea id="isi_klarifikasi" name="isi_klarifikasi" class="textarea" required></textarea></div></div><button class="button button-primary" style="margin-top:12px">Catat klarifikasi</button></form>
                @endif
            </section>

            <section class="panel panel-pad"><h2 class="panel-title">Linimasa Proses</h2><p class="help-text">Jejak perubahan utama disimpan berurutan dan tidak dapat diedit dari halaman ini.</p><div class="timeline-list">@forelse($laporanPembinaanSiswa->riwayatProsesPembinaanSiswa as $riwayat)<article class="timeline-item"><p class="person-name">{{ $riwayat->judul }}</p><p class="person-meta">{{ $riwayat->terjadi_pada?->format('d/m/Y H:i') }} &middot; {{ $riwayat->pengguna?->nama ?? 'Sistem' }}</p>@if($riwayat->status_sebelum!==$riwayat->status_sesudah && $riwayat->status_sesudah)<p class="timeline-status">{{ $riwayat->status_sebelum?str($riwayat->status_sebelum)->headline():'Awal' }} &rarr; {{ str($riwayat->status_sesudah)->headline() }}</p>@endif @if($riwayat->keterangan)<p style="margin:5px 0 0">{{ $riwayat->keterangan }}</p>@endif</article>@empty<div class="empty-state">Belum ada riwayat proses.</div>@endforelse</div></section>

            <section class="panel panel-pad"><h2 class="panel-title">Kronologi</h2><p style="white-space:pre-line;margin-bottom:0">{{ $laporanPembinaanSiswa->kronologi }}</p></section>
            <section class="panel panel-pad"><h2 class="panel-title">Tindakan Awal</h2><p style="white-space:pre-line;margin-bottom:0">{{ $teks($laporanPembinaanSiswa->tindakan_awal) }}</p></section>
            @izin('bk.kelola')<section class="panel panel-pad"><h2 class="panel-title">Catatan Rahasia BK</h2><p style="white-space:pre-line;margin-bottom:0">{{ $teks($laporanPembinaanSiswa->catatan_rahasia) }}</p></section>@endizin

            <section class="panel panel-pad"><div class="page-header" style="margin-bottom:0"><div><h2 class="panel-title">Riwayat Tindak Lanjut</h2><p class="help-text">Konseling, pemanggilan, mediasi, dan keputusan akhir.</p></div>@izin('bk.kelola')@if($laporanPembinaanSiswa->status!=='dibatalkan')<a href="{{ route('tindak-lanjut-pembinaan-siswa.create',$laporanPembinaanSiswa) }}" class="button button-primary">Tambah</a>@endif @endizin</div><div class="follow-up-list">@forelse($laporanPembinaanSiswa->tindakLanjutPembinaanSiswa as $tindak)<article class="follow-up-item"><div class="mobile-card-head"><div><p class="person-name">{{ $tindak->labelJenis() }}</p><p class="person-meta">{{ $tindak->tanggal_tindak_lanjut?->format('d/m/Y') }} · {{ $tindak->petugasPegawai?->nama_lengkap ?: '-' }}</p></div><span class="badge badge-muted">{{ $tindak->labelStatusLaporan() }}</span></div><div class="follow-up-body"><div><p class="person-meta">Ringkasan</p><p style="white-space:pre-line">{{ $tindak->ringkasan }}</p></div><div><p class="person-meta">Hasil</p><p style="white-space:pre-line">{{ $teks($tindak->hasil) }}</p></div></div>@izin('bk.kelola')<div class="actions"><a href="{{ route('tindak-lanjut-pembinaan-siswa.edit',$tindak) }}" class="button button-muted button-sm">Edit</a></div>@endizin</article>@empty<div class="empty-state">Belum ada tindak lanjut.</div>@endforelse</div></section>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded',()=>{
            const type=document.querySelector('[data-witness-type]');
            if(type){const student=document.querySelector('[data-witness-student]');const employee=document.querySelector('[data-witness-employee]');const other=document.querySelector('[data-witness-other]');const update=()=>{student.hidden=type.value!=='siswa';employee.hidden=type.value!=='pegawai';other.hidden=type.value!=='lainnya';student.querySelector('select').required=type.value==='siswa';employee.querySelector('select').required=type.value==='pegawai';other.querySelector('input').required=type.value==='lainnya'};type.addEventListener('change',update);update()}

            const decision=document.querySelector('[data-bk-decision]');const options=document.querySelector('[data-bk-point-options]');const checks=[...document.querySelectorAll('[data-bk-points]')];const total=document.querySelector('[data-bk-point-total]');
            if(decision&&options){const updateDecision=()=>{const pointDecision=decision.value==='sanksi_poin';options.hidden=!pointDecision;checks.forEach(check=>check.required=false);if(total)total.textContent=checks.filter(check=>check.checked).reduce((sum,check)=>sum+Number(check.dataset.bkPoints||0),0)};decision.addEventListener('change',updateDecision);checks.forEach(check=>check.addEventListener('change',updateDecision));updateDecision();const search=document.querySelector('[data-bk-violation-search]');const rows=[...document.querySelectorAll('[data-bk-violation-option]')];const groups=[...document.querySelectorAll('[data-bk-violation-group]')];search?.addEventListener('input',()=>{const keyword=search.value.toLowerCase().trim();rows.forEach(row=>row.hidden=keyword!==''&&!row.dataset.search.includes(keyword));groups.forEach(group=>group.hidden=!group.querySelector('[data-bk-violation-option]:not([hidden])'))})}
        });
    </script>
@endsection
