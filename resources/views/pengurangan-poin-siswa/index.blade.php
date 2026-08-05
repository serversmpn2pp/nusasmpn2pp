@extends('layouts.app')

@section('title', 'Pengurangan Poin Siswa - NUSA')

@section('content')
    <style>
        .reward-layout {
            display: grid;
            grid-template-columns: minmax(300px, .8fr) minmax(0, 1.6fr);
            gap: 20px;
        }

        .reward-context {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .reward-filter-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr)) auto;
            align-items: end;
            gap: 12px;
        }

        @media (max-width: 950px) {
            .reward-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 680px) {
            .reward-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Pengurangan Poin/Reward</h1>
            <p class="page-subtitle">Pengurangan diterapkan setelah disetujui dan tidak pernah membuat saldo poin menjadi negatif.</p>
            <div class="reward-context">
                <span class="badge {{ $tahunPelajaranAktif ? 'badge-active' : 'badge-warning' }}">
                    {{ $tahunPelajaranAktif ? 'Tahun aktif '.$tahunPelajaranAktif->nama : 'Tahun pelajaran aktif belum tersedia' }}
                </span>
                @if($tahunPelajaranAktif)
                    <span class="person-meta">{{ $daftarSiswa->count() }} siswa dengan saldo poin</span>
                @endif
            </div>
        </div>
        <a href="{{ route('rekap-poin-siswa.index') }}" class="button button-muted">Rekap Poin</a>
    </div>

    @if(session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @unless($tahunPelajaranAktif)
        <div class="alert alert-warning">Aktifkan tahun pelajaran terlebih dahulu sebelum membuat pengajuan reward.</div>
    @endunless

    <form method="GET" class="panel panel-pad" style="margin-bottom:20px">
        <div class="reward-filter-grid">
            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select">
                    <option value="">Semua kelas</option>
                    @foreach($daftarKelas as $kelas)
                        <option value="{{ $kelas->id }}" @selected((string)$kelasId === (string)$kelas->id)>{{ $kelas->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="status">Status pengajuan</label>
                <select id="status" name="status" class="select">
                    <option value="semua" @selected($status === 'semua')>Semua status</option>
                    @foreach(\App\Models\PenguranganPoinSiswa::DAFTAR_STATUS as $kode => $label)
                        <option value="{{ $kode }}" @selected($status === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <a href="{{ route('pengurangan-poin-siswa.index') }}" class="button button-muted">Reset</a>
                <button class="button button-dark">Terapkan</button>
            </div>
        </div>
    </form>

    <div class="reward-layout">
        <form method="POST" action="{{ route('pengurangan-poin-siswa.store') }}" enctype="multipart/form-data" class="panel panel-pad">
            @csrf
            <h2 class="panel-title">Ajukan reward</h2>
            <p class="help-text">Pilihan hanya menampilkan siswa yang masih memiliki saldo poin pada tahun pelajaran aktif.</p>

            <div class="field" style="margin-top:14px">
                <label for="siswa">Siswa</label>
                <select id="siswa" name="siswa_id" class="select @error('siswa_id') input-error @enderror" required @disabled(!$tahunPelajaranAktif || $daftarSiswa->isEmpty())>
                    <option value="">{{ $daftarSiswa->isEmpty() ? 'Tidak ada siswa dengan saldo poin' : 'Pilih siswa' }}</option>
                    @foreach($daftarSiswa as $siswa)
                        @php($anggotaAktif = $siswa->anggotaKelas->first())
                        <option value="{{ $siswa->id }}" @selected((string)old('siswa_id') === (string)$siswa->id)>
                            {{ $siswa->nama_lengkap }} · {{ $anggotaAktif?->kelas?->nama ?: 'Belum ditempatkan' }} · {{ (int)$siswa->saldo_poin }} poin
                        </option>
                    @endforeach
                </select>
                @error('siswa_id')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="field" style="margin-top:12px">
                <label for="tanggal">Tanggal kegiatan</label>
                <input id="tanggal" name="tanggal_kegiatan" type="date" value="{{ old('tanggal_kegiatan', now()->toDateString()) }}" class="input @error('tanggal_kegiatan') input-error @enderror" required>
                @error('tanggal_kegiatan')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="field" style="margin-top:12px">
                <label for="jenis">Kegiatan positif</label>
                <select id="jenis" name="jenis_kegiatan" class="select @error('jenis_kegiatan') input-error @enderror" required>
                    @foreach([
                        'Juara lomba tingkat sekolah',
                        'Juara tingkat kota/kabupaten',
                        'Juara tingkat provinsi',
                        'Aktif organisasi',
                        "Hafalan Al-Qur'an/kegiatan keagamaan",
                        'Teladan disiplin',
                    ] as $kegiatan)
                        <option value="{{ $kegiatan }}" @selected(old('jenis_kegiatan') === $kegiatan)>{{ $kegiatan }}</option>
                    @endforeach
                </select>
                @error('jenis_kegiatan')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="field" style="margin-top:12px">
                <label for="poin">Pengurangan</label>
                <select id="poin" name="poin_pengurangan" class="select @error('poin_pengurangan') input-error @enderror">
                    @foreach([10, 15, 20, 30] as $poin)
                        <option value="{{ $poin }}" @selected((string)old('poin_pengurangan', 10) === (string)$poin)>{{ $poin }} poin</option>
                    @endforeach
                </select>
                @error('poin_pengurangan')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="field" style="margin-top:12px">
                <label for="deskripsi">Keterangan</label>
                <textarea id="deskripsi" name="deskripsi" class="textarea @error('deskripsi') input-error @enderror">{{ old('deskripsi') }}</textarea>
                @error('deskripsi')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="field" style="margin-top:12px">
                <label for="bukti">Bukti</label>
                <input id="bukti" name="bukti" type="file" accept=".pdf,.jpg,.jpeg,.png" class="input @error('bukti') input-error @enderror">
                <p class="help-text">PDF/JPG/PNG maksimal 4 MB.</p>
                @error('bukti')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <button class="button button-primary button-full" style="margin-top:14px" @disabled(!$tahunPelajaranAktif || $daftarSiswa->isEmpty())>Ajukan</button>
        </form>

        <div class="section-stack">
            <section class="panel">
                <div class="mobile-list">
                    @forelse($pengurangan as $item)
                        @php($anggotaAktif = $item->siswa?->anggotaKelas?->first())
                        <article class="mobile-card">
                            <div class="mobile-card-head">
                                <div>
                                    <p class="person-name">{{ $item->siswa?->nama_lengkap }}</p>
                                    <p class="person-meta">
                                        {{ $anggotaAktif?->kelas?->nama ?: 'Belum ditempatkan' }} ·
                                        {{ $item->tanggal_kegiatan?->format('d/m/Y') }} ·
                                        {{ $item->jenis_kegiatan }}
                                    </p>
                                </div>
                                <span class="badge {{ $item->status === 'disetujui' ? 'badge-active' : ($item->status === 'ditolak' ? 'badge-inactive' : 'badge-warning') }}">-{{ $item->poin_pengurangan }}</span>
                            </div>
                            @if($item->deskripsi)<p>{{ $item->deskripsi }}</p>@endif
                            <p class="person-meta">Status: {{ \App\Models\PenguranganPoinSiswa::DAFTAR_STATUS[$item->status] }}</p>
                            @if($item->bukti)
                                <a href="{{ asset('storage/'.$item->bukti) }}" target="_blank" class="button button-muted button-sm">Lihat bukti</a>
                            @endif
                            @if($item->status === 'diajukan' && (auth()->user()?->administrator() || auth()->user()?->memilikiIzin('poin_siswa.putus_konflik')))
                                <form method="POST" action="{{ route('pengurangan-poin-siswa.putuskan', $item) }}" style="margin-top:12px">
                                    @csrf
                                    @method('PATCH')
                                    <textarea name="catatan_keputusan" class="textarea" placeholder="Catatan keputusan"></textarea>
                                    <div class="actions" style="margin-top:8px">
                                        <button name="keputusan" value="ditolak" class="button button-danger button-sm">Tolak</button>
                                        <button name="keputusan" value="disetujui" class="button button-primary button-sm">Setujui</button>
                                    </div>
                                </form>
                            @endif
                        </article>
                    @empty
                        <div class="empty-state">Belum ada pengajuan pengurangan poin untuk filter ini.</div>
                    @endforelse
                </div>
            </section>

            {{ $pengurangan->links() }}
        </div>
    </div>
@endsection
