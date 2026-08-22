@extends('layouts.app')

@section('title', 'Persiapan Ujian Terpusat - NUSA')

@section('content')
    <style>
        .central-summary {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(280px, .65fr);
            gap: 0;
            overflow: hidden;
            margin-bottom: 18px;
            background: var(--primary);
            color: #fff;
        }

        .central-summary-main,
        .central-summary-side {
            padding: 22px 24px;
        }

        .central-summary-side {
            border-left: 1px solid rgba(255, 255, 255, .18);
            background: rgba(255, 255, 255, .08);
        }

        .central-summary h2 {
            margin: 0;
            color: #fff;
            font-size: 1.4rem;
        }

        .central-summary p {
            margin: 7px 0 0;
            color: rgba(255, 255, 255, .8);
        }

        .central-summary .quick-facts {
            margin-top: 16px;
        }

        .central-summary .quick-facts div {
            border-color: rgba(255, 255, 255, .2);
            background: rgba(255, 255, 255, .08);
        }

        .central-summary .quick-facts dt {
            color: rgba(255, 255, 255, .72);
        }

        .central-summary .quick-facts dd {
            color: #fff;
        }

        .central-steps {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 24px;
        }

        .central-step {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            gap: 10px;
            align-items: center;
            min-height: 64px;
            border: 1px solid var(--line);
            border-left: 4px solid var(--line);
            border-radius: 7px;
            background: #fff;
            padding: 11px 12px;
            color: inherit;
            text-decoration: none;
        }

        .central-step.complete {
            border-left-color: #15803d;
            background: #f4fbf6;
        }

        .central-step > .central-step-number {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            flex: 0 0 34px;
            border-radius: 50%;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: .82rem;
            font-weight: 900;
            line-height: 1;
            margin: 0;
        }

        .central-step.complete .central-step-number {
            background: #dcf3e3;
            color: #166534;
        }

        .central-step-copy,
        .central-step-copy strong,
        .central-step-status {
            display: block;
        }

        .central-step-copy {
            min-width: 0;
        }

        .central-step-status {
            margin-top: 2px;
            color: var(--muted);
            font-size: .74rem;
            font-weight: 700;
        }

        .central-workspace {
            display: grid;
            gap: 20px;
        }

        .central-section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--line);
        }

        .central-add-grid {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) minmax(150px, .45fr) minmax(180px, .65fr) auto;
            gap: 12px;
            align-items: end;
            padding: 16px 0;
            border-bottom: 1px solid var(--line);
        }

        .central-add-grid.session {
            grid-template-columns: minmax(180px, 1fr) 150px 150px minmax(180px, .8fr) auto;
        }

        .central-add-grid.room {
            grid-template-columns: minmax(180px, 1fr) minmax(170px, .7fr) 120px minmax(180px, .75fr) auto;
        }

        .central-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 16px;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--line);
        }

        .central-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .central-row-main {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: 12px;
        }

        .central-initial {
            display: grid;
            width: 42px;
            height: 42px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 7px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: .78rem;
            font-weight: 900;
        }

        .central-row-copy {
            min-width: 0;
        }

        .central-row-copy strong,
        .central-row-copy span {
            display: block;
        }

        .central-row-copy strong {
            overflow-wrap: anywhere;
        }

        .central-row-copy span {
            margin-top: 3px;
            color: var(--muted);
            font-size: .79rem;
            font-weight: 700;
        }

        .central-row-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .central-edit-details > summary {
            list-style: none;
        }

        .central-edit-details > summary::-webkit-details-marker {
            display: none;
        }

        .central-edit-panel {
            grid-column: 1 / -1;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 7px;
            background: #f8fafc;
        }

        .central-edit-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            align-items: end;
        }

        @media (max-width: 1050px) {
            .central-add-grid,
            .central-add-grid.session,
            .central-add-grid.room,
            .central-edit-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .central-add-grid .actions,
            .central-edit-grid .actions {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 760px) {
            .central-summary {
                grid-template-columns: 1fr;
            }

            .central-summary-side {
                border-top: 1px solid rgba(255, 255, 255, .18);
                border-left: 0;
            }

            .central-steps {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .central-summary-main,
            .central-summary-side {
                padding: 18px;
            }

            .central-steps,
            .central-add-grid,
            .central-add-grid.session,
            .central-add-grid.room,
            .central-edit-grid {
                grid-template-columns: 1fr;
            }

            .central-add-grid .actions,
            .central-edit-grid .actions {
                grid-column: auto;
            }

            .central-add-grid .actions .button,
            .central-edit-grid .actions .button {
                width: 100%;
            }

            .central-row {
                grid-template-columns: 1fr;
            }

            .central-row-actions,
            .central-row-actions .button,
            .central-row-actions form {
                width: 100%;
            }

            .central-row-actions .button {
                flex: 1 1 0;
            }
        }
    </style>

    @php
        $panitiaSiap = $kegiatan->panitiaUjianCbt->isNotEmpty();
        $sesiSiap = $kegiatan->sesiKegiatanUjianCbt->isNotEmpty();
        $ruangSiap = $kegiatan->ruangKegiatanUjianCbt->isNotEmpty();
        $pelaksanaanSiap = $kegiatan->kelompokPesertaKegiatanUjianCbt->isNotEmpty() && $kegiatan->jadwal_ujian_cbt_count > 0;
        $jumlahPaketSiap = $kegiatan->jadwalUjianCbt->filter(fn ($jadwal) => $jadwal->ujianCbt && in_array($jadwal->ujianCbt->status, ['terjadwal', 'berlangsung', 'selesai'], true))->count();
        $paketSiap = $kegiatan->jadwal_ujian_cbt_count > 0 && $jumlahPaketSiap === $kegiatan->jadwal_ujian_cbt_count;
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian Terpusat</p>
            <h1 class="page-title">Ruang kerja panitia</h1>
            <p class="page-subtitle">{{ $kegiatan->nama }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('ujian-terpusat.index') }}" class="button button-muted">Daftar kegiatan</a>
            @if ($bolehKelolaUtama)
                <a href="{{ route('ujian-terpusat.edit', $kegiatan) }}" class="button button-primary">Edit informasi</a>
            @endif
            <a href="{{ route('ujian-terpusat.pelaksanaan.index', $kegiatan) }}" class="button button-dark">Jadwal & peserta</a>
            <a href="{{ route('paket-soal-terpusat.index', ['kegiatan' => $kegiatan->id]) }}" class="button button-dark">Paket soal</a>
            <a href="{{ route('ujian-terpusat.pelaksanaan-nilai.index', $kegiatan) }}" class="button button-dark">Pelaksanaan & nilai</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <section class="panel central-summary">
        <div class="central-summary-main">
            <p class="eyebrow" style="color: var(--accent);">{{ $kegiatan->jenisUjianCbt?->nama ?: 'Ujian Terpusat' }}</p>
            <h2>{{ $kegiatan->nama }}</h2>
            <p>{{ $kegiatan->tahunPelajaran?->nama ?: '-' }} · Semester {{ ucfirst($kegiatan->semester) }}</p>
            <dl class="quick-facts">
                <div><dt>Periode</dt><dd>{{ $kegiatan->labelPeriode() }}</dd></div>
                <div><dt>Kode</dt><dd>{{ $kegiatan->kode }}</dd></div>
            </dl>
        </div>
        <div class="central-summary-side">
            <span class="badge {{ $kegiatan->status === 'aktif' ? 'badge-active' : ($kegiatan->status === 'draft' ? 'badge-warning' : 'badge-muted') }}">{{ $kegiatan->labelStatus() }}</span>
            <dl class="quick-facts">
                <div><dt>Panitia</dt><dd>{{ $kegiatan->panitiaUjianCbt->count() }} orang</dd></div>
                <div><dt>Sesi</dt><dd>{{ $kegiatan->sesiKegiatanUjianCbt->count() }}</dd></div>
                <div><dt>Ruang</dt><dd>{{ $kegiatan->ruangKegiatanUjianCbt->count() }}</dd></div>
                <div><dt>Total kapasitas</dt><dd>{{ $kegiatan->ruangKegiatanUjianCbt->where('aktif', true)->sum('kapasitas') }}</dd></div>
            </dl>
        </div>
    </section>

    <nav class="central-steps" aria-label="Tahap persiapan Ujian Terpusat">
        <a href="{{ $bolehKelolaUtama ? route('ujian-terpusat.edit', $kegiatan) : '#panitia' }}" class="central-step complete"><span class="central-step-number">1</span><span class="central-step-copy"><strong>Informasi</strong><span class="central-step-status">Sudah diisi</span></span></a>
        <a href="#panitia" class="central-step {{ $panitiaSiap ? 'complete' : '' }}"><span class="central-step-number">2</span><span class="central-step-copy"><strong>Panitia</strong><span class="central-step-status">{{ $panitiaSiap ? 'Sudah diisi' : 'Belum diisi' }}</span></span></a>
        <a href="#sesi" class="central-step {{ $sesiSiap ? 'complete' : '' }}"><span class="central-step-number">3</span><span class="central-step-copy"><strong>Sesi</strong><span class="central-step-status">{{ $sesiSiap ? 'Sudah diisi' : 'Belum diisi' }}</span></span></a>
        <a href="#ruang" class="central-step {{ $ruangSiap ? 'complete' : '' }}"><span class="central-step-number">4</span><span class="central-step-copy"><strong>Ruang</strong><span class="central-step-status">{{ $ruangSiap ? 'Sudah diisi' : 'Belum diisi' }}</span></span></a>
        <a href="{{ route('ujian-terpusat.pelaksanaan.index', $kegiatan) }}" class="central-step {{ $pelaksanaanSiap ? 'complete' : '' }}"><span class="central-step-number">5</span><span class="central-step-copy"><strong>Jadwal & peserta</strong><span class="central-step-status">{{ $pelaksanaanSiap ? 'Sudah disusun' : 'Lanjutkan' }}</span></span></a>
        <a href="{{ route('paket-soal-terpusat.index', ['kegiatan' => $kegiatan->id]) }}" class="central-step {{ $paketSiap ? 'complete' : '' }}"><span class="central-step-number">6</span><span class="central-step-copy"><strong>Paket soal</strong><span class="central-step-status">{{ $paketSiap ? 'Semua siap' : $jumlahPaketSiap.' siap' }}</span></span></a>
        <a href="{{ route('ujian-terpusat.pelaksanaan-nilai.index', $kegiatan) }}" class="central-step {{ $paketSiap ? 'complete' : '' }}"><span class="central-step-number">7</span><span class="central-step-copy"><strong>Pelaksanaan & nilai</strong><span class="central-step-status">{{ $paketSiap ? 'Siap dipantau' : 'Menunggu paket' }}</span></span></a>
    </nav>

    <div class="central-workspace">
        <section id="panitia" class="panel panel-pad">
            <div class="central-section-head">
                <div><p class="eyebrow">Tahap 2</p><h2 class="panel-title">Panitia ujian</h2><p class="help-text" style="margin-top: 5px;">Pegawai yang ditugaskan mendapat akses Panitia Ujian pada akun NUSA.</p></div>
                <span class="badge {{ $panitiaSiap ? 'badge-active' : 'badge-warning' }}">{{ $kegiatan->panitiaUjianCbt->count() }} orang</span>
            </div>

            @if ($bolehKelolaUtama)
                <form action="{{ route('ujian-terpusat.panitia.store', $kegiatan) }}" method="POST" class="central-add-grid">
                    @csrf
                    <div class="field">
                        <label for="pegawai_id">Pegawai</label>
                        <select id="pegawai_id" name="pegawai_id" class="select" required>
                            <option value="">Pilih pegawai</option>
                            @foreach ($daftarPegawai as $pegawai)
                                <option value="{{ $pegawai->id }}">{{ $pegawai->nama_lengkap }}{{ $pegawai->pengguna ? '' : ' - belum memiliki akun' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="jabatan">Tugas</label>
                        <select id="jabatan" name="jabatan" class="select" required>
                            @foreach ($daftarJabatan as $kode => $label)<option value="{{ $kode }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="catatan_panitia">Catatan</label>
                        <input id="catatan_panitia" name="catatan" class="input" placeholder="Opsional">
                    </div>
                    <div class="actions"><button type="submit" class="button button-primary">Tambahkan</button></div>
                </form>
            @endif

            <div>
                @forelse ($kegiatan->panitiaUjianCbt as $panitia)
                    <div class="central-row">
                        <div class="central-row-main">
                            <span class="central-initial">{{ str($panitia->pegawai?->nama_lengkap ?: '?')->substr(0, 2)->upper() }}</span>
                            <div class="central-row-copy">
                                <strong>{{ $panitia->pegawai?->nama_lengkap ?: '-' }}</strong>
                                <span>{{ $panitia->labelJabatan() }} · {{ $panitia->pegawai?->nip ?: 'Tanpa NIP' }}{{ $panitia->pegawai?->pengguna ? '' : ' · akun NUSA belum tersedia' }}</span>
                            </div>
                        </div>
                        @if ($bolehKelolaUtama)
                            <div class="central-row-actions">
                                <form action="{{ route('ujian-terpusat.panitia.destroy', [$kegiatan, $panitia]) }}" method="POST" onsubmit="return confirm('Hapus {{ $panitia->pegawai?->nama_lengkap }} dari panitia ujian?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="button button-danger">Hapus</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="empty-state">Panitia belum ditentukan.</div>
                @endforelse
            </div>
        </section>

        <section id="sesi" class="panel panel-pad">
            <div class="central-section-head">
                <div><p class="eyebrow">Tahap 3</p><h2 class="panel-title">Sesi ujian</h2><p class="help-text" style="margin-top: 5px;">Sesi adalah pembagian waktu ujian dalam satu hari, misalnya sesi pagi dan sesi siang.</p></div>
                <span class="badge {{ $sesiSiap ? 'badge-active' : 'badge-warning' }}">{{ $kegiatan->sesiKegiatanUjianCbt->count() }} sesi</span>
            </div>

            @if ($bolehKelolaPersiapan)
                <form action="{{ route('ujian-terpusat.sesi.store', $kegiatan) }}" method="POST" class="central-add-grid session">
                    @csrf
                    <div class="field"><label for="nama_sesi">Nama sesi</label><input id="nama_sesi" name="nama" class="input" placeholder="Contoh: Sesi Pagi" required></div>
                    <div class="field"><label for="waktu_mulai_sesi">Mulai</label><input id="waktu_mulai_sesi" name="waktu_mulai" type="time" class="input" required></div>
                    <div class="field"><label for="waktu_selesai_sesi">Selesai</label><input id="waktu_selesai_sesi" name="waktu_selesai" type="time" class="input" required></div>
                    <div class="field"><label for="keterangan_sesi">Catatan</label><input id="keterangan_sesi" name="keterangan" class="input" placeholder="Opsional"><input type="hidden" name="aktif" value="1"></div>
                    <div class="actions"><button type="submit" class="button button-primary">Tambahkan</button></div>
                </form>
            @endif

            <div>
                @forelse ($kegiatan->sesiKegiatanUjianCbt as $sesi)
                    <div class="central-row">
                        <div class="central-row-main">
                            <span class="central-initial">{{ $sesi->kode }}</span>
                            <div class="central-row-copy"><strong>{{ $sesi->nama }}</strong><span>{{ $sesi->labelWaktu() }}{{ $sesi->keterangan ? ' · '.$sesi->keterangan : '' }}</span></div>
                        </div>
                        @if ($bolehKelolaPersiapan)
                            <div class="central-row-actions">
                                <details class="central-edit-details">
                                    <summary class="button button-muted">Edit</summary>
                                </details>
                                <form action="{{ route('ujian-terpusat.sesi.destroy', [$kegiatan, $sesi]) }}" method="POST" onsubmit="return confirm('Hapus {{ $sesi->nama }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="button button-danger">Hapus</button>
                                </form>
                            </div>
                            <div class="central-edit-panel" hidden data-edit-panel>
                                <form action="{{ route('ujian-terpusat.sesi.update', [$kegiatan, $sesi]) }}" method="POST" class="central-edit-grid">
                                    @csrf @method('PUT')
                                    <div class="field"><label for="nama_sesi_{{ $sesi->id }}">Nama</label><input id="nama_sesi_{{ $sesi->id }}" name="nama" value="{{ $sesi->nama }}" class="input" required></div>
                                    <div class="field"><label for="mulai_sesi_{{ $sesi->id }}">Mulai</label><input id="mulai_sesi_{{ $sesi->id }}" name="waktu_mulai" value="{{ substr($sesi->waktu_mulai, 0, 5) }}" type="time" class="input" required></div>
                                    <div class="field"><label for="selesai_sesi_{{ $sesi->id }}">Selesai</label><input id="selesai_sesi_{{ $sesi->id }}" name="waktu_selesai" value="{{ substr($sesi->waktu_selesai, 0, 5) }}" type="time" class="input" required></div>
                                    <div class="field"><label for="catatan_sesi_{{ $sesi->id }}">Catatan</label><input id="catatan_sesi_{{ $sesi->id }}" name="keterangan" value="{{ $sesi->keterangan }}" class="input"><input type="hidden" name="aktif" value="{{ $sesi->aktif ? 1 : 0 }}"></div>
                                    <div class="actions"><button type="submit" class="button button-dark">Simpan sesi</button></div>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="empty-state">Sesi ujian belum ditentukan.</div>
                @endforelse
            </div>
        </section>

        <section id="ruang" class="panel panel-pad">
            <div class="central-section-head">
                <div><p class="eyebrow">Tahap 4</p><h2 class="panel-title">Ruang ujian</h2><p class="help-text" style="margin-top: 5px;">Ruang dibuat satu kali untuk seluruh rangkaian ujian. Nomor ruang mengikuti urutan penambahan.</p></div>
                <span class="badge {{ $ruangSiap ? 'badge-active' : 'badge-warning' }}">{{ $kegiatan->ruangKegiatanUjianCbt->count() }} ruang</span>
            </div>

            @if ($bolehKelolaPersiapan)
                <form action="{{ route('ujian-terpusat.ruang.store', $kegiatan) }}" method="POST" class="central-add-grid room">
                    @csrf
                    <div class="field"><label for="nama_ruang">Nama ruang</label><input id="nama_ruang" name="nama" class="input" placeholder="Contoh: Ruang 1" required></div>
                    <div class="field"><label for="lokasi_ruang">Lokasi</label><input id="lokasi_ruang" name="lokasi" class="input" placeholder="Contoh: Kelas VII.A"></div>
                    <div class="field"><label for="kapasitas_ruang">Kapasitas</label><input id="kapasitas_ruang" name="kapasitas" type="number" min="1" max="100" value="20" class="input" required></div>
                    <div class="field"><label for="keterangan_ruang">Catatan</label><input id="keterangan_ruang" name="keterangan" class="input" placeholder="Opsional"><input type="hidden" name="aktif" value="1"></div>
                    <div class="actions"><button type="submit" class="button button-primary">Tambahkan</button></div>
                </form>
            @endif

            <div>
                @forelse ($kegiatan->ruangKegiatanUjianCbt as $ruang)
                    <div class="central-row">
                        <div class="central-row-main">
                            <span class="central-initial">{{ $ruang->kode }}</span>
                            <div class="central-row-copy"><strong>{{ $ruang->nama }}</strong><span>{{ $ruang->lokasi ?: 'Lokasi belum diisi' }} · Kapasitas {{ $ruang->kapasitas }} siswa{{ $ruang->keterangan ? ' · '.$ruang->keterangan : '' }}</span></div>
                        </div>
                        @if ($bolehKelolaPersiapan)
                            <div class="central-row-actions">
                                <details class="central-edit-details"><summary class="button button-muted">Edit</summary></details>
                                <form action="{{ route('ujian-terpusat.ruang.destroy', [$kegiatan, $ruang]) }}" method="POST" onsubmit="return confirm('Hapus {{ $ruang->nama }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="button button-danger">Hapus</button>
                                </form>
                            </div>
                            <div class="central-edit-panel" hidden data-edit-panel>
                                <form action="{{ route('ujian-terpusat.ruang.update', [$kegiatan, $ruang]) }}" method="POST" class="central-edit-grid">
                                    @csrf @method('PUT')
                                    <div class="field"><label for="nama_ruang_{{ $ruang->id }}">Nama</label><input id="nama_ruang_{{ $ruang->id }}" name="nama" value="{{ $ruang->nama }}" class="input" required></div>
                                    <div class="field"><label for="lokasi_ruang_{{ $ruang->id }}">Lokasi</label><input id="lokasi_ruang_{{ $ruang->id }}" name="lokasi" value="{{ $ruang->lokasi }}" class="input"></div>
                                    <div class="field"><label for="kapasitas_ruang_{{ $ruang->id }}">Kapasitas</label><input id="kapasitas_ruang_{{ $ruang->id }}" name="kapasitas" value="{{ $ruang->kapasitas }}" type="number" min="1" max="100" class="input" required></div>
                                    <div class="field"><label for="catatan_ruang_{{ $ruang->id }}">Catatan</label><input id="catatan_ruang_{{ $ruang->id }}" name="keterangan" value="{{ $ruang->keterangan }}" class="input"><input type="hidden" name="aktif" value="{{ $ruang->aktif ? 1 : 0 }}"></div>
                                    <div class="actions"><button type="submit" class="button button-dark">Simpan ruang</button></div>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="empty-state">Ruang ujian belum ditentukan.</div>
                @endforelse
            </div>
        </section>
    </div>

    @if ($bolehKelolaUtama && $kegiatan->status === 'draft' && $kegiatan->jadwal_ujian_cbt_count === 0)
        <form action="{{ route('ujian-terpusat.destroy', $kegiatan) }}" method="POST" style="margin-top: 22px;" onsubmit="return confirm('Hapus Ujian Terpusat {{ $kegiatan->nama }} beserta panitia, sesi, dan ruangnya?')">
            @csrf @method('DELETE')
            <button type="submit" class="button button-danger">Hapus kegiatan persiapan</button>
        </form>
    @endif

    <script>
        document.querySelectorAll('.central-edit-details').forEach((details) => {
            const row = details.closest('.central-row');
            const panel = row?.querySelector('[data-edit-panel]');
            details.addEventListener('toggle', () => {
                if (panel) panel.hidden = !details.open;
            });
        });
    </script>
@endsection
