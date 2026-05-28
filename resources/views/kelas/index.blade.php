@extends('layouts.app')

@section('title', 'Kelas - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Kelas</h1>
        </div>

        @izin('kelas.kelola')
            <a href="{{ route('kelas.create') }}" class="button button-primary">Tambah kelas</a>
        @endizin
    </div>

    @if ($cakupanWaliKelas ?? false)
        <div class="alert">Data kelas dibatasi pada kelas yang Anda wali.</div>
    @endif

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total</p>
            <p class="stat-value">{{ $jumlahKelas }}</p>
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

    <form action="{{ route('kelas.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid filter-grid-wide">
            <div class="field">
                <label for="kata_kunci">Cari kelas</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kata_kunci }}" placeholder="Nama kelas atau wali kelas" class="input">
            </div>

            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    <option value="">Semua</option>
                    @foreach ($tahunPelajaran as $item)
                        <option value="{{ $item->id }}" @selected((string) $tahunPelajaranId === (string) $item->id)>
                            {{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}
                        </option>
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
                <a href="{{ route('kelas.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Kelas</th>
                        <th>Tahun pelajaran</th>
                        <th>Wali kelas</th>
                        <th>Anggota</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($kelas as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">Tingkat {{ $item->tingkat ?: '-' }}{{ $item->kapasitas ? ' · kapasitas ' . $item->kapasitas : '' }}</p>
                            </td>
                            <td>
                                <div>{{ $item->tahunPelajaran?->nama ?: '-' }}</div>
                                <div class="muted">{{ $item->tahunPelajaran?->aktif ? 'Tahun aktif' : 'Tahun nonaktif' }}</div>
                            </td>
                            <td>{{ $item->waliKelas?->nama_lengkap ?: '-' }}</td>
                            <td>{{ $item->anggota_kelas_count }} siswa</td>
                            <td>
                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('kelas.show', $item) }}" class="button button-muted">Lihat</a>
                                    <a href="{{ route('penempatan-siswa.index', ['tahun_pelajaran_id' => $item->tahun_pelajaran_id, 'kelas_id' => $item->id]) }}" class="button button-muted">Anggota</a>
                                    @izin('kelas.kelola')
                                        <a href="{{ route('kelas.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada kelas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($kelas as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama }}</p>
                            <p class="person-meta">{{ $item->tahunPelajaran?->nama ?: '-' }}</p>
                        </div>

                        @if ($item->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Wali kelas</dt>
                            <dd>{{ $item->waliKelas?->nama_lengkap ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Anggota</dt>
                            <dd>{{ $item->anggota_kelas_count }} siswa</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('kelas.show', $item) }}" class="button button-muted">Lihat</a>
                        <a href="{{ route('penempatan-siswa.index', ['tahun_pelajaran_id' => $item->tahun_pelajaran_id, 'kelas_id' => $item->id]) }}" class="button button-muted">Anggota</a>
                        @izin('kelas.kelola')
                            <a href="{{ route('kelas.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada kelas.</div>
            @endforelse
        </div>
    </section>

    @if ($kelas->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $kelas->currentPage() }} dari {{ $kelas->lastPage() }}
            </div>
            <div class="actions">
                @if ($kelas->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $kelas->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($kelas->hasMorePages())
                    <a href="{{ $kelas->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
