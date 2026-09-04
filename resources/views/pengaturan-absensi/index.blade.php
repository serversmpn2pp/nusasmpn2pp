@extends('layouts.app')

@section('title', 'Pengaturan Presensi - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Presensi</p>
            <h1 class="page-title">Pengaturan presensi</h1>
        </div>

        @izin('absensi.pengaturan_kelola')
            <a href="{{ route('pengaturan-absensi.create') }}" class="button button-primary">Tambah pengaturan</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total</p>
            <p class="stat-value">{{ $jumlahPengaturanAbsensi }}</p>
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

    <form action="{{ route('pengaturan-absensi.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid">
            <div class="field">
                <label for="hari">Hari</label>
                <select id="hari" name="hari" class="select">
                    <option value="semua" @selected($hari === 'semua')>Semua hari</option>
                    @foreach (\App\Models\PengaturanAbsensi::DAFTAR_HARI as $key => $item)
                        <option value="{{ $key }}" @selected($hari === $key)>{{ $item['label'] }}</option>
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
                <a href="{{ route('pengaturan-absensi.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Hari</th>
                        <th>Scan masuk</th>
                        <th>Jam masuk</th>
                        <th>Scan pulang</th>
                        <th>Jam pulang</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pengaturanAbsensi as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->labelHari() }}</p>
                                <p class="person-meta">{{ $item->keterangan ?: 'Pengaturan jam presensi harian' }}</p>
                            </td>
                            <td>{{ $item->rentangMasuk() }}</td>
                            <td>{{ $item->formatJam($item->jam_masuk) }}</td>
                            <td>
                                @if ($item->pulangJumatDibedakan())
                                    <p class="person-name">Siswi: {{ $item->rentangPulang('P') }}</p>
                                    <p class="person-meta">Laki-laki: {{ $item->rentangPulang('L') }}</p>
                                @else
                                    {{ $item->rentangPulang() }}
                                @endif
                            </td>
                            <td>
                                @if ($item->pulangJumatDibedakan())
                                    <p class="person-name">Siswi: {{ $item->formatJam($item->jam_pulang_perempuan) }}</p>
                                    <p class="person-meta">Laki-laki: {{ $item->formatJam($item->jam_pulang) }}</p>
                                @else
                                    {{ $item->formatJam($item->jam_pulang) }}
                                @endif
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
                                    <a href="{{ route('pengaturan-absensi.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('absensi.pengaturan_kelola')
                                        <a href="{{ route('pengaturan-absensi.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada pengaturan presensi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($pengaturanAbsensi as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->labelHari() }}</p>
                            <p class="person-meta">
                                Masuk {{ $item->formatJam($item->jam_masuk) }} -
                                @if ($item->pulangJumatDibedakan())
                                    Pulang siswi {{ $item->formatJam($item->jam_pulang_perempuan) }}, laki-laki {{ $item->formatJam($item->jam_pulang) }}
                                @else
                                    Pulang {{ $item->formatJam($item->jam_pulang) }}
                                @endif
                            </p>
                        </div>

                        @if ($item->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Scan masuk</dt>
                            <dd>{{ $item->rentangMasuk() }}</dd>
                        </div>
                        <div>
                            <dt>Scan pulang</dt>
                            <dd>
                                @if ($item->pulangJumatDibedakan())
                                    Siswi {{ $item->rentangPulang('P') }}<br>
                                    Laki-laki {{ $item->rentangPulang('L') }}
                                @else
                                    {{ $item->rentangPulang() }}
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('pengaturan-absensi.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('absensi.pengaturan_kelola')
                            <a href="{{ route('pengaturan-absensi.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada pengaturan presensi.</div>
            @endforelse
        </div>
    </section>

    @if ($pengaturanAbsensi->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $pengaturanAbsensi->currentPage() }} dari {{ $pengaturanAbsensi->lastPage() }}
            </div>
            <div class="actions">
                @if ($pengaturanAbsensi->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $pengaturanAbsensi->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($pengaturanAbsensi->hasMorePages())
                    <a href="{{ $pengaturanAbsensi->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
