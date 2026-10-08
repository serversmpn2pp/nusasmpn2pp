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
  hanya dibuka bila pengguna memilihnya. Halaman memakai `no-store` dan
  `no-transform` agar Cloudflare tidak menyamarkan email kontak;
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

Pemeriksaan publik 8 Oktober 2026 sebelum perbaikan email mendapat HTTP 200,
tetapi Cloudflare menyamarkan email dan menyisipkan skrip decode yang diblokir
CSP halaman. Hasil perbaikan melalui Cloudflare **belum diverifikasi setelah
deployment**, karena pekerjaan ini tidak mengubah server sekolah.
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

AAB produksi **sudah dibangun ulang pada 8 Oktober 2026, 11.51 WIB** dan
memuat tautan privasi Login/Profil. File lokal signing awal telah diganti
oleh [app-release.aab](D:/nusasmpn2pp/mobile/build/app/outputs/bundle/release/app-release.aab)
versi **1.0.0+1**, sekitar 87,5 MiB. Hash dan hasil pemeriksaan artefak baru
ada dalam [panduan rilis](D:/nusasmpn2pp/docs/rilis-android-play-store.md).
Tidak ada unggahan ke Play Console pada pekerjaan ini. Jika version code
sebelumnya sudah dipakai di Play Console, naikkan angkanya dan build ulang.
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

### Build ulang AAB setelah persetujuan pengguna

AAB release produksi berhasil dibuat pada 8 Oktober 2026, 11.51 WIB.
Analyzer bersih dan subset **43 tes Flutter** di atas diuji ulang, semuanya
lulus. Penanda tautan privasi Login/Profil dan URL API produksi ditemukan
pada AOT ketiga ABI; plugin pembuka tautan tersedia dalam DEX. Sertifikat
cocok dengan upload key sebelumnya; 14 ELF 64-bit lulus alignment 16 KB.
Perincian hash, peringatan Java, dan batas validasi ada di panduan rilis.
Tidak mengubah kode fitur/API atau melakukan deployment server.

### Perbaikan email kontak Cloudflare

Pada 8 Oktober 2026, header respons **khusus `/kebijakan-privasi`** diubah
menjadi `Cache-Control: no-store, no-transform` (Laravel dapat menambahkan
direktif `private`). Menurut
[dokumentasi Cloudflare](https://developers.cloudflare.com/waf/tools/scrape-shield/email-address-obfuscation/),
`no-transform` mencegah Email Address Obfuscation. Tidak membuka izin skrip
pada CSP, menambah JavaScript, mengubah naskah/alamat email, atau mematikan
perlindungan Cloudflare secara global. Tidak mengubah header login/API.

Subset PHPUnit privasi, autentikasi, izin rute, dan izin tampilan:
**44 tes lulus, 1.013 assertion**. Regression memastikan `no-transform` dan
`no-store` tersedia, CSP tetap sama, dua tautan `mailto:` langsung tersedia,
HTML asal tidak memuat penanda/skrip obfuscation, dan header login tidak
terkena `no-transform`. Tes ini memeriksa Laravel, bukan simulasi edge
Cloudflare atau bukti email benar-benar terkirim.

Setelah perubahan sudah di-pull pada server, gunakan perintah deployment
Laravel di atas. Buka ulang halaman tanpa login/incognito dan pastikan:

- Alamat `official@smpn2padangpanjang.sch.id` terbaca dan tautan membuka
  aplikasi email, bukan `/cdn-cgi/l/email-protection`.
- Header `Cache-Control` menyertakan `no-transform` dan `no-store`, serta
  CSP tetap membatasi skrip. Tidak ada `data-cfemail` atau
  `email-decode.min.js` pada HTML yang dikirim Cloudflare.
- Jika versi lama masih tersaji, periksa header/cache proxy; bila perlu
  bersihkan cache **hanya URL kebijakan privasi** dan uji kembali. Jangan
  melonggarkan CSP atau mematikan WAF/Tunnel sebagai penyelesaian.

Perubahan ini hanya backend Laravel; **tidak perlu build ulang APK/AAB**.
Bundle produksi 11.51 WIB di atas tetap dapat dipakai untuk membuka URL
yang sama. Tidak ada deployment atau perubahan konfigurasi Cloudflare
yang dilakukan dari workspace ini.

## Yang masih menjadi gerbang rilis

Naskah telah disetujui pengguna, tetapi ketersediaan URL dan tautan hanyalah
implementasi akses. Tetapkan awal hitung retensi 5 tahun dan penghapusan/
cadangan, prosedur permintaan privasi serta perlindungan data anak, dan
tindak lanjuti risiko foto publik. Lengkapi Data Safety, verifikasi halaman
produksi, serta uji release/HP sesungguhnya. R02 belum dianggap ditutup
sepenuhnya oleh penghapusan label draf.

Rujukan: [Google Play User Data / Privacy Policy](https://support.google.com/googleplay/android-developer/answer/10144311?hl=en)
dan [url_launcher resmi Flutter](https://pub.dev/packages/url_launcher).
