@extends('layouts.app')

@section('title', 'Monitoring CBT - NUSA')

@section('content')
    <style>
        .monitor-filter-grid {
            display: grid;
            grid-template-columns: minmax(170px, .8fr) minmax(170px, .8fr) minmax(190px, .8fr) auto;
            gap: 12px;
            align-items: end;
        }

        .monitor-ready-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .monitor-status-card {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 14px;
        }

        .monitor-status-card.warning {
            border-color: rgba(241, 196, 15, .65);
            background: var(--accent-soft);
        }

        .monitor-status-card.danger {
            border-color: #fecaca;
            background: var(--danger-soft);
        }

        .monitor-status-card.success {
            border-color: #bbf7d0;
            background: #f0fdf4;
        }

        .monitor-status-card dt {
            margin: 0 0 4px;
            color: var(--muted);
            font-size: .78rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .monitor-status-card dd {
            margin: 0;
            color: var(--text);
            font-size: .98rem;
            font-weight: 900;
        }

        .monitor-progress {
            display: grid;
            gap: 6px;
            min-width: 150px;
        }

        .monitor-progress-track {
            height: 9px;
            overflow: hidden;
            border-radius: 999px;
            background: #e5e7eb;
        }

        .monitor-progress-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--primary), #1f78b4);
        }

        .monitor-meta {
            display: grid;
            gap: 2px;
            color: var(--muted);
            font-size: .82rem;
            font-weight: 760;
        }

        .monitor-token {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(241, 196, 15, .72);
            border-radius: 8px;
            background: #fff;
            padding: 8px 10px;
            color: var(--primary-dark);
            font-size: 1rem;
            font-weight: 950;
            letter-spacing: .03em;
        }

        .monitor-time {
            color: var(--primary-dark);
            font-weight: 950;
            white-space: nowrap;
        }

        .monitor-table td {
            vertical-align: top;
        }

        @media (max-width: 1100px) {
            .monitor-filter-grid,
            .monitor-ready-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 680px) {
            .monitor-filter-grid,
            .monitor-ready-grid {
                grid-template-columns: 1fr;
            }

            .monitor-token {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>

    @php
        $statusLabel = [
            'semua' => 'Semua status',
            'belum_login' => 'Belum login',
            'sudah_login' => 'Sudah login',
            'sedang_mengerjakan' => 'Sedang mengerjakan',
            'selesai' => 'Selesai',
            'nonaktif' => 'Nonaktif',
            'terblokir' => 'Terblokir',
        ];
        $queryAutoRefresh = array_merge(request()->query(), ['auto_refresh' => $autoRefresh ? 0 : 1]);
        $paketDibuka = in_array($ujianCbt->status, ['terjadwal', 'berlangsung'], true);
        $soalSiap = $jumlahSoalTampil > 0;
        $pesertaSiap = $ringkasan['total'] > 0;
        $tokenSiap = filled($ujianCbt->token);
        $badgeStatusPaket = $paketDibuka ? 'badge-active' : ($ujianCbt->status === 'nonaktif' ? 'badge-inactive' : 'badge-warning');
        $persenSelesai = $ringkasan['total'] > 0 ? round(($ringkasan['selesai'] / $ringkasan['total']) * 100) : 0;
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Monitoring CBT</h1>
        </div>

        <div class="actions">
            <a href="{{ route('ujian-cbt.monitoring.index', [$ujianCbt] + request()->query()) }}" class="button button-primary">Refresh</a>
            <form action="{{ route('ujian-cbt.koreksi-otomatis.store', $ujianCbt) }}" method="POST" onsubmit="return confirm('Jalankan koreksi otomatis untuk jawaban objektif pada paket ini?')">
                @csrf
                <button type="submit" class="button button-dark">Koreksi otomatis</button>
            </form>
            <a href="{{ route('ujian-cbt.koreksi-manual.index', $ujianCbt) }}" class="button button-muted">Koreksi manual</a>
            <a href="{{ route('ujian-cbt.monitoring.index', [$ujianCbt] + $queryAutoRefresh) }}" class="button {{ $autoRefresh ? 'button-danger' : 'button-muted' }}">
                {{ $autoRefresh ? 'Matikan auto refresh' : 'Auto refresh' }}
            </a>
            <a href="{{ route('ujian-cbt.hasil.index', $ujianCbt) }}" class="button button-muted">Hasil</a>
            <a href="{{ route('ujian-cbt.ruang.index', $ujianCbt) }}" class="button button-muted">Ruang</a>
            <a href="{{ route('ujian-cbt.peserta.index', $ujianCbt) }}" class="button button-muted">Peserta & sesi</a>
            <a href="{{ route('ujian-cbt.show', $ujianCbt) }}" class="button button-muted">Detail paket</a>
        </div>
    </div>

    <section class="panel panel-pad" style="margin-bottom: 24px;">
        @if (session('berhasil'))
            <div class="alert" style="margin-bottom: 16px;">{{ session('berhasil') }}</div>
        @endif

        <div style="display: flex; gap: 14px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap;">
            <div>
                <h2 class="panel-title">{{ $ujianCbt->nama }}</h2>
                <p class="help-text" style="margin-top: 6px;">
                    {{ $ujianCbt->kode }} - {{ $ujianCbt->mataPelajaran?->nama ?: '-' }} - {{ $ujianCbt->tahunPelajaran?->nama ?: '-' }}
                </p>
                <div class="actions" style="margin-top: 12px;">
                    <span class="badge {{ $badgeStatusPaket }}">{{ $ujianCbt->labelStatus() }}</span>
                    <span class="badge {{ $ujianCbt->acak_soal ? 'badge-active' : 'badge-muted' }}">Acak soal: {{ $ujianCbt->acak_soal ? 'Ya' : 'Tidak' }}</span>
                    <span class="badge {{ $ujianCbt->acak_jawaban ? 'badge-active' : 'badge-muted' }}">Acak jawaban: {{ $ujianCbt->acak_jawaban ? 'Ya' : 'Tidak' }}</span>
                </div>
            </div>

            <div style="display: grid; gap: 8px; min-width: min(100%, 250px);">
                <span class="monitor-token">
                    Token: <span id="tokenCbt">{{ $ujianCbt->token ?: '-' }}</span>
                </span>
                @if ($ujianCbt->token)
                    <button type="button" class="button button-muted button-full" data-copy-token>Salin token</button>
                @endif
            </div>
        </div>

        <dl class="quick-facts" style="margin-top: 18px;">
            <div><dt>Jadwal paket</dt><dd>{{ $ujianCbt->labelWaktu() }}</dd></div>
            <div><dt>Durasi</dt><dd>{{ $ujianCbt->durasi_menit }} menit</dd></div>
            <div><dt>Soal paket</dt><dd>{{ $jumlahSoalPaket }} soal, tampil {{ $jumlahSoalTampil }}</dd></div>
            <div><dt>Terakhir dipantau</dt><dd>{{ $waktuSekarang->format('d-m-Y H:i:s') }}</dd></div>
        </dl>
    </section>

    <section class="monitor-ready-grid" style="margin-bottom: 24px;">
        <dl class="monitor-status-card {{ $paketDibuka ? 'success' : 'warning' }}">
            <dt>Status paket</dt>
            <dd>{{ $paketDibuka ? 'Siap dibuka peserta' : 'Belum dibuka untuk login' }}</dd>
        </dl>
        <dl class="monitor-status-card {{ $tokenSiap ? 'success' : 'warning' }}">
            <dt>Token</dt>
            <dd>{{ $tokenSiap ? 'Sudah tersedia' : 'Belum tersedia' }}</dd>
        </dl>
        <dl class="monitor-status-card {{ $soalSiap ? 'success' : 'danger' }}">
            <dt>Soal</dt>
            <dd>{{ $soalSiap ? "{$jumlahSoalTampil} soal siap tampil" : 'Belum ada soal' }}</dd>
        </dl>
        <dl class="monitor-status-card {{ $pesertaSiap ? 'success' : 'danger' }}">
            <dt>Peserta</dt>
            <dd>{{ $pesertaSiap ? "{$ringkasan['total']} peserta terdaftar" : 'Belum ada peserta' }}</dd>
        </dl>
    </section>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total peserta</p>
            <p class="stat-value">{{ $ringkasan['total'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Belum login</p>
            <p class="stat-value">{{ $ringkasan['belum_login'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Sudah login</p>
            <p class="stat-value">{{ $ringkasan['sudah_login'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Sedang mengerjakan</p>
            <p class="stat-value">{{ $ringkasan['sedang_mengerjakan'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Selesai</p>
            <p class="stat-value">{{ $ringkasan['selesai'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Progres selesai</p>
            <p class="stat-value">{{ $persenSelesai }}%</p>
        </div>
    </div>

    <form action="{{ route('ujian-cbt.monitoring.index', $ujianCbt) }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="monitor-filter-grid">
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
                <label for="status_monitor">Status peserta</label>
                <select id="status_monitor" name="status_monitor" class="select">
                    @foreach ($statusLabel as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($statusMonitor === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                @if ($autoRefresh)
                    <input type="hidden" name="auto_refresh" value="1">
                @endif
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('ujian-cbt.monitoring.index', $ujianCbt) }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table monitor-table">
                <thead>
                    <tr>
                        <th>Peserta</th>
                        <th>Kelas/Sesi</th>
                        <th>Status</th>
                        <th>Jawaban</th>
                        <th>Waktu</th>
                        <th>Perangkat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pesertaUjianCbt as $peserta)
                        @php
                            $statusDasar = $peserta->status;
                            $sudahLogin = $statusDasar === 'aktif' && filled($peserta->ip_terakhir);
                            $labelStatus = match (true) {
                                $statusDasar === 'aktif' && ! $sudahLogin => 'Belum login',
                                $statusDasar === 'aktif' && $sudahLogin => 'Sudah login',
                                default => $peserta->labelStatus(),
                            };
                            $badgeStatus = match ($statusDasar) {
                                'sedang_mengerjakan' => 'badge-active',
                                'selesai' => 'badge-active',
                                'nonaktif', 'terblokir' => 'badge-inactive',
                                default => $sudahLogin ? 'badge-warning' : 'badge-muted',
                            };
                            $jawabanTersimpan = (int) $peserta->jumlah_jawaban_tersimpan;
                            $persenJawaban = $jumlahSoalTampil > 0 ? min(100, round(($jawabanTersimpan / $jumlahSoalTampil) * 100)) : 0;
                            $sisaMenit = null;
                            if ($peserta->status === 'sedang_mengerjakan' && $peserta->waktu_mulai) {
                                $batasPengerjaan = $peserta->waktu_mulai->copy()->addMinutes($ujianCbt->durasi_menit);
                                $batasSesi = $peserta->sesiUjianCbt?->waktu_selesai ?: $ujianCbt->tanggal_selesai;
                                if ($batasSesi && $batasSesi->lt($batasPengerjaan)) {
                                    $batasPengerjaan = $batasSesi;
                                }
                                $sisaMenit = max(0, (int) ceil(now()->diffInSeconds($batasPengerjaan, false) / 60));
                            }
                        @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $peserta->anggotaKelas?->siswa?->nama_lengkap ?: '-' }}</p>
                                <div class="monitor-meta">
                                    <span>No. {{ $peserta->akunPesertaCbt?->nomor_peserta ?: $peserta->nomor_peserta }}</span>
                                    <span>Username: {{ $peserta->akunPesertaCbt?->username ?: $peserta->username }}</span>
                                </div>
                            </td>
                            <td>
                                <p class="person-name">{{ $peserta->kelasUjianCbt?->kelas?->nama ?: '-' }}</p>
                                <div class="monitor-meta">
                                    <span>Absen {{ $peserta->anggotaKelas?->nomor_absen ?: '-' }}</span>
                                    <span>{{ $peserta->sesiUjianCbt?->nama ?: 'Tanpa sesi' }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $badgeStatus }}">{{ $labelStatus }}</span>
                                @if ($peserta->jumlah_jawaban_ragu > 0)
                                    <div style="margin-top: 7px;"><span class="badge badge-warning">{{ $peserta->jumlah_jawaban_ragu }} ragu</span></div>
                                @endif
                            </td>
                            <td>
                                <div class="monitor-progress">
                                    <strong>{{ $jawabanTersimpan }} / {{ $jumlahSoalTampil }}</strong>
                                    <div class="monitor-progress-track" aria-hidden="true">
                                        <div class="monitor-progress-fill" style="width: {{ $persenJawaban }}%;"></div>
                                    </div>
                                    <span class="person-meta">{{ $persenJawaban }}% terisi</span>
                                    @if ($peserta->jumlah_jawaban_dikoreksi > 0)
                                        <span class="person-meta">Benar {{ $peserta->jumlah_jawaban_benar }} / {{ $peserta->jumlah_jawaban_dikoreksi }} dikoreksi</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="monitor-meta">
                                    <span>Mulai: <strong>{{ $peserta->waktu_mulai?->format('H:i:s') ?: '-' }}</strong></span>
                                    <span>Selesai: <strong>{{ $peserta->waktu_selesai?->format('H:i:s') ?: '-' }}</strong></span>
                                    @if (! is_null($sisaMenit))
                                        <span class="monitor-time">Sisa sekitar {{ $sisaMenit }} menit</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="monitor-meta">
                                    <span>IP: {{ $peserta->ip_terakhir ?: '-' }}</span>
                                    <span title="{{ $peserta->user_agent_terakhir }}">{{ filled($peserta->user_agent_terakhir) ? str($peserta->user_agent_terakhir)->limit(52) : '-' }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada peserta yang sesuai filter monitoring.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($pesertaUjianCbt as $peserta)
                @php
                    $statusDasar = $peserta->status;
                    $sudahLogin = $statusDasar === 'aktif' && filled($peserta->ip_terakhir);
                    $labelStatus = match (true) {
                        $statusDasar === 'aktif' && ! $sudahLogin => 'Belum login',
                        $statusDasar === 'aktif' && $sudahLogin => 'Sudah login',
                        default => $peserta->labelStatus(),
                    };
                    $badgeStatus = match ($statusDasar) {
                        'sedang_mengerjakan' => 'badge-active',
                        'selesai' => 'badge-active',
                        'nonaktif', 'terblokir' => 'badge-inactive',
                        default => $sudahLogin ? 'badge-warning' : 'badge-muted',
                    };
                    $jawabanTersimpan = (int) $peserta->jumlah_jawaban_tersimpan;
                    $persenJawaban = $jumlahSoalTampil > 0 ? min(100, round(($jawabanTersimpan / $jumlahSoalTampil) * 100)) : 0;
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $peserta->anggotaKelas?->siswa?->nama_lengkap ?: '-' }}</p>
                            <p class="person-meta">{{ $peserta->kelasUjianCbt?->kelas?->nama ?: '-' }} - {{ $peserta->sesiUjianCbt?->nama ?: 'Tanpa sesi' }}</p>
                        </div>
                        <span class="badge {{ $badgeStatus }}">{{ $labelStatus }}</span>
                    </div>
                    <dl class="quick-facts">
                        <div><dt>Jawaban</dt><dd>{{ $jawabanTersimpan }} / {{ $jumlahSoalTampil }}</dd></div>
                        <div><dt>Ragu</dt><dd>{{ $peserta->jumlah_jawaban_ragu }}</dd></div>
                        <div><dt>Benar</dt><dd>{{ $peserta->jumlah_jawaban_benar }} / {{ $peserta->jumlah_jawaban_dikoreksi }}</dd></div>
                        <div><dt>Mulai</dt><dd>{{ $peserta->waktu_mulai?->format('H:i:s') ?: '-' }}</dd></div>
                        <div><dt>Selesai</dt><dd>{{ $peserta->waktu_selesai?->format('H:i:s') ?: '-' }}</dd></div>
                        <div><dt>IP</dt><dd>{{ $peserta->ip_terakhir ?: '-' }}</dd></div>
                        <div><dt>Progres</dt><dd>{{ $persenJawaban }}%</dd></div>
                    </dl>
                </article>
            @empty
                <div class="empty-state">Belum ada peserta yang sesuai filter monitoring.</div>
            @endforelse
        </div>
    </section>
@endsection

@push('scripts')
    @if ($autoRefresh)
        <script>
            window.setTimeout(() => window.location.reload(), 15000);
        </script>
    @endif
    <script>
        const tombolSalinToken = document.querySelector('[data-copy-token]');
        const tokenCbt = document.getElementById('tokenCbt');

        if (tombolSalinToken && tokenCbt) {
            tombolSalinToken.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(tokenCbt.textContent.trim());
                    tombolSalinToken.textContent = 'Token tersalin';
                    window.setTimeout(() => tombolSalinToken.textContent = 'Salin token', 1400);
                } catch (error) {
                    window.prompt('Salin token CBT:', tokenCbt.textContent.trim());
                }
            });
        }
    </script>
@endpush
