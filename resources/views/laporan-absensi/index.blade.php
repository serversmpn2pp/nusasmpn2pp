@extends('layouts.app')

@section('title', 'Laporan Presensi - NUSA')

@section('content')
    @php
        $formatPersen = fn (mixed $nilai) => rtrim(rtrim(number_format((float) $nilai, 1, ',', '.'), '0'), ',') . '%';
        $formatMenit = fn (int $menit) => $menit > 0 ? $menit . ' menit' : '-';
        $kelasDipilih = $kelasDipilih ?? ($kelasId ? $daftarKelas->firstWhere('id', (int) $kelasId) : null);
        $parameterExport = array_filter([
            'tahun_pelajaran_id' => $tahunPelajaranId,
            'kelas_id' => $kelasId,
            'periode' => $periode,
            'tanggal' => $tanggal,
            'bulan' => $bulan,
            'semester' => $semester,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
        ], fn ($nilai) => filled($nilai));
    @endphp

    <style>
        .periode-fields {
            display: none;
        }

        .periode-fields.is-visible {
            display: block;
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Presensi</p>
            <h1 class="page-title">Laporan presensi</h1>
        </div>

        <div class="actions">
            <a href="{{ route('rekap-absensi-harian.index') }}" class="button button-muted">Rekap harian</a>
            @izin('laporan.export')
                <a href="{{ route('laporan-absensi.export', $parameterExport) }}" class="button button-primary">Export Excel</a>
            @endizin
            @izin('absensi.scan')
                <a href="{{ route('scan-absensi.index') }}" target="_blank" rel="noopener" class="button button-dark">Scan presensi</a>
            @endizin
        </div>
    </div>

    <form action="{{ route('laporan-absensi.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid filter-grid-wide">
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

            <div class="field">
                <label for="periode">Periode</label>
                <select id="periode" name="periode" class="select">
                    <option value="harian" @selected($periode === 'harian')>Harian</option>
                    <option value="bulanan" @selected($periode === 'bulanan')>Bulanan</option>
                    <option value="semester" @selected($periode === 'semester')>Semester</option>
                    <option value="rentang" @selected($periode === 'rentang')>Rentang Tanggal</option>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('laporan-absensi.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>

        <div class="form-grid" style="margin-top: 16px;">
            <div class="field periode-fields" data-periode-field="harian">
                <label for="tanggal">Tanggal</label>
                <input id="tanggal" type="date" name="tanggal" value="{{ $tanggal }}" class="input">
            </div>

            <div class="field periode-fields" data-periode-field="bulanan">
                <label for="bulan">Bulan</label>
                <input id="bulan" type="month" name="bulan" value="{{ $bulan }}" class="input">
            </div>

            <div class="field periode-fields" data-periode-field="semester">
                <label for="semester">Semester</label>
                <select id="semester" name="semester" class="select">
                    <option value="ganjil" @selected($semester === 'ganjil')>Ganjil</option>
                    <option value="genap" @selected($semester === 'genap')>Genap</option>
                </select>
            </div>

            <div class="field periode-fields" data-periode-field="rentang">
                <label for="tanggal_mulai">Tanggal mulai</label>
                <input id="tanggal_mulai" type="date" name="tanggal_mulai" value="{{ $tanggalMulai }}" class="input">
            </div>

            <div class="field periode-fields" data-periode-field="rentang">
                <label for="tanggal_selesai">Tanggal selesai</label>
                <input id="tanggal_selesai" type="date" name="tanggal_selesai" value="{{ $tanggalSelesai }}" class="input">
            </div>
        </div>
    </form>

    @if ($cakupanWaliKelas ?? false)
        <div class="alert">Laporan presensi dibatasi pada kelas yang Anda wali.</div>
    @endif

    @if (empty($hariAktif))
        <div class="alert alert-danger">
            Belum ada pengaturan presensi aktif. Laporan belum dapat menghitung hari efektif.
        </div>
    @endif

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Siswa</p>
            <p class="stat-value">{{ $ringkasan['siswa'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Hari efektif</p>
            <p class="stat-value">{{ $ringkasan['hari_efektif'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Total hadir</p>
            <p class="stat-value">{{ $ringkasan['hadir'] }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Total alfa</p>
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
            <p class="stat-label">Rata-rata hadir</p>
            <p class="stat-value">{{ $formatPersen($ringkasan['rata_persentase_hadir']) }}</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-pad" style="border-bottom: 1px solid var(--line);">
            <h2 class="panel-title">{{ $labelPeriode }}</h2>
            <p class="help-text" style="margin-top: 6px;">
                {{ $kelasDipilih ? 'Kelas ' . $kelasDipilih->nama : (($cakupanWaliKelas ?? false) ? 'Semua kelas wali' : 'Semua kelas') }}.
                Alfa otomatis dihitung dari hari efektif yang tidak memiliki catatan presensi.
            </p>
        </div>

        <div class="desktop-only table-wrap">
            <table class="employee-table placement-table" style="min-width: 1180px;">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Siswa</th>
                        <th>Kelas</th>
                        <th>Hadir</th>
                        <th>Izin</th>
                        <th>Sakit</th>
                        <th>Alfa</th>
                        <th>Terlambat</th>
                        <th>Pulang cepat</th>
                        <th>% Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($laporanAbsensi as $item)
                        @php
                            $anggota = $item['anggota_kelas'];
                        @endphp
                        <tr>
                            <td data-label="No.">{{ $anggota->nomor_absen ?: '-' }}</td>
                            <td data-label="Siswa">
                                <p class="person-name">{{ $anggota->siswa?->nama_lengkap ?: '-' }}</p>
                                <p class="person-meta">NIS: {{ $anggota->siswa?->nis ?: '-' }} - NISN: {{ $anggota->siswa?->nisn ?: '-' }}</p>
                            </td>
                            <td data-label="Kelas">{{ $anggota->kelas?->nama ?: '-' }}</td>
                            <td data-label="Hadir">{{ $item['hadir'] }}</td>
                            <td data-label="Izin">{{ $item['izin'] }}</td>
                            <td data-label="Sakit">{{ $item['sakit'] }}</td>
                            <td data-label="Alfa">
                                <span class="{{ $item['alfa'] > 0 ? 'badge badge-danger' : 'badge badge-active' }}">{{ $item['alfa'] }}</span>
                            </td>
                            <td data-label="Terlambat">
                                <p class="person-name">{{ $item['terlambat'] }}</p>
                                <p class="person-meta">{{ $formatMenit($item['menit_terlambat']) }}</p>
                            </td>
                            <td data-label="Pulang cepat">
                                <p class="person-name">{{ $item['pulang_cepat'] }}</p>
                                <p class="person-meta">{{ $formatMenit($item['menit_pulang_cepat']) }}</p>
                            </td>
                            <td data-label="% Hadir">
                                <span class="badge {{ $item['persentase_hadir'] >= 90 ? 'badge-active' : 'badge-warning' }}">{{ $formatPersen($item['persentase_hadir']) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="empty-state">Belum ada siswa aktif pada pilihan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($laporanAbsensi as $item)
                @php
                    $anggota = $item['anggota_kelas'];
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $anggota->siswa?->nama_lengkap ?: '-' }}</p>
                            <p class="person-meta">{{ $anggota->kelas?->nama ?: '-' }} - NISN {{ $anggota->siswa?->nisn ?: '-' }}</p>
                        </div>

                        <span class="badge {{ $item['persentase_hadir'] >= 90 ? 'badge-active' : 'badge-warning' }}">{{ $formatPersen($item['persentase_hadir']) }}</span>
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Hadir</dt>
                            <dd>{{ $item['hadir'] }}</dd>
                        </div>
                        <div>
                            <dt>Alfa</dt>
                            <dd>{{ $item['alfa'] }}</dd>
                        </div>
                        <div>
                            <dt>Izin</dt>
                            <dd>{{ $item['izin'] }}</dd>
                        </div>
                        <div>
                            <dt>Sakit</dt>
                            <dd>{{ $item['sakit'] }}</dd>
                        </div>
                        <div>
                            <dt>Terlambat</dt>
                            <dd>{{ $item['terlambat'] }} kali</dd>
                        </div>
                        <div>
                            <dt>Pulang cepat</dt>
                            <dd>{{ $item['pulang_cepat'] }} kali</dd>
                        </div>
                    </dl>
                </article>
            @empty
                <div class="empty-state">Belum ada siswa aktif pada pilihan ini.</div>
            @endforelse
        </div>
    </section>

    <script>
        const periodeSelect = document.getElementById('periode');
        const periodeFields = document.querySelectorAll('[data-periode-field]');

        function tampilkanFieldPeriode() {
            periodeFields.forEach((field) => {
                field.classList.toggle('is-visible', field.dataset.periodeField === periodeSelect.value);
            });
        }

        periodeSelect.addEventListener('change', tampilkanFieldPeriode);
        tampilkanFieldPeriode();
    </script>
@endsection
