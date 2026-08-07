@extends('layouts.app')

@section('title', 'Rekap Absensi Harian - NUSA')

@section('content')
    @php
        $tanggalLabel = \Carbon\Carbon::parse($tanggal)->locale('id')->translatedFormat('d F Y');
        $formatJam = fn (?string $jam) => $jam ? substr($jam, 0, 5) : '-';
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $labelStatus = [
            'hadir' => 'Hadir',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'alfa' => 'Alfa',
        ];
        $badgeStatus = fn (string $status) => match ($status) {
            'hadir' => 'badge badge-active',
            'izin' => 'badge badge-warning',
            'sakit' => 'badge badge-muted',
            'alfa' => 'badge badge-danger',
            default => 'badge badge-muted',
        };
        $labelMasuk = [
            'tepat_waktu' => 'Tepat waktu',
            'terlambat' => 'Terlambat',
            'manual' => 'Manual',
        ];
        $labelPulang = [
            'normal' => 'Normal',
            'pulang_cepat' => 'Pulang cepat',
            'manual' => 'Manual',
        ];
        $statusPoinKeterlambatan = function ($absensi, $laporan): array {
            if ($laporan) {
                return match ($laporan->status_verifikasi) {
                    'diajukan', 'pemeriksaan_bk', 'perlu_klarifikasi', 'dikembalikan_bk' => ['Menunggu BK', 'badge badge-warning'],
                    'menunggu_pengesahan_wakil' => ['Menunggu Wakil Kesiswaan', 'badge badge-warning'],
                    'menunggu_persetujuan', 'disetujui_sebagian', 'perlu_musyawarah' => ['Menunggu keputusan BK', 'badge badge-warning'],
                    'disahkan' => ['Poin disahkan', 'badge badge-active'],
                    'ditetapkan_pembinaan' => ['Pembinaan tanpa poin', 'badge badge-active'],
                    'tidak_terbukti' => ['Tidak terbukti', 'badge badge-muted'],
                    'dibatalkan' => ['Dibatalkan', 'badge badge-muted'],
                    default => [$laporan->labelStatusVerifikasi(), 'badge badge-muted'],
                };
            }

            return match ($absensi?->status_poin_keterlambatan) {
                'toleransi' => ['Toleransi 0 poin', 'badge badge-muted'],
                'otomatis_nonaktif' => ['Otomatis nonaktif', 'badge badge-muted'],
                'laporan_dibatalkan' => ['Dibatalkan', 'badge badge-muted'],
                'laporan_tidak_terbukti' => ['Tidak terbukti', 'badge badge-muted'],
                default => [(int) ($absensi?->menit_terlambat ?? 0) > 0 ? 'Belum diproses' : '-', 'badge badge-muted'],
            };
        };
        $bolehLihatLaporanPoin = auth()->user()?->memilikiIzin(['bk.lihat', 'bk.kelola', 'poin_siswa.lapor', 'poin_siswa.lihat']) ?? false;
    @endphp

    <style>
        .wa-summary-modal {
            position: fixed;
            inset: 0;
            z-index: 80;
            display: grid;
            place-items: center;
            background: rgba(15, 23, 42, .58);
            padding: 18px;
        }

        .wa-summary-modal[hidden] {
            display: none;
        }

        .wa-summary-dialog {
            display: grid;
            width: min(100%, 760px);
            max-height: min(86vh, 780px);
            grid-template-rows: auto minmax(0, 1fr) auto;
            overflow: hidden;
            border: 1px solid rgba(21, 71, 122, .16);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 24px 80px rgba(15, 23, 42, .28);
        }

        .wa-summary-head,
        .wa-summary-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
        }

        .wa-summary-head {
            border-bottom: 1px solid var(--line);
            background: var(--primary);
            color: #fff;
        }

        .wa-summary-head .panel-title,
        .wa-summary-head .help-text {
            color: inherit;
        }

        .wa-summary-body {
            min-height: 0;
            overflow-y: auto;
            padding: 18px;
        }

        .wa-summary-textarea {
            min-height: 420px;
            font-family: ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", monospace;
            font-size: .88rem;
            line-height: 1.5;
            white-space: pre;
        }

        .wa-summary-foot {
            border-top: 1px solid var(--line);
            background: #fafafa;
        }

        .wa-copy-status {
            margin: 0;
            color: var(--primary-dark);
            font-size: .84rem;
            font-weight: 800;
        }

        @media (max-width: 620px) {
            .wa-summary-modal {
                align-items: center;
                padding: 10px;
            }

            .wa-summary-dialog {
                max-height: calc(100svh - 20px);
            }

            .wa-summary-foot {
                align-items: stretch;
                flex-direction: column;
                padding: 12px;
            }

            .wa-summary-head {
                align-items: center;
                padding: 12px;
            }

            .wa-summary-head .button {
                width: auto;
                flex: 0 0 auto;
            }

            .wa-summary-body {
                padding: 12px;
            }

            .wa-summary-foot .actions {
                display: grid;
                width: 100%;
                grid-template-columns: minmax(0, .8fr) minmax(0, 1.2fr);
            }

            .wa-summary-foot .button {
                min-width: 0;
            }

            .wa-summary-textarea {
                height: min(42svh, 320px);
                min-height: 220px;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Absensi</p>
            <h1 class="page-title">Rekap absensi harian</h1>
        </div>

        <div class="actions">
            @izin('absensi.scan')
                <a href="{{ route('scan-absensi.index') }}" target="_blank" rel="noopener" class="button button-primary">Scan absensi</a>
            @endizin
            <button
                type="button"
                class="button button-dark"
                data-wa-summary-open
                @disabled(! $bolehSalinRangkumanWhatsapp)
                title="{{ $bolehSalinRangkumanWhatsapp ? 'Buat pesan WA grup sesuai kelas yang dipilih' : 'Pilih kelas terlebih dahulu' }}"
            >
                Pesan WA Grup
            </button>
            @izin('absensi.pengaturan_kelola')
                <a href="{{ route('pengaturan-absensi.index') }}" class="button button-muted">Jam absensi</a>
            @endizin
            @if (auth()->user()?->memilikiIzin(['poin_siswa.pengaturan', 'poin_siswa.verifikasi_bk']))
                <form method="POST" action="{{ route('rekap-absensi-harian.proses-poin-keterlambatan') }}">
                    @csrf
                    <input type="hidden" name="tanggal" value="{{ $tanggal }}">
                    <input type="hidden" name="tahun_pelajaran_id" value="{{ $tahunPelajaranId }}">
                    <input type="hidden" name="kelas_id" value="{{ $kelasId }}">
                    <button class="button button-muted" @disabled(! $tahunPelajaranId)>Sinkronkan poin</button>
                </form>
            @endif
        </div>
    </div>

    <form action="{{ route('rekap-absensi-harian.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid filter-grid-wide">
            <div class="field">
                <label for="tanggal">Tanggal</label>
                <input id="tanggal" type="date" name="tanggal" value="{{ $tanggal }}" class="input">
            </div>

            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    @forelse ($daftarTahunPelajaran as $tahunPelajaran)
                        <option value="{{ $tahunPelajaran->id }}" @selected((string) $tahunPelajaranId === (string) $tahunPelajaran->id)>
                            {{ $tahunPelajaran->nama }}{{ $tahunPelajaran->aktif ? ' - aktif' : '' }}
                        </option>
                    @empty
                        <option value="">Belum ada tahun pelajaran</option>
                    @endforelse
                </select>
            </div>

            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select">
                    <option value="">{{ ($cakupanWaliKelas ?? false) ? 'Semua kelas wali' : 'Semua kelas' }}</option>
                    @foreach ($daftarKelas as $kelas)
                        <option value="{{ $kelas->id }}" @selected((string) $kelasId === (string) $kelas->id)>
                            {{ $kelas->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('rekap-absensi-harian.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    @if ($cakupanWaliKelas ?? false)
        <div class="alert">Rekap dan koreksi absensi dibatasi pada kelas yang Anda wali.</div>
    @endif

    @if (! ($cakupanWaliKelas ?? false) && ! $kelasId)
        <div class="alert">Untuk membuat pesan WA grup orang tua, pilih satu kelas terlebih dahulu. Rekap tabel tetap boleh menampilkan semua kelas.</div>
    @endif

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total siswa</p>
            <p class="stat-value">{{ $ringkasan['total'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Hadir</p>
            <p class="stat-value">{{ $ringkasan['hadir'] }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Alfa</p>
            <p class="stat-value">{{ $ringkasan['alfa'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Izin</p>
            <p class="stat-value">{{ $ringkasan['izin'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Sakit</p>
            <p class="stat-value">{{ $ringkasan['sakit'] }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Terlambat</p>
            <p class="stat-value">{{ $ringkasan['terlambat'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Pulang cepat</p>
            <p class="stat-value">{{ $ringkasan['pulang_cepat'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Belum pulang</p>
            <p class="stat-value">{{ $ringkasan['belum_pulang'] }}</p>
        </div>
    </div>

    <div id="wa-summary-modal" class="wa-summary-modal" hidden>
        <div class="wa-summary-dialog" role="dialog" aria-modal="true" aria-labelledby="wa-summary-title" tabindex="-1">
            <div class="wa-summary-head">
                <div>
                    <h2 id="wa-summary-title" class="panel-title">Pesan WA Grup Orang Tua</h2>
                    <p class="help-text" style="margin-top: 4px;">{{ $tanggalLabel }} - {{ $labelCakupan }}</p>
                </div>
                <button type="button" class="button button-muted button-sm" data-wa-summary-close>Tutup</button>
            </div>

            <div class="wa-summary-body">
                <label for="wa-summary-text" class="form-label">Rangkuman siap salin</label>
                <textarea id="wa-summary-text" class="textarea wa-summary-textarea" readonly>{{ $rangkumanWhatsapp }}</textarea>
                <p class="help-text">Silakan salin pesan ini lalu tempelkan ke grup WhatsApp orang tua siswa.</p>
            </div>

            <div class="wa-summary-foot">
                <p id="wa-copy-status" class="wa-copy-status" aria-live="polite"></p>
                <div class="actions">
                    <button type="button" class="button button-muted" data-wa-summary-close>Batal</button>
                    <button type="button" class="button button-primary" data-wa-summary-copy>Salin Pesan</button>
                </div>
            </div>
        </div>
    </div>

    @if ($daftarTahunPelajaran->isEmpty())
        <section class="panel panel-pad">
            <h2 class="panel-title">Tahun pelajaran belum tersedia</h2>
            <p class="help-text" style="margin-top: 8px;">Tambahkan tahun pelajaran dan kelas terlebih dahulu agar rekap absensi dapat ditampilkan.</p>
        </section>
    @else
        <section class="panel">
            <div class="panel-pad" style="border-bottom: 1px solid var(--line);">
                <h2 class="panel-title">{{ $tanggalLabel }}</h2>
                <p class="help-text" style="margin-top: 6px;">
                    {{ $kelasId ? 'Kelas ' . ($daftarKelas->firstWhere('id', (int) $kelasId)?->nama ?? '-') : (($cakupanWaliKelas ?? false) ? 'Semua kelas wali' : 'Semua kelas') }}
                </p>
            </div>

            <div class="desktop-only table-wrap">
                <table class="employee-table placement-table" style="min-width: 1320px;">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th>Status</th>
                            <th>Masuk</th>
                            <th>Pulang</th>
                            <th>Keterlambatan</th>
                            <th>Status poin</th>
                            <th>Catatan</th>
                            @izin('absensi.koreksi')
                                <th class="text-right">Aksi</th>
                            @endizin
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rekapAbsensi as $item)
                            @php
                                $anggota = $item['anggota_kelas'];
                                $absensi = $item['absensi'];
                                $status = $item['status_kehadiran'];
                                $laporanKeterlambatan = $item['laporan_keterlambatan'];
                                [$labelPoin, $badgePoin] = $statusPoinKeterlambatan($absensi, $laporanKeterlambatan);
                            @endphp
                            <tr>
                                <td data-label="No.">{{ $anggota->nomor_absen ?: '-' }}</td>
                                <td data-label="Siswa">
                                    <p class="person-name">{{ $anggota->siswa?->nama_lengkap ?: '-' }}</p>
                                    <p class="person-meta">NIS: {{ $teks($anggota->siswa?->nis) }} - NISN: {{ $teks($anggota->siswa?->nisn) }}</p>
                                </td>
                                <td data-label="Kelas">{{ $anggota->kelas?->nama ?: '-' }}</td>
                                <td data-label="Status">
                                    <span class="{{ $badgeStatus($status) }}">{{ $labelStatus[$status] ?? ucfirst($status) }}</span>
                                    @if ($item['status_sumber'] === 'inferensi')
                                        <p class="person-meta">Belum ada catatan</p>
                                    @else
                                        <p class="person-meta">{{ ucfirst($absensi?->sumber ?: 'catatan') }}</p>
                                    @endif
                                </td>
                                <td data-label="Masuk">
                                    <p class="person-name">{{ $formatJam($absensi?->jam_masuk) }}</p>
                                    <p class="person-meta">{{ $labelMasuk[$absensi?->status_masuk] ?? '-' }}</p>
                                </td>
                                <td data-label="Pulang">
                                    <p class="person-name">{{ $formatJam($absensi?->jam_pulang) }}</p>
                                    <p class="person-meta">{{ $labelPulang[$absensi?->status_pulang] ?? '-' }}</p>
                                </td>
                                <td data-label="Keterlambatan">
                                    <p class="person-name">{{ $item['terlambat'] > 0 ? $item['terlambat'] . ' menit' : '-' }}</p>
                                    <p class="person-meta">
                                        @if (! $absensi)
                                            -
                                        @elseif ($item['pulang_cepat'] > 0)
                                            Pulang cepat {{ $item['pulang_cepat'] }} menit
                                        @elseif ($item['belum_pulang'])
                                            Belum scan pulang
                                        @else
                                            Pulang normal
                                        @endif
                                    </p>
                                </td>
                                <td data-label="Status poin">
                                    <span class="{{ $badgePoin }}">{{ $labelPoin }}</span>
                                    @if ($laporanKeterlambatan)
                                        <p class="person-meta" style="margin-top: 5px;">{{ $laporanKeterlambatan->total_poin }} poin</p>
                                        @if ($bolehLihatLaporanPoin)
                                            <a href="{{ route('laporan-pembinaan-siswa.show', $laporanKeterlambatan) }}" class="text-link">Lihat laporan</a>
                                        @endif
                                    @elseif ((int) ($absensi?->poin_keterlambatan_terhitung ?? 0) > 0)
                                        <p class="person-meta" style="margin-top: 5px;">{{ $absensi->poin_keterlambatan_terhitung }} poin</p>
                                    @endif
                                </td>
                                <td data-label="Catatan">{{ $absensi?->catatan ?: '-' }}</td>
                                @izin('absensi.koreksi')
                                    <td data-label="Aksi">
                                        <div class="actions" style="justify-content: flex-end;">
                                            <a href="{{ route('rekap-absensi-harian.koreksi.edit', ['anggotaKelas' => $anggota, 'tanggal' => $tanggal]) }}" class="button button-dark button-sm">Koreksi</a>
                                        </div>
                                    </td>
                                @endizin
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()?->memilikiIzin('absensi.koreksi') ? 10 : 9 }}" class="empty-state">Belum ada siswa aktif pada pilihan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mobile-only mobile-list">
                @forelse ($rekapAbsensi as $item)
                    @php
                        $anggota = $item['anggota_kelas'];
                        $absensi = $item['absensi'];
                        $status = $item['status_kehadiran'];
                        $laporanKeterlambatan = $item['laporan_keterlambatan'];
                        [$labelPoin, $badgePoin] = $statusPoinKeterlambatan($absensi, $laporanKeterlambatan);
                    @endphp
                    <article class="mobile-card">
                        <div class="mobile-card-head">
                            <div>
                                <p class="person-name">{{ $anggota->siswa?->nama_lengkap ?: '-' }}</p>
                                <p class="person-meta">{{ $anggota->kelas?->nama ?: '-' }} - NISN {{ $teks($anggota->siswa?->nisn) }}</p>
                            </div>

                            <span class="{{ $badgeStatus($status) }}">{{ $labelStatus[$status] ?? ucfirst($status) }}</span>
                        </div>

                        <dl class="quick-facts">
                            <div>
                                <dt>Masuk</dt>
                                <dd>{{ $formatJam($absensi?->jam_masuk) }}</dd>
                            </div>
                            <div>
                                <dt>Pulang</dt>
                                <dd>{{ $formatJam($absensi?->jam_pulang) }}</dd>
                            </div>
                            <div>
                                <dt>Terlambat</dt>
                                <dd>{{ $item['terlambat'] > 0 ? $item['terlambat'] . ' menit' : '-' }}</dd>
                            </div>
                            <div>
                                <dt>Pulang cepat</dt>
                                <dd>{{ $item['pulang_cepat'] > 0 ? $item['pulang_cepat'] . ' menit' : '-' }}</dd>
                            </div>
                        </dl>

                        @if ($item['terlambat'] > 0 || $laporanKeterlambatan)
                            <div style="align-items: center; display: flex; flex-wrap: wrap; gap: 8px; margin-top: 13px;">
                                <span class="{{ $badgePoin }}">{{ $labelPoin }}</span>
                                @if ($laporanKeterlambatan)
                                    <span class="person-meta">{{ $laporanKeterlambatan->total_poin }} poin</span>
                                    @if ($bolehLihatLaporanPoin)
                                        <a href="{{ route('laporan-pembinaan-siswa.show', $laporanKeterlambatan) }}" class="text-link">Lihat laporan</a>
                                    @endif
                                @endif
                            </div>
                        @endif

                        @if ($absensi?->catatan)
                            <p class="help-text" style="margin-top: 12px;">{{ $absensi->catatan }}</p>
                        @endif

                        @izin('absensi.koreksi')
                            <div class="actions" style="margin-top: 14px;">
                                <a href="{{ route('rekap-absensi-harian.koreksi.edit', ['anggotaKelas' => $anggota, 'tanggal' => $tanggal]) }}" class="button button-dark">Koreksi</a>
                            </div>
                        @endizin
                    </article>
                @empty
                    <div class="empty-state">Belum ada siswa aktif pada pilihan ini.</div>
                @endforelse
            </div>
        </section>
    @endif

    <script>
        (() => {
            const modal = document.getElementById('wa-summary-modal');
            const dialog = modal.querySelector('.wa-summary-dialog');
            const textArea = document.getElementById('wa-summary-text');
            const statusText = document.getElementById('wa-copy-status');
            const openButtons = document.querySelectorAll('[data-wa-summary-open]');
            const closeButtons = document.querySelectorAll('[data-wa-summary-close]');
            const copyButton = document.querySelector('[data-wa-summary-copy]');

            const bukaModal = () => {
                modal.hidden = false;
                statusText.textContent = '';
                window.setTimeout(() => {
                    if (window.matchMedia('(max-width: 620px)').matches) {
                        dialog.focus({ preventScroll: true });
                        return;
                    }

                    textArea.focus({ preventScroll: true });
                }, 50);
            };

            const tutupModal = () => {
                modal.hidden = true;
            };

            const salinPesan = async () => {
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(textArea.value);
                    } else {
                        textArea.focus();
                        textArea.select();
                        document.execCommand('copy');
                    }

                    statusText.textContent = 'Pesan berhasil disalin.';
                } catch (error) {
                    statusText.textContent = 'Belum bisa menyalin otomatis. Tekan lama pada teks lalu pilih Salin.';
                }
            };

            openButtons.forEach((button) => button.addEventListener('click', bukaModal));
            closeButtons.forEach((button) => button.addEventListener('click', tutupModal));
            copyButton?.addEventListener('click', salinPesan);

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    tutupModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && ! modal.hidden) {
                    tutupModal();
                }
            });
        })();
    </script>
@endsection
