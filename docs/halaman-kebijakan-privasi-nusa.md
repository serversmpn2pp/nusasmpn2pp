# Halaman publik dan akses kebijakan privasi NUSA

Implementasi lokal: 8 Oktober 2026. Pengguna menyetujui naskah dan meminta
label draf dihapus. Halaman menampilkan tanggal berlaku **8 Oktober 2026**.
Ini bukan persetujuan rilis Google Play. Pengamanan foto publik ditunda atas
pilihan pengguna; kode penyimpanan/penayangan foto tidak diubah.

## Yang tersedia

- Halaman HTML `GET /kebijakan-privasi`, tanpa login, sesi, cookie, maupun
  query database akun. Tidak memakai layout dashboard atau menampilkan
  identitas pengguna, termasuk ketika dibuka dari akun yang sudah login.
- Tautan “Kebijakan Privasi” sebelum login dan di tab Profil Flutter, serta
  halaman login web. Link Flutter membuka browser dengan alamat publik
  berdasarkan server API yang dikonfigurasi; mendukung instalasi subfolder.
- Browser tidak menerima Bearer token, kredensial, query, fragment, atau ID
  akun dari tautan ini. Tidak mengubah router/gate identitas, wajib ganti
  sandi, ataupun akses modul bisnis yang sudah berjalan.
- Warna NUSA, font sistem, tata letak responsif, tautan email, tanggal berlaku,
  dan 12 bagian penjelasan pengolahan data. Label draf pada banner, isi,
  metadata, dan footer telah dihapus.
- Tidak memuat font, pelacak, atau skrip pihak ketiga. Tautan sumber penyedia
  hanya dibuka bila pengguna memilihnya. Halaman memakai `no-store`;
  header `noindex` khusus draf telah dihapus. Tidak ada izin perangkat baru
  untuk membuka tautan.
- Jika pembuka browser gagal, aplikasi menampilkan pesan umum dan tombol
  dapat dicoba kembali. Jika browser terbuka tetapi server sedang offline,
  browser menangani kegagalan jaringan; tidak ada salinan kebijakan offline
  yang dibundel pada tahap ini.

## Sumber naskah

Naskah yang ditampilkan halaman berada di
[kebijakan-privasi-nusa.md](D:/nusasmpn2pp/resources/legal/kebijakan-privasi-nusa.md).
Laravel merender Markdown statis dengan HTML mentah dan tautan tidak aman
dinonaktifkan. Jangan menerima HTML/Markdown dari parameter URL atau pengguna.

[Arsip draf editorial](D:/nusasmpn2pp/docs/kebijakan-privasi-nusa-draf.md) dan
[catatan tinjauan internal](D:/nusasmpn2pp/docs/tinjauan-kebijakan-privasi-nusa.md)
tidak disajikan sebagai file publik; keduanya menyimpan riwayat penyusunan.
Status/tanggal/teks ringkasan halaman ada pada
[view publik](D:/nusasmpn2pp/resources/views/legal/kebijakan-privasi.blade.php).
Perbarui metadata dan naskah bersama-sama saat ada perubahan kebijakan.
Persetujuan naskah tidak menyatakan pengamanan foto, penghapusan otomatis,
atau prosedur layanan privasi yang belum tersedia telah diimplementasikan.
Keterangan kondisi tersebut tetap ada pada halaman publik.

## Penerapan ke server sekolah

Tidak ada deployment, push Git, perubahan database, atau migrasi foto yang
dilakukan dalam tahap ini. Setelah perubahan kode sudah masuk repository dan
di-pull pada server, jalankan dari direktori Laravel server:

