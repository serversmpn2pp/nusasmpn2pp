<details class="attendance-form">
    <summary>{{ $rincianPiket['hanya_scan_pulang'] ? 'Catat gangguan scan masuk' : ($absensi ? 'Ubah catatan manual' : 'Catat hadir/sakit/izin') }}</summary>
    <form action="{{ route('piket-kehadiran-siswa.update', $anggota) }}" method="POST" class="attendance-form-fields" data-hadir-manual>
        @csrf @method('PUT')
        <select name="status_kehadiran" class="select" required aria-label="Status kehadiran">
            <option value="hadir" @selected(in_array($statusHariIni, ['hadir', 'belum_scan']))>Hadir</option>
            @unless ($absensi?->jam_pulang)
                <option value="sakit" @selected($statusHariIni === 'sakit')>Sakit</option>
                <option value="izin" @selected($statusHariIni === 'izin')>Izin</option>
            @endunless
        </select>
        <div data-alasan-manual>
            <label>Alasan hadir tanpa scan kartu</label>
            <select name="alasan_hadir_manual" class="select" aria-label="Alasan hadir tanpa scan kartu">
                @php($pilihanAlasanPiket = $rincianPiket['hanya_scan_pulang'] ? 'scan_bermasalah' : old('alasan_hadir_manual', $absensi?->alasan_hadir_manual))
                @unless ($rincianPiket['hanya_scan_pulang'])<option value="">Pilih alasan</option>@endunless
                @foreach (\App\Models\AbsensiSiswa::ALASAN_HADIR_MANUAL as $kode => $label)
                    @if ((! $rincianPiket['hanya_scan_pulang'] || $kode === 'scan_bermasalah') && (! $absensi?->memilikiSanksiManual() || $kode !== 'scan_bermasalah'))
                        <option value="{{ $kode }}" @selected($pilihanAlasanPiket === $kode)>{{ $label }}</option>
                    @endif
                @endforeach
            </select>
            @include('rekap-absensi-harian._pilihan-status-masuk-manual')
            <div data-jam-gangguan hidden>
                <label>Jam datang <input type="time" name="jam_masuk" class="input" value="{{ $absensi?->jam_masuk ? substr($absensi->jam_masuk, 0, 5) : '' }}" disabled></label>
                <p class="help-text" data-info-verifikasi hidden>Hadir &middot; Perlu verifikasi. Poin ditahan.</p>
            </div>
            <p class="help-text" data-info-sanksi hidden>{{ $aturanHadirManual['poin'] }} poin tanpa kartu sesuai pengaturan presensi. Poin ini terpisah dari status masuk.</p>
        </div>
        <textarea name="catatan" class="textarea" required placeholder="Keterangan petugas">{{ $absensi?->catatan }}</textarea>
        <button class="button button-primary button-sm" type="submit">Simpan</button>
    </form>
</details>
