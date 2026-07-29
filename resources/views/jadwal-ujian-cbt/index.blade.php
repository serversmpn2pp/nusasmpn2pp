@extends('layouts.app')

@section('title', 'Jadwal Ujian CBT - NUSA')

@section('content')
    <style>
        .exam-schedule-filter,
        .exam-event-form,
        .exam-session-form {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
            gap: 12px;
            align-items: end;
        }

        .exam-event-layout {
            display: grid;
            grid-template-columns: minmax(280px, .9fr) minmax(0, 1.7fr);
            gap: 18px;
            align-items: start;
        }

        .exam-event-list,
        .exam-schedule-list {
            display: grid;
            gap: 12px;
        }

        .exam-event-card {
            display: grid;
            gap: 8px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 14px;
            text-decoration: none;
        }

        .exam-event-card.active {
            border-color: rgba(241, 196, 15, .85);
            box-shadow: inset 4px 0 0 var(--accent);
        }

        .exam-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .exam-class-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }

        .exam-class-check {
            display: flex;
            min-height: 38px;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fafc;
            padding: 8px 10px;
            color: var(--ink);
            font-size: .88rem;
            font-weight: 780;
        }

        .exam-schedule-card {
            display: grid;
            gap: 14px;
        }

        .exam-schedule-head {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        @media (max-width: 1180px) {
            .exam-schedule-filter,
            .exam-event-form,
            .exam-session-form,
            .exam-event-layout {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .exam-class-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 680px) {
            .exam-schedule-filter,
            .exam-event-form,
            .exam-session-form,
            .exam-event-layout,
            .exam-form-grid,
            .exam-class-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @php
        $badgeKegiatan = fn ($status) => match ($status) {
            'aktif', 'selesai' => 'badge-active',
            'nonaktif' => 'badge-inactive',
            default => 'badge-warning',
        };
        $badgeJadwal = fn ($status) => match ($status) {
            'siap', 'selesai' => 'badge-active',
            'dibatalkan' => 'badge-inactive',
            default => 'badge-warning',
        };
        $tanggalInput = fn ($tanggal) => $tanggal?->format('Y-m-d');
        $jamInput = fn ($jam) => substr((string) $jam, 0, 5);
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Jadwal ujian terpusat</h1>
        </div>

        <div class="actions">
            <a href="{{ route('ujian-cbt.index') }}" class="button button-muted">Paket CBT</a>
            <a href="{{ route('jenis-ujian-cbt.index') }}" class="button button-muted">Jenis ujian</a>
            @izin('cbt.kelola')
                <a href="{{ route('ujian-cbt.create') }}" class="button button-primary">Tambah paket</a>
            @endizin
        </div>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Kegiatan aktif</p>
            <p class="stat-value">{{ $ringkasan['kegiatan'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Total jadwal</p>
            <p class="stat-value">{{ $ringkasan['jadwal'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Jadwal siap</p>
            <p class="stat-value">{{ $ringkasan['jadwal_siap'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Jadwal terkunci</p>
            <p class="stat-value">{{ $ringkasan['jadwal_terkunci'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Belum terhubung paket</p>
            <p class="stat-value">{{ $ringkasan['jadwal_tanpa_paket'] }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('jadwal-ujian-cbt.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="exam-schedule-filter">
            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    <option value="">Semua tahun</option>
                    @foreach ($daftarTahunPelajaran as $item)
                        <option value="{{ $item->id }}" @selected((string) $tahunPelajaranId === (string) $item->id)>{{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="status">Status kegiatan</label>
                <select id="status" name="status" class="select">
                    <option value="semua" @selected($status === 'semua')>Semua</option>
                    @foreach ($daftarStatusKegiatan as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="grid-column: span 2;">
                <label for="kegiatan_ujian_cbt_id">Kegiatan terpilih</label>
                <select id="kegiatan_ujian_cbt_id" name="kegiatan_ujian_cbt_id" class="select">
                    <option value="">Otomatis kegiatan terbaru</option>
                    @foreach ($daftarKegiatan as $item)
                        <option value="{{ $item->id }}" @selected((int) ($kegiatanTerpilih?->id) === (int) $item->id)>{{ $item->nama }} - {{ $item->tahunPelajaran?->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('jadwal-ujian-cbt.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    @izin('cbt.kelola')
        <section class="panel panel-pad" style="margin-bottom: 24px;">
            <h2 class="panel-title">Buat kegiatan ujian</h2>
            <form action="{{ route('jadwal-ujian-cbt.kegiatan.store') }}" method="POST" class="exam-event-form" style="margin-top: 16px;">
                @csrf
                <div class="field">
                    <label for="kode_kegiatan">Kode</label>
                    <input id="kode_kegiatan" name="kode" value="{{ old('kode', 'JADWAL-' . now()->format('Ymd')) }}" class="input" required>
                </div>
                <div class="field">
                    <label for="nama_kegiatan">Nama kegiatan</label>
                    <input id="nama_kegiatan" name="nama" value="{{ old('nama') }}" class="input" placeholder="Sumatif Akhir Semester" required>
                </div>
                <div class="field">
                    <label for="jenis_ujian_cbt_id_baru">Jenis ujian</label>
                    <select id="jenis_ujian_cbt_id_baru" name="jenis_ujian_cbt_id" class="select" required>
                        <option value="">Pilih jenis</option>
                        @foreach ($daftarJenisUjianCbt as $item)
                            <option value="{{ $item->id }}" @selected((string) old('jenis_ujian_cbt_id') === (string) $item->id)>{{ $item->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="tahun_pelajaran_id_baru">Tahun pelajaran</label>
                    <select id="tahun_pelajaran_id_baru" name="tahun_pelajaran_id" class="select" required>
                        <option value="">Pilih tahun</option>
                        @foreach ($daftarTahunPelajaran as $item)
                            <option value="{{ $item->id }}" @selected((string) old('tahun_pelajaran_id') === (string) $item->id || (! old('tahun_pelajaran_id') && $item->aktif))>{{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="semester_baru">Semester</label>
                    <select id="semester_baru" name="semester" class="select" required>
                        <option value="ganjil" @selected(old('semester') === 'ganjil')>Ganjil</option>
                        <option value="genap" @selected(old('semester') === 'genap')>Genap</option>
                    </select>
                </div>
                <div class="field">
                    <label for="tanggal_mulai_baru">Tanggal mulai</label>
                    <input id="tanggal_mulai_baru" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" type="date" class="input">
                </div>
                <div class="field">
                    <label for="tanggal_selesai_baru">Tanggal selesai</label>
                    <input id="tanggal_selesai_baru" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}" type="date" class="input">
                </div>
                <div class="field">
                    <label for="status_baru">Status</label>
                    <select id="status_baru" name="status" class="select" required>
                        @foreach ($daftarStatusKegiatan as $nilai => $label)
                            <option value="{{ $nilai }}" @selected(old('status', 'draft') === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="actions">
                    <button type="submit" class="button button-primary">Tambah kegiatan</button>
                </div>
            </form>
        </section>
    @endizin

    <section class="exam-event-layout">
        <aside class="panel panel-pad">
            <h2 class="panel-title">Daftar kegiatan</h2>
            <div class="exam-event-list" style="margin-top: 14px;">
                @forelse ($daftarKegiatan as $item)
                    <a href="{{ route('jadwal-ujian-cbt.index', [
                        'tahun_pelajaran_id' => $tahunPelajaranId,
                        'status' => $status,
                        'kegiatan_ujian_cbt_id' => $item->id,
                    ]) }}" class="exam-event-card {{ (int) ($kegiatanTerpilih?->id) === (int) $item->id ? 'active' : '' }}">
                        <div style="display: flex; justify-content: space-between; gap: 10px; align-items: flex-start;">
                            <div>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->kode }} - {{ $item->tahunPelajaran?->nama ?: '-' }}</p>
                            </div>
                            <span class="badge {{ $badgeKegiatan($item->status) }}">{{ $item->labelStatus() }}</span>
                        </div>
                        <dl class="quick-facts">
                            <div><dt>Jenis</dt><dd>{{ $item->jenisUjianCbt?->nama ?: '-' }}</dd></div>
                            <div><dt>Semester</dt><dd>{{ ucfirst($item->semester) }}</dd></div>
                            <div><dt>Jadwal</dt><dd>{{ $item->jadwal_ujian_cbt_count }}</dd></div>
                        </dl>
                    </a>
                @empty
                    <div class="empty-state">Belum ada kegiatan ujian CBT.</div>
                @endforelse
            </div>
        </aside>

        <div class="section-stack">
            @if ($kegiatanTerpilih)
                <section class="panel panel-pad">
                    <div style="display: flex; gap: 14px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap;">
                        <div>
                            <h2 class="panel-title">{{ $kegiatanTerpilih->nama }}</h2>
                            <p class="help-text" style="margin-top: 6px;">
                                {{ $kegiatanTerpilih->jenisUjianCbt?->nama ?: '-' }} - {{ $kegiatanTerpilih->tahunPelajaran?->nama ?: '-' }} - {{ ucfirst($kegiatanTerpilih->semester) }}
                            </p>
                        </div>
                        <span class="badge {{ $badgeKegiatan($kegiatanTerpilih->status) }}">{{ $kegiatanTerpilih->labelStatus() }}</span>
                    </div>

                    @izin('cbt.kelola')
                        <form action="{{ route('jadwal-ujian-cbt.kegiatan.update', $kegiatanTerpilih) }}" method="POST" class="exam-form-grid" style="margin-top: 18px;">
                            @csrf
                            @method('PUT')
                            <div class="field">
                                <label for="kode_edit">Kode</label>
                                <input id="kode_edit" name="kode" value="{{ old('kode', $kegiatanTerpilih->kode) }}" class="input" required>
                            </div>
                            <div class="field">
                                <label for="nama_edit">Nama</label>
                                <input id="nama_edit" name="nama" value="{{ old('nama', $kegiatanTerpilih->nama) }}" class="input" required>
                            </div>
                            <div class="field">
                                <label for="jenis_edit">Jenis ujian</label>
                                <select id="jenis_edit" name="jenis_ujian_cbt_id" class="select" required>
                                    @foreach ($daftarJenisUjianCbt as $item)
                                        <option value="{{ $item->id }}" @selected((string) $kegiatanTerpilih->jenis_ujian_cbt_id === (string) $item->id)>{{ $item->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="tahun_edit">Tahun pelajaran</label>
                                <select id="tahun_edit" name="tahun_pelajaran_id" class="select" required>
                                    @foreach ($daftarTahunPelajaran as $item)
                                        <option value="{{ $item->id }}" @selected((string) $kegiatanTerpilih->tahun_pelajaran_id === (string) $item->id)>{{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="semester_edit">Semester</label>
                                <select id="semester_edit" name="semester" class="select" required>
                                    <option value="ganjil" @selected($kegiatanTerpilih->semester === 'ganjil')>Ganjil</option>
                                    <option value="genap" @selected($kegiatanTerpilih->semester === 'genap')>Genap</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="mulai_edit">Tanggal mulai</label>
                                <input id="mulai_edit" name="tanggal_mulai" value="{{ $tanggalInput($kegiatanTerpilih->tanggal_mulai) }}" type="date" class="input">
                            </div>
                            <div class="field">
                                <label for="selesai_edit">Tanggal selesai</label>
                                <input id="selesai_edit" name="tanggal_selesai" value="{{ $tanggalInput($kegiatanTerpilih->tanggal_selesai) }}" type="date" class="input">
                            </div>
                            <div class="field">
                                <label for="status_edit">Status</label>
                                <select id="status_edit" name="status" class="select" required>
                                    @foreach ($daftarStatusKegiatan as $nilai => $label)
                                        <option value="{{ $nilai }}" @selected($kegiatanTerpilih->status === $nilai)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field span-2">
                                <label for="keterangan_edit">Keterangan</label>
                                <textarea id="keterangan_edit" name="keterangan" class="textarea">{{ $kegiatanTerpilih->keterangan }}</textarea>
                            </div>
                            <div class="actions" style="align-items: end;">
                                <button type="submit" class="button button-dark">Simpan kegiatan</button>
                                <button type="submit" form="nonaktif_kegiatan_{{ $kegiatanTerpilih->id }}" class="button button-danger" onclick="return confirm('Nonaktifkan kegiatan ujian ini?')">Nonaktifkan</button>
                            </div>
                        </form>

                        <form id="nonaktif_kegiatan_{{ $kegiatanTerpilih->id }}" action="{{ route('jadwal-ujian-cbt.kegiatan.destroy', $kegiatanTerpilih) }}" method="POST" style="display: none;">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endizin
                </section>

                @izin('cbt.kelola')
                    <section class="panel panel-pad">
                        <h2 class="panel-title">Tambah jadwal</h2>
                        <form action="{{ route('jadwal-ujian-cbt.jadwal.store') }}" method="POST" class="section-stack" style="margin-top: 16px;">
                            @csrf
                            <input type="hidden" name="kegiatan_ujian_cbt_id" value="{{ $kegiatanTerpilih->id }}">
                            <div class="exam-session-form">
                                <div class="field">
                                    <label for="tanggal_jadwal">Tanggal</label>
                                    <input id="tanggal_jadwal" name="tanggal" value="{{ old('tanggal', $tanggalInput($kegiatanTerpilih->tanggal_mulai)) }}" type="date" class="input" required>
                                </div>
                                <div class="field">
                                    <label for="mulai_jadwal">Mulai</label>
                                    <input id="mulai_jadwal" name="waktu_mulai" value="{{ old('waktu_mulai', '07:30') }}" type="time" class="input" required>
                                </div>
                                <div class="field">
                                    <label for="selesai_jadwal">Selesai</label>
                                    <input id="selesai_jadwal" name="waktu_selesai" value="{{ old('waktu_selesai', '09:00') }}" type="time" class="input" required>
                                </div>
                                <div class="field">
                                    <label for="label_sesi">Label sesi</label>
                                    <input id="label_sesi" name="label_sesi" value="{{ old('label_sesi') }}" class="input" placeholder="Jam 1">
                                </div>
                                <div class="field">
                                    <label for="urutan">Urutan</label>
                                    <input id="urutan" name="urutan" value="{{ old('urutan', 1) }}" type="number" min="1" max="20" class="input" required>
                                </div>
                            </div>

                            <div class="exam-form-grid">
                                <div class="field">
                                    <label for="ujian_cbt_id">Paket CBT</label>
                                    <select id="ujian_cbt_id" name="ujian_cbt_id" class="select">
                                        <option value="">Belum dihubungkan ke paket</option>
                                        @foreach ($daftarPaketCbt as $paket)
                                            <option value="{{ $paket->id }}" @selected((string) old('ujian_cbt_id') === (string) $paket->id)>
                                                {{ $paket->nama }} - kelas {{ $paket->tingkat }} - {{ $paket->mataPelajaran?->nama ?: '-' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="help-text">Jika paket dipilih, mapel dan tingkat mengikuti paket.</p>
                                </div>
                                <div class="field">
                                    <label for="mata_pelajaran_id">Mata pelajaran</label>
                                    <select id="mata_pelajaran_id" name="mata_pelajaran_id" class="select" required>
                                        <option value="">Pilih mapel</option>
                                        @foreach ($daftarMataPelajaran as $mapel)
                                            <option value="{{ $mapel->id }}" @selected((string) old('mata_pelajaran_id') === (string) $mapel->id)>{{ $mapel->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="tingkat">Tingkat</label>
                                    <select id="tingkat" name="tingkat" class="select" required>
                                        @foreach ([7, 8, 9] as $tingkat)
                                            <option value="{{ $tingkat }}" @selected((string) old('tingkat') === (string) $tingkat)>Kelas {{ $tingkat }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="status_jadwal">Status</label>
                                    <select id="status_jadwal" name="status" class="select" required>
                                        @foreach ($daftarStatusJadwal as $nilai => $label)
                                            <option value="{{ $nilai }}" @selected(old('status', 'draft') === $nilai)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <p class="form-label">Kelas peserta</p>
                                <div class="exam-class-grid" style="margin-top: 8px;">
                                    @foreach ($daftarKelas as $kelas)
                                        <label class="exam-class-check">
                                            <input type="checkbox" name="kelas_peserta[]" value="{{ $kelas->id }}" @checked(collect(old('kelas_peserta', []))->contains((string) $kelas->id))>
                                            <span>{{ $kelas->nama }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="field">
                                <label for="keterangan_jadwal">Keterangan</label>
                                <textarea id="keterangan_jadwal" name="keterangan" class="textarea" placeholder="Contoh: sesi pertama sebelum istirahat.">{{ old('keterangan') }}</textarea>
                            </div>

                            <div class="actions">
                                <button type="submit" class="button button-primary">Tambah jadwal</button>
                            </div>
                        </form>
                    </section>
                @endizin

                <section class="exam-schedule-list">
                    @forelse ($kegiatanTerpilih->jadwalUjianCbt as $jadwal)
                        @php
                            $jadwalTerkunci = $jadwal->terkunci();
                            $kelasJadwalIds = $jadwal->kelas->pluck('id')->map(fn ($id) => (string) $id);
                        @endphp
                        <article class="panel panel-pad exam-schedule-card">
                            <div class="exam-schedule-head">
                                <div>
                                    <h2 class="panel-title">{{ $jadwal->mataPelajaran?->nama ?: '-' }}</h2>
                                    <p class="help-text" style="margin-top: 6px;">
                                        {{ $jadwal->tanggal?->format('d-m-Y') ?: '-' }} - {{ $jadwal->labelWaktu() }}{{ $jadwal->label_sesi ? ' - ' . $jadwal->label_sesi : '' }}
                                    </p>
                                </div>
                                <div class="actions" style="justify-content: flex-end;">
                                    @if ($jadwalTerkunci)
                                        <span class="badge badge-active">Terkunci</span>
                                    @endif
                                    <span class="badge {{ $badgeJadwal($jadwal->status) }}">{{ $jadwal->labelStatus() }}</span>
                                </div>
                            </div>

                            @if ($jadwalTerkunci)
                                <p class="help-text">
                                    Dikunci pada {{ $jadwal->dikunci_pada?->format('d-m-Y H:i') ?: '-' }} oleh {{ $jadwal->dikunciOleh?->nama ?: '-' }}. Buka kunci jika jadwal perlu direvisi.
                                </p>
                            @endif

                            <dl class="quick-facts">
                                <div><dt>Tingkat</dt><dd>Kelas {{ $jadwal->tingkat ?: '-' }}</dd></div>
                                <div><dt>Kelas peserta</dt><dd>{{ $jadwal->kelas->pluck('nama')->implode(', ') ?: '-' }}</dd></div>
                                <div><dt>Paket CBT</dt><dd>{{ $jadwal->ujianCbt?->kode ?: 'Belum terhubung' }}</dd></div>
                                <div><dt>Urutan</dt><dd>{{ $jadwal->urutan }}</dd></div>
                            </dl>

                            @izin('cbt.kelola')
                                <form action="{{ route('jadwal-ujian-cbt.jadwal.update', $jadwal) }}" method="POST" class="section-stack">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="kegiatan_ujian_cbt_id" value="{{ $kegiatanTerpilih->id }}">
                                    <fieldset @disabled($jadwalTerkunci) class="section-stack" style="display: contents; border: 0; margin: 0; padding: 0;">
                                    <div class="exam-session-form">
                                        <div class="field">
                                            <label for="tanggal_{{ $jadwal->id }}">Tanggal</label>
                                            <input id="tanggal_{{ $jadwal->id }}" name="tanggal" value="{{ $tanggalInput($jadwal->tanggal) }}" type="date" class="input" required>
                                        </div>
                                        <div class="field">
                                            <label for="mulai_{{ $jadwal->id }}">Mulai</label>
                                            <input id="mulai_{{ $jadwal->id }}" name="waktu_mulai" value="{{ $jamInput($jadwal->waktu_mulai) }}" type="time" class="input" required>
                                        </div>
                                        <div class="field">
                                            <label for="selesai_{{ $jadwal->id }}">Selesai</label>
                                            <input id="selesai_{{ $jadwal->id }}" name="waktu_selesai" value="{{ $jamInput($jadwal->waktu_selesai) }}" type="time" class="input" required>
                                        </div>
                                        <div class="field">
                                            <label for="label_{{ $jadwal->id }}">Label sesi</label>
                                            <input id="label_{{ $jadwal->id }}" name="label_sesi" value="{{ $jadwal->label_sesi }}" class="input">
                                        </div>
                                        <div class="field">
                                            <label for="urutan_{{ $jadwal->id }}">Urutan</label>
                                            <input id="urutan_{{ $jadwal->id }}" name="urutan" value="{{ $jadwal->urutan }}" type="number" min="1" max="20" class="input" required>
                                        </div>
                                    </div>

                                    <div class="exam-form-grid">
                                        <div class="field">
                                            <label for="paket_{{ $jadwal->id }}">Paket CBT</label>
                                            <select id="paket_{{ $jadwal->id }}" name="ujian_cbt_id" class="select">
                                                <option value="">Belum dihubungkan ke paket</option>
                                                @foreach ($daftarPaketCbt as $paket)
                                                    <option value="{{ $paket->id }}" @selected((string) $jadwal->ujian_cbt_id === (string) $paket->id)>
                                                        {{ $paket->nama }} - kelas {{ $paket->tingkat }} - {{ $paket->mataPelajaran?->nama ?: '-' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label for="mapel_{{ $jadwal->id }}">Mata pelajaran</label>
                                            <select id="mapel_{{ $jadwal->id }}" name="mata_pelajaran_id" class="select" required>
                                                @foreach ($daftarMataPelajaran as $mapel)
                                                    <option value="{{ $mapel->id }}" @selected((string) $jadwal->mata_pelajaran_id === (string) $mapel->id)>{{ $mapel->nama }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label for="tingkat_{{ $jadwal->id }}">Tingkat</label>
                                            <select id="tingkat_{{ $jadwal->id }}" name="tingkat" class="select" required>
                                                @foreach ([7, 8, 9] as $tingkat)
                                                    <option value="{{ $tingkat }}" @selected((int) $jadwal->tingkat === $tingkat)>Kelas {{ $tingkat }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label for="status_{{ $jadwal->id }}">Status</label>
                                            <select id="status_{{ $jadwal->id }}" name="status" class="select" required>
                                                @foreach ($daftarStatusJadwal as $nilai => $label)
                                                    <option value="{{ $nilai }}" @selected($jadwal->status === $nilai)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <p class="form-label">Kelas peserta</p>
                                        <div class="exam-class-grid" style="margin-top: 8px;">
                                            @foreach ($daftarKelas as $kelas)
                                                <label class="exam-class-check">
                                                    <input type="checkbox" name="kelas_peserta[]" value="{{ $kelas->id }}" @checked($kelasJadwalIds->contains((string) $kelas->id))>
                                                    <span>{{ $kelas->nama }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="field">
                                        <label for="keterangan_{{ $jadwal->id }}">Keterangan</label>
                                        <textarea id="keterangan_{{ $jadwal->id }}" name="keterangan" class="textarea">{{ $jadwal->keterangan }}</textarea>
                                    </div>
                                    </fieldset>

                                    <div class="actions">
                                        @if ($jadwalTerkunci)
                                            <button type="submit" form="buka_kunci_jadwal_{{ $jadwal->id }}" class="button button-muted" onclick="return confirm('Buka kunci jadwal ini agar bisa direvisi?')">Buka kunci</button>
                                        @else
                                            <button type="submit" class="button button-dark">Simpan jadwal</button>
                                            <button type="submit" form="kunci_jadwal_{{ $jadwal->id }}" class="button button-primary" onclick="return confirm('Kunci jadwal ini? Jadwal tidak bisa diedit sampai kunci dibuka.')">Kunci jadwal</button>
                                            <button type="submit" form="hapus_jadwal_{{ $jadwal->id }}" class="button button-danger" onclick="return confirm('Hapus jadwal ini?')">Hapus jadwal</button>
                                        @endif
                                    </div>
                                </form>

                                <form id="hapus_jadwal_{{ $jadwal->id }}" action="{{ route('jadwal-ujian-cbt.jadwal.destroy', $jadwal) }}" method="POST" style="display: none;">
                                    @csrf
                                    @method('DELETE')
                                </form>

                                <form id="kunci_jadwal_{{ $jadwal->id }}" action="{{ route('jadwal-ujian-cbt.jadwal.kunci', $jadwal) }}" method="POST" style="display: none;">
                                    @csrf
                                    @method('PUT')
                                </form>

                                <form id="buka_kunci_jadwal_{{ $jadwal->id }}" action="{{ route('jadwal-ujian-cbt.jadwal.buka-kunci', $jadwal) }}" method="POST" style="display: none;">
                                    @csrf
                                    @method('PUT')
                                </form>
                            @endizin
                        </article>
                    @empty
                        <section class="panel panel-pad">
                            <div class="empty-state">Belum ada jadwal untuk kegiatan ini.</div>
                        </section>
                    @endforelse
                </section>
            @else
                <section class="panel panel-pad">
                    <div class="empty-state">Buat kegiatan ujian terlebih dahulu untuk mulai menyusun jadwal.</div>
                </section>
            @endif
        </div>
    </section>
@endsection
