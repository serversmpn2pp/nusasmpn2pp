@extends('layouts.app')
@section('admin-module', 'kehadiran')

@section('title', 'Koreksi Presensi - NUSA')

@section('content')
    @php
        $tanggalLabel = \Carbon\Carbon::parse($tanggal)->locale('id')->translatedFormat('d F Y');
        $formatJam = fn (?string $jam) => $jam ? substr($jam, 0, 5) : '-';
        $inisial = collect(explode(' ', trim($anggotaKelas->siswa?->nama_lengkap ?? 'Siswa')))
            ->filter()
            ->take(2)
            ->map(fn ($kata) => mb_substr($kata, 0, 1))
            ->join('');
        $statusSaatIni = old('status_kehadiran', $absensi?->status_kehadiran ?? 'alfa');
        $jadwalPulang = $pengaturanAbsensi?->jadwalPulangUntuk($anggotaKelas->siswa?->jenis_kelamin);
        $verifikasi = $absensi?->menungguVerifikasiScan() ?? false;
        $gangguanTerverifikasi = $absensi?->alasan_hadir_manual === \App\Models\AbsensiSiswa::GANGGUAN_SCAN_TERVERIFIKASI;
        $tanpaScan = ! $absensi?->penugasan_presensi_id && ! $gangguanTerverifikasi && (! $absensi || $absensi->memilikiSanksiManual()
            || (! $scanMasukBukti && ($absensi->sumber !== 'scan' || ! $absensi->jam_masuk)));
        $hanyaGangguan = ! $tanpaScan && $dapatMemverifikasi && ! $gangguanTerverifikasi && ! $absensi?->penugasan_presensi_id
            && $sumberJam['sumber_masuk'] !== 'scan';
        $aturanHadirManual = app(\App\Services\Pembinaan\PengaturanPoinKeterlambatanService::class)->aturanHadirManual($anggotaKelas->tahun_pelajaran_id);
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Presensi</p>
            <h1 class="page-title">{{ $verifikasi ? 'Verifikasi scan masuk' : 'Koreksi presensi' }}</h1>
        </div>

        <div class="actions">
            @if ($verifikasi)
                <a href="{{ route('rekap-absensi-harian.rincian', ['anggotaKelas' => $anggotaKelas, 'tanggal' => $tanggal]) }}" class="button button-muted">
                    <i data-lucide="history" aria-hidden="true"></i> Rincian dan riwayat
                </a>
            @endif
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
                @unless ($verifikasi)
                <div class="avatar avatar-lg">{{ mb_strtoupper($inisial ?: 'S') }}</div>
                @endunless
                <h2>{{ $anggotaKelas->siswa?->nama_lengkap ?: '-' }}</h2>
                <p>{{ $anggotaKelas->kelas?->nama ?: '-' }} - {{ $tanggalLabel }}</p>

                <div style="margin-top: 16px;">
                    <span class="badge badge-active">{{ $anggotaKelas->tahunPelajaran?->nama ?: '-' }}</span>
                </div>
            </div>

            <dl class="quick-facts" style="margin-top: 20px;">
                @unless ($verifikasi)
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
                @endunless
                @foreach (['masuk' => 'Masuk', 'pulang' => 'Pulang'] as $jenis => $label)
                    <div>
                        <dt>{{ $label }}</dt>
                        <dd>{{ $formatJam($absensi?->{'jam_'.$jenis}) }}@if ($sumber = $sumberJam['sumber_'.$jenis.'_label']) &middot; {{ $sumber }}@endif</dd>
                    </div>
                @endforeach
            </dl>

            @if ($pengaturanAbsensi)
                <div class="alert" style="margin: 20px 0 0;">
                    Masuk {{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_masuk) }},
                    pulang {{ $pengaturanAbsensi->formatJam($jadwalPulang['jam_pulang']) }}.
                </div>
            @else
                <div class="alert alert-danger" style="margin: 20px 0 0;">
                    Jadwal presensi tanggal ini belum aktif. Poin presensi tidak diterapkan pada hari tanpa jadwal aktif.
                </div>
            @endif
        </aside>

        <section class="panel panel-pad feature-section">
            <h2 class="panel-title">{{ $verifikasi ? 'Hasil pemeriksaan' : 'Data koreksi' }}</h2>
            @if ($verifikasi)
                <p class="help-text">Catatan awal: {{ $absensi->catatan ?: '-' }}</p>
            @endif
            @if ($absensi?->penugasan_presensi_id)<p class="help-text">{{ $absensi->keteranganPenugasan() }} Jam masuk hanya diisi bila benar-benar tercatat. Batalkan penugasan terlebih dahulu jika status perlu diubah menjadi Sakit/Izin/Alfa.</p>@endif

            <form action="{{ route('rekap-absensi-harian.koreksi.update', $anggotaKelas) }}" method="POST" style="margin-top: 16px;">
                @csrf
                @method('PUT')
                <input type="hidden" name="tanggal" value="{{ $tanggal }}">

                <div class="form-grid">
                    @if ($verifikasi)
                        <input type="hidden" id="status_kehadiran" name="status_kehadiran" value="hadir">
                        <div class="field span-2">
                            <p><span class="badge badge-active">Hadir</span> <span class="badge badge-warning">Perlu verifikasi</span></p>
                            <label for="hasil_verifikasi_scan">Hasil verifikasi</label>
                            <select id="hasil_verifikasi_scan" name="hasil_verifikasi_scan" class="select" required>
                                <option value="">Pilih hasil verifikasi</option>
                                @foreach (\App\Models\AbsensiSiswa::HASIL_VERIFIKASI_SCAN as $kode => $label)
                                    @if ($kode === 'gangguan_terkonfirmasi' || ($kode === 'scan_ditemukan') === (bool) $scanMasukBukti)
                                        <option value="{{ $kode }}" @selected(old('hasil_verifikasi_scan') === $kode)>{{ $label }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <p class="help-text">{{ $scanMasukBukti ? 'Log scan masuk berhasil: '.$scanMasukBukti->waktu_scan->format('H:i:s').' WIB.' : 'Tidak ditemukan log scan masuk yang berhasil.' }}</p>
                            @error('hasil_verifikasi_scan')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                    @else
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
                    @endif

                    <div class="field">
                        <label for="jam_masuk">Jam masuk</label>
                        <input id="jam_masuk" type="time" step="1" name="jam_masuk" value="{{ old('jam_masuk', $absensi?->jam_masuk ? substr($absensi->jam_masuk, 0, 8) : '') }}" class="input @error('jam_masuk') is-invalid @enderror" @required($verifikasi)>
                        <p class="help-text">{{ $absensi?->penugasan_presensi_id ? 'Isi hanya jika ada jam masuk sebenarnya.' : 'Wajib diisi jika status hadir.' }}</p>
                        @error('jam_masuk')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="jam_pulang">Jam pulang</label>
                        <input id="jam_pulang" type="time" step="1" name="jam_pulang" value="{{ old('jam_pulang', $absensi?->jam_pulang ? substr($absensi->jam_pulang, 0, 8) : '') }}" class="input @error('jam_pulang') is-invalid @enderror" @readonly($verifikasi)>
                        @unless ($verifikasi)<p class="help-text">Boleh dikosongkan jika siswa belum scan pulang.</p>@endunless
                        @error('jam_pulang')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    @if (($tanpaScan || $hanyaGangguan) && ! $verifikasi)
                        <div class="field span-2" id="alasan-hadir-manual">
                            <label for="alasan_hadir_manual">Alasan hadir tanpa scan kartu</label>
                            <select id="alasan_hadir_manual" name="alasan_hadir_manual" class="select">
                                <option value="">{{ $hanyaGangguan ? 'Tidak ada' : 'Pilih alasan' }}</option>
                                @foreach (\App\Models\AbsensiSiswa::ALASAN_HADIR_MANUAL as $kode => $label)
                                    @if (($tanpaScan && $kode !== 'scan_bermasalah') || ($kode === 'scan_bermasalah' && (! $absensi?->memilikiSanksiManual() || $dapatMemverifikasi)))
                                        <option value="{{ $kode }}" @selected(old('alasan_hadir_manual', $absensi?->alasan_hadir_manual) === $kode)>{{ $label }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @unless ($hanyaGangguan)<p class="help-text" id="info-sanksi-kartu">Kartu lupa atau hilang dikenai minimal {{ $aturanHadirManual['menit_minimal'] }} menit keterlambatan dan {{ $aturanHadirManual['poin'] }} poin sesuai pengaturan presensi.</p>@endunless
                            <p class="help-text" id="info-gangguan-scan" hidden>Hadir · Perlu verifikasi. Poin ditahan.</p>
                            @error('alasan_hadir_manual')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <div class="field span-2">
                        <label for="catatan">{{ $verifikasi ? 'Dasar verifikasi' : 'Catatan' }}</label>
                        <textarea id="catatan" name="catatan" class="textarea @error('catatan') is-invalid @enderror" @required($verifikasi || ($koreksiHariIniTerbatas ?? false))>{{ old('catatan', $verifikasi ? '' : $absensi?->catatan) }}</textarea>
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
                    <button type="submit" class="button button-primary">{{ $verifikasi ? 'Simpan hasil verifikasi' : 'Simpan koreksi' }}</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        const statusInput = document.getElementById('status_kehadiran');
        const jamMasukInput = document.getElementById('jam_masuk');
        const jamPulangInput = document.getElementById('jam_pulang');
        const pilihanAlasan = document.getElementById('alasan_hadir_manual');
        const hasilVerifikasi = document.getElementById('hasil_verifikasi_scan');
        const jamBukti = @json($scanMasukBukti?->waktu_scan->format('H:i:s'));
        let jamDiperiksa = jamMasukInput.value;

        function sinkronkanJam() {
            const hadir = statusInput.value === 'hadir';
            jamMasukInput.disabled = ! hadir;
            jamPulangInput.disabled = ! hadir;
            const alasan = document.getElementById('alasan-hadir-manual');
            if (alasan) {
                alasan.hidden = !hadir; pilihanAlasan.disabled = !hadir;
                pilihanAlasan.required = hadir && @json($tanpaScan);
                const gangguan = hadir && pilihanAlasan.value === 'scan_bermasalah';
                document.getElementById('info-gangguan-scan').hidden = !gangguan;
                const infoSanksi = document.getElementById('info-sanksi-kartu');
                if (infoSanksi) infoSanksi.hidden = gangguan || !pilihanAlasan.value;
                document.getElementById('catatan').required = gangguan || @json($koreksiHariIniTerbatas ?? false);
            }

            if (! hadir) {
                jamMasukInput.value = '';
                jamPulangInput.value = '';
            }
        }

        statusInput.addEventListener('change', sinkronkanJam);
        pilihanAlasan?.addEventListener('change', sinkronkanJam);
        function sinkronkanBukti() {
            if (!hasilVerifikasi) return;
            const pakaiScan = hasilVerifikasi.value === 'scan_ditemukan';
            jamMasukInput.readOnly = pakaiScan;
            jamMasukInput.value = pakaiScan ? jamBukti : jamDiperiksa;
        }
        hasilVerifikasi?.addEventListener('change', sinkronkanBukti);
        jamMasukInput.addEventListener('input', () => { if (!jamMasukInput.readOnly) jamDiperiksa = jamMasukInput.value; });
        sinkronkanJam();
        sinkronkanBukti();
    </script>
@endsection
