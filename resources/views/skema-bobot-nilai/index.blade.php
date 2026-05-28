@extends('layouts.app')

@section('title', 'Skema Bobot Nilai - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian</p>
            <h1 class="page-title">Skema bobot nilai</h1>
        </div>

        @izin('nilai.skema_kelola')
            <a href="{{ route('skema-bobot-nilai.create') }}" class="button button-primary">Tambah skema</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total</p>
            <p class="stat-value">{{ $jumlahSkemaBobotNilai }}</p>
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

    <form action="{{ route('skema-bobot-nilai.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid filter-grid-wide">
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
                <a href="{{ route('skema-bobot-nilai.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Ruang lingkup</th>
                        <th>Formatif</th>
                        <th>Sumatif</th>
                        <th>STS</th>
                        <th>SAS/SAJ</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($skemaBobotNilai as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->tahunPelajaran?->nama ?: '-' }}</p>
                                <p class="person-meta">{{ ucfirst($item->semester) }} - {{ $item->labelTingkat() }}</p>
                            </td>
                            <td>{{ $item->bobot_formatif }}%</td>
                            <td>{{ $item->bobot_sumatif }}%</td>
                            <td>{{ $item->bobot_sts }}%</td>
                            <td>{{ $item->bobot_sas_saj }}%</td>
                            <td>{{ $item->totalBobot() }}%</td>
                            <td>
                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('skema-bobot-nilai.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('nilai.skema_kelola')
                                        <a href="{{ route('skema-bobot-nilai.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Belum ada skema bobot nilai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($skemaBobotNilai as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->tahunPelajaran?->nama ?: '-' }}</p>
                            <p class="person-meta">{{ ucfirst($item->semester) }} - {{ $item->labelTingkat() }}</p>
                        </div>

                        @if ($item->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Formatif</dt>
                            <dd>{{ $item->bobot_formatif }}%</dd>
                        </div>
                        <div>
                            <dt>Sumatif</dt>
                            <dd>{{ $item->bobot_sumatif }}%</dd>
                        </div>
                        <div>
                            <dt>STS</dt>
                            <dd>{{ $item->bobot_sts }}%</dd>
                        </div>
                        <div>
                            <dt>SAS/SAJ</dt>
                            <dd>{{ $item->bobot_sas_saj }}%</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('skema-bobot-nilai.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('nilai.skema_kelola')
                            <a href="{{ route('skema-bobot-nilai.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada skema bobot nilai.</div>
            @endforelse
        </div>
    </section>

    @if ($skemaBobotNilai->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $skemaBobotNilai->currentPage() }} dari {{ $skemaBobotNilai->lastPage() }}
            </div>
            <div class="actions">
                @if ($skemaBobotNilai->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $skemaBobotNilai->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($skemaBobotNilai->hasMorePages())
                    <a href="{{ $skemaBobotNilai->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
