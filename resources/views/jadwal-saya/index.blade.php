@extends('layouts.app')

@section('title', 'Jadwal Saya - NUSA')

@section('content')
    <style>
        .personal-schedule-filter {
            display: flex;
            align-items: end;
            gap: 12px;
        }

        .personal-schedule-filter .field {
            width: min(100%, 320px);
        }

        .schedule-table {
            min-width: 1040px;
            table-layout: fixed;
        }

        .schedule-table th,
        .schedule-table td {
            vertical-align: top;
        }

        .schedule-table th:first-child,
        .schedule-table td:first-child {
            width: 112px;
        }

        .schedule-day-today {
            background: #fff8d8;
            color: #6b5200;
        }

        .schedule-time {
            color: var(--muted);
            font-size: .78rem;
            line-height: 1.35;
        }

        .schedule-cell {
            min-height: 92px;
            border: 1px solid #e4e4e7;
            border-radius: 6px;
            background: #fafafa;
            padding: 9px;
        }

        .schedule-cell.current {
            border-color: var(--accent);
            box-shadow: inset 0 0 0 2px rgba(241, 196, 15, .34);
        }

        .schedule-cell.lesson {
            border-color: #b9cde2;
            background: var(--primary-soft);
        }

        .schedule-cell.special {
            border-color: #fde68a;
            background: #fffbeb;
        }

        .schedule-cell.empty {
            color: #a1a1aa;
        }

        .schedule-subject,
        .schedule-empty-label {
            margin: 7px 0 0;
            font-size: .88rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .schedule-class {
            display: inline-flex;
            margin-top: 7px;
            border-radius: 999px;
            background: var(--primary);
            padding: 3px 7px;
            color: #fff;
            font-size: .72rem;
            font-weight: 800;
        }

        .schedule-mobile-tabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: thin;
        }

        .schedule-mobile-tab {
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

        .schedule-mobile-tab.active {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
        }

        .schedule-mobile-day {
            display: none;
            margin-top: 14px;
        }

        .schedule-mobile-day.active {
            display: grid;
            gap: 10px;
        }

        .schedule-mobile-slot {
            display: grid;
            grid-template-columns: 74px minmax(0, 1fr);
            gap: 10px;
            align-items: stretch;
        }

        .schedule-mobile-number {
            border: 1px solid #e4e4e7;
            border-radius: 6px;
            background: #fafafa;
            padding: 9px 7px;
            font-size: .82rem;
            font-weight: 800;
            text-align: center;
        }

        @media (max-width: 620px) {
            .personal-schedule-filter {
                display: grid;
            }

            .personal-schedule-filter .field {
                width: 100%;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Jadwal saya</h1>
        </div>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Jam mengajar</p>
            <p class="stat-value">{{ $jumlahJamMengajar }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Kelas</p>
            <p class="stat-value">{{ $jumlahKelas }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Mata pelajaran</p>
            <p class="stat-value">{{ $jumlahMataPelajaran }}</p>
        </div>
    </div>

    @unless ($pegawai)
        <div class="alert alert-danger">
            Akun ini belum terhubung dengan data pegawai. Hubungi administrator agar jadwal pribadi dapat ditampilkan.
        </div>
    @endunless

    <form action="{{ route('jadwal-saya.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="personal-schedule-filter">
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

            <button type="submit" class="button button-dark">Tampilkan</button>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table schedule-table">
                <thead>
                    <tr>
                        <th>Jam</th>
                        @foreach ($daftarHari as $kodeHari => $labelHari)
                            <th class="{{ $kodeHari === $hariHariIni ? 'schedule-day-today' : '' }}">{{ $labelHari }}</th>
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
                                    $jadwal = $jam ? $jadwalSaya->get($jam->id) : null;
                                    $sedangBerlangsung = $jam && $jam->id === $jamAktifId;
                                @endphp
                                <td>
                                    @if (! $jam)
                                        <div class="schedule-cell empty">
                                            <p class="schedule-empty-label">-</p>
                                        </div>
                                    @elseif ($jam->jenis !== 'pelajaran')
                                        <div class="schedule-cell special {{ $sedangBerlangsung ? 'current' : '' }}">
                                            <p class="schedule-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</p>
                                            <p class="schedule-subject">{{ $jam->label ?: $jam->labelJenis() }}</p>
                                        </div>
                                    @elseif ($jadwal)
                                        <div class="schedule-cell lesson {{ $sedangBerlangsung ? 'current' : '' }}">
                                            <p class="schedule-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</p>
                                            <p class="schedule-subject">{{ $jadwal->guruMataPelajaran?->mataPelajaran?->nama ?? '-' }}</p>
                                            <span class="schedule-class">{{ $jadwal->kelas?->nama ?? '-' }}</span>
                                        </div>
                                    @else
                                        <div class="schedule-cell empty {{ $sedangBerlangsung ? 'current' : '' }}">
                                            <p class="schedule-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</p>
                                            <p class="schedule-empty-label">Kosong</p>
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
            <div class="schedule-mobile-tabs" role="tablist" aria-label="Pilih hari">
                @foreach ($daftarHari as $kodeHari => $labelHari)
                    <button
                        type="button"
                        class="schedule-mobile-tab {{ $kodeHari === $hariHariIni || ($hariHariIni === 'minggu' && $loop->first) ? 'active' : '' }}"
                        data-schedule-tab="{{ $kodeHari }}"
                        role="tab"
                        aria-selected="{{ $kodeHari === $hariHariIni || ($hariHariIni === 'minggu' && $loop->first) ? 'true' : 'false' }}"
                    >
                        {{ $labelHari }}
                    </button>
                @endforeach
            </div>

            @foreach ($daftarHari as $kodeHari => $labelHari)
                <div
                    class="schedule-mobile-day {{ $kodeHari === $hariHariIni || ($hariHariIni === 'minggu' && $loop->first) ? 'active' : '' }}"
                    data-schedule-day="{{ $kodeHari }}"
                    role="tabpanel"
                >
                    @forelse ($jamPerHari->get($kodeHari, collect()) as $jam)
                        @php
                            $jadwal = $jadwalSaya->get($jam->id);
                            $sedangBerlangsung = $jam->id === $jamAktifId;
                        @endphp
                        <div class="schedule-mobile-slot">
                            <div class="schedule-mobile-number">
                                <div>Jam {{ $jam->nomor_jam }}</div>
                                <div class="schedule-time">{{ $jam->formatJam($jam->jam_mulai) }}</div>
                            </div>

                            @if ($jam->jenis !== 'pelajaran')
                                <div class="schedule-cell special {{ $sedangBerlangsung ? 'current' : '' }}">
                                    <p class="schedule-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</p>
                                    <p class="schedule-subject">{{ $jam->label ?: $jam->labelJenis() }}</p>
                                </div>
                            @elseif ($jadwal)
                                <div class="schedule-cell lesson {{ $sedangBerlangsung ? 'current' : '' }}">
                                    <p class="schedule-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</p>
                                    <p class="schedule-subject">{{ $jadwal->guruMataPelajaran?->mataPelajaran?->nama ?? '-' }}</p>
                                    <span class="schedule-class">{{ $jadwal->kelas?->nama ?? '-' }}</span>
                                </div>
                            @else
                                <div class="schedule-cell empty {{ $sedangBerlangsung ? 'current' : '' }}">
                                    <p class="schedule-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</p>
                                    <p class="schedule-empty-label">Kosong</p>
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
        document.querySelectorAll('[data-schedule-tab]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('[data-schedule-tab]').forEach(function (item) {
                    item.classList.toggle('active', item === button);
                    item.setAttribute('aria-selected', item === button ? 'true' : 'false');
                });

                document.querySelectorAll('[data-schedule-day]').forEach(function (day) {
                    day.classList.toggle('active', day.dataset.scheduleDay === button.dataset.scheduleTab);
                });
            });
        });
    </script>
@endsection
