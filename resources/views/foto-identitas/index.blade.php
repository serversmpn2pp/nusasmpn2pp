@extends('layouts.app')

@section('title', 'Foto Identitas - NUSA')

@section('content')
    <style>
        .identity-photo-shell {
            display: grid;
            gap: 18px;
        }

        .identity-tab-list {
            display: inline-grid;
            grid-auto-flow: column;
            grid-auto-columns: minmax(150px, 1fr);
            width: fit-content;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 7px;
            background: #fff;
        }

        .identity-tab {
            display: flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            border-right: 1px solid var(--line);
            padding: 9px 16px;
            color: #425466;
            font-size: .86rem;
            font-weight: 850;
            text-decoration: none;
        }

        .identity-tab:last-child {
            border-right: 0;
        }

        .identity-tab.is-active {
            background: #15477a;
            color: #fff;
        }

        .identity-filter-grid {
            display: grid;
            grid-template-columns: 190px 190px 170px minmax(230px, 1fr) auto;
            gap: 12px;
            align-items: end;
        }

        .identity-filter-grid.employee {
            grid-template-columns: 190px 160px 170px minmax(230px, 1fr) auto;
        }

        .identity-filter-grid .actions {
            flex-wrap: nowrap;
        }

        .identity-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .identity-stat-card {
            min-height: 100px;
            border: 1px solid #cbd8e5;
            border-radius: 8px;
            background: #fff;
            padding: 16px;
            box-shadow: 0 4px 14px rgba(21, 71, 122, .06);
        }

        .identity-stat-card.is-complete {
            border-color: #85c7a2;
            background: #f0fbf5;
        }

        .identity-stat-card.is-missing {
            border-color: #ddb400;
            background: #fff9dc;
        }

        .identity-list-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 14px;
        }

        .identity-list-heading h2 {
            margin: 0;
            font-size: 1.05rem;
            letter-spacing: 0;
        }

        .identity-list-heading p {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: .78rem;
        }

        .identity-auto-badge {
            flex: 0 0 auto;
            border: 1px solid #a7d8bd;
            border-radius: 999px;
            background: #effbf4;
            padding: 7px 10px;
            color: #17643a;
            font-size: .72rem;
            font-weight: 850;
        }

        .identity-photo-list {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            box-shadow: var(--shadow);
        }

        .identity-photo-row {
            display: grid;
            grid-template-columns: 54px minmax(220px, 1fr) minmax(280px, 330px);
            gap: 16px;
            align-items: center;
            min-width: 0;
            border-bottom: 1px solid var(--line);
            padding: 14px 16px;
            transition: background-color .18s ease, box-shadow .18s ease;
        }

        .identity-photo-row:last-child {
            border-bottom: 0;
        }

        .identity-photo-row.is-next-target {
            background: #fffbea;
            box-shadow: inset 4px 0 0 #f1c40f;
        }

        .identity-order {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: 7px;
            background: #e8f0f8;
            color: #15477a;
            font-size: .9rem;
            font-weight: 900;
        }

        .identity-person {
            min-width: 0;
        }

        .identity-person h3 {
            margin: 0;
            overflow-wrap: anywhere;
            font-size: .94rem;
            letter-spacing: 0;
            line-height: 1.35;
        }

        .identity-person-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 5px 12px;
            margin-top: 5px;
            color: #64748b;
            font-size: .76rem;
        }

        .identity-person-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            align-items: center;
            margin-top: 8px;
        }

        .identity-photo-state {
            display: inline-flex;
            align-items: center;
            min-height: 25px;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: .68rem;
            font-weight: 850;
        }

        .identity-photo-state.is-ready {
            background: #dcfce7;
            color: #166534;
        }

        .identity-photo-state.is-missing {
            background: #fff3bf;
            color: #725b00;
        }

        .identity-status-muted {
            color: #64748b;
            font-size: .7rem;
            font-weight: 750;
        }

        .identity-photo-row .foto-uploader-compact {
            justify-self: end;
        }

        @media (max-width: 1320px) {
            .identity-filter-grid,
            .identity-filter-grid.employee {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .identity-filter-grid .field:last-of-type,
            .identity-filter-grid .actions {
                grid-column: 1 / -1;
            }

            .identity-photo-row {
                grid-template-columns: 48px minmax(0, 1fr);
            }

            .identity-photo-row .foto-uploader-compact {
                grid-column: 2;
                justify-self: start;
            }
        }

        @media (max-width: 700px) {
            .identity-tab-list {
                width: 100%;
            }

            .identity-filter-grid,
            .identity-filter-grid.employee {
                grid-template-columns: 1fr;
            }

            .identity-filter-grid .field:last-of-type,
            .identity-filter-grid .actions {
                grid-column: auto;
            }

            .identity-filter-grid .actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .identity-stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
            }

            .identity-stat-card {
                min-height: 88px;
                padding: 12px;
            }

            .identity-stat-card .stat-label {
                font-size: .68rem;
                line-height: 1.3;
            }

            .identity-stat-card .stat-value {
                font-size: 1.45rem;
            }

            .identity-list-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .identity-photo-row {
                grid-template-columns: 42px minmax(0, 1fr);
                gap: 12px;
                padding: 14px;
            }

            .identity-order {
                width: 38px;
                height: 38px;
            }

            .identity-photo-row .foto-uploader-compact {
                grid-column: 1 / -1;
                width: 100%;
                justify-self: stretch;
            }
        }
    </style>

    <div class="identity-photo-shell">
        <div class="page-header">
            <div>
                <p class="eyebrow">Data Sekolah</p>
                <h1 class="page-title">Foto Identitas</h1>
            </div>

            <div class="actions">
                @if ($tab === 'siswa')
                    @izin('kartu_pelajar.lihat', 'kartu_pelajar.cetak')
                        <a href="{{ route('kartu-pelajar.index', array_filter([
                            'tahun_pelajaran_id' => $tahunPelajaranDipilih?->id,
                            'kelas_id' => $kelasDipilih?->id,
                        ])) }}" class="button button-muted">Kartu pelajar</a>
                    @endizin
                @else
                    @izin('pegawai.lihat', 'pegawai.kelola')
                        <a href="{{ route('kartu-pegawai.index') }}" class="button button-muted">Kartu pegawai</a>
                    @endizin
                @endif
            </div>
        </div>

        @if ($bolehKelolaSiswa && $bolehKelolaPegawai)
            <nav class="identity-tab-list" aria-label="Jenis foto identitas">
                <a href="{{ route('foto-identitas.index', ['tab' => 'siswa']) }}" class="identity-tab {{ $tab === 'siswa' ? 'is-active' : '' }}">
                    Siswa
                </a>
                <a href="{{ route('foto-identitas.index', ['tab' => 'pegawai']) }}" class="identity-tab {{ $tab === 'pegawai' ? 'is-active' : '' }}">
                    Pegawai
                </a>
            </nav>
        @endif

        @if ($tab === 'siswa')
            <form action="{{ route('foto-identitas.index') }}" method="GET" class="panel panel-pad">
                <input type="hidden" name="tab" value="siswa">
                <div class="identity-filter-grid">
                    <div class="field">
                        <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                        <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                            @forelse ($daftarTahunPelajaran as $tahunPelajaran)
                                <option value="{{ $tahunPelajaran->id }}" @selected($tahunPelajaranDipilih?->id === $tahunPelajaran->id)>
                                    {{ $tahunPelajaran->nama }}{{ $tahunPelajaran->aktif ? ' - aktif' : '' }}
                                </option>
                            @empty
                                <option value="">Belum ada tahun pelajaran</option>
                            @endforelse
                        </select>
                    </div>

                    <div class="field">
                        <label for="kelas_id">Kelas</label>
                        <select id="kelas_id" name="kelas_id" class="select">
                            @forelse ($daftarKelas as $kelas)
                                <option value="{{ $kelas->id }}" @selected($kelasDipilih?->id === $kelas->id)>{{ $kelas->nama }}</option>
                            @empty
                                <option value="">Belum ada kelas</option>
                            @endforelse
                        </select>
                    </div>

                    <div class="field">
                        <label for="status_foto_siswa">Status foto</label>
                        <select id="status_foto_siswa" name="status_foto" class="select">
                            <option value="semua" @selected($statusFoto === 'semua')>Semua</option>
                            <option value="belum" @selected($statusFoto === 'belum')>Belum ada foto</option>
                            <option value="sudah" @selected($statusFoto === 'sudah')>Sudah ada foto</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="kata_kunci_siswa">Cari siswa</label>
                        <input id="kata_kunci_siswa" name="kata_kunci" type="search" class="input" value="{{ $kataKunci }}" placeholder="Nama, NIS, atau NISN">
                    </div>

                    <div class="actions">
                        <button type="submit" class="button button-primary">Terapkan</button>
                        <a href="{{ route('foto-identitas.index', ['tab' => 'siswa']) }}" class="button button-muted">Reset</a>
                    </div>
                </div>
            </form>
        @else
            <form action="{{ route('foto-identitas.index') }}" method="GET" class="panel panel-pad">
                <input type="hidden" name="tab" value="pegawai">
                <div class="identity-filter-grid employee">
                    <div class="field">
                        <label for="jenis_pegawai">Jenis pegawai</label>
                        <select id="jenis_pegawai" name="jenis_pegawai" class="select">
                            <option value="">Semua jenis</option>
                            @foreach ($daftarJenisPegawai as $jenis)
                                <option value="{{ $jenis }}" @selected($jenisPegawai === $jenis)>{{ $jenis }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="status_pegawai">Status pegawai</label>
                        <select id="status_pegawai" name="status_pegawai" class="select">
                            <option value="aktif" @selected($statusPegawai === 'aktif')>Aktif</option>
                            <option value="nonaktif" @selected($statusPegawai === 'nonaktif')>Nonaktif</option>
                            <option value="semua" @selected($statusPegawai === 'semua')>Semua</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="status_foto_pegawai">Status foto</label>
                        <select id="status_foto_pegawai" name="status_foto" class="select">
                            <option value="semua" @selected($statusFoto === 'semua')>Semua</option>
                            <option value="belum" @selected($statusFoto === 'belum')>Belum ada foto</option>
                            <option value="sudah" @selected($statusFoto === 'sudah')>Sudah ada foto</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="kata_kunci_pegawai">Cari pegawai</label>
                        <input id="kata_kunci_pegawai" name="kata_kunci" type="search" class="input" value="{{ $kataKunci }}" placeholder="Nama, NIP, atau NUPTK">
                    </div>

                    <div class="actions">
                        <button type="submit" class="button button-primary">Terapkan</button>
                        <a href="{{ route('foto-identitas.index', ['tab' => 'pegawai']) }}" class="button button-muted">Reset</a>
                    </div>
                </div>
            </form>
        @endif

        <section class="stats-grid identity-stats" aria-label="Ringkasan foto identitas">
            <article class="stat-card identity-stat-card">
                <p class="stat-label">{{ $tab === 'siswa' ? 'Anggota kelas' : 'Pegawai' }}</p>
                <p class="stat-value" data-photo-stat="total">{{ $ringkasanFoto['total'] }}</p>
            </article>
            <article class="stat-card identity-stat-card is-complete">
                <p class="stat-label">Sudah ada foto</p>
                <p class="stat-value" data-photo-stat="sudah">{{ $ringkasanFoto['sudah'] }}</p>
            </article>
            <article class="stat-card identity-stat-card is-missing">
                <p class="stat-label">Belum ada foto</p>
                <p class="stat-value" data-photo-stat="belum">{{ $ringkasanFoto['belum'] }}</p>
            </article>
        </section>

        <div class="identity-list-heading">
            <div>
                <h2>{{ $tab === 'siswa' ? 'Anggota '.($kelasDipilih?->nama ?? 'kelas') : 'Daftar pegawai' }}</h2>
                <p>{{ $tab === 'siswa' ? $daftarAnggota->count() : $daftarPegawai->count() }} nama ditampilkan</p>
            </div>
            <span class="identity-auto-badge">Penyimpanan otomatis aktif</span>
        </div>

        @php
            $daftarFoto = $tab === 'siswa' ? $daftarAnggota : $daftarPegawai;
        @endphp

        @if ($daftarFoto->isEmpty())
            <section class="panel empty-state">
                <h2 class="panel-title">Tidak ada data yang ditampilkan</h2>
                <p class="help-text">Periksa kembali kelas, status foto, atau kata kunci.</p>
            </section>
        @else
            <section class="identity-photo-list" data-identity-photo-list>
                @foreach ($daftarFoto as $urutan => $item)
                    @php
                        $orang = $tab === 'siswa' ? $item->siswa : $item;
                        $nama = $orang->nama_lengkap;
                        $punyaFoto = filled($orang->foto);
                        $bagianNama = collect(preg_split('/\s+/', trim($nama)) ?: [])->filter()->take(2);
                        $inisial = $bagianNama->map(fn ($bagian) => mb_strtoupper(mb_substr($bagian, 0, 1)))->implode('') ?: 'FT';
                        $nomor = $tab === 'siswa' ? ($item->nomor_absen ?: $urutan + 1) : $urutan + 1;
                        $urlFoto = $punyaFoto ? asset('storage/'.$orang->foto) : null;
                        $urlUnggah = $tab === 'siswa'
                            ? route('siswa.foto.update', $orang)
                            : route('pegawai.foto.update', $orang);
                    @endphp

                    <article class="identity-photo-row" data-identity-photo-row data-photo-present="{{ $punyaFoto ? '1' : '0' }}">
                        <div class="identity-order">{{ $nomor }}</div>

                        <div class="identity-person">
                            <h3>{{ $nama }}</h3>
                            <div class="identity-person-meta">
                                @if ($tab === 'siswa')
                                    <span>NISN: {{ $orang->nisn ?: '-' }}</span>
                                    <span>NIS: {{ $orang->nis ?: '-' }}</span>
                                @else
                                    <span>NIP: {{ $orang->nip ?: '-' }}</span>
                                    <span>{{ $orang->jenis_pegawai ?: 'Jenis pegawai belum diisi' }}</span>
                                @endif
                            </div>
                            <div class="identity-person-footer">
                                <span class="identity-photo-state {{ $punyaFoto ? 'is-ready' : 'is-missing' }}" data-photo-state>
                                    {{ $punyaFoto ? 'Sudah ada foto' : 'Belum ada foto' }}
                                </span>
                                @if ($tab === 'pegawai' && filled($orang->jabatan_utama))
                                    <span class="identity-status-muted">{{ $orang->jabatan_utama }}</span>
                                @endif
                            </div>
                        </div>

                        <x-input-foto-profil
                            :id="'foto-identitas-'.$tab.'-'.$orang->id"
                            :name="'foto-identitas-'.$tab.'-'.$orang->id"
                            :label="$punyaFoto ? 'Ganti foto' : 'Unggah foto'"
                            :foto-url="$urlFoto"
                            :inisial="$inisial"
                            :alt="'Foto '.$nama"
                            :upload-url="$urlUnggah"
                            variant="compact"
                        />
                    </article>
                @endforeach
            </section>
        @endif
    </div>

    @push('scripts')
        <script>
            (() => {
                document.addEventListener('nusa:foto-tersimpan', (event) => {
                    const row = event.target.closest('[data-identity-photo-row]');

                    if (! row) {
                        return;
                    }

                    if (row.dataset.photoPresent !== '1') {
                        row.dataset.photoPresent = '1';
                        const sudah = document.querySelector('[data-photo-stat="sudah"]');
                        const belum = document.querySelector('[data-photo-stat="belum"]');

                        if (sudah) {
                            sudah.textContent = String(Number(sudah.textContent) + 1);
                        }
                        if (belum) {
                            belum.textContent = String(Math.max(0, Number(belum.textContent) - 1));
                        }
                    }

                    const status = row.querySelector('[data-photo-state]');
                    if (status) {
                        status.textContent = 'Sudah ada foto';
                        status.classList.remove('is-missing');
                        status.classList.add('is-ready');
                    }

                    const label = row.querySelector('.foto-uploader-compact .form-label');
                    if (label) {
                        label.textContent = 'Ganti foto';
                    }

                    document.querySelectorAll('[data-identity-photo-row].is-next-target')
                        .forEach((item) => item.classList.remove('is-next-target'));
                    const rows = Array.from(document.querySelectorAll('[data-identity-photo-row]'));
                    const index = rows.indexOf(row);
                    const berikutnya = rows.slice(index + 1).find((item) => item.dataset.photoPresent !== '1')
                        || rows.find((item) => item.dataset.photoPresent !== '1');

                    if (berikutnya) {
                        berikutnya.classList.add('is-next-target');
                        berikutnya.querySelector('input[type="file"]')?.focus({ preventScroll: true });
                    }
                });
            })();
        </script>
    @endpush
@endsection
