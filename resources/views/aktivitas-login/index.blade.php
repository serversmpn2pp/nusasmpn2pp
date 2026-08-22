@extends('layouts.app')

@section('title', 'Aktivitas Login - NUSA')

@section('content')
    <style>
        .login-activity-tabs {
            display: inline-flex;
            gap: 4px;
            margin-bottom: 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 4px;
        }

        .login-activity-tab {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            padding: 7px 14px;
            color: var(--muted);
            font-size: .88rem;
            font-weight: 800;
        }

        .login-activity-tab.active {
            background: var(--primary);
            color: #fff;
        }

        .login-activity-stats {
            margin-bottom: 18px;
        }

        .login-activity-stat {
            position: relative;
            overflow: hidden;
        }

        .login-activity-stat .stat-note {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: .78rem;
            line-height: 1.45;
        }

        .login-activity-stat::before {
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: var(--primary);
            content: '';
        }

        .login-activity-stat.success::before {
            background: #16835f;
        }

        .login-activity-stat.warning::before {
            background: var(--accent);
        }

        .login-activity-stat.danger::before {
            background: #c54848;
        }

        .login-activity-filter {
            margin-bottom: 18px;
        }

        .login-activity-filter-grid {
            display: grid;
            grid-template-columns: minmax(220px, 1.5fr) repeat(2, minmax(170px, .8fr)) auto;
            gap: 12px;
            align-items: end;
        }

        .login-activity-filter-grid.history {
            grid-template-columns: minmax(210px, 1.25fr) repeat(2, minmax(150px, .75fr)) repeat(2, minmax(145px, .7fr)) auto;
        }

        .login-activity-filter-grid .field {
            min-width: 0;
        }

        .login-activity-filter-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .login-activity-table-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            border-bottom: 1px solid var(--line);
            padding: 17px 18px;
        }

        .login-activity-table-head p {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: .85rem;
        }

        .login-activity-time {
            display: grid;
            gap: 2px;
        }

        .login-activity-time strong {
            color: var(--text);
            font-size: .9rem;
        }

        .login-activity-time small,
        .login-activity-device {
            color: var(--muted);
            font-size: .78rem;
        }

        .login-activity-role {
            max-width: 250px;
            overflow-wrap: anywhere;
        }

        .login-activity-counts {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .login-activity-mobile {
            display: none;
            gap: 10px;
            padding: 12px;
        }

        .login-activity-mobile-card {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 15px;
        }

        .login-activity-mobile-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .login-activity-mobile-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
            border-top: 1px solid var(--line);
            padding-top: 12px;
        }

        .login-activity-mobile-meta span {
            display: block;
            margin-bottom: 3px;
            color: var(--muted);
            font-size: .74rem;
        }

        .login-activity-mobile-meta strong {
            color: var(--text);
            font-size: .84rem;
            overflow-wrap: anywhere;
        }

        @media (max-width: 1120px) {
            .login-activity-filter-grid,
            .login-activity-filter-grid.history {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .login-activity-filter-grid .field:first-child,
            .login-activity-filter-grid.history .field:first-child {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 760px) {
            .login-activity-tabs {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                width: 100%;
            }

            .login-activity-filter-grid,
            .login-activity-filter-grid.history {
                grid-template-columns: 1fr;
            }

            .login-activity-filter-grid .field:first-child,
            .login-activity-filter-grid.history .field:first-child {
                grid-column: auto;
            }

            .login-activity-filter-actions,
            .login-activity-filter-grid .button {
                width: 100%;
                justify-content: center;
            }

            .login-activity-table-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .login-activity-desktop {
                display: none;
            }

            .login-activity-mobile {
                display: grid;
            }
        }
    </style>

    @php
        $parameterDasar = array_filter([
            'kata_kunci' => $kataKunci ?: null,
            'jenis_akun' => $jenisAkun !== 'semua' ? $jenisAkun : null,
        ]);
        $labelPeran = static function ($pengguna): string {
            $peran = $pengguna?->daftarPeran?->pluck('nama')->filter()->join(', ');

            return $peran ?: str((string) ($pengguna?->peran ?: 'tanpa role'))->replace('_', ' ')->title()->toString();
        };
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Keamanan Sistem</p>
            <h1 class="page-title">Aktivitas Login</h1>
            <p class="page-subtitle">Pantau login terakhir dan percobaan masuk akun NUSA tanpa menyimpan kata sandi.</p>
        </div>
    </div>

    <div class="stats-grid login-activity-stats">
        <article class="panel stat login-activity-stat">
            <p class="stat-label">Seluruh akun</p>
            <p class="stat-value">{{ $ringkasan['jumlah_akun'] }}</p>
            <p class="stat-note">Akun yang terdaftar di NUSA</p>
        </article>
        <article class="panel stat login-activity-stat success">
            <p class="stat-label">Login hari ini</p>
            <p class="stat-value">{{ $ringkasan['login_hari_ini'] }}</p>
            <p class="stat-note">Pengguna unik yang berhasil masuk</p>
        </article>
        <article class="panel stat login-activity-stat warning">
            <p class="stat-label">Belum pernah login</p>
            <p class="stat-value">{{ $ringkasan['belum_pernah_login'] }}</p>
            <p class="stat-note">Akun yang belum digunakan</p>
        </article>
        <article class="panel stat login-activity-stat danger">
            <p class="stat-label">Gagal hari ini</p>
            <p class="stat-value">{{ $ringkasan['gagal_hari_ini'] }}</p>
            <p class="stat-note">Percobaan login yang tidak berhasil</p>
        </article>
    </div>

    <nav class="login-activity-tabs" aria-label="Tampilan aktivitas login">
        <a
            href="{{ route('aktivitas-login.index', array_merge($parameterDasar, ['tampilan' => 'pengguna'])) }}"
            class="login-activity-tab {{ $tampilan === 'pengguna' ? 'active' : '' }}"
        >Daftar Pengguna</a>
        <a
            href="{{ route('aktivitas-login.index', array_merge($parameterDasar, ['tampilan' => 'riwayat'])) }}"
            class="login-activity-tab {{ $tampilan === 'riwayat' ? 'active' : '' }}"
        >Riwayat Percobaan</a>
    </nav>

    @if ($errors->any())
        <div class="alert alert-danger">Filter belum dapat diterapkan. Periksa kembali periode yang dipilih.</div>
    @endif

    <form method="GET" action="{{ route('aktivitas-login.index') }}" class="panel panel-pad login-activity-filter" data-login-filter>
        <input type="hidden" name="tampilan" value="{{ $tampilan }}">

        <div class="login-activity-filter-grid {{ $tampilan === 'riwayat' ? 'history' : '' }}">
            <div class="field">
                <label for="kata_kunci">Cari pengguna</label>
                <input
                    id="kata_kunci"
                    name="kata_kunci"
                    type="search"
                    class="input"
                    value="{{ $kataKunci }}"
                    placeholder="Nama atau username"
                    autocomplete="off"
                    data-login-search
                >
            </div>

            <div class="field">
                <label for="jenis_akun">Jenis akun</label>
                <select id="jenis_akun" name="jenis_akun" class="select" data-login-auto>
                    @foreach ($daftarJenisAkun as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($jenisAkun === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($tampilan === 'pengguna')
                <div class="field">
                    <label for="status_login">Riwayat penggunaan</label>
                    <select id="status_login" name="status_login" class="select" data-login-auto>
                        <option value="semua" @selected($statusLogin === 'semua')>Semua akun</option>
                        <option value="pernah" @selected($statusLogin === 'pernah')>Pernah login</option>
                        <option value="belum" @selected($statusLogin === 'belum')>Belum pernah login</option>
                    </select>
                </div>
            @else
                <div class="field">
                    <label for="status_percobaan">Hasil percobaan</label>
                    <select id="status_percobaan" name="status_percobaan" class="select" data-login-auto>
                        <option value="semua" @selected($statusPercobaan === 'semua')>Semua hasil</option>
                        <option value="berhasil" @selected($statusPercobaan === 'berhasil')>Berhasil</option>
                        <option value="gagal" @selected($statusPercobaan === 'gagal')>Gagal</option>
                    </select>
                </div>
                <div class="field">
                    <label for="tanggal_mulai">Mulai tanggal</label>
                    <input id="tanggal_mulai" name="tanggal_mulai" type="date" class="input" value="{{ $tanggalMulai }}" data-login-auto>
                </div>
                <div class="field">
                    <label for="tanggal_selesai">Sampai tanggal</label>
                    <input id="tanggal_selesai" name="tanggal_selesai" type="date" class="input" value="{{ $tanggalSelesai }}" data-login-auto>
                </div>
            @endif

            <div class="login-activity-filter-actions">
                <a href="{{ route('aktivitas-login.index', ['tampilan' => $tampilan]) }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    @if ($tampilan === 'pengguna')
        <section class="panel">
            <div class="login-activity-table-head">
                <div>
                    <h2 class="panel-title">Daftar pengguna</h2>
                    <p>{{ $daftarPengguna->total() }} akun sesuai filter.</p>
                </div>
                <span class="badge badge-muted">Urut dari login terbaru</span>
            </div>

            <div class="login-activity-desktop table-wrap">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th>Pengguna</th>
                            <th>Jenis akun & role</th>
                            <th>Status</th>
                            <th>Login terakhir</th>
                            <th>Aktivitas</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftarPengguna as $item)
                            <tr>
                                <td>
                                    <p class="person-name">{{ $item->nama }}</p>
                                    <p class="person-meta">{{ $item->username }}</p>
                                </td>
                                <td>
                                    <p class="person-name">{{ $item->labelJenisAkun() }}</p>
                                    <p class="person-meta login-activity-role">{{ $labelPeran($item) }}</p>
                                </td>
                                <td>
                                    <span class="badge {{ $item->aktif ? 'badge-active' : 'badge-inactive' }}">
                                        {{ $item->aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td>
                                    @if ($item->terakhir_login_pada)
                                        <div class="login-activity-time">
                                            <strong>{{ $item->terakhir_login_pada->locale('id')->translatedFormat('d M Y, H:i') }}</strong>
                                            <small>{{ $item->terakhir_login_pada->diffForHumans() }}</small>
                                            @if ($item->loginBerhasilTerbaru)
                                                <small>{{ $item->loginBerhasilTerbaru->labelPerangkat() }}</small>
                                            @endif
                                        </div>
                                    @else
                                        <span class="badge badge-inactive">Belum pernah login</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="login-activity-counts">
                                        <span class="badge badge-active">{{ $item->jumlah_login_berhasil }} berhasil</span>
                                        @if ($item->jumlah_login_gagal > 0)
                                            <span class="badge badge-danger">{{ $item->jumlah_login_gagal }} gagal</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="actions" style="justify-content:flex-end;">
                                        <a
                                            href="{{ route('aktivitas-login.index', ['tampilan' => 'riwayat', 'kata_kunci' => $item->username]) }}"
                                            class="button button-muted button-sm"
                                        >Lihat riwayat</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">Tidak ada akun yang sesuai dengan filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="login-activity-mobile">
                @forelse ($daftarPengguna as $item)
                    <article class="login-activity-mobile-card">
                        <div class="login-activity-mobile-head">
                            <div>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->username }} &middot; {{ $item->labelJenisAkun() }}</p>
                            </div>
                            <span class="badge {{ $item->aktif ? 'badge-active' : 'badge-inactive' }}">{{ $item->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>

                        <div class="login-activity-mobile-meta">
                            <div><span>Role</span><strong>{{ $labelPeran($item) }}</strong></div>
                            <div>
                                <span>Login terakhir</span>
                                <strong>{{ $item->terakhir_login_pada?->locale('id')->translatedFormat('d M Y, H:i') ?: 'Belum pernah login' }}</strong>
                            </div>
                            <div><span>Berhasil</span><strong>{{ $item->jumlah_login_berhasil }} percobaan</strong></div>
                            <div><span>Gagal</span><strong>{{ $item->jumlah_login_gagal }} percobaan</strong></div>
                        </div>

                        <div class="actions" style="margin-top:14px;">
                            <a href="{{ route('aktivitas-login.index', ['tampilan' => 'riwayat', 'kata_kunci' => $item->username]) }}" class="button button-muted button-sm">Lihat riwayat</a>
                        </div>
                    </article>
                @empty
                    <div class="empty-state">Tidak ada akun yang sesuai dengan filter.</div>
                @endforelse
            </div>
        </section>

        @if ($daftarPengguna->hasPages())
            {{ $daftarPengguna->links() }}
        @endif
    @else
        <section class="panel">
            <div class="login-activity-table-head">
                <div>
                    <h2 class="panel-title">Riwayat percobaan login</h2>
                    <p>{{ $daftarRiwayat->total() }} percobaan sesuai filter.</p>
                </div>
                <span class="badge badge-muted">Data terbaru di atas</span>
            </div>

            <div class="login-activity-desktop table-wrap">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                            <th>Jenis akun</th>
                            <th>Hasil</th>
                            <th>Perangkat</th>
                            <th>Alamat IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftarRiwayat as $item)
                            <tr>
                                <td>
                                    <div class="login-activity-time">
                                        <strong>{{ $item->created_at->locale('id')->translatedFormat('d M Y, H:i:s') }}</strong>
                                        <small>{{ $item->created_at->diffForHumans() }}</small>
                                    </div>
                                </td>
                                <td>
                                    <p class="person-name">{{ $item->pengguna?->nama ?: 'Akun tidak ditemukan' }}</p>
                                    <p class="person-meta">{{ $item->username }}</p>
                                </td>
                                <td>
                                    <p class="person-name">{{ $item->pengguna?->labelJenisAkun() ?: 'Tidak dikenali' }}</p>
                                    @if ($item->pengguna)
                                        <p class="person-meta login-activity-role">{{ $labelPeran($item->pengguna) }}</p>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $item->berhasil ? 'badge-active' : 'badge-danger' }}">
                                        {{ $item->berhasil ? 'Berhasil' : 'Gagal' }}
                                    </span>
                                </td>
                                <td><span class="login-activity-device">{{ $item->labelPerangkat() }}</span></td>
                                <td><span class="badge badge-muted">{{ $item->alamat_ip ?: '-' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">Belum ada riwayat login yang sesuai dengan filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="login-activity-mobile">
                @forelse ($daftarRiwayat as $item)
                    <article class="login-activity-mobile-card">
                        <div class="login-activity-mobile-head">
                            <div>
                                <p class="person-name">{{ $item->pengguna?->nama ?: 'Akun tidak ditemukan' }}</p>
                                <p class="person-meta">{{ $item->username }}</p>
                            </div>
                            <span class="badge {{ $item->berhasil ? 'badge-active' : 'badge-danger' }}">{{ $item->berhasil ? 'Berhasil' : 'Gagal' }}</span>
                        </div>
                        <div class="login-activity-mobile-meta">
                            <div><span>Waktu</span><strong>{{ $item->created_at->locale('id')->translatedFormat('d M Y, H:i:s') }}</strong></div>
                            <div><span>Jenis akun</span><strong>{{ $item->pengguna?->labelJenisAkun() ?: 'Tidak dikenali' }}</strong></div>
                            <div><span>Perangkat</span><strong>{{ $item->labelPerangkat() }}</strong></div>
                            <div><span>Alamat IP</span><strong>{{ $item->alamat_ip ?: '-' }}</strong></div>
                        </div>
                    </article>
                @empty
                    <div class="empty-state">Belum ada riwayat login yang sesuai dengan filter.</div>
                @endforelse
            </div>
        </section>

        @if ($daftarRiwayat->hasPages())
            {{ $daftarRiwayat->links() }}
        @endif
    @endif

    <script>
        (() => {
            const form = document.querySelector('[data-login-filter]');

            if (!form) return;

            let timer;
            const search = form.querySelector('[data-login-search]');

            form.querySelectorAll('[data-login-auto]').forEach((field) => {
                field.addEventListener('change', () => form.requestSubmit());
            });

            search?.addEventListener('input', () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(() => form.requestSubmit(), 450);
            });
        })();
    </script>
@endsection
