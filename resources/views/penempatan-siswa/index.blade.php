@extends('layouts.app')

@section('title', 'Penempatan Siswa - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $oldSiswaIds = collect(old('siswa_ids', []))->map(fn ($id) => (int) $id)->all();
        $kelasPenuh = $kelasDipilih && $kelasDipilih->kapasitas && $jumlahAnggotaKelas >= $kelasDipilih->kapasitas;
        $bolehKelolaKelas = auth()->user()?->memilikiIzin('kelas.kelola') ?? false;
        $bolehLihatSiswa = auth()->user()?->memilikiIzin(['siswa.lihat', 'siswa.kelola']) ?? false;
    @endphp

    <style>
        .placement-filter-grid {
            display: grid;
            grid-template-columns: minmax(180px, .8fr) minmax(180px, .8fr) minmax(220px, 1fr) auto;
            gap: 12px;
            align-items: end;
        }

        .placement-shell {
            display: grid;
            grid-template-columns: minmax(300px, .88fr) minmax(0, 1.35fr);
            gap: 18px;
            align-items: start;
        }

        .placement-side {
            position: sticky;
            top: 92px;
            display: grid;
            gap: 16px;
        }

        .placement-class-card {
            display: grid;
            gap: 12px;
        }

        .placement-class-title {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .available-list {
            display: grid;
            gap: 8px;
            max-height: 520px;
            overflow: auto;
            padding-right: 4px;
        }

        .available-check {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 10px;
            align-items: start;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 10px;
        }

        .available-check input {
            width: 18px;
            height: 18px;
            margin-top: 2px;
            accent-color: var(--primary);
        }

        .available-check.is-selected {
            border-color: rgba(21, 71, 122, .32);
            box-shadow: inset 3px 0 0 var(--accent);
        }

        .placement-member-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            gap: 8px;
        }

        .placement-table.compact-member-table {
            min-width: 0;
        }

        .compact-member-table th:first-child,
        .compact-member-table td:first-child {
            width: 120px;
        }

        .member-name-link {
            color: var(--primary);
            text-decoration: none;
        }

        .member-name-link:hover {
            text-decoration: underline;
        }

        .placement-note {
            border: 1px solid rgba(21, 71, 122, .14);
            border-radius: 8px;
            background: rgba(21, 71, 122, .04);
            padding: 12px;
        }

        @media (max-width: 1160px) {
            .placement-filter-grid,
            .placement-shell {
                grid-template-columns: 1fr;
            }

            .placement-side {
                position: static;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Penempatan siswa</h1>
        </div>

        <a href="{{ route('kelas.index') }}" class="button button-muted">Data kelas</a>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($cakupanWaliKelas ?? false)
        <div class="alert">Data penempatan dibatasi pada kelas yang Anda wali.</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Penempatan belum bisa disimpan.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('penempatan-siswa.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="placement-filter-grid">
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
                    @forelse ($kelas as $item)
                        <option value="{{ $item->id }}" @selected($kelasDipilih && $kelasDipilih->id === $item->id)>
                            {{ $item->nama }} - {{ $item->anggota_kelas_count }} siswa
                        </option>
                    @empty
                        <option value="">Belum ada kelas</option>
                    @endforelse
                </select>
            </div>

            <div class="field">
                <label for="kata_kunci_siswa">Cari siswa tersedia</label>
                <input id="kata_kunci_siswa" name="kata_kunci_siswa" type="search" value="{{ $kataKunciSiswa }}" class="input" placeholder="Nama, NIS, atau NISN">
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('penempatan-siswa.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Siswa aktif</p>
            <p class="stat-value">{{ $jumlahSiswaAktif }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Sudah ditempatkan</p>
            <p class="stat-value">{{ $jumlahDitempatkanTahunIni }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Belum ditempatkan</p>
            <p class="stat-value">{{ $jumlahBelumDitempatkanTahunIni }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Anggota kelas ini</p>
            <p class="stat-value">{{ $jumlahAnggotaKelas }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Sisa kursi</p>
            <p class="stat-value">{{ $sisaKursi === null ? '-' : $sisaKursi }}</p>
        </div>
    </div>

    @if ($tahunPelajaran->isEmpty())
        <section class="panel panel-pad">
            <h2 class="panel-title">Tahun pelajaran belum tersedia</h2>
            <p class="help-text" style="margin-top: 8px;">Buat tahun pelajaran terlebih dahulu sebelum menempatkan siswa ke kelas.</p>
        </section>
    @elseif ($kelas->isEmpty())
        <section class="panel panel-pad">
            <h2 class="panel-title">Kelas belum tersedia</h2>
            <p class="help-text" style="margin-top: 8px;">Tahun pelajaran yang dipilih belum memiliki kelas. Buat kelas terlebih dahulu.</p>
            @izin('kelas.kelola')
                <div class="form-actions" style="margin-top: 16px;">
                    <a href="{{ route('kelas.create', ['tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-primary">Tambah kelas</a>
                </div>
            @endizin
        </section>
    @elseif (! $kelasDipilih)
        <section class="panel panel-pad">
            <h2 class="panel-title">Pilih kelas</h2>
            <p class="help-text" style="margin-top: 8px;">Pilih tahun pelajaran dan kelas untuk melihat anggota kelas.</p>
        </section>
    @else
        <div class="placement-shell">
            <aside class="placement-side">
                <section class="panel panel-pad placement-class-card">
                    <div class="placement-class-title">
                        <div>
                            <p class="eyebrow">Kelas tujuan</p>
                            <h2 class="panel-title">{{ $kelasDipilih->nama }}</h2>
                        </div>
                        @if ($kelasDipilih->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Tahun</dt>
                            <dd>{{ $kelasDipilih->tahunPelajaran?->nama ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Wali kelas</dt>
                            <dd>{{ $kelasDipilih->waliKelas?->nama_lengkap ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Kapasitas</dt>
                            <dd>{{ $kelasDipilih->kapasitas ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Sisa kursi</dt>
                            <dd>{{ $sisaKursi === null ? '-' : $sisaKursi }}</dd>
                        </div>
                    </dl>

                    <div class="actions">
                        <a href="{{ route('kelas.show', $kelasDipilih) }}" class="button button-muted">Detail kelas</a>
                        @izin('kelas.kelola')
                            <a href="{{ route('kelas.edit', $kelasDipilih) }}" class="button button-dark">Edit kelas</a>
                        @endizin
                    </div>
                </section>

                @izin('kelas.kelola')
                    <section class="panel panel-pad">
                        <h2 class="panel-title">Masukkan siswa</h2>
                        <p class="help-text" style="margin-top: 6px;">Siswa yang tampil adalah siswa aktif yang belum punya kelas pada tahun pelajaran ini.</p>

                        @if ($kelasPenuh)
                            <div class="alert alert-danger" style="margin: 14px 0 0;">Kapasitas kelas sudah penuh.</div>
                        @else
                            <form action="{{ route('penempatan-siswa.store-massal') }}" method="POST" style="margin-top: 16px;" data-placement-form>
                                @csrf
                                <input type="hidden" name="kelas_id" value="{{ $kelasDipilih->id }}">

                                <div class="form-grid">
                                    <div class="field">
                                        <label for="tanggal_masuk">Tanggal masuk</label>
                                        <input id="tanggal_masuk" name="tanggal_masuk" type="date" value="{{ old('tanggal_masuk', $kelasDipilih->tahunPelajaran?->tanggal_mulai?->format('Y-m-d')) }}" class="input">
                                    </div>
                                    <div class="field">
                                        <label for="keterangan">Keterangan</label>
                                        <input id="keterangan" name="keterangan" type="text" value="{{ old('keterangan', 'Penempatan siswa') }}" class="input">
                                    </div>
                                </div>

                                <div class="actions" style="margin: 14px 0;">
                                    <button type="button" class="button button-muted" data-placement-select-all>Pilih semua</button>
                                    <button type="button" class="button button-muted" data-placement-clear>Pilih nol</button>
                                </div>

                                <div class="available-list">
                                    @forelse ($siswaTersedia as $siswa)
                                        @php
                                            $dipilih = in_array($siswa->id, $oldSiswaIds, true);
                                        @endphp
                                        <label class="available-check {{ $dipilih ? 'is-selected' : '' }}" for="siswa-tersedia-{{ $siswa->id }}">
                                            <input
                                                id="siswa-tersedia-{{ $siswa->id }}"
                                                type="checkbox"
                                                name="siswa_ids[]"
                                                value="{{ $siswa->id }}"
                                                data-placement-checkbox
                                                @checked($dipilih)
                                            >
                                            <span>
                                                <span class="person-name">{{ $siswa->nama_lengkap }}</span>
                                                <span class="person-meta">NIS {{ $teks($siswa->nis) }} - NISN {{ $teks($siswa->nisn) }}</span>
                                            </span>
                                        </label>
                                    @empty
                                        <div class="empty-state">Tidak ada siswa tersedia untuk pilihan ini.</div>
                                    @endforelse
                                </div>

                                @if ($siswaTersedia->isNotEmpty())
                                    <div class="form-actions" style="margin-top: 16px;">
                                        <button type="submit" class="button button-primary">Masukkan ke kelas</button>
                                    </div>
                                @endif
                            </form>
                        @endif
                    </section>
                @else
                    <section class="panel panel-pad">
                        <h2 class="panel-title">Daftar siswa tersedia</h2>
                        <p class="help-text" style="margin-top: 6px;">Akun ini hanya dapat melihat data penempatan. Perubahan anggota kelas membutuhkan izin kelola kelas.</p>
                    </section>
                @endizin
            </aside>

            <section class="panel">
                <div class="panel-pad" style="border-bottom: 1px solid var(--line);">
                    <h2 class="panel-title">Anggota {{ $kelasDipilih->nama }}</h2>
                    <p class="help-text" style="margin-top: 6px;">Nomor absen otomatis mengikuti urutan nama A-Z dan diperbarui setiap kali anggota kelas berubah.</p>
                </div>

                <div class="desktop-only table-wrap">
                    <table class="employee-table placement-table compact-member-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($anggotaKelas as $item)
                                <tr>
                                    <td data-label="No." style="width: 72px;">{{ $item->nomor_absen ?: '-' }}</td>
                                    <td data-label="Nama">
                                        @if ($bolehLihatSiswa && $item->siswa)
                                            <a href="{{ route('siswa.show', $item->siswa) }}" class="person-name member-name-link">{{ $item->siswa->nama_lengkap }}</a>
                                        @else
                                            <p class="person-name">{{ $item->siswa?->nama_lengkap ?: '-' }}</p>
                                        @endif

                                        @if ($bolehKelolaKelas)
                                            <div class="placement-member-actions" style="margin-top: 9px;">
                                                <form action="{{ route('anggota-kelas.destroy', $item) }}" method="POST" onsubmit="return confirm('Keluarkan siswa ini dari kelas? Data siswa tidak akan dihapus.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="kembali" value="penempatan">
                                                    <button type="submit" class="button button-danger button-sm">Keluarkan</button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="empty-state">Belum ada siswa di kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mobile-only mobile-list">
                    @forelse ($anggotaKelas as $item)
                        <article class="mobile-card">
                            <div class="mobile-card-head">
                                <div>
                                    @if ($bolehLihatSiswa && $item->siswa)
                                        <a href="{{ route('siswa.show', $item->siswa) }}" class="person-name member-name-link">{{ $item->siswa->nama_lengkap }}</a>
                                    @else
                                        <p class="person-name">{{ $item->siswa?->nama_lengkap ?: '-' }}</p>
                                    @endif
                                </div>
                                <span class="badge badge-active">No. {{ $item->nomor_absen ?: '-' }}</span>
                            </div>

                            @if ($bolehKelolaKelas)
                                <div class="placement-member-actions" style="margin-top: 14px; justify-content: flex-start;">
                                    <form action="{{ route('anggota-kelas.destroy', $item) }}" method="POST" onsubmit="return confirm('Keluarkan siswa ini dari kelas? Data siswa tidak akan dihapus.')">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="kembali" value="penempatan">
                                        <button type="submit" class="button button-danger">Keluarkan</button>
                                    </form>
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="empty-state">Belum ada siswa di kelas ini.</div>
                    @endforelse
                </div>
            </section>
        </div>
    @endif

    <script>
        (() => {
            const form = document.querySelector('[data-placement-form]');

            if (!form) {
                return;
            }

            const checkboxes = Array.from(form.querySelectorAll('[data-placement-checkbox]'));

            function updateSelectedState() {
                checkboxes.forEach((checkbox) => {
                    checkbox.closest('.available-check')?.classList.toggle('is-selected', checkbox.checked);
                });
            }

            form.querySelector('[data-placement-select-all]')?.addEventListener('click', () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = true;
                });
                updateSelectedState();
            });

            form.querySelector('[data-placement-clear]')?.addEventListener('click', () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = false;
                });
                updateSelectedState();
            });

            form.addEventListener('change', (event) => {
                if (event.target.matches('[data-placement-checkbox]')) {
                    updateSelectedState();
                }
            });

            updateSelectedState();
        })();
    </script>
@endsection
