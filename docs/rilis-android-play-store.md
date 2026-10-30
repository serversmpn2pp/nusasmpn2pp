# Signing dan build rilis NUSA Android

Upload key dibuat dan disimpan oleh pengelola sekolah pada komputer pengembang,
bukan server Laravel. Kunci tersebut berbeda dari service-account Firebase.
Jangan memasukkan keystore, password, atau `key.properties` ke Git/chat.

## AAB terbaru — 1.0.6+7, 31 Oktober 2026

File distribusi:
[app-release-1.0.6-7.aab](D:/nusasmpn2pp/mobile/build/releases/app-release-1.0.6-7.aab).
Ukuran **97.225.765 byte** (97,2 MB / sekitar 92,7 MiB). Salinan bernama versi
identik dengan `mobile/build/app/outputs/bundle/release/app-release.aab`.
Semua AAB versi sebelumnya tetap tersimpan dan AAB 1.0.5+6 tidak berubah.

SHA-256:

```text
08f4277786d6bc623ec9f95da184691af456c99a51976f0688e092b706a76516
```

Checksum: `mobile/build/releases/app-release-1.0.6-7.aab.sha256`.
Sumber build: HEAD `b7a7a79`, ditambah kenaikan versi menjadi **1.0.6+7**
dan dokumentasi rilis. Package tetap `id.sch.smpn2padangpanjang.nusa`.

### Perubahan sejak AAB 1.0.5+6

- Login tablet small/medium memisahkan lebar foto dari lebar formulir.
  Portrait menampilkan foto selebar layar dengan tinggi terbatas, sedangkan
  landscape memakai panel foto dan formulir berdampingan pada ruang yang cukup.
- Formulir menyesuaikan ruang keyboard, pesan validasi, dan huruf besar.
  Isian serta pilihan tampilkan kata sandi tetap dipertahankan saat rotasi.
- Beranda tablet membatasi lebar isi, mengatur tinggi header landscape,
  jumlah kolom menu dan ukuran kartu presensi sesuai ruang yang tersedia.
- Label tablet lebih mudah dibaca dan navigasi bawah menyesuaikan ukuran
  huruf perangkat. Ikon asli, hak akses, dan tujuan menu tetap dipakai.

