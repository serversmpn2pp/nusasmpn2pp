@extends('layouts.app')

@section('title', 'Detail Laporan Pembinaan - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $statusBadge = fn (string $status) => match ($status) {
            'baru' => 'badge badge-warning',
            'diproses' => 'badge badge-active',
            'perlu_tindak_lanjut' => 'badge badge-danger',
            'selesai' => 'badge badge-muted',
            'dibatalkan' => 'badge badge-inactive',
            default => 'badge badge-muted',
        };
        $tingkatBadge = fn (string $tingkat) => match ($tingkat) {
            'ringan' => 'badge badge-active',
            'sedang' => 'badge badge-warning',
            'berat' => 'badge badge-danger',
            default => 'badge badge-muted',
        };
    @endphp

    <style>
        .follow-up-list {
            display: grid;
            gap: 14px;
            margin-top: 18px;
        }

        .follow-up-item {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 16px;
            background: #fff;
        }

        .follow-up-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .follow-up-body {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 14px;
        }

        .follow-up-note {
            margin: 0;
            white-space: pre-line;
        }

        @media (max-width: 900px) {
            .follow-up-head,
            .follow-up-body {
                grid-template-columns: 1fr;
            }

            .follow-up-head {
                display: grid;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Pembinaan</p>
            <h1 class="page-title">Detail laporan pembinaan</h1>
        </div>

        <div class="actions">
            <a href="{{ route('laporan-pembinaan-siswa.index') }}" class="button button-muted">Kembali</a>
            @izin('bk.kelola')
                <a href="{{ route('laporan-pembinaan-siswa.edit', $laporanPembinaanSiswa) }}" class="button button-dark">Edit</a>
                @if ($laporanPembinaanSiswa->status !== 'dibatalkan')
                    <a href="{{ route('tindak-lanjut-pembinaan-siswa.create', $laporanPembinaanSiswa) }}" class="button button-primary">Tambah tindak lanjut</a>
                @endif
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if (session('gagal'))
        <div class="alert alert-danger">{{ session('gagal') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">PB</div>
                <h2>{{ $laporanPembinaanSiswa->siswa?->nama_lengkap ?: 'Siswa tidak ditemukan' }}</h2>
                <p>{{ $laporanPembinaanSiswa->nomor_laporan }}</p>

                <div style="display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; margin-top: 16px;">
                    <span class="{{ $statusBadge($laporanPembinaanSiswa->status) }}">{{ $laporanPembinaanSiswa->labelStatus() }}</span>
                    <span class="{{ $tingkatBadge($laporanPembinaanSiswa->tingkat) }}">{{ $laporanPembinaanSiswa->labelTingkat() }}</span>
                </div>
            </div>

            @izin('bk.kelola')
                @if ($laporanPembinaanSiswa->status !== 'dibatalkan')
                    <form action="{{ route('laporan-pembinaan-siswa.destroy', $laporanPembinaanSiswa) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Batalkan laporan pembinaan ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Batalkan laporan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Laporan</h2>

                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Nomor laporan</dt>
                        <dd>{{ $laporanPembinaanSiswa->nomor_laporan }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tanggal kejadian</dt>
                        <dd>
                            {{ $laporanPembinaanSiswa->tanggal_kejadian?->format('d/m/Y') ?: '-' }}
                            {{ $laporanPembinaanSiswa->waktuKejadianRingkas() ? 'pukul ' . $laporanPembinaanSiswa->waktuKejadianRingkas() : '' }}
                        </dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tempat kejadian</dt>
                        <dd>{{ $teks($laporanPembinaanSiswa->tempat_kejadian) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Siswa</dt>
                        <dd>{{ $laporanPembinaanSiswa->siswa?->nama_lengkap ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>NISN</dt>
                        <dd>{{ $teks($laporanPembinaanSiswa->siswa?->nisn) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kelas</dt>
                        <dd>{{ $teks($laporanPembinaanSiswa->kelas?->nama) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tahun pelajaran</dt>
                        <dd>{{ $teks($laporanPembinaanSiswa->tahunPelajaran?->nama) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kategori</dt>
                        <dd>{{ $teks($laporanPembinaanSiswa->kategoriPembinaanSiswa?->nama) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Pelapor / pencatat</dt>
                        <dd>{{ $teks($laporanPembinaanSiswa->pelaporPegawai?->nama_lengkap) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Dibuat oleh</dt>
                        <dd>{{ $teks($laporanPembinaanSiswa->dibuatOlehPengguna?->nama) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Dibuat pada</dt>
                        <dd>{{ $laporanPembinaanSiswa->created_at?->format('d/m/Y H:i') ?: '-' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Kronologi</h2>
                <p style="white-space: pre-line; margin-bottom: 0;">{{ $laporanPembinaanSiswa->kronologi }}</p>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Tindakan Awal</h2>
                <p style="white-space: pre-line; margin-bottom: 0;">{{ $teks($laporanPembinaanSiswa->tindakan_awal) }}</p>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Catatan Rahasia</h2>
                <p class="help-text" style="margin-top: 8px;">Catatan ini disiapkan sebagai ruang internal untuk BK atau pimpinan.</p>
                <p style="white-space: pre-line; margin-bottom: 0;">{{ $teks($laporanPembinaanSiswa->catatan_rahasia) }}</p>
            </section>

            <section class="panel panel-pad">
                <div class="page-header" style="margin-bottom: 0;">
                    <div>
                        <h2 class="panel-title">Riwayat Tindak Lanjut</h2>
                        <p class="help-text">Catatan konseling, pemanggilan, mediasi, dan keputusan akhir yang terkait dengan laporan ini.</p>
                    </div>

                    @izin('bk.kelola')
                        @if ($laporanPembinaanSiswa->status !== 'dibatalkan')
                            <a href="{{ route('tindak-lanjut-pembinaan-siswa.create', $laporanPembinaanSiswa) }}" class="button button-primary">Tambah</a>
                        @endif
                    @endizin
                </div>

                <div class="follow-up-list">
                    @forelse ($laporanPembinaanSiswa->tindakLanjutPembinaanSiswa as $tindakLanjut)
                        <article class="follow-up-item">
                            <div class="follow-up-head">
                                <div>
                                    <p class="person-name">{{ $tindakLanjut->labelJenis() }}</p>
                                    <p class="person-meta">
                                        {{ $tindakLanjut->tanggal_tindak_lanjut?->format('d/m/Y') ?: '-' }}
                                        {{ $tindakLanjut->waktuTindakLanjutRingkas() ? 'pukul ' . $tindakLanjut->waktuTindakLanjutRingkas() : '' }}
                                    </p>
                                    <p class="person-meta">Petugas: {{ $tindakLanjut->petugasPegawai?->nama_lengkap ?: '-' }}</p>
                                </div>

                                <div class="actions" style="justify-content: flex-end;">
                                    <span class="{{ $statusBadge($tindakLanjut->status_laporan) }}">{{ $tindakLanjut->labelStatusLaporan() }}</span>
                                    @izin('bk.kelola')
                                        <a href="{{ route('tindak-lanjut-pembinaan-siswa.edit', $tindakLanjut) }}" class="button button-muted button-sm">Edit</a>
                                        <form action="{{ route('tindak-lanjut-pembinaan-siswa.destroy', $tindakLanjut) }}" method="POST" onsubmit="return confirm('Hapus tindak lanjut ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="button button-danger button-sm">Hapus</button>
                                        </form>
                                    @endizin
                                </div>
                            </div>

                            <div class="follow-up-body">
                                <div>
                                    <p class="person-meta">Pihak terlibat</p>
                                    <p class="follow-up-note">{{ $teks($tindakLanjut->pihak_terlibat) }}</p>
                                </div>
                                <div>
                                    <p class="person-meta">Dibuat oleh</p>
                                    <p class="follow-up-note">{{ $teks($tindakLanjut->dibuatOlehPengguna?->nama) }}</p>
                                </div>
                                <div class="span-2">
                                    <p class="person-meta">Ringkasan tindakan</p>
                                    <p class="follow-up-note">{{ $tindakLanjut->ringkasan }}</p>
                                </div>
                                <div class="span-2">
                                    <p class="person-meta">Hasil / kesepakatan</p>
                                    <p class="follow-up-note">{{ $teks($tindakLanjut->hasil) }}</p>
                                </div>
                                <div class="span-2">
                                    <p class="person-meta">Rencana lanjutan</p>
                                    <p class="follow-up-note">{{ $teks($tindakLanjut->rencana_lanjutan) }}</p>
                                </div>
                                @if ($tindakLanjut->catatan_rahasia)
                                    <div class="span-2">
                                        <p class="person-meta">Catatan rahasia</p>
                                        <p class="follow-up-note">{{ $tindakLanjut->catatan_rahasia }}</p>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="empty-state">Belum ada tindak lanjut untuk laporan ini.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
