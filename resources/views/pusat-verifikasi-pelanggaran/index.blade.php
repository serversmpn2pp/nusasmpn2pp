@extends('layouts.app')

@section('title', 'Pemeriksaan & Pengesahan Laporan - NUSA')

@section('content')
    @php
        $badgeStatus = fn (string $status) => match ($status) {
            'disahkan', 'ditetapkan_pembinaan' => 'badge badge-active',
            'tidak_terbukti', 'dibatalkan' => 'badge badge-inactive',
            'perlu_klarifikasi', 'dikembalikan_bk' => 'badge badge-danger',
            default => 'badge badge-warning',
        };
    @endphp

    <style>
        .verification-stats { display:grid; gap:14px; grid-template-columns:repeat(5,minmax(0,1fr)); margin-bottom:20px; }
        .verification-stat { color:inherit; min-width:0; padding:16px; text-decoration:none; }
        .verification-stat.active { border-color:var(--primary); box-shadow:inset 0 3px 0 var(--secondary); }
        .verification-stat-label { color:var(--muted); font-size:12px; font-weight:800; margin:0; text-transform:uppercase; }
        .verification-stat-value { color:var(--primary-dark); font-size:30px; font-weight:800; margin:5px 0 0; }
        .verification-filter { align-items:end; display:grid; gap:12px; grid-template-columns:minmax(220px,1.4fr) minmax(190px,.8fr) auto; }
        .verification-list { display:grid; }
        .verification-row { border-bottom:1px solid var(--line); display:grid; gap:18px; grid-template-columns:minmax(210px,.9fr) minmax(280px,1.3fr) minmax(170px,.7fr) auto; min-width:0; padding:18px; }
        .verification-row:last-child { border-bottom:0; }
        .verification-row > * { min-width:0; }
        .verification-name { font-size:16px; font-weight:800; margin:3px 0; overflow-wrap:anywhere; }
        .verification-task { color:var(--primary-dark); font-size:14px; font-weight:800; margin:8px 0 0; }
        .verification-readonly { color:#725900; font-size:12px; font-weight:750; margin:7px 0 0; }
        .verification-flow { display:grid; gap:6px; grid-template-columns:repeat(2,minmax(0,1fr)); margin-top:12px; }
        .verification-step { background:#edf1f5; border-radius:6px; color:var(--muted); font-size:11px; font-weight:800; padding:7px 5px; text-align:center; }
        .verification-step.done { background:#e6f4ec; color:#21643c; }
        .verification-step.current { background:#fff3b0; color:#665100; outline:1px solid #e2bd00; }
        .fact-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
        .fact-chip { background:#eef3f8; border-radius:5px; color:#38536d; font-size:11px; font-weight:700; padding:5px 7px; }
        .fact-chip.missing { background:#f5f6f8; color:#7a8793; }
        .verification-age { font-size:13px; margin-top:9px; }
        .verification-age.late { color:#a12828; font-weight:800; }
        .verification-actions { align-content:start; display:grid; gap:8px; justify-items:end; }
        .verification-actions .button { white-space:nowrap; }
        @media(max-width:1120px){.verification-stats{grid-template-columns:repeat(3,minmax(0,1fr))}.verification-row{grid-template-columns:minmax(200px,.8fr) minmax(280px,1.2fr) minmax(150px,.7fr)}.verification-actions{grid-column:1/-1;display:flex;justify-content:flex-end}}
        @media(max-width:760px){.verification-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.verification-filter{grid-template-columns:1fr}.verification-filter .actions{justify-content:stretch}.verification-filter .button{flex:1}.verification-row{grid-template-columns:1fr;padding:16px}.verification-actions{grid-column:auto;justify-content:stretch}.verification-actions .button{width:100%}.verification-flow{grid-template-columns:1fr}.page-header .actions{width:100%}}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Pemeriksaan & Pengesahan Laporan</h1>
            <p class="page-subtitle">BK memeriksa laporan; Wakil Kesiswaan mengesahkan rekomendasi pelanggaran berpoin.</p>
        </div>
        <div class="actions"><a href="{{ route('laporan-pembinaan-siswa.index') }}" class="button button-muted">Daftar semua laporan</a></div>
    </div>

    <x-alur-penanganan-siswa
        :tahap="$hakAksi['wakil'] && !$hakAksi['bk'] ? 'pengesahan' : 'pemeriksaan'"
        judul="BK memeriksa, Wakil Kesiswaan mengesahkan poin"
        deskripsi="BK memeriksa fakta dan memilih pembinaan, rekomendasi poin, atau tidak terbukti. Pembinaan dan tidak terbukti selesai di BK. Khusus rekomendasi poin, Wakil Kesiswaan mengesahkan atau mengembalikannya kepada BK dengan catatan."
        catatan="Poin belum resmi sebelum disahkan Wakil Kesiswaan."
    />

    @if(session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif

    <div class="verification-stats">
        <a class="panel verification-stat {{ $antrean==='semua'?'active':'' }}" href="{{ route('pusat-verifikasi-pelanggaran.index') }}"><p class="verification-stat-label">Perlu diperiksa</p><p class="verification-stat-value">{{ $ringkasan['aktif'] }}</p></a>
        <a class="panel verification-stat {{ $antrean==='bk'?'active':'' }}" href="{{ route('pusat-verifikasi-pelanggaran.index',['antrean'=>'bk']) }}"><p class="verification-stat-label">Menunggu keputusan BK</p><p class="verification-stat-value">{{ $ringkasan['bk'] }}</p></a>
        <a class="panel verification-stat {{ $antrean==='wakil'?'active':'' }}" href="{{ route('pusat-verifikasi-pelanggaran.index',['antrean'=>'wakil']) }}"><p class="verification-stat-label">Menunggu pengesahan Wakil</p><p class="verification-stat-value">{{ $ringkasan['wakil'] }}</p></a>
        <a class="panel verification-stat {{ $antrean==='terlambat'?'active':'' }}" href="{{ route('pusat-verifikasi-pelanggaran.index',['antrean'=>'terlambat']) }}"><p class="verification-stat-label">Terlambat diproses</p><p class="verification-stat-value">{{ $ringkasan['terlambat'] }}</p></a>
        <a class="panel verification-stat {{ $antrean==='selesai'?'active':'' }}" href="{{ route('pusat-verifikasi-pelanggaran.index',['antrean'=>'selesai']) }}"><p class="verification-stat-label">Selesai diperiksa</p><p class="verification-stat-value">{{ $ringkasan['selesai'] }}</p></a>
    </div>

    <form method="GET" class="panel panel-pad" style="margin-bottom:20px">
        <div class="verification-filter">
            <div class="field"><label for="kata_kunci">Cari laporan</label><input id="kata_kunci" name="kata_kunci" value="{{ $kataKunci }}" class="input" placeholder="Nomor laporan, siswa, NISN, atau kelas"></div>
            <div class="field"><label for="antrean">Jenis antrean</label><select id="antrean" name="antrean" class="select">@foreach($daftarAntrean as $kode=>$label)<option value="{{ $kode }}" @selected($antrean===$kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="actions"><a href="{{ route('pusat-verifikasi-pelanggaran.index') }}" class="button button-muted">Reset</a><button class="button button-dark">Tampilkan</button></div>
        </div>
    </form>

    <section class="panel">
        <div class="verification-list">
            @forelse($laporan as $item)
                @php
                    $fakta = $item->kelengkapan_fakta;
                    $tahap = (int) $item->tahap_aktif;
                    $keputusanBk = $item->verifikasiBkPelanggaran->first();
                    $modeBacaBk = (bool) $item->mode_baca_bk;
                @endphp
                <article class="verification-row">
                    <div>
                        <p class="person-meta">{{ $item->nomor_laporan }} &middot; {{ $item->tanggal_kejadian?->format('d/m/Y') }}</p>
                        <p class="verification-name">{{ $item->siswa?->nama_lengkap }}</p>
                        <p class="person-meta">{{ $item->kelas?->nama ?: 'Kelas belum ditentukan' }} &middot; NISN {{ $item->siswa?->nisn ?: '-' }}</p>
                        <p class="verification-task">{{ $modeBacaBk ? 'Ditangani Guru BK tingkat lain' : $item->tugas_pengguna }}</p>
                        @if($modeBacaBk)<p class="verification-readonly">Anda tetap dapat melihat perkembangan laporan ini.</p>@endif
                        <span class="{{ $badgeStatus($item->status_verifikasi) }}" style="margin-top:8px">{{ $item->labelStatusVerifikasi() }}</span>
                    </div>

                    <div>
                        <div class="mobile-card-head"><div><p class="person-meta">Klasifikasi BK</p><p class="person-name">{{ $item->butirPelanggaranLaporan->first()?->nama_pelanggaran ?: ($item->status_verifikasi==='ditetapkan_pembinaan'?'Pembinaan tanpa poin':'Belum diklasifikasikan') }}</p></div><strong>{{ in_array($item->status_verifikasi,['menunggu_pengesahan_wakil','disahkan'],true)?$item->total_poin.' poin':'-' }}</strong></div>
                        <div class="fact-chips">
                            <span class="fact-chip {{ $fakta['lokasi']?'':'missing' }}">Lokasi {{ $fakta['lokasi']?'ada':'belum' }}</span>
                            <span class="fact-chip {{ $fakta['bukti']?'':'missing' }}">{{ $item->bukti_laporan_pembinaan_siswa_count }} bukti</span>
                            <span class="fact-chip {{ $fakta['saksi']?'':'missing' }}">{{ $item->saksi_laporan_pembinaan_siswa_count }} saksi</span>
                            <span class="fact-chip {{ $fakta['klarifikasi']?'':'missing' }}">{{ $item->klarifikasi_siswa_pembinaan_count }} klarifikasi</span>
                        </div>
                        <div class="verification-flow"><span class="verification-step {{ $tahap>1?'done':($tahap===1?'current':'') }}">1. Pemeriksaan BK</span><span class="verification-step {{ $tahap>2?'done':($tahap===2?'current':'') }}">2. Pengesahan Wakil</span></div>
                    </div>

                    <div>
                        <p class="person-meta">Keputusan terakhir BK</p>
                        <p class="person-name">{{ $keputusanBk?->labelHasil() ?? 'Belum diputuskan' }}</p>
                        @if($keputusanBk)<p class="person-meta">{{ $keputusanBk->bkPegawai?->nama_lengkap ?: 'BK' }} &middot; {{ $keputusanBk->diverifikasi_pada?->format('d/m/Y H:i') }}</p>@endif
                        @if($item->batas_hari>0)
                            <p class="verification-age {{ $item->terlambat_diproses?'late':'' }}">
                                {{ $item->hari_menunggu }} hari diproses
                                @if($item->batas_proses_pada)
                                    &middot; batas {{ $item->batas_proses_pada->format('d/m/Y H:i') }}
                                @else
                                    &middot; batas {{ $item->batas_hari }} hari
                                @endif
                            </p>
                            @if($item->terlambat_diproses)
                                <p class="verification-age late">Terlambat {{ max(1, abs($item->sisa_hari)) }} hari.</p>
                            @endif
                        @endif
                    </div>

                    <div class="verification-actions"><a href="{{ route('laporan-pembinaan-siswa.show',$item) }}" class="button button-primary">{{ $modeBacaBk?'Lihat laporan':(in_array($item->status_verifikasi,\App\Services\Pembinaan\AntreanVerifikasiPelanggaranService::STATUS_FINAL,true)?'Lihat hasil':(in_array($item->status_verifikasi,\App\Services\Pembinaan\AntreanVerifikasiPelanggaranService::STATUS_WAKIL,true)?'Buka pengesahan':'Buka pemeriksaan')) }}</a></div>
                </article>
            @empty
                <div class="empty-state">Tidak ada laporan dalam antrean ini.</div>
            @endforelse
        </div>
    </section>

    @if($laporan->hasPages())<nav class="pagination-simple"><span>Halaman {{ $laporan->currentPage() }} dari {{ $laporan->lastPage() }}</span><div class="actions">@if($laporan->onFirstPage())<span class="button button-muted">Sebelumnya</span>@else<a href="{{ $laporan->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>@endif @if($laporan->hasMorePages())<a href="{{ $laporan->nextPageUrl() }}" class="button button-muted">Berikutnya</a>@else<span class="button button-muted">Berikutnya</span>@endif</div></nav>@endif
@endsection
