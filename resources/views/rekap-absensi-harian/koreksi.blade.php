@extends('layouts.app')

@section('title', 'Koreksi Presensi - NUSA')

@section('content')
    @php
        $tanggalLabel = \Carbon\Carbon::parse($tanggal)->locale('id')->translatedFormat('d F Y');
        $formatJam = fn (?string $jam) => $jam ? substr($jam, 0, 5) : '';
        $inisial = collect(explode(' ', trim($anggotaKelas->siswa?->nama_lengkap ?? 'Siswa')))
            ->filter()
            ->take(2)
            ->map(fn ($kata) => mb_substr($kata, 0, 1))
            ->join('');
        $statusSaatIni = old('status_kehadiran', $absensi?->status_kehadiran ?? 'alfa');
        $jadwalPulang = $pengaturanAbsensi?->jadwalPulangUntuk($anggotaKelas->siswa?->jenis_kelamin);
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Presensi</p>
            <h1 class="page-title">Koreksi presensi</h1>
        </div>

        <div class="actions">
            <a href="{{ route('rekap-absensi-harian.index', [
                'tanggal' => $tanggal,
                'tahun_pelajaran_id' => $anggotaKelas->tahun_pelajaran_id,
                'kelas_id' => $anggotaKelas->kelas_id,
            ]) }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            Periksa kembali data koreksi.
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($koreksiHariIniTerbatas ?? false)
        <div class="alert">Koreksi Guru PL hanya berlaku hari ini. Catatan wajib diisi agar riwayat perubahan dapat diperiksa kembali.</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">{{ mb_strtoupper($inisial ?: 'S') }}</div>
                <h2>{{ $anggotaKelas->siswa?->nama_lengkap ?: '-' }}</h2>
                <p>{{ $anggotaKelas->kelas?->nama ?: '-' }} - {{ $tanggalLabel }}</p>

                <div style="margin-top: 16px;">
                    <span class="badge badge-active">{{ $anggotaKelas->tahunPelajaran?->nama ?: '-' }}</span>
                </div>
            </div>

            <dl class="quick-facts" style="margin-top: 20px;">
                <div>
                    <dt>NIS</dt>
                    <dd>{{ $anggotaKelas->siswa?->nis ?: '-' }}</dd>
                </div>
                <div>
                    <dt>NISN</dt>
                    <dd>{{ $anggotaKelas->siswa?->nisn ?: '-' }}</dd>
                </div>
                <div>
                    <dt>No. absen</dt>
                    <dd>{{ $anggotaKelas->nomor_absen ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Sumber</dt>
                    <dd>{{ $absensi ? ucfirst($absensi->sumber) : 'Belum ada catatan' }}</dd>
                </div>
            </dl>

            @if ($pengaturanAbsensi)
                <div class="alert" style="margin: 20px 0 0;">
                    Masuk {{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_masuk) }},
                    pulang {{ $pengaturanAbsensi->formatJam($jadwalPulang['jam_pulang']) }}.
                </div>
            @else
                <div class="alert alert-danger" style="margin: 20px 0 0;">
                    Jadwal presensi tanggal ini belum aktif. Jam tetap bisa disimpan, tetapi status terlambat/pulang cepat tidak dihitung.
                </div>
            @endif
        </aside>

        <section class="panel panel-pad">
            <h2 class="panel-title">Data koreksi</h2>

            <form action="{{ route('rekap-absensi-harian.koreksi.update', $anggotaKelas) }}" method="POST" style="margin-top: 16px;">
                @csrf
                @method('PUT')
                <input type="hidden" name="tanggal" value="{{ $tanggal }}">

                <div class="form-grid">
                    <div class="field span-2">
                        <label for="status_kehadiran">Status kehadiran</label>
                        <select id="status_kehadiran" name="status_kehadiran" class="select @error('status_kehadiran') is-invalid @enderror" required>
                            <option value="hadir" @selected($statusSaatIni === 'hadir')>Hadir</option>
                            <option value="izin" @selected($statusSaatIni === 'izin')>Izin</option>
                            <option value="sakit" @selected($statusSaatIni === 'sakit')>Sakit</option>
                            <option value="alfa" @selected($statusSaatIni === 'alfa')>Alfa</option>
                        </select>
                        @error('status_kehadiran')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="jam_masuk">Jam masuk</label>
                        <input id="jam_masuk" type="time" name="jam_masuk" value="{{ old('jam_masuk', $formatJam($absensi?->jam_masuk)) }}" class="input @error('jam_masuk') is-invalid @enderror">
                        <p class="help-text">Wajib diisi jika status hadir.</p>
                        @error('jam_masuk')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="jam_pulang">Jam pulang</label>
                        <input id="jam_pulang" type="time" name="jam_pulang" value="{{ old('jam_pulang', $formatJam($absensi?->jam_pulang)) }}" class="input @error('jam_pulang') is-invalid @enderror">
                        <p class="help-text">Boleh dikosongkan jika siswa belum scan pulang.</p>
                        @error('jam_pulang')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field span-2">
                        <label for="catatan">Catatan{{ ($koreksiHariIniTerbatas ?? false) ? ' *' : '' }}</label>
                        <textarea id="catatan" name="catatan" class="textarea @error('catatan') is-invalid @enderror" placeholder="Contoh: Izin dokter, lupa membawa kartu, atau koreksi guru piket." @required($koreksiHariIniTerbatas ?? false)>{{ old('catatan', $absensi?->catatan) }}</textarea>
                        @error('catatan')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 20px;">
                    <a href="{{ route('rekap-absensi-harian.index', [
                        'tanggal' => $tanggal,
                        'tahun_pelajaran_id' => $anggotaKelas->tahun_pelajaran_id,
                        'kelas_id' => $anggotaKelas->kelas_id,
                    ]) }}" class="button button-muted">Batal</a>
                    <button type="submit" class="button button-primary">Simpan koreksi</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        const statusInput = document.getElementById('status_kehadiran');
        const jamMasukInput = document.getElementById('jam_masuk');
        const jamPulangInput = document.getElementById('jam_pulang');

        function sinkronkanJam() {
            const hadir = statusInput.value === 'hadir';
            jamMasukInput.disabled = ! hadir;
            jamPulangInput.disabled = ! hadir;

            if (! hadir) {
                jamMasukInput.value = '';
                jamPulangInput.value = '';
            }
        }

        statusInput.addEventListener('change', sinkronkanJam);
        sinkronkanJam();
    </script>
@endsection
