# Monitoring TV Aktivitas Siswa

Untuk layar pameran dengan dua tab (aktivitas siswa bertanggal dan statistik pengunjung), gunakan cakupan **Mode pameran**; lihat [Monitoring Pameran](monitoring-pameran.md). Monitoring ruang guru di halaman ini tetap mengikuti hari berjalan dan tidak menyediakan pemilih tanggal.

## Penggunaan

1. Pada komputer yang terhubung ke TV, login sebagai administrator NUSA.
2. Buka **Kehadiran > Monitoring TV** (`/perangkat-monitoring`), dengan cakupan **Aktivitas siswa** atau **Aktivitas siswa dan ujian terpusat**.
3. Isi nama layar, konfirmasi bahwa layar hanya untuk area guru, lalu aktifkan browser.
4. Pilih **Buka layar TV**. Halaman `/monitoring-siswa` terbuka pada tab baru.
5. Keluar dari akun admin pada tab pengaturan. Halaman TV tetap berjalan.
6. Gunakan tombol layar penuh atau F11. Pilih periode grafik dan, bila diperlukan, grafik per kelas.

Data diambil setiap **5 detik** melalui `GET /monitoring-siswa/data`, tanpa me-refresh dokumen. Daftar panjang berganti halaman setiap 15 detik; laporan yang baru masuk ditampilkan di halaman pertama. Layar dioptimalkan untuk 1920x1080, 1366x768, dan 1280x720. Pada layar kecil halaman dapat digulir tanpa terpotong secara horizontal.

## Modal Rincian

- Klik kartu Hadir, Izin, Sakit, Alfa, Belum tercatat, Terlambat, atau Laporan masuk untuk membuka rincian tanpa meninggalkan monitoring. Tombol ikon di judul bagian membuka rincian ibadah, keterlambatan, laporan, dan saldo poin. Angka penambahan/pengurangan poin membuka transaksi resmi hari ini. Jumlah siswa pada footer membuka seluruh siswa yang dipantau.
- Rincian berisi nama, kelas, foto bila tersedia, status, waktu kedatangan/pengiriman/transaksi bila relevan, dan informasi terbatas seperti menit terlambat atau poin. Foto yang tidak tersedia memakai inisial. Waktu ibadah tidak direka dari jadwal: bila tidak dimuat, kolom waktu berisi `-`.
- Tersedia pencarian nama, filter kelas, dan 20 baris per halaman. Modal ibadah menambahkan filter kegiatan serta status sudah/belum tercatat. Hanya peserta wajib dari rekap ibadah yang ditampilkan, bukan status pengecualian pribadi.
- Modal diperbarui setiap 5 detik selama terbuka. Fokus, filter, halaman, dan posisi gulir dipertahankan. Siswa yang baru scan menghilang dari daftar belum tercatat pada pembaruan berikutnya. Jika jumlah halaman menyusut, halaman disesuaikan ke halaman terakhir yang masih tersedia.
- Rotasi otomatis daftar di latar belakang dijeda selama modal terbuka, tetapi ringkasan dan grafik tetap diperbarui. Tutup dengan ikon silang, Escape, atau klik latar di luar modal. Fokus kembali ke tombol pembuka.
- Saat koneksi rincian gagal, data terakhir untuk filter yang sama dipertahankan dengan peringatan. Mengganti filter tidak mempertahankan daftar lama yang tidak cocok. Pencabutan akses dari ringkasan atau rincian menutup modal, menghapus identitas, dan menghentikan polling.

Endpoint tambahan (tetap memakai otorisasi perangkat TV siswa, bukan sesi akun):

```text
GET /monitoring-siswa/rincian?jenis=belum&kelas_id=...&q=...&page=1
GET /monitoring-siswa/foto/{siswa}
```

Jenis rincian: `dipantau`, `hadir`, `izin`, `sakit`, `alfa`, `belum`, `pengecualian`, `terlambat`, `laporan`, `ibadah`, `poin`, `poin_tambah`, `poin_kurang`. Untuk ibadah, filter opsional `kegiatan_id` dan `status_ibadah=sudah|belum`. Filter dan jenis divalidasi; ukuran halaman tetap 20. Rincian memakai snapshot yang sama dengan kartu, bukan perhitungan kedua atau kueri per siswa. Ringkasan tidak mengirim seluruh daftar rincian. Modal laporan mencakup semua laporan hari ini, tidak hanya 18 laporan terbaru di panel.

