@extends('layouts.app')

@section('title', 'Role - NUSA')

@section('content')
    <style>
        .role-note {
            color: var(--muted);
            font-size: .92rem;
            margin: 6px 0 0;
        }

        .role-list-head {
            align-items: center;
            border-bottom: 1px solid var(--line);
            display: flex;
            gap: 14px;
            justify-content: space-between;
            padding: 18px 20px;
        }

        .role-list-head h2 {
            color: var(--primary);
            font-size: 1rem;
            font-weight: 900;
            margin: 0;
        }

        .role-list-head p {
            color: var(--muted);
            font-size: .86rem;
            margin: 2px 0 0;
        }

        .role-code {
            background: var(--primary-soft);
            border: 1px solid rgba(21, 71, 122, .14);
            border-radius: 8px;
            color: var(--primary);
            display: inline-flex;
            font-size: .76rem;
            font-weight: 800;
            margin-top: 8px;
            padding: 4px 8px;
        }

        .role-count {
            color: var(--primary);
            font-size: 1.18rem;
            font-weight: 900;
            line-height: 1.1;
            margin: 0;
        }

        .role-count span {
            color: var(--muted);
            font-size: .78rem;
            font-weight: 800;
        }

        .role-subtext {
            color: var(--muted);
            font-size: .78rem;
            margin: 4px 0 0;
        }

        .role-progress {
            background: #e9eef5;
            border-radius: 999px;
            height: 8px;
            margin-top: 10px;
            overflow: hidden;
            width: min(100%, 170px);
        }

        .role-progress span {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            display: block;
            height: 100%;
            width: var(--permission-width);
        }

        .role-badges,
        .role-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .role-actions {
            justify-content: flex-end;
        }

        .role-mobile-metrics {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 14px;
        }

        .role-mobile-metric {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 10px;
        }

        .role-mobile-metric .role-progress {
            width: 100%;
        }

        @media (max-width: 760px) {
            .role-list-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .role-mobile-metrics {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @php
        $jumlahIzinAktif = (int) ($ringkasan['izin_aktif'] ?? 0);
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Hak akses</p>
            <h1 class="page-title">Role / Peran</h1>
            <p class="role-note">Pantau role, jumlah permission, dan jumlah pengguna yang memakai setiap role.</p>
        </div>

        <div class="actions">
            @izin('akun.lihat', 'akun.kelola')
                <a href="{{ route('akun-pegawai.index') }}" class="button button-muted">Akun pegawai</a>
            @endizin
            @izin('peran.kelola')
                <a href="{{ route('peran.create') }}" class="button button-primary">Tambah role</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if (session('gagal'))
        <div class="alert alert-danger">{{ session('gagal') }}</div>
    @endif

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total role</p>
            <p class="stat-value">{{ $ringkasan['total'] }}</p>
            <p class="role-subtext">{{ $ringkasan['sistem'] }} sistem, {{ $ringkasan['tambahan'] }} tambahan</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Role aktif</p>
            <p class="stat-value">{{ $ringkasan['aktif'] }}</p>
            <p class="role-subtext">Siap diberikan ke akun pegawai</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Permission aktif</p>
            <p class="stat-value">{{ $ringkasan['izin_aktif'] }}</p>
            <p class="role-subtext">Hak akses tersedia di NUSA</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Pengguna berperan</p>
            <p class="stat-value">{{ $ringkasan['pengguna_terhubung'] }}</p>
            <p class="role-subtext">Akun yang sudah punya minimal satu role</p>
        </div>
    </div>

    <form action="{{ route('peran.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari role</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" class="input" placeholder="Nama, kode, atau deskripsi">
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="semua" @selected($status === 'semua')>Semua</option>
                    <option value="aktif" @selected($status === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('peran.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="role-list-head">
            <div>
                <h2>Daftar role</h2>
                <p>{{ $peran->total() }} role sesuai filter yang sedang dipakai.</p>
            </div>
            @izin('peran.kelola')
                <a href="{{ route('peran.create') }}" class="button button-primary button-sm">Tambah role</a>
            @endizin
        </div>

        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width: 1040px;">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Permission</th>
                        <th>Pengguna</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($peran as $item)
                        @php
                            $persentaseIzin = $jumlahIzinAktif > 0
                                ? min(100, (int) round(((int) $item->izin_count / $jumlahIzinAktif) * 100))
                                : 0;
                            $labelIzin = $jumlahIzinAktif > 0
                                ? $item->izin_count . ' dari ' . $jumlahIzinAktif . ' permission aktif'
                                : 'Belum ada permission aktif';
                        @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->deskripsi ?: '-' }}</p>
                                <span class="role-code">{{ $item->kode }}</span>
                            </td>
                            <td>
                                <p class="role-count">{{ $item->izin_count }} <span>permission</span></p>
                                <p class="role-subtext">{{ $labelIzin }}</p>
                                <div class="role-progress" style="--permission-width: {{ $persentaseIzin }}%;">
                                    <span aria-hidden="true"></span>
                                </div>
                            </td>
                            <td>
                                <p class="role-count">{{ $item->pengguna_count }} <span>pengguna</span></p>
                                <p class="role-subtext">{{ $item->pengguna_count > 0 ? 'Akun terhubung' : 'Belum dipakai akun' }}</p>
                            </td>
                            <td>
                                <div class="role-badges">
                                    <span class="badge {{ $item->aktif ? 'badge-active' : 'badge-inactive' }}">
                                        {{ $item->aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                    @if ($item->sistem)
                                        <span class="badge badge-muted">Sistem</span>
                                    @else
                                        <span class="badge badge-muted">Tambahan</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @izin('peran.kelola')
                                    <div class="role-actions">
                                        <a href="{{ route('peran.edit', $item) }}" class="button button-dark button-sm">Atur</a>
                                        @if (! $item->sistem)
                                            <form action="{{ route('peran.destroy', $item) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="button button-danger button-sm">Nonaktifkan</button>
                                            </form>
                                        @endif
                                    </div>
                                @endizin
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">Belum ada role yang sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($peran as $item)
                @php
                    $persentaseIzin = $jumlahIzinAktif > 0
                        ? min(100, (int) round(((int) $item->izin_count / $jumlahIzinAktif) * 100))
                        : 0;
                    $labelIzin = $jumlahIzinAktif > 0
                        ? $item->izin_count . ' dari ' . $jumlahIzinAktif . ' permission aktif'
                        : 'Belum ada permission aktif';
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama }}</p>
                            <span class="role-code">{{ $item->kode }}</span>
                        </div>
                        <span class="badge {{ $item->aktif ? 'badge-active' : 'badge-inactive' }}">
                            {{ $item->aktif ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    <p class="help-text">{{ $item->deskripsi ?: '-' }}</p>

                    <div class="role-mobile-metrics">
                        <div class="role-mobile-metric">
                            <p class="role-count">{{ $item->izin_count }} <span>permission</span></p>
                            <p class="role-subtext">{{ $labelIzin }}</p>
                            <div class="role-progress" style="--permission-width: {{ $persentaseIzin }}%;">
                                <span aria-hidden="true"></span>
                            </div>
                        </div>
                        <div class="role-mobile-metric">
                            <p class="role-count">{{ $item->pengguna_count }} <span>pengguna</span></p>
                            <p class="role-subtext">{{ $item->pengguna_count > 0 ? 'Akun terhubung' : 'Belum dipakai akun' }}</p>
                        </div>
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Jenis</dt>
                            <dd>{{ $item->sistem ? 'Sistem' : 'Tambahan' }}</dd>
                        </div>
                        <div>
                            <dt>Status</dt>
                            <dd>{{ $item->aktif ? 'Aktif' : 'Nonaktif' }}</dd>
                        </div>
                    </dl>

                    @izin('peran.kelola')
                        <div class="actions" style="margin-top: 12px;">
                            <a href="{{ route('peran.edit', $item) }}" class="button button-dark button-sm">Atur</a>
                            @if (! $item->sistem)
                                <form action="{{ route('peran.destroy', $item) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button button-danger button-sm">Nonaktifkan</button>
                                </form>
                            @endif
                        </div>
                    @endizin
                </article>
            @empty
                <div class="empty-state">Belum ada role yang sesuai filter.</div>
            @endforelse
        </div>
    </section>

    @if ($peran->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $peran->currentPage() }} dari {{ $peran->lastPage() }}
            </div>
            <div class="actions">
                @if ($peran->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $peran->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($peran->hasMorePages())
                    <a href="{{ $peran->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
