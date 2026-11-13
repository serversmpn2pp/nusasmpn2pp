<details class="attendance-form">
    <summary>{{ $absensi?->hanyaTercatatPulang() ? 'Catat gangguan scan masuk' : ($absensi ? 'Ubah catatan' : 'Catat hadir/sakit/izin') }}</summary>
    <form action="{{ route('piket-kehadiran-siswa.update', $anggota) }}" method="POST" class="attendance-form-fields" data-hadir-manual>
        @csrf @method('PUT')
        <select name="status_kehadiran" class="select" required aria-label="Status kehadiran">
            <option value="hadir" @selected(in_array($statusHariIni, ['hadir', 'belum_scan']))>Hadir</option>
            @unless ($absensi?->hanyaTercatatPulang())
                <option value="sakit" @selected($statusHariIni === 'sakit')>Sakit</option>
                <option value="izin" @selected($statusHariIni === 'izin')>Izin</option>
            @endunless
        </select>
        <div data-alasan-manual>
            <label>Alasan hadir tanpa scan kartu</label>
            <select name="alasan_hadir_manual" class="select" aria-label="Alasan hadir tanpa scan kartu">
                @php($pilihanAlasanPiket = $absensi?->hanyaTercatatPulang() ? 'scan_bermasalah' : old('alasan_hadir_manual', $absensi?->alasan_hadir_manual))
                @unless ($absensi?->hanyaTercatatPulang())<option value="">Pilih alasan</option>@endunless
                @foreach (\App\Models\AbsensiSiswa::ALASAN_HADIR_MANUAL as $kode => $label)
                    @if ((! $absensi?->hanyaTercatatPulang() || $kode === 'scan_bermasalah') && (! $absensi?->memilikiSanksiManual() || $kode !== 'scan_bermasalah'))
                        <option value="{{ $kode }}" @selected($pilihanAlasanPiket === $kode)>{{ $label }}</option>
                    @endif
                @endforeach
            </select>
            <div data-jam-gangguan hidden>
                <label>Jam datang <input type="time" name="jam_masuk" class="input" disabled></label>
                <p class="help-text">Hadir · Perlu verifikasi. Poin ditahan.</p>
            </div>
            <p class="help-text" data-info-sanksi>Kartu lupa atau hilang dikenai minimal {{ $aturanHadirManual['menit_minimal'] }} menit keterlambatan dan {{ $aturanHadirManual['poin'] }} poin sesuai pengaturan presensi.</p>
        </div>
        <textarea name="catatan" class="textarea" required placeholder="Keterangan petugas">{{ $absensi?->catatan }}</textarea>
        <button class="button button-primary button-sm" type="submit">Simpan</button>
    </form>
</details>
