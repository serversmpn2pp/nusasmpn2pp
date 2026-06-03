@extends('layouts.app')

@section('title', 'Proses PDF LJK - NUSA')

@section('content')
    <style>
        .omr-upload-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, .72fr);
            gap: 18px;
        }

        .omr-upload-box {
            border: 1px dashed #9fb2c7;
            border-radius: 8px;
            background: #f8fbff;
            padding: 18px;
        }

        .omr-stat-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .omr-stat {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px;
        }

        .omr-stat strong {
            display: block;
            color: var(--primary);
            font-size: 1.35rem;
        }

        @media (max-width: 860px) {
            .omr-upload-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian OMR</p>
            <h1 class="page-title">Proses PDF hasil scan</h1>
            <p class="page-subtitle">{{ $ujianOmr->nama }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('ujian-omr.show', $ujianOmr) }}" class="button button-muted">Kembali ke ujian</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">
            <strong>PDF belum dapat diproses.</strong>
            <ul style="margin: 8px 0 0 18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="omr-upload-grid">
        <section class="panel panel-pad">
            <h2 class="panel-title">Unggah hasil scan LJK</h2>
            <p class="help-text">PDF dapat berisi satu kelas atau beberapa kelas sekaligus. NUSA akan membaca QR token, versi soal, dan bulatan jawaban setiap siswa.</p>

            <form action="{{ route('ujian-omr.scan.store', $ujianOmr) }}" method="POST" enctype="multipart/form-data" class="omr-upload-box" style="margin-top: 16px;">
                @csrf
                <div class="field">
                    <label for="file_pdf">File PDF hasil scan</label>
                    <input id="file_pdf" name="file_pdf" type="file" class="input @error('file_pdf') is-invalid @enderror" accept="application/pdf" required>
                    <p class="help-text" style="margin-top: 7px;">Maksimal 50 MB. Gunakan kualitas scan 300 DPI agar hasil paling konsisten.</p>
                </div>
                <button type="submit" class="button button-primary" style="margin-top: 14px;">Proses PDF</button>
            </form>
        </section>

        <aside class="panel panel-pad">
            <h2 class="panel-title">Ringkasan ujian</h2>
            <dl class="detail-grid" style="margin-top: 14px;">
                <div class="detail-item"><dt>Kode ujian</dt><dd>{{ $ujianOmr->kode }}</dd></div>
                <div class="detail-item"><dt>Mapel</dt><dd>{{ $ujianOmr->mataPelajaran?->nama ?: '-' }}</dd></div>
                <div class="detail-item"><dt>Tahun pelajaran</dt><dd>{{ $ujianOmr->tahunPelajaran?->nama ?: '-' }}</dd></div>
                <div class="detail-item"><dt>LJK personal</dt><dd>{{ $ujianOmr->lembar_jawab_ujian_omr_count }} lembar</dd></div>
            </dl>
        </aside>
    </div>

    <section class="panel panel-pad" style="margin-top: 20px;">
        <h2 class="panel-title">Riwayat pemrosesan PDF</h2>
        @if ($batchScan->isEmpty())
            <p class="help-text" style="margin-top: 10px;">Belum ada PDF hasil scan yang diproses.</p>
        @else
            <div class="table-wrap" style="margin-top: 14px;">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>File</th>
                            <th>Halaman</th>
                            <th>LJK</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batchScan as $batch)
                            <tr>
                                <td>{{ $batch->created_at?->format('d-m-Y H:i') }}</td>
                                <td><strong>{{ $batch->nama_file_asli }}</strong></td>
                                <td>{{ $batch->jumlah_halaman_pdf }}</td>
                                <td>{{ $batch->jumlah_ljk_terdeteksi }}</td>
                                <td>
                                    <span class="badge {{ $batch->status === 'selesai' ? 'badge-active' : ($batch->status === 'gagal' ? 'badge-inactive' : 'badge-warning') }}">
                                        {{ ucfirst(str_replace('_', ' ', $batch->status)) }}
                                    </span>
                                </td>
                                <td><a href="{{ route('ujian-omr.scan.show', [$ujianOmr, $batch]) }}" class="button button-muted button-sm">Lihat hasil</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $batchScan->links() }}</div>
        @endif
    </section>
@endsection
