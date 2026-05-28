@extends('layouts.app')

@section('title', 'Jam Absensi Pegawai - NUSA')

@section('content')
    <style>
        .pegawai-filter-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 170px 190px 150px auto;
            gap: 12px;
            align-items: end;
        }

        @media (max-width: 980px) {
            .pegawai-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Absensi Pegawai</p>
            <h1 class="page-title">Jam absensi pegawai</h1>
        </div>

        @izin('absensi.pengaturan_kelola')
            <a href="{{ route('pengaturan-absensi-pegawai.create') }}" class="button button-primary">Tambah jadwal</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total</p>
            <p class="stat-value">{{ $jumlahPengaturanAbsensiPegawai }}</p>
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

    <form action="{{ route('pengaturan-absensi-pegawai.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="pegawai-filter-grid">
            <div class="field">
                <label for="q">Pencarian</label>
                <input id="q" name="q" type="text" value="{{ $kataKunci }}" class="input" placeholder="Nama jadwal, pegawai, atau NIP">
            </div>

            <div class="field">
                <label for="hari">Hari</label>
                <select id="hari" name="hari" class="select">
                    <option value="semua_hari" @selected($hari === 'semua_hari')>Semua hari</option>
                    @foreach (\App\Models\PengaturanAbsensiPegawai::DAFTAR_HARI as $key => $item)
                        <option value="{{ $key }}" @selected($hari === $key)>{{ $item['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="cakupan">Cakupan</label>
                <select id="cakupan" name="cakupan" class="select">
                    <option value="semua_cakupan" @selected($cakupan === 'semua_cakupan')>Semua cakupan</option>
                    @foreach (\App\Models\PengaturanAbsensiPegawai::DAFTAR_CAKUPAN as $key => $label)
                        <option value="{{ $key }}" @selected($cakupan === $key)>{{ $label }}</option>
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
                <a href="{{ route('pengaturan-absensi-pegawai.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width: 1100px;">
                <thead>
                    <tr>
                        <th>Jadwal</th>
                        <th>Sasaran</th>
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
                    @forelse ($pengaturanAbsensiPegawai as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama_jadwal }}</p>
                                <p class="person-meta">{{ $item->keterangan ?: 'Pengaturan jam absensi pegawai' }}</p>
                            </td>
                            <td>
                                <div>{{ $item->labelCakupan() }}</div>
                                <p class="person-meta">{{ $item->labelSasaran() }}</p>
                            </td>
                            <td>{{ $item->labelHari() }}</td>
                            <td>{{ $item->rentangMasuk() }}</td>
                            <td>{{ $item->formatJam($item->jam_masuk) }}</td>
                            <td>{{ $item->rentangPulang() }}</td>
                            <td>{{ $item->formatJam($item->jam_pulang) }}</td>
                            <td>
                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('pengaturan-absensi-pegawai.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('absensi.pengaturan_kelola')
                                        <a href="{{ route('pengaturan-absensi-pegawai.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="empty-state">Belum ada pengaturan absensi pegawai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($pengaturanAbsensiPegawai as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama_jadwal }}</p>
                            <p class="person-meta">{{ $item->labelHari() }} · {{ $item->labelSasaran() }}</p>
                        </div>

                        @if ($item->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Cakupan</dt>
                            <dd>{{ $item->labelCakupan() }}</dd>
                        </div>
                        <div>
                            <dt>Jam masuk</dt>
                            <dd>{{ $item->formatJam($item->jam_masuk) }}</dd>
                        </div>
                        <div>
                            <dt>Scan masuk</dt>
                            <dd>{{ $item->rentangMasuk() }}</dd>
                        </div>
                        <div>
                            <dt>Scan pulang</dt>
                            <dd>{{ $item->rentangPulang() }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('pengaturan-absensi-pegawai.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('absensi.pengaturan_kelola')
                            <a href="{{ route('pengaturan-absensi-pegawai.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada pengaturan absensi pegawai.</div>
            @endforelse
        </div>
    </section>

    @if ($pengaturanAbsensiPegawai->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $pengaturanAbsensiPegawai->currentPage() }} dari {{ $pengaturanAbsensiPegawai->lastPage() }}
            </div>
            <div class="actions">
                @if ($pengaturanAbsensiPegawai->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $pengaturanAbsensiPegawai->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($pengaturanAbsensiPegawai->hasMorePages())
                    <a href="{{ $pengaturanAbsensiPegawai->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
