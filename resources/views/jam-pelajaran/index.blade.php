@extends('layouts.app')

@section('title', 'Jam Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Jam pelajaran</h1>
        </div>

        @izin('jadwal.kelola')
            <a href="{{ route('jam-pelajaran.create') }}" class="button button-primary">Tambah jam</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total</p>
            <p class="stat-value">{{ $jumlahJamPelajaran }}</p>
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

    <form action="{{ route('jam-pelajaran.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid">
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
                <a href="{{ route('jam-pelajaran.index') }}" class="button button-muted">Reset</a>
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
                        <th>Waktu</th>
                        <th>Jenis</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jamPelajaran as $item)
                        <tr>
                            <td>{{ $item->labelHari() }}</td>
                            <td>
                                <p class="person-name">{{ $item->label ?: 'Jam ' . $item->nomor_jam }}</p>
                                <p class="person-meta">Nomor {{ $item->nomor_jam }}</p>
                            </td>
                            <td>{{ $item->formatJam($item->jam_mulai) }} - {{ $item->formatJam($item->jam_selesai) }}</td>
                            <td>{{ $item->labelJenis() }}</td>
                            <td>
                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('jam-pelajaran.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('jadwal.kelola')
                                        <a href="{{ route('jam-pelajaran.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada jam pelajaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($jamPelajaran as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->label ?: 'Jam ' . $item->nomor_jam }}</p>
                            <p class="person-meta">{{ $item->labelHari() }} - {{ $item->formatJam($item->jam_mulai) }} sampai {{ $item->formatJam($item->jam_selesai) }}</p>
                        </div>

                        @if ($item->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Nomor</dt>
                            <dd>{{ $item->nomor_jam }}</dd>
                        </div>
                        <div>
                            <dt>Jenis</dt>
                            <dd>{{ $item->labelJenis() }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('jam-pelajaran.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('jadwal.kelola')
                            <a href="{{ route('jam-pelajaran.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada jam pelajaran.</div>
            @endforelse
        </div>
    </section>

    @if ($jamPelajaran->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $jamPelajaran->currentPage() }} dari {{ $jamPelajaran->lastPage() }}</div>
            <div class="actions">
                @if ($jamPelajaran->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $jamPelajaran->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($jamPelajaran->hasMorePages())
                    <a href="{{ $jamPelajaran->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
