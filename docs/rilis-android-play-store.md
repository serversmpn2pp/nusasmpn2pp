# Signing dan build rilis NUSA Android

Upload key dibuat dan disimpan oleh pengelola sekolah pada komputer pengembang,
bukan server Laravel. Kunci tersebut berbeda dari service-account Firebase.
Jangan memasukkan keystore, password, atau `key.properties` ke Git/chat.

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

SHA-256 **AAB saat ini**:

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
