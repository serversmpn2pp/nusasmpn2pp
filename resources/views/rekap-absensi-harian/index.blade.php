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
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Absensi</p>
            <h1 class="page-title">Rekap absensi harian</h1>
        </div>

        <div class="actions">
            <a href="{{ route('scan-absensi.index') }}" target="_blank" rel="noopener" class="button button-primary">Scan absensi</a>
            <a href="{{ route('pengaturan-absensi.index') }}" class="button button-muted">Jam absensi</a>
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
                    <option value="">Semua kelas</option>
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
                    {{ $kelasId ? 'Kelas ' . ($daftarKelas->firstWhere('id', (int) $kelasId)?->nama ?? '-') : 'Semua kelas' }}
                </p>
            </div>

            <div class="desktop-only table-wrap">
                <table class="employee-table placement-table" style="min-width: 1180px;">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th>Status</th>
                            <th>Masuk</th>
                            <th>Pulang</th>
                            <th>Keterlambatan</th>
                            <th>Catatan</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rekapAbsensi as $item)
                            @php
                                $anggota = $item['anggota_kelas'];
                                $absensi = $item['absensi'];
                                $status = $item['status_kehadiran'];
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
                                <td data-label="Catatan">{{ $absensi?->catatan ?: '-' }}</td>
                                <td data-label="Aksi">
                                    <div class="actions" style="justify-content: flex-end;">
                                        <a href="{{ route('rekap-absensi-harian.koreksi.edit', ['anggotaKelas' => $anggota, 'tanggal' => $tanggal]) }}" class="button button-dark button-sm">Koreksi</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="empty-state">Belum ada siswa aktif pada pilihan ini.</td>
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

                        @if ($absensi?->catatan)
                            <p class="help-text" style="margin-top: 12px;">{{ $absensi->catatan }}</p>
                        @endif

                        <div class="actions" style="margin-top: 14px;">
                            <a href="{{ route('rekap-absensi-harian.koreksi.edit', ['anggotaKelas' => $anggota, 'tanggal' => $tanggal]) }}" class="button button-dark">Koreksi</a>
                        </div>
                    </article>
                @empty
                    <div class="empty-state">Belum ada siswa aktif pada pilihan ini.</div>
                @endforelse
            </div>
        </section>
    @endif
@endsection
