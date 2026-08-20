@extends('layouts.app')

@section('title', 'Akun Orang Tua - NUSA')

@section('content')
    <style>
        .parent-account-filter {
            grid-template-columns: minmax(250px, 1fr) 220px 190px auto;
        }

        .parent-account-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .parent-account-actions form {
            margin: 0;
        }

        .parent-account-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            padding: 16px 18px;
            border: 1px solid #d7e2ec;
            border-left: 4px solid var(--secondary);
            border-radius: 8px;
            background: #f8fbfe;
        }

        .parent-account-summary strong {
            display: block;
            color: var(--primary-dark);
            font-size: 1.05rem;
        }

        .parent-account-summary p {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: .86rem;
        }

        .initial-password-state {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #166534;
            font-size: .8rem;
            font-weight: 800;
        }

        .initial-password-state::before {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            content: "";
        }

        .initial-password-state.changed {
            color: #64748b;
        }

        .initial-password-state.changed::before {
            background: #94a3b8;
        }

        @media (max-width: 760px) {
            .parent-account-filter {
                grid-template-columns: 1fr;
            }

            .parent-account-summary {
                align-items: stretch;
                flex-direction: column;
            }

            .parent-account-summary .button {
                width: 100%;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Akses NUSA</p>
            <h1 class="page-title">Akun orang tua</h1>
            <p class="page-description">Buat akun orang tua dari NISN anak dan siapkan kredensial per kelas.</p>
        </div>

        <div class="actions">
            @if ($kelasDipilih)
                @izin('akun_orang_tua.kelola')
                    <form action="{{ route('akun-orang-tua.buat-massal', $kelasDipilih) }}" method="POST">
                        @csrf
                        <button type="submit" class="button button-primary">Buat akun kelas</button>
                    </form>
                @endizin
                @izin('akun_orang_tua.cetak', 'akun_orang_tua.kelola')
                    <a href="{{ route('akun-orang-tua.cetak', $kelasDipilih) }}" class="button button-dark" target="_blank" rel="noopener">
                        Cetak daftar akun
                    </a>
                @endizin
            @endif
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if (! $tahunPelajaranAktif)
        <div class="alert alert-danger">Belum ada tahun pelajaran aktif yang dapat diakses.</div>
    @endif

    @if ($errors->has('akun'))
        <div class="alert alert-danger">{{ $errors->first('akun') }}</div>
    @endif

    @if (session('ringkasan_akun_orang_tua'))
        @php($hasil = session('ringkasan_akun_orang_tua'))
        <div class="alert">
            <strong>{{ $hasil['dibuat'] }} akun dibuat.</strong>
            {{ $hasil['dilewati'] }} siswa dilewati.
            @if (! empty($hasil['catatan']))
                <ul>
                    @foreach (array_slice($hasil['catatan'], 0, 6) as $catatan)
                        <li>{{ $catatan }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">{{ $kelasDipilih ? 'Siswa di kelas' : 'Siswa tahun aktif' }}</p>
            <p class="stat-value">{{ $ringkasan['jumlah_siswa'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Akun aktif</p>
            <p class="stat-value">{{ $ringkasan['akun_aktif'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Akun nonaktif</p>
            <p class="stat-value">{{ $ringkasan['akun_nonaktif'] }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Belum punya akun</p>
            <p class="stat-value">{{ $ringkasan['belum_akun'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">NISN belum diisi</p>
            <p class="stat-value">{{ $ringkasan['tanpa_nisn'] }}</p>
        </div>
    </div>

    <form action="{{ route('akun-orang-tua.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 22px;" data-auto-filter>
        <div class="filter-grid parent-account-filter">
            <div class="field">
                <label for="kata_kunci">Cari siswa atau orang tua</label>
                <input id="kata_kunci" type="search" name="kata_kunci" value="{{ $kataKunci }}" class="input" placeholder="Nama siswa, orang tua, NIS, atau NISN" autocomplete="off" data-auto-search>
            </div>
            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select" data-auto-submit>
                    <option value="">Semua kelas</option>
                    @forelse ($daftarKelas as $kelas)
                        <option value="{{ $kelas->id }}" @selected((int) $kelasId === (int) $kelas->id)>
                            {{ $kelas->nama }} ({{ $kelas->jumlah_siswa_aktif }} siswa)
                        </option>
                    @empty
                        <option value="" disabled>Belum ada kelas yang dapat diakses</option>
                    @endforelse
                </select>
            </div>
            <div class="field">
                <label for="status_akun">Status akun</label>
                <select id="status_akun" name="status_akun" class="select" data-auto-submit>
                    <option value="semua" @selected($statusAkun === 'semua')>Semua status</option>
                    <option value="aktif" @selected($statusAkun === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($statusAkun === 'nonaktif')>Nonaktif</option>
                    <option value="belum" @selected($statusAkun === 'belum')>Belum punya akun</option>
                    <option value="tanpa_nisn" @selected($statusAkun === 'tanpa_nisn')>NISN belum diisi</option>
                </select>
            </div>
            <div class="actions">
                <a href="{{ route('akun-orang-tua.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    @if ($tahunPelajaranAktif)
        <div class="parent-account-summary">
            <div>
                <strong>{{ $kelasDipilih?->nama ?: 'Semua kelas' }}</strong>
                <p>{{ $tahunPelajaranAktif->nama }}. Username memakai format ORT-NISN dan password awal terdiri dari 8 angka acak.</p>
            </div>
            @if ($kelasDipilih)
                @izin('akun_orang_tua.cetak', 'akun_orang_tua.kelola')
                    <a href="{{ route('akun-orang-tua.cetak', $kelasDipilih) }}" class="button button-muted" target="_blank" rel="noopener">Pratinjau cetak</a>
                @endizin
            @endif
        </div>
    @endif

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width: 980px;">
                <thead>
                    <tr>
                        <th style="width: 65px;">No.</th>
                        <th>Siswa</th>
                        <th>Orang tua/wali</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Password awal</th>
                        <th style="width: 260px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($anggotaKelas as $anggota)
                        @php($siswa = $anggota->siswa)
                        @php($orangTua = $siswa?->orangTuaWali->first(fn ($item) => (bool) $item->pivot?->utama) ?: $siswa?->orangTuaWali->first())
                        @php($akun = $orangTua?->pengguna)
                        <tr>
                            <td>{{ $anggota->nomor_absen ?: $anggotaKelas->firstItem() + $loop->index }}</td>
                            <td>
                                <p class="person-name">{{ $siswa?->nama_lengkap ?: '-' }}</p>
                                <p class="person-meta">{{ $anggota->kelas?->nama ?: '-' }} &middot; NISN {{ $siswa?->nisn ?: '-' }}</p>
                            </td>
                            <td>
                                <p class="person-name">{{ $orangTua?->nama_lengkap ?: 'Belum terhubung' }}</p>
                                @if ($orangTua)
                                    <p class="person-meta">{{ str($orangTua->pivot?->hubungan ?: 'wali')->headline() }}{{ $orangTua->nomor_wa ? ' · '.$orangTua->nomor_wa : '' }}</p>
                                @endif
                            </td>
                            <td>{{ $akun?->username ?: ($siswa?->nisn ? 'ORT-'.$siswa->nisn : '-') }}</td>
                            <td>
                                @if ($akun)
                                    <span class="badge {{ $akun->aktif ? 'badge-active' : 'badge-inactive' }}">{{ $akun->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                                @elseif ($siswa?->nisn)
                                    <span class="badge badge-warning">Belum ada akun</span>
                                @else
                                    <span class="badge badge-muted">NISN kosong</span>
                                @endif
                            </td>
                            <td>
                                @if ($akun?->kata_sandi_awal)
                                    <span class="initial-password-state">Siap dibagikan</span>
                                @elseif ($akun)
                                    <span class="initial-password-state changed">Sudah diganti</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @izin('akun_orang_tua.kelola')
                                    <div class="parent-account-actions">
                                        @if (! $akun)
                                            <form action="{{ route('akun-orang-tua.store', $siswa) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="button button-primary button-sm" @disabled(! $siswa?->nisn)>Buat akun</button>
                                            </form>
                                        @else
                                            <form action="{{ route('akun-orang-tua.reset-password', $akun) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="button button-muted button-sm">Reset password</button>
                                            </form>
                                            <form action="{{ route('akun-orang-tua.status', $akun) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="button {{ $akun->aktif ? 'button-danger' : 'button-primary' }} button-sm">{{ $akun->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                            </form>
                                        @endif
                                    </div>
                                @else
                                    <span class="muted">Dikelola administrator.</span>
                                @endizin
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">Belum ada siswa yang sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($anggotaKelas as $anggota)
                @php($siswa = $anggota->siswa)
                @php($orangTua = $siswa?->orangTuaWali->first(fn ($item) => (bool) $item->pivot?->utama) ?: $siswa?->orangTuaWali->first())
                @php($akun = $orangTua?->pengguna)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $siswa?->nama_lengkap ?: '-' }}</p>
                            <p class="person-meta">{{ $anggota->kelas?->nama ?: '-' }} &middot; NISN {{ $siswa?->nisn ?: '-' }}</p>
                        </div>
                        @if ($akun)
                            <span class="badge {{ $akun->aktif ? 'badge-active' : 'badge-inactive' }}">{{ $akun->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                        @else
                            <span class="badge badge-warning">Belum ada akun</span>
                        @endif
                    </div>
                    <dl class="mobile-details">
                        <div><dt>Orang tua/wali</dt><dd>{{ $orangTua?->nama_lengkap ?: 'Belum terhubung' }}</dd></div>
                        <div><dt>Username</dt><dd>{{ $akun?->username ?: ($siswa?->nisn ? 'ORT-'.$siswa->nisn : '-') }}</dd></div>
                        <div><dt>Password</dt><dd>{{ $akun?->kata_sandi_awal ? 'Siap dibagikan' : ($akun ? 'Sudah diganti' : '-') }}</dd></div>
                    </dl>
                    @izin('akun_orang_tua.kelola')
                        <div class="parent-account-actions">
                            @if (! $akun)
                                <form action="{{ route('akun-orang-tua.store', $siswa) }}" method="POST" style="width:100%;">
                                    @csrf
                                    <button type="submit" class="button button-primary button-sm button-full" @disabled(! $siswa?->nisn)>Buat akun</button>
                                </form>
                            @else
                                <form action="{{ route('akun-orang-tua.reset-password', $akun) }}" method="POST" style="flex:1;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="button button-muted button-sm button-full">Reset password</button>
                                </form>
                                <form action="{{ route('akun-orang-tua.status', $akun) }}" method="POST" style="flex:1;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="button {{ $akun->aktif ? 'button-danger' : 'button-primary' }} button-sm button-full">{{ $akun->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                            @endif
                        </div>
                    @endizin
                </article>
            @empty
                <div class="empty-state">Belum ada siswa yang sesuai filter.</div>
            @endforelse
        </div>
    </section>

    {{ $anggotaKelas->links() }}
@endsection
