# Keterlambatan pada Rapor STS

Rekap, pratinjau, dan cetakan Rapor STS menampilkan jumlah kejadian terlambat
(kali) dan total durasi keterlambatan (menit), tepat di bawah Sakit/Izin/Alfa
dalam bagian kehadiran yang sama.

- Mengikuti awal dan batas rekap presensi yang disimpan untuk rapor kelas.
- Mengambil presensi siswa pada tahun pelajaran kegiatan STS, bukan presensi ujian CBT.
- Satu hari berstatus hadir dengan `menit_terlambat > 0` dihitung satu kejadian.
- Scan pulang tidak wajib untuk menghitung keterlambatan datang.
- Hari tanpa presensi dan hari sakit/izin/alfa tidak dihitung terlambat.
- Jika belum ada keterlambatan tercatat, ditampilkan `0 kali` dan `0 menit`.

Rekap keterlambatan bersifat baca-saja dan mengikuti koreksi presensi harian.
Koreksi Sakit/Izin/Alfa khusus rapor tetap terpisah; data presensi asli dan
pemeriksaan kehadiran yang telah tersimpan tidak diubah oleh penambahan ini.

Tidak ada migrasi baru. Setelah pull di server, jalankan `php artisan optimize:clear`.
Cetakan tetap A4 portrait, satu halaman per siswa, termasuk rapor bernilai belum lengkap.

## Pengujian

`php vendor/phpunit/phpunit/phpunit tests/Feature/RaporStsTest.php`

Untuk menyiapkan fixture pengujian cetak, set `NUSA_CAPTURE_STS_PRINT=1` saat
menjalankan tes di atas, kemudian jalankan `node tests/Browser/rapor-sts-print.mjs`
dengan Playwright dan pdf-lib yang tersedia.
