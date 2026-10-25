# Monitoring TV Aktivitas Siswa

## Penggunaan

1. Pada komputer yang terhubung ke TV, login sebagai administrator NUSA.
2. Buka **Kehadiran > Monitoring TV Siswa** (`/perangkat-monitoring`).
3. Isi nama layar, konfirmasi bahwa layar hanya untuk area guru, lalu aktifkan browser.
4. Pilih **Buka layar TV**. Halaman `/monitoring-siswa` terbuka pada tab baru.
5. Keluar dari akun admin pada tab pengaturan. Halaman TV tetap berjalan.
6. Gunakan tombol layar penuh atau F11. Pilih periode grafik dan, bila diperlukan, grafik per kelas.

Data diambil setiap **5 detik** melalui `GET /monitoring-siswa/data`, tanpa me-refresh dokumen. Daftar panjang berganti halaman setiap 15 detik; laporan yang baru masuk ditampilkan di halaman pertama. Layar dioptimalkan untuk 1920x1080, 1366x768, dan 1280x720. Pada layar kecil halaman dapat digulir tanpa terpotong secara horizontal.

## Makna Data

- Ringkasan mengikuti tahun pelajaran aktif, siswa aktif, kelas aktif, dan tanggal keanggotaan efektif. Kehadiran yang hanya memiliki scan masuk tetap termasuk hadir.
- Terlambat termasuk hadir; penanda `status_masuk=terlambat` tetap dihitung meskipun baru lewat beberapa detik dan menit tercatat masih nol.
- Belum tercatat **bukan alfa**. Pengecualian presensi dan hari tanpa jadwal tidak dipaksakan menjadi alfa. Alfa pada kartu merupakan alfa yang telah tercatat hari ini.
- Grafik presensi menggunakan layanan grafik dashboard NUSA yang sama, dengan kalender, tanggal masuk/keluar, dan pengecualian presensi. Minggu dimulai hari Senin.
- Laporan masuk dihitung berdasarkan **waktu pengiriman (`created_at`)**, bukan tanggal kejadian atau waktu pengesahan. Laporan kejadian lama yang baru dikirim tetap muncul hari ini. Laporan dibatalkan/tidak terbukti tidak ditampilkan. Daftar memuat 18 laporan terbaru hari ini dan jumlah keseluruhan tetap ditampilkan.
- `Belum diverifikasi` tidak menyatakan siswa terbukti melanggar. Usulan poin laporan tidak dihitung sebagai poin resmi.
- Poin resmi berasal dari buku transaksi poin, termasuk koreksi/pengurangan. Saldo tidak negatif dihitung per siswa, kemudian dijumlahkan. Tidak ada pemeringkatan siswa berdasarkan pelanggaran.
- Ibadah mengikuti jadwal aktif hari ini dan perhitungan peserta wajib yang hadir di sekolah. Status berhalangan individual tidak dikirim ke TV.
- Halaman ini tidak mencatat presensi, tidak mengesahkan laporan, dan tidak menetapkan poin.

## Keamanan dan Koneksi

Aktivasi khusus monitoring terpisah dari akun login dan perangkat scanner. Hanya administrator aktif (bukan akun siswa/orang tua yang salah diberi role) dapat mengelola perangkat. Kredensial tidak berada di URL, HTML, log aplikasi, atau localStorage. Database hanya menyimpan hash token; browser menyimpan cookie terenkripsi HttpOnly, SameSite Strict, host-only, Secure pada HTTPS, diperpanjang hingga 400 hari selama digunakan.

Layar menampilkan identitas terbatas (nama dan kelas) hanya untuk ruang guru. Tidak mengirim NISN, nomor kontak, kronologi, alasan sakit, bukti kasus, catatan BK, ataupun catatan rahasia. Laporan konseling dianonimkan. Jangan tempatkan TV pada area publik atau akses orang tua/siswa.

Cabut akses dari daftar perangkat bila TV dipindahkan atau tidak lagi dikelola. Permintaan berikutnya (normalnya dalam 5 detik) ditolak meskipun snapshot masih ada di cache. UI menghapus identitas yang sebelumnya terlihat dan berhenti meminta data. Setelah aktivasi ulang, muat ulang tab monitoring.

Saat jaringan gagal, data terakhir dipertahankan dengan peringatan jelas dan waktu pembaruan terakhir; permintaan dicoba lagi setiap 5 detik. Tidak ada permintaan yang tumpang tindih. Timeout 4,5 detik. Snapshot server dibagikan antarlayar dalam jendela maksimal 5 detik untuk membatasi beban. Endpoint menerapkan batas 240 permintaan/menit per IP dan header no-store/noindex.

## Setelah Pull di Server

```sh
php artisan migrate --force
php artisan optimize:clear
```

Tidak memerlukan seeder role, queue worker tambahan, npm build, atau pembaruan APK untuk fitur browser ini. Gunakan HTTPS di server sekolah. Migrasi hanya menambah tabel `perangkat_monitoring`; tidak mengubah nilai, presensi, kasus, atau poin lama. Administrator perlu mengaktifkan browser TV sekali di server.

## Verifikasi

- `tests/Feature/MonitoringTvSiswaTest.php`: aktivasi, logout, role, pemisahan scanner, pencabutan, presensi detik, laporan pending, transaksi/koreksi poin, privasi ibadah, kalender, dan jumlah query.
- `tests/Browser/monitoring-tv.mjs`: enam viewport, grafik berisi piksel, pergantian periode/per kelas, polling 5 detik, tanpa request tumpang tindih, offline/pulih, laporan baru, dan penghapusan identitas saat akses dicabut. Fixture hanya data siswa sintetis dari pengujian SQLite.

```powershell
$env:NUSA_CAPTURE_MONITOR='1'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/MonitoringTvSiswaTest.php
$env:PLAYWRIGHT_MODULE='PATH/node_modules/playwright/index.mjs'
$env:PLAYWRIGHT_CHANNEL='msedge'
node tests/Browser/monitoring-tv.mjs
```
