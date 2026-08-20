@extends('layouts.app')

@section('title', 'Pembinaan & Poin Anak - NUSA')

@section('content')
    @include('progress-kasus-siswa._styles')

    <style>
        .parent-case-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));max-width:430px}
        .parent-case-tab{align-items:center;background:#fff;border:1px solid #cfd9e3;color:#526477;display:flex;font-size:.84rem;font-weight:900;justify-content:center;min-height:44px;padding:9px 14px;text-decoration:none}
        .parent-case-tab:first-child{border-radius:8px 0 0 8px}
        .parent-case-tab:last-child{border-left:0;border-radius:0 8px 8px 0}
        .parent-case-tab.active{background:#15477a;border-color:#15477a;color:#fff}
        .parent-point-list{background:#fff;border:1px solid #dce4eb;border-radius:8px;overflow:hidden}
        .parent-point-row{align-items:center;border-bottom:1px solid #e8edf2;display:grid;gap:14px;grid-template-columns:120px minmax(0,1fr) auto;padding:14px 16px}
        .parent-point-row:last-child{border-bottom:0}
        .parent-point-date{color:#526477;font-size:.76rem;font-weight:800}
        .parent-point-main{min-width:0}
        .parent-point-main strong{color:#172536;display:block;font-size:.86rem;line-height:1.4;overflow-wrap:anywhere}
        .parent-point-main small{color:#64748b;display:block;font-size:.72rem;line-height:1.45;margin-top:4px}
        .parent-point-value{color:#b91c1c;font-size:1.05rem;font-weight:900;text-align:right;white-space:nowrap}
        .parent-point-value.reduction{color:#15803d}
        .parent-point-note{background:#eef5fb;border-left:4px solid #2582bd;border-radius:6px;color:#36566f;font-size:.78rem;line-height:1.55;padding:11px 12px}
        @media(max-width:640px){.parent-case-tabs{max-width:none}.parent-point-row{align-items:start;grid-template-columns:minmax(0,1fr) auto}.parent-point-date{grid-column:1/-1}.parent-point-main{grid-column:1}.parent-point-value{grid-column:2;grid-row:2}}
    </style>

    <div class="case-page">
        <section class="case-hero">
            <div>
                <h1>Pembinaan & Poin Anak</h1>
                <p>{{ $siswa?->nama_lengkap ?: 'Akun belum terhubung ke data siswa' }} · {{ $anggotaKelas?->kelas?->nama ?: 'Kelas belum tersedia' }}</p>
            </div>
            <div class="case-hero-meta">{{ $tahunPelajaran?->nama ?: 'Tahun pelajaran belum tersedia' }}</div>
        </section>

        @if (! $siswa)
            <section class="case-empty">
                <strong>Akun belum terhubung ke data siswa</strong>
                Hubungi administrator sekolah agar akun orang tua dihubungkan dengan data anak yang benar.
            </section>
        @else
            <section class="case-summary" aria-label="Ringkasan pembinaan dan poin anak">
                <article class="case-stat"><span>Semua laporan</span><strong>{{ $ringkasan['laporan'] }}</strong></article>
                <article class="case-stat is-yellow"><span>Sedang diproses</span><strong>{{ $ringkasan['diproses'] }}</strong></article>
                <article class="case-stat is-red"><span>Poin pelanggaran</span><strong>{{ $ringkasan['poin_pelanggaran'] }}</strong></article>
                <article class="case-stat {{ $ringkasan['saldo'] > 0 ? 'is-red' : 'is-green' }}"><span>Saldo poin</span><strong>{{ $ringkasan['saldo'] }}</strong></article>
            </section>

            <nav class="parent-case-tabs" aria-label="Bagian pembinaan dan poin anak">
                <a href="{{ route('pembinaan-poin-anak.index', ['tab' => 'laporan']) }}" class="parent-case-tab {{ $tab === 'laporan' ? 'active' : '' }}">Perkembangan Laporan</a>
                <a href="{{ route('pembinaan-poin-anak.index', ['tab' => 'poin']) }}" class="parent-case-tab {{ $tab === 'poin' ? 'active' : '' }}">Riwayat Poin</a>
            </nav>

            @if ($tab === 'laporan')
                <section class="case-list" aria-label="Perkembangan laporan anak">
                    @forelse ($laporan as $item)
                        @php
                            $status = $presentasiStatus[$item->id];
                        @endphp
                        <article class="case-card">
                            <div class="case-card-main">
                                <div class="case-card-top">
                                    <span class="case-number">{{ $item->nomor_laporan }}</span>
                                    <span class="case-status {{ $status['warna'] }}">{{ $status['label'] }}</span>
                                </div>
                                <h2>{{ $item->berasalDariAbsensi() ? 'Catatan keterlambatan dari presensi' : 'Laporan kejadian siswa' }}</h2>
                                <p>{{ $status['deskripsi'] }}</p>
                                <div class="case-facts">
                                    <span>{{ $item->tanggal_kejadian?->locale('id')->translatedFormat('d F Y') }}</span>
                                    <span>{{ $item->tempat_kejadian ?: 'Tempat tidak dicantumkan' }}</span>
                                    <span>{{ $item->kelas?->nama ?: 'Kelas tidak dicantumkan' }}</span>
                                </div>
                            </div>
                            <a class="case-open" href="{{ route('pembinaan-poin-anak.show', $item) }}">Lihat Detail</a>
                        </article>
                    @empty
                        <div class="case-empty">
                            <strong>Belum ada laporan pembinaan</strong>
                            Perkembangan laporan anak akan ditampilkan di halaman ini.
                        </div>
                    @endforelse
                </section>

                @if ($laporan->hasPages())
                    <div>{{ $laporan->appends(['tab' => 'laporan'])->links() }}</div>
                @endif
            @else
                <div class="parent-point-note">
                    Poin pelanggaran menambah saldo, sedangkan reward atau kegiatan positif mengurangi saldo poin setelah disetujui sekolah.
                </div>

                <section class="parent-point-list" aria-label="Riwayat poin anak">
                    @forelse ($riwayatPoin as $transaksi)
                        @php
                            $pengurangan = $transaksi->jenis === 'pengurangan' || $transaksi->poin < 0;
                            $sumber = $transaksi->laporanPembinaanSiswa?->nomor_laporan
                                ?: $transaksi->penguranganPoinSiswa?->jenis_kegiatan;
                        @endphp
                        <article class="parent-point-row">
                            <time class="parent-point-date" datetime="{{ $transaksi->tercatat_pada?->toDateString() }}">
                                {{ $transaksi->tercatat_pada?->locale('id')->translatedFormat('d M Y') ?: '-' }}
                            </time>
                            <div class="parent-point-main">
                                <strong>{{ $transaksi->keterangan ?: ($pengurangan ? 'Pengurangan poin' : 'Pelanggaran berpoin') }}</strong>
                                <small>{{ $pengurangan ? 'Reward / pengurangan poin' : 'Poin pelanggaran resmi' }}{{ $sumber ? ' · '.$sumber : '' }}</small>
                            </div>
                            <span class="parent-point-value {{ $pengurangan ? 'reduction' : '' }}">
                                {{ $transaksi->poin > 0 ? '+' : '' }}{{ $transaksi->poin }} poin
                            </span>
                        </article>
                    @empty
                        <div class="case-empty">
                            <strong>Belum ada transaksi poin</strong>
                            Poin resmi dan pengurangan poin akan ditampilkan di halaman ini.
                        </div>
                    @endforelse
                </section>

                @if ($riwayatPoin->hasPages())
                    <div>{{ $riwayatPoin->appends(['tab' => 'poin'])->links() }}</div>
                @endif
            @endif

            <div class="case-privacy">
                Halaman ini hanya menampilkan perkembangan yang dapat diketahui orang tua. Catatan pemeriksaan internal tetap dikelola oleh sekolah.
            </div>
        @endif
    </div>
@endsection
