@extends('layouts.app')

@section('title', 'Jadwal Pelajaran - NUSA')

@section('content')
    <style>
        .schedule-filter-grid {
            grid-template-columns: minmax(180px, 1fr) minmax(150px, .75fr) 150px 150px auto;
        }

        @media (max-width: 1080px) {
            .schedule-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .schedule-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Jadwal pelajaran</h1>
        </div>

        <div class="actions">
            @izin('jadwal.kelola')
                <a href="{{ route('jam-pelajaran.index') }}" class="button button-muted">Jam pelajaran</a>
                <a href="{{ route('jadwal-pelajaran.create', ['tahun_pelajaran_id' => $tahunPelajaranId, 'kelas_id' => $kelasId]) }}" class="button button-muted">Tambah satu</a>
                <a href="{{ route('jadwal-pelajaran.susun', ['tahun_pelajaran_id' => $tahunPelajaranId, 'kelas_id' => $kelasId]) }}" class="button button-primary">Susun jadwal</a>
            @endizin
        </div>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total</p>
            <p class="stat-value">{{ $jumlahJadwal }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Aktif</p>
            <p class="stat-value">{{ $jumlahAktif }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Nonaktif</p>
            <p class="stat-value">{{ $jumlahNonaktif }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('jadwal-pelajaran.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid schedule-filter-grid">
            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    @foreach ($tahunPelajaran as $item)
                        <option value="{{ $item->id }}" @selected((string) $tahunPelajaranId === (string) $item->id)>
                            {{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select">
                    <option value="">Semua kelas</option>
                    @foreach ($kelas as $item)
                        <option value="{{ $item->id }}" @selected((string) $kelasId === (string) $item->id)>{{ $item->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="hari">Hari</label>
                <select id="hari" name="hari" class="select">
                    <option value="semua" @selected($hari === 'semua')>Semua hari</option>
                    @foreach ($daftarHari as $kode => $label)
                        <option value="{{ $kode }}" @selected($hari === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="semua" @selected($status === 'semua')>Semua</option>
                    <option value="aktif" @selected($status === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('jadwal-pelajaran.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Hari</th>
                        <th>Jam</th>
                        <th>Kelas</th>
                        <th>Mata pelajaran</th>
                        <th>Guru</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jadwalPelajaran as $item)
                        @php
                            $guruMapel = $item->guruMataPelajaran;
                            $mataPelajaran = $item->mataPelajaranTerjadwal();
                            $pengaturanMapel = $mataPelajaran?->pengaturanUntuk(
                                (int) $item->tahun_pelajaran_id,
                                (int) $item->kelas?->tingkat,
                            );
                        @endphp
                        <tr>
                            <td>{{ $item->labelHari() }}</td>
                            <td>
                                <p class="person-name">{{ $item->jamPelajaran?->label ?: 'Jam ' . ($item->jamPelajaran?->nomor_jam ?? '-') }}</p>
                                <p class="person-meta">{{ $item->jamPelajaran?->formatJam($item->jamPelajaran?->jam_mulai) ?? '-' }} - {{ $item->jamPelajaran?->formatJam($item->jamPelajaran?->jam_selesai) ?? '-' }}</p>
                            </td>
                            <td>{{ $item->kelas?->nama ?? '-' }}</td>
                            <td>
                                <p class="person-name">{{ $mataPelajaran?->nama ?? '-' }}</p>
                                <p class="person-meta">{{ $pengaturanMapel?->kode ?: 'Kode belum diatur' }}</p>
                            </td>
                            <td>
                                <p class="person-name">{{ $guruMapel?->pegawai?->nama_lengkap ?? 'Kegiatan kelas' }}</p>
                                <p class="person-meta">{{ $guruMapel?->pegawai?->nip ?: ($mataPelajaran?->kelompok ?? '-') }}</p>
                            </td>
                            <td>
                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('jadwal-pelajaran.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('jadwal.kelola')
                                        <a href="{{ route('jadwal-pelajaran.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada jadwal pelajaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($jadwalPelajaran as $item)
                        @php
                            $guruMapel = $item->guruMataPelajaran;
                            $mataPelajaran = $item->mataPelajaranTerjadwal();
                            $pengaturanMapel = $mataPelajaran?->pengaturanUntuk(
                                (int) $item->tahun_pelajaran_id,
                                (int) $item->kelas?->tingkat,
                            );
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $mataPelajaran?->nama ?? '-' }}</p>
                            <p class="person-meta">{{ $item->labelHari() }} - {{ $item->jamPelajaran?->labelJam() ?? '-' }}</p>
                        </div>

                        @if ($item->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Kelas</dt>
                            <dd>{{ $item->kelas?->nama ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt>Guru</dt>
                            <dd>{{ $guruMapel?->pegawai?->nama_lengkap ?? 'Kegiatan kelas' }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('jadwal-pelajaran.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('jadwal.kelola')
                            <a href="{{ route('jadwal-pelajaran.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada jadwal pelajaran.</div>
            @endforelse
        </div>
    </section>

    @if ($jadwalPelajaran->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $jadwalPelajaran->currentPage() }} dari {{ $jadwalPelajaran->lastPage() }}</div>
            <div class="actions">
                @if ($jadwalPelajaran->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $jadwalPelajaran->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($jadwalPelajaran->hasMorePages())
                    <a href="{{ $jadwalPelajaran->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
