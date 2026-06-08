@extends('layouts.app')

@section('title', 'Koreksi Manual CBT - NUSA')

@section('content')
    <style>
        .manual-filter-grid {
            display: grid;
            grid-template-columns: minmax(170px, .8fr) minmax(170px, .8fr) minmax(190px, .8fr) auto;
            gap: 12px;
            align-items: end;
        }

        .manual-table td {
            vertical-align: top;
        }

        .manual-question {
            display: grid;
            gap: 6px;
            min-width: 210px;
        }

        .manual-question strong {
            color: var(--primary-dark);
            font-weight: 950;
        }

        .manual-answer {
            max-width: 520px;
            white-space: pre-line;
            color: var(--text);
            font-weight: 760;
            line-height: 1.55;
        }

        .manual-answer.empty {
            color: var(--muted);
            font-style: italic;
        }

        .manual-score {
            display: grid;
            gap: 6px;
            min-width: 132px;
        }

        .manual-score .input {
            max-width: 132px;
            text-align: center;
            font-weight: 900;
        }

        .manual-save-bar {
            position: sticky;
            bottom: 0;
            z-index: 5;
            margin-top: 18px;
            border-top: 1px solid var(--line);
            background: rgba(248, 250, 252, .94);
            padding: 14px 0 0;
            backdrop-filter: blur(8px);
        }

        @media (max-width: 1100px) {
            .manual-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 680px) {
            .manual-filter-grid {
                grid-template-columns: 1fr;
            }

            .manual-score .input {
                max-width: none;
                width: 100%;
            }
        }
    </style>

    @php
        $statusLabel = [
            'semua' => 'Semua jawaban',
            'belum_dikoreksi' => 'Belum dikoreksi',
            'sudah_dikoreksi' => 'Sudah dikoreksi',
        ];
        $formatAngka = fn ($nilai, $desimal = 2) => number_format((float) $nilai, $desimal, ',', '.');
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Koreksi manual CBT</h1>
        </div>

        <div class="actions">
            <a href="{{ route('ujian-cbt.hasil.index', $ujianCbt) }}" class="button button-primary">Hasil</a>
            <a href="{{ route('ujian-cbt.monitoring.index', $ujianCbt) }}" class="button button-muted">Monitoring</a>
            <a href="{{ route('ujian-cbt.show', $ujianCbt) }}" class="button button-muted">Detail paket</a>
            <a href="{{ route('ujian-cbt.index') }}" class="button button-muted">Daftar paket</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="panel panel-pad" style="margin-bottom: 24px;">
        <div style="display: flex; gap: 14px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap;">
            <div>
                <h2 class="panel-title">{{ $ujianCbt->nama }}</h2>
                <p class="help-text" style="margin-top: 6px;">
                    {{ $ujianCbt->kode }} - {{ $ujianCbt->mataPelajaran?->nama ?: '-' }} - {{ $ujianCbt->tahunPelajaran?->nama ?: '-' }}
                </p>
            </div>
            <span class="badge {{ $soalManual->count() > 0 ? 'badge-warning' : 'badge-muted' }}">
                {{ $soalManual->count() }} soal manual
            </span>
        </div>

        <dl class="quick-facts" style="margin-top: 18px;">
            <div><dt>Jenis ujian</dt><dd>{{ $ujianCbt->jenisUjianCbt?->nama ?: '-' }}</dd></div>
            <div><dt>Jadwal paket</dt><dd>{{ $ujianCbt->labelWaktu() }}</dd></div>
            <div><dt>Jawaban terjawab</dt><dd>{{ $ringkasan['terjawab'] }}</dd></div>
            <div><dt>Belum dijawab</dt><dd>{{ $ringkasan['belum_dijawab'] }}</dd></div>
            <div><dt>Belum dikoreksi</dt><dd>{{ $ringkasan['belum_dikoreksi'] }}</dd></div>
            <div><dt>Sudah dikoreksi</dt><dd>{{ $ringkasan['sudah_dikoreksi'] }}</dd></div>
        </dl>
    </section>

    <form action="{{ route('ujian-cbt.koreksi-manual.index', $ujianCbt) }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="manual-filter-grid">
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
                <label for="status_koreksi">Status koreksi</label>
                <select id="status_koreksi" name="status_koreksi" class="select">
                    @foreach ($statusLabel as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($statusKoreksi === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('ujian-cbt.koreksi-manual.index', $ujianCbt) }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    @if ($soalManual->isEmpty())
        <section class="panel panel-pad">
            <div class="empty-state">
                Paket ini tidak memiliki soal uraian atau upload file yang perlu koreksi manual.
            </div>
        </section>
    @else
        <form action="{{ route('ujian-cbt.koreksi-manual.update', $ujianCbt) }}" method="POST" class="panel">
            @csrf
            @method('PUT')

            <div class="desktop-only table-wrap">
                <table class="employee-table manual-table">
                    <thead>
                        <tr>
                            <th>Peserta</th>
                            <th>Soal</th>
                            <th>Jawaban</th>
                            <th>Status</th>
                            <th>Skor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($barisKoreksi as $item)
                            @php
                                $peserta = $item['peserta'];
                                $relasiSoal = $item['relasi_soal'];
                                $soal = $relasiSoal->soalCbt;
                                $jawaban = $item['jawaban'];
                            @endphp
                            <tr>
                                <td>
                                    <p class="person-name">{{ $peserta->anggotaKelas?->siswa?->nama_lengkap ?: '-' }}</p>
                                    <div class="person-meta">
                                        {{ $peserta->kelasUjianCbt?->kelas?->nama ?: '-' }} - {{ $peserta->sesiUjianCbt?->nama ?: 'Tanpa sesi' }}
                                    </div>
                                    <div class="person-meta">No. {{ $peserta->akunPesertaCbt?->nomor_peserta ?: $peserta->nomor_peserta }}</div>
                                </td>
                                <td>
                                    <div class="manual-question">
                                        <strong>No. {{ $relasiSoal->nomor_urut ?: '-' }} - {{ $soal?->labelJenis() ?: '-' }}</strong>
                                        <span class="person-meta">{{ $soal?->kode ?: '-' }}</span>
                                        <span>{{ $soal ? str(strip_tags($soal->pertanyaan))->limit(180) : 'Soal tidak ditemukan' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="manual-answer {{ $item['sudah_dijawab'] ? '' : 'empty' }}">
                                        {{ $item['sudah_dijawab'] ? $item['teks_jawaban'] : 'Belum dijawab' }}
                                    </div>
                                </td>
                                <td>
                                    @if (! $item['sudah_dijawab'])
                                        <span class="badge badge-muted">Belum dijawab</span>
                                    @elseif ($item['sudah_dikoreksi'])
                                        <span class="badge badge-active">Sudah dikoreksi</span>
                                    @else
                                        <span class="badge badge-warning">Belum dikoreksi</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($jawaban && $item['sudah_dijawab'])
                                        <div class="manual-score">
                                            <input type="number" name="skor[{{ $jawaban->id }}]" value="{{ old('skor.' . $jawaban->id, $jawaban->skor) }}" min="0" max="{{ (float) $relasiSoal->bobot }}" step="0.01" class="input" aria-label="Skor {{ $peserta->anggotaKelas?->siswa?->nama_lengkap }}" data-manual-input="desktop">
                                            <span class="person-meta">Maks. {{ $formatAngka($relasiSoal->bobot) }}</span>
                                        </div>
                                    @else
                                        <span class="person-meta">Tidak ada jawaban</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state">Belum ada jawaban manual yang sesuai filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mobile-only mobile-list" style="padding: 16px;">
                @forelse ($barisKoreksi as $item)
                    @php
                        $peserta = $item['peserta'];
                        $relasiSoal = $item['relasi_soal'];
                        $soal = $relasiSoal->soalCbt;
                        $jawaban = $item['jawaban'];
                    @endphp
                    <article class="mobile-card">
                        <div class="mobile-card-head">
                            <div>
                                <p class="person-name">{{ $peserta->anggotaKelas?->siswa?->nama_lengkap ?: '-' }}</p>
                                <p class="person-meta">{{ $peserta->kelasUjianCbt?->kelas?->nama ?: '-' }} - {{ $peserta->sesiUjianCbt?->nama ?: 'Tanpa sesi' }}</p>
                            </div>
                            @if (! $item['sudah_dijawab'])
                                <span class="badge badge-muted">Kosong</span>
                            @elseif ($item['sudah_dikoreksi'])
                                <span class="badge badge-active">Selesai</span>
                            @else
                                <span class="badge badge-warning">Koreksi</span>
                            @endif
                        </div>
                        <dl class="quick-facts">
                            <div><dt>Soal</dt><dd>No. {{ $relasiSoal->nomor_urut ?: '-' }}</dd></div>
                            <div><dt>Jenis</dt><dd>{{ $soal?->labelJenis() ?: '-' }}</dd></div>
                            <div><dt>Bobot</dt><dd>{{ $formatAngka($relasiSoal->bobot) }}</dd></div>
                        </dl>
                        <div style="margin-top: 12px;">
                            <p class="person-meta">Pertanyaan</p>
                            <p style="margin-top: 4px;">{{ $soal ? str(strip_tags($soal->pertanyaan))->limit(180) : 'Soal tidak ditemukan' }}</p>
                        </div>
                        <div style="margin-top: 12px;">
                            <p class="person-meta">Jawaban</p>
                            <div class="manual-answer {{ $item['sudah_dijawab'] ? '' : 'empty' }}" style="margin-top: 4px;">
                                {{ $item['sudah_dijawab'] ? $item['teks_jawaban'] : 'Belum dijawab' }}
                            </div>
                        </div>
                        @if ($jawaban && $item['sudah_dijawab'])
                            <div class="field" style="margin-top: 12px;">
                                <label for="skor_mobile_{{ $jawaban->id }}">Skor</label>
                                <input id="skor_mobile_{{ $jawaban->id }}" type="number" name="skor[{{ $jawaban->id }}]" value="{{ old('skor.' . $jawaban->id, $jawaban->skor) }}" min="0" max="{{ (float) $relasiSoal->bobot }}" step="0.01" class="input" data-manual-input="mobile">
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="empty-state">Belum ada jawaban manual yang sesuai filter.</div>
                @endforelse
            </div>

            @if ($barisKoreksi->isNotEmpty())
                <div class="manual-save-bar">
                    <div class="panel-pad" style="padding-top: 0;">
                        <div class="actions" style="justify-content: flex-end;">
                            <a href="{{ route('ujian-cbt.hasil.index', $ujianCbt) }}" class="button button-muted">Lihat hasil</a>
                            <button type="submit" class="button button-primary">Simpan koreksi manual</button>
                        </div>
                    </div>
                </div>
            @endif
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        const mediaKoreksiManual = window.matchMedia('(max-width: 680px)');
        const aturInputKoreksiManual = () => {
            document.querySelectorAll('[data-manual-input]').forEach((input) => {
                input.disabled = input.dataset.manualInput === 'mobile' ? !mediaKoreksiManual.matches : mediaKoreksiManual.matches;
            });
        };

        aturInputKoreksiManual();
        mediaKoreksiManual.addEventListener('change', aturInputKoreksiManual);
    </script>
@endpush