Identitas laporan konseling tetap dianonimkan, termasuk foto dan kelas. Pencarian nama atau filter kelas tidak digunakan untuk mengungkap identitas konseling. Tidak ada NISN, kontak, kronologi, catatan BK, narasi medis, atau alasan koreksi poin dalam rincian. Foto hanya dapat diambil oleh perangkat TV dengan cakupan siswa untuk siswa dan keanggotaan aktif pada tahun aktif. Browser dengan cakupan ujian saja tidak memperoleh akses ini.

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

Layar menampilkan identitas terbatas (nama, kelas, dan foto dalam modal bila tersedia) hanya untuk ruang guru. Tidak mengirim NISN, nomor kontak, kronologi, alasan sakit, bukti kasus, catatan BK, ataupun catatan rahasia. Laporan konseling dianonimkan. Jangan tempatkan TV pada area publik atau akses orang tua/siswa.

Cabut akses dari daftar perangkat bila TV dipindahkan atau tidak lagi dikelola. Permintaan berikutnya (normalnya dalam 5 detik) ditolak meskipun snapshot masih ada di cache. UI menghapus identitas yang sebelumnya terlihat dan berhenti meminta data. Setelah aktivasi ulang, muat ulang tab monitoring.

Saat jaringan gagal, data terakhir dipertahankan dengan peringatan jelas dan waktu pembaruan terakhir; permintaan dicoba lagi setiap 5 detik. Tidak ada permintaan yang tumpang tindih. Timeout 4,5 detik. Snapshot server dibagikan antarlayar dalam jendela maksimal 5 detik untuk membatasi beban. Endpoint menerapkan batas 240 permintaan/menit per IP dan header no-store/noindex.

## Setelah Pull di Server

```sh
php artisan migrate --force
php artisan optimize:clear
```

Tidak memerlukan seeder role, queue worker tambahan, npm build, atau pembaruan APK untuk fitur browser ini. Gunakan HTTPS di server sekolah. Migrasi hanya menambah tabel `perangkat_monitoring`; tidak mengubah nilai, presensi, kasus, atau poin lama. Administrator perlu mengaktifkan browser TV sekali di server.

Pembaruan modal rincian tidak menambah migrasi atau izin baru. Jika fitur TV sebelumnya sudah dipasang, cukup `php artisan optimize:clear` setelah pull, lalu muat ulang tab TV sekali untuk mengambil JavaScript dan CSS terbaru. Aktivasi perangkat lama tetap berlaku.

## Verifikasi

- `tests/Feature/MonitoringTvSiswaTest.php`: aktivasi, logout, role, pemisahan scanner, pencabutan, presensi detik, laporan pending, transaksi/koreksi poin, privasi ibadah, kalender, dan jumlah query.
- `tests/Browser/monitoring-tv.mjs`: enam viewport, grafik berisi piksel, pergantian periode/per kelas, polling 5 detik, tanpa request tumpang tindih, offline/pulih, laporan baru, dan penghapusan identitas saat akses dicabut. Fixture hanya data siswa sintetis dari pengujian SQLite.
- `tests/Browser/monitoring-tv-rincian.mjs`: lima viewport (1920x1080, 1280x720, 1024x768, 390x844, 320x640), seluruh jenis modal, foto, pencarian/filter/paginasi, posisi gulir dan fokus saat polling, pemulihan koneksi, serta pencabutan akses dari endpoint rincian.

```powershell
$env:NUSA_CAPTURE_MONITOR='1'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/MonitoringTvSiswaTest.php
$env:PLAYWRIGHT_MODULE='PATH/node_modules/playwright/index.mjs'
$env:PLAYWRIGHT_CHANNEL='msedge'
node tests/Browser/monitoring-tv.mjs
node tests/Browser/monitoring-tv-rincian.mjs
```
