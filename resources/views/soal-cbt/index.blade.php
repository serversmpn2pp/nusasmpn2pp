@extends('layouts.app')

@section('title', 'Bank Soal CBT - NUSA')

@section('content')
    <style>
        .soal-filter-grid {
            display: grid;
            grid-template-columns: minmax(210px, 1fr) minmax(180px, .85fr) 120px minmax(170px, .85fr) 140px auto;
            gap: 12px;
            align-items: end;
        }

        @media (max-width: 1180px) {
            .soal-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .soal-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Bank soal CBT</h1>
        </div>

        @if ($bisaKelolaSoal)
            <a href="{{ route('soal-cbt.create') }}" class="button button-primary">Tambah soal</a>
        @endif
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total soal</p>
            <p class="stat-value">{{ $jumlahSoal }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Siap digunakan</p>
            <p class="stat-value">{{ $jumlahSiap }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Draft</p>
            <p class="stat-value">{{ $jumlahDraft }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('soal-cbt.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="soal-filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari soal</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Kode, topik, materi, atau pertanyaan" class="input">
            </div>

            <div class="field">
                <label for="mata_pelajaran_id">Mata pelajaran</label>
                <select id="mata_pelajaran_id" name="mata_pelajaran_id" class="select">
                    <option value="">Semua mapel</option>
                    @foreach ($daftarMataPelajaran as $item)
                        <option value="{{ $item->id }}" @selected((string) $mataPelajaranId === (string) $item->id)>{{ $item->nama }}{{ $item->tingkat ? ' - kelas ' . $item->tingkat : '' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="tingkat">Tingkat</label>
                <select id="tingkat" name="tingkat" class="select">
                    <option value="semua" @selected($tingkat === 'semua')>Semua</option>
                    @foreach ([7, 8, 9] as $item)
                        <option value="{{ $item }}" @selected((string) $tingkat === (string) $item)>Kelas {{ $item }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="jenis_soal">Jenis soal</label>
                <select id="jenis_soal" name="jenis_soal" class="select">
                    <option value="semua" @selected($jenisSoal === 'semua')>Semua</option>
                    @foreach ($daftarJenisSoal as $kode => $label)
                        <option value="{{ $kode }}" @selected($jenisSoal === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="semua">Semua</option>
                    @foreach ($daftarStatus as $kode => $label)
                        <option value="{{ $kode }}" @selected($status === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('soal-cbt.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Soal</th>
                        <th>Mapel</th>
                        <th>Jenis</th>
                        <th>Kategori</th>
                        <th>Skor</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($soalCbt as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->kode }}</p>
                                <p class="person-meta">{{ str(strip_tags($item->pertanyaan))->limit(90) }}</p>
                            </td>
                            <td>
                                <p>{{ $item->mataPelajaran?->nama ?: '-' }}</p>
                                <p class="person-meta">Kelas {{ $item->tingkat }}</p>
                            </td>
                            <td>{{ $item->labelJenis() }}</td>
                            <td>
                                <p>{{ $item->labelKategori() }}</p>
                                <p class="person-meta">{{ $item->labelKesulitan() }}</p>
                            </td>
                            <td>{{ $item->skor_maksimal }}</td>
                            <td>
                                <span class="badge {{ $item->status === 'siap' ? 'badge-active' : ($item->status === 'arsip' ? 'badge-inactive' : 'badge-warning') }}">{{ $item->labelStatus() }}</span>
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('soal-cbt.show', $item) }}" class="button button-muted">Lihat</a>
                                    @if ($bisaKelolaSoal)
                                        <a href="{{ route('soal-cbt.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada soal CBT.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($soalCbt as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->kode }}</p>
                            <p class="person-meta">{{ $item->labelJenis() }}</p>
                        </div>
                        <span class="badge {{ $item->status === 'siap' ? 'badge-active' : ($item->status === 'arsip' ? 'badge-inactive' : 'badge-warning') }}">{{ $item->labelStatus() }}</span>
                    </div>
                    <p style="margin: 10px 0 0;">{{ str(strip_tags($item->pertanyaan))->limit(120) }}</p>
                    <dl class="quick-facts">
                        <div><dt>Mapel</dt><dd>{{ $item->mataPelajaran?->nama ?: '-' }}</dd></div>
                        <div><dt>Tingkat</dt><dd>Kelas {{ $item->tingkat }}</dd></div>
                        <div><dt>Kategori</dt><dd>{{ $item->labelKategori() }}</dd></div>
                        <div><dt>Skor</dt><dd>{{ $item->skor_maksimal }}</dd></div>
                    </dl>
                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('soal-cbt.show', $item) }}" class="button button-muted">Lihat</a>
                        @if ($bisaKelolaSoal)
                            <a href="{{ route('soal-cbt.edit', $item) }}" class="button button-dark">Edit</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada soal CBT.</div>
            @endforelse
        </div>
    </section>

    @if ($soalCbt->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $soalCbt->currentPage() }} dari {{ $soalCbt->lastPage() }}</div>
            <div class="actions">
                @if ($soalCbt->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $soalCbt->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($soalCbt->hasMorePages())
                    <a href="{{ $soalCbt->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
