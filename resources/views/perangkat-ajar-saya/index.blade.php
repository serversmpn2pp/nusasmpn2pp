@extends('layouts.app')

@section('title', 'Perangkat Ajar Saya - NUSA')

@section('content')
    <style>
        .teaching-document-filter {
            display: grid;
            grid-template-columns: minmax(200px, 1fr) 180px auto;
            gap: 12px;
            align-items: end;
        }

        .teaching-document-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .teaching-document-progress {
            overflow: hidden;
            height: 8px;
            margin-top: 12px;
            border-radius: 999px;
            background: #e4e4e7;
        }

        .teaching-document-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: var(--primary);
        }

        .subject-document-panel + .subject-document-panel {
            margin-top: 16px;
        }

        .subject-document-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            border-bottom: 1px solid var(--line);
            padding: 16px;
        }

        .subject-document-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            padding: 16px;
        }

        .document-item {
            display: flex;
            min-width: 0;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 14px;
        }

        .document-item-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        @media (max-width: 900px) {
            .teaching-document-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .teaching-document-filter,
            .teaching-document-summary,
            .subject-document-grid {
                grid-template-columns: 1fr;
            }

            .subject-document-head {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kurikulum</p>
            <h1 class="page-title">Perangkat ajar saya</h1>
        </div>

        @if ($pegawai && $tahunPelajaranId)
            <a href="{{ route('perangkat-ajar-saya.create', ['tahun_pelajaran_id' => $tahunPelajaranId, 'semester' => $semester]) }}" class="button button-primary">Unggah PDF</a>
        @endif
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @unless ($pegawai)
        <div class="alert alert-danger">Akun ini belum terhubung dengan data pegawai. Hubungi administrator agar perangkat ajar dapat diunggah.</div>
    @endunless

    <form action="{{ route('perangkat-ajar-saya.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="teaching-document-filter">
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

            <button type="submit" class="button button-dark">Tampilkan</button>
        </div>
    </form>

    @php
        $persentase = $jumlahWajib > 0 ? min(100, round($jumlahTerunggah / $jumlahWajib * 100)) : 0;
    @endphp

    <div class="teaching-document-summary">
        <article class="panel stat">
            <p class="stat-label">Dokumen wajib</p>
            <p class="stat-value">{{ $jumlahTerunggah }}/{{ $jumlahWajib }}</p>
            <div class="teaching-document-progress" aria-label="Progres kelengkapan {{ $persentase }} persen">
                <span style="width: {{ $persentase }}%"></span>
            </div>
        </article>
        <article class="panel stat active">
            <p class="stat-label">Kelengkapan</p>
            <p class="stat-value">{{ $persentase }}%</p>
        </article>
        <article class="panel stat inactive">
            <p class="stat-label">Menunggu pemeriksaan</p>
            <p class="stat-value">{{ $jumlahMenunggu }}</p>
        </article>
        <article class="panel stat">
            <p class="stat-label">Perlu perbaikan</p>
            <p class="stat-value">{{ $jumlahPerluPerbaikan }}</p>
        </article>
    </div>

    @if ($mataPelajaran->isEmpty())
        <section class="panel empty-state">
            Belum ada penugasan guru mata pelajaran aktif untuk tahun pelajaran ini.
        </section>
    @elseif ($jenisPerangkatAjar->isEmpty())
        <section class="panel empty-state">
            Jenis perangkat ajar belum diatur oleh Wakil Kurikulum.
        </section>
    @else
        @foreach ($mataPelajaran as $mapel)
            <section class="panel subject-document-panel">
                <header class="subject-document-head">
                    <div>
                        <p class="eyebrow">{{ $mapel->kode ?: 'Mata pelajaran' }}</p>
                        <h2 class="panel-title">{{ $mapel->nama }}</h2>
                    </div>

                    <a href="{{ route('perangkat-ajar-saya.create', ['tahun_pelajaran_id' => $tahunPelajaranId, 'semester' => $semester, 'mata_pelajaran_id' => $mapel->id]) }}" class="button button-muted button-sm">Unggah dokumen</a>
                </header>

                <div class="subject-document-grid">
                    @foreach ($jenisPerangkatAjar as $jenis)
                        @php
                            $dokumen = $perangkatAjar->get($mapel->id . '-' . $jenis->id);
                        @endphp
                        <article class="document-item">
                            <div>
                                <div class="document-item-head">
                                    <div>
                                        <p class="person-name">{{ $jenis->nama }}</p>
                                        <p class="person-meta">{{ $jenis->wajib ? 'Dokumen wajib' : 'Dokumen opsional' }}</p>
                                    </div>

                                    @if ($dokumen)
                                        <span class="badge {{ $dokumen->kelasBadgeStatus() }}">{{ $dokumen->labelStatus() }}</span>
                                    @else
                                        <span class="badge badge-muted">Belum diunggah</span>
                                    @endif
                                </div>

                                @if ($dokumen)
                                    <p class="help-text" style="margin-top: 12px;">{{ $dokumen->nama_file_asli }}</p>
                                    <p class="help-text">Diunggah {{ $dokumen->diunggah_pada?->format('d M Y H:i') }}</p>
                                @elseif ($jenis->deskripsi)
                                    <p class="help-text" style="margin-top: 12px;">{{ $jenis->deskripsi }}</p>
                                @endif
                            </div>

                            <div class="actions">
                                @if ($dokumen)
                                    <a href="{{ route('perangkat-ajar-saya.show', $dokumen) }}" class="button button-muted button-sm">Lihat</a>
                                    <a href="{{ route('perangkat-ajar-saya.edit', $dokumen) }}" class="button button-dark button-sm">Revisi</a>
                                @else
                                    <a href="{{ route('perangkat-ajar-saya.create', ['tahun_pelajaran_id' => $tahunPelajaranId, 'semester' => $semester, 'mata_pelajaran_id' => $mapel->id, 'jenis_perangkat_ajar_id' => $jenis->id]) }}" class="button button-primary button-sm">Unggah PDF</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
@endsection
