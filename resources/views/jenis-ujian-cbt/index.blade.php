@extends('layouts.app')

@section('title', 'Jenis Ujian CBT - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Jenis ujian CBT</h1>
        </div>

        @izin('cbt.kelola')
            <a href="{{ route('jenis-ujian-cbt.create') }}" class="button button-primary">Tambah jenis ujian</a>
        @endizin
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
            <p class="stat-label">Memakai token</p>
            <p class="stat-value">{{ $jumlahToken }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('jenis-ujian-cbt.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid filter-grid-wide">
            <div class="field">
                <label for="kata_kunci">Cari jenis ujian</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Nama, kode, atau deskripsi" class="input">
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
                <a href="{{ route('jenis-ujian-cbt.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Jenis ujian</th>
                        <th>Token</th>
                        <th>Nilai</th>
                        <th>Kartu</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jenisUjianCbt as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->kode }} - Urutan {{ $item->urutan }}</p>
                            </td>
                            <td>
                                <span class="badge {{ $item->memerlukan_token ? 'badge-active' : 'badge-muted' }}">{{ $item->memerlukan_token ? 'Ya' : 'Tidak' }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $item->dapat_diterapkan_ke_nilai ? 'badge-active' : 'badge-muted' }}">{{ $item->dapat_diterapkan_ke_nilai ? 'Diterapkan' : 'Arsip' }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $item->tampil_di_kartu_peserta ? 'badge-active' : 'badge-muted' }}">{{ $item->tampil_di_kartu_peserta ? 'Tampil' : 'Tidak' }}</span>
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
                                    <a href="{{ route('jenis-ujian-cbt.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('cbt.kelola')
                                        <a href="{{ route('jenis-ujian-cbt.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada jenis ujian CBT.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($jenisUjianCbt as $item)
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
                            <dt>Token</dt>
                            <dd>{{ $item->memerlukan_token ? 'Memerlukan token' : 'Tanpa token' }}</dd>
                        </div>
                        <div>
                            <dt>Nilai</dt>
                            <dd>{{ $item->dapat_diterapkan_ke_nilai ? 'Dapat diterapkan' : 'Arsip/simulasi' }}</dd>
                        </div>
                        <div>
                            <dt>Kartu peserta</dt>
                            <dd>{{ $item->tampil_di_kartu_peserta ? 'Ditampilkan' : 'Tidak ditampilkan' }}</dd>
                        </div>
                        <div>
                            <dt>Urutan</dt>
                            <dd>{{ $item->urutan }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('jenis-ujian-cbt.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('cbt.kelola')
                            <a href="{{ route('jenis-ujian-cbt.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada jenis ujian CBT.</div>
            @endforelse
        </div>
    </section>

    @if ($jenisUjianCbt->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $jenisUjianCbt->currentPage() }} dari {{ $jenisUjianCbt->lastPage() }}</div>
            <div class="actions">
                @if ($jenisUjianCbt->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $jenisUjianCbt->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($jenisUjianCbt->hasMorePages())
                    <a href="{{ $jenisUjianCbt->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
