@extends('layouts.app')

@section('title', 'Jadwal Kelas Saya - NUSA')

@section('content')
    <style>
        .class-schedule-filter {
            display: flex;
            align-items: end;
            gap: 12px;
        }

        .class-schedule-filter .field {
            width: min(100%, 320px);
        }

        .class-schedule-table {
            min-width: 1040px;
            table-layout: fixed;
        }

        .class-schedule-table th,
        .class-schedule-table td {
            vertical-align: top;
        }

        .class-schedule-table th:first-child,
        .class-schedule-table td:first-child {
            width: 112px;
        }

        .class-schedule-day-today {
            background: #fff8d8;
            color: #6b5200;
        }

        .class-schedule-time {
            color: var(--muted);
            font-size: .78rem;
            line-height: 1.35;
        }

        .class-schedule-cell {
            min-height: 100px;
            border: 1px solid #e4e4e7;
            border-radius: 6px;
            background: #fafafa;
            padding: 9px;
        }

        .class-schedule-cell.current {
            border-color: var(--accent);
            box-shadow: inset 0 0 0 2px rgba(241, 196, 15, .34);
        }

        .class-schedule-cell.lesson {
            border-color: #b9cde2;
            background: var(--primary-soft);
        }

        .class-schedule-cell.special {
            border-color: #fde68a;
            background: #fffbeb;
        }

        .class-schedule-cell.empty {
            color: #a1a1aa;
        }

        .class-schedule-subject,
        .class-schedule-empty-label {
            margin: 7px 0 0;
            font-size: .88rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .class-schedule-teacher {
            display: block;
            margin-top: 7px;
            color: #3f3f46;
            font-size: .74rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .class-schedule-mobile-tabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: thin;
        }

        .class-schedule-mobile-tab {
            min-height: 38px;
            border: 1px solid #d4d4d8;
            border-radius: 8px;
            background: #fff;
            padding: 8px 12px;
            color: #3f3f46;
            cursor: pointer;
            font-size: .84rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .class-schedule-mobile-tab.active {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
        }

        .class-schedule-mobile-day {
            display: none;
            margin-top: 14px;
        }

        .class-schedule-mobile-day.active {
            display: grid;
            gap: 10px;
        }

        .class-schedule-mobile-slot {
            display: grid;
            grid-template-columns: 74px minmax(0, 1fr);
            gap: 10px;
            align-items: stretch;
        }

        .class-schedule-mobile-number {
            border: 1px solid #e4e4e7;
            border-radius: 6px;
            background: #fafafa;
            padding: 9px 7px;
            font-size: .82rem;
            font-weight: 800;
            text-align: center;
        }

        @media (max-width: 720px) {
            .class-schedule-filter {
                display: grid;
            }

            .class-schedule-filter .field {
                width: 100%;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Wali Kelas</p>
            <h1 class="page-title">Jadwal Kelas Saya</h1>
            <p class="page-subtitle">
                @if ($kelasDipilih)
                    Jadwal mingguan {{ $kelasDipilih->nama }} pada tahun pelajaran yang dipilih.
                @else
                    Jadwal hanya ditampilkan untuk kelas yang Anda wali.
                @endif
            </p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Kelas</p>
            <p class="stat-value">{{ $kelasDipilih?->nama ?? '-' }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Jam terjadwal</p>
            <p class="stat-value">{{ $jumlahJadwal }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Mata pelajaran</p>
            <p class="stat-value">{{ $jumlahMataPelajaran }}</p>
        </div>
    </div>

    @unless ($pegawai)
        <div class="alert alert-danger">
            Akun ini belum terhubung dengan data pegawai. Hubungi administrator agar jadwal kelas dapat ditampilkan.
        </div>
    @endunless

    @if ($pegawai && $daftarKelas->isEmpty())
        <div class="alert alert-warning">
            Anda belum ditetapkan sebagai wali kelas pada tahun pelajaran ini.
        </div>
    @endif

    <form action="{{ route('jadwal-kelas-saya.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="class-schedule-filter">
            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    @forelse ($tahunPelajaran as $item)
                        <option value="{{ $item->id }}" @selected((string) $tahunPelajaranId === (string) $item->id)>
                            {{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}
                        </option>
                    @empty
                        <option value="">Belum ada tahun pelajaran</option>
                    @endforelse
                </select>
            </div>

            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select">
                    @forelse ($daftarKelas as $item)
                        <option value="{{ $item->id }}" @selected((string) $kelasId === (string) $item->id)>
                            {{ $item->nama }}
                        </option>
                    @empty
                        <option value="">Belum ada kelas wali</option>
                    @endforelse
                </select>
            </div>

            <button type="submit" class="button button-dark">Tampilkan</button>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table class-schedule-table">
                <thead>
                    <tr>
                        <th>Jam</th>
                        @foreach ($daftarHari as $kodeHari => $labelHari)
                            <th class="{{ $kodeHari === $hariHariIni ? 'class-schedule-day-today' : '' }}">{{ $labelHari }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($nomorJam as $nomor)
                        <tr>
                            <td>
                                <p class="person-name">Jam {{ $nomor }}</p>
                            </td>
                            @foreach ($daftarHari as $kodeHari => $labelHari)
                                @php
                                    $jam = $jamPerHari->get($kodeHari)?->get($nomor);
                                    $jadwal = $jam ? $jadwalKelas->get($jam->id) : null;
                                    $sedangBerlangsung = $jam && $jam->id === $jamAktifId;
                                @endphp
                                <td>
                                    @if (! $jam)
                                        <div class="class-schedule-cell empty">
                                            <p class="class-schedule-empty-label">-</p>
                                        </div>
                                    @elseif ($jam->jenis !== 'pelajaran')
                                        <div class="class-schedule-cell special {{ $sedangBerlangsung ? 'current' : '' }}">
                                            <p class="class-schedule-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</p>
                                            <p class="class-schedule-subject">{{ $jam->label ?: $jam->labelJenis() }}</p>
                                        </div>
                                    @elseif ($jadwal)
                                        <div class="class-schedule-cell lesson {{ $sedangBerlangsung ? 'current' : '' }}">
                                            <p class="class-schedule-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</p>
                                            <p class="class-schedule-subject">{{ $jadwal->mataPelajaranTerjadwal()?->nama ?? '-' }}</p>
                                            <span class="class-schedule-teacher">{{ $jadwal->guruMataPelajaran?->pegawai?->nama_lengkap ?? 'Guru belum ditentukan' }}</span>
                                        </div>
                                    @else
                                        <div class="class-schedule-cell empty {{ $sedangBerlangsung ? 'current' : '' }}">
                                            <p class="class-schedule-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</p>
                                            <p class="class-schedule-empty-label">Kosong</p>
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($daftarHari) + 1 }}" class="empty-state">
                                Jam pelajaran belum diatur oleh administrator.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only panel-pad">
            <div class="class-schedule-mobile-tabs" role="tablist" aria-label="Pilih hari">
                @foreach ($daftarHari as $kodeHari => $labelHari)
                    <button
                        type="button"
                        class="class-schedule-mobile-tab {{ $kodeHari === $hariHariIni || ($hariHariIni === 'minggu' && $loop->first) ? 'active' : '' }}"
                        data-class-schedule-tab="{{ $kodeHari }}"
                        role="tab"
                        aria-selected="{{ $kodeHari === $hariHariIni || ($hariHariIni === 'minggu' && $loop->first) ? 'true' : 'false' }}"
                    >
                        {{ $labelHari }}
                    </button>
                @endforeach
            </div>

            @foreach ($daftarHari as $kodeHari => $labelHari)
                <div
                    class="class-schedule-mobile-day {{ $kodeHari === $hariHariIni || ($hariHariIni === 'minggu' && $loop->first) ? 'active' : '' }}"
                    data-class-schedule-day="{{ $kodeHari }}"
                    role="tabpanel"
                >
                    @forelse ($jamPerHari->get($kodeHari, collect()) as $jam)
                        @php
                            $jadwal = $jadwalKelas->get($jam->id);
                            $sedangBerlangsung = $jam->id === $jamAktifId;
                        @endphp
                        <div class="class-schedule-mobile-slot">
                            <div class="class-schedule-mobile-number">
                                <div>Jam {{ $jam->nomor_jam }}</div>
                                <div class="class-schedule-time">{{ $jam->formatJam($jam->jam_mulai) }}</div>
                            </div>

                            @if ($jam->jenis !== 'pelajaran')
                                <div class="class-schedule-cell special {{ $sedangBerlangsung ? 'current' : '' }}">
                                    <p class="class-schedule-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</p>
                                    <p class="class-schedule-subject">{{ $jam->label ?: $jam->labelJenis() }}</p>
                                </div>
                            @elseif ($jadwal)
                                <div class="class-schedule-cell lesson {{ $sedangBerlangsung ? 'current' : '' }}">
                                    <p class="class-schedule-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</p>
                                    <p class="class-schedule-subject">{{ $jadwal->mataPelajaranTerjadwal()?->nama ?? '-' }}</p>
                                    <span class="class-schedule-teacher">{{ $jadwal->guruMataPelajaran?->pegawai?->nama_lengkap ?? 'Guru belum ditentukan' }}</span>
                                </div>
                            @else
                                <div class="class-schedule-cell empty {{ $sedangBerlangsung ? 'current' : '' }}">
                                    <p class="class-schedule-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</p>
                                    <p class="class-schedule-empty-label">Kosong</p>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="empty-state">Jam pelajaran {{ $labelHari }} belum diatur.</div>
                    @endforelse
                </div>
            @endforeach
        </div>
    </section>

    <script>
        document.querySelectorAll('[data-class-schedule-tab]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('[data-class-schedule-tab]').forEach(function (item) {
                    item.classList.toggle('active', item === button);
                    item.setAttribute('aria-selected', item === button ? 'true' : 'false');
                });

                document.querySelectorAll('[data-class-schedule-day]').forEach(function (day) {
                    day.classList.toggle('active', day.dataset.classScheduleDay === button.dataset.classScheduleTab);
                });
            });
        });
    </script>
@endsection
