@extends('layouts.app')

@section('title', 'Hasil Asesmen Kelas - NUSA')

@section('content')
    <style>
        .hasil-asesmen-actions {
            align-items: center;
        }

        .hasil-asesmen-actions form {
            margin: 0;
        }

        .hasil-asesmen-overview {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(260px, .75fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .hasil-asesmen-title-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .hasil-asesmen-title-row .badge {
            flex: 0 0 auto;
        }

        .hasil-asesmen-targets {
            display: grid;
            gap: 10px;
            margin-top: 16px;
        }

        .hasil-asesmen-target {
            display: grid;
            grid-template-columns: minmax(72px, auto) minmax(0, 1fr);
            gap: 12px;
            align-items: center;
            padding: 11px 12px;
            border: 1px solid var(--line);
            border-radius: 7px;
            background: #f8fafc;
        }

        .hasil-asesmen-target strong,
        .hasil-asesmen-target span {
            min-width: 0;
        }

        .hasil-asesmen-target span {
            color: var(--muted);
            font-size: .86rem;
            font-weight: 720;
            overflow-wrap: anywhere;
        }

        .hasil-asesmen-filter {
            display: grid;
            grid-template-columns: minmax(190px, .8fr) minmax(220px, 1fr) auto;
            gap: 12px;
            align-items: end;
            margin-bottom: 24px;
        }

        .hasil-asesmen-filter .actions {
            min-height: 44px;
        }

        .hasil-asesmen-score {
            display: grid;
            gap: 4px;
            min-width: 96px;
        }

        .hasil-asesmen-score strong {
            color: var(--primary-dark);
            font-size: 1.4rem;
            font-weight: 900;
            line-height: 1;
        }

        .hasil-asesmen-meta {
            display: grid;
            gap: 3px;
            color: var(--muted);
            font-size: .82rem;
            font-weight: 700;
        }

        .hasil-asesmen-progress {
            display: grid;
            gap: 7px;
            min-width: 170px;
        }

        .hasil-asesmen-progress-track {
            height: 8px;
            overflow: hidden;
            border-radius: 999px;
            background: #e5e7eb;
        }

        .hasil-asesmen-progress-fill {
            height: 100%;
            border-radius: inherit;
            background: var(--primary);
        }

        .hasil-asesmen-table td {
            vertical-align: top;
        }

        .stats-grid .stat.warning {
            border-color: #f1c40f;
            background: #fff8d6;
        }

        .hasil-asesmen-mobile-value {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid var(--line);
        }

        @media (max-width: 1060px) {
            .hasil-asesmen-overview {
                grid-template-columns: 1fr;
            }

            .hasil-asesmen-filter {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .hasil-asesmen-filter .actions {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 680px) {
            .hasil-asesmen-actions,
            .hasil-asesmen-actions form,
            .hasil-asesmen-actions .button {
                width: 100%;
            }

            .hasil-asesmen-overview {
                gap: 14px;
            }

            .hasil-asesmen-title-row {
                display: grid;
            }

            .hasil-asesmen-filter {
                grid-template-columns: 1fr;
            }

            .hasil-asesmen-filter .actions {
                grid-column: auto;
            }

            .hasil-asesmen-filter .actions .button {
                flex: 1 1 0;
            }
        }
    </style>

    @php
        $statusLabel = [
            'semua' => 'Semua hasil',
            'tuntas' => 'Tuntas',
            'belum_tuntas' => $ujianCbt->kkm ? 'Belum tuntas' : 'Selesai',
            'perlu_koreksi_otomatis' => 'Perlu koreksi otomatis',
            'perlu_koreksi_manual' => 'Perlu koreksi uraian',
            'belum_selesai' => 'Belum selesai mengerjakan',
        ];
        $formatAngka = fn ($nilai, $desimal = 2) => number_format((float) $nilai, $desimal, ',', '.');
        $jumlahSelesai = max(0, $ringkasan['total_peserta'] - $ringkasan['belum_selesai']);
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Asesmen Kelas</p>
            <h1 class="page-title">Hasil asesmen</h1>
            <p class="page-subtitle">Periksa hasil siswa, selesaikan koreksi, lalu masukkan nilainya ke komponen penilaian.</p>
        </div>

        <div class="actions hasil-asesmen-actions">
            <a href="{{ route('ujian-cbt.monitoring.index', $ujianCbt) }}" class="button button-muted">Pantau pengerjaan</a>
            @if ($ujianCbt->dapatDikelolaOleh(auth()->user()))
                @if ($jumlahSoalManual > 0)
                    <a href="{{ route('ujian-cbt.koreksi-manual.index', $ujianCbt) }}" class="button button-muted">Koreksi uraian</a>
                @endif
                <form action="{{ route('ujian-cbt.koreksi-otomatis.store', $ujianCbt) }}" method="POST" onsubmit="return confirm('Koreksi semua jawaban objektif yang sudah dikumpulkan?')">
                    @csrf
                    <button type="submit" class="button button-dark">Koreksi otomatis</button>
                </form>
                <form action="{{ route('ujian-cbt.terapkan-nilai.store', $ujianCbt) }}" method="POST" onsubmit="return confirm('Masukkan hasil yang sudah selesai dikoreksi ke nilai siswa?')">
                    @csrf
                    <button type="submit" class="button button-primary">Masukkan ke nilai</button>
                </form>
            @endif
            <a href="{{ route('asesmen-kelas-cbt.show', $ujianCbt) }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif
    @if (session('gagal'))
        <div class="alert alert-danger">{{ session('gagal') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="hasil-asesmen-overview">
        <section class="panel panel-pad">
            <div class="hasil-asesmen-title-row">
                <div>
                    <h2 class="panel-title">{{ $ujianCbt->nama }}</h2>
                    <p class="help-text" style="margin-top: 6px;">
                        {{ $ujianCbt->mataPelajaran?->nama ?: '-' }} · Semester {{ ucfirst($ujianCbt->semester) }} · {{ $ujianCbt->tahunPelajaran?->nama ?: '-' }}
                    </p>
                </div>
                <span class="badge badge-active">{{ $ujianCbt->labelStatus() }}</span>
            </div>

            <dl class="quick-facts" style="margin-top: 18px;">
                <div><dt>Waktu pelaksanaan</dt><dd>{{ $ujianCbt->labelWaktu() }}</dd></div>
                <div><dt>Durasi</dt><dd>{{ $ujianCbt->durasi_menit }} menit</dd></div>
                <div><dt>Soal digunakan</dt><dd>{{ $jumlahSoalTampil }} soal</dd></div>
                <div><dt>KKM</dt><dd>{{ $ujianCbt->kkm ?: 'Tidak digunakan' }}</dd></div>
            </dl>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Tujuan nilai</h2>
            <p class="help-text" style="margin-top: 6px;">Hasil yang sudah selesai dikoreksi akan masuk ke komponen berikut.</p>

            <div class="hasil-asesmen-targets">
                @foreach ($kelasPeserta as $kelasUjian)
                    <div class="hasil-asesmen-target">
                        <strong>{{ $kelasUjian->kelas?->nama ?: '-' }}</strong>
                        <span>{{ $kelasUjian->komponenNilai?->nama ?: 'Komponen nilai belum tersedia' }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Peserta</p>
            <p class="stat-value">{{ $ringkasan['total_peserta'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Sudah selesai</p>
            <p class="stat-value">{{ $jumlahSelesai }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Rata-rata</p>
            <p class="stat-value">{{ is_null($ringkasan['rata_rata_final']) ? '-' : $formatAngka($ringkasan['rata_rata_final']) }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Nilai tertinggi</p>
            <p class="stat-value">{{ is_null($ringkasan['nilai_tertinggi_final']) ? '-' : $formatAngka($ringkasan['nilai_tertinggi_final']) }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">{{ $ujianCbt->kkm ? 'Tuntas' : 'Hasil final' }}</p>
            <p class="stat-value">{{ $ujianCbt->kkm ? $ringkasan['tuntas'] : $ringkasan['hasil_final'] }}</p>
        </div>
        <div class="panel stat {{ $ringkasan['perlu_koreksi'] > 0 ? 'warning' : '' }}">
            <p class="stat-label">Perlu koreksi</p>
            <p class="stat-value">{{ $ringkasan['perlu_koreksi'] }}</p>
        </div>
    </div>

    <form action="{{ route('ujian-cbt.hasil.index', $ujianCbt) }}" method="GET" class="panel panel-pad hasil-asesmen-filter" data-auto-filter-form>
        <div class="field">
            <label for="kelas_id">Kelas</label>
            <select id="kelas_id" name="kelas_id" class="select" data-auto-submit>
                <option value="">Semua kelas</option>
                @foreach ($kelasPeserta as $kelasUjian)
                    <option value="{{ $kelasUjian->kelas_id }}" @selected((string) $kelasId === (string) $kelasUjian->kelas_id)>{{ $kelasUjian->kelas?->nama ?: '-' }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="status_hasil">Status hasil</label>
            <select id="status_hasil" name="status_hasil" class="select" data-auto-submit>
                @foreach ($statusLabel as $nilai => $label)
                    <option value="{{ $nilai }}" @selected($statusHasil === $nilai)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="actions">
            <noscript><button type="submit" class="button button-dark">Tampilkan</button></noscript>
            <a href="{{ route('ujian-cbt.hasil.index', $ujianCbt) }}" class="button button-muted">Reset</a>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table hasil-asesmen-table">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Kelas</th>
                        <th>Pengerjaan</th>
                        <th>Nilai</th>
                        <th>Status hasil</th>
                        <th>Nilai siswa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rekapHasil as $item)
                        @php
                            $peserta = $item['peserta'];
                            $persenJawaban = $jumlahSoalTampil > 0 ? min(100, round(($item['jawaban_tersimpan'] / $jumlahSoalTampil) * 100)) : 0;
                            $nilaiSementara = in_array($item['kode_status_hasil'], ['perlu_koreksi_otomatis', 'perlu_koreksi_manual'], true);
                            $nilaiTersedia = $item['kode_status_hasil'] !== 'belum_selesai';
                        @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $peserta->anggotaKelas?->siswa?->nama_lengkap ?: '-' }}</p>
                                <div class="hasil-asesmen-meta">
                                    <span>NISN {{ $peserta->anggotaKelas?->siswa?->nisn ?: '-' }}</span>
                                    <span>No. absen {{ $peserta->anggotaKelas?->nomor_absen ?: '-' }}</span>
                                </div>
                            </td>
                            <td><p class="person-name">{{ $peserta->kelasUjianCbt?->kelas?->nama ?: '-' }}</p></td>
                            <td>
                                <div class="hasil-asesmen-progress">
                                    <strong>{{ $item['jawaban_tersimpan'] }} dari {{ $jumlahSoalTampil }} soal dijawab</strong>
                                    <div class="hasil-asesmen-progress-track" aria-hidden="true">
                                        <div class="hasil-asesmen-progress-fill" style="width: {{ $persenJawaban }}%;"></div>
                                    </div>
                                    <span class="hasil-asesmen-meta">{{ $peserta->labelStatus() }}{{ $peserta->waktu_selesai ? ' · selesai '.$peserta->waktu_selesai->format('H:i') : '' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="hasil-asesmen-score">
                                    <strong>{{ $nilaiTersedia ? $formatAngka($item['nilai']) : '-' }}</strong>
                                    <span class="hasil-asesmen-meta">{{ ! $nilaiTersedia ? 'Belum tersedia' : ($nilaiSementara ? 'Nilai sementara' : 'Nilai akhir') }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $item['badge_status_hasil'] }}">{{ $item['label_status_hasil'] }}</span>
                                @if ($item['perlu_koreksi_manual'] > 0)
                                    <div class="hasil-asesmen-meta" style="margin-top: 7px;">{{ $item['perlu_koreksi_manual'] }} jawaban uraian menunggu koreksi</div>
                                @endif
                            </td>
                            <td>
                                @if ($peserta->nilai_diterapkan_pada)
                                    <span class="badge badge-active">Sudah masuk</span>
                                    <div class="hasil-asesmen-meta" style="margin-top: 7px;">{{ $peserta->nilai_diterapkan_pada->format('d-m-Y H:i') }}</div>
                                @else
                                    <span class="badge badge-muted">Belum masuk</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada hasil siswa yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($rekapHasil as $item)
                @php
                    $peserta = $item['peserta'];
                    $nilaiSementara = in_array($item['kode_status_hasil'], ['perlu_koreksi_otomatis', 'perlu_koreksi_manual'], true);
                    $nilaiTersedia = $item['kode_status_hasil'] !== 'belum_selesai';
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $peserta->anggotaKelas?->siswa?->nama_lengkap ?: '-' }}</p>
                            <p class="person-meta">{{ $peserta->kelasUjianCbt?->kelas?->nama ?: '-' }} · Absen {{ $peserta->anggotaKelas?->nomor_absen ?: '-' }}</p>
                        </div>
                        <span class="badge {{ $item['badge_status_hasil'] }}">{{ $item['label_status_hasil'] }}</span>
                    </div>

                    <dl class="quick-facts">
                        <div><dt>Soal dijawab</dt><dd>{{ $item['jawaban_tersimpan'] }} / {{ $jumlahSoalTampil }}</dd></div>
                        <div><dt>Status pengerjaan</dt><dd>{{ $peserta->labelStatus() }}</dd></div>
                        <div><dt>Jawaban benar</dt><dd>{{ $item['benar'] }}</dd></div>
                        <div><dt>Perlu koreksi uraian</dt><dd>{{ $item['perlu_koreksi_manual'] }}</dd></div>
                    </dl>

                    <div class="hasil-asesmen-mobile-value">
                        <div class="hasil-asesmen-score">
                            <span class="hasil-asesmen-meta">{{ ! $nilaiTersedia ? 'Belum tersedia' : ($nilaiSementara ? 'Nilai sementara' : 'Nilai akhir') }}</span>
                            <strong>{{ $nilaiTersedia ? $formatAngka($item['nilai']) : '-' }}</strong>
                        </div>
                        <span class="badge {{ $peserta->nilai_diterapkan_pada ? 'badge-active' : 'badge-muted' }}">
                            {{ $peserta->nilai_diterapkan_pada ? 'Sudah masuk nilai' : 'Belum masuk nilai' }}
                        </span>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada hasil siswa yang sesuai dengan filter.</div>
            @endforelse
        </div>
    </section>

    <script>
        document.querySelectorAll('[data-auto-submit]').forEach((field) => {
            field.addEventListener('change', () => field.form?.requestSubmit());
        });
    </script>
@endsection
