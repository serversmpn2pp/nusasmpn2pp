@extends('layouts.app')

@section('title', 'Peserta dan Sesi CBT - NUSA')

@section('content')
    <style>
        .cbt-session-form {
            display: grid;
            grid-template-columns: 110px minmax(180px, 1fr) minmax(170px, .85fr) minmax(170px, .85fr) 120px 140px auto;
            gap: 12px;
            align-items: end;
        }

        .cbt-session-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
            margin-top: 16px;
        }

        .cbt-session-card {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 14px;
            background: #fff;
        }

        .cbt-session-card-head,
        .cbt-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .cbt-session-edit {
            display: grid;
            grid-template-columns: 90px 1fr;
            gap: 10px;
            margin-top: 12px;
        }

        .cbt-filter-grid {
            display: grid;
            grid-template-columns: minmax(170px, .8fr) minmax(170px, .8fr) minmax(150px, .7fr) auto;
            gap: 12px;
            align-items: end;
        }

        .cbt-credential {
            display: grid;
            gap: 3px;
            font-size: .86rem;
        }

        @media (max-width: 1180px) {
            .cbt-session-form,
            .cbt-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 680px) {
            .cbt-session-form,
            .cbt-session-edit,
            .cbt-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @php
        $jumlahPeserta = $pesertaUjianCbt->count();
        $jumlahAktif = (int) ($ringkasanStatus['aktif'] ?? 0);
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Peserta & sesi CBT</h1>
        </div>

        <div class="actions">
            <a href="{{ route('ujian-cbt.kartu-peserta.index', $ujianCbt) }}" class="button button-primary">Kartu peserta</a>
            <a href="{{ route('ujian-cbt.show', $ujianCbt) }}" class="button button-muted">Detail paket</a>
            <a href="{{ route('ujian-cbt.index') }}" class="button button-muted">Daftar paket</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ada data yang perlu diperbaiki.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Peserta tampil</p>
            <p class="stat-value">{{ $jumlahPeserta }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Peserta aktif</p>
            <p class="stat-value">{{ $jumlahAktif }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Sesi ujian</p>
            <p class="stat-value">{{ $sesiUjianCbt->count() }}</p>
        </div>
    </div>

    <section class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="cbt-toolbar">
            <div>
                <h2 class="panel-title">{{ $ujianCbt->nama }}</h2>
                <p class="help-text" style="margin-top: 6px;">{{ $ujianCbt->kode }} - {{ $ujianCbt->mataPelajaran?->nama ?: '-' }} - kelas {{ $ujianCbt->tingkat }}</p>
            </div>
            <form action="{{ route('ujian-cbt.peserta.generate', $ujianCbt) }}" method="POST" onsubmit="return confirm('Generate peserta CBT dari siswa aktif di kelas peserta?')">
                @csrf
                <button type="submit" class="button button-primary">Generate peserta</button>
            </form>
            @if ($ujianCbt->pesertaUjianCbt()->exists())
                <a href="{{ route('ujian-cbt.kartu-peserta.index', $ujianCbt) }}" class="button button-dark">Cetak kartu peserta</a>
            @endif
        </div>
        <dl class="quick-facts" style="margin-top: 16px;">
            <div><dt>Tahun pelajaran</dt><dd>{{ $ujianCbt->tahunPelajaran?->nama ?: '-' }}</dd></div>
            <div><dt>Jadwal paket</dt><dd>{{ $ujianCbt->labelWaktu() }}</dd></div>
            <div><dt>Durasi</dt><dd>{{ $ujianCbt->durasi_menit }} menit</dd></div>
            <div><dt>Status paket</dt><dd>{{ $ujianCbt->labelStatus() }}</dd></div>
        </dl>
    </section>

    <section class="panel panel-pad" style="margin-bottom: 24px;">
        <h2 class="panel-title">Tambah Sesi Ujian</h2>
        <form action="{{ route('ujian-cbt.sesi.store', $ujianCbt) }}" method="POST" class="cbt-session-form" style="margin-top: 16px;">
            @csrf
            <div class="field">
                <label for="kode">Kode</label>
                <input id="kode" name="kode" value="{{ old('kode') }}" class="input" placeholder="S-01">
            </div>
            <div class="field">
                <label for="nama">Nama sesi</label>
                <input id="nama" name="nama" value="{{ old('nama', 'Sesi ' . ($sesiUjianCbt->count() + 1)) }}" class="input" required>
            </div>
            <div class="field">
                <label for="waktu_mulai">Mulai</label>
                <input id="waktu_mulai" name="waktu_mulai" type="datetime-local" value="{{ old('waktu_mulai', $ujianCbt->tanggal_mulai?->format('Y-m-d\TH:i')) }}" class="input">
            </div>
            <div class="field">
                <label for="waktu_selesai">Selesai</label>
                <input id="waktu_selesai" name="waktu_selesai" type="datetime-local" value="{{ old('waktu_selesai', $ujianCbt->tanggal_selesai?->format('Y-m-d\TH:i')) }}" class="input">
            </div>
            <div class="field">
                <label for="kapasitas">Kapasitas</label>
                <input id="kapasitas" name="kapasitas" type="number" min="1" max="1000" value="{{ old('kapasitas') }}" class="input" placeholder="Opsional">
            </div>
            <div class="field">
                <label for="status_sesi">Status</label>
                <select id="status_sesi" name="status" class="select">
                    @foreach ($daftarStatusSesi as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(old('status', 'draft') === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="button button-dark">Tambah</button>
            </div>
        </form>

        <div class="cbt-session-grid">
            @forelse ($sesiUjianCbt as $sesi)
                <article class="cbt-session-card">
                    <div class="cbt-session-card-head">
                        <div>
                            <p class="person-name">{{ $sesi->nama }}</p>
                            <p class="person-meta">{{ $sesi->kode }} - {{ $sesi->labelWaktu() }}</p>
                        </div>
                        <span class="badge {{ $sesi->status === 'aktif' ? 'badge-active' : ($sesi->status === 'nonaktif' ? 'badge-inactive' : 'badge-warning') }}">{{ $sesi->labelStatus() }}</span>
                    </div>
                    <dl class="quick-facts" style="margin-top: 12px;">
                        <div><dt>Peserta</dt><dd>{{ $sesi->peserta_ujian_cbt_count }}</dd></div>
                        <div><dt>Kapasitas</dt><dd>{{ $sesi->kapasitas ?: 'Tidak dibatasi' }}</dd></div>
                    </dl>

                    <form action="{{ route('ujian-cbt.sesi.update', [$ujianCbt, $sesi]) }}" method="POST" class="cbt-session-edit">
                        @csrf
                        @method('PUT')
                        <input name="kode" value="{{ old("sesi.{$sesi->id}.kode", $sesi->kode) }}" class="input" aria-label="Kode sesi">
                        <input name="nama" value="{{ old("sesi.{$sesi->id}.nama", $sesi->nama) }}" class="input" aria-label="Nama sesi" required>
                        <input name="waktu_mulai" type="datetime-local" value="{{ old("sesi.{$sesi->id}.waktu_mulai", $sesi->waktu_mulai?->format('Y-m-d\TH:i')) }}" class="input" aria-label="Waktu mulai">
                        <input name="waktu_selesai" type="datetime-local" value="{{ old("sesi.{$sesi->id}.waktu_selesai", $sesi->waktu_selesai?->format('Y-m-d\TH:i')) }}" class="input" aria-label="Waktu selesai">
                        <input name="kapasitas" type="number" min="1" max="1000" value="{{ old("sesi.{$sesi->id}.kapasitas", $sesi->kapasitas) }}" class="input" aria-label="Kapasitas">
                        <select name="status" class="select" aria-label="Status sesi">
                            @foreach ($daftarStatusSesi as $nilai => $label)
                                <option value="{{ $nilai }}" @selected(old("sesi.{$sesi->id}.status", $sesi->status) === $nilai)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <textarea name="keterangan" rows="2" class="textarea" style="grid-column: 1 / -1;" placeholder="Keterangan sesi">{{ old("sesi.{$sesi->id}.keterangan", $sesi->keterangan) }}</textarea>
                        <div class="actions" style="grid-column: 1 / -1;">
                            <button type="submit" class="button button-muted">Simpan sesi</button>
                        </div>
                    </form>

                    <form action="{{ route('ujian-cbt.sesi.destroy', [$ujianCbt, $sesi]) }}" method="POST" style="margin-top: 10px;" onsubmit="return confirm('Hapus atau nonaktifkan sesi ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Hapus / nonaktifkan sesi</button>
                    </form>
                </article>
            @empty
                <div class="empty-state">Belum ada sesi. Jika peserta digenerate sekarang, NUSA akan otomatis membuat Sesi 1.</div>
            @endforelse
        </div>
    </section>

    <form action="{{ route('ujian-cbt.peserta.index', $ujianCbt) }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="cbt-filter-grid">
            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select">
                    <option value="">Semua kelas</option>
                    @foreach ($kelasPeserta as $kelasUjian)
                        <option value="{{ $kelasUjian->kelas_id }}" @selected((string) $kelasId === (string) $kelasUjian->kelas_id)>{{ $kelasUjian->kelas?->nama ?: '-' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="sesi_ujian_cbt_id">Sesi</label>
                <select id="sesi_ujian_cbt_id" name="sesi_ujian_cbt_id" class="select">
                    <option value="">Semua sesi</option>
                    @foreach ($sesiUjianCbt as $sesi)
                        <option value="{{ $sesi->id }}" @selected((string) $sesiUjianCbtId === (string) $sesi->id)>{{ $sesi->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="semua" @selected($status === 'semua')>Semua</option>
                    @foreach ($daftarStatusPeserta as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('ujian-cbt.peserta.index', $ujianCbt) }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <form action="{{ route('ujian-cbt.peserta.update', $ujianCbt) }}" method="POST" class="panel">
        @csrf
        @method('PUT')
        <div class="table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Peserta</th>
                        <th>Kelas</th>
                        <th>Akun CBT</th>
                        <th>Sesi</th>
                        <th>Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pesertaUjianCbt as $peserta)
                        @php($akunPeserta = $peserta->akunPesertaCbt)
                        <tr>
                            <td>
                                <p class="person-name">{{ $peserta->anggotaKelas?->siswa?->nama_lengkap ?: '-' }}</p>
                                <p class="person-meta">{{ $akunPeserta?->nomor_peserta ?: $peserta->nomor_peserta }}</p>
                            </td>
                            <td>
                                <p>{{ $peserta->kelasUjianCbt?->kelas?->nama ?: '-' }}</p>
                                <p class="person-meta">Absen {{ $peserta->anggotaKelas?->nomor_absen ?: '-' }}</p>
                            </td>
                            <td>
                                <div class="cbt-credential">
                                    <span>Username: <strong>{{ $akunPeserta?->username ?: $peserta->username }}</strong></span>
                                    <span>Password: <strong>{{ $akunPeserta?->kata_sandi ?: $peserta->kata_sandi }}</strong></span>
                                </div>
                            </td>
                            <td>
                                <select name="peserta[{{ $peserta->id }}][sesi_ujian_cbt_id]" class="select">
                                    <option value="">Belum dipilih</option>
                                    @foreach ($sesiUjianCbt as $sesi)
                                        <option value="{{ $sesi->id }}" @selected((string) old("peserta.{$peserta->id}.sesi_ujian_cbt_id", $peserta->sesi_ujian_cbt_id) === (string) $sesi->id)>{{ $sesi->nama }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="peserta[{{ $peserta->id }}][status]" class="select">
                                    @foreach ($daftarStatusPeserta as $nilai => $label)
                                        <option value="{{ $nilai }}" @selected(old("peserta.{$peserta->id}.status", $peserta->status) === $nilai)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input name="peserta[{{ $peserta->id }}][catatan]" value="{{ old("peserta.{$peserta->id}.catatan", $peserta->catatan) }}" class="input" placeholder="-">
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada peserta CBT. Klik Generate peserta untuk mengambil siswa aktif dari kelas peserta paket.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pesertaUjianCbt->isNotEmpty())
            <div class="form-actions" style="padding: 18px;">
                <button type="submit" class="button button-primary">Simpan perubahan peserta</button>
            </div>
        @endif
    </form>
@endsection
