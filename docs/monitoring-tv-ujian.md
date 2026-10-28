# Monitoring TV Ujian Terpusat

## Aktivasi

1. Login sebagai administrator pada browser komputer yang tersambung ke TV.
2. Buka **Pusat CBT > Monitoring TV ujian**, atau **Kehadiran > Monitoring TV** (`/perangkat-monitoring?jenis=ujian`).
3. Pilih cakupan **Ujian terpusat** atau **Aktivitas siswa dan ujian terpusat**, serta kegiatan ujian yang diizinkan.
4. Konfirmasi lokasi terbatas guru, aktifkan browser, lalu pilih **Buka TV ujian**. Tab baru membuka `/monitoring-ujian-tv`.
5. Keluar akun admin pada tab pengaturan. Aktivasi TV tidak ikut berakhir.
6. Gunakan layar penuh/F11. Pilih jadwal dan sesi.

Mode **Semua ruang** menampilkan ringkasan ruang. Mode **Detail ruang** menampilkan meja, nama, kelas, status, presensi ujian, jawaban tersimpan, sisa waktu, tambahan waktu, dan komunikasi terakhir.

Data diperbarui setiap **5 detik** tanpa reload. Daftar berganti halaman setiap 15 detik; dalam mode detail seluruh halaman peserta satu ruang ditampilkan sebelum pindah ruang. Memilih ruang manual menonaktifkan pergiliran ruang; checkbox **Bergilir** mengaktifkannya lagi. Halaman peserta tetap berganti untuk daftar panjang.

## Modal Rincian

- Kartu Peserta terdaftar, Mengerjakan, Selesai, Belum mulai, Tidak hadir, dan Perlu perhatian membuka modal dengan filter status yang sesuai. Belum mulai mencakup belum hadir serta hadir tetapi belum mulai; bukan peserta nonaktif atau terblokir.
- Klik kartu ruang untuk melihat peserta ruang tersebut sebagai modal, tanpa mengubah mode layar utama. Mode Detail ruang dan pilihan pergilirannya tetap tersedia. Ikon rincian pada workspace mengikuti ruang yang sedang dipilih ketika mode detail aktif.
- Ikon pada panel perhatian dan grafik membuka rincian peserta yang relevan. Klik nama peserta pada daftar perhatian atau tabel detail ruang untuk membuka rincian satu peserta. Reset filter menampilkan seluruh peserta dari jadwal/sesi yang sama.
- Modal memuat nama, kelas, ruang, meja, sesi, status, presensi ujian, batas/sisa waktu, tambahan waktu, komunikasi terakhir, dan indikator pemeriksaan. Tidak menambahkan isi jawaban, progres jawaban, skor, nilai, atau token ke dalam modal. Presensi ujian tetap dibedakan dari status pengerjaan.
- Pencarian nama, filter ruang, kelas, status, dan paginasi 20 baris dikerjakan pada snapshot yang sudah diterima. Tidak ada endpoint baru atau request tambahan setiap kali filter diganti. Pada HP, pencarian tetap terlihat dan tombol ikon filter membuka/menutup pilihan lainnya agar daftar tetap lapang.
- Modal aktivitas menampilkan maksimal 20 aktivitas terbaru yang memang tersedia pada snapshot, bukan seluruh riwayat kejadian. Filter ruang dan kelas memakai metadata aktivitas yang telah dibatasi ke peserta jadwal/sesi terpilih. Tidak mengirim catatan atau metadata keamanan mentah.
- Polling 5 detik tetap berjalan, termasuk perubahan status saat modal terbuka. Posisi gulir, filter, halaman, dan fokus tetap dipertahankan; halaman disesuaikan jika jumlah halaman menyusut. Hitung mundur mengikuti waktu server dan tetap berjalan per detik tanpa mengubah batas waktu ujian.
- Rotasi otomatis ruang/daftar dijeda selama modal terbuka. Tutup dengan silang, Escape, atau klik di luar modal untuk kembali ke tampilan yang sama. Fokus tombol dipertahankan saat daftar diperbarui.
- Pergantian jadwal/sesi, termasuk pergantian otomatis, menutup dan membersihkan modal agar data dari dua konteks tidak tercampur. Koneksi terputus mempertahankan data terakhir dengan peringatan. Pencabutan akses menutup modal, menghapus data/opsi filter, menonaktifkan tombol rincian, dan menghentikan polling.

## Akses dan Privasi

- Perangkat lama tetap bercakupan `siswa`; tidak otomatis mendapat akses ujian. Akses ujian harus diberikan administrator secara eksplisit, terikat satu kegiatan.
- Perangkat khusus ujian tidak dapat membaca monitoring siswa. Pilih `keduanya` bila kedua halaman diperlukan.
- Kepemilikan jadwal/sesi diperiksa sebelum cache; manipulasi ID tidak memberi akses kegiatan lain.
- Hanya administrator aktif, bukan akun siswa/orang tua yang salah diberi role, dapat mengelola perangkat. Izin akun operasional guru/panitia tidak diperluas.
- Cookie terenkripsi HttpOnly, SameSite Strict, host-only, Secure pada HTTPS; hash saja di database. Tidak ada token di URL/localStorage. Aktivasi terpisah dari login dan scanner.
- Tidak mengirim pertanyaan, isi jawaban, kunci, skor/nilai, token ujian, password, IP, catatan rahasia, atau metadata keamanan mentah.
- Pencabutan menghalangi request berikutnya meskipun cache ada. UI menghapus identitas dan berhenti polling. Aktivasi ulang diikuti reload tab.
- Layar hanya untuk area guru, bukan area publik.

