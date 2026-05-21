@extends('layouts.app')

@section('title', 'Rekap Absensi Pegawai Harian - NUSA')

@section('content')
    @php
        $tanggalLabel = \Carbon\Carbon::parse($tanggal)->locale('id')->translatedFormat('d F Y');
        $formatJam = fn (?string $jam) => $jam ? substr($jam, 0, 5) : '-';
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $labelStatus = \App\Models\AbsensiPegawai::DAFTAR_STATUS_KEHADIRAN;
        $badgeStatus = fn (string $status) => match ($status) {
            'hadir' => 'badge badge-active',
            'izin', 'dinas_luar', 'cuti' => 'badge badge-warning',
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

    <style>
        .employee-attendance-filter {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 12px;
            align-items: end;
        }

        .employee-attendance-filter .field,
        .employee-attendance-filter .actions {
            min-width: 0;
        }

        .employee-attendance-filter .input,
        .employee-attendance-filter .select {
            min-width: 0;
        }

        .filter-date {
            grid-column: span 2;
        }

        .filter-search {
            grid-column: span 4;
        }

        .filter-kind,
        .filter-person {
            grid-column: span 3;
        }

        .filter-status,
        .filter-attendance {
            grid-column: span 3;
        }

        .filter-actions {
            grid-column: span 6;
            justify-content: flex-end;
        }

        .employee-attendance-stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        @media (max-width: 1180px) {
            .employee-attendance-filter {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filter-date,
            .filter-search,
            .filter-kind,
            .filter-person,
            .filter-status,
            .filter-attendance {
                grid-column: auto;
            }

            .filter-actions {
                grid-column: 1 / -1;
            }

            .employee-attendance-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .employee-attendance-filter,
            .employee-attendance-stats {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                grid-column: auto;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Absensi Pegawai</p>
            <h1 class="page-title">Rekap absensi pegawai harian</h1>
        </div>

        <div class="actions">
            <a href="{{ route('scan-absensi-pegawai.index') }}" target="_blank" rel="noopener" class="button button-primary">Scan pegawai</a>
            <a href="{{ route('pengaturan-absensi-pegawai.index') }}" class="button button-muted">Jam pegawai</a>
        </div>
    </div>

    <form action="{{ route('rekap-absensi-pegawai-harian.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="employee-attendance-filter">
            <div class="field filter-date">
                <label for="tanggal">Tanggal</label>
                <input id="tanggal" type="date" name="tanggal" value="{{ $tanggal }}" class="input">
            </div>

            <div class="field filter-search">
                <label for="kata_kunci">Cari pegawai</label>
                <input id="kata_kunci" type="search" name="kata_kunci" value="{{ $kataKunci }}" class="input" placeholder="Nama, NIP, jabatan">
            </div>

            <div class="field filter-kind">
                <label for="jenis_pegawai">Jenis pegawai</label>
                <select id="jenis_pegawai" name="jenis_pegawai" class="select">
                    <option value="">Semua jenis</option>
                    @foreach ($daftarJenisPegawai as $item)
                        <option value="{{ $item }}" @selected($jenisPegawai === $item)>{{ $item }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field filter-person">
                <label for="pegawai_id">Pegawai</label>
                <select id="pegawai_id" name="pegawai_id" class="select">
                    <option value="">Semua pegawai</option>
                    @foreach ($daftarPegawai as $pegawai)
                        <option value="{{ $pegawai->id }}" @selected((string) $pegawaiId === (string) $pegawai->id)>
                            {{ $pegawai->nama_lengkap }} - {{ $pegawai->nip ?: 'NIP kosong' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field filter-status">
                <label for="status_pegawai">Status pegawai</label>
                <select id="status_pegawai" name="status_pegawai" class="select">
                    <option value="aktif" @selected($statusPegawai === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($statusPegawai === 'nonaktif')>Nonaktif</option>
                    <option value="semua" @selected($statusPegawai === 'semua')>Semua</option>
                </select>
            </div>

            <div class="field filter-attendance">
                <label for="status_kehadiran">Kehadiran</label>
                <select id="status_kehadiran" name="status_kehadiran" class="select">
                    <option value="semua" @selected($statusKehadiran === 'semua')>Semua</option>
                    @foreach ($labelStatus as $key => $label)
                        <option value="{{ $key }}" @selected($statusKehadiran === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="actions filter-actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('rekap-absensi-pegawai-harian.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="employee-attendance-stats">
        <div class="panel stat">
            <p class="stat-label">Total pegawai</p>
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
        <div class="panel stat">
            <p class="stat-label">Dinas luar</p>
            <p class="stat-value">{{ $ringkasan['dinas_luar'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Cuti</p>
            <p class="stat-value">{{ $ringkasan['cuti'] }}</p>
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

    <section class="panel">
        <div class="panel-pad" style="border-bottom: 1px solid var(--line);">
            <h2 class="panel-title">{{ $tanggalLabel }}</h2>
            <p class="help-text" style="margin-top: 6px;">
                {{ $jenisPegawai ? 'Jenis pegawai: ' . $jenisPegawai : 'Semua jenis pegawai' }}
            </p>
        </div>

        <div class="desktop-only table-wrap">
            <table class="employee-table placement-table" style="min-width: 1260px;">
                <thead>
                    <tr>
                        <th>Pegawai</th>
                        <th>Kepegawaian</th>
                        <th>Status</th>
                        <th>Masuk</th>
                        <th>Pulang</th>
                        <th>Keterlambatan</th>
                        <th>Jadwal</th>
                        <th>Catatan</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rekapAbsensi as $item)
                        @php
                            $pegawai = $item['pegawai'];
                            $absensi = $item['absensi'];
                            $status = $item['status_kehadiran'];
                            $jadwal = $absensi?->pengaturanAbsensiPegawai;
                        @endphp
                        <tr>
                            <td data-label="Pegawai">
                                <div class="person">
                                    <div class="avatar avatar-sm">
                                        @if ($pegawai->foto)
                                            <img src="{{ asset('storage/' . $pegawai->foto) }}" alt="Foto {{ $pegawai->nama_lengkap }}">
                                        @else
                                            {{ strtoupper(mb_substr($pegawai->nama_lengkap, 0, 1)) }}
                                        @endif
                                    </div>
                                    <div>
                                        <p class="person-name">{{ $pegawai->nama_lengkap }}</p>
                                        <p class="person-meta">NIP: {{ $teks($pegawai->nip) }}</p>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Kepegawaian">
                                <div>{{ $teks($pegawai->jabatan_utama ?: $pegawai->jenis_pegawai) }}</div>
                                <p class="person-meta">{{ $teks($pegawai->status_kepegawaian) }}</p>
                            </td>
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
                            <td data-label="Jadwal">
                                <div>{{ $jadwal?->nama_jadwal ?: '-' }}</div>
                                <p class="person-meta">{{ $jadwal?->labelSasaran() ?: '-' }}</p>
                            </td>
                            <td data-label="Catatan">{{ $absensi?->catatan ?: '-' }}</td>
                            <td data-label="Aksi">
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('rekap-absensi-pegawai-harian.koreksi.edit', ['pegawai' => $pegawai, 'tanggal' => $tanggal]) }}" class="button button-dark button-sm">Koreksi</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="empty-state">Belum ada pegawai pada pilihan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($rekapAbsensi as $item)
                @php
                    $pegawai = $item['pegawai'];
                    $absensi = $item['absensi'];
                    $status = $item['status_kehadiran'];
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-main">
                        <div class="avatar avatar-md">
                            @if ($pegawai->foto)
                                <img src="{{ asset('storage/' . $pegawai->foto) }}" alt="Foto {{ $pegawai->nama_lengkap }}">
                            @else
                                {{ strtoupper(mb_substr($pegawai->nama_lengkap, 0, 1)) }}
                            @endif
                        </div>

                        <div style="min-width:0; flex:1;">
                            <div class="mobile-card-head">
                                <div>
                                    <p class="person-name">{{ $pegawai->nama_lengkap }}</p>
                                    <p class="person-meta">NIP {{ $teks($pegawai->nip) }}</p>
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
                                <a href="{{ route('rekap-absensi-pegawai-harian.koreksi.edit', ['pegawai' => $pegawai, 'tanggal' => $tanggal]) }}" class="button button-dark">Koreksi</a>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada pegawai pada pilihan ini.</div>
            @endforelse
        </div>
    </section>
@endsection