Ini pembaruan tampilan native, tanpa perubahan dependensi maupun API.
Perubahan backend monitoring/pameran di riwayat Git tidak diterapkan ke server
oleh pekerjaan build ini. Rincian desain dan ukuran pengujian ada di
[dokumentasi foto/tablet](tampilan-foto-sekolah-mobile.md#penyesuaian-tablet-small-dan-medium--28-oktober-2026).

### Pemeriksaan AAB 1.0.6+7

- Flutter **3.47.1 / Dart 3.13.1**; **914 tes Flutter lulus** dan analyzer
  tidak menemukan masalah. Tes mencakup tablet small/medium pada portrait,
  landscape, keyboard terbuka, huruf 200%, serta regresi HP dan fitur sebelumnya.
- Dependensi dipersiapkan dengan `pub get --offline --enforce-lockfile`;
  `pubspec.lock` tetap identik. Build memakai `APP_ENV=production` dan API
  `https://nusa.smpn2padangpanjang.sch.id/api/v1/`.
- Manifest: versi **1.0.6**, versionCode **7**, min SDK **24**, target SDK
  **36**, debug/backup/cleartext HTTP nonaktif.
- `bundletool validate`, validasi JAR, CRC/duplikasi ZIP, dan `jarsigner`
  lulus. Sertifikat upload cocok dengan AAB 1.0.5+6 serta fingerprint tercatat:
  `CA:E5:19:98:35:1F:48:D5:38:2C:F1:3C:74:CA:B6:67:CF:FB:C8:32:9B:D8:9F:47:70:0C:8C:B4:7B:27:34:05`.
- Penanda login tablet portrait/landscape, API produksi, dan fitur sebelumnya
  terdeteksi dalam ketiga `libapp.so`. Foto dan logo dalam AAB cocok byte demi
  byte dengan aset sumber; foto referensi lama tidak ikut dikemas.
- Bundle memakai `PAGE_ALIGNMENT_16K`; pemeriksa Android Studio memeriksa
  **14 library ELF 64-bit: 0 masalah** dan melewati 7 library 32-bit.
- APK universal diagnostik lulus `zipalign -v -c -P 16 4`. Manifestnya juga
  menunjukkan 1.0.6/7 dan ketiga library AOT identik dengan AAB baru. APK ini
  ditandatangani debug key standar untuk pemeriksaan lokal, bukan untuk diunggah.

Log utama ada di `mobile/build/*release-1.0.6-7.log`. Bukti manifest,
sertifikat, konten, ELF, dan APK ada di `mobile/build/aab-validation-1.0.6-7/`.
Pembuatan APK diagnostik sempat terhalang izin folder sementara sandbox;
pengulangan dengan folder sementara di workspace berhasil. Peringatan toolchain
Java, KGP, XML SDK dan font Cupertino tidak menggagalkan build. Peringatan
sertifikat self-signed/timestamp dan pembacaan streaming JAR konsisten dengan
rilis sebelumnya; verifikasi JAR berbasis berkas lulus. Pemeriksaan lokal tidak
mencakup pemasangan melalui Play atau runtime perangkat fisik 16 KB.

### Catatan rilis dan pengunggahan 1.0.6

Salin isi [catatan-rilis-1.0.6.txt](D:/nusasmpn2pp/docs/catatan-rilis-1.0.6.txt)
ke Release notes. Isinya **274 karakter** di dalam tag `<id>`.
Nama rilis: **1.0.6 (7)**.

Pada halaman **Open testing → Create new release**, gunakan **Upload** untuk
berkas AAB baru ini. Bila ingin merilis untuk penggunaan umum dan akses produksi
sudah disetujui, pilih **Production → Create new release**. Isi catatan rilis,
periksa ringkasan, dan lanjutkan alur review/publikasi di Publishing overview.
Panduan Google: [menyiapkan rilis](https://support.google.com/googleplay/android-developer/answer/9859348?hl=en).

AAB belum diunggah atau dipublikasikan oleh pekerjaan ini. VersionCode 7 dipilih
sebagai kelanjutan 6 yang sudah digunakan; riwayat unggahan Console tidak dibaca
langsung. Tidak ada deploy, migrasi, maupun perubahan data sekolah.

## Riwayat AAB — 1.0.5+6, 28 Oktober 2026

AAB ini belum memuat penyesuaian tablet. Perbaikan tersebut sudah dikemas pada
AAB 1.0.6+7 di atas.

File untuk pembaruan **closed testing — Alpha**:
[app-release-1.0.5-6.aab](D:/nusasmpn2pp/mobile/build/releases/app-release-1.0.5-6.aab).
Ukuran **97.200.254 byte** (97,2 MB / 92,7 MiB). Salinan versi ini identik
dengan keluaran `mobile/build/app/outputs/bundle/release/app-release.aab`.
AAB 1.0.4+5 dan versi sebelumnya tetap tersimpan sebagai riwayat.

SHA-256:

```text
7e8232432b0f32a49db17d4b58a15ba359b7ce1157de60fc076e472a6759224a
```

Checksum juga tersedia di
`mobile/build/releases/app-release-1.0.5-6.aab.sha256`.
Sumber: HEAD `613edb7`, ditambah kenaikan `mobile/pubspec.yaml` menjadi
**1.0.5+6**. Package tetap `id.sch.smpn2padangpanjang.nusa`; sertifikat upload
telah dibandingkan dan cocok dengan AAB 1.0.4+5.

### Perubahan sejak AAB 1.0.4+5

- Login dan beranda memakai foto sekolah serta identitas NUSA yang lebih jelas.
- Login menyesuaikan tinggi foto dengan ruang layar agar seluruh pilihan muat
  tanpa scroll pada ukuran huruf normal. Keyboard memakai header ringkas,
  latar sekolah yang lebih terlihat, dan footer tersembunyi sementara. Isian
  dan pilihan tampilkan kata sandi tetap dipertahankan.
- Akses Cepat menjadi satu baris pada ukuran normal, dengan ikon asli dan label
  yang lebih mudah dibaca. Nama penuh tetap tersedia melalui tooltip/pembaca
  layar; susunan mengikuti ruang serta ukuran huruf perangkat.
- Ini pembaruan antarmuka mobile. Fitur keamanan ujian, pratinjau soal, dan
  perhitungan presensi yang sudah dirilis dalam 1.0.4+5 tetap tercakup.
  Perubahan backend monitoring TV di riwayat Git tidak dikemas sebagai fitur
  Android baru dan tidak diterapkan ke server oleh pekerjaan build ini.

### Pemeriksaan AAB 1.0.5+6

- Flutter **3.47.1 / Dart 3.13.1**; seluruh **893 tes Flutter lulus**, dan
  analyzer tidak menemukan masalah.
- Dependensi disiapkan dengan `pub get --offline --enforce-lockfile`, tanpa
  perubahan lockfile atau upgrade pustaka. Build memakai `APP_ENV=production`
  dan API `https://nusa.smpn2padangpanjang.sch.id/api/v1/`.
- Manifest final: versi **1.0.5**, version code **6**, min SDK **24**, target SDK
  **36**, bukan debug, backup nonaktif, dan cleartext HTTP nonaktif.
- `bundletool validate`, `jar --validate`, CRC/duplikasi ZIP, serta verifikasi
  `jarsigner` lulus. Fingerprint sertifikat upload sama dengan rilis sebelumnya.
- Kedua foto aktif di dalam AAB cocok byte demi byte dengan aset sumber.
  Foto referensi lama yang tidak didaftarkan tidak ikut dikemas. Penanda API
  produksi, antarmuka baru, dan fitur utama sebelumnya terdeteksi pada tiga
  library AOT `libapp.so`.
- Konfigurasi bundle memakai `PAGE_ALIGNMENT_16K`. Pemeriksa Android Studio
  memeriksa **14 ELF 64-bit: 0 masalah**; 7 library 32-bit dilewati oleh pemeriksa
  tersebut. APK universal diagnostik juga lulus `zipalign -v -c -P 16 4`.
  APK diagnostik memakai debug key standar untuk pemeriksaan lokal saja;
  file distribusi adalah AAB di folder `build/releases/`.

Log pub, tes, analyzer dan build ada di `mobile/build/*release-1.0.5-6.log`.
Bukti manifest, sertifikat, penanda AOT, aset, dan native alignment ada di
`mobile/build/aab-validation-1.0.5-6/`. Peringatan Java/KGP dan pencarian font
Cupertino dari toolchain tetap muncul tanpa menggagalkan build. Pemeriksaan
lokal tidak mencakup pemasangan AAB melalui Play atau runtime HP fisik 16 KB.

### Catatan rilis 1.0.5 dan pengunggahan

Salin isi [catatan-rilis-1.0.5.txt](D:/nusasmpn2pp/docs/catatan-rilis-1.0.5.txt)
ke Release notes. Isinya **313 karakter** di dalam tag `<id>`.

Pada track **Closed testing → Alpha**, buat rilis baru dan unggah
`app-release-1.0.5-6.aab`; nama rilis dapat memakai **1.0.5 (6)**. Isi catatan
rilis, periksa ringkasan, lalu kirim untuk review melalui Publishing overview.
Jika Managed publishing aktif, publikasikan sesudah disetujui. Pertahankan
track dan daftar tester yang sedang digunakan. Sesuaikan screenshot login serta
beranda dengan tampilan versi baru ketika perubahan ini dipublikasikan.

Version code **6** harus belum pernah digunakan di Console. Riwayat Console
tidak dibuka pada pekerjaan ini; AAB belum diunggah atau dipublikasikan.
Pekerjaan ini tidak mengubah konfigurasi server, migrasi, atau data sekolah.

## Riwayat AAB — 1.0.4+5, 25 Oktober 2026

File untuk pembaruan **closed testing — Alpha**:
[app-release-1.0.4-5.aab](D:/nusasmpn2pp/mobile/build/releases/app-release-1.0.4-5.aab).
Ukuran **95.911.238 byte** (sekitar 95,9 MB / 91,5 MiB). Salinan bernama versi
ini identik dengan keluaran `mobile/build/app/outputs/bundle/release/app-release.aab`.
Seluruh AAB versi lama tetap tersimpan di `mobile/build/releases/`.

SHA-256:

```text
51eba4ded39e2c106ce2ae557a5b924a9279fb18d4e0bce067ba216805e233b2
```

Sumber: HEAD `3f1d612`, dengan kenaikan versi pada `mobile/pubspec.yaml`
menjadi **1.0.4+5**. Package tetap `id.sch.smpn2padangpanjang.nusa` dan
sertifikat upload cocok dengan AAB 1.0.3+4.

### Isi pembaruan dan verifikasi

- Penguatan pengawasan ujian, pemeriksaan proteksi layar, pengecualian pemilih
  berkas berizin server, serta pemulihan draf jawaban terenkripsi yang mengikuti
  status, batas waktu dan versi jawaban server.
- Pratinjau Siswa native pada bank/detail/paket soal; komponen soal dipakai
  bersama dengan pengerjaan siswa. Gambar per bagian, tabel dan rumus ikut
  ditampilkan, disertai pembesar gambar dan penanganan kegagalan/timeout.
- Penyempurnaan laporan presensi siswa: hari wajib per masa keanggotaan,
  pengecualian PJJ, rincian persentase, serta tampilan ketika tidak ada hari wajib.
- Build memakai Flutter 3.47.1 / Dart 3.13.1, dependensi terkunci
  (`pub get --offline --enforce-lockfile`, lalu `--no-pub`), `APP_ENV=production`
  dan API HTTPS sekolah. Tidak mengganti dependensi atau upload key.
- Manifest final: versi **1.0.4**, version code **5**, min SDK **24**, target
  SDK **36**, bukan debug dan `allowBackup=false`. Penanda API produksi,
  pratinjau/pembesar, nol toleransi, draf, versi jawaban, presensi dan tautan
  kebijakan ditemukan pada ketiga library AOT `libapp.so`.
- `bundletool validate` CLI 1.18.3, `jar --validate`, integritas CRC/duplikasi
  ZIP, dan verifikasi `jarsigner` lulus. Fingerprint sertifikat upload sama
  dengan rilis sebelumnya.
- Konfigurasi meminta `PAGE_ALIGNMENT_16K`. Pemeriksa Android Studio memeriksa
  **14 ELF 64-bit: 0 masalah**. APK universal diagnostik dari AAB juga lulus
  `zipalign -v -c -P 16 4`. APK diagnostik memakai debug key standar dan hanya
  berada di folder validasi; file distribusi adalah AAB bertanda tangan upload key.
- Pengujian sumber sebelum build: analyzer bersih dan **865 tes Flutter lulus**;
  regresi gabungan backend **219 tes / 2.820 assertion lulus**. Temuan lama
  alfa STS sudah ditelusuri sebagai harapan tes salinan rekap yang usang;
  lihat [verifikasi alfa rapor STS](verifikasi-alfa-rapor-sts.md).

Log pub/build/tes/analyzer tersedia di `mobile/build/*release-1.0.4-5.log`;
manifest, konfigurasi, checksum, pemeriksaan sertifikat, penanda AOT dan bukti
validasi native ada di `mobile/build/aab-validation-1.0.4-5/`.
Peringatan toolchain Java/KGP dan font Cupertino masih ada tanpa menggagalkan
build. Pemeriksaan lokal ini tidak mencakup runtime HP fisik/perangkat 16 KB
atau APK split aktual yang kelak didistribusikan Google Play.

### Catatan rilis dan pembaruan closed testing

Salin isi [catatan-rilis-1.0.4.txt](D:/nusasmpn2pp/docs/catatan-rilis-1.0.4.txt)
ke Release notes. Isinya 350 karakter di dalam tag `<id>`, sesuai batas 500
karakter per bahasa pada [panduan rilis Google Play](https://support.google.com/googleplay/android-developer/answer/9859348?hl=en).

1. Pilih aplikasi NUSA, lalu **Test and release → Testing → Closed testing**.
2. Pada track **Alpha** yang sedang dipakai, pilih **Manage track → Create new release**.
3. Unggah `app-release-1.0.4-5.aab`, beri nama rilis **1.0.4 (5)** dan isi catatan rilis.
4. Pilih **Next**, periksa ringkasan dan selesaikan error yang ditampilkan.
5. Simpan/kirim untuk review melalui **Publishing overview** sesuai tombol yang tersedia.
   Setelah disetujui, publikasikan perubahan jika Managed publishing diaktifkan.
6. Tester memperbarui NUSA melalui Play Store pada akun Google yang terdaftar.

Gunakan track dan daftar tester yang sama; rilis terpublikasi sebelumnya tetap
menjadi riwayat. Bila masih ada draft/perubahan rilis yang tertunda, selesaikan
atau sunting rilis tertunda tersebut sebelum membuat rilis lain.
Version code **5** harus belum pernah digunakan di Console. Riwayat Console
tidak dibuka pada pekerjaan ini dan AAB belum diunggah/dipublikasikan.
Syarat tester adalah keikutsertaan yang berkelanjutan; lihat
[ketentuan pengujian akun personal](https://support.google.com/googleplay/android-developer/answer/14151465?hl=en).

Pastikan backend produksi memuat API keamanan/draf/pratinjau dan presensi
terbaru. Scanner tanpa sesi serta monitoring TV adalah perubahan backend
tersendiri; pemasangannya mengikuti [panduan scanner](perangkat-scanner.md),
[monitor siswa](monitoring-tv-siswa.md) dan [monitor ujian](monitoring-tv-ujian.md).
Pekerjaan build ini tidak menerapkan kode, migrasi atau perubahan data ke server.

## Riwayat AAB — 1.0.3+4, 23 Oktober 2026

File untuk diunggah ke pembaruan **closed testing — Alpha**:
[app-release-1.0.3-4.aab](D:/nusasmpn2pp/mobile/build/releases/app-release-1.0.3-4.aab).
Salinan bernama versi ini identik dengan keluaran
`mobile/build/app/outputs/bundle/release/app-release.aab` yang selesai pada
**06.25.44 WIB**. Ukuran **95.782.125 byte** (sekitar **95,8 MB / 91,3 MiB**).
Versi sebelumnya 1.0.0+1, 1.0.1+2, dan 1.0.2+3 tetap disimpan di
`mobile/build/releases/`; jangan mengunggah berkas lama untuk pembaruan ini.

SHA-256:

```text
4173D5B62928527E36434A92C5FA34D6A1469D70487F9FDBD39F00303F592315
```

### Isi dan verifikasi build

- Source HEAD `634d78c`, dengan kenaikan versi di `mobile/pubspec.yaml` menjadi
  **1.0.3+4**. Package tetap `id.sch.smpn2padangpanjang.nusa`; manifest final
  memakai min SDK **24**, target SDK **36**, bukan debug, `allowBackup=false`,
  dan `extractNativeLibs=false`.
- Flutter **3.47.1 / Dart 3.13.1**; build memakai perintah produksi di bawah,
  dependensi terkunci tanpa upgrade (`pub get --offline --enforce-lockfile`,
  kemudian `--no-pub`), API HTTPS sekolah, dan upload key yang sama dengan
  1.0.2+3. Keystore/password tidak diganti atau dicetak.
- Memuat pembaruan native Humas: FAQ/bantuan, laporan kinerja semester,
  direktori kontak, publikasi/persetujuan, program kerja, komite, kemitraan,
  portofolio akreditasi, aset promosi, media resmi, kliping, prestasi, dan alumni
  sesuai izin akun. Ketiga `libapp.so` memuat seluruh **13 penanda Humas** serta
  penanda API produksi, STS, privasi, dan permintaan penghapusan akun.
- Analyzer bersih; seluruh **800 tes Flutter lulus**. Subset API Humas/menu/
  notifikasi: **148 tes / 5.515 assertion lulus**. Tes memakai fixture sintetis
  dan SQLite di memori, bukan database sekolah atau pengguna produksi.
- `bundletool validate` CLI **1.18.3**, `jar --validate`, pemeriksaan CRC/
  duplikasi ZIP, dan `jarsigner -verify` lulus. Fingerprint sertifikat upload
  cocok dengan AAB 1.0.2+3.
- Konfigurasi AAB meminta `PAGE_ALIGNMENT_16K`; seluruh **14 ELF 64-bit** lolos
  pemeriksa Android Studio untuk LOAD/RELRO (**0 masalah**). Metrik mentah akhir
  RELRO pada skrip Python hanya informasional, bukan penentu kelulusan.
- APK universal **diagnostik** yang dibuat dari AAB lulus
  `zipalign -v -c -P 16 4`. Berkas `diagnostic-16kb.apks` dan
  `diagnostic-16kb.apk` di `mobile/build/aab-validation-1.0.3-4/` memakai
  **debug key standar**, bukan artefak rilis; jangan unggah/pasang untuk
  distribusi. Pemeriksaan ini bukan uji runtime perangkat 16 KB atau validasi
  APK split aktual dari Google Play.
- Log build/tes tersedia di `mobile/build/build-release-1.0.3-4.log` dan
  `mobile/build/test-release-1.0.3-4.log`; bukti validasi artefak ada di
  `mobile/build/aab-validation-1.0.3-4/`.

Peringatan Java/KGP plugin `firebase_core`/`mobile_scanner`, font Cupertino,
sertifikat self-signed/tanpa timestamp, dan pembaca streaming JDK masih muncul
tanpa menggagalkan build/verifikasi. Tidak mengubah dependensi atau mengurangi
perlindungan aplikasi untuk menyembunyikan peringatan.

### Sebelum mengunggah versi ini

1. Pastikan backend produksi memuat API/menu Humas terbaru dan migrasi Humas
   `2026_10_22_000001` sampai `2026_10_22_000004` yang dijelaskan dalam
   [panduan mobile Humas](D:/nusasmpn2pp/docs/mobile-humas.md). Build ini tidak
   melakukan deployment, migrasi, seeder, restart worker, atau perubahan server.
2. Pada track **closed testing — Alpha yang sama**, buat rilis baru dan unggah
   salinan `app-release-1.0.3-4.aab`. Version code **4** harus belum pernah
   digunakan di Play Console; riwayat Console tidak diakses dalam pekerjaan ini.
   Jangan mengakhiri track atau menghapus daftar tester untuk melakukan update.
3. Setelah disetujui/dipublikasikan, uji update melalui Play Store: login,
   akses Humas menurut peran, kamera/galeri/QR, unggahan/unduhan, formulir,
   notifikasi, serta ekspor/cetak. Kamera/push dan perilaku HP nyata belum diuji
   dari AAB ini. Tinjau deklarasi Data Safety jika cakupan data berubah.

Contoh catatan rilis:

```text
<id>
Pembaruan layanan Humas dengan menu native dan navigasi yang lebih konsisten.
Penambahan FAQ, laporan kinerja semester, direktori kontak, dan alur publikasi.
Akses program kerja, komite, kemitraan, akreditasi, aset promosi, media resmi, kliping, prestasi, dan alumni sesuai izin akun.
Penyempurnaan formulir, unggahan lampiran, pencarian, dan riwayat perubahan.
</id>
```

Belum mengunggah atau menerbitkan AAB ini di Play Console. Pemeriksaan lokal
bukan jaminan persetujuan reviewer atau pengganti pre-launch report/uji perangkat.

## Riwayat AAB — 1.0.2+3, 13 Oktober 2026

File untuk diunggah:
[app-release-1.0.2-3.aab](D:/nusasmpn2pp/mobile/build/releases/app-release-1.0.2-3.aab).
Salinan ini identik dengan keluaran `mobile/build/app/outputs/bundle/release/app-release.aab`
yang selesai dibangun pada **08.36 WIB**. Ukuran **94.936.750 byte**
(sekitar **94,9 MB / 90,5 MiB**). Build sebelumnya tetap disimpan sebagai
`mobile/build/releases/app-release-1.0.1-2.aab`; bagian build lama di bawah
merupakan riwayat, bukan file yang perlu diunggah sekarang.

SHA-256:

```text
70CD1E5E21540E0A2A43F3B5CDF2E2AC40E77A95A24F82DF1BADA4662F8D3C68
```

- Manifest final: package `id.sch.smpn2padangpanjang.nusa`, version name
  **1.0.2**, version code **3**, min SDK **24**, target SDK **36**.
  Bukan debug; `allowBackup=false`, `extractNativeLibs=false`.
- Build memakai `APP_ENV=production`, API HTTPS sekolah yang sama, dependensi
  terkunci (`--no-pub`), dan upload key lama. Fingerprint SHA-256 sertifikat
  cocok dengan build 1.0.1+2; tidak mengganti keystore/password.
- Memuat pusat Ujian & Asesmen, Rapor/Leger STS, publikasi rapor STS, pembaca
  rapor/lampiran perilaku untuk penerima yang berhak, penyempurnaan tampilan
  STS/presensi, serta tab **Jadwal/Riwayat** siswa dan orang tua.
  Penanda route STS, tab ujian, URL produksi, dan tautan privasi/penghapusan
  ditemukan pada ketiga library AOT `libapp.so`.
- Analyzer bersih; seluruh **537 tes mobile** lulus. **76 tes API terkait /
  984 assertion** lulus memakai SQLite di memori dan data contoh, tanpa
  menguji/mengubah database produksi atau mengirim notifikasi pengguna nyata.
- `bundletool validate`, `jar --validate`, ZIP CRC/duplikasi, dan
  `jarsigner -verify` lulus. Konfigurasi meminta `PAGE_ALIGNMENT_16K`;
  **14 ELF 64-bit** lolos pemeriksa LOAD/RELRO Android Studio (0 masalah).
- APK universal diagnostik dari AAB juga lolos `zipalign -v -c -P 16 4`.
  File di `mobile/build/aab-validation-1.0.2-3/diagnostic-universal.*`
  memakai **debug key**, hanya untuk pemeriksaan alignment; jangan diunggah
  atau digunakan sebagai APK rilis. Ini bukan uji runtime 16 KB atau
  pemeriksaan APK split aktual dari Google Play.

Peringatan toolchain KGP/Java dan font Cupertino masih muncul tanpa error
build. Peringatan sertifikat self-signed/tanpa timestamp, atribut POSIX,
dan pembaca `JarInputStream` pada JDK 25 juga tetap muncul; tanda tangan
terverifikasi dan bundletool berhasil memvalidasi artefak final.

### Langkah berikutnya untuk versi ini

1. Pastikan backend produksi sudah memuat API/menu terbaru dan migration
   publikasi STS. Lihat [pemasangan publikasi STS](D:/nusasmpn2pp/docs/publikasi-rapor-sts.md).
   Build AAB ini tidak melakukan deployment, migrasi, atau restart worker.
2. Buat rilis baru **internal testing** dan unggah salinan AAB bernama versi
   di atas. Version code 3 harus belum pernah dipakai di Play Console;
   riwayat Console tidak diperiksa dari komputer ini.
3. Uji update melalui Play pada HP tester: login, kamera/push, ujian,
   akses rapor sesuai penerima, lampiran, dan ekspor PDF. Pemeriksaan artefak
   bukan pengganti uji perangkat, pre-launch report, atau deklarasi kebijakan.

Contoh release notes:

```text
<id>
Pembaruan Ujian & Asesmen, pelaksanaan, pemantauan, dan hasil.
Ujian siswa dan orang tua kini dipisahkan menjadi Jadwal dan Riwayat.
Rapor dan Leger STS tersedia di mobile, termasuk publikasi rapor serta lampiran perilaku sesuai izin akun.
Penyempurnaan tampilan Lampiran Perilaku STS dan Pengecualian Presensi.
</id>
```

Belum mengunggah atau menerbitkan aplikasi di Google Play Console.

## Konfigurasi lokal

File `mobile/android/key.properties` memuat empat properti:

```properties
storePassword=GANTI_DENGAN_PASSWORD_LOKAL
keyPassword=GANTI_DENGAN_PASSWORD_LOKAL
keyAlias=upload
storeFile=D:/NUSA-Keys/nusa-upload.jks
```

Gunakan password dan alias kunci yang sebenarnya. Path dengan `/` dapat dipakai
di Windows; jika menggunakan `\`, tulis sebagai `\\` dalam file properties.
Path relatif dihitung dari folder `mobile/android`.

`mobile/android/.gitignore` mengecualikan `key.properties`, `*.jks`, dan
`*.keystore`. Simpan keystore di luar repository dan buat cadangan terlindungi
beserta password melalui prosedur sekolah. Jangan mengganti kunci setiap build.

## Perilaku build

- Debug tetap memakai debug key Android dan tidak memerlukan upload key.
- Release hanya memakai konfigurasi upload key, tidak pernah fallback ke debug.
- Release berhenti jika konfigurasi/properti/keystore tidak tersedia. Password
  dan alias yang tidak cocok juga membuat proses penandatanganan gagal.
- Task `:app:validateNusaReleaseSigning` memeriksa kelengkapan konfigurasi tanpa
  mencetak password. Validasi keystore/password terjadi saat penandatanganan.
- Override opsional Gradle `-PnusaSigningProperties=<path>` tersedia untuk CI
  atau pengujian konfigurasi yang hilang; tanpa override memakai `key.properties`.

## Build produksi

Dari `D:\nusasmpn2pp\mobile`:

```powershell
flutter pub get
flutter analyze --no-pub
flutter build appbundle --release --no-pub --dart-define=APP_ENV=production --dart-define=API_BASE_URL=https://nusa.smpn2padangpanjang.sch.id/api/v1/
```

Hasil: `mobile/build/app/outputs/bundle/release/app-release.aab`.
Versi dan version code berasal dari `mobile/pubspec.yaml`. Tingkatkan angka
setelah `+` sebelum mengunggah versi berikutnya; jangan memakai ulang version
code yang sudah digunakan di Play Console.

Untuk APK release yang dipasang langsung pada HP uji:

```powershell
flutter build apk --release --no-pub --dart-define=APP_ENV=production --dart-define=API_BASE_URL=https://nusa.smpn2padangpanjang.sch.id/api/v1/
```

APK ini memakai upload key. Instalasi debug lama dengan package yang sama tidak
dapat ditimpa jika sertifikatnya berbeda. Jangan menghapus aplikasi saat masih
ada jawaban/unggahan/jurnal ujian lokal yang belum tersinkron. AAB bukan file
yang dapat dipasang langsung seperti APK.

## Google Play

Aktifkan Play App Signing pada proses rilis pertama dan unggah AAB bertanda
tangan upload key ke **internal testing** terlebih dahulu. Google Play memakai
app signing key untuk APK yang didistribusikan; sertifikatnya dapat berbeda
dari sertifikat upload key. Jika suatu layanan Firebase/Google memakai
pembatasan sertifikat, daftarkan fingerprint app signing dari Play Console,
bukan hanya fingerprint debug/upload.

Build yang berhasil belum berarti siap rilis publik. Kebijakan privasi dalam
aplikasi dan URL publik, deklarasi Data Safety/target usia, akun demo reviewer,
server produksi, pemeriksaan 16 KB artefak final, serta uji HP nyata tetap
merupakan gerbang rilis. Tidak ada upload ke Play Console atau perubahan
database/server yang dilakukan oleh perintah build.

Referensi: [Flutter Android release](https://docs.flutter.dev/deployment/android)
dan [Play App Signing](https://support.google.com/googleplay/android-developer/answer/9842756).

## Bukti verifikasi 8 Oktober 2026

- AAB release berhasil dibangun menggunakan `APP_ENV=production` dan HTTPS sekolah.
- `jarsigner -verify` menyatakan signature valid; sertifikat bukan Android Debug.
- Build release tanpa konfigurasi signing ditolak oleh `preReleaseBuild` dengan
  pesan yang jelas; `preDebugBuild` tetap berhasil tanpa upload key. Tidak ada
  pemindahan atau perubahan file keystore/password selama pengujian.
- Analyzer: tidak ada issue. Kode Dart dan backend tidak diubah pada tahap ini.
- Seluruh 14 library ELF 64-bit (arm64-v8a/x86_64) dalam AAB memiliki alignment
  segmen LOAD minimal 16 KB dan offset/alamat yang sesuai. Ini bukan pengganti
  pemeriksaan ZIP alignment APK hasil Google Play atau uji perangkat 16 KB.
- Build masih menampilkan peringatan toolchain Java/Kotlin/SDK, deprecation,
  serta referensi font Cupertino yang tidak dibundel. Tidak ada error build;
  pengujian visual/perilaku artefak release belum dilakukan pada HP fisik.
- AAB belum diunggah ke Google Play; privasi/Data Safety dan uji penerimaan
  tetap belum dinyatakan selesai.

SHA-256 AAB signing awal (sudah diganti oleh hasil build ulang di bawah):

```text
42D3E0B24B3679B466C99084D59FDFDB70B8F09154F13A115BE298E4269C8A8A
```

## Perubahan setelah AAB signing dibuat

Pada 8 Oktober 2026 tautan kebijakan privasi ditambahkan ke login/Profil
Flutter dan halaman publik Laravel dibuat. Pengguna kemudian menyetujui
naskah dan label draf halaman dihapus. AAB signing awal dengan hash di atas
belum memuat tautan tersebut; file lokalnya telah diganti hasil build ulang
yang diverifikasi di bawah. Detail deployment dan uji akses ada di
[panduan halaman privasi](D:/nusasmpn2pp/docs/halaman-kebijakan-privasi-nusa.md).

## Build ulang dengan tautan privasi — 8 Oktober 2026, 11.51 WIB

Atas permintaan pengguna, AAB release berhasil dibangun ulang dengan perintah
produksi di atas, memakai dependensi terkunci (`--no-pub`) dan upload key
lokal yang sama. Tidak mengubah kode aplikasi, versi, backend, database,
kredensial, atau file kunci. Tidak ada unggahan ke Play Console pada tahap ini.

- File: [app-release.aab](D:/nusasmpn2pp/mobile/build/app/outputs/bundle/release/app-release.aab).
- Ukuran: **91.721.656 byte**, sekitar **87,5 MiB / 91,7 MB**. Ini ukuran
  bundle, bukan perkiraan ukuran unduhan APK per perangkat dari Google Play.
- Package: `id.sch.smpn2padangpanjang.nusa`; versi tetap **1.0.0+1**.
  Manifest hasil build: min SDK 24, target SDK 36.
- Konfigurasi build: `APP_ENV=production`, API
  `https://nusa.smpn2padangpanjang.sch.id/api/v1/`.
- Analyzer bersih; **43 tes Flutter** tautan privasi, Profil, dan alur
  aplikasi lulus. Ini subset, bukan pengujian ulang seluruh suite.
- Penanda tautan Login/Profil dan URL API produksi ditemukan di ketiga
  library AOT `libapp.so` (arm64-v8a, armeabi-v7a, x86_64). Kanal plugin
  `url_launcher_android` ditemukan dalam DEX bundle.
- `jarsigner -verify` menghasilkan `jar verified`; fingerprint sertifikat
  SHA-256 cocok dengan AAB signing awal, bukan Android Debug.
- Seluruh **14 library ELF 64-bit** lulus pemeriksaan LOAD alignment minimal
  16 KB dan kesesuaian offset/alamat. `jar --validate --file` lulus tanpa
  pesan; tidak ada duplikasi nama ZIP dan hanya satu manifest signing.

SHA-256 **AAB 8 Oktober 2026** (lihat pembaruan 12 Oktober di bawah):

```text
01276DCDE5E2B46BC424421FDBB665C4A1C2D1456C969E4A1ACFA16ECAC82B20
```

Peringatan build Java native access, KGP `firebase_core`/`mobile_scanner`,
dan font Cupertino tetap muncul, tanpa kegagalan build. Verifikasi JDK 25
juga memperingatkan sertifikat self-signed/chain tanpa CA, timestamp tidak
tersedia, atribut POSIX, serta perbedaan pembacaan `JarInputStream`.
Manifest signing pada bundle ini berada di akhir arsip. Itu menjelaskan
mengapa pembaca streaming tidak mengenalinya: menurut
[dokumentasi JarInputStream JDK 25](https://docs.oracle.com/en/java/javase/25/docs/api/java.base/java/util/jar/JarInputStream.html),
pembaca tersebut memerlukan manifest di awal. Penjelasan ini adalah
inferensi dari urutan arsip dan dokumentasi, bukan hasil penerimaan Google
Play. [Dokumentasi jarsigner](https://docs.oracle.com/en/java/javase/25/docs/specs/man/jarsigner.html)
membedakan peringatan dari kegagalan verifikasi. Jangan mengubah atau
menandatangani ulang bundle secara manual hanya untuk menyembunyikan pesan.

Validasi Google Play/bundletool CLI, ZIP alignment APK hasil distribusi,
uji HP release, dan ketersediaan halaman privasi produksi belum diverifikasi
pada tahap ini. JAR bundletool yang tersedia di cache Gradle bukan executable
CLI (`no main manifest attribute`); tidak mengklaim validasi bundletool lulus.
Gerbang privasi/Data Safety dan perlindungan foto publik tetap berlaku.
Jika version code **1** sudah pernah digunakan di Play Console, naikkan
build number dan buat AAB baru sebelum unggah; build ulang ini tidak menaikkan
version code otomatis.

## Build 1.0.1+2 — 12 Oktober 2026, 16.18 WIB

Hasil final: [app-release.aab](D:/nusasmpn2pp/mobile/build/app/outputs/bundle/release/app-release.aab).
Ukuran **91.880.876 byte** (sekitar 87,6 MiB / 91,9 MB); SHA-256:

```text
D85AF7C0482F4312AA1E70B31A753E111ABB4B025090F71BEC3A62DE482B614A
```

- Version name **1.0.1**, version code **2**, package tetap
  `id.sch.smpn2padangpanjang.nusa`. Code 1 sudah dipakai dalam internal testing.
- Manifest AAB: min SDK **24** (Android 7.0), target SDK **36** (Android 16),
  bukan debug; `allowBackup=false`, `extractNativeLibs=false`.
  Target 36 sesuai [ketentuan target API Google Play saat pemeriksaan](https://support.google.com/googleplay/android-developer/answer/11926878?hl=en).
- Memuat [Lampiran Perilaku STS native](D:/nusasmpn2pp/docs/lampiran-perilaku-sts.md),
  tautan kebijakan privasi, serta permintaan penghapusan pada Login/Profil.
  Ketujuh penanda route/link/URL produksi ditemukan pada ketiga `libapp.so`.
- Kunci upload sama; `jarsigner -verify` menghasilkan `jar verified`.
  Fingerprint SHA-256 sertifikat identik dengan AAB lama, bukan Android Debug:
  `CA:E5:19:98:35:1F:48:D5:38:2C:F1:3C:74:CA:B6:67:CF:FB:C8:32:9B:D8:9F:47:70:0C:8C:B4:7B:27:34:05`.
- Validador **bundletool CLI 1.18.3** resmi Google berhasil membaca/validasi AAB.
  CLI diunduh dari rilis resmi ke `.dart_tool`, bukan dependensi baru aplikasi.
  `jar --validate` dan pemeriksaan CRC/duplikasi ZIP lulus.
- Konfigurasi AAB meminta `PAGE_ALIGNMENT_16K`. Semua 14 ELF 64-bit memiliki
  LOAD alignment minimal 16 KB dengan offset/alamat sesuai.
- Pemeriksa ELF Android Studio terpasang (`PageAlignUtilsKt`, melalui
  `scripts/VerifyAabElf.java`) memeriksa 14 ELF 64-bit: **0 masalah**, termasuk
  LOAD dan RELRO; 7 library ABI 32-bit dilewati sesuai perilaku alat tersebut.
  Penghitungan mentah akhir RELRO pada `scripts/verify-aab-native.py` merupakan
  indikator tambahan, bukan penentu kelulusan: pemeriksa lengkap juga membaca
  section headers/susunan seluruh LOAD agar padding/segmen penuh tidak menjadi
  alarm palsu. Tidak menambal header ELF atau memodifikasi library prebuilt.
- APK universal diagnostik yang dibuat dari AAB dengan bundletool lulus
  `zipalign -v -c -P 16 4`: `Verification successful`. APK ini memakai kunci
  **debug** untuk diagnostik lokal saja, bukan artefak distribusi/rilis Play.
  Ini bukan pemeriksaan APK split aktual yang nanti didistribusikan Google Play,
  juga bukan pengganti [uji runtime perangkat 16 KB](https://developer.android.com/guide/practices/page-sizes).
- **62 tes backend / 1.486 assertion** dan **31 tes mobile** relevan lulus.
  Analyzer Flutter dan format PHP lulus. Ini subset, bukan seluruh suite.
  Tes memakai data contoh dan SQLite di memori, tidak data sekolah asli.
- GET HTTPS publik `/kebijakan-privasi` dan `/penghapusan-akun` menghasilkan
  **HTTP 200** tanpa login pada pemeriksaan ini; kontak privasi dapat dibaca,
  tidak terhalang halaman challenge Cloudflare. Bukan jaminan akses global
  terus-menerus atau audit kesesuaian isi kebijakan.
- AAB lama dipertahankan di `mobile/build/releases/app-release-1.0.0-1.aab`.
  Jangan menaruh salinan AAB lama di folder keluaran `bundle/release`: Flutter
  bisa melaporkan file pertama yang cocok, bukan AAB baru. Versi/hash di atas
  dibaca dari file `app-release.aab` final menggunakan bundletool dan hash file.

Perintah build tetap memakai `--no-pub`, `APP_ENV=production`, dan API HTTPS
sekolah. Tidak mengganti keystore, password, dependensi aplikasi, Flutter SDK,
atau menonaktifkan perlindungan ujian. Peringatan KGP plugin, Java/SDK dan font
Cupertino masih tampil; build selesai tanpa error.

### Sebelum unggah / rilis

1. Deploy controller API STS baru, route, dan konfigurasi menu pada backend,
   lalu segarkan cache/layanan sesuai prosedur sekolah. Belum dilakukan pada
   tahap ini. AAB lama tidak mempunyai layar baru; server lama tidak mempunyai
   endpoint baru. Tidak ada migration baru dari penambahan mobile ini.
2. Unggah **file `app-release.aab` versi 1.0.1+2** ke internal testing, bukan
   salinan code 1 atau APK diagnostik. Uji install/update, Login/Profil,
   tautan publik, pemeriksaan tunggal/kolektif STS, kamera dan push di HP nyata.
   Uji perangkat/emulator 16 KB serta laporan pre-launch Play tetap diperlukan.
3. Selesaikan Data Safety, akses reviewer, target usia/Families, store listing,
   dan prosedur pemenuhan permintaan penghapusan. Halaman permintaan tidak
   otomatis menghapus arsip sekolah dan bukan bukti seluruh kewajiban Google
   Play sudah dipenuhi. Gerbang perlindungan foto publik/prosedur privasi anak
   yang dibahas sebelumnya tidak menjadi selesai hanya karena AAB dibangun.

Belum mengunggah, menerbitkan aplikasi, atau mengubah server/database produksi.
Kelulusan pemeriksaan artefak bukan jaminan persetujuan reviewer Google Play.
