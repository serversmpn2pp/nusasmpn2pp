@extends('layouts.app')

@section('title', 'Profil Disiplin ' . $siswa->nama_lengkap . ' - NUSA')

@section('content')
    <style>
        .discipline-hero {
            align-items: center;
            background: var(--primary);
            color: #fff;
            display: grid;
            gap: 22px;
            grid-template-columns: auto minmax(0, 1fr) auto;
            margin-bottom: 18px;
            overflow: hidden;
            padding: 24px;
            position: relative;
        }

        .discipline-photo {
            background: #fff;
            border: 3px solid rgba(255, 255, 255, .85);
            border-radius: 8px;
            height: 104px;
            object-fit: cover;
            width: 84px;
        }

        .discipline-identity {
            min-width: 0;
        }

        .discipline-identity h2 {
            font-size: clamp(22px, 3vw, 32px);
            line-height: 1.12;
            margin: 2px 0 9px;
            overflow-wrap: anywhere;
        }

        .discipline-identity p {
            color: rgba(255, 255, 255, .8);
            margin: 4px 0 0;
        }

        .discipline-total {
            min-width: 125px;
            text-align: right;
        }

        .discipline-total span {
            color: rgba(255, 255, 255, .75);
            display: block;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .discipline-total strong {
            color: var(--secondary);
            display: block;
            font-size: 46px;
            line-height: 1;
            margin: 7px 0;
        }

        .discipline-metrics {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            margin-bottom: 20px;
        }

        .discipline-metric {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            min-width: 0;
            padding: 17px;
        }

        .discipline-metric span {
            color: var(--muted);
            display: block;
            font-size: 12px;
            font-weight: 700;
        }

        .discipline-metric strong {
            color: var(--primary-dark);
            display: block;
            font-size: 25px;
            line-height: 1.15;
            margin: 7px 0 4px;
            overflow-wrap: anywhere;
        }

        .discipline-metric small {
            color: var(--muted);
            display: block;
            line-height: 1.4;
        }

        .discipline-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 18px;
            overflow-x: auto;
            padding-bottom: 3px;
        }

        .discipline-nav a {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 7px;
            color: var(--primary-dark);
            flex: 0 0 auto;
            font-size: 13px;
            font-weight: 800;
            padding: 9px 12px;
            text-decoration: none;
        }

        .discipline-nav a:hover {
            border-color: var(--primary);
        }

        .discipline-grid {
            display: grid;
            gap: 18px;
            grid-template-columns: minmax(0, 1.5fr) minmax(280px, .8fr);
            margin-bottom: 20px;
        }

        .discipline-section-head {
            align-items: flex-start;
            display: flex;
            gap: 16px;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .discipline-section-head h2 {
            font-size: 19px;
            margin: 0 0 4px;
        }

        .discipline-section-head p {
            color: var(--muted);
            font-size: 13px;
            margin: 0;
        }

        .discipline-progress {
            background: #e6ebf0;
            border-radius: 999px;
            height: 10px;
            margin: 14px 0 9px;
            overflow: hidden;
        }

        .discipline-progress span {
            background: var(--secondary);
            display: block;
            height: 100%;
        }

        .discipline-threshold {
            align-items: baseline;
            display: flex;
            gap: 12px;
            justify-content: space-between;
        }

        .discipline-threshold strong {
            color: var(--primary-dark);
        }

        .discipline-threshold span {
            color: var(--muted);
            font-size: 12px;
            text-align: right;
        }

        .trend-chart {
            display: grid;
            gap: 9px;
            margin-top: 18px;
        }

        .trend-row {
            align-items: center;
            display: grid;
            gap: 10px;
            grid-template-columns: 72px minmax(0, 1fr) 40px;
        }

        .trend-label,
        .trend-value {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .trend-value {
            color: var(--primary-dark);
            text-align: right;
        }

        .trend-track {
            background: #e7edf3;
            border-radius: 999px;
            height: 8px;
            overflow: hidden;
        }

        .trend-fill {
            background: var(--primary);
            display: block;
            height: 100%;
            min-width: 0;
        }

        .attention-list {
            display: grid;
            gap: 12px;
        }

        .attention-item {
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
        }

        .attention-item:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .attention-item strong {
            color: var(--primary-dark);
            display: block;
            margin-bottom: 4px;
        }

        .attention-item p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
            margin: 0;
        }

        .discipline-section {
            margin-bottom: 20px;
            scroll-margin-top: 90px;
        }

        .transaction-points {
            font-size: 18px;
            font-weight: 900;
            white-space: nowrap;
        }

        .transaction-points.is-minus {
            color: #14805e;
        }

        .transaction-points.is-plus {
            color: #b3342f;
        }

        .badge-info {
            background: #e6f2fa;
            color: #175b88;
        }

        .profile-warning-important {
            background: #fee9e7;
            color: #a72c27;
        }

        .profile-warning-normal {
            background: #fff5cf;
            color: #795b00;
        }

        @media (max-width: 1180px) {
            .discipline-metrics {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 980px) {
            .discipline-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .discipline-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 680px) {
            .discipline-hero {
                align-items: start;
                grid-template-columns: auto minmax(0, 1fr);
                padding: 18px;
            }

            .discipline-photo {
                height: 88px;
                width: 70px;
            }

            .discipline-total {
                grid-column: 1 / -1;
                text-align: left;
            }

            .discipline-total strong {
                font-size: 38px;
            }

            .discipline-metrics {
                grid-template-columns: 1fr;
            }

            .discipline-section-head {
                flex-direction: column;
            }

            .trend-row {
                grid-template-columns: 62px minmax(0, 1fr) 34px;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Profil Disiplin Siswa</h1>
            <p class="page-subtitle">Poin resmi dan riwayat penanganan ditampilkan terpisah dari proses yang belum disahkan.</p>
        </div>
        <div class="actions">
            <a href="{{ route('dokumen-poin-siswa.laporan', ['siswa' => $siswa, 'tahun_pelajaran_id' => $tahunPelajaranId]) }}"
                class="button button-primary" target="_blank" rel="noopener">Cetak Laporan</a>
            <a href="{{ route('dokumen-poin-siswa.surat', ['siswa' => $siswa, 'tahun_pelajaran_id' => $tahunPelajaranId]) }}"
                class="button button-dark">Buat Surat</a>
            <a href="{{ route('rekap-poin-siswa.index', ['tahun_pelajaran_id' => $tahunPelajaranId]) }}"
                class="button button-muted">Kembali</a>
        </div>
    </div>

    <form method="GET" class="panel panel-pad" style="margin-bottom: 16px;">
        <div class="form-grid">
            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    @forelse ($daftarTahunPelajaran as $tahun)
                        <option value="{{ $tahun->id }}" @selected((string) $tahunPelajaranId === (string) $tahun->id)>
                            {{ $tahun->nama }}{{ $tahun->aktif ? ' (aktif)' : '' }}
                        </option>
                    @empty
                        <option value="">Belum ada tahun pelajaran</option>
                    @endforelse
                </select>
            </div>
            <div class="field" style="align-self: end;">
                <button class="button button-dark">Tampilkan</button>
            </div>
        </div>
    </form>

    <section class="panel discipline-hero">
        <img class="discipline-photo"
            src="{{ $siswa->foto ? asset('storage/' . $siswa->foto) : asset('images/kartu-pelajar/default-user.png') }}"
            alt="Foto {{ $siswa->nama_lengkap }}">
        <div class="discipline-identity">
            <span class="{{ $indikator['kelas'] }}">{{ $indikator['label'] }}</span>
            <h2>{{ $siswa->nama_lengkap }}</h2>
            <p>
                {{ $anggotaKelas?->kelas?->nama ?: 'Belum ditempatkan di kelas' }}
                · NISN {{ $siswa->nisn ?: '-' }}
                · NIS {{ $siswa->nis ?: '-' }}
            </p>
            <p>Guru Wali: {{ $penugasanGuruWali?->guruWali?->nama_lengkap ?: '-' }}</p>
        </div>
        <div class="discipline-total">
            <span>Total poin resmi</span>
            <strong>{{ $totalPoin }}</strong>
            <small>{{ $tahunPelajaran?->nama ?: 'Semua periode' }}</small>
        </div>
    </section>

    <section class="discipline-metrics" aria-label="Ringkasan profil disiplin">
        <article class="discipline-metric">
            <span>Peringatan aktif</span>
            <strong>{{ $peringatanAktif->count() }}</strong>
            <small>{{ $peringatanAktif->where('tingkat', 'penting')->count() }} berstatus penting</small>
        </article>
        <article class="discipline-metric">
            <span>Ambang berikutnya</span>
            <strong>{{ $indikator['aturanBerikutnya']?->batas_poin ?? '-' }}</strong>
            <small>{{ $indikator['aturanBerikutnya']?->nama ?? 'Ambang tertinggi telah tercapai' }}</small>
        </article>
        <article class="discipline-metric">
            <span>Jarak menuju ambang</span>
            <strong>{{ $indikator['jarak'] ?? '-' }}</strong>
            <small>{{ $indikator['jarak'] !== null ? 'poin lagi' : 'Tidak ada ambang berikutnya' }}</small>
        </article>
        <article class="discipline-metric">
            <span>Laporan belum disahkan</span>
            <strong>{{ $laporanMenunggu->count() }}</strong>
            <small>Potensi {{ $poinDalamProses }} poin, belum masuk saldo resmi</small>
        </article>
        <article class="discipline-metric">
            <span>Sanksi aktif</span>
            <strong>{{ $jumlahSanksiAktif }}</strong>
            <small>Menunggu atau sedang dilaksanakan</small>
        </article>
    </section>

    <nav class="discipline-nav" aria-label="Bagian profil disiplin">
        <a href="#perkembangan">Perkembangan</a>
        <a href="#peringatan">Peringatan Aktif</a>
        <a href="#tindak-lanjut">Tindak Lanjut</a>
        <a href="#transaksi-poin">Transaksi Poin</a>
        <a href="#laporan">Laporan</a>
        <a href="#sanksi">Sanksi</a>
        <a href="#pengurangan">Pengurangan</a>
        <a href="#keterlambatan">Keterlambatan</a>
    </nav>

    <div class="discipline-grid" id="perkembangan">
        <section class="panel panel-pad">
            <div class="discipline-section-head">
                <div>
                    <h2>Perkembangan poin bulanan</h2>
                    <p>Saldo kumulatif hanya berasal dari transaksi yang sudah resmi.</p>
                </div>
            </div>

            <div class="discipline-progress" aria-hidden="true">
                <span style="width: {{ $indikator['persentase'] }}%;"></span>
            </div>
            <div class="discipline-threshold">
                <strong>{{ $totalPoin }} poin saat ini</strong>
                <span>
                    @if ($indikator['aturanBerikutnya'])
                        {{ $indikator['jarak'] }} poin menuju {{ $indikator['aturanBerikutnya']->nama }}
                    @else
                        Tidak ada ambang sanksi berikutnya
                    @endif
                </span>
            </div>

            <div class="trend-chart">
                @foreach ($perkembanganBulanan as $bulan)
                    <div class="trend-row">
                        <span class="trend-label">{{ $bulan['label'] }}</span>
                        <span class="trend-track" aria-hidden="true">
                            <span class="trend-fill"
                                style="width: {{ min(100, (int) round(($bulan['saldo'] / $maksSaldoBulanan) * 100)) }}%;"></span>
                        </span>
                        <span class="trend-value">{{ $bulan['saldo'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <aside class="panel panel-pad">
            <div class="discipline-section-head">
                <div>
                    <h2>Perlu diperhatikan</h2>
                    <p>Ringkasan tindakan yang masih terbuka.</p>
                </div>
            </div>
            <div class="attention-list">
                <div class="attention-item">
                    <strong>{{ $peringatanAktif->count() }} peringatan dini aktif</strong>
                    <p>{{ $peringatanAktif->where('tingkat', 'penting')->count() }} peringatan memerlukan perhatian segera.</p>
                </div>
                <div class="attention-item">
                    <strong>{{ $pendampinganAktif ? '1 tindak lanjut berjalan' : 'Tidak ada tindak lanjut aktif' }}</strong>
                    <p>{{ $pendampinganAktif ? $pendampinganAktif->labelJenis().' bersama '.($pendampinganAktif->petugasPegawai?->nama_lengkap ?: 'petugas yang ditunjuk').'.' : 'Tindak lanjut dapat dimulai dari peringatan aktif siswa.' }}</p>
                </div>
                <div class="attention-item">
                    <strong>{{ $laporanMenunggu->count() }} laporan menunggu verifikasi</strong>
                    <p>{{ $poinDalamProses }} potensi poin belum memengaruhi saldo sampai BK menetapkan keputusan.</p>
                </div>
                <div class="attention-item">
                    <strong>{{ $jumlahSanksiAktif }} sanksi aktif</strong>
                    <p>Pastikan penugasan, bukti pelaksanaan, dan hasil penanganan sudah dilengkapi.</p>
                </div>
                <div class="attention-item">
                    <strong>{{ $rekapKeterlambatan['jumlah'] }} kali terlambat</strong>
                    <p>Total {{ $rekapKeterlambatan['total_menit'] }} menit pada tahun pelajaran ini.</p>
                </div>
            </div>
        </aside>
    </div>

    <section class="panel discipline-section" id="peringatan">
        <div class="panel-pad discipline-section-head">
            <div>
                <h2>Peringatan dini aktif</h2>
                <p>Deteksi otomatis yang masih memerlukan pemantauan atau tindak lanjut.</p>
            </div>
            <a class="button button-muted button-sm" href="{{ route('peringatan-dini-siswa.index', ['tahun_pelajaran_id' => $tahunPelajaranId, 'kata_kunci' => $siswa->nisn ?: $siswa->nama_lengkap]) }}">Buka pusat peringatan</a>
        </div>
        <div class="table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Jenis</th>
                        <th>Alasan</th>
                        <th>Tingkat</th>
                        <th>Siklus</th>
                        <th>Terakhir terdeteksi</th>
                        @izin('poin_siswa.pendampingan_kelola')<th class="text-right">Aksi</th>@endizin
                    </tr>
                </thead>
                <tbody>
                    @forelse($peringatanAktif as $peringatan)
                        <tr>
                            <td><p class="person-name">{{ $peringatan->labelJenis() }}</p></td>
                            <td>{{ $peringatan->pesan }}</td>
                            <td>
                                <span class="badge {{ $peringatan->tingkat === 'penting' ? 'profile-warning-important' : 'profile-warning-normal' }}">
                                    {{ $peringatan->labelTingkat() }}
                                </span>
                            </td>
                            <td>{{ $peringatan->siklus }}</td>
                            <td>{{ $peringatan->terakhir_terdeteksi_pada?->translatedFormat('d M Y, H:i') }}</td>
                            @izin('poin_siswa.pendampingan_kelola')
                                <td class="text-right">
                                    @if($pendampinganAktif)
                                        <a class="button button-dark button-sm" href="{{ route('pendampingan-siswa.edit', $pendampinganAktif) }}">Lanjutkan</a>
                                    @else
                                        <a class="button button-dark button-sm" href="{{ route('pendampingan-siswa.create', ['peringatan_id' => $peringatan->id]) }}">Tindak Lanjuti</a>
                                    @endif
                                </td>
                            @endizin
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()?->memilikiIzin('poin_siswa.pendampingan_kelola') ? 6 : 5 }}" class="empty-state">Tidak ada peringatan dini aktif untuk siswa ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel discipline-section" id="tindak-lanjut">
        <div class="panel-pad discipline-section-head">
            <div>
                <h2>Riwayat tindak lanjut</h2>
                <p>Catatan pendampingan siswa yang sedang diproses maupun sudah selesai.</p>
            </div>
            @izin('poin_siswa.pendampingan_kelola')
                @if($pendampinganAktif)
                    <a class="button button-primary button-sm" href="{{ route('pendampingan-siswa.edit', $pendampinganAktif) }}">Lanjutkan</a>
                @else
                    <a class="button button-primary button-sm" href="{{ route('pendampingan-siswa.create', ['siswa_id' => $siswa->id, 'tahun_pelajaran_id' => $tahunPelajaranId]) }}">Mulai Tindak Lanjut</a>
                @endif
            @endizin
        </div>
        <div class="table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Tindakan</th>
                        <th>Petugas</th>
                        <th>Catatan/Hasil</th>
                        <th>Status</th>
                        @izin('poin_siswa.pendampingan_kelola')<th class="text-right">Aksi</th>@endizin
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarPendampingan as $item)
                        <tr>
                            <td>{{ $item->tanggal_tindak_lanjut?->translatedFormat('d M Y') }}</td>
                            <td>
                                <p class="person-name">{{ $item->labelJenis() }}</p>
                                @if($item->peringatanDiniSiswa)
                                    <p class="person-meta">Dari {{ $item->peringatanDiniSiswa->labelJenis() }}</p>
                                @endif
                            </td>
                            <td>{{ $item->petugasPegawai?->nama_lengkap ?: '-' }}</td>
                            <td>{{ str($item->hasil ?: $item->catatan)->limit(150) }}</td>
                            <td>
                                <span class="badge {{ $item->status === 'selesai' ? 'badge-active' : 'badge-warning' }}">{{ $item->labelStatus() }}</span>
                            </td>
                            @izin('poin_siswa.pendampingan_kelola')
                                <td class="text-right"><a class="button button-muted button-sm" href="{{ route('pendampingan-siswa.edit', $item) }}">Lihat/Edit</a></td>
                            @endizin
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()?->memilikiIzin('poin_siswa.pendampingan_kelola') ? 6 : 5 }}" class="empty-state">Belum ada tindak lanjut untuk siswa ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel discipline-section" id="transaksi-poin">
        <div class="panel-pad discipline-section-head">
            <div>
                <h2>Transaksi poin resmi</h2>
                <p>Pelanggaran yang disahkan, pembatalan, dan pengurangan poin yang disetujui.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Keterangan</th>
                        <th>Poin</th>
                        <th class="text-right">Sumber</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transaksiPoin as $transaksi)
                        <tr>
                            <td>{{ $transaksi->tercatat_pada?->translatedFormat('d M Y, H:i') }}</td>
                            <td>
                                <span class="{{ $transaksi->poin < 0 ? 'badge badge-active' : 'badge badge-warning' }}">
                                    {{ str($transaksi->jenis)->replace('_', ' ')->title() }}
                                </span>
                            </td>
                            <td>{{ $transaksi->keterangan }}</td>
                            <td>
                                <span class="transaction-points {{ $transaksi->poin < 0 ? 'is-minus' : 'is-plus' }}">
                                    {{ $transaksi->poin > 0 ? '+' : '' }}{{ $transaksi->poin }}
                                </span>
                            </td>
                            <td class="text-right">
                                @if ($transaksi->laporanPembinaanSiswa)
                                    <a class="button button-muted button-sm"
                                        href="{{ route('laporan-pembinaan-siswa.show', $transaksi->laporanPembinaanSiswa) }}">
                                        {{ $transaksi->laporanPembinaanSiswa->nomor_laporan }}
                                    </a>
                                @elseif ($transaksi->penguranganPoinSiswa)
                                    <span class="person-meta">{{ $transaksi->penguranganPoinSiswa->jenis_kegiatan }}</span>
                                @else
                                    <span class="person-meta">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Belum ada transaksi poin resmi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel discipline-section" id="laporan">
        <div class="panel-pad discipline-section-head">
            <div>
                <h2>Riwayat laporan</h2>
                <p>Termasuk laporan pembinaan non-poin dan pelanggaran yang masih dalam proses.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Tanggal & nomor</th>
                        <th>Jenis</th>
                        <th>Kategori / butir</th>
                        <th>Status</th>
                        <th>Poin laporan</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($laporanPembinaan as $laporan)
                        @php
                            $statusKelas = match ($laporan->status_verifikasi) {
                                'disahkan' => 'badge badge-danger',
                                'tidak_terbukti', 'dibatalkan' => 'badge badge-muted',
                                'tidak_perlu' => 'badge badge-info',
                                default => 'badge badge-warning',
                            };
                        @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $laporan->tanggal_kejadian?->translatedFormat('d M Y') }}</p>
                                <p class="person-meta">{{ $laporan->nomor_laporan }}</p>
                            </td>
                            <td>{{ $laporan->labelJenisLaporan() }}</td>
                            <td>
                                <p class="person-name">{{ $laporan->kategoriPembinaanSiswa?->nama ?: '-' }}</p>
                                @if ($laporan->butirPelanggaranLaporan->isNotEmpty())
                                    <p class="person-meta">
                                        {{ $laporan->butirPelanggaranLaporan->pluck('kode_pelanggaran')->join(', ') }}
                                    </p>
                                @endif
                            </td>
                            <td><span class="{{ $statusKelas }}">{{ $laporan->labelStatusVerifikasi() }}</span></td>
                            <td>{{ $laporan->total_poin }}</td>
                            <td class="text-right">
                                <a class="button button-muted button-sm"
                                    href="{{ route('laporan-pembinaan-siswa.show', $laporan) }}">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Belum ada riwayat laporan pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel discipline-section" id="sanksi">
        <div class="panel-pad discipline-section-head">
            <div>
                <h2>Riwayat sanksi</h2>
                <p>Ambang yang pernah terpicu dan status pelaksanaannya.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Terpicu</th>
                        <th>Sanksi</th>
                        <th>Poin saat terpicu</th>
                        <th>Petugas</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarSanksi as $sanksi)
                        @php
                            $kelasStatusSanksi = match ($sanksi->status) {
                                'selesai' => 'badge badge-active',
                                'dibatalkan' => 'badge badge-muted',
                                'diproses' => 'badge badge-warning',
                                default => 'badge badge-danger',
                            };
                        @endphp
                        <tr>
                            <td>{{ $sanksi->terpicu_pada?->translatedFormat('d M Y') }}</td>
                            <td>
                                <p class="person-name">{{ $sanksi->aturanSanksiPoin?->nama }}</p>
                                <p class="person-meta">Ambang {{ $sanksi->aturanSanksiPoin?->batas_poin }} poin</p>
                            </td>
                            <td>{{ $sanksi->poin_saat_terpicu }}</td>
                            <td>{{ $sanksi->petugasPegawai?->nama_lengkap ?: '-' }}</td>
                            <td><span class="{{ $kelasStatusSanksi }}">{{ $sanksi->labelStatus() }}</span></td>
                            <td class="text-right">
                                <a class="button button-muted button-sm"
                                    href="{{ route('sanksi-poin-siswa.show', $sanksi) }}">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Belum ada sanksi yang terpicu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel discipline-section" id="pengurangan">
        <div class="panel-pad discipline-section-head">
            <div>
                <h2>Pengurangan poin</h2>
                <p>Riwayat usulan apresiasi atau kegiatan positif siswa.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kegiatan</th>
                        <th>Poin diajukan</th>
                        <th>Status</th>
                        <th>Disetujui oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($penguranganPoin as $pengurangan)
                        @php
                            $kelasPengurangan = match ($pengurangan->status) {
                                'disetujui' => 'badge badge-active',
                                'ditolak' => 'badge badge-danger',
                                default => 'badge badge-warning',
                            };
                        @endphp
                        <tr>
                            <td>{{ $pengurangan->tanggal_kegiatan?->translatedFormat('d M Y') }}</td>
                            <td>
                                <p class="person-name">{{ $pengurangan->jenis_kegiatan }}</p>
                                <p class="person-meta">{{ $pengurangan->deskripsi ?: '-' }}</p>
                            </td>
                            <td>{{ $pengurangan->poin_pengurangan }}</td>
                            <td>
                                <span class="{{ $kelasPengurangan }}">
                                    {{ \App\Models\PenguranganPoinSiswa::DAFTAR_STATUS[$pengurangan->status] ?? str($pengurangan->status)->title() }}
                                </span>
                            </td>
                            <td>{{ $pengurangan->disetujuiOlehPegawai?->nama_lengkap ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Belum ada usulan pengurangan poin.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel discipline-section" id="keterlambatan">
        <div class="panel-pad discipline-section-head">
            <div>
                <h2>Riwayat keterlambatan terbaru</h2>
                <p>
                    {{ $rekapKeterlambatan['jumlah'] }} kejadian ·
                    {{ $rekapKeterlambatan['total_menit'] }} menit pada periode ini.
                </p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kelas</th>
                        <th>Jam masuk</th>
                        <th>Keterlambatan</th>
                        <th>Status poin otomatis</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarKeterlambatan as $absensi)
                        <tr>
                            <td>{{ $absensi->tanggal?->translatedFormat('d M Y') }}</td>
                            <td>{{ $absensi->kelas?->nama ?: '-' }}</td>
                            <td>{{ $absensi->jam_masuk ? substr((string) $absensi->jam_masuk, 0, 5) : '-' }}</td>
                            <td><strong>{{ $absensi->menit_terlambat }} menit</strong></td>
                            <td>
                                <span class="{{ $absensi->status_poin_keterlambatan === 'diproses' ? 'badge badge-active' : 'badge badge-muted' }}">
                                    {{ str($absensi->status_poin_keterlambatan ?: 'belum diproses')->replace('_', ' ')->title() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Tidak ada keterlambatan pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
