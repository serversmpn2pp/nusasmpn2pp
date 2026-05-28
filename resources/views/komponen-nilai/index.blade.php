@extends('layouts.app')

@section('title', 'Komponen Nilai - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian</p>
            <h1 class="page-title">Komponen nilai</h1>
        </div>

        @izin('nilai.komponen_kelola')
            <a href="{{ route('komponen-nilai.create') }}" class="button button-primary">Tambah komponen</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total</p>
            <p class="stat-value">{{ $jumlahKomponenNilai }}</p>
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

    <form action="{{ route('komponen-nilai.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid filter-grid-wide">
            <div class="field">
                <label for="kata_kunci">Cari komponen</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kata_kunci }}" placeholder="Komponen, guru, mapel, atau kelas" class="input">
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
                <label for="semester">Semester</label>
                <select id="semester" name="semester" class="select">
                    <option value="semua" @selected($semester === 'semua')>Semua</option>
                    <option value="ganjil" @selected($semester === 'ganjil')>Ganjil</option>
                    <option value="genap" @selected($semester === 'genap')>Genap</option>
                </select>
            </div>

            <div class="field">
                <label for="jenis_komponen">Jenis</label>
                <select id="jenis_komponen" name="jenis_komponen" class="select">
                    <option value="semua" @selected($jenis === 'semua')>Semua</option>
                    <option value="formatif" @selected($jenis === 'formatif')>Formatif</option>
                    <option value="sumatif" @selected($jenis === 'sumatif')>Sumatif</option>
                    <option value="sts" @selected($jenis === 'sts')>STS</option>
                    <option value="sas_saj" @selected($jenis === 'sas_saj')>SAS/SAJ</option>
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
                <a href="{{ route('komponen-nilai.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Komponen</th>
                        <th>Guru mapel</th>
                        <th>Kelas</th>
                        <th>Semester</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($komponenNilai as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->labelJenis() }}{{ $item->urutan ? ' - Urutan ' . $item->urutan : '' }}</p>
                            </td>
                            <td>
                                <p class="person-name">{{ $item->guruMataPelajaran?->mataPelajaran?->nama ?: '-' }}</p>
                                <p class="person-meta">{{ $item->guruMataPelajaran?->pegawai?->nama_lengkap ?: '-' }}</p>
                            </td>
                            <td>{{ $item->guruMataPelajaran?->kelas?->nama ?: '-' }}</td>
                            <td>{{ ucfirst($item->semester) }}</td>
                            <td>{{ $item->tanggal_penilaian ? $item->tanggal_penilaian->format('d-m-Y') : '-' }}</td>
                            <td>
                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('komponen-nilai.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('nilai.input')
                                        @if ($item->aktif)
                                            <a href="{{ route('input-nilai.index', ['komponen_nilai_id' => $item->id]) }}" class="button button-primary">Input</a>
                                        @endif
                                    @endizin
                                    @izin('nilai.komponen_kelola')
                                        <a href="{{ route('komponen-nilai.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada komponen nilai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($komponenNilai as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama }}</p>
                            <p class="person-meta">{{ $item->labelJenis() }} - {{ $item->guruMataPelajaran?->kelas?->nama ?: '-' }}</p>
                        </div>

                        @if ($item->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Mapel</dt>
                            <dd>{{ $item->guruMataPelajaran?->mataPelajaran?->nama ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Guru</dt>
                            <dd>{{ $item->guruMataPelajaran?->pegawai?->nama_lengkap ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Semester</dt>
                            <dd>{{ ucfirst($item->semester) }}</dd>
                        </div>
                        <div>
                            <dt>Tanggal</dt>
                            <dd>{{ $item->tanggal_penilaian ? $item->tanggal_penilaian->format('d-m-Y') : '-' }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('komponen-nilai.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('nilai.input')
                            @if ($item->aktif)
                                <a href="{{ route('input-nilai.index', ['komponen_nilai_id' => $item->id]) }}" class="button button-primary">Input</a>
                            @endif
                        @endizin
                        @izin('nilai.komponen_kelola')
                            <a href="{{ route('komponen-nilai.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada komponen nilai.</div>
            @endforelse
        </div>
    </section>

    @if ($komponenNilai->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $komponenNilai->currentPage() }} dari {{ $komponenNilai->lastPage() }}
            </div>
            <div class="actions">
                @if ($komponenNilai->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $komponenNilai->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($komponenNilai->hasMorePages())
                    <a href="{{ $komponenNilai->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
