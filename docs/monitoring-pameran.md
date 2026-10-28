# Monitoring Pameran

## Aktivasi

1. Setelah pull, jalankan `php artisan migrate --force` dan `php artisan optimize:clear`.
2. Pada browser komputer TV, login admin dan buka **Kehadiran > Monitoring TV** (`/perangkat-monitoring?jenis=pameran`).
3. Pilih cakupan **Mode pameran: siswa dan pengunjung stand**, acara buku tamu yang tepat, dan tanggal aktivitas awal. Acara draf tidak dapat dipilih.
4. Identitas siswa **disamarkan secara bawaan** di server, termasuk daftar keterlambatan, laporan dan modal. Jika sekolah sudah memastikan izin penayangan, admin dapat mengaktifkan nama/foto secara eksplisit saat aktivasi. Konseling tetap anonim; kronologi, NISN, kontak, alasan medis, dan catatan BK tidak dikirim. Statistik tamu tidak pernah memuat nama/komentar pengunjung.
5. Konfirmasikan penayangan publik lalu aktifkan. Klik **Buka layar pameran** (`/monitoring-pameran`) pada tab baru. Keluar dari akun admin tidak menghentikan layar.

Aktivasi pameran terpisah dari cakupan siswa/ujian internal dan scanner. Kredensial hanya tersimpan dalam cookie terenkripsi HttpOnly SameSite Strict; hash di database. Browser ruang guru tidak otomatis mendapat cakupan pameran, dan cakupan pameran tidak dapat membuka endpoint internal, scanner, administrasi Humas, atau acara lain. Tidak memerlukan worker atau APK baru. Pakai HTTPS di server sekolah.

## Dua Tab

- **Aktivitas siswa**: ringkasan, grafik, daftar dan modal seperti monitor ruang guru. Semua mengikuti tanggal pilihan pada perangkat ini. Perubahan tanggal tidak menulis presensi, ibadah, laporan, atau poin; hanya menyimpan `pameran_tanggal` perangkat yang sedang dipakai. Tanggal tidak mengubah jam server atau layar ruang guru.
- **Pengunjung stand**: total respons valid sepanjang acara yang dipilih, pengunjung hari berjalan WIB, grafik kunjungan harian, kepuasan stand/NUSA, kategori dan minat. Tidak mengikuti tanggal aktivitas siswa. Nilai NUSA kosong tidak masuk pembagi kepuasan NUSA. Acara yang berubah menjadi draf/tidak tersedia menghentikan penayangan statistiknya.
- Tab dapat dipilih manual, dengan navigasi keyboard, atau opsi **Rotasi 30 detik**. Rotasi dijeda saat modal terbuka, tanggal sedang disimpan, pengguna memfokuskan tanggal, atau halaman tidak terlihat. Daftar minat berganti halaman sesuai ruang layar, tanpa menimpa keterangan.
- Data tab aktif diperbarui setiap **5 detik**, bukan me-refresh seluruh halaman. Snapshot singkat dibagikan antar-TV. Saat jaringan terputus, data terakhir tetap terlihat bersama peringatan; pencabutan perangkat menghapus data dan menghentikan polling.

## Tanggal Aktivitas

Tanggal dapat dipilih dalam rentang tahun pelajaran aktif, maksimal hari ini. Pilihan tersimpan di database per perangkat dan bertahan setelah reload/logout. Pada Sabtu **31 Oktober 2026**, petugas dapat menampilkan Jumat **30 Oktober 2026** atau Kamis **29 Oktober 2026**. Bila tahun aktif berganti dan pilihan lama di luar rentang, tanggal efektif dibatasi ke rentang tahun aktif.

