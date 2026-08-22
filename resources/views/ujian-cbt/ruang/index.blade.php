@extends('layouts.app')

@section('title', 'Ruang Ujian CBT - NUSA')

@section('content')
    <style>
        .cbt-room-filter,
        .cbt-room-tools {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
            gap: 12px;
            align-items: end;
        }

        .cbt-room-tools {
            grid-template-columns: repeat(5, minmax(0, 1fr)) auto;
        }

        .cbt-room-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .cbt-room-card {
            display: grid;
            gap: 14px;
        }

        .cbt-room-card-head {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            justify-content: space-between;
        }

        .cbt-room-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .cbt-room-wide-2 {
            grid-column: span 2;
        }

        .cbt-room-meta {
            display: grid;
            gap: 3px;
            color: var(--muted);
            font-size: .84rem;
            font-weight: 760;
        }

        .cbt-proof-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .cbt-proof-box {
            display: grid;
            gap: 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fafc;
            padding: 12px;
        }

        .cbt-proof-head {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .cbt-proof-title {
            margin: 0;
            color: var(--primary-strong);
            font-size: .92rem;
            font-weight: 850;
        }

        .cbt-proof-file {
            display: grid;
            gap: 4px;
            min-width: 0;
            color: var(--muted);
            font-size: .82rem;
            font-weight: 740;
        }

        .cbt-proof-file strong {
            color: var(--ink);
            overflow-wrap: anywhere;
        }

        .cbt-room-table {
            min-width: 1180px;
        }

        .cbt-room-table td {
            vertical-align: top;
        }

        .cbt-room-table .input,
        .cbt-room-table .select {
            min-height: 38px;
            padding: 8px 10px;
            font-size: .9rem;
        }

        @media (max-width: 1180px) {
            .cbt-room-filter,
            .cbt-room-tools,
            .cbt-room-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 680px) {
            .cbt-room-filter,
            .cbt-room-tools,
            .cbt-room-grid,
            .cbt-proof-grid,
            .cbt-room-form-grid {
                grid-template-columns: 1fr;
            }

            .cbt-room-wide-2 {
                grid-column: auto;
            }
        }
    </style>

    @php
        $formatTanggalInput = fn ($tanggal) => $tanggal?->format('Y-m-d\TH:i');
        $badgeRuang = fn ($status) => match ($status) {
            'siap', 'berlangsung', 'selesai' => 'badge-active',
            'nonaktif' => 'badge-inactive',
            default => 'badge-muted',
        };
        $badgeKehadiran = fn ($status) => match ($status) {
            'hadir' => 'badge-active',
            'terlambat' => 'badge-warning',
            'sakit', 'izin' => 'badge-muted',
            'alfa' => 'badge-danger',
            default => 'badge-muted',
        };
        $queryCetak = array_filter([
            'sesi_ujian_cbt_id' => $sesiUjianCbtId,
            'jadwal_ujian_cbt_id' => $jadwalUjianCbtId,
            'ruang_ujian_cbt_id' => $ruangUjianCbtId,
        ], fn ($nilai) => filled($nilai));
        $labelJadwalUjian = function ($jadwal) use ($ujianCbt) {
            $tanggal = $jadwal->tanggal?->format('d-m-Y') ?: '-';
            $jam = $jadwal->labelWaktu();
            $mapel = $jadwal->mataPelajaran?->nama ?: ($ujianCbt->mataPelajaran?->nama ?: 'Mata pelajaran');
            $sesi = filled($jadwal->label_sesi) ? " - {$jadwal->label_sesi}" : '';

            return "{$tanggal} {$jam} - {$mapel}{$sesi}";
        };
        $formatUkuranFile = function (?int $ukuran) {
            if (! $ukuran) {
                return '-';
            }

            return $ukuran >= 1048576
                ? number_format($ukuran / 1048576, 1, ',', '.') . ' MB'
                : number_format($ukuran / 1024, 0, ',', '.') . ' KB';
        };
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Ruang ujian CBT</h1>
        </div>

        <div class="actions">
            <a href="{{ route('ujian-cbt.ruang.cetak', array_merge([$ujianCbt], $queryCetak)) }}" target="_blank" rel="noopener" class="button button-primary">Cetak daftar hadir & BA</a>
            <a href="{{ route('ujian-cbt.peserta.index', $ujianCbt) }}" class="button button-muted">Peserta & sesi</a>
            <a href="{{ route('ujian-cbt.monitoring.index', $ujianCbt) }}" class="button button-muted">Monitoring</a>
            <a href="{{ route('ujian-cbt.hasil.index', $ujianCbt) }}" class="button button-muted">Hasil</a>
            <a href="{{ route('ujian-cbt.show', $ujianCbt) }}" class="button button-muted">Detail paket</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <section class="panel panel-pad" style="margin-bottom: 24px;">
        <div style="display: flex; gap: 14px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap;">
            <div>
                <h2 class="panel-title">{{ $ujianCbt->nama }}</h2>
                <p class="help-text" style="margin-top: 6px;">
                    {{ $ujianCbt->kode }} - {{ $ujianCbt->mataPelajaran?->nama ?: '-' }} - {{ $ujianCbt->tahunPelajaran?->nama ?: '-' }}
                </p>
            </div>
            <span class="badge badge-active">{{ $ujianCbt->labelStatus() }}</span>
        </div>

        <dl class="quick-facts" style="margin-top: 18px;">
            <div><dt>Ruang</dt><dd>{{ $ringkasan['ruang'] }}</dd></div>
            <div><dt>Peserta</dt><dd>{{ $ringkasan['peserta'] }}</dd></div>
            <div><dt>Ditempatkan</dt><dd>{{ $ringkasan['sudah_ditempatkan'] }}</dd></div>
            <div><dt>Belum ditempatkan</dt><dd>{{ $ringkasan['belum_ditempatkan'] }}</dd></div>
            <div><dt>Hadir</dt><dd>{{ $ringkasan['hadir'] }}</dd></div>
            <div><dt>Tidak hadir</dt><dd>{{ $ringkasan['tidak_hadir'] }}</dd></div>
        </dl>
    </section>

    <form action="{{ route('ujian-cbt.ruang.index', $ujianCbt) }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="cbt-room-filter">
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
                <label for="jadwal_ujian_cbt_id">Jadwal ujian</label>
                <select id="jadwal_ujian_cbt_id" name="jadwal_ujian_cbt_id" class="select">
                    <option value="">Semua jadwal</option>
                    @foreach ($jadwalUjianCbt as $jadwal)
                        <option value="{{ $jadwal->id }}" @selected((string) $jadwalUjianCbtId === (string) $jadwal->id)>{{ $labelJadwalUjian($jadwal) }}</option>
                    @endforeach
                </select>
            </div>
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
                <label for="ruang_ujian_cbt_id">Ruang</label>
                <select id="ruang_ujian_cbt_id" name="ruang_ujian_cbt_id" class="select">
                    <option value="">Semua ruang</option>
                    @foreach ($ruangUjianCbt as $ruang)
                        <option value="{{ $ruang->id }}" @selected((string) $ruangUjianCbtId === (string) $ruang->id)>{{ $ruang->kode }} - {{ $ruang->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('ujian-cbt.ruang.index', $ujianCbt) }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel panel-pad" style="margin-bottom: 24px;">
        <h2 class="panel-title">Buat ruang dan bagi peserta</h2>
        <p class="help-text" style="margin-top: 6px;">Buat ruang sesuai kapasitas, lalu bagi peserta otomatis berdasarkan urutan kelas dan nomor absen.</p>
        <div class="cbt-room-grid" style="margin-top: 16px;">
            <form action="{{ route('ujian-cbt.ruang.store', $ujianCbt) }}" method="POST" class="section-stack">
                @csrf
                <div class="cbt-room-form-grid">
                    <div class="field">
                        <label for="kode">Kode ruang</label>
                        <input id="kode" name="kode" type="text" class="input" placeholder="LAB-01" required>
                    </div>
                    <div class="field">
                        <label for="nama">Nama ruang</label>
                        <input id="nama" name="nama" type="text" class="input" placeholder="Labor Komputer 1" required>
                    </div>
                    <div class="field">
                        <label for="sesi_baru">Sesi</label>
                        <select id="sesi_baru" name="sesi_ujian_cbt_id" class="select">
                            <option value="">Mengikuti paket</option>
                            @foreach ($sesiUjianCbt as $sesi)
                                <option value="{{ $sesi->id }}" @selected((string) $sesiUjianCbtId === (string) $sesi->id)>{{ $sesi->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="jadwal_baru">Jadwal ujian</label>
                        <select id="jadwal_baru" name="jadwal_ujian_cbt_id" class="select">
                            <option value="">Belum dihubungkan</option>
                            @foreach ($jadwalUjianCbt as $jadwal)
                                <option value="{{ $jadwal->id }}" @selected((string) $jadwalUjianCbtId === (string) $jadwal->id)>{{ $labelJadwalUjian($jadwal) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="kapasitas">Kapasitas</label>
                        <input id="kapasitas" name="kapasitas" type="number" min="1" max="200" class="input" placeholder="30">
                    </div>
                    <div class="field span-2">
                        <label for="lokasi">Lokasi</label>
                        <input id="lokasi" name="lokasi" type="text" class="input" placeholder="Lantai 2">
                    </div>
                </div>
                <input type="hidden" name="status" value="draft">
                <button type="submit" class="button button-primary">Tambah ruang</button>
            </form>

            <div class="section-stack">
                <form action="{{ route('ujian-cbt.ruang.generate', $ujianCbt) }}" method="POST" class="panel panel-pad" style="box-shadow: none;">
                    @csrf
                    <div class="cbt-room-tools">
                        <div class="field">
                            <label for="prefix">Prefix</label>
                            <input id="prefix" name="prefix" type="text" value="RUANG" class="input" required>
                        </div>
                        <div class="field">
                            <label for="jumlah_ruang">Jumlah ruang</label>
                            <input id="jumlah_ruang" name="jumlah_ruang" type="number" min="1" max="30" value="2" class="input" required>
                        </div>
                        <div class="field">
                            <label for="kapasitas_massal">Kapasitas/ruang</label>
                            <input id="kapasitas_massal" name="kapasitas" type="number" min="1" max="200" value="30" class="input" required>
                        </div>
                        <div class="field">
                            <label for="sesi_massal">Sesi</label>
                            <select id="sesi_massal" name="sesi_ujian_cbt_id" class="select">
                                <option value="">Mengikuti paket</option>
                                @foreach ($sesiUjianCbt as $sesi)
                                    <option value="{{ $sesi->id }}" @selected((string) $sesiUjianCbtId === (string) $sesi->id)>{{ $sesi->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="jadwal_massal">Jadwal ujian</label>
                            <select id="jadwal_massal" name="jadwal_ujian_cbt_id" class="select">
                                <option value="">Belum dihubungkan</option>
                                @foreach ($jadwalUjianCbt as $jadwal)
                                    <option value="{{ $jadwal->id }}" @selected((string) $jadwalUjianCbtId === (string) $jadwal->id)>{{ $labelJadwalUjian($jadwal) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="button button-dark">Generate</button>
                    </div>
                </form>

                <form action="{{ route('ujian-cbt.ruang.bagi-otomatis', $ujianCbt) }}" method="POST" class="panel panel-pad" style="box-shadow: none;" onsubmit="return confirm('Bagi peserta sesi ini otomatis ke ruang dan nomor meja sesuai kapasitas?')">
                    @csrf
                    <div class="cbt-room-filter">
                        <div class="field cbt-room-wide-2">
                            <label for="sesi_bagi">Sesi untuk pembagian otomatis</label>
                            <select id="sesi_bagi" name="sesi_ujian_cbt_id" class="select" required>
                                <option value="">Pilih sesi</option>
                                @foreach ($sesiUjianCbt as $sesi)
                                    <option value="{{ $sesi->id }}" @selected((string) $sesiUjianCbtId === (string) $sesi->id)>{{ $sesi->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field cbt-room-wide-2">
                            <label for="jadwal_bagi">Jadwal ujian</label>
                            <select id="jadwal_bagi" name="jadwal_ujian_cbt_id" class="select">
                                <option value="">Semua ruang pada sesi</option>
                                @foreach ($jadwalUjianCbt as $jadwal)
                                    <option value="{{ $jadwal->id }}" @selected((string) $jadwalUjianCbtId === (string) $jadwal->id)>{{ $labelJadwalUjian($jadwal) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="button button-primary">Bagi peserta otomatis</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="cbt-room-grid" style="margin-bottom: 24px;">
        @forelse ($ruangUjianCbt as $ruang)
            @php
                $ruangTerkunci = $ruang->terkunci();
                $pesertaRuang = $ruang->pesertaUjianCbt;
                $jumlahHadir = $pesertaRuang->whereIn('status_kehadiran_ujian', ['hadir', 'terlambat'])->count();
                $jumlahTidakHadir = $pesertaRuang->whereIn('status_kehadiran_ujian', ['sakit', 'izin', 'alfa'])->count();
            @endphp
            <form action="{{ route('ujian-cbt.ruang.update', [$ujianCbt, $ruang]) }}" method="POST" class="panel panel-pad cbt-room-card">
                @csrf
                @method('PUT')
                <div class="cbt-room-card-head">
                    <div>
                        <h2 class="panel-title">{{ $ruang->kode }} - {{ $ruang->nama }}</h2>
                        <div class="cbt-room-meta" style="margin-top: 6px;">
                            <span>{{ $ruang->jadwalUjianCbt ? $labelJadwalUjian($ruang->jadwalUjianCbt) : 'Belum terhubung ke jadwal ujian' }}</span>
                            <span>{{ $ruang->sesiUjianCbt?->nama ?: 'Mengikuti jadwal paket' }}</span>
                            <span>{{ $ruang->lokasi ?: 'Lokasi belum diisi' }}</span>
                        </div>
                    </div>
                    <div class="actions" style="justify-content: flex-end;">
                        @if ($ruangTerkunci)
                            <span class="badge badge-active">Terkunci</span>
                        @endif
                        <span class="badge {{ $badgeRuang($ruang->status) }}">{{ $ruang->labelStatus() }}</span>
                         <a href="{{ route('ujian-cbt.ruang.cetak', [
                            $ujianCbt,
                            'sesi_ujian_cbt_id' => $ruang->sesi_ujian_cbt_id,
                            'jadwal_ujian_cbt_id' => $ruang->jadwal_ujian_cbt_id,
                            'ruang_ujian_cbt_id' => $ruang->id,
                         ]) }}" target="_blank" rel="noopener" class="button button-muted">Cetak hadir & BA</a>
                         <a href="{{ route('presensi-ujian-cbt.show', [$ujianCbt, $ruang]) }}" target="_blank" rel="noopener" class="button button-dark">Buka presensi</a>
                        @if ($ruangTerkunci)
                            <button type="submit" form="buka_kunci_ruang_{{ $ruang->id }}" class="button button-muted" onclick="return confirm('Buka kunci ruang {{ $ruang->kode }} agar bisa direvisi?')">Buka kunci</button>
                        @else
                            <button type="submit" form="kunci_ruang_{{ $ruang->id }}" class="button button-primary" onclick="return confirm('Kunci ruang {{ $ruang->kode }}? Penempatan dan nomor meja tidak bisa diubah sampai kunci dibuka.')">Kunci ruang</button>
                            <button
                                type="submit"
                                form="hapus_ruang_{{ $ruang->id }}"
                                class="button button-danger"
                                onclick="return confirm('Hapus ruang {{ $ruang->kode }}? Peserta di ruang ini akan dikembalikan menjadi belum ditempatkan.')"
                            >
                                Hapus
                            </button>
                        @endif
                    </div>
                </div>

                @if ($ruangTerkunci)
                    <p class="help-text">
                        Dikunci pada {{ $ruang->dikunci_pada?->format('d-m-Y H:i') ?: '-' }} oleh {{ $ruang->dikunciOleh?->nama ?: '-' }}. Upload bukti masih bisa dilakukan.
                    </p>
                @endif

                <dl class="quick-facts">
                    <div><dt>Kapasitas</dt><dd>{{ $ruang->kapasitas ?: '-' }}</dd></div>
                    <div><dt>Peserta</dt><dd>{{ $pesertaRuang->count() }}</dd></div>
                    <div><dt>Hadir</dt><dd>{{ $jumlahHadir }}</dd></div>
                    <div><dt>Tidak hadir</dt><dd>{{ $jumlahTidakHadir }}</dd></div>
                    <div><dt>Bukti</dt><dd>{{ $ruang->bukti_daftar_hadir_lokasi_file && $ruang->bukti_berita_acara_lokasi_file ? 'Lengkap' : 'Belum lengkap' }}</dd></div>
                </dl>

                <fieldset @disabled($ruangTerkunci) class="cbt-room-form-grid" style="border: 0; margin: 0; padding: 0;">
                    <div class="field">
                        <label for="kode_{{ $ruang->id }}">Kode</label>
                        <input id="kode_{{ $ruang->id }}" name="kode" value="{{ $ruang->kode }}" class="input" required>
                    </div>
                    <div class="field">
                        <label for="nama_{{ $ruang->id }}">Nama</label>
                        <input id="nama_{{ $ruang->id }}" name="nama" value="{{ $ruang->nama }}" class="input" required>
                    </div>
                    <div class="field">
                        <label for="sesi_{{ $ruang->id }}">Sesi</label>
                        <select id="sesi_{{ $ruang->id }}" name="sesi_ujian_cbt_id" class="select">
                            <option value="">Mengikuti paket</option>
                            @foreach ($sesiUjianCbt as $sesi)
                                <option value="{{ $sesi->id }}" @selected((string) $ruang->sesi_ujian_cbt_id === (string) $sesi->id)>{{ $sesi->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="jadwal_{{ $ruang->id }}">Jadwal ujian</label>
                        <select id="jadwal_{{ $ruang->id }}" name="jadwal_ujian_cbt_id" class="select">
                            <option value="">Belum dihubungkan</option>
                            @foreach ($jadwalUjianCbt as $jadwal)
                                <option value="{{ $jadwal->id }}" @selected((string) $ruang->jadwal_ujian_cbt_id === (string) $jadwal->id)>{{ $labelJadwalUjian($jadwal) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="status_{{ $ruang->id }}">Status</label>
                        <select id="status_{{ $ruang->id }}" name="status" class="select">
                            @foreach ($daftarStatusRuang as $nilai => $label)
                                <option value="{{ $nilai }}" @selected($ruang->status === $nilai)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="kapasitas_{{ $ruang->id }}">Kapasitas</label>
                        <input id="kapasitas_{{ $ruang->id }}" name="kapasitas" value="{{ $ruang->kapasitas }}" type="number" min="1" max="200" class="input">
                    </div>
                    <div class="field">
                        <label for="lokasi_{{ $ruang->id }}">Lokasi</label>
                        <input id="lokasi_{{ $ruang->id }}" name="lokasi" value="{{ $ruang->lokasi }}" class="input">
                    </div>
                    <div class="field">
                        <label for="pengawas_utama_{{ $ruang->id }}">Pengawas utama</label>
                        <select id="pengawas_utama_{{ $ruang->id }}" name="pengawas_utama_pegawai_id" class="select">
                            <option value="">Belum dipilih</option>
                            @foreach ($daftarPegawai as $pegawai)
                                <option value="{{ $pegawai->id }}" @selected((string) $ruang->pengawas_utama_pegawai_id === (string) $pegawai->id)>{{ $pegawai->nama_lengkap }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="pengawas_pendamping_{{ $ruang->id }}">Pengawas pendamping</label>
                        <select id="pengawas_pendamping_{{ $ruang->id }}" name="pengawas_pendamping_pegawai_id" class="select">
                            <option value="">Belum dipilih</option>
                            @foreach ($daftarPegawai as $pegawai)
                                <option value="{{ $pegawai->id }}" @selected((string) $ruang->pengawas_pendamping_pegawai_id === (string) $pegawai->id)>{{ $pegawai->nama_lengkap }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="mulai_{{ $ruang->id }}">Mulai aktual</label>
                        <input id="mulai_{{ $ruang->id }}" name="waktu_mulai_aktual" value="{{ $formatTanggalInput($ruang->waktu_mulai_aktual) }}" type="datetime-local" class="input">
                    </div>
                    <div class="field">
                        <label for="selesai_{{ $ruang->id }}">Selesai aktual</label>
                        <input id="selesai_{{ $ruang->id }}" name="waktu_selesai_aktual" value="{{ $formatTanggalInput($ruang->waktu_selesai_aktual) }}" type="datetime-local" class="input">
                    </div>
                    <div class="field span-2">
                        <label for="berita_{{ $ruang->id }}">Berita acara</label>
                        <textarea id="berita_{{ $ruang->id }}" name="berita_acara" class="textarea" placeholder="Tuliskan jalannya ujian di ruang ini.">{{ $ruang->berita_acara }}</textarea>
                    </div>
                    <div class="field">
                        <label for="hambatan_{{ $ruang->id }}">Hambatan</label>
                        <textarea id="hambatan_{{ $ruang->id }}" name="hambatan" class="textarea" placeholder="Contoh: jaringan lambat, perangkat bermasalah.">{{ $ruang->hambatan }}</textarea>
                    </div>
                    <div class="field">
                        <label for="tindak_lanjut_{{ $ruang->id }}">Tindak lanjut</label>
                        <textarea id="tindak_lanjut_{{ $ruang->id }}" name="tindak_lanjut" class="textarea" placeholder="Solusi atau catatan lanjutan.">{{ $ruang->tindak_lanjut }}</textarea>
                    </div>
                    <div class="field span-2">
                        <label for="catatan_{{ $ruang->id }}">Catatan internal</label>
                        <textarea id="catatan_{{ $ruang->id }}" name="catatan" class="textarea">{{ $ruang->catatan }}</textarea>
                    </div>
                </fieldset>

                <div class="cbt-proof-grid">
                    <section class="cbt-proof-box">
                        <div class="cbt-proof-head">
                            <h3 class="cbt-proof-title">Bukti daftar hadir</h3>
                            <span class="badge {{ $ruang->bukti_daftar_hadir_lokasi_file ? 'badge-active' : 'badge-muted' }}">{{ $ruang->bukti_daftar_hadir_lokasi_file ? 'Sudah upload' : 'Belum upload' }}</span>
                        </div>
                        @if ($ruang->bukti_daftar_hadir_lokasi_file)
                            <div class="cbt-proof-file">
                                <strong>{{ $ruang->bukti_daftar_hadir_nama_file_asli ?: 'Bukti daftar hadir' }}</strong>
                                <span>{{ $formatUkuranFile($ruang->bukti_daftar_hadir_ukuran_file) }} - {{ $ruang->bukti_daftar_hadir_diunggah_pada?->format('d-m-Y H:i') ?: '-' }}</span>
                                <span>Oleh {{ $ruang->buktiDaftarHadirDiunggahOleh?->nama ?: '-' }}</span>
                            </div>
                            <div class="actions">
                                <a href="{{ route('ujian-cbt.ruang.bukti.download', [$ujianCbt, $ruang, 'daftar-hadir']) }}" class="button button-muted">Unduh</a>
                                <button type="submit" form="hapus_bukti_daftar_hadir_{{ $ruang->id }}" class="button button-danger" onclick="return confirm('Hapus bukti daftar hadir ruang {{ $ruang->kode }}?')">Hapus bukti</button>
                            </div>
                        @else
                            <p class="help-text">Upload foto/PDF daftar hadir yang sudah ditandatangani peserta.</p>
                        @endif
                        <div class="field">
                            <label for="bukti_daftar_hadir_{{ $ruang->id }}">Upload/revisi daftar hadir</label>
                            <input id="bukti_daftar_hadir_{{ $ruang->id }}" form="upload_bukti_{{ $ruang->id }}" name="bukti_daftar_hadir" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" class="input">
                        </div>
                    </section>

                    <section class="cbt-proof-box">
                        <div class="cbt-proof-head">
                            <h3 class="cbt-proof-title">Bukti berita acara</h3>
                            <span class="badge {{ $ruang->bukti_berita_acara_lokasi_file ? 'badge-active' : 'badge-muted' }}">{{ $ruang->bukti_berita_acara_lokasi_file ? 'Sudah upload' : 'Belum upload' }}</span>
                        </div>
                        @if ($ruang->bukti_berita_acara_lokasi_file)
                            <div class="cbt-proof-file">
                                <strong>{{ $ruang->bukti_berita_acara_nama_file_asli ?: 'Bukti berita acara' }}</strong>
                                <span>{{ $formatUkuranFile($ruang->bukti_berita_acara_ukuran_file) }} - {{ $ruang->bukti_berita_acara_diunggah_pada?->format('d-m-Y H:i') ?: '-' }}</span>
                                <span>Oleh {{ $ruang->buktiBeritaAcaraDiunggahOleh?->nama ?: '-' }}</span>
                            </div>
                            <div class="actions">
                                <a href="{{ route('ujian-cbt.ruang.bukti.download', [$ujianCbt, $ruang, 'berita-acara']) }}" class="button button-muted">Unduh</a>
                                <button type="submit" form="hapus_bukti_berita_acara_{{ $ruang->id }}" class="button button-danger" onclick="return confirm('Hapus bukti berita acara ruang {{ $ruang->kode }}?')">Hapus bukti</button>
                            </div>
                        @else
                            <p class="help-text">Upload foto/PDF berita acara yang sudah ditandatangani pengawas.</p>
                        @endif
                        <div class="field">
                            <label for="bukti_berita_acara_{{ $ruang->id }}">Upload/revisi berita acara</label>
                            <input id="bukti_berita_acara_{{ $ruang->id }}" form="upload_bukti_{{ $ruang->id }}" name="bukti_berita_acara" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" class="input">
                        </div>
                    </section>
                </div>

                <div class="actions">
                    <button type="submit" form="upload_bukti_{{ $ruang->id }}" class="button button-primary">Upload bukti</button>
                </div>
                @unless ($ruangTerkunci)
                    <button type="submit" class="button button-dark">Simpan ruang & berita acara</button>
                @endunless
            </form>

            <form id="upload_bukti_{{ $ruang->id }}" action="{{ route('ujian-cbt.ruang.bukti.update', [$ujianCbt, $ruang]) }}" method="POST" enctype="multipart/form-data" style="display: none;">
                @csrf
            </form>

            <form id="hapus_bukti_daftar_hadir_{{ $ruang->id }}" action="{{ route('ujian-cbt.ruang.bukti.destroy', [$ujianCbt, $ruang, 'daftar-hadir']) }}" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>

            <form id="hapus_bukti_berita_acara_{{ $ruang->id }}" action="{{ route('ujian-cbt.ruang.bukti.destroy', [$ujianCbt, $ruang, 'berita-acara']) }}" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>

            <form id="hapus_ruang_{{ $ruang->id }}" action="{{ route('ujian-cbt.ruang.destroy', [$ujianCbt, $ruang]) }}" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>

            <form id="kunci_ruang_{{ $ruang->id }}" action="{{ route('ujian-cbt.ruang.kunci', [$ujianCbt, $ruang]) }}" method="POST" style="display: none;">
                @csrf
                @method('PUT')
            </form>

            <form id="buka_kunci_ruang_{{ $ruang->id }}" action="{{ route('ujian-cbt.ruang.buka-kunci', [$ujianCbt, $ruang]) }}" method="POST" style="display: none;">
                @csrf
                @method('PUT')
            </form>
        @empty
            <div class="panel panel-pad">
                <div class="empty-state">Belum ada ruang CBT untuk filter ini.</div>
            </div>
        @endforelse
    </section>

    <form action="{{ route('ujian-cbt.ruang.peserta.update', $ujianCbt) }}" method="POST" class="panel">
        @csrf
        @method('PUT')
        <input type="hidden" name="filter_sesi_ujian_cbt_id" value="{{ $sesiUjianCbtId }}">
        <input type="hidden" name="filter_jadwal_ujian_cbt_id" value="{{ $jadwalUjianCbtId }}">
        <input type="hidden" name="filter_ruang_ujian_cbt_id" value="{{ $ruangUjianCbtId }}">
        <div class="panel-pad" style="display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
            <div>
                <h2 class="panel-title">Peserta, nomor meja, dan presensi</h2>
                <p class="help-text">Atur ruang, nomor meja, status hadir, dan catatan presensi ujian siswa.</p>
            </div>
            <button type="submit" class="button button-primary">Simpan presensi peserta</button>
        </div>

        <div class="table-wrap">
            <table class="employee-table cbt-room-table">
                <thead>
                    <tr>
                        <th>Peserta</th>
                        <th>Kelas/Sesi</th>
                        <th>Ruang</th>
                        <th>No. Meja</th>
                        <th>Presensi</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pesertaUjianCbt as $peserta)
                        <tr>
                            <td>
                                <p class="person-name">{{ $peserta->anggotaKelas?->siswa?->nama_lengkap ?: '-' }}</p>
                                <p class="person-meta">NISN {{ $peserta->anggotaKelas?->siswa?->nisn ?: '-' }}</p>
                            </td>
                            <td>
                                <p class="person-name">{{ $peserta->kelasUjianCbt?->kelas?->nama ?: '-' }}</p>
                                <p class="person-meta">{{ $peserta->sesiUjianCbt?->nama ?: 'Tanpa sesi' }}</p>
                            </td>
                            <td>
                                <select name="peserta[{{ $peserta->id }}][ruang_ujian_cbt_id]" class="select">
                                    <option value="">Belum ditempatkan</option>
                                    @foreach ($ruangUjianCbt as $ruang)
                                        @php
                                            $ruangCocokSesi = ! $ruang->sesi_ujian_cbt_id || ! $peserta->sesi_ujian_cbt_id || (int) $ruang->sesi_ujian_cbt_id === (int) $peserta->sesi_ujian_cbt_id;
                                        @endphp
                                        @if ($ruangCocokSesi)
                                            <option value="{{ $ruang->id }}" @selected((string) $peserta->ruang_ujian_cbt_id === (string) $ruang->id)>{{ $ruang->kode }} - {{ $ruang->nama }}{{ $ruang->terkunci() ? ' - terkunci' : '' }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input name="peserta[{{ $peserta->id }}][nomor_meja]" value="{{ $peserta->nomor_meja }}" type="number" min="1" max="999" class="input" style="max-width: 110px;">
                            </td>
                            <td>
                                <select name="peserta[{{ $peserta->id }}][status_kehadiran_ujian]" class="select">
                                    @foreach ($daftarStatusKehadiran as $nilai => $label)
                                        <option value="{{ $nilai }}" @selected($peserta->status_kehadiran_ujian === $nilai)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div style="margin-top: 8px;">
                                    <span class="badge {{ $badgeKehadiran($peserta->status_kehadiran_ujian) }}">{{ $peserta->labelStatusKehadiranUjian() }}</span>
                                </div>
                                @if ($peserta->absen_ujian_pada)
                                    <p class="person-meta">{{ $peserta->absen_ujian_pada->format('d-m-Y H:i') }}</p>
                                @endif
                            </td>
                            <td>
                                <textarea name="peserta[{{ $peserta->id }}][catatan_kehadiran_ujian]" class="textarea" style="min-height: 76px;">{{ $peserta->catatan_kehadiran_ujian }}</textarea>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada peserta CBT yang sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pesertaUjianCbt->isNotEmpty())
            <div class="panel-pad" style="border-top: 1px solid var(--line);">
                <div class="actions" style="justify-content: flex-end;">
                    <button type="submit" class="button button-primary">Simpan presensi peserta</button>
                </div>
            </div>
        @endif
    </form>
@endsection
