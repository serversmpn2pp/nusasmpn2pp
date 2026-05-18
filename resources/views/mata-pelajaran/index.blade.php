@extends('layouts.app')

@section('title', 'Mata Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Mata pelajaran</h1>
        </div>

        <a href="{{ route('mata-pelajaran.create') }}" class="button button-primary">Tambah mata pelajaran</a>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total</p>
            <p class="stat-value">{{ $jumlahMataPelajaran }}</p>
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

    <form action="{{ route('mata-pelajaran.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid filter-grid-wide">
            <div class="field">
                <label for="kata_kunci">Cari mata pelajaran</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kata_kunci }}" placeholder="Nama, kode, atau kelompok" class="input">
            </div>

            <div class="field">
                <label for="tingkat">Tingkat</label>
                <select id="tingkat" name="tingkat" class="select">
                    <option value="semua" @selected($tingkat === 'semua')>Semua</option>
                    <option value="7" @selected($tingkat === '7')>VII</option>
                    <option value="8" @selected($tingkat === '8')>VIII</option>
                    <option value="9" @selected($tingkat === '9')>IX</option>
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
                <a href="{{ route('mata-pelajaran.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Mata pelajaran</th>
                        <th>Kelompok</th>
                        <th>Tingkat</th>
                        <th>KKM/KKTP</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mataPelajaran as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->kode ?: 'Kode belum diisi' }}{{ $item->urutan ? ' - Urutan ' . $item->urutan : '' }}</p>
                            </td>
                            <td>{{ $item->kelompok ?: '-' }}</td>
                            <td>{{ $item->tingkat ? 'Kelas ' . $item->tingkat : 'Semua' }}</td>
                            <td>{{ $item->kkm ?? '-' }}</td>
                            <td>
                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('mata-pelajaran.show', $item) }}" class="button button-muted">Lihat</a>
                                    <a href="{{ route('mata-pelajaran.edit', $item) }}" class="button button-dark">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada mata pelajaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($mataPelajaran as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama }}</p>
                            <p class="person-meta">{{ $item->kode ?: 'Kode belum diisi' }}</p>
                        </div>

                        @if ($item->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Kelompok</dt>
                            <dd>{{ $item->kelompok ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Tingkat</dt>
                            <dd>{{ $item->tingkat ? 'Kelas ' . $item->tingkat : 'Semua' }}</dd>
                        </div>
                        <div>
                            <dt>KKM/KKTP</dt>
                            <dd>{{ $item->kkm ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt>Urutan</dt>
                            <dd>{{ $item->urutan }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('mata-pelajaran.show', $item) }}" class="button button-muted">Lihat</a>
                        <a href="{{ route('mata-pelajaran.edit', $item) }}" class="button button-dark">Edit</a>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada mata pelajaran.</div>
            @endforelse
        </div>
    </section>

    @if ($mataPelajaran->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $mataPelajaran->currentPage() }} dari {{ $mataPelajaran->lastPage() }}
            </div>
            <div class="actions">
                @if ($mataPelajaran->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $mataPelajaran->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($mataPelajaran->hasMorePages())
                    <a href="{{ $mataPelajaran->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
