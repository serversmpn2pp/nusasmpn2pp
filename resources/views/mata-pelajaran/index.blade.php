@extends('layouts.app')

@section('title', 'Mata Pelajaran - NUSA')

@section('content')
    @php
        $romawi = [7 => 'VII', 8 => 'VIII', 9 => 'IX'];
        $tahunDipilih = $tahunPelajaran->firstWhere('id', $tahunPelajaranId);
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Mata pelajaran</h1>
            <p class="help-text">Satu nama mapel dengan kode dan jenis penilaian yang sesuai untuk setiap tingkat.</p>
        </div>

        @izin('mata_pelajaran.kelola')
            <a href="{{ route('mata-pelajaran.create', ['tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-primary">Tambah mata pelajaran</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total mapel</p>
            <p class="stat-value">{{ $jumlahMataPelajaran }}</p>
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

    <form action="{{ route('mata-pelajaran.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid filter-grid-wide">
            <div class="field">
                <label for="kata_kunci">Cari mata pelajaran</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Nama, kode, atau kelompok" class="input">
            </div>

            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    @foreach ($tahunPelajaran as $item)
                        <option value="{{ $item->id }}" @selected((int) $tahunPelajaranId === (int) $item->id)>
                            {{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}
                        </option>
                    @endforeach
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
                <a href="{{ route('mata-pelajaran.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="panel-pad" style="border-bottom: 1px solid #dce5ee;">
            <p class="stat-label">Pengaturan ditampilkan untuk</p>
            <p class="person-name">{{ $tahunDipilih?->nama ?? 'Tahun pelajaran belum tersedia' }}</p>
        </div>

        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Mata pelajaran</th>
                        <th>Kelompok</th>
                        <th>Kode per tingkat</th>
                        <th>Penilaian</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mataPelajaran as $item)
                        @php
                            $pengaturanAktif = $item->pengaturanTingkat->where('aktif', true);
                        @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">Urutan {{ $item->urutan }}</p>
                            </td>
                            <td>{{ $item->kelompok ?: '-' }}</td>
                            <td>
                                @forelse ($pengaturanAktif as $pengaturan)
                                    <div style="margin-bottom: 5px;">
                                        <span class="badge badge-active">{{ $romawi[$pengaturan->tingkat] ?? $pengaturan->tingkat }}</span>
                                        <strong style="margin-left: 6px;">{{ $pengaturan->kode }}</strong>
                                    </div>
                                @empty
                                    <span class="person-meta">Belum diatur</span>
                                @endforelse
                            </td>
                            <td>
                                @forelse ($pengaturanAktif as $pengaturan)
                                    <div style="min-height: 29px;">
                                        Kelas {{ $romawi[$pengaturan->tingkat] ?? $pengaturan->tingkat }}:
                                        <strong>{{ $item->menggunakanPredikat() ? 'SB/B/C/K' : 'KKM '.($pengaturan->kkm ?? '-') }}</strong>
                                    </div>
                                @empty
                                    -
                                @endforelse
                            </td>
                            <td>
                                <span class="badge {{ $item->aktif ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $item->aktif ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('mata-pelajaran.show', [$item, 'tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-muted">Lihat</a>
                                    @izin('mata_pelajaran.kelola')
                                        <a href="{{ route('mata-pelajaran.edit', [$item, 'tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada mata pelajaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($mataPelajaran as $item)
                @php
                    $pengaturanAktif = $item->pengaturanTingkat->where('aktif', true);
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama }}</p>
                            <p class="person-meta">{{ $item->kelompok ?: 'Kelompok belum diisi' }}</p>
                        </div>
                        <span class="badge {{ $item->aktif ? 'badge-active' : 'badge-inactive' }}">
                            {{ $item->aktif ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    <dl class="quick-facts">
                        @forelse ($pengaturanAktif as $pengaturan)
                            <div>
                                <dt>Kelas {{ $romawi[$pengaturan->tingkat] ?? $pengaturan->tingkat }}</dt>
                                <dd>
                                    {{ $pengaturan->kode }} ·
                                    {{ $item->menggunakanPredikat() ? 'Predikat SB/B/C/K' : 'KKM '.($pengaturan->kkm ?? '-') }}
                                </dd>
                            </div>
                        @empty
                            <div>
                                <dt>Pengaturan tingkat</dt>
                                <dd>Belum diatur</dd>
                            </div>
                        @endforelse
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('mata-pelajaran.show', [$item, 'tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-muted">Lihat</a>
                        @izin('mata_pelajaran.kelola')
                            <a href="{{ route('mata-pelajaran.edit', [$item, 'tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada mata pelajaran.</div>
            @endforelse
        </div>
    </section>

    @if ($mataPelajaran->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $mataPelajaran->currentPage() }} dari {{ $mataPelajaran->lastPage() }}</div>
            <div class="actions">
                @if ($mataPelajaran->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $mataPelajaran->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif
                @if ($mataPelajaran->hasMorePages())
                    <a href="{{ $mataPelajaran->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
