@extends('layouts.app')

@section('title', 'Detail Kelengkapan Perangkat Ajar - NUSA')

@section('content')
    <style>
        .teacher-document-filter {
            display: grid;
            grid-template-columns: minmax(200px, 1fr) 180px auto;
            gap: 12px;
            align-items: end;
        }

        .teacher-document-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .teacher-document-panel + .teacher-document-panel {
            margin-top: 16px;
        }

        .teacher-document-head {
            border-bottom: 1px solid var(--line);
            padding: 16px;
        }

        .teacher-document-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            padding: 16px;
        }

        .teacher-document-item {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 14px;
        }

        .teacher-document-item-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        @media (max-width: 900px) {
            .teacher-document-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .teacher-document-filter,
            .teacher-document-summary,
            .teacher-document-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kurikulum</p>
            <h1 class="page-title">Kelengkapan perangkat ajar</h1>
            <p class="person-meta">{{ $pegawai->nama_lengkap }}{{ $pegawai->nip ? ' - ' . $pegawai->nip : '' }}</p>
        </div>

        <a href="{{ route('pemeriksaan-perangkat-ajar.index', ['tahun_pelajaran_id' => $tahunPelajaranId, 'semester' => $semester]) }}" class="button button-muted">Kembali</a>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('pemeriksaan-perangkat-ajar.show', $pegawai) }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="teacher-document-filter">
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

    <section class="teacher-document-summary" aria-label="Ringkasan kelengkapan guru">
        <article class="panel stat">
            <p class="stat-label">Dokumen wajib</p>
            <p class="stat-value">{{ $jumlahTerunggahWajib }}/{{ $jumlahWajib }}</p>
        </article>
        <article class="panel stat inactive">
            <p class="stat-label">Menunggu pemeriksaan</p>
            <p class="stat-value">{{ $jumlahMenunggu }}</p>
        </article>
        <article class="panel stat">
            <p class="stat-label">Perlu perbaikan</p>
            <p class="stat-value">{{ $jumlahPerluPerbaikan }}</p>
        </article>
        <article class="panel stat active">
            <p class="stat-label">Sudah diperiksa</p>
            <p class="stat-value">{{ $jumlahSudahDiperiksa }}</p>
        </article>
    </section>

    @if ($perangkatTanpaTingkat->isNotEmpty())
        <div class="alert alert-danger">
            Ada {{ $perangkatTanpaTingkat->count() }} dokumen lama yang belum memiliki tingkat. Dokumen tersebut belum dihitung sebagai kelengkapan dan perlu diperbarui oleh guru pemiliknya.
        </div>
    @endif

    @foreach ($penugasanPerTingkat as $penugasan)
        @php
            $mapel = $penugasan['mata_pelajaran'];
            $tingkat = $penugasan['tingkat'];
            $labelTingkat = $penugasan['label_tingkat'];
        @endphp
        <section class="panel teacher-document-panel">
            <header class="teacher-document-head">
                <p class="eyebrow">Tingkat {{ $labelTingkat }}</p>
                <h2 class="panel-title">{{ $mapel->nama }}</h2>
            </header>

            <div class="teacher-document-grid">
                @foreach ($jenisPerangkatAjar as $jenis)
                    @php
                        $dokumen = $perangkatAjar->get($mapel->id . '-' . $tingkat . '-' . $jenis->id);
                    @endphp
                    <article class="teacher-document-item">
                        <div>
                            <div class="teacher-document-item-head">
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
                                <p class="help-text">Diunggah {{ $dokumen->diunggah_pada?->format('d M Y H:i') ?? '-' }}</p>
                                @if ($dokumen->catatan_pemeriksa)
                                    <p class="help-text" style="margin-top: 8px;">Catatan: {{ $dokumen->catatan_pemeriksa }}</p>
                                @endif
                            @endif
                        </div>

                        @if ($dokumen)
                            <div class="actions">
                                <a href="{{ route('perangkat-ajar-saya.download', $dokumen) }}" class="button button-muted button-sm">Unduh PDF</a>
                                <a href="{{ route('perangkat-ajar-saya.show', $dokumen) }}" class="button button-muted button-sm">Riwayat</a>
                                <a href="{{ route('pemeriksaan-perangkat-ajar.preview', $dokumen) }}" target="_blank" rel="noopener" class="button button-muted button-sm">Pratinjau</a>
                                @izin('perangkat_ajar.periksa')
                                    <a href="{{ route('pemeriksaan-perangkat-ajar.edit', $dokumen) }}" class="button button-dark button-sm">Periksa</a>
                                @endizin
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection
