@extends('layouts.app')

@section('title', 'Akun Pegawai - NUSA')

@section('content')
    <style>
        .role-checks {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            min-width: 360px;
        }

        .role-check {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 8px 10px;
            color: #3f3f46;
            font-size: .82rem;
            font-weight: 800;
        }

        .role-check input {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
            accent-color: var(--primary);
        }

        .role-check.is-base {
            background: var(--primary-soft);
            color: var(--primary-dark);
        }

        .role-form {
            display: grid;
            gap: 10px;
        }

        @media (max-width: 900px) {
            .role-checks {
                min-width: 0;
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Akses NUSA</p>
            <h1 class="page-title">Akun pegawai</h1>
        </div>

        <div class="actions">
            <form action="{{ route('akun-pegawai.buat-massal') }}" method="POST">
                @csrf
                <button type="submit" class="button button-primary">Buat akun semua</button>
            </form>
            <a href="{{ route('peran.index') }}" class="button button-muted">Kelola role</a>
            <a href="{{ route('pegawai.index') }}" class="button button-muted">Data pegawai</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if (session('gagal'))
        <div class="alert alert-danger">{{ session('gagal') }}</div>
    @endif

    @if (session('ringkasan_akun_pegawai'))
        @php
            $ringkasanImport = session('ringkasan_akun_pegawai');
        @endphp
        <div class="alert">
            <strong>{{ $ringkasanImport['dibuat'] }} akun dibuat.</strong>
            {{ $ringkasanImport['dilewati'] }} pegawai dilewati.
            @if (! empty($ringkasanImport['catatan']))
                <ul>
                    @foreach (array_slice($ringkasanImport['catatan'], 0, 6) as $catatan)
                        <li>{{ $catatan }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Pegawai aktif</p>
            <p class="stat-value">{{ $ringkasan['pegawai_aktif'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Punya NIP</p>
            <p class="stat-value">{{ $ringkasan['punya_nip'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Akun pegawai</p>
            <p class="stat-value">{{ $ringkasan['akun_pegawai'] }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Belum punya akun</p>
            <p class="stat-value">{{ $ringkasan['belum_akun'] }}</p>
        </div>
    </div>

    <form action="{{ route('akun-pegawai.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari pegawai</label>
                <input id="kata_kunci" type="search" name="kata_kunci" value="{{ $kataKunci }}" class="input" placeholder="Nama, NIP, atau jabatan">
            </div>

            <div class="field">
                <label for="status_akun">Status akun</label>
                <select id="status_akun" name="status_akun" class="select">
                    <option value="semua" @selected($statusAkun === 'semua')>Semua</option>
                    <option value="sudah" @selected($statusAkun === 'sudah')>Sudah punya akun</option>
                    <option value="belum" @selected($statusAkun === 'belum')>Belum punya akun</option>
                    <option value="tanpa_nip" @selected($statusAkun === 'tanpa_nip')>Tanpa NIP</option>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('akun-pegawai.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>

        <p class="help-text">
            Username akun pegawai memakai NIP tanpa spasi. Password default: <strong>{{ config('nusa.kata_sandi_default_pegawai') }}</strong>.
        </p>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width: 1280px;">
                <thead>
                    <tr>
                        <th>Pegawai</th>
                        <th>NIP</th>
                        <th>Username</th>
                        <th>Status akun</th>
                        <th>Role</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pegawai as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama_lengkap }}</p>
                                <p class="person-meta">{{ $item->jabatan_utama ?: 'Pegawai' }}</p>
                            </td>
                            <td>{{ $item->nip ?: '-' }}</td>
                            <td>{{ $item->pengguna?->username ?: '-' }}</td>
                            <td>
                                @if ($item->pengguna)
                                    <span class="badge {{ $item->pengguna->aktif ? 'badge-active' : 'badge-inactive' }}">
                                        {{ $item->pengguna->aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                @elseif ($item->nip)
                                    <span class="badge badge-warning">Belum ada akun</span>
                                @else
                                    <span class="badge badge-muted">NIP kosong</span>
                                @endif
                            </td>
                            <td>
                                @if ($item->pengguna)
                                    @php
                                        $peranAkun = $item->pengguna->daftarPeran->pluck('id')->all();
                                        $punyaPeranPegawai = $item->pengguna->daftarPeran->contains('kode', 'pegawai');
                                    @endphp
                                    <form action="{{ route('akun-pegawai.peran.update', $item->pengguna) }}" method="POST" class="role-form">
                                        @csrf
                                        @method('PATCH')
                                        <div class="role-checks">
                                            @foreach ($daftarPeran as $peran)
                                                @php
                                                    $peranDasar = $peran->kode === 'pegawai';
                                                @endphp
                                                <label class="role-check {{ $peranDasar ? 'is-base' : '' }}" for="peran-{{ $item->id }}-{{ $peran->id }}">
                                                    <input
                                                        id="peran-{{ $item->id }}-{{ $peran->id }}"
                                                        type="checkbox"
                                                        name="peran_ids[]"
                                                        value="{{ $peran->id }}"
                                                        @checked(in_array($peran->id, $peranAkun, true) || ($peranDasar && ! $punyaPeranPegawai))
                                                        @disabled($peranDasar)
                                                    >
                                                    <span>{{ $peran->nama }}</span>
                                                </label>
                                                @if ($peranDasar)
                                                    <input type="hidden" name="peran_ids[]" value="{{ $peran->id }}">
                                                @endif
                                            @endforeach
                                        </div>
                                        <button type="submit" class="button button-primary button-sm">Simpan role</button>
                                    </form>
                                @else
                                    <span class="muted">Buat akun dulu.</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions">
                                    @if (! $item->pengguna)
                                        <form action="{{ route('akun-pegawai.store', $item) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="button button-primary button-sm" @disabled(! $item->nip)>Buat</button>
                                        </form>
                                    @else
                                        <form action="{{ route('akun-pegawai.reset-password', $item->pengguna) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="button button-muted button-sm">Reset password</button>
                                        </form>

                                        <form action="{{ route('akun-pegawai.status', $item->pengguna) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="button {{ $item->pengguna->aktif ? 'button-danger' : 'button-primary' }} button-sm">
                                                {{ $item->pengguna->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada data pegawai yang sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($pegawai as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama_lengkap }}</p>
                            <p class="person-meta">NIP {{ $item->nip ?: '-' }}</p>
                        </div>

                        @if ($item->pengguna)
                            <span class="badge {{ $item->pengguna->aktif ? 'badge-active' : 'badge-inactive' }}">
                                {{ $item->pengguna->aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        @elseif ($item->nip)
                            <span class="badge badge-warning">Belum</span>
                        @else
                            <span class="badge badge-muted">Tanpa NIP</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Username</dt>
                            <dd>{{ $item->pengguna?->username ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Jabatan</dt>
                            <dd>{{ $item->jabatan_utama ?: '-' }}</dd>
                        </div>
                    </dl>

                    @if ($item->pengguna)
                        @php
                            $peranAkun = $item->pengguna->daftarPeran->pluck('id')->all();
                            $punyaPeranPegawai = $item->pengguna->daftarPeran->contains('kode', 'pegawai');
                        @endphp
                        <form action="{{ route('akun-pegawai.peran.update', $item->pengguna) }}" method="POST" class="role-form" style="margin-top: 12px;">
                            @csrf
                            @method('PATCH')
                            <div class="role-checks">
                                @foreach ($daftarPeran as $peran)
                                    @php
                                        $peranDasar = $peran->kode === 'pegawai';
                                    @endphp
                                    <label class="role-check {{ $peranDasar ? 'is-base' : '' }}" for="mobile-peran-{{ $item->id }}-{{ $peran->id }}">
                                        <input
                                            id="mobile-peran-{{ $item->id }}-{{ $peran->id }}"
                                            type="checkbox"
                                            name="peran_ids[]"
                                            value="{{ $peran->id }}"
                                            @checked(in_array($peran->id, $peranAkun, true) || ($peranDasar && ! $punyaPeranPegawai))
                                            @disabled($peranDasar)
                                        >
                                        <span>{{ $peran->nama }}</span>
                                    </label>
                                    @if ($peranDasar)
                                        <input type="hidden" name="peran_ids[]" value="{{ $peran->id }}">
                                    @endif
                                @endforeach
                            </div>
                            <button type="submit" class="button button-primary button-sm button-full">Simpan role</button>
                        </form>
                    @endif

                    <div class="actions" style="margin-top: 12px;">
                        @if (! $item->pengguna)
                            <form action="{{ route('akun-pegawai.store', $item) }}" method="POST">
                                @csrf
                                <button type="submit" class="button button-primary button-sm button-full" @disabled(! $item->nip)>Buat akun</button>
                            </form>
                        @else
                            <form action="{{ route('akun-pegawai.reset-password', $item->pengguna) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="button button-muted button-sm button-full">Reset password</button>
                            </form>
                            <form action="{{ route('akun-pegawai.status', $item->pengguna) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="button {{ $item->pengguna->aktif ? 'button-danger' : 'button-primary' }} button-sm button-full">
                                    {{ $item->pengguna->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada data pegawai yang sesuai filter.</div>
            @endforelse
        </div>
    </section>

    @if ($pegawai->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $pegawai->currentPage() }} dari {{ $pegawai->lastPage() }}
            </div>
            <div class="actions">
                @if ($pegawai->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $pegawai->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($pegawai->hasMorePages())
                    <a href="{{ $pegawai->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
