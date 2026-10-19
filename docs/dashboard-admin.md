# Dashboard Admin

Dashboard admin memakai view `beranda.admin`. Dashboard pegawai, siswa, dan orang tua
tetap memakai data/view khusus masing-masing, dengan bahasa visual bersama.
Flag tema juga diperiksa terhadap akun administrator di layout. Menu tetap bersumber dari daftar menu
dan izin yang sama; notifikasi, menu akun, pencarian sidebar, dan tautan operasional tidak disimulasikan.

## Kerangka Admin Bersama

- Seluruh akun terautentikasi yang memakai layouts.app memuat css/admin-shell.css dan
  js/admin-shell.js secara otomatis. Tema visual tidak memberikan izin administrator.
- Sidebar putih, logo, pencarian, header, notifikasi, dan kontrol gulir memakai sumber yang sama.
  Menu aktif mengikuti route yang sudah ada. Breadcrumb memakai kelompok menu aktif dan judul halaman.
- Kode navigasi tidak bergantung pada canvas, Chart.js, atau elemen dashboard. Pustaka grafik serta CSS
  isi dashboard tetap hanya dimuat pada dashboard. Formulir, tabel, validasi, API, dan izin tidak diubah.
- Sidebar memiliki tiga pilihan: tampil penuh, ringkas (ikon), dan sembunyikan seluruh panel.
  Tombol panel di kiri header menyembunyikan/menampilkan; panah di sebelahnya membuka pilihan mode.
  Saat tersembunyi, konten memakai lebar yang tersedia dan tombol tampilkan tetap dapat dijangkau.
- Preferensi sidebar disimpan per akun di localStorage, termasuk mode sebelum disembunyikan;
  jika penyimpanan ditolak,
  navigasi tetap berfungsi. Grup menu aktif tetap dibuka oleh mekanisme navigasi yang sudah ada.
- Di ponsel, drawer memiliki focus trap, Escape, backdrop, dan inert untuk konten di belakangnya.
  Ctrl+K membuka sidebar sebelum memfokuskan pencarian, termasuk dari mode ringkas/tersembunyi.
- Akun guru, TU, pimpinan, siswa, dan orang tua memakai kerangka yang sama. Halaman cetak,
  login, serta layar khusus yang tidak memakai layouts.app tidak diberi sidebar baru.

## Kehadiran

- Data dibaca dari PostgreSQL melalui model presensi, keanggotaan kelas, dan pengaturan presensi aktif.
- Hari ini menampilkan akumulasi jam masuk; mingguan mulai Senin; bulanan mulai tanggal 1.
- Rentang dibatasi tanggal hari ini dan tahun pelajaran aktif. Hari tanpa jadwal tidak dianggap alfa.
- Keanggotaan mengikuti tanggal masuk/keluar. Satu siswa dihitung sekali pada tanggal yang sama.
- Hadir mencakup terlambat. Angka keterlambatan bukan tambahan jumlah siswa hadir.
- Tanpa catatan hari ini berarti belum scan; pada hari aktif yang telah berakhir mengikuti alfa otomatis.
- Pengecualian mengikuti AturanAlfaOtomatisSiswaService. Pengecualian tanpa catatan mengurangi penyebut,
  tetapi tidak menghapus presensi/manual alfa yang sudah dicatat, sesuai aturan rekap yang ada.
- Persentase = jumlah hadir / kewajiban hadir. Rekap lintas hari merupakan akumulasi hari-siswa.
- Hari yang seluruhnya dikecualikan ditampilkan sebagai celah/null, bukan hadir nol atau alfa.
- Catatan hadir tanpa jam masuk tetap dihitung hadir, tetapi tidak direkayasa waktunya pada kurva.
- Semua agregasi hanya membaca data. Tidak ada migrasi, perubahan poin, atau penetapan alfa baru.
- Angka adalah snapshot ketika halaman dibuka, dengan waktu rekap yang terlihat. Tidak ada polling baru.

## Grafik Tambahan

- Presensi pegawai dan laporan siswa memiliki pilihan periode, batang/garis, legenda, dan tabel sendiri.
  Mengubah salah satunya tidak mengubah grafik kehadiran siswa.
