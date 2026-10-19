@php
    $angka = fn ($nilai) => number_format($nilai, 0, ',', '.');
    $jam = fn ($nilai) => $nilai ? substr($nilai, 0, 5) : '-';
    $labelDashboard = $modeOrangTua ? 'Dashboard Orang Tua' : 'Dashboard Siswa';
    $urlNilai = $modeOrangTua ? route('akademik-anak.index', ['tab' => 'nilai']) : route('nilai-saya.index');
    $urlPoin = $modeOrangTua ? route('pembinaan-poin-anak.index') : route('progress-kasus-siswa.index');
    $labelKehadiranHariIni = match ($absensiHariIni?->status_kehadiran) {
        'hadir' => $absensiHariIni->menit_terlambat > 0
            ? 'Hadir, terlambat '.$absensiHariIni->menit_terlambat.' menit'
            : ($absensiHariIni->jam_masuk ? 'Hadir tepat waktu' : 'Hadir'),
        'sakit' => 'Sakit', 'izin' => 'Izin', 'alfa' => 'Alfa',
        default => $kodeHari === 'minggu' ? 'Hari Minggu' : 'Belum tercatat',
    };
    $warnaPresensi = $absensiHariIni?->status_kehadiran === 'hadir' && ! $absensiHariIni->menit_terlambat ? 'teal' : 'amber';
@endphp

