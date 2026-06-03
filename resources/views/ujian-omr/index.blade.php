@extends('layouts.app')

@section('title', 'Ujian dan LJK - NUSA')

@section('content')
    <style>
        .omr-filter-grid {
            display: grid;
            grid-template-columns: minmax(210px, 1fr) minmax(180px, .8fr) 130px 160px auto;
            gap: 12px;
            align-items: end;
        }

        @media (max-width: 1080px) {
            .omr-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .omr-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian OMR</p>
            <h1 class="page-title">Ujian dan lembar jawab</h1>
        </div>

        @izin('omr.kelola')
            <a href="{{ route('ujian-omr.create') }}" class="button button-primary">Tambah ujian</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total ujian</p>
            <p class="stat-value">{{ $jumlahUjian }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Draft</p>
            <p class="stat-value">{{ $jumlahDraft }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Siap digunakan</p>
            <p class="stat-value">{{ $jumlahSiap }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('ujian-omr.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="omr-filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari ujian</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Nama, kode, atau mata pelajaran" class="input">
            </div>

            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    <option value="">Semua tahun</option>
                    @foreach ($daftarTahunPelajaran as $item)
                        <option value="{{ $item->id }}" @selected((string) $tahunPelajaranId === (string) $item->id)>{{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}</option>
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
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="semua">Semua</option>
                    @foreach ($daftarStatus as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('ujian-omr.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Ujian</th>
                        <th>Mata pelajaran</th>
                        <th>Periode</th>
                        <th>Kelas</th>
                        <th>Versi</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ujianOmr as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->kode }} - {{ $item->jumlah_soal }} soal A-D</p>
                            </td>
                            <td>{{ $item->mataPelajaran?->nama ?: '-' }}</td>
                            <td>
                                <p>{{ $item->tahunPelajaran?->nama ?: '-' }}</p>
                                <p class="person-meta">{{ ucfirst($item->semester) }}{{ $item->tanggal_ujian ? ' - ' . $item->tanggal_ujian->format('d-m-Y') : '' }}</p>
                            </td>
                            <td>{{ $item->kelas_ujian_omr_count }}</td>
                            <td>{{ $item->versi_soal_count }}</td>
                            <td>
                                <span class="badge {{ $item->status === 'siap' ? 'badge-active' : ($item->status === 'nonaktif' ? 'badge-inactive' : 'badge-warning') }}">{{ $item->labelStatus() }}</span>
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('ujian-omr.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('omr.kelola')
                                        <a href="{{ route('ujian-omr.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada ujian OMR.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($ujianOmr as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama }}</p>
                            <p class="person-meta">{{ $item->kode }}</p>
                        </div>
                        <span class="badge {{ $item->status === 'siap' ? 'badge-active' : ($item->status === 'nonaktif' ? 'badge-inactive' : 'badge-warning') }}">{{ $item->labelStatus() }}</span>
                    </div>
                    <dl class="quick-facts">
                        <div><dt>Mapel</dt><dd>{{ $item->mataPelajaran?->nama ?: '-' }}</dd></div>
                        <div><dt>Periode</dt><dd>{{ ucfirst($item->semester) }} - {{ $item->tahunPelajaran?->nama ?: '-' }}</dd></div>
                        <div><dt>Kelas peserta</dt><dd>{{ $item->kelas_ujian_omr_count }}</dd></div>
                        <div><dt>Versi soal</dt><dd>{{ $item->versi_soal_count }}</dd></div>
                    </dl>
                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('ujian-omr.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('omr.kelola')
                            <a href="{{ route('ujian-omr.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada ujian OMR.</div>
            @endforelse
        </div>
    </section>

    @if ($ujianOmr->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $ujianOmr->currentPage() }} dari {{ $ujianOmr->lastPage() }}</div>
            <div class="actions">
                @if ($ujianOmr->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $ujianOmr->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($ujianOmr->hasMorePages())
                    <a href="{{ $ujianOmr->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
