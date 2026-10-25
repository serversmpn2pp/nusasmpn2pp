# Browser Scanner Tanpa Sesi Login

Scan siswa (`/scan-absensi`) dan pegawai (`/scan-absensi-pegawai`) tetap memakai
browser. Aksesnya sekarang berdasarkan aktivasi browser, bukan masa berlaku
login akun. Jadwal, jam server, pemrosesan scan, duplikasi, poin dan notifikasi
tetap melalui layanan presensi yang sama.

## Penerapan Server

```sh
php artisan migrate --force
php artisan optimize:clear
```

Tidak perlu seeder, perubahan `SESSION_LIFETIME`, pengaturan worker baru,
atau APK baru. Migrasi hanya menambah tabel `perangkat_scan_presensi` dan tidak
mengubah presensi lama. Selalu gunakan HTTPS pada server sekolah.

## Aktivasi Sekali Pada Setiap Browser

1. Pada komputer scanner, buka alamat NUSA yang akan digunakan sehari-hari.
2. Login administrator dan buka **Kehadiran > Perangkat Scanner** (`/perangkat-scan`).
3. Isi nama komputer/browser, pilih akses siswa, pegawai, atau keduanya,
   centang konfirmasi perangkat sekolah, lalu pilih **Aktifkan browser ini**.
4. Buka halaman scan dari tombol yang tersedia, lalu logout akun administrator.
   Logout tidak mencabut aktivasi scanner. Jangan tinggalkan dashboard admin
   dalam keadaan login di komputer scanner yang dapat dijangkau siswa.

Browser/profil berbeda harus diaktifkan masing-masing. Cookie yang dihapus,
mode incognito, pergantian alamat host sekolah, atau akses yang dicabut memerlukan
aktivasi lagi. Dua tab scan pada browser yang sama dapat memakai satu aktivasi
dengan cakupan **Siswa dan pegawai**, termasuk pada komputer dua monitor.

Admin dapat memilih **Cabut akses** pada daftar perangkat, dengan alasan yang
dicatat. Setiap permintaan diperiksa kembali ke server; tab lama tidak bisa
mengirim scan setelah akses dicabut. Cakupan yang salah juga ditolak. Aktivasi
ulang pada browser yang sama mengganti kredensial dan mencabut aktivasi lamanya.

## Keamanan Dan Operasional

- Token acak 80 karakter hanya disimpan dalam cookie terenkripsi, HttpOnly,
  host-only dan SameSite Strict. Di HTTPS cookie juga Secure. Database hanya
  menyimpan SHA-256 token, bukan token mentah. Token tidak dimasukkan ke URL,
  localStorage, daftar perangkat atau log aplikasi.
- Cookie bersifat persisten 400 hari (batas browser) dan diperbarui pada setiap
  permintaan yang sah, terpisah dari sesi login. Browser tetap bisa menghapus
  penyimpanannya sendiri; perangkat yang tidak digunakan sangat lama perlu
  diaktifkan ulang bila cookie kedaluwarsa.
- GET/POST scanner tidak menjalankan StartSession, ShareErrorsFromSession,
  atau CSRF sesi. Khusus POST scanner, header CSRF HMAC yang terikat kredensial
  perangkat wajib cocok; Origin dan Fetch Metadata lintas situs juga ditolak.
  CSRF standar pada aktivasi, pencabutan, login, dan fitur lain tidak diubah.
- Kredensial scanner tidak mengautentikasi akun admin atau Android API. Admin
  yang mengaktifkan/mencabut harus akun aktif, bukan akun siswa/orang tua yang
  salah mendapat role administrator.
- Batas laju: 600 permintaan/menit per IP untuk rute scanner dan 180 pengiriman
  scan/menit per perangkat per cakupan. Nama, pengaktif, status, waktu kontak
  terakhir dan alasan pencabutan tersedia pada daftar admin.
- Layar menyinkronkan jam, status akses dan jadwal setiap menit. Pergantian hari
  atau perubahan jadwal memuat ulang layar saat antrean kosong. Pemeriksaan
  otorisasi tetap dilakukan pada setiap scan, tidak menunggu sinkronisasi.
- Otorisasi berubah (419) disinkronkan lalu dicoba ulang satu kali. Gangguan
  jaringan tidak diputar ulang otomatis: hasil tampil gagal dan kartu harus
  discan lagi, agar tidak mengirim presensi lama tanpa kepastian.
- Pencatatan waktu selalu memakai jam server saat diterima, bukan jam komputer.
  Tidak ada penyimpanan antrean kartu secara offline di disk browser.

## Verifikasi

`PerangkatScanPresensiTest` menguji cookie/HTTPS, pembatasan admin/cakupan,
scan nyata tanpa sesi, akses sesudah 30 hari, CSRF/Origin, pemalsuan cookie,
pencabutan, rotasi kredensial, batas laju dan pergantian hari di SQLite terisolasi.

`tests/Browser/scanner-device.mjs` memakai HTML sintetis dari tes tersebut:
monitor 1024x768, 1280x720, 1920x1080 dan HP, hasil scan, 419, jaringan gagal,
pencabutan pada tab lama, serta dialog pengelolaan perangkat. Tidak memakai
presensi siswa/pegawai sebenarnya.

```powershell
$env:NUSA_CAPTURE_SCANNER='1'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --filter PerangkatScanPresensiTest
node tests/Browser/scanner-device.mjs
```

Untuk runtime Node bundled, atur `PLAYWRIGHT_MODULE` ke modul Playwright yang
terpasang; gunakan `PLAYWRIGHT_CHANNEL=msedge` bila Chromium uji belum tersedia.

Hasil verifikasi: 81 tes inti (1.706 assertions), 29 pemeriksaan alur browser,
132 pemeriksaan viewport siswa dan 157 viewport pegawai lolos, serta Pint dan
whitespace bersih. Pada regresi lebih luas, 351 dari 352 tes lolos; satu tes
di luar scanner masih gagal pada
`PengecualianPresensiSiswaTest::test_rapor_menghitung_sumber_baru_tetapi_tidak_menimpa_koreksi_yang_disimpan`
(ekspektasi alfa 4, hasil 0). Kegagalan tersebut juga terjadi saat dijalankan
sendiri. Tes dan layanan rapor itu tidak diubah dalam pekerjaan scanner ini.
