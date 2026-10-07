# Rincian presensi per siswa

Di **Kehadiran > Laporan Presensi**, pilih tahun pelajaran, kelas, serta periode harian, bulanan, semester, atau rentang tanggal. Tombol **Rincian** tersedia di bawah identitas setiap siswa pada tabel desktop dan bagian bawah baris siswa di HP.

Rincian mengikuti periode yang dipilih dan menampilkan hari/tanggal, status hadir/sakit/izin/alfa, jam datang/pulang, menit terlambat, catatan petugas, serta pulang cepat jika tercatat. Ringkasan menunjukkan jumlah hari tiap status, jumlah kejadian terlambat, dan total menit terlambat.

Filter **Status / kejadian** memilih semua hari, sakit, izin, alfa, terlambat, hadir, atau belum dikonfirmasi. Filter terlambat memilih catatan dengan `menit_terlambat > 0`; siswa tersebut tetap berstatus hadir. Ringkasan periode tidak berubah ketika rincian difilter. Reset dan tombol kembali mempertahankan periode awal.

Alfa tanpa konfirmasi pada hari aktif yang sudah berakhir diberi penanda **Alfa otomatis**. Alfa yang dicatat petugas tidak diberi penanda otomatis. Hari ini yang belum memiliki catatan tetap belum dikonfirmasi. Hari nonaktif dan tanggal mendatang tidak muncul. Scan datang tanpa scan pulang tetap hadir, dengan keterangan **Belum scan pulang**. Halaman ini hanya membaca data; koreksi presensi tetap melalui rekap harian.

Hari tanpa catatan yang memiliki penetapan pengecualian aktif ditampilkan sebagai **PJJ / pengecualian - scan sekolah tidak diwajibkan**, beserta alasannya. Filter **Pengecualian presensi** memilih hari tersebut. Tidak dihitung sebagai alfa otomatis atau hadir; catatan manual tetap diutamakan.

Rute web `/laporan-absensi/{anggotaKelas}/rincian` memakai izin `absensi.laporan` dan cakupan kelas yang sama dengan laporan utama. Wali kelas tidak bisa membuka rincian siswa di luar kelasnya. Web dan API memakai perhitungan rincian yang sama. Kontrak API lama dipertahankan dengan tambahan label hari, tanggal panjang, sumber, penanda alfa otomatis, dan status belum scan pulang.

Tidak ada migrasi database atau perubahan scheduler. Setelah pembaruan kode di server, jalankan `php artisan optimize:clear`. Mulai ulang layanan PHP bila OPcache tidak memeriksa perubahan file, pada waktu pemeliharaan.

## Verifikasi

Pengujian fitur memakai database SQLite sementara, bukan data sekolah.

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/RincianPresensiSiswaTest.php tests/Feature/Api/LaporanPresensiSiswaApiTest.php tests/Feature/AlfaOtomatisSiswaTest.php tests/Feature/PermissionRouteTest.php
```

Untuk audit browser, buat fixture dengan `NUSA_CAPTURE_RINCIAN_PRESENSI=1` lalu jalankan `tests/Browser/rincian-presensi.mjs` memakai Playwright. Audit mencakup desktop/tablet/HP, tombol rincian, filter, reset, kembali, keutuhan periode, dan pemuatan ikon lokal.
