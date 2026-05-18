@extends('layouts.app')

@section('title', 'Pegawai - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Database sekolah</p>
            <h1 class="page-title">Data pegawai</h1>
        </div>

        <div class="actions">
            <a href="{{ route('pegawai.import.create') }}" class="button button-muted">Import Excel</a>
            <a href="{{ route('pegawai.create') }}" class="button button-primary">Tambah pegawai</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total</p>
            <p class="stat-value">{{ $jumlahPegawai }}</p>
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

    @if (session('ringkasan_import'))
        @php
            $ringkasan = session('ringkasan_import');
        @endphp
        <div class="panel panel-pad" style="margin-bottom: 20px;">
            <h2 class="panel-title">Ringkasan import</h2>
            <div class="stats-grid" style="margin: 16px 0 0;">
                <div class="panel stat">
                    <p class="stat-label">Dibaca</p>
                    <p class="stat-value">{{ $ringkasan['dibaca'] }}</p>
                </div>
                <div class="panel stat active">
                    <p class="stat-label">Ditambahkan</p>
                    <p class="stat-value">{{ $ringkasan['ditambahkan'] }}</p>
                </div>
                <div class="panel stat inactive">
                    <p class="stat-label">Diperbarui</p>
                    <p class="stat-value">{{ $ringkasan['diperbarui'] }}</p>
                </div>
                <div class="panel stat">
                    <p class="stat-label">Dilewati</p>
                    <p class="stat-value">{{ $ringkasan['dilewati'] }}</p>
                </div>
            </div>

            @if (! empty($ringkasan['catatan']))
                <div class="alert alert-danger" style="margin: 16px 0 0;">
                    <strong>Catatan import</strong>
                    <ul>
                        @foreach (array_slice($ringkasan['catatan'], 0, 8) as $catatan)
                            <li>{{ $catatan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    <form action="{{ route('pegawai.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari pegawai</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kata_kunci }}" placeholder="Nama, NIP, NUPTK, atau NIK" class="input">
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
                <a href="{{ route('pegawai.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Pegawai</th>
                        <th>Identitas</th>
                        <th>Kepegawaian</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pegawai as $item)
                        <tr>
                            <td>
                                <div class="person">
                                    <div class="avatar avatar-sm">
                                        @if ($item->foto)
                                            <img src="{{ asset('storage/' . $item->foto) }}" alt="Foto {{ $item->nama_lengkap }}">
                                        @else
                                            {{ strtoupper(mb_substr($item->nama_lengkap, 0, 1)) }}
                                        @endif
                                    </div>
                                    <div>
                                        <p class="person-name">{{ $item->nama_lengkap }}</p>
                                        <p class="person-meta">{{ $item->jenis_kelamin === 'L' ? 'Laki-laki' : ($item->jenis_kelamin === 'P' ? 'Perempuan' : '-') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>NIP: {{ $item->nip ?: '-' }}</div>
                                <div class="muted">NUPTK: {{ $item->nuptk ?: '-' }}</div>
                            </td>
                            <td>
                                <div>{{ $item->jenis_pegawai ?: '-' }}</div>
                                <div class="muted">{{ $item->jabatan_utama ?: $item->status_kepegawaian ?: '-' }}</div>
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
                                    <a href="{{ route('pegawai.show', $item) }}" class="button button-muted">Lihat</a>
                                    <a href="{{ route('pegawai.edit', $item) }}" class="button button-dark">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">Belum ada data pegawai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($pegawai as $item)
                <article class="mobile-card">
                    <div class="mobile-card-main">
                        <div class="avatar avatar-md">
                            @if ($item->foto)
                                <img src="{{ asset('storage/' . $item->foto) }}" alt="Foto {{ $item->nama_lengkap }}">
                            @else
                                {{ strtoupper(mb_substr($item->nama_lengkap, 0, 1)) }}
                            @endif
                        </div>

                        <div style="min-width:0; flex:1;">
                            <div class="mobile-card-head">
                                <div>
                                    <p class="person-name">{{ $item->nama_lengkap }}</p>
                                    <p class="person-meta">{{ $item->jabatan_utama ?: $item->jenis_pegawai ?: '-' }}</p>
                                </div>

                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </div>

                            <dl class="quick-facts">
                                <div>
                                    <dt>NIP</dt>
                                    <dd>{{ $item->nip ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt>NUPTK</dt>
                                    <dd>{{ $item->nuptk ?: '-' }}</dd>
                                </div>
                            </dl>

                            <div class="actions" style="margin-top: 14px;">
                                <a href="{{ route('pegawai.show', $item) }}" class="button button-muted">Lihat</a>
                                <a href="{{ route('pegawai.edit', $item) }}" class="button button-dark">Edit</a>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada data pegawai.</div>
            @endforelse
        </div>
    </section>

    @if ($pegawai->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $pegawai->currentPage() }} dari {{ $pegawai->lastPage() }}
            </div>
            <div class="actions">
                @if ($pegawai->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $pegawai->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($pegawai->hasMorePages())
                    <a href="{{ $pegawai->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