## Arti Data

- Satu layar mengambil **satu jadwal/paket**; sesi bisa difilter. Angka adalah keikutsertaan pada paket terpilih, bukan penjumlahan seluruh mapel.
- Jadwal otomatis memilih paket hari ini: sedang berlangsung, berikutnya, atau terakhir hari ini. Jadwal lain dapat dipilih eksplisit. Asesmen kelas dan jadwal dibatalkan tidak tersedia.
- Presensi adalah **presensi ujian**, bukan scan datang sekolah. Status pengerjaan tetap terpisah.
- Peserta nonaktif termasuk total terdaftar dan persentase selesai. Perlu perhatian merupakan subset, bukan kategori tambahan untuk dijumlahkan.
- Peserta tanpa ruang tetap dihitung pada **Belum ditempatkan**.
- Jawaban adalah jumlah jawaban non-null tersimpan, dibatasi jumlah soal paket; bukan jawaban benar atau nilai.
- Batas waktu memakai layanan CBT yang sama, termasuk sesi, durasi, susulan, dan tambahan waktu.
- Komunikasi lebih dari 120 detik tanpa pembaruan saat pengerjaan diberi indikator pemeriksaan. Bukan kepastian internet putus atau kecurangan. Tanpa heartbeat, usia sesi/pengerjaan menjadi acuan; kolom komunikasi tetap `-`.
- Aktivitas keamanan memakai label netral; bukan vonis pelanggaran.
- Grafik batang: mengerjakan, selesai, belum mulai per ruang. Grafik garis: akumulasi timestamp selesai per 10 menit dan titik terbaru. Selesai tanpa timestamp valid masuk ringkasan tanpa waktu fiktif di grafik.
- **Selesai mengerjakan bukan finalisasi hasil.**

## Jalur Baca-Saja

`GET /monitoring-ujian-tv` dan `GET /monitoring-ujian-tv/data?jadwal_id=...&sesi_id=...` memakai perangkat tanpa sesi web. Tidak memanggil controller operasional yang menyelesaikan ujian kedaluwarsa.

Tidak menutup/membuka sesi, menambah waktu, memblokir, memberi poin, mengoreksi jawaban, atau memfinalisasi hasil. Pembaruan pendukung hanya waktu terakhir perangkat terhubung, maksimal sekali per menit.

Snapshot dibagi antarlayar dengan kegiatan/jadwal/sesi sama dalam jendela maksimal 5 detik. Batas 240 request/menit per IP. Polling tanpa tumpang tindih, timeout 4,5 detik; offline mempertahankan data terakhir dengan peringatan dan waktu pembaruan.

## Setelah Pull

```sh
php artisan migrate --force
php artisan optimize:clear
```

Tidak memerlukan seeder, worker tambahan, npm build, atau APK baru. Migrasi hanya menambah `cakupan` dan `kegiatan_ujian_cbt_id` pada `perangkat_monitoring`, tanpa mengubah peserta/hasil/jadwal. Browser TV lama perlu diperbarui aktivasinya hanya bila akan diberi akses ujian.

Khusus pembaruan modal, tidak ada migrasi, perubahan role, atau aktivasi ulang. Bila monitoring TV ujian sudah terpasang, cukup `php artisan optimize:clear` setelah pull dan muat ulang tab TV sekali untuk mengambil aset terbaru.

## Verifikasi

- `tests/Feature/MonitoringTvUjianTest.php`: aktivasi, logout, cakupan, isolasi kegiatan/jadwal/sesi, presensi ujian, kerahasiaan payload, progres, tambahan waktu, pembacaan tanpa perubahan peserta, pencabutan, kosong dan query terbatas.
- `tests/Browser/monitoring-ujian-tv.mjs`: enam viewport, grafik nonblank, mode ringkasan/detail, pergiliran peserta/ruang, pilihan manual, polling, filter, tanpa request tumpang tindih/POST, offline/pulih, aktivitas baru dan pembersihan data saat dicabut. Fixture siswa sintetis SQLite.
- `tests/Browser/monitoring-ujian-tv-rincian.mjs`: enam viewport, seluruh kartu status, modal ruang/peserta/aktivitas, filter kelas/status/ruang tanpa penempatan, pencarian aman, paginasi, timer, fokus/posisi gulir, koneksi gagal/pulih, pergantian jadwal, kondisi kosong dan pencabutan akses. Tidak melakukan perubahan data ujian.

```powershell
$env:NUSA_CAPTURE_EXAM_TV='1'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/MonitoringTvUjianTest.php
$env:PLAYWRIGHT_MODULE='PATH/node_modules/playwright/index.mjs'
$env:PLAYWRIGHT_CHANNEL='msedge'
node tests/Browser/monitoring-ujian-tv.mjs
node tests/Browser/monitoring-ujian-tv-rincian.mjs
```