- Presensi/keterlambatan mengikuti tanggal catatan. Hari tanpa jadwal atau pengecualian tidak otomatis dianggap alfa. Pada hari wajib yang telah berakhir, perhitungan alfa tetap mengikuti aturan presensi NUSA.
- Laporan mengikuti **waktu pengiriman**, bukan tanggal kejadian/pengesahan. Status laporan yang sudah dikoreksi mengikuti data tersimpan saat dimuat.
- Ibadah mengikuti jadwal hari terpilih, presensi tanggal terpilih dan keanggotaan efektif. Identitas pengecualian pribadi tidak ditampilkan.
- Saldo poin dihitung dari transaksi sampai akhir tanggal terpilih; tambahan/pengurangan hanya transaksi hari tersebut. Jika tanggal hari ini, batasnya waktu sekarang. Transaksi hari berikutnya tidak masuk ke data hari sebelumnya.
- Grafik hari/minggu/bulan berpatokan pada tanggal pilihan, bukan tanggal aktual TV. Tanggal data dan waktu pembaruan dipisahkan: data Jumat dapat diperbarui pada Sabtu. Data historis ini merupakan perhitungan ulang dari data terkoreksi yang tersimpan, bukan salinan beku seluruh konfigurasi/status masa lalu.
- Histori menggunakan keanggotaan yang berlaku pada tanggal tersebut, termasuk anggota yang kemudian keluar/pindah dengan tanggal keluar tercatat.

Jika riwayat kelas/keanggotaan pernah ditimpa tanpa catatan historis, layar tidak merekonstruksi data yang sudah hilang; perhitungan memakai keanggotaan tersimpan dan tidak mencampur presensi/ibadah dari kelas lain.

Sumber mengikuti instalasi yang dibuka: NUSA server memakai database server, NUSA lokal memakai database lokal. Tidak ada sinkronisasi otomatis antara keduanya dan tidak ada angka acak/demo pada layar operasional.

## Endpoint dan Keamanan

Semua endpoint di bawah menggunakan middleware perangkat TV dengan cakupan `pameran`, tanpa sesi akun. Semua respons no-store/noindex. Raw snapshot tidak dikirim utuh; identitas disamarkan sebelum pencarian/paginasi dan sebelum JSON dikirim jika izin identitas tidak aktif. Hanya admin dapat mengubah izin identitas/cakupan/acara dengan aktivasi ulang (kredensial sebelumnya dicabut).

```text
GET /monitoring-pameran
GET /monitoring-pameran/data
GET /monitoring-pameran/rincian?jenis=hadir&q=...&kelas_id=...&page=1
GET /monitoring-pameran/foto/{siswa}
GET /monitoring-pameran/pengunjung
PUT /monitoring-pameran/tanggal   {"tanggal":"2026-10-30"}
```

Perubahan tanggal memerlukan header `X-Monitor-CSRF` yang diturunkan dari kredensial perangkat, pemeriksaan Origin/Fetch-Site, cookie perangkat, serta validasi rentang di server. Header tersebut bukan token aktivasi dan tidak memberi akses tanpa cookie. Foto hanya dapat diambil jika admin mengaktifkan identitas dan siswa tercakup pada tanggal pilihan. Mengirim ID acara/perangkat/tahun lain pada permintaan TV tidak mengubah sumber yang diikat saat aktivasi.

## Pengujian

`tests/Feature/MonitoringPameranTest.php` mencakup tanggal historis, laporan/ibadah/poin, keanggotaan keluar, batas tanggal, CSRF, cakupan, pencabutan, privasi server, statistik acara dan logout. `tests/Browser/monitoring-pameran.mjs` mencakup enam viewport, pergantian tanggal/reload, dua tab/rotasi, grafik berpiksel, mode offline dan pencabutan. Fixture hanya data sintetis SQLite.

```powershell
$env:NUSA_CAPTURE_PAMERAN='1'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/MonitoringPameranTest.php
$env:PLAYWRIGHT_MODULE='PATH/node_modules/playwright/index.mjs'
$env:PLAYWRIGHT_CHANNEL='msedge'
node tests/Browser/monitoring-pameran.mjs
```
