@extends('layouts.app')

@section('title', 'Laporan Absensi Pegawai Bulanan - NUSA')

@section('content')
    @php
        $formatPersen = fn (mixed $nilai) => rtrim(rtrim(number_format((float) $nilai, 1, ',', '.'), '0'), ',') . '%';
        $formatMenit = fn (int $menit) => $menit > 0 ? $menit . ' menit' : '-';
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $parameterCetak = array_filter([
            'bulan' => $bulan,
            'kata_kunci' => $kataKunci,
            'jenis_pegawai' => $jenisPegawai,
            'pegawai_id' => $pegawaiId,
            'status_pegawai' => $statusPegawai,
        ], fn ($nilai) => filled($nilai));
    @endphp

    <style>
        .employee-report-filter {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 12px;
            align-items: end;
        }

        .employee-report-filter .field,
        .employee-report-filter .actions {
            min-width: 0;
        }

        .employee-report-filter .input,
        .employee-report-filter .select {
            min-width: 0;
        }

        .report-month {
            grid-column: span 2;
        }

        .report-search {
            grid-column: span 4;
        }

        .report-kind,
        .report-person {
            grid-column: span 3;
        }

        .report-status {
            grid-column: span 3;
        }

        .report-actions {
            grid-column: span 9;
            justify-content: flex-end;
        }

        .employee-report-stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        @media (max-width: 1180px) {
            .employee-report-filter {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .report-month,
            .report-search,
            .report-kind,
            .report-person,
            .report-status {
                grid-column: auto;
            }

            .report-actions {
                grid-column: 1 / -1;
            }

            .employee-report-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .employee-report-filter,
            .employee-report-stats {
                grid-template-columns: 1fr;
            }

            .report-actions {
                grid-column: auto;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Absensi Pegawai</p>
            <h1 class="page-title">Laporan absensi pegawai bulanan</h1>
        </div>

        <div class="actions">
            <a href="{{ route('rekap-absensi-pegawai-harian.index') }}" class="button button-muted">Rekap harian</a>
            @izin('laporan.export')
                <a href="{{ route('laporan-absensi-pegawai-bulanan.cetak', $parameterCetak) }}" target="_blank" rel="noopener" class="button button-primary">Cetak semua</a>
            @endizin
            @izin('absensi.scan')
                <a href="{{ route('scan-absensi-pegawai.index') }}" target="_blank" rel="noopener" class="button button-dark">Scan pegawai</a>
            @endizin
        </div>
    </div>

    <form action="{{ route('laporan-absensi-pegawai-bulanan.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="employee-report-filter">
            <div class="field report-month">
                <label for="bulan">Bulan</label>
                <input id="bulan" type="month" name="bulan" value="{{ $bulan }}" class="input">
            </div>

            <div class="field report-search">
                <label for="kata_kunci">Cari pegawai</label>
                <input id="kata_kunci" type="search" name="kata_kunci" value="{{ $kataKunci }}" class="input" placeholder="Nama, NIP, jabatan">
            </div>

            <div class="field report-kind">
                <label for="jenis_pegawai">Jenis pegawai</label>
                <select id="jenis_pegawai" name="jenis_pegawai" class="select">
                    <option value="">Semua jenis</option>
                    @foreach ($daftarJenisPegawai as $item)
                        <option value="{{ $item }}" @selected($jenisPegawai === $item)>{{ $item }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field report-person">
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

            <div class="field report-status">
                <label for="status_pegawai">Status pegawai</label>
                <select id="status_pegawai" name="status_pegawai" class="select">
                    <option value="aktif" @selected($statusPegawai === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($statusPegawai === 'nonaktif')>Nonaktif</option>
                    <option value="semua" @selected($statusPegawai === 'semua')>Semua</option>
                </select>
            </div>

            <div class="actions report-actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('laporan-absensi-pegawai-bulanan.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <div class="employee-report-stats">
        <div class="panel stat">
            <p class="stat-label">Pegawai</p>
            <p class="stat-value">{{ $ringkasan['pegawai'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Hari jadwal</p>
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
            <p class="stat-label">Rata-rata hadir</p>
            <p class="stat-value">{{ $formatPersen($ringkasan['rata_persentase_hadir']) }}</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-pad" style="border-bottom: 1px solid var(--line);">
            <h2 class="panel-title">{{ $labelPeriode }}</h2>
            <p class="help-text" style="margin-top: 6px;">
                Alfa dihitung dari hari yang memiliki jadwal absensi pegawai tetapi belum memiliki catatan scan atau koreksi manual.
            </p>
        </div>

        <div class="desktop-only table-wrap">
            <table class="employee-table placement-table" style="min-width: 1380px;">
                <thead>
                    <tr>
                        <th>Pegawai</th>
                        <th>Kepegawaian</th>
                        <th>Hari jadwal</th>
                        <th>Hadir</th>
                        <th>Izin</th>
                        <th>Sakit</th>
                        <th>Dinas/Cuti</th>
                        <th>Alfa</th>
                        <th>Terlambat</th>
                        <th>Pulang cepat</th>
                        <th>% Hadir</th>
                        @izin('laporan.export')
                            <th class="text-right">Aksi</th>
                        @endizin
                    </tr>
                </thead>
                <tbody>
                    @forelse ($laporanAbsensiPegawai as $item)
                        @php
                            $pegawai = $item['pegawai'];
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
                            <td data-label="Hari jadwal">{{ $item['hari_efektif'] }}</td>
                            <td data-label="Hadir">{{ $item['hadir'] }}</td>
                            <td data-label="Izin">{{ $item['izin'] }}</td>
                            <td data-label="Sakit">{{ $item['sakit'] }}</td>
                            <td data-label="Dinas/Cuti">
                                <p class="person-name">{{ $item['dinas_luar'] }} / {{ $item['cuti'] }}</p>
                                <p class="person-meta">Manual {{ $item['manual'] }} catatan</p>
                            </td>
                            <td data-label="Alfa">
                                <span class="{{ $item['alfa'] > 0 ? 'badge badge-danger' : 'badge badge-active' }}">{{ $item['alfa'] }}</span>
                            </td>
                            <td data-label="Terlambat">
                                <p class="person-name">{{ $item['terlambat'] }}</p>
                                <p class="person-meta">{{ $formatMenit($item['menit_terlambat']) }}</p>
                            </td>
                            <td data-label="Pulang cepat">
                                <p class="person-name">{{ $item['pulang_cepat'] }}</p>
                                <p class="person-meta">
                                    {{ $formatMenit($item['menit_pulang_cepat']) }}
                                    @if ($item['belum_pulang'] > 0)
                                        - Belum pulang {{ $item['belum_pulang'] }}
                                    @endif
                                </p>
                            </td>
                            <td data-label="% Hadir">
                                <span class="badge {{ $item['persentase_hadir'] >= 90 ? 'badge-active' : ($item['persentase_hadir'] >= 75 ? 'badge-warning' : 'badge-danger') }}">
                                    {{ $formatPersen($item['persentase_hadir']) }}
                                </span>
                            </td>
                            @izin('laporan.export')
                                <td data-label="Aksi">
                                    <div class="actions" style="justify-content: flex-end;">
                                        <a href="{{ route('laporan-absensi-pegawai-bulanan.cetak-pegawai', ['pegawai' => $pegawai, ...$parameterCetak]) }}" target="_blank" rel="noopener" class="button button-dark button-sm">Cetak</a>
                                    </div>
                                </td>
                            @endizin
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->memilikiIzin('laporan.export') ? 12 : 11 }}" class="empty-state">Belum ada pegawai pada pilihan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($laporanAbsensiPegawai as $item)
                @php
                    $pegawai = $item['pegawai'];
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

                                <span class="badge {{ $item['persentase_hadir'] >= 90 ? 'badge-active' : ($item['persentase_hadir'] >= 75 ? 'badge-warning' : 'badge-danger') }}">
                                    {{ $formatPersen($item['persentase_hadir']) }}
                                </span>
                            </div>

                            <dl class="quick-facts">
                                <div>
                                    <dt>Hari jadwal</dt>
                                    <dd>{{ $item['hari_efektif'] }}</dd>
                                </div>
                                <div>
                                    <dt>Hadir</dt>
                                    <dd>{{ $item['hadir'] }}</dd>
                                </div>
                                <div>
                                    <dt>Alfa</dt>
                                    <dd>{{ $item['alfa'] }}</dd>
                                </div>
                                <div>
                                    <dt>Izin/Sakit</dt>
                                    <dd>{{ $item['izin'] }} / {{ $item['sakit'] }}</dd>
                                </div>
                                <div>
                                    <dt>Dinas/Cuti</dt>
                                    <dd>{{ $item['dinas_luar'] }} / {{ $item['cuti'] }}</dd>
                                </div>
                                <div>
                                    <dt>Terlambat</dt>
                                    <dd>{{ $item['terlambat'] }} kali</dd>
                                </div>
                                <div>
                                    <dt>Pulang cepat</dt>
                                    <dd>{{ $item['pulang_cepat'] }} kali</dd>
                                </div>
                                <div>
                                    <dt>Koreksi manual</dt>
                                    <dd>{{ $item['manual'] }}</dd>
                                </div>
                            </dl>

                            @izin('laporan.export')
                                <div class="actions" style="margin-top: 14px;">
                                    <a href="{{ route('laporan-absensi-pegawai-bulanan.cetak-pegawai', ['pegawai' => $pegawai, ...$parameterCetak]) }}" target="_blank" rel="noopener" class="button button-dark">Cetak</a>
                                </div>
                            @endizin
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada pegawai pada pilihan ini.</div>
            @endforelse
        </div>
    </section>
@endsection
