@extends('layouts.app')

@section('title', 'Tahun Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Tahun pelajaran</h1>
        </div>

        <a href="{{ route('tahun-pelajaran.create') }}" class="button button-primary">Tambah tahun pelajaran</a>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total</p>
            <p class="stat-value">{{ $jumlahTahunPelajaran }}</p>
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

    <form action="{{ route('tahun-pelajaran.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari tahun pelajaran</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kata_kunci }}" placeholder="Contoh: 2026/2027" class="input">
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
                <a href="{{ route('tahun-pelajaran.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Tahun pelajaran</th>
                        <th>Periode</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tahunPelajaran as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->keterangan ?: 'Pengaturan tahun ajaran' }}</p>
                            </td>
                            <td>
                                <div>{{ $item->tanggal_mulai ? $item->tanggal_mulai->format('d-m-Y') : '-' }}</div>
                                <div class="muted">{{ $item->tanggal_selesai ? $item->tanggal_selesai->format('d-m-Y') : '-' }}</div>
                            </td>
                            <td>{{ $item->kelas_count }} kelas</td>
                            <td>
                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('tahun-pelajaran.show', $item) }}" class="button button-muted">Lihat</a>
                                    <a href="{{ route('tahun-pelajaran.edit', $item) }}" class="button button-dark">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">Belum ada tahun pelajaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($tahunPelajaran as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama }}</p>
                            <p class="person-meta">{{ $item->kelas_count }} kelas</p>
                        </div>

                        @if ($item->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Mulai</dt>
                            <dd>{{ $item->tanggal_mulai ? $item->tanggal_mulai->format('d-m-Y') : '-' }}</dd>
                        </div>
                        <div>
                            <dt>Selesai</dt>
                            <dd>{{ $item->tanggal_selesai ? $item->tanggal_selesai->format('d-m-Y') : '-' }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('tahun-pelajaran.show', $item) }}" class="button button-muted">Lihat</a>
                        <a href="{{ route('tahun-pelajaran.edit', $item) }}" class="button button-dark">Edit</a>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada tahun pelajaran.</div>
            @endforelse
        </div>
    </section>

    @if ($tahunPelajaran->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $tahunPelajaran->currentPage() }} dari {{ $tahunPelajaran->lastPage() }}
            </div>
            <div class="actions">
                @if ($tahunPelajaran->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $tahunPelajaran->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($tahunPelajaran->hasMorePages())
                    <a href="{{ $tahunPelajaran->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
