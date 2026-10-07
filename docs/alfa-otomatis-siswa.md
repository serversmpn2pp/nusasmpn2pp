# Alfa otomatis siswa setelah hari berakhir

## Aturan

- Tidak ada scan maupun catatan hadir, sakit, izin, atau alfa dari petugas pada hari presensi aktif: status masih **Belum dikonfirmasi** selama hari berjalan.
- Mulai pukul 00:00 keesokan harinya, tanggal tersebut dihitung **Alfa** jika tetap tanpa konfirmasi, mengikuti zona waktu aplikasi (`Asia/Jakarta` pada server sekolah).
- Berlaku pada rekap harian web/API, pesan WhatsApp rekap, laporan presensi web/API/Excel, Presensi Anak, serta rekap dan cetak Rapor STS.
- Tanggal mendatang, hari presensi nonaktif, tanggal di luar tahun pelajaran, dan tanggal di luar masa keanggotaan siswa tidak menghasilkan alfa otomatis.
- Catatan scan atau manual tetap diutamakan. Konfirmasi/koreksi petugas yang berwenang setelahnya menggantikan alfa otomatis; riwayat koreksi tetap dicatat oleh fitur koreksi presensi.

Aturan ini **tetap berlaku ketika scanner bermasalah** jika tidak ada konfirmasi petugas atau penetapan pengecualian. Untuk PJJ, libur khusus, keadaan darurat, dan gangguan scanner, pengelola dapat menetapkan rentang tanggal melalui **Pengaturan Presensi > Pengecualian presensi**. Alfa otomatis tidak dihitung pada cakupan penetapan aktif tersebut; catatan manual tetap berlaku. Lihat [panduan pengecualian](pengecualian-presensi-siswa.md).

## Perhitungan dan koreksi

Alfa otomatis merupakan perhitungan pada rekap, bukan penyisipan massal catatan baru ke `absensi_siswa`. Tidak ada penghapusan atau penulisan ulang presensi lama, dan tidak bergantung pada scheduler/NSSM scheduler untuk berganti status.

Pada rapor STS, angka sumber alfa = alfa yang tercatat + hari aktif yang telah berakhir tanpa catatan. Angka koreksi rapor yang sudah disimpan tetap dipertahankan. Jika sumber berubah akibat aturan ini atau konfirmasi presensi baru, wali kelas wajib memeriksa dan menyimpan ulang sebelum cetak. Gunakan **Kembalikan ke rekap awal** untuk menggunakan angka otomatis terbaru, atau simpan koreksi dengan alasan yang sesuai. Koreksi pada Rapor STS hanya berlaku untuk rapor; koreksi melalui Rekap Presensi Harian mengubah catatan presensi sekolah.

Perubahan pengaturan hari aktif mempengaruhi perhitungan hari tanpa catatan dalam rentang laporan. Data keterlambatan tidak diubah oleh fitur ini.

## Pembaruan server

Fitur alfa otomatis awal tidak memerlukan migrasi, tetapi fitur pengecualian tanggal menambahkan tabel baru. Untuk pembaruan yang mencakup pengecualian, jalankan `php artisan migrate --force` lalu `php artisan optimize:clear`. Jika layanan PHP memakai OPcache tanpa pemeriksaan perubahan file, mulai ulang layanan PHP pada waktu pemeliharaan. Periksa zona waktu aplikasi dan lakukan pemeriksaan ulang rekap rapor yang sumbernya berubah.

## Verifikasi

Pengujian memakai SQLite sementara dan siswa contoh:

```powershell
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/AlfaOtomatisSiswaTest.php tests/Feature/RaporStsTest.php tests/Feature/PresensiAnakTest.php tests/Feature/Api/RekapPresensiSiswaApiTest.php tests/Feature/Api/LaporanPresensiSiswaApiTest.php
```