```powershell
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Tahap ini tidak menambah paket Composer, migrasi database, atau aset Vite;
tidak memerlukan `npm run build` atau restart worker khusus untuk halaman
privasi. Jangan menjalankan migrasi penghapusan data untuk mengaktifkan halaman.
Ikuti prosedur deployment umum sekolah bila ada perubahan lain dalam pull yang
sama. Pastikan root situs server tetap menunjuk folder `public` Laravel.

Alamat yang diharapkan setelah deployment:

[Kebijakan Privasi NUSA](https://nusa.smpn2padangpanjang.sch.id/kebijakan-privasi).

Alamat produksi tersebut **belum diperiksa tersedia** dalam implementasi ini.
Uji dari browser tanpa login/incognito dan jaringan di luar sekolah. Halaman
harus 200 HTML, tidak mengarahkan ke login, tidak dibatasi Cloudflare Access/
geografis, dan menampilkan versi naskah yang benar. Halaman publik berarti
informasi kebijakan dapat dibaca siapa pun, bukan data pengguna dipublikasikan.

## Aplikasi Android

Dependensi baru pembuka tautan: `url_launcher` 6.3.3 beserta implementasi
platformnya; lockfile diperbarui tanpa upgrade paket lama. Tidak memakai
WebView dengan sesi/token login NUSA.

Untuk menguji perubahan, lakukan build ulang/pasang aplikasi baru, bukan
hanya hot reload karena ada plugin native baru. Contoh dari direktori mobile:

```powershell
flutter pub get
flutter run --dart-define=APP_ENV=development --dart-define=API_BASE_URL=https://nusa.smpn2padangpanjang.sch.id/api/v1/
```

Server sekolah harus sudah memuat rute baru. Untuk pengujian server Laravel
lokal dari emulator Android, gunakan URL API `http://10.0.2.2:8000/api/v1/`
dan pastikan server lokal berjalan pada port tersebut.

AAB produksi yang dibuat pada tahap signing **belum berisi tautan privasi
baru** dan tidak dibangun ulang pada tahap ini. Buat AAB baru sesuai
[panduan rilis](D:/nusasmpn2pp/docs/rilis-android-play-store.md) sebelum unggah.
Jika version code sebelumnya sudah dipakai di Play Console, naikkan angkanya.
Setelah kode tautan terpasang, pembaruan isi halaman di server tidak memerlukan
build ulang aplikasi selama alamat publik tidak berubah.

## Verifikasi lokal implementasi awal

- PHPUnit halaman privasi + autentikasi: **32 tes lulus, 112 assertion**.
- PHPUnit izin rute + tampilan: **10 tes lulus, 879 assertion**.
- Flutter tautan privasi + profil + alur aplikasi: **43 tes lulus**.
- Analyzer Flutter: **tidak ada issue**.
- APK debug berhasil dibangun; AAB release lama tidak ditimpa. Peringatan
  toolchain Java/KGP dari Firebase/komponen scanner tetap muncul, tanpa error
  build. Pembukaan browser nyata pada HP/emulator belum diuji end-to-end.
- Halaman lokal dibuka di browser: 12 bagian naskah tampil, logo termuat,
  status draf terlihat, dan lebar dokumen tidak melebihi viewport pada
  320, 360, serta 1280 piksel. Tautan diuji dengan pembuka palsu pada tes
  Flutter; tidak mengirim email atau data pengguna.

### Pembaruan label dan tanggal berlaku

Setelah persetujuan pengguna, subset PHPUnit privasi, autentikasi, izin rute,
dan izin tampilan diuji ulang: **43 tes lulus, 1.000 assertion**. Regression
memastikan tidak ada kata draf/draft di HTML publik, tanggal berlaku tampil,
dan keterangan risiko foto serta retensi yang belum otomatis tetap tersedia.
Perubahan ini hanya pada Laravel/naskah/dokumentasi; Flutter tidak diubah
atau dibangun ulang. Pengujian browser awal di atas dilakukan sebelum
penghapusan banner; produksi belum diverifikasi dari pekerjaan lokal ini.

## Yang masih menjadi gerbang rilis

Naskah telah disetujui pengguna, tetapi ketersediaan URL dan tautan hanyalah
implementasi akses. Tetapkan awal hitung retensi 5 tahun dan penghapusan/
cadangan, prosedur permintaan privasi serta perlindungan data anak, dan
tindak lanjuti risiko foto publik. Lengkapi Data Safety, verifikasi halaman
produksi, serta uji release/HP sesungguhnya. R02 belum dianggap ditutup
sepenuhnya oleh penghapusan label draf.

Rujukan: [Google Play User Data / Privacy Policy](https://support.google.com/googleplay/android-developer/answer/10144311?hl=en)
dan [url_launcher resmi Flutter](https://pub.dev/packages/url_launcher).
