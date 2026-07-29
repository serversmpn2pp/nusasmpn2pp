@extends('layouts.app')

@section('title', 'Guru Mata Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Guru mata pelajaran</h1>
        </div>

        @izin('guru_mapel.kelola')
            <a href="{{ route('guru-mata-pelajaran.create', ['tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-primary">Tambah Penugasan</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total</p>
            <p class="stat-value">{{ $jumlahGuruMataPelajaran }}</p>
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

    <form action="{{ route('guru-mata-pelajaran.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid filter-grid-wide">
            <div class="field">
                <label for="kata_kunci">Cari penugasan</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kata_kunci }}" placeholder="Guru, mapel, kode, atau kelas" class="input">
            </div>

            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    <option value="">Semua tahun</option>
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
                <a href="{{ route('guru-mata-pelajaran.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Guru</th>
                        <th>Mata pelajaran</th>
                        <th>Kelas</th>
                        <th>Tahun pelajaran</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($guruMataPelajaran as $item)
                        @php
                            $pengaturanMapel = $item->mataPelajaran?->pengaturanUntuk(
                                (int) $item->tahun_pelajaran_id,
                                (int) $item->kelas?->tingkat,
                            );
                        @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->pegawai?->nama_lengkap ?: '-' }}</p>
                                <p class="person-meta">{{ $item->pegawai?->nip ?: 'NIP belum diisi' }} - {{ ucfirst($item->jenis_penugasan) }}</p>
                            </td>
                            <td>
                                <p class="person-name">{{ $item->mataPelajaran?->nama ?: '-' }}</p>
                                <p class="person-meta">{{ $pengaturanMapel?->kode ?: 'Kode belum diatur' }}</p>
                            </td>
                            <td>{{ $item->kelas?->nama ?: '-' }}</td>
                            <td>{{ $item->tahunPelajaran?->nama ?: '-' }}</td>
                            <td>
                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('guru-mata-pelajaran.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('guru_mapel.kelola')
                                        @if ($item->aktif && $item->jenis_penugasan === 'pengampu')
                                            <a href="{{ route('guru-mata-pelajaran.ganti-guru', $item) }}" class="button button-primary">Ganti Guru</a>
                                        @endif
                                        <a href="{{ route('guru-mata-pelajaran.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada penugasan guru mata pelajaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($guruMataPelajaran as $item)
                @php
                    $pengaturanMapel = $item->mataPelajaran?->pengaturanUntuk(
                        (int) $item->tahun_pelajaran_id,
                        (int) $item->kelas?->tingkat,
                    );
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->pegawai?->nama_lengkap ?: '-' }}</p>
                            <p class="person-meta">{{ $item->mataPelajaran?->nama ?: '-' }} - {{ $item->kelas?->nama ?: '-' }}</p>
                        </div>

                        @if ($item->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Tahun</dt>
                            <dd>{{ $item->tahunPelajaran?->nama ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Kode mapel</dt>
                            <dd>{{ $pengaturanMapel?->kode ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Jenis</dt>
                            <dd>{{ ucfirst($item->jenis_penugasan) }}</dd>
                        </div>
                        <div>
                            <dt>NIP</dt>
                            <dd>{{ $item->pegawai?->nip ?: '-' }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('guru-mata-pelajaran.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('guru_mapel.kelola')
                            @if ($item->aktif && $item->jenis_penugasan === 'pengampu')
                                <a href="{{ route('guru-mata-pelajaran.ganti-guru', $item) }}" class="button button-primary">Ganti Guru</a>
                            @endif
                            <a href="{{ route('guru-mata-pelajaran.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada penugasan guru mata pelajaran.</div>
            @endforelse
        </div>
    </section>

    @if ($guruMataPelajaran->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $guruMataPelajaran->currentPage() }} dari {{ $guruMataPelajaran->lastPage() }}
            </div>
            <div class="actions">
                @if ($guruMataPelajaran->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $guruMataPelajaran->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($guruMataPelajaran->hasMorePages())
                    <a href="{{ $guruMataPelajaran->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
