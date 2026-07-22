@extends('layouts.app')

@section('title', 'Pelaksanaan Sanksi Siswa - NUSA')

@section('content')
    @php
        $badgeStatus = fn (string $nilai) => match ($nilai) {
            'selesai' => 'badge badge-active',
            'dibatalkan' => 'badge badge-inactive',
            'diproses' => 'badge badge-warning',
            default => 'badge badge-danger',
        };
    @endphp

    <style>
        .sanction-stats { display:grid; gap:12px; grid-template-columns:repeat(5,minmax(0,1fr)); margin-bottom:20px; }
        .sanction-stat { color:inherit; min-width:0; padding:15px; text-decoration:none; }
        .sanction-stat.active { border-color:var(--primary); box-shadow:inset 0 3px 0 var(--secondary); }
        .sanction-stat p { color:var(--muted); font-size:11px; font-weight:800; margin:0; text-transform:uppercase; }
        .sanction-stat strong { color:var(--primary-dark); display:block; font-size:28px; margin-top:4px; }
        .sanction-filter { align-items:end; display:grid; gap:12px; grid-template-columns:repeat(4,minmax(0,1fr)); }
        .sanction-deadline { font-size:12px; font-weight:700; }
        .sanction-deadline.late { color:#a12828; }
        .sanction-mobile-list { display:grid; gap:12px; }
        @media(max-width:1050px){.sanction-stats{grid-template-columns:repeat(3,minmax(0,1fr))}.sanction-filter{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:640px){.sanction-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.sanction-filter{grid-template-columns:1fr}.sanction-filter .actions{justify-content:stretch}.sanction-filter .button{flex:1}}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Pelaksanaan Sanksi Siswa</h1>
            <p class="page-subtitle">Pantau sanksi yang terpicu dari akumulasi poin hingga pelaksanaannya selesai.</p>
        </div>
        <div class="actions">
            <a href="{{ route('rekap-poin-siswa.index') }}" class="button button-muted">Rekap Poin</a>
            @izin('poin_siswa.reward_kelola')<a href="{{ route('pengurangan-poin-siswa.index') }}" class="button button-primary">Pengurangan Poin</a>@endizin
        </div>
    </div>

    @if(session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif

    <div class="sanction-stats">
        <a class="panel sanction-stat {{ $status === 'aktif' ? 'active' : '' }}" href="{{ route('sanksi-poin-siswa.index', ['status' => 'aktif', 'tahun_pelajaran_id' => $tahunPelajaranId]) }}"><p>Perlu ditangani</p><strong>{{ $ringkasan['aktif'] }}</strong></a>
        <a class="panel sanction-stat {{ $status === 'menunggu' ? 'active' : '' }}" href="{{ route('sanksi-poin-siswa.index', ['status' => 'menunggu', 'tahun_pelajaran_id' => $tahunPelajaranId]) }}"><p>Menunggu</p><strong>{{ $ringkasan['menunggu'] }}</strong></a>
        <a class="panel sanction-stat {{ $status === 'diproses' ? 'active' : '' }}" href="{{ route('sanksi-poin-siswa.index', ['status' => 'diproses', 'tahun_pelajaran_id' => $tahunPelajaranId]) }}"><p>Diproses</p><strong>{{ $ringkasan['diproses'] }}</strong></a>
        <a class="panel sanction-stat" href="{{ route('sanksi-poin-siswa.index', ['status' => 'aktif', 'tahun_pelajaran_id' => $tahunPelajaranId]) }}"><p>Terlambat</p><strong>{{ $ringkasan['terlambat'] }}</strong></a>
        <a class="panel sanction-stat {{ $status === 'selesai' ? 'active' : '' }}" href="{{ route('sanksi-poin-siswa.index', ['status' => 'selesai', 'tahun_pelajaran_id' => $tahunPelajaranId]) }}"><p>Selesai</p><strong>{{ $ringkasan['selesai'] }}</strong></a>
    </div>

    <form method="GET" class="panel panel-pad" style="margin-bottom:20px">
        <div class="sanction-filter">
            <div class="field"><label for="tahun_pelajaran_id">Tahun pelajaran</label><select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select"><option value="">Semua tahun</option>@foreach($daftarTahunPelajaran as $tahun)<option value="{{ $tahun->id }}" @selected((string)$tahunPelajaranId === (string)$tahun->id)>{{ $tahun->nama }}{{ $tahun->aktif ? ' (aktif)' : '' }}</option>@endforeach</select></div>
            <div class="field"><label for="kelas_id">Kelas</label><select id="kelas_id" name="kelas_id" class="select"><option value="">Semua kelas</option>@foreach($daftarKelas as $kelas)<option value="{{ $kelas->id }}" @selected((string)$kelasId === (string)$kelas->id)>{{ $kelas->nama }}</option>@endforeach</select></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status" class="select"><option value="aktif" @selected($status === 'aktif')>Perlu ditangani</option><option value="semua" @selected($status === 'semua')>Semua status</option>@foreach(\App\Models\SanksiPoinSiswa::DAFTAR_STATUS as $kode => $label)<option value="{{ $kode }}" @selected($status === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="kata_kunci">Cari siswa atau sanksi</label><input id="kata_kunci" name="kata_kunci" class="input" value="{{ $kataKunci }}" placeholder="Nama, NISN, atau nama sanksi"></div>
        </div>
        <div class="actions" style="justify-content:flex-end;margin-top:14px"><a href="{{ route('sanksi-poin-siswa.index') }}" class="button button-muted">Reset</a><button class="button button-dark">Terapkan</button></div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead><tr><th>Siswa</th><th>Sanksi</th><th>Pelaksanaan</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse($daftarSanksi as $item)
                        @php $anggota = $item->siswa?->anggotaKelas->firstWhere('tahun_pelajaran_id', $item->tahun_pelajaran_id); @endphp
                        <tr>
                            <td><p class="person-name">{{ $item->siswa?->nama_lengkap }}</p><p class="person-meta">{{ $anggota?->kelas?->nama ?: '-' }} &middot; NISN {{ $item->siswa?->nisn ?: '-' }}</p></td>
                            <td><p class="person-name">{{ $item->aturanSanksiPoin?->nama }}</p><p class="person-meta">Ambang {{ $item->aturanSanksiPoin?->batas_poin }} &middot; terpicu {{ $item->poin_saat_terpicu }} poin</p></td>
                            <td><p>{{ $item->petugasPegawai?->nama_lengkap ?: 'Belum ditugaskan' }}</p>@if($item->batas_pelaksanaan)<p class="sanction-deadline {{ $item->terlambat() ? 'late' : '' }}">Batas {{ $item->batas_pelaksanaan->format('d/m/Y') }}{{ $item->terlambat() ? ' - terlambat' : '' }}</p>@else<p class="person-meta">Belum ada tenggat</p>@endif</td>
                            <td><span class="{{ $badgeStatus($item->status) }}">{{ $item->labelStatus() }}</span><p class="person-meta" style="margin-top:7px">{{ $item->bukti_pelaksanaan_sanksi_count }} bukti</p></td>
                            <td><div class="actions" style="justify-content:flex-end"><a href="{{ route('sanksi-poin-siswa.show', $item) }}" class="button button-dark button-sm">Buka</a></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Tidak ada sanksi pada cakupan dan filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list sanction-mobile-list">
            @forelse($daftarSanksi as $item)
                @php $anggota = $item->siswa?->anggotaKelas->firstWhere('tahun_pelajaran_id', $item->tahun_pelajaran_id); @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head"><div><p class="person-name">{{ $item->siswa?->nama_lengkap }}</p><p class="person-meta">{{ $anggota?->kelas?->nama ?: '-' }} &middot; {{ $item->poin_saat_terpicu }} poin</p></div><span class="{{ $badgeStatus($item->status) }}">{{ $item->labelStatus() }}</span></div>
                    <p style="margin:13px 0 4px;font-weight:800">{{ $item->aturanSanksiPoin?->nama }}</p>
                    <p class="person-meta">Petugas: {{ $item->petugasPegawai?->nama_lengkap ?: 'belum ditugaskan' }}</p>
                    @if($item->batas_pelaksanaan)<p class="sanction-deadline {{ $item->terlambat() ? 'late' : '' }}" style="margin-top:7px">Batas {{ $item->batas_pelaksanaan->format('d/m/Y') }}{{ $item->terlambat() ? ' - terlambat' : '' }}</p>@endif
                    <a href="{{ route('sanksi-poin-siswa.show', $item) }}" class="button button-dark button-full" style="margin-top:13px">Buka pelaksanaan</a>
                </article>
            @empty
                <div class="empty-state">Tidak ada sanksi pada cakupan dan filter ini.</div>
            @endforelse
        </div>
    </section>

    @if($daftarSanksi->hasPages())<nav class="pagination-simple"><span>Halaman {{ $daftarSanksi->currentPage() }} dari {{ $daftarSanksi->lastPage() }}</span><div class="actions">@if($daftarSanksi->onFirstPage())<span class="button button-muted">Sebelumnya</span>@else<a href="{{ $daftarSanksi->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>@endif @if($daftarSanksi->hasMorePages())<a href="{{ $daftarSanksi->nextPageUrl() }}" class="button button-muted">Berikutnya</a>@else<span class="button button-muted">Berikutnya</span>@endif</div></nav>@endif
@endsection
