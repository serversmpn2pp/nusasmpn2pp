@extends('layouts.app')

@section('title', 'Peran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Hak akses</p>
            <h1 class="page-title">Role / Peran</h1>
        </div>

        <div class="actions">
            <a href="{{ route('akun-pegawai.index') }}" class="button button-muted">Akun pegawai</a>
            <a href="{{ route('peran.create') }}" class="button button-primary">Tambah peran</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if (session('gagal'))
        <div class="alert alert-danger">{{ session('gagal') }}</div>
    @endif

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total</p>
            <p class="stat-value">{{ $ringkasan['total'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Aktif</p>
            <p class="stat-value">{{ $ringkasan['aktif'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Sistem</p>
            <p class="stat-value">{{ $ringkasan['sistem'] }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Tambahan</p>
            <p class="stat-value">{{ $ringkasan['tambahan'] }}</p>
        </div>
    </div>

    <form action="{{ route('peran.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari peran</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" class="input" placeholder="Nama, kode, atau deskripsi">
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
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('peran.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width: 980px;">
                <thead>
                    <tr>
                        <th>Peran</th>
                        <th>Kode</th>
                        <th>Status</th>
                        <th>Akun</th>
                        <th>Izin</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($peran as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->deskripsi ?: '-' }}</p>
                            </td>
                            <td>{{ $item->kode }}</td>
                            <td>
                                <div class="actions">
                                    <span class="badge {{ $item->aktif ? 'badge-active' : 'badge-inactive' }}">
                                        {{ $item->aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                    @if ($item->sistem)
                                        <span class="badge badge-muted">Sistem</span>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $item->pengguna_count }}</td>
                            <td>{{ $item->izin_count }}</td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('peran.edit', $item) }}" class="button button-dark button-sm">Edit</a>
                                    @if (! $item->sistem)
                                        <form action="{{ route('peran.destroy', $item) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="button button-danger button-sm">Nonaktifkan</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada peran yang sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($peran as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama }}</p>
                            <p class="person-meta">{{ $item->kode }}</p>
                        </div>
                        <span class="badge {{ $item->aktif ? 'badge-active' : 'badge-inactive' }}">
                            {{ $item->aktif ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    <p class="help-text">{{ $item->deskripsi ?: '-' }}</p>

                    <dl class="quick-facts">
                        <div>
                            <dt>Jenis</dt>
                            <dd>{{ $item->sistem ? 'Sistem' : 'Tambahan' }}</dd>
                        </div>
                        <div>
                            <dt>Akun</dt>
                            <dd>{{ $item->pengguna_count }}</dd>
                        </div>
                        <div>
                            <dt>Izin</dt>
                            <dd>{{ $item->izin_count }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 12px;">
                        <a href="{{ route('peran.edit', $item) }}" class="button button-dark button-sm">Edit</a>
                        @if (! $item->sistem)
                            <form action="{{ route('peran.destroy', $item) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button button-danger button-sm">Nonaktifkan</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada peran yang sesuai filter.</div>
            @endforelse
        </div>
    </section>

    @if ($peran->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $peran->currentPage() }} dari {{ $peran->lastPage() }}
            </div>
            <div class="actions">
                @if ($peran->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $peran->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($peran->hasMorePages())
                    <a href="{{ $peran->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
