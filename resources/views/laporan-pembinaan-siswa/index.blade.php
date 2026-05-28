@extends('layouts.app')

@section('title', 'Laporan Pembinaan Siswa - NUSA')

@section('content')
    @php
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
        .laporan-filter-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            align-items: end;
        }

        .laporan-filter-actions {
            grid-column: 1 / -1;
            justify-content: flex-end;
            padding-top: 2px;
        }

        .report-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }

        @media (max-width: 1100px) {
            .laporan-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .laporan-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Pembinaan</p>
            <h1 class="page-title">Laporan pembinaan siswa</h1>
        </div>

        @izin('bk.kelola')
            <a href="{{ route('laporan-pembinaan-siswa.create') }}" class="button button-primary">Tambah laporan</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total laporan</p>
            <p class="stat-value">{{ $ringkasan['total'] }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Baru</p>
            <p class="stat-value">{{ $ringkasan['baru'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Diproses</p>
            <p class="stat-value">{{ $ringkasan['diproses'] }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Tindak lanjut</p>
            <p class="stat-value">{{ $ringkasan['tindak_lanjut'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Selesai</p>
            <p class="stat-value">{{ $ringkasan['selesai'] }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('laporan-pembinaan-siswa.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="laporan-filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari laporan</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Nomor, siswa, NISN, kategori" class="input">
            </div>

            <div class="field">
                <label for="kategori_pembinaan_siswa_id">Kategori</label>
                <select id="kategori_pembinaan_siswa_id" name="kategori_pembinaan_siswa_id" class="select">
                    <option value="">Semua</option>
                    @foreach ($daftarKategoriPembinaan as $kategori)
                        <option value="{{ $kategori->id }}" @selected((string) $kategoriDipilih === (string) $kategori->id)>
                            {{ $kategori->nama }}{{ $kategori->aktif ? '' : ' (nonaktif)' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="semua" @selected($status === 'semua')>Semua</option>
                    @foreach ($daftarStatus as $kode => $label)
                        <option value="{{ $kode }}" @selected($status === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="tingkat">Tingkat</label>
                <select id="tingkat" name="tingkat" class="select">
                    <option value="semua" @selected($tingkat === 'semua')>Semua</option>
                    @foreach ($daftarTingkat as $kode => $label)
                        <option value="{{ $kode }}" @selected($tingkat === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="tahun_pelajaran_id">Tahun</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    <option value="">Semua</option>
                    @foreach ($daftarTahunPelajaran as $tahunPelajaran)
                        <option value="{{ $tahunPelajaran->id }}" @selected((string) $tahunPelajaranDipilih === (string) $tahunPelajaran->id)>
                            {{ $tahunPelajaran->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select">
                    <option value="">Semua</option>
                    @foreach ($daftarKelas as $kelas)
                        <option value="{{ $kelas->id }}" @selected((string) $kelasDipilih === (string) $kelas->id)>
                            {{ $kelas->nama }}{{ $kelas->tahunPelajaran ? ' - ' . $kelas->tahunPelajaran->nama : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="actions laporan-filter-actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('laporan-pembinaan-siswa.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width: 1040px;">
                <thead>
                    <tr>
                        <th>Laporan</th>
                        <th>Siswa</th>
                        <th>Kategori</th>
                        <th>Status</th>
                        <th>Pelapor</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($laporanPembinaanSiswa as $laporan)
                        <tr>
                            <td>
                                <p class="person-name">{{ $laporan->nomor_laporan }}</p>
                                <p class="person-meta">
                                    {{ $laporan->tanggal_kejadian?->format('d/m/Y') ?: '-' }}
                                    {{ $laporan->waktuKejadianRingkas() ? 'pukul ' . $laporan->waktuKejadianRingkas() : '' }}
                                </p>
                                @if ($laporan->tempat_kejadian)
                                    <p class="person-meta">{{ $laporan->tempat_kejadian }}</p>
                                @endif
                            </td>
                            <td>
                                <p class="person-name">{{ $laporan->siswa?->nama_lengkap ?: '-' }}</p>
                                <p class="person-meta">
                                    NISN {{ $laporan->siswa?->nisn ?: '-' }}
                                    @if ($laporan->kelas)
                                        - {{ $laporan->kelas->nama }}
                                    @endif
                                </p>
                            </td>
                            <td>
                                <p class="person-name">{{ $laporan->kategoriPembinaanSiswa?->nama ?: '-' }}</p>
                                <div class="report-meta-row">
                                    <span class="{{ $tingkatBadge($laporan->tingkat) }}">{{ $laporan->labelTingkat() }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="{{ $statusBadge($laporan->status) }}">{{ $laporan->labelStatus() }}</span>
                            </td>
                            <td>
                                <p class="person-name">{{ $laporan->pelaporPegawai?->nama_lengkap ?: '-' }}</p>
                                <p class="person-meta">{{ $laporan->pelaporPegawai?->nip ?: 'Pelapor belum diisi' }}</p>
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('laporan-pembinaan-siswa.show', $laporan) }}" class="button button-muted">Lihat</a>
                                    @izin('bk.kelola')
                                        <a href="{{ route('laporan-pembinaan-siswa.edit', $laporan) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada laporan pembinaan siswa.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($laporanPembinaanSiswa as $laporan)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $laporan->siswa?->nama_lengkap ?: '-' }}</p>
                            <p class="person-meta">{{ $laporan->nomor_laporan }} - {{ $laporan->tanggal_kejadian?->format('d/m/Y') ?: '-' }}</p>
                            @if ($laporan->tempat_kejadian)
                                <p class="person-meta">{{ $laporan->tempat_kejadian }}</p>
                            @endif
                        </div>
                        <span class="{{ $statusBadge($laporan->status) }}">{{ $laporan->labelStatus() }}</span>
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Kategori</dt>
                            <dd>{{ $laporan->kategoriPembinaanSiswa?->nama ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Tingkat</dt>
                            <dd><span class="{{ $tingkatBadge($laporan->tingkat) }}">{{ $laporan->labelTingkat() }}</span></dd>
                        </div>
                        <div>
                            <dt>Kelas</dt>
                            <dd>{{ $laporan->kelas?->nama ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Pelapor</dt>
                            <dd>{{ $laporan->pelaporPegawai?->nama_lengkap ?: '-' }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('laporan-pembinaan-siswa.show', $laporan) }}" class="button button-muted">Lihat</a>
                        @izin('bk.kelola')
                            <a href="{{ route('laporan-pembinaan-siswa.edit', $laporan) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada laporan pembinaan siswa.</div>
            @endforelse
        </div>
    </section>

    @if ($laporanPembinaanSiswa->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $laporanPembinaanSiswa->currentPage() }} dari {{ $laporanPembinaanSiswa->lastPage() }}
            </div>
            <div class="actions">
                @if ($laporanPembinaanSiswa->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $laporanPembinaanSiswa->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($laporanPembinaanSiswa->hasMorePages())
                    <a href="{{ $laporanPembinaanSiswa->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
