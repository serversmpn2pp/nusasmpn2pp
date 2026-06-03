@extends('layouts.app')

@section('title', 'Hasil Scan LJK - NUSA')

@section('content')
    <style>
        .omr-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .omr-summary {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 14px;
        }

        .omr-summary strong {
            display: block;
            color: var(--primary);
            font-size: 1.5rem;
        }

        .omr-result-card {
            display: grid;
            grid-template-columns: 120px minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            border-top: 1px solid var(--line);
            padding: 14px 0;
        }

        .omr-preview {
            display: block;
            width: 104px;
            height: 146px;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #eef3f8;
        }

        .omr-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .omr-score {
            color: var(--primary);
            font-size: 1.6rem;
            font-weight: 950;
            text-align: right;
        }

        .omr-result-action {
            display: grid;
            justify-items: end;
            gap: 8px;
        }

        @media (max-width: 760px) {
            .omr-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .omr-result-card {
                grid-template-columns: 88px minmax(0, 1fr);
            }

            .omr-preview {
                width: 78px;
                height: 110px;
            }

            .omr-score {
                text-align: left;
            }

            .omr-result-action {
                grid-column: 2;
                justify-items: start;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian OMR</p>
            <h1 class="page-title">Hasil pembacaan LJK</h1>
            <p class="page-subtitle">{{ $batchScan->nama_file_asli }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('ujian-omr.scan.index', $ujianOmr) }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if (session('gagal'))
        <div class="alert alert-danger">{{ session('gagal') }}</div>
    @endif

    <section class="panel panel-pad">
        <div class="omr-summary-grid">
            <div class="omr-summary"><strong>{{ $batchScan->jumlah_halaman_pdf }}</strong><span class="help-text">Halaman PDF</span></div>
            <div class="omr-summary"><strong>{{ $batchScan->jumlah_ljk_terdeteksi }}</strong><span class="help-text">LJK terdeteksi</span></div>
            <div class="omr-summary"><strong>{{ $batchScan->jumlah_berhasil }}</strong><span class="help-text">Terbaca bersih</span></div>
            <div class="omr-summary"><strong>{{ $batchScan->jumlah_perlu_diperiksa }}</strong><span class="help-text">Perlu diperiksa</span></div>
        </div>
    </section>

    <section class="panel panel-pad" style="margin-top: 20px;">
        <div class="page-header" style="margin-bottom: 0;">
            <div>
                <p class="eyebrow">Integrasi Nilai Siswa</p>
                <h2 class="panel-title">{{ $jumlahDapatDiterapkan }} nilai siap diterapkan</h2>
                <p class="help-text">
                    Hanya LJK yang terbaca bersih dan diarahkan ke komponen STS atau SAS/SAJ yang akan masuk ke nilai siswa.
                    {{ $jumlahDiterapkan }} nilai dari batch ini sudah diterapkan.
                </p>
            </div>
            @if ($jumlahDapatDiterapkan)
                <form action="{{ route('ujian-omr.scan.terapkan-nilai', [$ujianOmr, $batchScan]) }}" method="POST" onsubmit="return confirm('Terapkan nilai OMR yang terbaca bersih ke nilai siswa?')">
                    @csrf
                    <button type="submit" class="button button-primary">Terapkan Nilai</button>
                </form>
            @endif
        </div>
    </section>

    <section class="panel panel-pad" style="margin-top: 20px;">
        <h2 class="panel-title">Daftar LJK dalam batch</h2>
        @forelse ($batchScan->hasilScan->sortBy(fn ($hasil) => sprintf('%05d|%03d', $hasil->halaman_pdf, $hasil->urutan_ljk)) as $hasil)
            @php
                $lembar = $hasil->lembarJawabUjianOmr;
                $anggota = $lembar?->anggotaKelas;
                $siswa = $anggota?->siswa;
            @endphp
            <article class="omr-result-card">
                <a href="{{ route('ujian-omr.scan.pratinjau', [$ujianOmr, $batchScan, $hasil]) }}" target="_blank" rel="noopener" class="omr-preview">
                    <img src="{{ route('ujian-omr.scan.pratinjau', [$ujianOmr, $batchScan, $hasil]) }}" alt="Pratinjau LJK halaman {{ $hasil->halaman_pdf }}">
                </a>
                <div>
                    <div class="actions" style="margin-bottom: 6px;">
                        <span class="badge {{ $hasil->status === 'terbaca' ? 'badge-active' : 'badge-warning' }}">{{ ucfirst(str_replace('_', ' ', $hasil->status)) }}</span>
                        @if ($hasil->diterapkan_pada)
                            <span class="badge badge-active">Nilai diterapkan</span>
                        @endif
                        @if ($hasil->dikoreksi_pada)
                            <span class="badge badge-muted">Dikoreksi manual</span>
                        @endif
                        <span class="person-meta">Halaman {{ $hasil->halaman_pdf }} / posisi {{ $hasil->urutan_ljk }}</span>
                    </div>
                    <h3 class="person-name">{{ $siswa?->nama_lengkap ?: 'Token siswa belum dikenali' }}</h3>
                    <p class="person-meta">
                        {{ $anggota?->kelas?->nama ?: '-' }}
                        · No. {{ $anggota?->nomor_absen ?: '-' }}
                        · Versi {{ $lembar?->versiSoalUjianOmr?->kode ?: '-' }}
                    </p>
                    <p class="help-text" style="margin-top: 7px;">
                        Benar {{ $hasil->jumlah_benar }} · Salah {{ $hasil->jumlah_salah }} · Kosong {{ $hasil->jumlah_kosong }} · Ganda {{ $hasil->jumlah_ganda }}
                    </p>
                    @if ($hasil->catatan)
                        <p class="help-text" style="margin-top: 6px; color: #9a6700;">{{ $hasil->catatan }}</p>
                    @endif
                    @if ($hasil->catatan_koreksi)
                        <p class="help-text" style="margin-top: 6px;">Koreksi: {{ $hasil->catatan_koreksi }}</p>
                    @endif
                </div>
                <div class="omr-result-action">
                    <div class="omr-score">{{ $hasil->nilai !== null ? number_format((float) $hasil->nilai, 2, ',', '.') : '-' }}</div>
                    @if (! $hasil->diterapkan_pada)
                        <a href="{{ route('ujian-omr.scan.hasil.periksa', [$ujianOmr, $batchScan, $hasil]) }}" class="button {{ $hasil->status === 'terbaca' ? 'button-muted' : 'button-primary' }} button-sm">
                            {{ $hasil->status === 'terbaca' ? 'Tinjau' : 'Periksa' }}
                        </a>
                    @endif
                </div>
            </article>
        @empty
            <p class="help-text" style="margin-top: 10px;">Belum ada LJK yang terdeteksi dalam batch ini.</p>
        @endforelse
    </section>
@endsection
