@extends('layouts.app')

@section('title', 'Pemeriksaan Perangkat Ajar - NUSA')

@section('content')
    <style>
        .curriculum-monitor-summary {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .curriculum-monitor-filter {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) 170px 170px 190px minmax(180px, 1fr);
            gap: 12px;
            align-items: end;
        }

        .curriculum-monitor-actions {
            grid-column: 1 / -1;
            justify-content: flex-end;
        }

        .curriculum-progress {
            overflow: hidden;
            width: 160px;
            height: 8px;
            margin-top: 7px;
            border-radius: 999px;
            background: #e4e4e7;
        }

        .curriculum-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: var(--primary);
        }

        .curriculum-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }

        @media (max-width: 1100px) {
            .curriculum-monitor-summary {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .curriculum-monitor-filter {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .curriculum-monitor-summary,
            .curriculum-monitor-filter {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kurikulum</p>
            <h1 class="page-title">Pemeriksaan perangkat ajar</h1>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <section class="curriculum-monitor-summary" aria-label="Ringkasan perangkat ajar">
        <article class="panel stat">
            <p class="stat-label">Guru mapel</p>
            <p class="stat-value">{{ $ringkasan['jumlah_guru'] }}</p>
        </article>
        <article class="panel stat active">
            <p class="stat-label">Sudah lengkap</p>
            <p class="stat-value">{{ $ringkasan['lengkap'] }}</p>
        </article>
        <article class="panel stat inactive">
            <p class="stat-label">Belum lengkap</p>
            <p class="stat-value">{{ $ringkasan['belum_lengkap'] }}</p>
        </article>
        <article class="panel stat">
            <p class="stat-label">Menunggu pemeriksaan</p>
            <p class="stat-value">{{ $ringkasan['menunggu_pemeriksaan'] }}</p>
        </article>
        <article class="panel stat">
            <p class="stat-label">Perlu perbaikan</p>
            <p class="stat-value">{{ $ringkasan['perlu_perbaikan'] }}</p>
        </article>
    </section>

    <form action="{{ route('pemeriksaan-perangkat-ajar.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="curriculum-monitor-filter">
            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
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
                    <option value="1" @selected($semester === 1)>Semester 1</option>
                    <option value="2" @selected($semester === 2)>Semester 2</option>
                </select>
            </div>

            <div class="field">
                <label for="kelengkapan">Kelengkapan</label>
                <select id="kelengkapan" name="kelengkapan" class="select">
                    <option value="semua" @selected($kelengkapan === 'semua')>Semua</option>
                    <option value="lengkap" @selected($kelengkapan === 'lengkap')>Sudah lengkap</option>
                    <option value="belum_lengkap" @selected($kelengkapan === 'belum_lengkap')>Belum lengkap</option>
                </select>
            </div>

            <div class="field">
                <label for="status_dokumen">Status dokumen</label>
                <select id="status_dokumen" name="status_dokumen" class="select">
                    <option value="semua" @selected($statusDokumen === 'semua')>Semua</option>
                    <option value="belum_diunggah" @selected($statusDokumen === 'belum_diunggah')>Belum diunggah</option>
                    <option value="menunggu_pemeriksaan" @selected($statusDokumen === 'menunggu_pemeriksaan')>Menunggu pemeriksaan</option>
                    <option value="perlu_perbaikan" @selected($statusDokumen === 'perlu_perbaikan')>Perlu perbaikan</option>
                    <option value="sudah_diperiksa" @selected($statusDokumen === 'sudah_diperiksa')>Sudah diperiksa</option>
                </select>
            </div>

            <div class="field">
                <label for="kata_kunci">Cari guru atau mapel</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Nama, NIP, atau mata pelajaran" class="input">
            </div>

            <div class="actions curriculum-monitor-actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('pemeriksaan-perangkat-ajar.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width: 1040px;">
                <thead>
                    <tr>
                        <th>Guru</th>
                        <th>Mata pelajaran</th>
                        <th>Kelengkapan wajib</th>
                        <th>Menunggu</th>
                        <th>Perlu perbaikan</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($monitoringGuru as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item['pegawai']->nama_lengkap }}</p>
                                <p class="person-meta">{{ $item['pegawai']->nip ?: 'NIP belum diisi' }}</p>
                            </td>
                            <td>
                                <div class="curriculum-chip-row">
                                    @foreach ($item['penugasan_per_tingkat'] as $penugasan)
                                        <span class="badge badge-muted">{{ $penugasan['mata_pelajaran']->nama }} · {{ $penugasan['label_tingkat'] }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                <p class="person-name">{{ $item['jumlah_terunggah_wajib'] }}/{{ $item['jumlah_wajib'] }} dokumen</p>
                                <div class="curriculum-progress" aria-label="Kelengkapan {{ $item['persentase'] }} persen">
                                    <span style="width: {{ $item['persentase'] }}%"></span>
                                </div>
                                <p class="person-meta">{{ $item['persentase'] }}%</p>
                            </td>
                            <td>{{ $item['jumlah_menunggu'] }}</td>
                            <td>{{ $item['jumlah_perlu_perbaikan'] }}</td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('pemeriksaan-perangkat-ajar.show', ['pegawai' => $item['pegawai'], 'tahun_pelajaran_id' => $tahunPelajaranId, 'semester' => $semester]) }}" class="button button-dark">Lihat dokumen</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada guru mapel yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($monitoringGuru as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item['pegawai']->nama_lengkap }}</p>
                            <p class="person-meta">{{ $item['pegawai']->nip ?: 'NIP belum diisi' }}</p>
                        </div>
                        <span class="badge {{ $item['lengkap'] ? 'badge-active' : 'badge-inactive' }}">
                            {{ $item['lengkap'] ? 'Lengkap' : $item['persentase'] . '%' }}
                        </span>
                    </div>

                    <div class="curriculum-chip-row">
                        @foreach ($item['penugasan_per_tingkat'] as $penugasan)
                            <span class="badge badge-muted">{{ $penugasan['mata_pelajaran']->nama }} · {{ $penugasan['label_tingkat'] }}</span>
                        @endforeach
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Dokumen wajib</dt>
                            <dd>{{ $item['jumlah_terunggah_wajib'] }}/{{ $item['jumlah_wajib'] }}</dd>
                        </div>
                        <div>
                            <dt>Menunggu</dt>
                            <dd>{{ $item['jumlah_menunggu'] }}</dd>
                        </div>
                        <div>
                            <dt>Perlu perbaikan</dt>
                            <dd>{{ $item['jumlah_perlu_perbaikan'] }}</dd>
                        </div>
                        <div>
                            <dt>Sudah diperiksa</dt>
                            <dd>{{ $item['jumlah_sudah_diperiksa'] }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('pemeriksaan-perangkat-ajar.show', ['pegawai' => $item['pegawai'], 'tahun_pelajaran_id' => $tahunPelajaranId, 'semester' => $semester]) }}" class="button button-dark">Lihat dokumen</a>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada guru mapel yang sesuai dengan filter.</div>
            @endforelse
        </div>
    </section>

    @if ($monitoringGuru->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $monitoringGuru->currentPage() }} dari {{ $monitoringGuru->lastPage() }}</div>
            <div class="actions">
                @if ($monitoringGuru->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $monitoringGuru->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($monitoringGuru->hasMorePages())
                    <a href="{{ $monitoringGuru->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