- Pegawai: populasi pegawai aktif saat dashboard dibuka, dibatasi tanggal mulai bertugas/kerja.
  Jadwal semua pegawai, jenis pegawai, dan individu diperhitungkan tanpa query per pegawai/hari.
  Catatan nyata tetap dihitung pada hari tanpa jadwal; hari tanpa jadwal/catatan menjadi celah.
- Pegawai tanpa catatan pada hari terjadwal: hari ini belum scan, hari lampau alfa inferensi,
  sejalan dengan rekap harian. Ini hanya perhitungan tampilan, tidak menyimpan alfa atau mengubah poin.
  Izin menggabungkan izin, sakit, dinas luar, dan cuti. Terlambat termasuk jumlah hadir.
- Grafik laporan bersumber dari tanggal/jam kejadian tahun pelajaran aktif, bukan waktu pembuatan.
  Mingguan mulai Senin, bulanan tanggal 1, mencakup akhir pekan dan tidak melampaui hari ini.
  Seri: kejadian, pembinaan/konseling, dan pelanggaran berpoin. Ini volume laporan, bukan
  jumlah siswa, poin resmi, atau keputusan bahwa siswa bersalah. Laporan dibatalkan dikecualikan.
- Ringkasan tetap menghitung data tanpa jam valid; grafik harian tidak membuat jam fiktif.
  JSON grafik hanya berisi agregat, tanpa identitas siswa, kronologi, atau catatan rahasia.
- Desktop menggunakan satu gulir konten; mobile satu gulir halaman. Footer tetap dapat dijangkau
  tanpa ruang kosong tambahan. Blok sketsa, slogan dan akun di bawah sidebar dihapus agar
  area menu lebih luas; identitas dan menu akun tetap tersedia di header.

## Keterbacaan

- Teks dashboard utama 16 px, judul bagian 20 px, keterangan 13-14 px, angka statistik 32-36 px.
  Skala ukuran terpusat di dashboard-admin-layout.css dan tidak diperkecil berdasarkan lebar layar.
- Label sumbu grafik 13 px, tooltip 13-14 px. Teks pendukung memakai warna lebih gelap.
- Navigasi admin: judul kelompok 15 px, submenu dan pencarian 14 px, keterangan kelompok 13 px.
  Ukuran ini berlaku sama pada semua halaman admin yang memakai kerangka bersama.
- Panel, kontrol, dan label berpindah baris sesuai ruang. Ringkasan presensi satu kolom di layar
  kecil, dan kisi angka memakai lebar minimum agar nilai tidak bertumpuk dengan keterangannya.
- Browser QA memeriksa batas ukuran huruf, kontras teks pendukung, serta angka 12.000 pada layar
  320 px, selain pengujian grafik dan navigasi yang sudah ada. Tidak ada perubahan data atau izin.

## Aset Lokal

Chart.js 4.5.1 dan Lucide disimpan lokal di public/vendor/ dengan lisensinya.
Aset sketsa sekolah tetap tersedia untuk halaman login, tetapi tidak lagi dimuat di sidebar.
CSS dan JavaScript dashboard menggunakan versi filemtime; tidak memerlukan npm build atau CDN saat dipakai.

## Verifikasi

```powershell
$env:NUSA_CAPTURE_DASHBOARD='1'
$env:NUSA_CAPTURE_ADMIN_SHELL='1'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --filter='TemaKerangkaAdminTest|DashboardAdminTest|SidebarNavigasiTest|DashboardSiswaTest|IdentitasSesiPenggunaTest'
node tests/Browser/dashboard-admin.mjs
node tests/Browser/admin-shell.mjs
```

Browser QA memerlukan Playwright/Chrome dan variabel PLAYWRIGHT_MODULE bila memakai runtime bundel.
QA mencakup ketiga grafik, isolasi filter, perhitungan, ruang gulir sidebar, gulir desktop/mobile, dan tabel.
QA menggunakan hasil render Blade dari SQLite sementara dengan aset aplikasi asli; tidak login,
mengubah database lokal, atau mengirim formulir ke server. Snapshot ada di storage/logs/dashboard-admin-qa/.

Setelah pull: php artisan view:clear, lalu refresh browser. Tidak perlu migrasi atau restart worker untuk perubahan ini.
