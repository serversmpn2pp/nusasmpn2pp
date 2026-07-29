@extends('layouts.app')

@section('title', 'Susun Jadwal Kelas - NUSA')

@section('content')
    @php
        $jadwalAwal = $jadwalTersimpan
            ->mapWithKeys(fn ($item, $jamId) => [
                (string) $jamId => $item->mata_pelajaran_id
                    ? 'kegiatan:'.$item->mata_pelajaran_id
                    : 'guru:'.$item->guru_mata_pelajaran_id,
            ])
            ->all();
        $pilihanJadwal = old('jadwal', $jadwalAwal);
        $pilihanJadwal = is_array($pilihanJadwal) ? $pilihanJadwal : [];
        $jumlahSlotPelajaran = $jamPelajaran->where('jenis', 'pelajaran')->count();
        $jumlahTerisi = collect($pilihanJadwal)->filter(fn ($nilai) => filled($nilai))->count();
        $jumlahPilihanJadwal = $guruMataPelajaran->count() + $kegiatanJadwal->count();
        $hariPertama = (string) $hariTersedia->keys()->first();
    @endphp

    <style>
        .schedule-builder-filter {
            grid-template-columns: minmax(220px, 1fr) minmax(180px, .8fr) auto;
        }

        .schedule-builder-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--line);
        }

        .schedule-builder-toolbar .actions {
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .weekly-schedule-wrap {
            overflow: auto;
        }

        .weekly-schedule {
            width: 100%;
            min-width: 1180px;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
        }

        .weekly-schedule th,
        .weekly-schedule td {
            border-right: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            padding: 10px;
            vertical-align: top;
        }

        .weekly-schedule thead th {
            position: sticky;
            top: 0;
            z-index: 3;
            background: #15477a;
            color: #fff;
            text-align: left;
        }

        .weekly-schedule .schedule-time-heading,
        .weekly-schedule .schedule-time-cell {
            position: sticky;
            left: 0;
            width: 104px;
            z-index: 2;
        }

        .weekly-schedule .schedule-time-heading {
            z-index: 4;
        }

        .weekly-schedule .schedule-time-cell {
            background: #f7f9fc;
        }

        .schedule-slot {
            min-height: 104px;
        }

        .schedule-slot-time {
            display: block;
            margin-bottom: 7px;
            color: var(--muted);
            font-size: .78rem;
            font-weight: 700;
        }

        .schedule-slot .select {
            width: 100%;
            min-width: 0;
            font-size: .82rem;
        }

        .schedule-slot-locked,
        .schedule-slot-empty {
            display: flex;
            min-height: 78px;
            align-items: center;
            justify-content: center;
            padding: 10px;
            border: 1px dashed #c9d4df;
            background: #f7f9fc;
            color: var(--muted);
            text-align: center;
            font-size: .82rem;
            font-weight: 700;
        }

        .schedule-slot-locked {
            border-color: #ead576;
            background: #fff9db;
            color: #725b00;
        }

        .schedule-day-tabs {
            display: none;
        }

        .schedule-builder-status {
            color: var(--muted);
            font-size: .86rem;
        }

        .schedule-builder-stats {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        @media (max-width: 900px) {
            .schedule-builder-filter {
                grid-template-columns: 1fr;
            }

            .schedule-builder-toolbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .schedule-builder-toolbar .actions {
                width: 100%;
                justify-content: flex-start;
            }

            .schedule-builder-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 720px) {
            .schedule-builder-stats {
                grid-template-columns: 1fr;
            }

            .schedule-day-tabs {
                display: flex;
                gap: 8px;
                overflow-x: auto;
                padding: 12px 14px;
                border-bottom: 1px solid var(--line);
            }

            .schedule-day-tab {
                flex: 0 0 auto;
                min-width: 88px;
            }

            .schedule-day-tab.is-active {
                border-color: #15477a;
                background: #15477a;
                color: #fff;
            }

            .weekly-schedule {
                min-width: 100%;
                table-layout: fixed;
            }

            .weekly-schedule .schedule-day-heading,
            .weekly-schedule .schedule-day-cell {
                display: none;
            }

            .weekly-schedule .schedule-day-heading.is-active,
            .weekly-schedule .schedule-day-cell.is-active {
                display: table-cell;
            }

            .weekly-schedule .schedule-time-heading,
            .weekly-schedule .schedule-time-cell {
                width: 82px;
            }

            .weekly-schedule th,
            .weekly-schedule td {
                padding: 8px;
            }

            .schedule-slot {
                min-height: 94px;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Susun jadwal kelas</h1>
        </div>

        <div class="actions">
            <a href="{{ route('jadwal-pelajaran.index', ['tahun_pelajaran_id' => $tahunPelajaranId, 'kelas_id' => $kelasId]) }}" class="button button-muted">Kembali</a>
            <a href="{{ route('jam-pelajaran.index') }}" class="button button-muted">Jam pelajaran</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Jadwal belum dapat disimpan.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('jadwal-pelajaran.susun') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid schedule-builder-filter">
            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select" required>
                    @foreach ($tahunPelajaran as $item)
                        <option value="{{ $item->id }}" @selected((int) $tahunPelajaranId === (int) $item->id)>
                            {{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select" required>
                    <option value="">Pilih kelas</option>
                    @foreach ($kelas as $item)
                        <option value="{{ $item->id }}" @selected((int) $kelasId === (int) $item->id)>
                            {{ $item->nama }}{{ $item->tingkat ? ' - tingkat '.$item->tingkat : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan jadwal</button>
            </div>
        </div>
    </form>

    @if (! $kelasDipilih)
        <section class="panel panel-pad">
            <h2 class="panel-title">Pilih kelas</h2>
            <p class="help-text" style="margin-top: 8px;">Jadwal mingguan akan ditampilkan setelah tahun pelajaran dan kelas dipilih.</p>
        </section>
    @elseif ($jamPelajaran->isEmpty())
        <section class="panel panel-pad">
            <h2 class="panel-title">Jam pelajaran belum tersedia</h2>
            <p class="help-text" style="margin-top: 8px;">Tambahkan jam pelajaran terlebih dahulu sebelum menyusun jadwal kelas.</p>
        </section>
    @else
        <div class="stats-grid schedule-builder-stats">
            <div class="panel stat">
                <p class="stat-label">Kelas</p>
                <p class="stat-value" style="font-size: 1.35rem;">{{ $kelasDipilih->nama }}</p>
            </div>
            <div class="panel stat active">
                <p class="stat-label">Slot terisi</p>
                <p class="stat-value"><span data-filled-count>{{ $jumlahTerisi }}</span>/{{ $jumlahSlotPelajaran }}</p>
            </div>
            <div class="panel stat">
                <p class="stat-label">Guru mapel</p>
                <p class="stat-value">{{ $guruMataPelajaran->count() }}</p>
            </div>
            <div class="panel stat">
                <p class="stat-label">Kegiatan</p>
                <p class="stat-value">{{ $kegiatanJadwal->count() }}</p>
            </div>
        </div>

        @if ($jumlahPilihanJadwal === 0)
            <div class="alert alert-danger">
                Belum ada guru pengampu atau kegiatan aktif yang tersedia untuk {{ $kelasDipilih->nama }}.
            </div>
        @endif

        <form action="{{ route('jadwal-pelajaran.simpan-massal') }}" method="POST" data-schedule-form>
            @csrf
            <input type="hidden" name="tahun_pelajaran_id" value="{{ $tahunPelajaranId }}">
            <input type="hidden" name="kelas_id" value="{{ $kelasId }}">

            <section class="panel">
                <div class="schedule-builder-toolbar">
                    <div>
                        <h2 class="panel-title">Jadwal mingguan {{ $kelasDipilih->nama }}</h2>
                        <p class="schedule-builder-status" data-schedule-status>Perubahan belum disimpan: 0</p>
                    </div>

                    <div class="actions">
                        <button type="button" class="button button-muted" data-clear-schedule>Kosongkan semua</button>
                        <button type="submit" class="button button-primary" @disabled($jumlahPilihanJadwal === 0)>Simpan semua</button>
                    </div>
                </div>

                <div class="schedule-day-tabs" role="tablist" aria-label="Pilih hari">
                    @foreach ($hariTersedia as $kodeHari => $labelHari)
                        <button
                            type="button"
                            class="button button-muted schedule-day-tab {{ $kodeHari === $hariPertama ? 'is-active' : '' }}"
                            data-schedule-day-target="{{ $kodeHari }}"
                        >
                            {{ $labelHari }}
                        </button>
                    @endforeach
                </div>

                <div class="weekly-schedule-wrap">
                    <table class="weekly-schedule">
                        <thead>
                            <tr>
                                <th class="schedule-time-heading">Jam</th>
                                @foreach ($hariTersedia as $kodeHari => $labelHari)
                                    <th
                                        class="schedule-day-heading {{ $kodeHari === $hariPertama ? 'is-active' : '' }}"
                                        data-schedule-day="{{ $kodeHari }}"
                                    >
                                        {{ $labelHari }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($nomorJam as $nomor)
                                <tr>
                                    <th class="schedule-time-cell">
                                        <span class="person-name">Jam {{ $nomor }}</span>
                                    </th>

                                    @foreach ($hariTersedia as $kodeHari => $labelHari)
                                        @php
                                            $slot = $jamPerHari->get($kodeHari, collect())->get($nomor);
                                            $nilaiTerpilih = $slot
                                                ? (string) ($pilihanJadwal[(string) $slot->id] ?? '')
                                                : '';
                                        @endphp
                                        <td
                                            class="schedule-day-cell {{ $kodeHari === $hariPertama ? 'is-active' : '' }}"
                                            data-schedule-day="{{ $kodeHari }}"
                                        >
                                            @if (! $slot)
                                                <div class="schedule-slot-empty">Tidak ada slot</div>
                                            @elseif ($slot->jenis !== 'pelajaran')
                                                <div class="schedule-slot">
                                                    <span class="schedule-slot-time">{{ $slot->formatJam($slot->jam_mulai) }}-{{ $slot->formatJam($slot->jam_selesai) }}</span>
                                                    <div class="schedule-slot-locked">
                                                        {{ $slot->label ?: $slot->labelJenis() }}
                                                    </div>
                                                </div>
                                            @else
                                                <div class="schedule-slot">
                                                    <span class="schedule-slot-time">{{ $slot->formatJam($slot->jam_mulai) }}-{{ $slot->formatJam($slot->jam_selesai) }}</span>
                                                    <select
                                                        name="jadwal[{{ $slot->id }}]"
                                                        class="select"
                                                        aria-label="{{ $labelHari }} jam {{ $nomor }}"
                                                        data-schedule-select
                                                        @disabled($jumlahPilihanJadwal === 0)
                                                    >
                                                        <option value="">Kosong</option>
                                                        @if ($guruMataPelajaran->isNotEmpty())
                                                            <optgroup label="Pelajaran umum">
                                                                @foreach ($guruMataPelajaran as $penugasan)
                                                                    <option value="guru:{{ $penugasan->id }}" @selected($nilaiTerpilih === 'guru:'.$penugasan->id)>
                                                                        {{ $penugasan->mataPelajaran?->nama ?? '-' }} - {{ $penugasan->pegawai?->nama_lengkap ?? '-' }}
                                                                    </option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endif
                                                        @foreach (['Kokurikuler', 'Ekstrakurikuler'] as $kelompok)
                                                            @php
                                                                $kegiatanKelompok = $kegiatanJadwal->where('kelompok', $kelompok);
                                                            @endphp
                                                            @if ($kegiatanKelompok->isNotEmpty())
                                                                <optgroup label="{{ $kelompok }}">
                                                                    @foreach ($kegiatanKelompok as $kegiatan)
                                                                        <option value="kegiatan:{{ $kegiatan->id }}" @selected($nilaiTerpilih === 'kegiatan:'.$kegiatan->id)>
                                                                            {{ $kegiatan->nama }}
                                                                        </option>
                                                                    @endforeach
                                                                </optgroup>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                    @error('jadwal.'.$slot->id)
                                                        <p class="error-text">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="form-actions" style="padding: 16px 18px;">
                    <a href="{{ route('jadwal-pelajaran.index', ['tahun_pelajaran_id' => $tahunPelajaranId, 'kelas_id' => $kelasId]) }}" class="button button-muted">Batal</a>
                    <button type="submit" class="button button-primary" @disabled($jumlahPilihanJadwal === 0)>Simpan semua</button>
                </div>
            </section>
        </form>
    @endif

    @push('scripts')
        <script>
            (() => {
                const filterYear = document.getElementById('tahun_pelajaran_id');
                const filterClass = document.getElementById('kelas_id');
                const scheduleForm = document.querySelector('[data-schedule-form]');
                const selects = Array.from(document.querySelectorAll('[data-schedule-select]'));
                const filledCount = document.querySelector('[data-filled-count]');
                const status = document.querySelector('[data-schedule-status]');
                let changed = 0;

                filterYear?.addEventListener('change', () => {
                    filterClass.value = '';
                    filterYear.form.submit();
                });

                const updateFilled = () => {
                    if (filledCount) {
                        filledCount.textContent = String(selects.filter((select) => select.value).length);
                    }
                };

                const markChanged = () => {
                    changed += 1;
                    updateFilled();

                    if (status) {
                        status.textContent = `Perubahan belum disimpan: ${changed}`;
                    }
                };

                selects.forEach((select) => select.addEventListener('change', markChanged));

                document.querySelectorAll('[data-schedule-day-target]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const day = button.dataset.scheduleDayTarget;

                        document.querySelectorAll('[data-schedule-day-target]').forEach((item) => {
                            item.classList.toggle('is-active', item === button);
                        });
                        document.querySelectorAll('[data-schedule-day]').forEach((item) => {
                            item.classList.toggle('is-active', item.dataset.scheduleDay === day);
                        });
                    });
                });

                document.querySelector('[data-clear-schedule]')?.addEventListener('click', () => {
                    if (! window.confirm('Kosongkan seluruh slot pelajaran pada kelas ini?')) {
                        return;
                    }

                    selects.forEach((select) => {
                        select.value = '';
                    });
                    changed += 1;
                    updateFilled();

                    if (status) {
                        status.textContent = 'Seluruh slot akan dikosongkan setelah disimpan.';
                    }
                });

                scheduleForm?.addEventListener('submit', () => {
                    if (status) {
                        status.textContent = 'Menyimpan jadwal...';
                    }
                });
            })();
        </script>
    @endpush
@endsection
