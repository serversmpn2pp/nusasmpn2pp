# Pengecualian Presensi Siswa

Menu: **Kehadiran > Pengaturan Presensi > Pengecualian presensi**. Tombol juga tersedia pada rekap harian untuk pengelola.

## Penetapan

1. Pilih tahun pelajaran, seluruh sekolah atau satu kelas, tanggal mulai/selesai, jenis kejadian, dan alasan.
2. Tekan **Pratinjau dampak**. Belum ada data yang berubah pada tahap ini.
3. Periksa daftar siswa, jumlah alfa otomatis yang dikecualikan, serta catatan manual yang tetap berlaku.
4. Centang konfirmasi dan tekan **Terapkan pengecualian**.

Contoh kasus sekolah: 14-18 September 2026, seluruh sekolah pada tahun 2026/2027, PJJ, alasan bencana asap. **Tidak ada penetapan otomatis dalam migrasi**; pengelola perlu meninjau dan menerapkan sendiri periode tersebut.

Jenis tersedia: PJJ, libur khusus, keadaan darurat, gangguan scanner. Semua jenis meniadakan inferensi alfa akibat tidak scan pada rentang dan cakupan yang ditetapkan. Ini bukan bukti kehadiran PJJ dan bukan perubahan kalender pembelajaran.

## Perhitungan

- Hanya alfa **otomatis** yang tidak dihitung. Tidak ada penghapusan/pembuatan massal baris `absensi_siswa`.
- Scan, sakit, izin, alfa manual, jam datang/pulang, dan keterlambatan asli tetap berlaku. Alfa manual yang salah perlu dikoreksi terpisah oleh petugas.
- Hari tanpa catatan pada periode pengecualian memiliki status turunan `pengecualian`, bukan hadir, alfa, atau belum scan. Rincian menjelaskan jenis dan alasannya.
- Hari aktif mingguan, jumlah hari presensi aktif, dan dasar persentase hadir pada laporan tidak diubah. PJJ masih merupakan kegiatan pembelajaran; fitur ini hanya mengecualikan kewajiban scan. Persentase scan bukan ukuran kehadiran PJJ.
- Berlaku konsisten pada rekap harian web/API, laporan/rincian web/API/Excel, pesan WA, presensi anak, serta angka dasar ketidakhadiran rapor STS.
- Pada rapor yang sudah disimpan, angka koreksi wali kelas tidak ditimpa. Jika angka dasar berubah, pemeriksaan lama tidak lagi berlaku. Gunakan angka rekap terbaru dan periksa ulang sebelum mencetak.
- Riwayat ibadah dan presensi ujian CBT tidak diubah: keduanya memiliki aturan tersendiri.

## Pengamanan

- Izin `absensi.pengaturan_kelola`, akun aktif petugas, bukan akun siswa/orang tua dan bukan wali dengan cakupan terbatas.
- Pratinjau terenkripsi, terikat akun, berlaku 15 menit, dan wajib dikonfirmasi. Data presensi/cakupan yang berubah memerlukan pratinjau baru.
- Permintaan penerapan berulang dengan pratinjau yang sama tidak menggandakan penetapan. Pratinjau bekas penetapan yang dibatalkan tidak dapat dipakai lagi.
- Rentang wajib dalam tanggal tahun pelajaran, maksimal 367 hari. Tahun pelajaran harus memiliki tanggal awal/akhir. Kelas harus berasal dari tahun yang dipilih.
- Pengecualian aktif yang tumpang tindih untuk cakupan sama ditolak. Dua kelas berbeda boleh memiliki periode sama; aturan seluruh sekolah tidak boleh bertumpuk dengan aturan kelas.
- Pembatalan memerlukan alasan dan konfirmasi. Catatan pembuat, waktu, alasan, dampak saat penetapan, serta pembatal tetap tersimpan. Tidak tersedia penghapusan permanen atau edit langsung; batalkan lalu buat penetapan pengganti.
- Penetapan/pembatalan diserialisasi dengan lock tahun pelajaran. Daftar pengecualian hanya dimuat sekali per objek aturan selama perhitungan, bukan per siswa/hari.

## Penerapan Server

Setelah pull, ada **migrasi baru**:

```powershell
php artisan migrate --force
php artisan optimize:clear
```

Migrasi menambahkan `pengecualian_presensi_siswa`; tidak mengubah presensi siswa. Tidak memerlukan npm build atau scheduler baru. Restart layanan PHP NSSM hanya jika OPcache tidak memeriksa perubahan berkas.

Backup PostgreSQL menggunakan daftar tabel dinamis sehingga tabel baru ikut dicadangkan.

## API

Respons rekap/rincian menambahkan status `pengecualian`, label/alasan penetapan, serta ringkasan `pengecualian` pada rekap harian. Filter rekap API menerima `status=pengecualian`; filter web rincian menerima `status_rincian=pengecualian`. Android perlu menggunakan label dari API dan menangani status tambahan ini. Penetapan/pembatalan dilakukan di web, belum disediakan endpoint manajemen mobile.

## Verifikasi

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/PengecualianPresensiSiswaTest.php tests/Feature/AlfaOtomatisSiswaTest.php tests/Feature/RincianPresensiSiswaTest.php tests/Feature/Api/RekapPresensiSiswaApiTest.php tests/Feature/PresensiAnakTest.php tests/Feature/RaporStsTest.php
```

Fixture browser dapat dibuat lewat `NUSA_CAPTURE_PENGECUALIAN=1` saat menjalankan test fitur, lalu jalankan `tests/Browser/pengecualian-presensi.mjs`. Fixture memakai database SQLite uji, bukan data sekolah.