<div class="family-home account-home" data-family-dashboard data-family-mode="{{ $modeOrangTua ? 'orang-tua' : 'siswa' }}">
    @if (session('berhasil'))
        <div class="alert" role="status">{{ session('berhasil') }}</div>
    @endif

    @if (! $siswaLogin)
        <section class="fh-unlinked" aria-labelledby="fh-unlinked-title">
            <i data-lucide="user-round-search" aria-hidden="true"></i>
            <p class="ph-eyebrow">{{ $labelDashboard }}</p>
            <h1 id="fh-unlinked-title">Akun belum terhubung ke data siswa</h1>
            <p>Hubungi operator sekolah agar akun ini dihubungkan dengan data siswa yang benar.</p>
        </section>
    @else
        <header class="ph-heading fh-heading">
            <div class="fh-identity">
                <img class="fh-photo" src="{{ $urlFotoSiswa }}" alt="Foto {{ $siswaLogin->nama_lengkap }}" width="66" height="82">
                <div>
                    <p class="ph-eyebrow"><i data-lucide="{{ $modeOrangTua ? 'heart-handshake' : 'graduation-cap' }}" aria-hidden="true"></i>{{ $labelDashboard }}</p>
                    <h1>{{ $siswaLogin->nama_lengkap }}</h1>
                    <p class="ph-date">{{ $kelasAktif?->nama ?: 'Belum ditempatkan di kelas' }} &middot; NISN {{ $siswaLogin->nisn ?: '-' }} &middot; {{ $tahunPelajaranAktif?->nama ?: 'Tahun pelajaran belum tersedia' }}</p>
                </div>
            </div>
            <div class="ph-heading-side">
                <span class="ph-badge {{ $warnaPresensi }}"><i data-lucide="{{ $absensiHariIni ? 'badge-check' : 'clock-3' }}" aria-hidden="true"></i>{{ $labelKehadiranHariIni }}</span>
                <small>{{ $hariIni->copy()->locale('id')->translatedFormat('l, d F Y') }}</small>
            </div>
        </header>

        <section class="ph-stats" aria-label="Ringkasan {{ $modeOrangTua ? 'anak' : 'siswa' }}">
            @foreach ([
                ['label' => 'Hadir', 'nilai' => $ringkasanKehadiran['hadir'], 'unit' => 'hari', 'note' => $labelBulan, 'ikon' => 'calendar-check-2'],
                ['label' => 'Terlambat', 'nilai' => $ringkasanKehadiran['terlambat'], 'unit' => 'kali', 'note' => $angka($ringkasanKehadiran['menit_terlambat']).' menit selama '.$labelBulan, 'ikon' => 'alarm-clock'],
                ['label' => 'Publikasi nilai', 'nilai' => $jumlahNilaiDipublikasikan, 'unit' => 'publikasi', 'note' => 'Nilai yang sudah diterbitkan', 'ikon' => 'notebook-pen'],
                ['label' => 'Poin resmi', 'nilai' => $ringkasanPoin['total'], 'unit' => 'poin', 'note' => $ringkasanPoin['total'] > 0 ? 'Catatan pembinaan tahun ini' : 'Tidak ada poin aktif', 'ikon' => 'shield-check'],
            ] as $item)
                <article class="ph-stat">
                    <div class="ph-stat-label">{{ $item['label'] }}<i data-lucide="{{ $item['ikon'] }}" aria-hidden="true"></i></div>
                    <p><strong>{{ $angka($item['nilai']) }}</strong><span>{{ $item['unit'] }}</span></p>
                    <small>{{ $item['note'] }}</small>
                </article>
            @endforeach
        </section>

        <div class="ph-row">
            <section class="ph-section" id="fh-jadwal" aria-labelledby="fh-jadwal-title">
                <header class="ph-section-head">
                    <h2 id="fh-jadwal-title"><i data-lucide="calendar-days" aria-hidden="true"></i>Jadwal Pelajaran Hari Ini</h2>
                    @if ($modeOrangTua)
                        <a class="ph-link" href="{{ route('akademik-anak.index', ['tab' => 'jadwal']) }}">Lihat jadwal<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
                    @else
                        <span class="ph-badge blue">{{ $angka($jadwalHariIni->count()) }} jam pelajaran</span>
                    @endif
                </header>
                <div class="ph-section-body">
                    @forelse ($jadwalHariIni as $jadwal)
                        @php
                            $mulai = $jam($jadwal->jamPelajaran?->jam_mulai);
                            $selesai = $jam($jadwal->jamPelajaran?->jam_selesai);
                            $waktuSekarang = $hariIni->format('H:i');
                            $statusJadwal = $waktuSekarang > $selesai ? 'Selesai' : ($waktuSekarang >= $mulai ? 'Berlangsung' : 'Berikutnya');
                        @endphp
                        <div class="ph-schedule">
                            <div class="ph-time">{{ $mulai }}<small>{{ $selesai }}</small></div>
                            <div><strong>{{ $jadwal->mataPelajaranTerjadwal()?->nama ?: 'Mata pelajaran belum ditentukan' }}</strong><p>{{ $jadwal->guruMataPelajaran?->pegawai?->nama_lengkap ?: 'Guru belum ditentukan' }}</p></div>
                            <span class="ph-badge {{ $statusJadwal === 'Berlangsung' ? 'teal' : 'blue' }}">{{ $statusJadwal }}</span>
                        </div>
                    @empty
                        <div class="ph-empty"><i data-lucide="calendar-check" aria-hidden="true"></i><p>{{ $kelasAktif ? 'Tidak ada jadwal pelajaran untuk hari ini.' : 'Kelas aktif belum ditentukan.' }}</p></div>
                    @endforelse
                </div>
            </section>

            <section class="ph-section" aria-labelledby="fh-belajar-title">
                <header class="ph-section-head"><h2 id="fh-belajar-title"><i data-lucide="{{ $modeOrangTua ? 'book-open-check' : 'notebook-tabs' }}" aria-hidden="true"></i>{{ $modeOrangTua ? 'Perkembangan anak' : 'Ringkasan belajar' }}</h2></header>
                <div class="ph-section-body">
                    @if (! $modeOrangTua)
                        <a class="ph-task" href="{{ route('ujian-saya.index') }}">
                            <span class="ph-task-icon"><i data-lucide="clipboard-pen-line" aria-hidden="true"></i></span>
                            <span class="ph-task-text"><strong>Ujian Saya</strong><small>{{ $angka($ringkasanUjianSaya['aktif']) }} aktif &middot; {{ $angka($ringkasanUjianSaya['akan_datang']) }} akan datang &middot; {{ $angka($ringkasanUjianSaya['selesai']) }} selesai</small></span>
                            <i data-lucide="chevron-right" aria-hidden="true"></i>
                        </a>
                    @else
                        <a class="ph-task" href="{{ route('presensi-anak.index') }}">
                            <span class="ph-task-icon"><i data-lucide="calendar-check-2" aria-hidden="true"></i></span>
                            <span class="ph-task-text"><strong>Presensi anak</strong><small>{{ $labelKehadiranHariIni }} &middot; masuk {{ $jam($absensiHariIni?->jam_masuk) }}</small></span>
                            <i data-lucide="chevron-right" aria-hidden="true"></i>
                        </a>
                    @endif
                    <a class="ph-task" href="{{ $urlNilai }}">
                        <span class="ph-task-icon"><i data-lucide="notebook-pen" aria-hidden="true"></i></span>
                        <span class="ph-task-text"><strong>{{ $modeOrangTua ? 'Nilai Anak' : 'Nilai Saya' }}</strong><small>{{ $jumlahNilaiDipublikasikan > 0 ? $angka($jumlahNilaiDipublikasikan).' publikasi nilai tersedia' : 'Belum ada nilai yang dipublikasikan' }}</small></span>
                        <i data-lucide="chevron-right" aria-hidden="true"></i>
                    </a>
                    <a class="ph-task" href="{{ route('rapor-sts-saya.index') }}">
                        <span class="ph-task-icon"><i data-lucide="file-check-2" aria-hidden="true"></i></span>
                        <span class="ph-task-text"><strong>Rapor STS</strong><small>{{ $jumlahRaporDashboard > 0 ? $angka($jumlahRaporDashboard).' rapor telah dipublikasikan' : 'Belum ada rapor yang dipublikasikan untuk akun ini' }}</small></span>
                        <i data-lucide="chevron-right" aria-hidden="true"></i>
                    </a>
                </div>
            </section>
        </div>

        <div class="ph-row">
            @include('beranda.partials.grafik-kedatangan-keluarga')

            <section class="ph-section" aria-labelledby="fh-presensi-title">
                <header class="ph-section-head"><h2 id="fh-presensi-title"><i data-lucide="scan-line" aria-hidden="true"></i>Presensi hari ini</h2><span class="ph-badge {{ $warnaPresensi }}">{{ $absensiHariIni ? 'Tercatat' : 'Belum tercatat' }}</span></header>
                <div class="ph-presensi">
                    <div class="ph-presensi-main"><span>Jam masuk</span><strong>{{ $jam($absensiHariIni?->jam_masuk) }}</strong><small>{{ $labelKehadiranHariIni }}</small></div>
                    <dl class="ph-facts">
                        <div><dt>Keterlambatan</dt><dd>{{ $absensiHariIni ? $angka(max(0, (int) $absensiHariIni->menit_terlambat)).' menit' : '-' }}</dd></div>
                        <div><dt>Jam pulang</dt><dd>{{ $absensiHariIni?->jam_pulang ? $jam($absensiHariIni->jam_pulang) : 'Belum tercatat' }}</dd></div>
                        <div><dt>Pulang cepat</dt><dd>{{ $absensiHariIni ? $angka(max(0, (int) $absensiHariIni->menit_pulang_cepat)).' menit' : '-' }}</dd></div>
                    </dl>
                    @if ($modeOrangTua)<a class="ph-link fh-detail-link" href="{{ route('presensi-anak.index') }}">Rincian presensi<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>@endif
                </div>
                <div class="ph-status-footer" aria-label="Presensi {{ $labelBulan }}">
                    @foreach (['sakit' => 'Sakit', 'izin' => 'Izin', 'alfa' => 'Alfa', 'pulang_cepat' => 'Pulang cepat'] as $key => $label)<span>{{ $label }}<strong>{{ $angka($ringkasanKehadiran[$key]) }}</strong></span>@endforeach
                </div>
            </section>
        </div>

        <nav class="ph-actions" aria-label="Layanan {{ $modeOrangTua ? 'orang tua' : 'siswa' }}">
            <a href="{{ $urlNilai }}"><i data-lucide="notebook-pen" aria-hidden="true"></i>{{ $modeOrangTua ? 'Nilai anak' : 'Nilai saya' }}<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            <a href="{{ $urlPoin }}"><i data-lucide="shield-check" aria-hidden="true"></i>Poin &amp; pembinaan<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            @if ($modeOrangTua)
                <a href="{{ route('pengaduan-saya.index') }}"><i data-lucide="message-square-text" aria-hidden="true"></i>Aspirasi &amp; pengaduan<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
                <a href="{{ route('umpan-balik-saya.index') }}"><i data-lucide="list-checks" aria-hidden="true"></i>Umpan balik<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
                <a href="{{ route('pertemuan-saya.index') }}"><i data-lucide="calendar-days" aria-hidden="true"></i>Pertemuan<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            @else
                <a href="{{ route('profil-siswa.show') }}"><i data-lucide="user-round" aria-hidden="true"></i>Profil saya<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            @endif
            <a href="{{ route('kata-sandi.edit') }}"><i data-lucide="key-round" aria-hidden="true"></i>Ganti Password<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
        </nav>

        @if ($raporDashboard->isNotEmpty())
            <section class="ph-section fh-report-section" aria-labelledby="fh-rapor-title">
                <header class="ph-section-head"><h2 id="fh-rapor-title"><i data-lucide="file-check-2" aria-hidden="true"></i>Rapor yang tersedia</h2><a class="ph-link" href="{{ route('rapor-sts-saya.index') }}">Semua rapor<i data-lucide="arrow-up-right" aria-hidden="true"></i></a></header>
                <div class="ph-section-body">
                    @foreach ($raporDashboard as $rapor)
                        <a class="ph-task" href="{{ route('rapor-sts-saya.show', $rapor['id']) }}">
                            <span class="ph-task-icon"><i data-lucide="file-text" aria-hidden="true"></i></span>
                            <span class="ph-task-text"><strong>{{ $rapor['nama'] }}</strong><small>{{ $rapor['tahun'] }} &middot; Terbit {{ $rapor['tanggal']->copy()->locale('id')->translatedFormat('d M Y') }}</small></span>
                            <i data-lucide="chevron-right" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="ph-row">
            <section class="ph-section" aria-labelledby="fh-ibadah-title">
                <header class="ph-section-head"><h2 id="fh-ibadah-title"><i data-lucide="hand-heart" aria-hidden="true"></i>{{ $modeOrangTua ? 'Presensi Ibadah Anak' : 'Ibadah Saya' }}</h2><span class="ph-badge blue">{{ $labelBulan }}</span></header>
                <div class="ph-section-body">
                    @forelse ($ringkasanIbadahSaya as $ibadah)
                        <div class="fh-worship">
                            <div class="fh-worship-head"><strong>{{ $ibadah['kegiatan']?->nama ?: 'Kegiatan ibadah' }}</strong><span class="ph-badge {{ $ibadah['presensi_hari_ini'] ? 'teal' : ($ibadah['dijadwalkan_hari_ini'] ? 'amber' : 'blue') }}">{{ $ibadah['status_hari_ini'] }}</span></div>
                            <p>{{ $ibadah['presensi_hari_ini'] ? 'Tercatat pukul '.$jam($ibadah['presensi_hari_ini']->waktu_scan) : ($ibadah['dijadwalkan_hari_ini'] ? 'Belum tercatat hari ini' : 'Tidak dijadwalkan hari ini') }}</p>
                            <div class="fh-progress-label"><span>{{ $angka($ibadah['tercatat']) }} dari {{ $angka($ibadah['target']) }} kegiatan</span><strong>{{ number_format($ibadah['persentase'], 1, ',', '.') }}%</strong></div>
                            <div class="fh-progress" role="progressbar" aria-label="Capaian {{ $ibadah['kegiatan']?->nama }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min($ibadah['persentase'], 100) }}"><span style="width: {{ min($ibadah['persentase'], 100) }}%;"></span></div>
                        </div>
                    @empty
                        <div class="ph-empty"><i data-lucide="hand-heart" aria-hidden="true"></i><p>Belum ada kegiatan ibadah aktif pada tahun pelajaran ini.</p></div>
                    @endforelse
                </div>
            </section>
            <section class="ph-section" aria-labelledby="fh-informasi-title">
                <header class="ph-section-head"><h2 id="fh-informasi-title"><i data-lucide="school" aria-hidden="true"></i>{{ $modeOrangTua ? 'Informasi Anak' : 'Informasi Sekolah' }}</h2></header>
                <dl class="ph-facts fh-profile">
                    <div><dt>Kelas</dt><dd>{{ $kelasAktif?->nama ?: '-' }}</dd></div>
                    <div><dt>Nomor absen</dt><dd>{{ $anggotaKelasAktif?->nomor_absen ?: '-' }}</dd></div>
                    <div><dt>Wali kelas</dt><dd>{{ $waliKelas?->nama_lengkap ?: 'Belum ditentukan' }}</dd></div>
                    <div><dt>Guru wali</dt><dd>{{ $guruWali?->nama_lengkap ?: 'Belum ditentukan' }}</dd></div>
                    @if ($modeOrangTua)<div><dt>Orang tua/wali</dt><dd>{{ $orangTua?->nama_lengkap ?: auth()->user()->nama }}</dd></div>@endif
                </dl>
            </section>
        </div>

        <section class="ph-section" aria-labelledby="fh-poin-title">
            <header class="ph-section-head"><h2 id="fh-poin-title"><i data-lucide="shield-check" aria-hidden="true"></i>{{ $modeOrangTua ? 'Poin & Pembinaan' : 'Poin Saya' }}</h2><a class="ph-link" href="{{ $urlPoin }}">{{ $modeOrangTua ? 'Lihat detail' : 'Lihat Progress' }}<i data-lucide="arrow-up-right" aria-hidden="true"></i></a></header>
            <div class="fh-point-summary"><span>Total poin resmi<strong>{{ $angka($ringkasanPoin['total']) }}</strong></span><span>Pelanggaran disahkan<strong>{{ $angka($ringkasanPoin['pelanggaran']) }}</strong></span><span>Poin dikurangi<strong>{{ $angka($ringkasanPoin['pengurangan']) }}</strong></span></div>
            <div class="ph-section-body">
                @forelse ($riwayatPoinTerbaru as $transaksi)
                    <div class="fh-point-row"><div><strong>{{ $transaksi->keterangan ?: str($transaksi->jenis)->headline() }}</strong><small>{{ $transaksi->tercatat_pada?->copy()->locale('id')->translatedFormat('d M Y') }}</small></div><span class="ph-badge {{ $transaksi->poin < 0 ? 'teal' : 'amber' }}">{{ $transaksi->poin > 0 ? '+' : '' }}{{ $angka($transaksi->poin) }} poin</span></div>
                @empty
                    <p class="fh-empty-note">Belum ada catatan poin resmi.</p>
                @endforelse
            </div>
        </section>

        <section class="ph-section" aria-labelledby="fh-notifikasi-title">
            <header class="ph-section-head"><h2 id="fh-notifikasi-title"><i data-lucide="bell" aria-hidden="true"></i>Notifikasi Terbaru</h2><a class="ph-link" href="{{ route('notifikasi.index') }}">Semua notifikasi<i data-lucide="arrow-up-right" aria-hidden="true"></i></a></header>
            <div class="ph-section-body">
                @forelse ($notifikasiDashboard as $notifikasi)
                    <form class="ph-activity-form" method="POST" action="{{ route('notifikasi.buka', $notifikasi) }}">
                        @csrf
                        <button class="ph-activity" type="submit"><span class="ph-activity-dot {{ $notifikasi->dibaca_pada ? '' : 'unread' }}" aria-hidden="true"></span><span><strong>{{ $notifikasi->judul }}</strong><span class="ph-activity-message">{{ $notifikasi->pesan }}</span></span><time datetime="{{ $notifikasi->created_at->toIso8601String() }}">{{ $notifikasi->created_at->copy()->locale('id')->diffForHumans() }}</time></button>
                    </form>
                @empty
                    <div class="ph-empty"><i data-lucide="inbox" aria-hidden="true"></i><p>Belum ada notifikasi baru.</p></div>
                @endforelse
            </div>
        </section>
    @endif
</div>
