<details class="attendance-form">
    <summary>{{ $absensi ? 'Ubah catatan' : 'Catat hadir/sakit/izin' }}</summary>
    <form action="{{ route('piket-kehadiran-siswa.update', $anggota) }}" method="POST" class="attendance-form-fields" data-hadir-manual>
        @csrf @method('PUT')
        <select name="status_kehadiran" class="select" required aria-label="Status kehadiran">
            <option value="hadir" @selected(in_array($statusHariIni, ['hadir', 'belum_scan']))>Hadir</option>
            <option value="sakit" @selected($statusHariIni === 'sakit')>Sakit</option>
            <option value="izin" @selected($statusHariIni === 'izin')>Izin</option>
        </select>
        <div data-alasan-manual>
            <label>Alasan hadir tanpa scan kartu</label>
            <select name="alasan_hadir_manual" class="select" aria-label="Alasan hadir tanpa scan kartu">
                @foreach (\App\Models\AbsensiSiswa::ALASAN_HADIR_MANUAL as $kode => $label)
                    <option value="{{ $kode }}" @selected(\App\Models\AbsensiSiswa::normalisasiAlasanHadirManual($absensi?->alasan_hadir_manual) === $kode)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="help-text">Hadir manual dikenai minimal {{ $aturanHadirManual['menit_minimal'] }} menit keterlambatan dan {{ $aturanHadirManual['poin'] }} poin sesuai aturan sekolah. Jika lebih terlambat, menit sebenarnya tetap dihitung. Poin tidak diterapkan pada hari dengan pengecualian presensi. Alasannya diinformasikan kepada orang tua. Segera hubungi admin untuk mendapatkan atau mengganti kartu pelajar.</p>
        </div>
        <textarea name="catatan" class="textarea" required placeholder="Keterangan petugas">{{ $absensi?->catatan }}</textarea>
        <button class="button button-primary button-sm" type="submit">Simpan</button>
    </form>
</details>
