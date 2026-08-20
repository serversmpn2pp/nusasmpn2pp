@extends('layouts.app')

@section('title', ($judulHalaman ?? 'Detail Progress Kasus').' - NUSA')

@section('content')
    @include('progress-kasus-siswa._styles')

    <div class="case-page">
        <div class="page-header">
            <div>
                <p class="eyebrow">Kesiswaan</p>
                <h1 class="page-title">{{ $judulHalaman ?? 'Detail Progress Kasus' }}</h1>
                <p class="page-subtitle">{{ $laporan->nomor_laporan }}</p>
            </div>
            <a class="case-back" href="{{ $urlKembali ?? route('progress-kasus-siswa.index') }}">Kembali</a>
        </div>

        <section class="case-hero">
            <div>
                <span class="case-status {{ $presentasiStatus['warna'] }}">{{ $presentasiStatus['label'] }}</span>
                <h1 style="margin-top:10px">{{ $siswa->nama_lengkap }}</h1>
                <p>{{ $presentasiStatus['deskripsi'] }}</p>
            </div>
            <div class="case-hero-meta">Status penanganan: {{ $presentasiStatus['status_penanganan'] }}</div>
        </section>

        <section class="case-panel">
            <div class="case-panel-body">
                <div class="case-progress" aria-label="Tahapan progress kasus">
                    @foreach (['Laporan diterima', 'Pemeriksaan BK', 'Keputusan sekolah', 'Selesai'] as $nomor => $label)
                        <div class="case-progress-step {{ $presentasiStatus['langkah'] >= $nomor + 1 ? 'is-done' : '' }}">
                            <span class="case-progress-number">{{ $nomor + 1 }}</span>
                            <strong>{{ $label }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="case-detail-grid">
            <div style="display:grid;gap:18px">
                <article class="case-panel">
                    <header class="case-panel-head">
                        <h2>Ringkasan Kejadian</h2>
                    </header>
                    <div class="case-panel-body">
                        <div class="case-info-grid">
                            <div class="case-info">
                                <span>Tanggal</span>
                                <strong>{{ $laporan->tanggal_kejadian?->locale('id')->translatedFormat('d F Y') ?: '-' }}</strong>
                            </div>
                            <div class="case-info">
                                <span>Waktu</span>
                                <strong>{{ $laporan->waktuKejadianRingkas() ?: '-' }}</strong>
                            </div>
                            <div class="case-info">
                                <span>Tempat</span>
                                <strong>{{ $laporan->tempat_kejadian ?: '-' }}</strong>
                            </div>
                            <div class="case-info">
                                <span>Kelas saat kejadian</span>
                                <strong>{{ $laporan->kelas?->nama ?: '-' }}</strong>
                            </div>
                            <div class="case-info">
                                <span>Tahun pelajaran</span>
                                <strong>{{ $laporan->tahunPelajaran?->nama ?: '-' }}</strong>
                            </div>
                            <div class="case-info">
                                <span>Sumber</span>
                                <strong>{{ $laporan->berasalDariAbsensi() ? 'Presensi keterlambatan' : 'Laporan pegawai' }}</strong>
                            </div>
                        </div>
                        <div class="case-narrative">{{ $laporan->kronologi }}</div>
                    </div>
                </article>

                <article class="case-panel">
                    <header class="case-panel-head">
                        <h2>Keputusan Sekolah</h2>
                    </header>
                    <div class="case-panel-body">
                        <div class="case-decision">
                            <span class="case-status {{ $presentasiStatus['warna'] }}">{{ $presentasiStatus['label'] }}</span>
                            <p>{{ $presentasiStatus['deskripsi'] }}</p>

                            @if ($laporan->status_verifikasi === 'disahkan')
                                <div class="case-violation-list">
                                    @foreach ($laporan->butirPelanggaranLaporan as $butir)
                                        <div class="case-violation">
                                            <strong>{{ $butir->nama_pelanggaran }}</strong>
                                            <span>{{ $butir->poin }} poin</span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="case-total">
                                    <span>Total poin resmi</span>
                                    <strong>{{ $laporan->total_poin }}</strong>
                                </div>
                            @elseif (! $presentasiStatus['final'])
                                <div class="case-privacy" style="margin-top:12px">
                                    Rekomendasi yang masih diperiksa belum menjadi keputusan resmi sekolah.
                                </div>
                            @endif
                        </div>
                    </div>
                </article>
            </div>

            <aside style="display:grid;gap:18px;align-content:start">
                <article class="case-panel">
                    <header class="case-panel-head">
                        <h2>Riwayat Proses</h2>
                    </header>
                    <div class="case-panel-body">
                        <div class="case-timeline">
                            @foreach ($linimasa as $item)
                                <div class="case-timeline-item">
                                    <span class="case-timeline-dot" aria-hidden="true"></span>
                                    <div>
                                        <strong>{{ $item['judul'] }}</strong>
                                        <p>{{ $item['deskripsi'] }}</p>
                                        @if ($item['tanggal'])
                                            <time datetime="{{ $item['tanggal']->toDateString() }}">
                                                {{ $item['tanggal']->locale('id')->translatedFormat('d F Y') }}
                                            </time>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>

                @if ($laporan->tindakLanjutPembinaanSiswa->isNotEmpty())
                    <article class="case-panel">
                        <header class="case-panel-head">
                            <h2>Tindak Lanjut</h2>
                        </header>
                        <div class="case-panel-body">
                            <div class="case-follow-list">
                                @foreach ($laporan->tindakLanjutPembinaanSiswa as $tindakLanjut)
                                    <div class="case-follow">
                                        <strong>{{ $tindakLanjut->labelJenis() }}</strong>
                                        <span>
                                            {{ $tindakLanjut->tanggal_tindak_lanjut?->locale('id')->translatedFormat('d F Y') }}
                                            · {{ $tindakLanjut->labelStatusLaporan() }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </article>
                @endif

                <div class="case-privacy">
                    {{ $teksPrivasi ?? 'Rincian pemeriksaan internal dikelola oleh sekolah dan tidak ditampilkan pada akun siswa.' }}
                </div>
            </aside>
        </div>
    </div>
@endsection
