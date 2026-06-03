@extends('layouts.app')

@section('title', 'Paket CBT - NUSA')

@section('content')
    <style>
        .cbt-filter-grid {
            display: grid;
            grid-template-columns: minmax(210px, 1fr) minmax(170px, .75fr) minmax(170px, .75fr) 130px 150px auto;
            gap: 12px;
            align-items: end;
        }

        @media (max-width: 1180px) {
            .cbt-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .cbt-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Paket ujian CBT</h1>
        </div>

        @izin('cbt.kelola')
            <a href="{{ route('ujian-cbt.create') }}" class="button button-primary">Tambah paket CBT</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total paket</p>
            <p class="stat-value">{{ $jumlahUjian }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Draft</p>
            <p class="stat-value">{{ $jumlahDraft }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Terjadwal</p>
            <p class="stat-value">{{ $jumlahTerjadwal }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('ujian-cbt.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="cbt-filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari paket</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Nama, kode, mapel, atau jenis" class="input">
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
                <label for="jenis_ujian_cbt_id">Jenis ujian</label>
                <select id="jenis_ujian_cbt_id" name="jenis_ujian_cbt_id" class="select">
                    <option value="">Semua jenis</option>
                    @foreach ($daftarJenisUjianCbt as $item)
                        <option value="{{ $item->id }}" @selected((string) $jenisUjianCbtId === (string) $item->id)>{{ $item->nama }}</option>
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
                <a href="{{ route('ujian-cbt.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Paket CBT</th>
                        <th>Jenis</th>
                        <th>Mata pelajaran</th>
                        <th>Jadwal</th>
                        <th>Soal</th>
                        <th>Kelas</th>
                        <th>Peserta</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ujianCbt as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->kode }} - kelas {{ $item->tingkat }} - {{ $item->jumlah_soal }} soal</p>
                            </td>
                            <td>{{ $item->jenisUjianCbt?->nama ?: '-' }}</td>
                            <td>{{ $item->mataPelajaran?->nama ?: '-' }}</td>
                            <td>
                                <p>{{ $item->tahunPelajaran?->nama ?: '-' }}</p>
                                <p class="person-meta">{{ $item->tanggal_mulai ? $item->tanggal_mulai->format('d-m-Y H:i') : 'Belum dijadwalkan' }}</p>
                            </td>
                            <td>{{ $item->soal_ujian_cbt_count }} / {{ $item->jumlah_soal }}</td>
                            <td>{{ $item->kelas_ujian_cbt_count }}</td>
                            <td>{{ $item->peserta_ujian_cbt_count }}</td>
                            <td>
                                <span class="badge {{ $item->status === 'terjadwal' || $item->status === 'berlangsung' ? 'badge-active' : ($item->status === 'nonaktif' ? 'badge-inactive' : 'badge-warning') }}">{{ $item->labelStatus() }}</span>
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('ujian-cbt.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('cbt.kelola')
                                        <a href="{{ route('ujian-cbt.soal.edit', $item) }}" class="button button-muted">Soal</a>
                                        <a href="{{ route('ujian-cbt.peserta.index', $item) }}" class="button button-muted">Peserta</a>
                                        <a href="{{ route('ujian-cbt.kartu-peserta.index', $item) }}" class="button button-muted">Kartu</a>
                                        <a href="{{ route('ujian-cbt.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="empty-state">Belum ada paket ujian CBT.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($ujianCbt as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama }}</p>
                            <p class="person-meta">{{ $item->kode }}</p>
                        </div>
                        <span class="badge {{ $item->status === 'terjadwal' || $item->status === 'berlangsung' ? 'badge-active' : ($item->status === 'nonaktif' ? 'badge-inactive' : 'badge-warning') }}">{{ $item->labelStatus() }}</span>
                    </div>
                    <dl class="quick-facts">
                        <div><dt>Jenis</dt><dd>{{ $item->jenisUjianCbt?->nama ?: '-' }}</dd></div>
                        <div><dt>Mapel</dt><dd>{{ $item->mataPelajaran?->nama ?: '-' }}</dd></div>
                        <div><dt>Periode</dt><dd>Kelas {{ $item->tingkat }} - {{ ucfirst($item->semester) }}</dd></div>
                        <div><dt>Soal paket</dt><dd>{{ $item->soal_ujian_cbt_count }} / {{ $item->jumlah_soal }}</dd></div>
                        <div><dt>Kelas peserta</dt><dd>{{ $item->kelas_ujian_cbt_count }}</dd></div>
                        <div><dt>Peserta CBT</dt><dd>{{ $item->peserta_ujian_cbt_count }}</dd></div>
                    </dl>
                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('ujian-cbt.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('cbt.kelola')
                            <a href="{{ route('ujian-cbt.soal.edit', $item) }}" class="button button-muted">Soal</a>
                            <a href="{{ route('ujian-cbt.peserta.index', $item) }}" class="button button-muted">Peserta</a>
                            <a href="{{ route('ujian-cbt.kartu-peserta.index', $item) }}" class="button button-muted">Kartu</a>
                            <a href="{{ route('ujian-cbt.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada paket ujian CBT.</div>
            @endforelse
        </div>
    </section>

    @if ($ujianCbt->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $ujianCbt->currentPage() }} dari {{ $ujianCbt->lastPage() }}</div>
            <div class="actions">
                @if ($ujianCbt->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $ujianCbt->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($ujianCbt->hasMorePages())
                    <a href="{{ $ujianCbt->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
