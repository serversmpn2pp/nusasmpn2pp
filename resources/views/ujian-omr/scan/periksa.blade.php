@extends('layouts.app')

@section('title', 'Periksa LJK - NUSA')

@section('content')
    @php
        $lembarDipilihId = old('lembar_jawab_ujian_omr_id', $hasilScan->lembar_jawab_ujian_omr_id);
        $jawabanTersimpan = $hasilScan->jawaban->keyBy('nomor_soal');
        $nilaiJawaban = fn (int $nomor) => old('jawaban.' . $nomor, $jawabanTersimpan[$nomor]?->jawaban);
        $statusJawaban = fn (int $nomor) => $jawabanTersimpan[$nomor]?->status;
    @endphp

    <style>
        .omr-review-shell {
            display: grid;
            grid-template-columns: minmax(300px, 42%) minmax(0, 1fr);
            gap: 20px;
            align-items: start;
        }

        .omr-review-preview {
            position: sticky;
            top: 18px;
        }

        .omr-review-preview img {
            display: block;
            width: 100%;
            max-height: calc(100vh - 210px);
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #eef3f8;
            object-fit: contain;
        }

        .omr-answer-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 9px;
            margin-top: 14px;
        }

        .omr-answer-item {
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff;
            padding: 8px;
        }

        .omr-answer-item.problem {
            border-color: #f1c40f;
            background: #fffbea;
        }

        .omr-answer-item label {
            display: block;
            margin-bottom: 5px;
            color: var(--primary-dark);
            font-size: .78rem;
            font-weight: 800;
        }

        .omr-answer-item .select {
            min-width: 0;
            padding: 7px 8px;
            font-size: .86rem;
        }

        .omr-live-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        @media (max-width: 980px) {
            .omr-review-shell {
                grid-template-columns: 1fr;
            }

            .omr-review-preview {
                position: static;
            }

            .omr-review-preview img {
                max-height: 520px;
            }
        }

        @media (max-width: 620px) {
            .omr-answer-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Pemeriksaan Manual OMR</p>
            <h1 class="page-title">Periksa hasil LJK</h1>
            <p class="page-subtitle">Halaman {{ $hasilScan->halaman_pdf }} / posisi {{ $hasilScan->urutan_ljk }} dari {{ $batchScan->nama_file_asli }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('ujian-omr.scan.show', [$ujianOmr, $batchScan]) }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ada data yang perlu diperbaiki.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('ujian-omr.scan.hasil.koreksi', [$ujianOmr, $batchScan, $hasilScan]) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="omr-review-shell">
            <aside class="panel panel-pad omr-review-preview">
                <p class="eyebrow">Pratinjau Hasil Scan</p>
                <h2 class="panel-title" style="margin-bottom: 12px;">Lembar jawaban siswa</h2>
                <a href="{{ route('ujian-omr.scan.pratinjau', [$ujianOmr, $batchScan, $hasilScan]) }}" target="_blank" rel="noopener">
                    <img src="{{ route('ujian-omr.scan.pratinjau', [$ujianOmr, $batchScan, $hasilScan]) }}" alt="Pratinjau LJK halaman {{ $hasilScan->halaman_pdf }}">
                </a>
                <p class="help-text" style="margin-top: 10px;">Klik gambar untuk membuka ukuran penuh pada tab baru.</p>

                @if ($hasilScan->catatan)
                    <div class="alert" style="margin: 14px 0 0;">
                        {{ $hasilScan->catatan }}
                    </div>
                @endif
            </aside>

            <div class="section-stack">
                <section class="panel panel-pad">
                    <p class="eyebrow">Identitas Peserta</p>
                    <h2 class="panel-title">Hubungkan LJK dengan siswa</h2>
                    <p class="help-text">Pilih siswa yang namanya tercetak pada lembar jawaban. Langkah ini juga memperbaiki hasil scan saat QR tidak terbaca.</p>

                    <div class="field" style="margin-top: 14px;">
                        <label for="lembar_jawab_ujian_omr_id">Siswa pemilik LJK</label>
                        <select id="lembar_jawab_ujian_omr_id" name="lembar_jawab_ujian_omr_id" class="select" required>
                            <option value="">Pilih siswa</option>
                            @foreach ($daftarLembarJawab as $lembar)
                                @php
                                    $sudahTerpakai = $lembarJawabTerpakaiIds->contains((int) $lembar->id);
                                @endphp
                                <option value="{{ $lembar->id }}" @selected((string) $lembarDipilihId === (string) $lembar->id) @disabled($sudahTerpakai)>
                                    {{ $lembar->anggotaKelas?->kelas?->nama ?: '-' }}
                                    - No. {{ $lembar->anggotaKelas?->nomor_absen ?: '-' }}
                                    - {{ $lembar->anggotaKelas?->siswa?->nama_lengkap ?: '-' }}
                                    - Versi {{ $lembar->versiSoalUjianOmr?->kode ?: '-' }}
                                    {{ $sudahTerpakai ? '- sudah terhubung' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </section>

                <section class="panel panel-pad">
                    <p class="eyebrow">Jawaban Siswa</p>
                    <h2 class="panel-title">Koreksi hasil pembacaan</h2>
                    <p class="help-text">Kotak berwarna kuning perlu diperhatikan. Pilih tanda strip jika siswa memang tidak menjawab soal tersebut.</p>

                    <div class="omr-live-summary">
                        <span class="badge badge-active"><span data-terisi>0</span>&nbsp;terisi</span>
                        <span class="badge badge-warning"><span data-kosong>0</span>&nbsp;kosong</span>
                    </div>

                    <div class="omr-answer-grid">
                        @foreach (range(1, $ujianOmr->jumlah_soal) as $nomorSoal)
                            @php
                                $status = $statusJawaban($nomorSoal);
                                $bermasalah = ! in_array($status, ['terbaca', 'dikoreksi_manual', 'kosong_dikonfirmasi'], true);
                            @endphp
                            <div class="omr-answer-item {{ $bermasalah ? 'problem' : '' }}" data-answer-item>
                                <label for="jawaban_{{ $nomorSoal }}">No. {{ $nomorSoal }}</label>
                                <select id="jawaban_{{ $nomorSoal }}" name="jawaban[{{ $nomorSoal }}]" class="select" data-answer>
                                    <option value="">-</option>
                                    @foreach ($daftarPilihan as $pilihan)
                                        <option value="{{ $pilihan }}" @selected($nilaiJawaban($nomorSoal) === $pilihan)>{{ $pilihan }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="panel panel-pad">
                    <div class="field">
                        <label for="catatan_koreksi">Catatan koreksi</label>
                        <textarea id="catatan_koreksi" name="catatan_koreksi" class="textarea" placeholder="Opsional, misalnya: QR tidak terbaca dan identitas dicocokkan dari nama siswa.">{{ old('catatan_koreksi', $hasilScan->catatan_koreksi) }}</textarea>
                    </div>
                </section>

                <div class="form-actions">
                    <a href="{{ route('ujian-omr.scan.show', [$ujianOmr, $batchScan]) }}" class="button button-muted">Batal</a>
                    <button type="submit" class="button button-primary">Simpan Koreksi</button>
                </div>
            </div>
        </div>
    </form>

    <script>
        (() => {
            const answers = [...document.querySelectorAll('[data-answer]')];
            const terisi = document.querySelector('[data-terisi]');
            const kosong = document.querySelector('[data-kosong]');

            const updateSummary = () => {
                const jumlahTerisi = answers.filter((answer) => answer.value).length;
                terisi.textContent = jumlahTerisi;
                kosong.textContent = answers.length - jumlahTerisi;
            };

            answers.forEach((answer) => {
                answer.addEventListener('change', () => {
                    answer.closest('[data-answer-item]').classList.remove('problem');
                    updateSummary();
                });
            });
            updateSummary();
        })();
    </script>
@endsection
