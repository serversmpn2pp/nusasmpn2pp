@extends('layouts.app')

@section('title', 'Rekap Hasil CBT - NUSA')

@section('content')
    <style>
        .hasil-filter-grid {
            display: grid;
            grid-template-columns: minmax(170px, .8fr) minmax(170px, .8fr) minmax(190px, .8fr) auto;
            gap: 12px;
            align-items: end;
        }

        .hasil-score {
            display: grid;
            gap: 2px;
            min-width: 110px;
        }

        .hasil-score strong {
            color: var(--primary-dark);
            font-size: 1.35rem;
            font-weight: 950;
            line-height: 1;
        }

        .hasil-score span,
        .hasil-meta {
            color: var(--muted);
            font-size: .82rem;
            font-weight: 760;
        }

        .hasil-meta {
            display: grid;
            gap: 2px;
        }

        .hasil-progress {
            display: grid;
            gap: 6px;
            min-width: 150px;
        }

        .hasil-progress-track {
            height: 9px;
            overflow: hidden;
            border-radius: 999px;
            background: #e5e7eb;
        }

        .hasil-progress-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--primary), #1f78b4);
        }

        .hasil-table td {
            vertical-align: top;
        }

        @media (max-width: 1100px) {
            .hasil-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 680px) {
            .hasil-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @php
        $statusLabel = [
            'semua' => 'Semua hasil',
            'tuntas' => 'Tuntas',
            'belum_tuntas' => 'Belum tuntas',
            'perlu_koreksi_otomatis' => 'Perlu koreksi otomatis',
            'perlu_koreksi_manual' => 'Perlu koreksi manual',
            'belum_selesai' => 'Belum selesai',
        ];
        $formatAngka = fn ($nilai, $desimal = 2) => number_format((float) $nilai, $desimal, ',', '.');
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Rekap hasil CBT</h1>
        </div>

        <div class="actions">
            @if ($ujianCbt->dapatDikelolaOleh(auth()->user()))
                <form action="{{ route('ujian-cbt.koreksi-otomatis.store', $ujianCbt) }}" method="POST" onsubmit="return confirm('Jalankan koreksi otomatis untuk jawaban objektif pada paket ini?')">
                    @csrf
                    <button type="submit" class="button button-dark">Koreksi otomatis</button>
                </form>
                <a href="{{ route('ujian-cbt.koreksi-manual.index', $ujianCbt) }}" class="button button-muted">Koreksi manual</a>
                <form action="{{ route('ujian-cbt.terapkan-nilai.store', $ujianCbt) }}" method="POST" onsubmit="return confirm('Terapkan nilai CBT ke nilai siswa? Koreksi otomatis akan dijalankan dahulu, dan peserta yang masih perlu koreksi manual akan dilewati.')">
                    @csrf
                    <button type="submit" class="button button-primary">Terapkan nilai</button>
                </form>
            @endif
            @if ($ujianCbt->ujianTerpusat())
                <a href="{{ route('ujian-cbt.ruang.index', $ujianCbt) }}" class="button button-muted">Ruang</a>
            @endif
            <a href="{{ route('ujian-cbt.monitoring.index', $ujianCbt) }}" class="button button-muted">Monitoring</a>
            <a href="{{ route($ujianCbt->asesmenKelas() ? 'asesmen-kelas-cbt.show' : 'ujian-cbt.show', $ujianCbt) }}" class="button button-muted">Detail {{ $ujianCbt->asesmenKelas() ? 'asesmen' : 'paket' }}</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif
    @if (session('gagal'))
        <div class="alert alert-danger">{{ session('gagal') }}</div>
    @endif

    <section class="panel panel-pad" style="margin-bottom: 24px;">
        <div style="display: flex; gap: 14px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap;">
            <div>
                <h2 class="panel-title">{{ $ujianCbt->nama }}</h2>
                <p class="help-text" style="margin-top: 6px;">
                    {{ $ujianCbt->kode }} - {{ $ujianCbt->mataPelajaran?->nama ?: '-' }} - {{ $ujianCbt->tahunPelajaran?->nama ?: '-' }}
                </p>
            </div>
            <span class="badge {{ $ujianCbt->kkm ? 'badge-active' : 'badge-warning' }}">
                KKM: {{ $ujianCbt->kkm ?: 'Belum diatur' }}
            </span>
        </div>

        <dl class="quick-facts" style="margin-top: 18px;">
            <div><dt>Jenis ujian</dt><dd>{{ $ujianCbt->jenisUjianCbt?->nama ?: '-' }}</dd></div>
            <div><dt>Jadwal paket</dt><dd>{{ $ujianCbt->labelWaktu() }}</dd></div>
            <div><dt>Soal tampil</dt><dd>{{ $jumlahSoalTampil }} soal</dd></div>
            <div><dt>Total bobot</dt><dd>{{ $formatAngka($bobotTotal) }}</dd></div>
            <div><dt>Soal otomatis</dt><dd>{{ $jumlahSoalOtomatis }}</dd></div>
            <div><dt>Soal manual</dt><dd>{{ $jumlahSoalManual }}</dd></div>
        </dl>
    </section>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total peserta</p>
            <p class="stat-value">{{ $ringkasan['total_peserta'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Rata-rata</p>
            <p class="stat-value">{{ $formatAngka($ringkasan['rata_rata']) }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Tertinggi</p>
            <p class="stat-value">{{ $formatAngka($ringkasan['nilai_tertinggi']) }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Terendah</p>
            <p class="stat-value">{{ $formatAngka($ringkasan['nilai_terendah']) }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Tuntas</p>
            <p class="stat-value">{{ $ringkasan['tuntas'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Perlu koreksi</p>
            <p class="stat-value">{{ $ringkasan['perlu_koreksi'] }}</p>
        </div>
    </div>

    <form action="{{ route('ujian-cbt.hasil.index', $ujianCbt) }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="hasil-filter-grid">
            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select">
                    <option value="">Semua kelas</option>
                    @foreach ($kelasPeserta as $kelasUjian)
                        <option value="{{ $kelasUjian->kelas_id }}" @selected((string) $kelasId === (string) $kelasUjian->kelas_id)>{{ $kelasUjian->kelas?->nama ?: '-' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="sesi_ujian_cbt_id">Sesi</label>
                <select id="sesi_ujian_cbt_id" name="sesi_ujian_cbt_id" class="select">
                    <option value="">Semua sesi</option>
                    @foreach ($sesiUjianCbt as $sesi)
                        <option value="{{ $sesi->id }}" @selected((string) $sesiUjianCbtId === (string) $sesi->id)>{{ $sesi->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="status_hasil">Status hasil</label>
                <select id="status_hasil" name="status_hasil" class="select">
                    @foreach ($statusLabel as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($statusHasil === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('ujian-cbt.hasil.index', $ujianCbt) }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table hasil-table">
                <thead>
                    <tr>
                        <th>Peserta</th>
                        <th>Kelas/Sesi</th>
                        <th>Nilai</th>
                        <th>Rincian</th>
                        <th>Status</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rekapHasil as $item)
                        @php
                            $peserta = $item['peserta'];
                            $persenJawaban = $jumlahSoalTampil > 0 ? min(100, round(($item['jawaban_tersimpan'] / $jumlahSoalTampil) * 100)) : 0;
                            $nilaiSementara = in_array($item['kode_status_hasil'], ['perlu_koreksi_otomatis', 'perlu_koreksi_manual'], true);
                        @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $peserta->anggotaKelas?->siswa?->nama_lengkap ?: '-' }}</p>
                                <div class="hasil-meta">
                                    <span>NISN {{ $peserta->anggotaKelas?->siswa?->nisn ?: '-' }}</span>
                                </div>
                            </td>
                            <td>
                                <p class="person-name">{{ $peserta->kelasUjianCbt?->kelas?->nama ?: '-' }}</p>
                                <div class="hasil-meta">
                                    <span>Absen {{ $peserta->anggotaKelas?->nomor_absen ?: '-' }}</span>
                                    <span>{{ $peserta->sesiUjianCbt?->nama ?: 'Tanpa sesi' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="hasil-score">
                                    <strong>{{ $formatAngka($item['nilai']) }}</strong>
                                    <span>{{ $nilaiSementara ? 'nilai sementara' : 'nilai akhir' }}</span>
                                    <span>Skor {{ $formatAngka($item['skor_total']) }} / {{ $formatAngka($bobotTotal) }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="hasil-progress">
                                    <strong>{{ $item['jawaban_tersimpan'] }} / {{ $jumlahSoalTampil }} terjawab</strong>
                                    <div class="hasil-progress-track" aria-hidden="true">
                                        <div class="hasil-progress-fill" style="width: {{ $persenJawaban }}%;"></div>
                                    </div>
                                    <span class="hasil-meta">
                                        Benar {{ $item['benar'] }}, salah {{ $item['salah'] }}, kosong {{ $item['belum_jawab'] }}
                                    </span>
                                    @if ($item['belum_dikoreksi_otomatis'] > 0 || $item['perlu_koreksi_manual'] > 0)
                                        <span class="hasil-meta">
                                            Koreksi otomatis {{ $item['belum_dikoreksi_otomatis'] }}, manual {{ $item['perlu_koreksi_manual'] }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $item['badge_status_hasil'] }}">{{ $item['label_status_hasil'] }}</span>
                                <div class="hasil-meta" style="margin-top: 8px;">
                                    <span>Status ujian: {{ $peserta->labelStatus() }}</span>
                                    @if ($peserta->nilai_diterapkan_pada)
                                        <span>Nilai diterapkan: {{ $peserta->nilai_diterapkan_pada->format('d-m-Y H:i') }}</span>
                                    @else
                                        <span>Nilai belum diterapkan</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="hasil-meta">
                                    <span>Mulai: <strong>{{ $peserta->waktu_mulai?->format('H:i:s') ?: '-' }}</strong></span>
                                    <span>Selesai: <strong>{{ $peserta->waktu_selesai?->format('H:i:s') ?: '-' }}</strong></span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada hasil CBT yang sesuai filter.</td>
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
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $peserta->anggotaKelas?->siswa?->nama_lengkap ?: '-' }}</p>
                            <p class="person-meta">{{ $peserta->kelasUjianCbt?->kelas?->nama ?: '-' }} - {{ $peserta->sesiUjianCbt?->nama ?: 'Tanpa sesi' }}</p>
                        </div>
                        <span class="badge {{ $item['badge_status_hasil'] }}">{{ $item['label_status_hasil'] }}</span>
                    </div>
                    <dl class="quick-facts">
                        <div><dt>Nilai</dt><dd>{{ $formatAngka($item['nilai']) }}{{ $nilaiSementara ? ' sementara' : '' }}</dd></div>
                        <div><dt>Skor</dt><dd>{{ $formatAngka($item['skor_total']) }} / {{ $formatAngka($bobotTotal) }}</dd></div>
                        <div><dt>Benar</dt><dd>{{ $item['benar'] }}</dd></div>
                        <div><dt>Salah</dt><dd>{{ $item['salah'] }}</dd></div>
                        <div><dt>Kosong</dt><dd>{{ $item['belum_jawab'] }}</dd></div>
                        <div><dt>Dikoreksi</dt><dd>{{ $item['jawaban_dikoreksi'] }}</dd></div>
                        <div><dt>Diterapkan</dt><dd>{{ $peserta->nilai_diterapkan_pada ? $peserta->nilai_diterapkan_pada->format('d-m-Y H:i') : 'Belum' }}</dd></div>
                    </dl>
                </article>
            @empty
                <div class="empty-state">Belum ada hasil CBT yang sesuai filter.</div>
            @endforelse
        </div>
    </section>
@endsection
