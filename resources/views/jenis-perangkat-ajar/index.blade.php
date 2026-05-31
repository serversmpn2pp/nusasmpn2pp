@extends('layouts.app')

@section('title', 'Jenis Perangkat Ajar - NUSA')

@section('content')
    <style>
        .teaching-device-filter-grid {
            grid-template-columns: minmax(180px, 1fr) 180px 180px auto;
        }

        @media (max-width: 900px) {
            .teaching-device-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .teaching-device-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kurikulum</p>
            <h1 class="page-title">Jenis perangkat ajar</h1>
        </div>

        <a href="{{ route('jenis-perangkat-ajar.create') }}" class="button button-primary">Tambah jenis perangkat</a>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total jenis</p>
            <p class="stat-value">{{ $jumlahJenis }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Jenis aktif</p>
            <p class="stat-value">{{ $jumlahAktif }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Wajib diunggah</p>
            <p class="stat-value">{{ $jumlahWajib }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('jenis-perangkat-ajar.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid teaching-device-filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari jenis perangkat</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Nama, kode, atau deskripsi" class="input">
            </div>

            <div class="field">
                <label for="kewajiban">Kewajiban</label>
                <select id="kewajiban" name="kewajiban" class="select">
                    <option value="semua" @selected($kewajiban === 'semua')>Semua</option>
                    <option value="wajib" @selected($kewajiban === 'wajib')>Wajib</option>
                    <option value="opsional" @selected($kewajiban === 'opsional')>Opsional</option>
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
                <a href="{{ route('jenis-perangkat-ajar.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Jenis perangkat</th>
                        <th>Kewajiban</th>
                        <th>Urutan</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jenisPerangkatAjar as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->kode }}</p>
                            </td>
                            <td>
                                @if ($item->wajib)
                                    <span class="badge badge-active">Wajib</span>
                                @else
                                    <span class="badge badge-inactive">Opsional</span>
                                @endif
                            </td>
                            <td>{{ $item->urutan }}</td>
                            <td>
                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('jenis-perangkat-ajar.show', $item) }}" class="button button-muted">Lihat</a>
                                    <a href="{{ route('jenis-perangkat-ajar.edit', $item) }}" class="button button-dark">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">Belum ada jenis perangkat ajar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($jenisPerangkatAjar as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama }}</p>
                            <p class="person-meta">{{ $item->kode }}</p>
                        </div>

                        @if ($item->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Kewajiban</dt>
                            <dd>{{ $item->wajib ? 'Wajib' : 'Opsional' }}</dd>
                        </div>
                        <div>
                            <dt>Urutan</dt>
                            <dd>{{ $item->urutan }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('jenis-perangkat-ajar.show', $item) }}" class="button button-muted">Lihat</a>
                        <a href="{{ route('jenis-perangkat-ajar.edit', $item) }}" class="button button-dark">Edit</a>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada jenis perangkat ajar.</div>
            @endforelse
        </div>
    </section>

    @if ($jenisPerangkatAjar->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $jenisPerangkatAjar->currentPage() }} dari {{ $jenisPerangkatAjar->lastPage() }}</div>
            <div class="actions">
                @if ($jenisPerangkatAjar->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $jenisPerangkatAjar->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($jenisPerangkatAjar->hasMorePages())
                    <a href="{{ $jenisPerangkatAjar->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
