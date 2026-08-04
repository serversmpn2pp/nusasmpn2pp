@extends('layouts.app')

@section('title', 'Penugasan Guru Wali - NUSA')

@section('content')
    <style>
        .guardian-summary {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-bottom: 20px;
        }

        .guardian-summary-item {
            min-width: 0;
            padding: 17px 18px;
        }

        .guardian-summary-item + .guardian-summary-item {
            border-left: 1px solid var(--line);
        }

        .guardian-summary-value {
            color: var(--primary);
            display: block;
            font-size: 24px;
            font-weight: 800;
            line-height: 1.1;
        }

        .guardian-summary-label {
            color: var(--muted);
            display: block;
            font-size: 13px;
            margin-top: 5px;
        }

        .guardian-layout {
            align-items: start;
            display: grid;
            gap: 20px;
            grid-template-columns: minmax(350px, .95fr) minmax(0, 1.45fr);
        }

        .guardian-layout > .section-stack,
        .guardian-layout > .section-stack > .panel {
            min-width: 0;
        }

        .guardian-help {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
            margin: 6px 0 0;
        }

        .guardian-student-tools {
            display: grid;
            gap: 10px;
            grid-template-columns: minmax(0, 1.35fr) minmax(160px, .65fr);
            margin-top: 8px;
        }

        .guardian-filter-label {
            color: var(--muted);
            display: block;
            font-size: 12px;
            font-weight: 700;
            margin: 14px 0 7px;
            text-transform: uppercase;
        }

        .guardian-segmented {
            background: #eef3f8;
            border: 1px solid #dce5ee;
            border-radius: 7px;
            display: grid;
            gap: 3px;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            padding: 3px;
        }

        .guardian-segmented button {
            background: transparent;
            border: 0;
            border-radius: 5px;
            color: #536579;
            cursor: pointer;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            min-height: 34px;
            padding: 6px 8px;
        }

        .guardian-segmented button:hover {
            color: var(--primary);
        }

        .guardian-segmented button.is-active {
            background: #fff;
            box-shadow: 0 1px 3px rgba(21, 71, 122, .13);
            color: var(--primary);
        }

        .guardian-toggle {
            align-items: center;
            color: #40546a;
            cursor: pointer;
            display: flex;
            font-size: 13px;
            gap: 9px;
            margin: 12px 0;
        }

        .guardian-toggle input,
        .student-choice input {
            accent-color: var(--primary);
        }

        .guardian-selection-bar {
            align-items: center;
            background: #f8fafc;
            border: 1px solid var(--line);
            border-bottom: 0;
            border-radius: 8px 8px 0 0;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: space-between;
            padding: 9px 10px;
        }

        .guardian-selection-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .guardian-selection-action {
            background: #fff;
            border: 1px solid #cad6e2;
            border-radius: 5px;
            color: var(--primary);
            cursor: pointer;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            min-height: 31px;
            padding: 5px 9px;
        }

        .guardian-selection-action:hover {
            border-color: var(--primary);
        }

        .guardian-selection-count {
            color: var(--primary);
            font-size: 13px;
        }

        .student-choice-list {
            border: 1px solid var(--line);
            border-radius: 0 0 8px 8px;
            max-height: 470px;
            overflow-y: auto;
        }

        .student-group[hidden],
        .student-choice[hidden] {
            display: none !important;
        }

        .student-group-heading {
            align-items: center;
            background: #edf4fa;
            border-bottom: 1px solid #d8e4ef;
            color: var(--primary);
            display: flex;
            font-size: 12px;
            font-weight: 800;
            justify-content: space-between;
            padding: 8px 12px;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .student-choice {
            align-items: flex-start;
            border-bottom: 1px solid #e7edf3;
            cursor: pointer;
            display: grid;
            gap: 10px;
            grid-template-columns: 20px minmax(0, 1fr);
            padding: 10px 12px;
        }

        .student-choice:last-child {
            border-bottom: 0;
        }

        .student-choice:hover {
            background: #fbfdff;
        }

        .student-choice:has(input:checked) {
            background: #fffbea;
        }

        .student-choice strong,
        .student-choice span {
            display: block;
        }

        .student-choice-name {
            color: #1d2b3b;
            line-height: 1.35;
        }

        .student-choice-meta,
        .student-choice-assignment {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
            margin-top: 2px;
        }

        .student-choice-assignment {
            color: #805f00;
        }

        .guardian-transfer-note {
            background: #fff9dc;
            border: 1px solid #f0d76a;
            border-radius: 6px;
            color: #654f00;
            font-size: 13px;
            line-height: 1.5;
            margin-top: 12px;
            padding: 10px 12px;
        }

        .guardian-empty-filter {
            color: var(--muted);
            font-size: 13px;
            padding: 20px;
            text-align: center;
        }

        .guardian-list-filter {
            display: grid;
            gap: 12px;
            grid-template-columns: minmax(0, 1fr) minmax(190px, .75fr);
        }

        .guardian-list-actions {
            align-items: end;
            display: flex;
            gap: 7px;
            grid-column: 1 / -1;
            justify-content: flex-end;
        }

        @media (max-width: 1100px) {
            .guardian-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .guardian-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .guardian-summary-item:nth-child(3) {
                border-left: 0;
                border-top: 1px solid var(--line);
            }

            .guardian-summary-item:nth-child(4) {
                border-top: 1px solid var(--line);
            }

            .guardian-student-tools,
            .guardian-list-filter {
                grid-template-columns: 1fr;
            }

            .guardian-list-actions {
                align-items: stretch;
            }

            .guardian-list-actions .button {
                flex: 1;
            }
        }

        @media (max-width: 520px) {
            .guardian-segmented {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .guardian-selection-bar {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Penugasan Guru Wali</h1>
            <p class="page-subtitle">Tetapkan siswa lintas kelas kepada satu Guru Wali tanpa terikat rombel.</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <section class="guardian-summary" aria-label="Ringkasan penugasan Guru Wali">
        <div class="guardian-summary-item">
            <strong class="guardian-summary-value">{{ number_format($ringkasan['jumlah_siswa_aktif']) }}</strong>
            <span class="guardian-summary-label">Siswa aktif</span>
        </div>
        <div class="guardian-summary-item">
            <strong class="guardian-summary-value">{{ number_format($ringkasan['jumlah_ditugaskan']) }}</strong>
            <span class="guardian-summary-label">Sudah memiliki Guru Wali</span>
        </div>
        <div class="guardian-summary-item">
            <strong class="guardian-summary-value">{{ number_format($ringkasan['jumlah_belum_ditugaskan']) }}</strong>
            <span class="guardian-summary-label">Belum ditugaskan</span>
        </div>
        <div class="guardian-summary-item">
            <strong class="guardian-summary-value">{{ number_format($ringkasan['jumlah_guru_wali']) }}</strong>
            <span class="guardian-summary-label">Guru Wali aktif</span>
        </div>
    </section>

    <div class="guardian-layout">
        <form method="POST" action="{{ route('penugasan-guru-wali.store') }}" class="panel panel-pad" data-guardian-form>
            @csrf
            <h2 class="panel-title">Buat penugasan</h2>
            <p class="guardian-help">Pilih satu guru, kemudian tandai seluruh siswa yang menjadi tanggung jawabnya.</p>

            <div class="field" style="margin-top: 18px;">
                <label for="guru_wali_pegawai_id">Guru Wali</label>
                <select id="guru_wali_pegawai_id" name="guru_wali_pegawai_id" class="select" required data-guardian-select>
                    <option value="">Pilih pegawai</option>
                    @foreach ($daftarPegawai as $pegawai)
                        <option value="{{ $pegawai->id }}" @selected((string) old('guru_wali_pegawai_id') === (string) $pegawai->id)>
                            {{ $pegawai->nama_lengkap }} ({{ $pegawai->jumlah_siswa_wali_aktif }} siswa)
                        </option>
                    @endforeach
                </select>
                @error('guru_wali_pegawai_id')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="form-grid" style="margin-top: 14px;">
                <div class="field">
                    <label for="tanggal_mulai">Mulai bertugas</label>
                    <input id="tanggal_mulai" name="tanggal_mulai" type="date" value="{{ old('tanggal_mulai', now()->toDateString()) }}" class="input" required>
                    @error('tanggal_mulai')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="nomor_sk">Nomor SK</label>
                    <input id="nomor_sk" name="nomor_sk" value="{{ old('nomor_sk') }}" class="input" placeholder="Opsional">
                    @error('nomor_sk')<p class="error-text">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="field" style="margin-top: 16px;">
                <label for="cari_siswa_guru_wali">Pilih siswa</label>
                <div class="guardian-student-tools">
                    <input id="cari_siswa_guru_wali" type="search" class="input" placeholder="Cari nama atau NISN" data-student-search>
                    <select class="select" aria-label="Saring berdasarkan kelas" data-class-filter>
                        <option value="">Semua kelas</option>
                        @foreach ($daftarKelas as $kelas)
                            <option value="{{ $kelas->id }}" data-level="{{ $kelas->tingkat }}">
                                {{ [7 => 'VII', 8 => 'VIII', 9 => 'IX'][$kelas->tingkat] ?? $kelas->tingkat }} - {{ $kelas->nama }}
                            </option>
                        @endforeach
                        <option value="none" data-level="0">Belum ditempatkan</option>
                    </select>
                </div>

                <span class="guardian-filter-label">Tingkat</span>
                <div class="guardian-segmented" role="group" aria-label="Saring tingkat siswa" data-level-filter>
                    <button type="button" class="is-active" data-level="all">Semua</button>
                    <button type="button" data-level="7">VII</button>
                    <button type="button" data-level="8">VIII</button>
                    <button type="button" data-level="9">IX</button>
                    <button type="button" data-level="0">Belum kelas</button>
                </div>

                <label class="guardian-toggle">
                    <input type="checkbox" data-unassigned-filter>
                    Hanya tampilkan siswa yang belum memiliki Guru Wali
                </label>

                <div class="guardian-selection-bar">
                    <div class="guardian-selection-actions">
                        <button type="button" class="guardian-selection-action" data-select-visible>Pilih yang tampil</button>
                        <button type="button" class="guardian-selection-action" data-clear-selection>Kosongkan pilihan</button>
                    </div>
                    <strong class="guardian-selection-count" data-selected-count>0 siswa dipilih</strong>
                </div>

                @php
                    $namaTingkat = [7 => 'Tingkat VII', 8 => 'Tingkat VIII', 9 => 'Tingkat IX', 0 => 'Belum ditempatkan'];
                    $kelompokTingkat = $daftarSiswa->groupBy(function ($siswa) {
                        return $siswa->anggotaKelas->first()?->kelas?->tingkat ?? 0;
                    });
                @endphp

                <div class="student-choice-list" data-student-list>
                    @forelse ($kelompokTingkat as $tingkat => $siswaTingkat)
                        @php
                            $kelompokKelas = $siswaTingkat->groupBy(function ($siswa) {
                                return $siswa->anggotaKelas->first()?->kelas?->id ?? 'none';
                            });
                        @endphp

                        @foreach ($kelompokKelas as $kelasId => $siswaKelas)
                            @php
                                $kelas = $siswaKelas->first()->anggotaKelas->first()?->kelas;
                                $judulKelompok = ($namaTingkat[(int) $tingkat] ?? 'Tingkat ' . $tingkat) . ' / ' . ($kelas?->nama ?? 'Belum ditempatkan');
                            @endphp
                            <section class="student-group" data-student-group>
                                <div class="student-group-heading">
                                    <span>{{ $judulKelompok }}</span>
                                    <span data-group-count>{{ $siswaKelas->count() }} siswa</span>
                                </div>

                                @foreach ($siswaKelas as $siswa)
                                    @php
                                        $anggotaAktif = $siswa->anggotaKelas->first();
                                        $kelasAktif = $anggotaAktif?->kelas;
                                        $penugasanAktif = $siswa->penugasanGuruWaliSiswa->first();
                                        $guruAktif = $penugasanAktif?->guruWali;
                                    @endphp
                                    <label
                                        class="student-choice"
                                        data-student-choice
                                        data-search="{{ str($siswa->nama_lengkap . ' ' . $siswa->nisn . ' ' . $siswa->nis)->lower() }}"
                                        data-level="{{ $kelasAktif?->tingkat ?? 0 }}"
                                        data-class="{{ $kelasAktif?->id ?? 'none' }}"
                                        data-assigned="{{ $penugasanAktif ? '1' : '0' }}"
                                        data-guardian-id="{{ $guruAktif?->id }}"
                                        data-guardian-name="{{ $guruAktif?->nama_lengkap }}"
                                    >
                                        <input type="checkbox" name="siswa_ids[]" value="{{ $siswa->id }}" @checked(in_array($siswa->id, old('siswa_ids', [])))>
                                        <span>
                                            <strong class="student-choice-name">{{ $siswa->nama_lengkap }}</strong>
                                            <span class="student-choice-meta">{{ $kelasAktif?->nama ?? 'Belum ditempatkan' }} &middot; NISN {{ $siswa->nisn ?: '-' }}</span>
                                            <span class="student-choice-assignment" data-assignment-status @if (! $guruAktif) hidden @endif>
                                                @if ($guruAktif)Guru Wali saat ini: {{ $guruAktif->nama_lengkap }}@endif
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </section>
                        @endforeach
                    @empty
                        <div class="empty-state">Belum ada siswa aktif.</div>
                    @endforelse

                    <div class="guardian-empty-filter" data-empty-filter hidden>Tidak ada siswa yang sesuai dengan penyaring.</div>
                </div>
                @error('siswa_ids')<p class="error-text">{{ $message }}</p>@enderror
                @error('siswa_ids.*')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="guardian-transfer-note" data-transfer-note hidden>
                <strong data-transfer-count>0 siswa</strong> sudah mempunyai Guru Wali lain. Saat disimpan, penugasan lama akan diakhiri dan riwayatnya tetap tersimpan.
            </div>

            <div class="field" style="margin-top: 14px;">
                <label for="catatan">Catatan</label>
                <textarea id="catatan" name="catatan" class="textarea" placeholder="Opsional">{{ old('catatan') }}</textarea>
                @error('catatan')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <button class="button button-primary button-full" type="submit" style="margin-top: 16px;">Simpan penugasan</button>
        </form>

        <div class="section-stack">
            <form method="GET" class="panel panel-pad">
                <div class="guardian-list-filter">
                    <div class="field">
                        <label for="kata_kunci">Cari penugasan aktif</label>
                        <input id="kata_kunci" name="kata_kunci" value="{{ $kataKunci }}" class="input" placeholder="Siswa, NISN, atau Guru Wali">
                    </div>
                    <div class="field">
                        <label for="guru_wali_filter">Guru Wali</label>
                        <select id="guru_wali_filter" name="guru_wali_pegawai_id" class="select">
                            <option value="">Semua Guru Wali</option>
                            @foreach ($daftarPegawai as $pegawai)
                                <option value="{{ $pegawai->id }}" @selected((string) $guruWaliDipilih === (string) $pegawai->id)>{{ $pegawai->nama_lengkap }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="guardian-list-actions">
                        <a href="{{ route('penugasan-guru-wali.index') }}" class="button button-muted">Reset</a>
                        <button class="button button-dark" type="submit">Terapkan</button>
                    </div>
                </div>
            </form>

            <section class="panel">
                <div class="table-wrap desktop-only">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Guru Wali</th>
                                <th>Mulai</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($penugasan as $item)
                                <tr>
                                    <td>
                                        <p class="person-name">{{ $item->siswa?->nama_lengkap }}</p>
                                        <p class="person-meta">{{ $item->siswa?->anggotaKelas?->first()?->kelas?->nama ?: 'Belum ditempatkan' }} &middot; NISN {{ $item->siswa?->nisn ?: '-' }}</p>
                                    </td>
                                    <td>
                                        <p class="person-name">{{ $item->guruWali?->nama_lengkap }}</p>
                                        <p class="person-meta">{{ $item->guruWali?->nip ?: '-' }}</p>
                                    </td>
                                    <td>{{ $item->tanggal_mulai?->format('d/m/Y') }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('penugasan-guru-wali.destroy', $item) }}" onsubmit="return confirm('Akhiri penugasan Guru Wali ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button-danger button-sm" type="submit">Akhiri</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="empty-state">Belum ada penugasan Guru Wali.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mobile-only mobile-list">
                    @forelse ($penugasan as $item)
                        <article class="mobile-card">
                            <p class="person-name">{{ $item->siswa?->nama_lengkap }}</p>
                            <p class="person-meta">{{ $item->siswa?->anggotaKelas?->first()?->kelas?->nama ?: 'Belum ditempatkan' }} &middot; NISN {{ $item->siswa?->nisn ?: '-' }}</p>
                            <p class="person-meta" style="margin-top: 7px;">Guru Wali: {{ $item->guruWali?->nama_lengkap }}</p>
                            <p class="person-meta">Mulai {{ $item->tanggal_mulai?->format('d/m/Y') }}</p>
                            <form method="POST" action="{{ route('penugasan-guru-wali.destroy', $item) }}" style="margin-top: 12px;" onsubmit="return confirm('Akhiri penugasan Guru Wali ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="button button-danger button-sm" type="submit">Akhiri</button>
                            </form>
                        </article>
                    @empty
                        <div class="empty-state">Belum ada penugasan Guru Wali.</div>
                    @endforelse
                </div>
            </section>

            @if ($penugasan->hasPages()){{ $penugasan->links() }}@endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-guardian-form]');
            const search = document.querySelector('[data-student-search]');
            const classFilter = document.querySelector('[data-class-filter]');
            const unassignedFilter = document.querySelector('[data-unassigned-filter]');
            const guardianSelect = document.querySelector('[data-guardian-select]');
            const levelButtons = [...document.querySelectorAll('[data-level-filter] [data-level]')];
            const choices = [...document.querySelectorAll('[data-student-choice]')];
            const groups = [...document.querySelectorAll('[data-student-group]')];
            const selectedCount = document.querySelector('[data-selected-count]');
            const emptyFilter = document.querySelector('[data-empty-filter]');
            const transferNote = document.querySelector('[data-transfer-note]');
            const transferCount = document.querySelector('[data-transfer-count]');
            let activeLevel = 'all';

            const checkedChoices = () => choices.filter((choice) => choice.querySelector('input').checked);

            const updateAssignmentStatus = () => {
                const selectedGuardian = guardianSelect?.value || '';

                choices.forEach((choice) => {
                    const status = choice.querySelector('[data-assignment-status]');
                    const guardianName = choice.dataset.guardianName || '';
                    const guardianId = choice.dataset.guardianId || '';

                    if (!status || choice.dataset.assigned !== '1') {
                        if (status) status.hidden = true;
                        return;
                    }

                    status.hidden = false;
                    status.textContent = selectedGuardian !== '' && guardianId === selectedGuardian
                        ? 'Sudah berada pada Guru Wali yang dipilih'
                        : `Guru Wali saat ini: ${guardianName}`;
                });
            };

            const updateSelection = () => {
                const selected = checkedChoices();
                const selectedGuardian = guardianSelect?.value || '';
                const transferred = selected.filter((choice) => (
                    choice.dataset.assigned === '1'
                    && choice.dataset.guardianId !== ''
                    && choice.dataset.guardianId !== selectedGuardian
                ));

                if (selectedCount) selectedCount.textContent = `${selected.length} siswa dipilih`;
                if (transferCount) transferCount.textContent = `${transferred.length} siswa`;
                if (transferNote) transferNote.hidden = selectedGuardian === '' || transferred.length === 0;
            };

            const applyFilters = () => {
                const keyword = (search?.value || '').toLowerCase().trim();
                const selectedClass = classFilter?.value || '';
                const onlyUnassigned = Boolean(unassignedFilter?.checked);

                choices.forEach((choice) => {
                    const matchesKeyword = keyword === '' || choice.dataset.search.includes(keyword);
                    const matchesLevel = activeLevel === 'all' || choice.dataset.level === activeLevel;
                    const matchesClass = selectedClass === '' || choice.dataset.class === selectedClass;
                    const matchesAssignment = !onlyUnassigned || choice.dataset.assigned === '0';
                    choice.hidden = !(matchesKeyword && matchesLevel && matchesClass && matchesAssignment);
                });

                groups.forEach((group) => {
                    const visibleChoices = [...group.querySelectorAll('[data-student-choice]')]
                        .filter((choice) => !choice.hidden);
                    const count = group.querySelector('[data-group-count]');
                    group.hidden = visibleChoices.length === 0;
                    if (count) count.textContent = `${visibleChoices.length} siswa`;
                });

                if (emptyFilter) emptyFilter.hidden = choices.some((choice) => !choice.hidden);
            };

            levelButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    activeLevel = button.dataset.level;
                    levelButtons.forEach((item) => item.classList.toggle('is-active', item === button));

                    if (classFilter?.value) {
                        const selectedOption = classFilter.options[classFilter.selectedIndex];
                        if (activeLevel !== 'all' && selectedOption?.dataset.level !== activeLevel) {
                            classFilter.value = '';
                        }
                    }

                    [...(classFilter?.options || [])].forEach((option) => {
                        option.hidden = option.value !== '' && activeLevel !== 'all' && option.dataset.level !== activeLevel;
                    });

                    applyFilters();
                });
            });

            search?.addEventListener('input', applyFilters);
            classFilter?.addEventListener('change', applyFilters);
            unassignedFilter?.addEventListener('change', applyFilters);
            guardianSelect?.addEventListener('change', () => {
                updateAssignmentStatus();
                updateSelection();
            });

            choices.forEach((choice) => {
                choice.querySelector('input')?.addEventListener('change', updateSelection);
            });

            document.querySelector('[data-select-visible]')?.addEventListener('click', () => {
                choices.filter((choice) => !choice.hidden).forEach((choice) => {
                    choice.querySelector('input').checked = true;
                });
                updateSelection();
            });

            document.querySelector('[data-clear-selection]')?.addEventListener('click', () => {
                choices.forEach((choice) => {
                    choice.querySelector('input').checked = false;
                });
                updateSelection();
            });

            form?.addEventListener('submit', (event) => {
                const selected = checkedChoices();

                if (selected.length === 0) {
                    event.preventDefault();
                    window.alert('Pilih minimal satu siswa untuk ditugaskan.');
                    return;
                }

                const selectedGuardian = guardianSelect?.value || '';
                const transferred = selected.filter((choice) => (
                    choice.dataset.assigned === '1'
                    && choice.dataset.guardianId !== ''
                    && choice.dataset.guardianId !== selectedGuardian
                ));

                if (transferred.length > 0 && !window.confirm(`${transferred.length} siswa akan dipindahkan dari Guru Wali sebelumnya. Lanjutkan?`)) {
                    event.preventDefault();
                }
            });

            applyFilters();
            updateAssignmentStatus();
            updateSelection();
        });
    </script>
@endsection
