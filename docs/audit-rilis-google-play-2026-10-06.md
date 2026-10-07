# Audit kesiapan rilis NUSA Android

Tanggal: 6 Oktober 2026. Snapshot: `3f9b866`, termasuk enam perubahan Humas yang sudah ada sebelum audit ini. Audit tidak mengubah kode aplikasi, backend, database sekolah, konfigurasi Firebase, atau hak akses pengguna. Berkas ini merupakan hasil audit, bukan pernyataan bahwa seluruh skenario produksi telah teruji.

**Tindak lanjut kode R03–R05 sudah diterapkan pada 6 Oktober 2026.** Bagian 1–7
tetap merekam snapshot/temuan awal; status dan bukti perbaikannya ada di bagian 8.

## Keputusan

**Belum direkomendasikan untuk rilis publik/production di Google Play.**

Pondasi fitur dan otorisasi memiliki hasil pengujian yang baik. Pada snapshot awal, penghalang utama adalah signing release, kebijakan privasi dalam aplikasi, pengamanan push saat status akun berubah, serta belum adanya bukti pengujian push dan AAB final di perangkat sungguhan. Pengamanan push R03–R05 kini sudah diperbaiki dan diuji otomatis (bagian 8); signing, privasi, serta pengujian produksi tetap perlu diselesaikan sebelum rilis publik.

## 1. Bukti pengujian

| Pemeriksaan | Hasil | Batas kesimpulan |
| --- | --- | --- |
| Seluruh tes Flutter | **340 lulus** | Tes otomatis yang tersedia, bukan seluruh interaksi pada HP fisik |
| Flutter analyzer | **Tidak ada issue** | Tidak membuktikan FCM, kamera, atau stabilitas jaringan produksi |
| Seluruh suite PHPUnit Laravel | **1.286 lulus; 16.802 assertion; 0 error/failure/skipped** | SQLite in-memory, HTTP Firebase dipalsukan pada tes; bukan PostgreSQL/server sekolah |
| Subset API, unit, permission, identitas, dan notifikasi | **527 lulus; 7.526 assertion** | Sudah termasuk dalam cakupan suite lengkap; jangan dijumlahkan sebagai tes tambahan |
| Inventaris API v1 | **448 endpoint; hanya login publik** | Pemeriksaan middleware, bukan bukti bahwa setiap kombinasi izin kustom sudah diuji |
| Guard API selain login | **447 memakai Sanctum, ability mobile, dan akun aktif** | Endpoint auth memang dikecualikan dari kewajiban ganti sandi supaya sandi dapat diganti |
| Guard ganti sandi API bisnis | **Tidak ada endpoint bisnis yang kehilangan guard** | Pemeriksaan rute yang terdaftar saat audit |
| Rute seluruh menu native | **108 dari 108 ditemukan** | Validitas rute tidak sama dengan kesetaraan seluruh operasi web/native |
| API sekolah melalui HTTPS, tanpa token | **401 JSON, melalui Cloudflare** | GET `/api/v1/auth/saya`; tidak login atau memeriksa konfigurasi internal server |
| ZIP alignment APK debug, `zipalign -c -P 16 -v 4` | **Verification successful** | APK debug yang sudah ada, bukan AAB rilis final |
| ELF alignment library 64-bit pada APK debug | **11 library diperiksa; semua LOAD alignment >= 16 KB** | APK emulator tidak memuat seluruh binary Flutter/app untuk rilis arm64 |

Perintah pengujian utama:

```powershell
# Dari D:\nusasmpn2pp\mobile, Flutter CLI standar:
flutter analyze --no-pub
flutter test --no-pub --reporter compact

# Dari D:\nusasmpn2pp:
php -d memory_limit=1024M vendor/phpunit/phpunit/phpunit
```

Pada lingkungan ini Flutter dijalankan melalui `dart.exe` dan `flutter_tools.snapshot` milik SDK yang sama. JUnit lengkap disimpan sementara di `C:/Users/pitra/AppData/Local/Temp/nusa-release-audit-20261006-phpunit-all.xml`.

Pengujian PHP dengan batas bawaan 128 MB sempat berhenti di pendeteksian MIME unduhan bukti `LaporanSiswaWaliApiTest`. Setelah batas memori **proses tes saja** dinaikkan menjadi 1 GB, seluruh suite lulus. Ini tidak membuktikan aplikasi Android mengalami kehabisan RAM dan bukan instruksi menaikkan semua proses PHP produksi menjadi 1 GB. Tetap uji unduhan PDF/berkas sebenarnya pada konfigurasi server produksi.

## 2. Temuan yang perlu diselesaikan sebelum rilis

### R01 — P1: Release masih ditandatangani menggunakan debug key

Bukti: [build.gradle.kts](D:/nusasmpn2pp/mobile/android/app/build.gradle.kts:40) memilih `signingConfigs.getByName("debug")` untuk release. `mobile/android/key.properties` belum tersedia; AAB rilis final belum dihasilkan dalam audit ini.

Dampak: build release saat ini bukan artefak siap unggah ke Google Play. Buat upload key yang dikelola sekolah, konfigurasi signing release yang gagal secara jelas jika key tidak tersedia, lalu hasilkan AAB. Jangan memasukkan keystore/password ke Git. Google menjelaskan penggunaan upload key untuk menandatangani release app bundle melalui [Play App Signing](https://support.google.com/googleplay/android-developer/answer/9842756?hl=en).

### R02 — P1: Kebijakan privasi global belum dapat diakses dari aplikasi

Pencarian halaman/tautan kebijakan privasi pada kode mobile tidak menemukan implementasi global. Pemberitahuan privat pada modul BK/ibadah bukan pengganti kebijakan privasi aplikasi. Tidak dilakukan asumsi bahwa sekolah belum memiliki kebijakan di luar repository.

Sediakan kebijakan resmi sekolah pada URL publik yang aktif, serta tautan atau teks di aplikasi. Kebijakan perlu menjelaskan data, tujuan penggunaan, pihak pemroses, keamanan, retensi, penghapusan, dan kontak privasi. Google mewajibkan kebijakan dalam aplikasi dan di Play Console; URL kebijakan tidak boleh berupa PDF atau halaman yang memerlukan login. Lihat [User Data / Privacy Policy](https://support.google.com/googleplay/android-developer/answer/10144311?hl=en).

### R03 — P1: Job push tidak memeriksa ulang apakah akun penerima masih aktif

Bukti: [KirimPushNotification](D:/nusasmpn2pp/app/Jobs/KirimPushNotification.php:30) memeriksa konfigurasi, keberadaan notifikasi, dan perangkat aktif/terakhir terlihat, tetapi tidak memeriksa ulang `Pengguna.aktif` saat job dieksekusi. [NotifikasiPenggunaService](D:/nusasmpn2pp/app/Services/Notifikasi/NotifikasiPenggunaService.php:26) memeriksa akun aktif ketika notifikasi dibuat, bukan ketika antrean dikirim.

Skenario: notifikasi dibuat untuk akun aktif → job mengantre → admin menonaktifkan akun → perangkat push masih aktif → job tetap bisa mengirim isi notifikasi. Guard API menolak akun tersebut, tetapi tidak melindungi teks yang sudah terkirim ke sistem notifikasi HP.

Perbaikan: validasi ulang akun saat pengiriman, hentikan/deaktivasi registrasi perangkat ketika akun dicabut, dan tentukan kebijakan pengiriman ketika penugasan/izin sensitif berubah. Tambahkan tes akun dinonaktifkan setelah job diantrekan. Temuan ini berasal dari analisis jalur kode; tidak mengirim push nyata ke akun pengguna selama audit.

### R04 — P1: Koordinator push belum mengikat pesan tertunda dengan pemilik akun

Bukti: [PushNotificationCoordinator](D:/nusasmpn2pp/mobile/lib/features/push_notifications/application/push_notification_coordinator.dart:53):

- `loggedOut()` hanya mengubah boolean; pesan tertunda tidak dibersihkan atau dikaitkan dengan identitas akun.
- Handler foreground pada baris 82 menampilkan title/body tanpa guard authenticated.
- Pada baris 118, kegagalan penandaan baca, termasuk 401/403, diabaikan dan navigasi tetap dilanjutkan.

Skenario: pesan akun A tertunda ketika sesi belum siap → login akun B → pesan A berpotensi diproses di sesi B. Pesan yang masuk ketika layar login terbuka juga dapat ditampilkan melalui SnackBar. API tetap memeriksa kepemilikan dan cakupan, sehingga ini **bukan bukti bahwa akun B dapat membaca data backend akun A**, tetapi teks payload dan navigasinya belum aman pada pergantian akun.

Perbaikan: verifikasi notifikasi terhadap akun aktif sebelum menampilkan/navigasi, hapus state tertunda ketika identitas berubah, hentikan navigasi pada penolakan autentikasi/otorisasi, dan audit notifikasi sistem yang tertinggal setelah logout. Tambahkan tes cold start, logout/login akun lain, sandi wajib diganti, dan akun dinonaktifkan.

### R05 — P1: Detail sensitif dikirim langsung sebagai isi push

Bukti: [FirebaseCloudMessagingService](D:/nusasmpn2pp/app/Services/Notifikasi/FirebaseCloudMessagingService.php:58) menyalin judul dan pesan notifikasi ke payload push tanpa klasifikasi sensitivitas. [ProsesPoinSiswaService](D:/nusasmpn2pp/app/Services/Pembinaan/ProsesPoinSiswaService.php:285) menghasilkan pesan yang memuat nama siswa, sanksi, dan saldo poin; pesan peringatan dini juga memuat data perilaku siswa.

Dampak: isi tersebut dapat terlihat di notification shade/lock screen, tergantung pengaturan perangkat, dan diproses melalui FCM. Catatan privat berhalangan tidak ditemukan dalam contoh pesan konfirmasi yang diperiksa, tetapi tetap perlu kebijakan redaksi lintas jenis notifikasi.

Perbaikan: untuk BK, sanksi, ibadah privat, dan data siswa sensitif, gunakan pesan umum seperti “Ada pembaruan di NUSA. Buka aplikasi untuk melihat detail.” Detail hanya diambil melalui API setelah autentikasi dan pemeriksaan izin. Visibility private boleh menjadi lapisan tambahan, bukan pengganti redaksi payload.

### R06 — Gerbang verifikasi: Push produksi belum terbukti muncul pada perangkat

Tes FCM backend tersedia dan lulus dengan `Http::fake()`; registrasi/pemindahan token juga memiliki tes API. Itu membuktikan struktur permintaan dan respons kode, bukan delivery nyata dari proyek Firebase sekolah ke HP.

`google-services.json` klien tersedia dan package cocok dengan `id.sch.smpn2padangpanjang.nusa`. Akan tetapi, kredensial service account, project ID efektif, konfigurasi worker, izin notifikasi HP, dan penerimaan FCM produksi belum diverifikasi end-to-end. Tidak ada pengiriman push nyata atau akses ke Firebase Console selama audit.

Selesaikan matriks uji di bagian 5 menggunakan artefak release/internal test. Status job `DONE` **bukan bukti notifikasi tampil**: job juga dapat selesai ketika push dimatikan atau tidak ada perangkat yang memenuhi filter.

## 3. Temuan lanjutan dan batas fitur

| ID | Prioritas | Temuan | Tindakan |
| --- | --- | --- | --- |
| R07 | P2 | Inbox mobile hanya memperoleh 10 notifikasi terbaru; badge menghitung semua yang belum dibaca. [BerandaMobileService](D:/nusasmpn2pp/app/Services/Mobile/BerandaMobileService.php:223) | Sediakan daftar berhalaman/lihat sebelumnya agar notifikasi lama yang belum dibaca dapat dibuka |
| R08 | P2 | Backup & Restore masih item placeholder, tidak memiliki rute native. [menu_mobile.php](D:/nusasmpn2pp/config/menu_mobile.php:174) | Sembunyikan dari release mobile atau jelaskan bahwa tersedia di web; jangan menandainya sudah native |
| R09 | P2 | Notifikasi fitur web yang belum native diarahkan ke inbox/tidak memiliki tujuan native. Contoh: kemitraan/MoU dan publikasi Humas | Labeli batas fitur; jangan mengarahkan ke route Flutter yang tidak ada. Bukan alasan membangun semua modul web sebelum rilis pertama |
| R10 | P2 | Inisialisasi/registrasi Firebase gagal secara lunak; pesan diagnostik hanya dicetak. [firebase_bootstrap.dart](D:/nusasmpn2pp/mobile/lib/core/notifications/firebase_bootstrap.dart:18) | Tambahkan indikator kesehatan push dan jalur mencoba ulang; release perlu pemeriksaan konfigurasi Firebase yang eksplisit |
| R11 | P2 | Ganti sandi API tidak mencabut sesi/token lain. [AutentikasiController](D:/nusasmpn2pp/app/Http/Controllers/Api/V1/AutentikasiController.php:65) | Tentukan kebijakan keluar dari perangkat lain, terutama pada reset keamanan; tambahkan tes. Ini hardening, bukan bukti kebocoran yang sudah terjadi |
| R12 | Pemeriksaan produksi | Seeder instalasi baru memakai kata sandi administrator yang diketahui dari kode. [DatabaseSeeder](D:/nusasmpn2pp/database/seeders/DatabaseSeeder.php:35) | Pastikan kredensial awal sudah diganti dan tidak dipakai pada server/demo reviewer. Audit tidak mencoba login dengan kredensial awal ke sekolah |

Nilai `APP_ENV=local` dan `APP_DEBUG=true` pada workspace pengembang tidak dianggap bukti konfigurasi server sekolah. Sebelum publikasi, admin server harus memverifikasi `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` HTTPS sekolah, migrasi terbaru, izin storage, worker, scheduler, backup, dan pembatasan akses kredensial. Jangan membuka service-account JSON atau menaruhnya dalam folder publik/aplikasi mobile.

## 4. Cakupan fitur dan hak akses

### Inventaris menu

Ada **109 item katalog dalam 8 kelompok**. Sebanyak **108 berstatus tersedia/native**, dengan rute Flutter yang ditemukan setelah normalisasi query string. Satu item adalah Backup & Restore. Jumlah katalog bukan jumlah menu yang diterima satu akun; menu siswa/orang tua/pegawai disaring menurut identitas, izin, serta penugasan.

| Kelompok | Item katalog | Native | Cakupan yang diperiksa |
| --- | ---: | ---: | --- |
| Data Sekolah | 9 | 9 | Tahun, pegawai, siswa, kelas, penempatan, kenaikan, foto, kartu |
| Akademik | 18 | 18 | Jadwal, jam, penugasan, mapel, bobot/komponen, input/rekap nilai, survei, perangkat ajar, akademik anak |
| Ujian & Asesmen | 4 | 4 | Entry pusat CBT, pengawas, ujian siswa, ujian anak; halaman turunan persiapan, monitoring, koreksi, hasil, presensi |
| Kehadiran | 23 | 23 | Presensi siswa/pegawai, piket, ibadah, scan kamera ibadah, berhalangan/konfirmasi privat, rekap pribadi/anak |
| Kesiswaan & BK | 22 | 22 | Laporan, pemeriksaan, pendampingan, sanksi, poin, peringatan, wali, BK tingkat, progres siswa/anak |
| Sarana Prasarana | 19 | 19 | Master, aset, label, barang datang, stok, pinjam/kembali, laporan, katalog dan pengajuan |
| Humas | 8 | 8 | Dashboard, agenda, dokumen, pengaduan, umpan balik dan tiga layanan orang tua |
| Sistem | 6 | 5 | Akun, role/izin, aktivitas login; backup masih placeholder |

Tidak ditemukan menu berstatus native yang menunjuk ke rute Flutter tidak dikenal. Ini tidak berarti setiap import, cetak, unduh, atau aksi web telah dipindahkan ke mobile. Contohnya, pembuatan undangan Humas tetap dilakukan di web sesuai keputusan pengguna; scan kehadiran pertemuan orang tua tetap tersedia pada mobile.

### Matriks akun, role, dan penugasan

Pemisahan identitas siswa/orang tua memakai relasi nyata, bukan sekadar label role. Otorisasi operasi tetap berada di API; penyembunyian kartu menu bukan satu-satunya pengamanan. Suite yang lulus mencakup negative tests identitas, izin, kepemilikan, dan cakupan beberapa modul. Tabel ini adalah ringkasan kontrak akses yang diperiksa, **bukan bukti login manual untuk setiap akun sekolah/role kustom**.

| Akun/penugasan | Batas akses penting | Bukti/hal yang perlu dikonfirmasi di lapangan |
| --- | --- | --- |
| Administrator | Akses kelola menurut kebijakan sistem; identitas pegawai tidak berubah karena role admin | Tes peran, menu admin, dan identitas sesi lulus; pastikan kredensial awal diganti |
| Pegawai biasa, kebersihan | Profil dan presensi sendiri, layanan pegawai, laporan yang dibuat sendiri | Tidak otomatis mendapat pemeriksaan BK/pelaksanaan sanksi |
| Guru mapel | Jadwal, kelas/mapel penugasan, input/rekap nilai dan asesmen sesuai cakupan | Tes rekap nilai mapel lulus; cek penugasan aktual tahun aktif |
| Wali kelas | Kelas binaan, monitoring siswa kelas, nilai/presensi menurut izin | Tes pembatasan kelas lulus; uji akun wali yang juga guru mapel |
| Guru wali | Siswa bimbingan aktif dan laporan/monitoring sesuai penugasan | Tidak otomatis mendapat hak pemeriksaan/pengesahan; tes laporan wali dan peringatan lulus |
| BK | Proses laporan/kasus sesuai izin dan cakupan tingkat yang ditugaskan | Tes modul dan penugasan BK lulus; cek penugasan tingkat produksi |
| Wakil kesiswaan | Pengesahan/monitoring sesuai izin | Pisahkan kewenangan dari pelapor dan guru wali |
| Pimpinan | Monitoring sesuai izin, bukan semua aksi kelola | Tes pembedaan baca/kelola; cek role kustom tidak memberi izin tulis berlebih |
| Wakil kurikulum | Monitoring/kelola akademik sesuai izin | Jangan otomatis mengizinkan data BK privat |
| Wakil sarpras/petugas inventaris | Sarpras menurut izin baca/kelola/peminjaman | Tes modul sarpras lulus; pegawai lain hanya pengajuan miliknya |
| Wakil Humas/petugas tiket | Operasi Humas sesuai izin dan disposisi | Tes pemisahan identitas, berkas, dan petugas terdisposisi lulus |
| Satpam | Status scan presensi/operasional sesuai izin | Tidak menambahkan scan kamera presensi siswa; tetap memakai mesin sekolah |
| Guru piket hari berjalan/guru PL | Scan ibadah menurut aturan petugas yang berlaku | Masih perlu uji akun berpenugasan pada hari nyata dan kamera HP |
| Pengawas/panitia ujian | Ruang/jadwal/peserta sesuai penugasan dan izin | Uji konflik peran guru-mapel/pengawas dan pergantian penugasan |
| Siswa | Data sendiri: nilai, kehadiran, ibadah, progres, ujian | Nilai sebelum survei tidak membocorkan rincian; kata sandi awal tetap mengunci menu/API |
| Orang tua | Anak yang terhubung: akademik, presensi, ibadah, poin/kasus, ujian, layanan Humas | Tes pilihan anak dan penolakan anak lain lulus; label anak dan hasil terkunci mengikuti survei anak |

Role dapat dikustomisasi sehingga audit semua variasi izin dari database sekolah membutuhkan ekspor daftar izin/penugasan yang aman atau pengujian akun demo. Tidak mengubah role ataupun mengakses data siswa nyata dalam audit ini.

Uji penerimaan minimal: admin, pegawai biasa, guru mapel, guru mapel + wali kelas, guru wali, BK per tingkat, piket/PL, pengawas, Humas terdisposisi, siswa, orang tua dengan dua anak. Untuk tiap akun, uji juga akses API/detail yang tidak boleh dilihat, bukan hanya kartu menu.

## 5. Audit notifikasi dan push

### Yang sudah baik

- Notifikasi API hanya dapat ditandai baca oleh pemilik; “baca semua” dibatasi relasi akun sendiri.
- Kunci unik mencegah duplikasi notifikasi untuk akun yang sama; job baru diantrekan setelah transaksi commit.
- Tujuan native berasal dari pemetaan backend yang dikenal; tautan web yang belum native tidak dipaksakan sebagai halaman Flutter.
- Bahasa publikasi nilai dan hasil ujian sudah membedakan siswa (“Anda”) dan orang tua (“anak Anda”). Rincian nilai yang terkunci survei tetap dibatasi API, tidak dibuka oleh klik notifikasi.
- Endpoint registrasi token menggunakan identitas sesi, bukan ID pengguna yang dikirim klien; token berpindah ke akun terakhir yang mendaftarkannya.
- Logout berupaya menonaktifkan perangkat server dan menghapus token FCM lokal; token `UNREGISTERED` dinonaktifkan oleh job.
- Izin Android 13+, channel `nusa_notifications`, icon, warna, serta handler background tersedia. Server dan Android menggunakan nama channel yang sama.
- Tes FCM memeriksa payload/tujuan/channel dan penonaktifan token tidak valid. Tes tersebut tidak mencakup seluruh risiko R03–R05; belum ditemukan tes koordinator push Flutter khusus.

### Matriks verifikasi pada HP release/internal test

Semua baris berikut masih perlu dibuktikan pada perangkat nyata dan server sekolah; jangan mencentang berdasarkan `DONE` worker saja.

| Skenario | Hasil yang diharapkan |
| --- | --- |
| Aplikasi sedang dibuka | Pesan tampil sesuai desain foreground, untuk akun aktif saja; tidak mengganggu ujian |
| Aplikasi di background | Notifikasi sistem muncul ketika izin/channel aktif |
| Aplikasi tidak berjalan, bukan force-stop | Push muncul; ketukan cold start menuju halaman sah setelah sesi selesai dipulihkan |
| Android 13+ menolak izin | Aplikasi tetap berfungsi; pengguna tahu cara mengaktifkan notifikasi; tidak dianggap delivery berhasil |
| Izin diaktifkan kembali | Registrasi diperiksa ulang dan pengiriman berikutnya berhasil |
| Logout, kemudian login akun lain | Tidak menampilkan pesan/detail akun lama; badge, inbox, dan tujuan sesuai akun baru |
| Akun dinonaktifkan saat job mengantre | Tidak mengirim atau menampilkan push sensitif untuk akun yang dicabut |
| Role/kelas/penugasan sensitif dicabut | Detail tidak dapat dibuka; kebijakan payload tidak membocorkan data lama |
| Wajib ganti sandi | Tidak dapat melewati gate melalui deep link push |
| Token FCM berubah/uninstall-reinstall | Registrasi baru aktif, token lama ditangani tanpa duplikasi |
| Server offline lalu pulih | Pesan gagal/retry jelas; tidak mengirim ulang duplikat tanpa kendali |
| FCM 401/403/429/5xx | Kegagalan dapat didiagnosis, retry sesuai jenis error, tidak ada token/kredensial di log pengguna |
| Dua perangkat satu akun | Keduanya menerima sesuai kebijakan; logout satu perangkat tidak menonaktifkan yang lain |
| Layar terkunci/HP bersama | Teks umum; rincian BK/ibadah/siswa tidak muncul sebelum autentikasi |
| Ketukan notifikasi lama | Data yang sudah dihapus/izin berubah ditangani dengan pesan umum, tanpa halaman error teknis |

Foreground saat ini memakai SnackBar, bukan notifikasi sistem lokal. Itu bukan otomatis bug: Firebase menyatakan notification message foreground tidak menampilkan notifikasi visible secara default. Android yang di-force-stop dari Settings juga perlu dibuka lagi untuk melanjutkan penerimaan. Lihat [Receive messages in Flutter apps](https://firebase.google.com/docs/cloud-messaging/flutter/receive-messages).

Untuk verifikasi server, bedakan tahapan: notifikasi database dibuat → perangkat terdaftar → job diproses → HTTP FCM menerima pesan → HP menerima → sistem menampilkan → ketukan membuka tujuan yang sah. Tambahkan observabilitas yang tidak menyimpan token lengkap/kredensial; tidak perlu mencetak seluruh payload sensitif.

## 6. Checklist Google Play

### Artefak dan konfigurasi

- [ ] Upload key resmi sekolah dan Play App Signing; backup key di tempat aman, bukan repository.
- [ ] AAB release final dengan `APP_ENV=production` dan API HTTPS sekolah.
- [ ] Firebase klien dan server menggunakan proyek yang sama; secret server tidak berada dalam AAB/Git.
- [ ] Manifest release final diperiksa: tidak debuggable, tidak mewarisi izin cleartext debug, tidak menambah permission yang tidak diperlukan.
- [ ] Target API diperiksa dari artefak final. SDK yang terpasang menghasilkan target API 36; ini sesuai persyaratan aplikasi baru/update sejak 31 Agustus 2026 dalam [Target API requirements](https://support.google.com/googleplay/android-developer/answer/11926878?hl=en).
- [ ] Periksa ZIP **dan** ELF 16 KB serta uji runtime pada artefak release, termasuk `libapp.so`/Flutter arm64. Hasil debug yang lulus belum cukup. Panduan resmi: [Support 16 KB page sizes](https://developer.android.com/guide/practices/page-sizes).
- [ ] Nomor versi/build unik dan meningkat pada setiap pembaruan. `1.0.0+1` saat ini dapat menjadi versi pertama jika belum pernah digunakan di Play.
- [ ] Uji install dari internal track; jangan memakai ukuran APK debug sebagai ukuran download Play Store.

Perintah kandidat build **setelah** signing dan temuan keamanan ditangani:

```powershell
flutter build appbundle --release --dart-define=APP_ENV=production --dart-define=API_BASE_URL=https://nusa.smpn2padangpanjang.sch.id/api/v1/
```

Perintah ini tidak dijalankan dalam audit dan tidak otomatis membuat signing release menjadi benar.

### Privasi dan deklarasi

- [ ] Kebijakan privasi resmi tampil di aplikasi dan URL publik.
- [ ] Data Safety mengikuti praktik nyata NUSA beserta SDK: identitas/kontak, foto/berkas, kegiatan akademik/presensi, informasi sensitif yang dikirim, identifier instalasi/perangkat, dan aktivitas aplikasi. Jangan memilih “tidak mengumpulkan data” hanya karena database berada di sekolah. Transfer ke pemroses layanan tidak selalu diklasifikasikan sebagai “sharing”; tentukan sesuai definisi formulir dan konfigurasi. Lihat [Data Safety](https://support.google.com/googleplay/android-developer/answer/10787469?hl=en).
- [ ] Deklarasi FCM mencakup dependency Firebase Installations dan penggunaan data versi/identifier yang relevan; sesuaikan versi SDK dan praktik aplikasi. Panduan: [Firebase data disclosure](https://firebase.google.com/docs/android/play-data-disclosure).
- [ ] Kamera barcode ML Kit memproses input di perangkat, tetapi SDK dapat mengirim metrik penggunaan/performa; jangan menjanjikan “tidak ada komunikasi Google” secara menyeluruh. Tinjau [ML Kit Terms & Privacy](https://developers.google.com/ml-kit/terms).
- [ ] Informasi penggunaan kamera, notifikasi, dan pengawasan lifecycle ujian dijelaskan dengan wajar sebelum fitur digunakan. Jangan mengklaim anti-cheat mampu memastikan siswa tidak curang atau membaca aplikasi lain secara menyeluruh.
- [ ] Tetapkan alur permintaan penghapusan akun/data dan retensi resmi. NUSA menyediakan pembuatan akun oleh petugas, bukan self-sign-up umum; perlu menentukan cakupan kebijakan yang tepat, bukan mengasumsikan pengecualian karena aplikasi sekolah. Google mensyaratkan jalur dalam aplikasi dan web jika aplikasi memungkinkan pembuatan akun. Jangan menghapus otomatis arsip sekolah yang wajib dipertahankan tanpa keputusan kebijakan. Lihat [Account deletion requirements](https://support.google.com/googleplay/android-developer/answer/13327111?hl=en).
- [ ] Target Audience/Content mengikuti usia pengguna sebenarnya. Jika menyasar anak, ikuti persyaratan data dan SDK [Families Policy](https://support.google.com/googleplay/android-developer/answer/9893335?hl=en); jangan memilih dewasa hanya untuk menghindari deklarasi.

### Review dan pengujian

- [ ] Nama/deskripsi, kategori, icon, screenshot, feature graphic, kontak dukungan, content rating, dan deklarasi iklan disiapkan; tidak memakai screenshot data pribadi siswa nyata.
- [ ] Akun demo reviewer dengan data sintetis, sandi sudah diganti, penugasan dan data uji tersedia. Jangan memberikan akun administrator produksi atau membutuhkan barcode fisik sekolah untuk satu-satunya jalur review. Sertakan instruksi akses sesuai [Prepare your app for review](https://support.google.com/googleplay/android-developer/answer/9859455?hl=en).
- [ ] Verifikasi jenis akun developer. Akun personal yang dibuat setelah 13 November 2023 memerlukan closed test dengan minimal 12 tester yang opted-in terus-menerus selama 14 hari sebelum mengajukan akses production; tidak dianggap otomatis berlaku untuk semua akun organisasi. Sumber: [Testing requirements](https://support.google.com/googleplay/android-developer/answer/14151465?hl=en).
- [ ] Internal test/pre-launch report: layar kecil, font besar, keyboard, scroll panjang, kamera, file/PDF, printer/share, penolakan izin, jaringan putus, dan cold start.
- [ ] Ujian: autosave, reconnect, submit ganda, perpindahan aplikasi, lock screen, batas waktu, susulan, finalisasi/publikasi, dan notifikasi masuk selama pengerjaan diuji pada HP fisik.
- [ ] Tes staging PostgreSQL dan beban/concurrency yang proporsional dilakukan. Suite SQLite tidak memverifikasi lock, indeks, migrasi produksi, dan semua perilaku PostgreSQL.
- [ ] Worker/scheduler tetap hidup setelah reboot/deploy; deployment memiliki backup dan rollback yang sudah diuji.

## 7. Urutan tindak lanjut

1. Perbaiki R03–R05 dan tambahkan regression test keamanan push lintas akun/status; jangan mengubah kontrak identitas yang sudah benar.
2. Tambahkan kebijakan privasi resmi, kontak/retensi, serta alur permintaan data/akun yang disepakati sekolah.
3. Siapkan signing/AAB production dan validasi Firebase release; sembunyikan placeholder yang belum native.
4. Jalankan kembali seluruh analyzer/tes, kemudian matriks akun dan push di HP nyata/server sekolah serta staging PostgreSQL.
5. Unggah ke internal testing dengan akun/data demo, lengkapi deklarasi Play, dan tindak lanjuti pre-launch report sebelum rilis publik.

**Kriteria akhir:** temuan P1 ditutup dengan tes, AAB final tervalidasi, kebijakan/deklarasi lengkap, pengujian perangkat dan role selesai, serta push terbukti end-to-end. Persetujuan Google Play tidak dapat dijamin hanya dari audit repository.

## 8. Tindak lanjut R03–R05 — keamanan push

Bagian ini mencatat verifikasi perbaikan awal. Kebijakan presentasi kemudian
disempurnakan atas permintaan pengguna; lihat bagian 9 dan README mobile.

Perubahan terbatas pada pengiriman, verifikasi, presentasi, dan lifecycle push.
Kontrak identitas siswa/orang tua/pegawai, relasi identitas, RBAC, dan aturan
`harusMenggantiKataSandi()` tidak diubah. Perubahan Humas sebelumnya dipertahankan.

- **R03 — diperbaiki di kode:** job membaca ulang akun saat eksekusi dan sebelum
  setiap perangkat dikirim; akun nonaktif atau wajib ganti sandi ditolak dan
  registrasi push dinonaktifkan. Pemilik token, status aktif, dan batas umur
  perangkat diperiksa ulang. Perubahan model akun melalui Eloquent juga
  menonaktifkan perangkat ketika akun dicabut/kembali wajib ganti sandi; job
  tetap melindungi jalur update massal yang melewati event model.
- **R04 — diperbaiki di kode:** push diverifikasi melalui endpoint milik akun
  sebelum tampil/navigasi; tidak mengirim isi privat pada respons verifikasi.
  Versi sesi menghalangi hasil async akun lama, pesan tertunda dibersihkan pada
  logout/pergantian akun, dan notifikasi Android yang sudah tampil dibersihkan.
  Penolakan verifikasi/penandaan baca menghentikan navigasi; tujuan payload
  mentah tidak dipercaya. Gate sandi global ikut menghentikan push.
- **R05 — diperbaiki di kode:** semua jenis push memakai title/body umum,
  termasuk modul baru dan job lama. SnackBar tidak menampilkan payload privat
  lama. Teks detail di database/inbox tidak diubah. Android memakai visibility
  `PRIVATE` sesuai [referensi FCM AndroidNotification](https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#androidnotification);
  redaksi tetap menjadi perlindungan utama.

Regression test keamanan meliputi akun A/B pada foreground/cold start,
logout/pergantian akun saat verifikasi atau baca berjalan, callback sesi lama,
401/403/404/428, ID/payload tidak valid, rute eksternal, gate sandi, pencabutan
akun setelah antrean dibuat maupun di tengah pengiriman, perpindahan pemilik
token, perangkat nonaktif/kedaluwarsa, UNREGISTERED/retry, dan redaksi data
sensitif tanpa mengubah detail di database.

Bukti verifikasi: **28 tes Flutter keamanan push lulus; seluruh 368 tes Flutter
lulus; seluruh 1.297 tes Laravel lulus dengan 16.845 assertion dan tanpa
error/failure/skipped; analyzer bersih; APK debug berhasil dibangun** dengan API
HTTPS sekolah. Build masih memberi peringatan kompatibilitas KGP dari plugin
`firebase_core`/`mobile_scanner` serta Java native access; tidak ada build error.
Subset backend push/notifikasi/perangkat juga lulus **19 tes, 106 assertion**;
subset ini sudah termasuk dalam suite lengkap, bukan tes tambahan. JUnit
verifikasi disimpan lokal di `storage/logs/regression-push-junit.xml`. Perintah
suite lengkap sama dengan bagian 1. Pemeriksaan `git diff --check` bersih.

Deploy backend sebelum APK baru, kosongkan cache Laravel, dan restart worker
agar job antrean memakai kode redaksi terbaru. Tidak ada perubahan schema atau
secret Firebase. Langkah penerapan dan perintah tes ada di
[README mobile](D:/nusasmpn2pp/mobile/README.md).

**Batas verifikasi tetap berlaku:** Firebase/HTTP pada tes dipalsukan; server
sekolah tidak dideploy dan push nyata tidak dikirim. Notifikasi yang sudah
terkirim sebelum deployment tidak dapat ditarik kembali dari FCM. Pengujian
HP fisik R06, PostgreSQL, signing/AAB R01, dan kebijakan privasi R02 tetap perlu
diselesaikan sebelum rekomendasi rilis publik berubah.

## 9. Penyempurnaan pesan dan notifikasi presensi — 6 Oktober 2026

Atas permintaan pengguna, notifikasi tidak lagi diseragamkan menjadi kalimat
umum untuk semua jenis. Kontrak identitas, guard sesi/status/ganti sandi,
pemeriksaan kepemilikan perangkat, dan penjagaan pergantian akun tidak diubah.

- SnackBar memakai judul/pesan asli **dari API setelah verifikasi pemilik**.
  Endpoint tujuan menambahkan `judul`/`pesan` tanpa menandai baca dan tetap
  `private, no-store`. Teks dan tujuan dari payload FCM tidak dipercaya.
- Push rutin nilai/hasil ujian dan survei memakai teks sesuai penerima. Presensi
  memakai waktu dan status scan tanpa nama/NISN anak. Modul dengan catatan bebas
  memakai ringkasan sesuai modul, bukan salinan catatan.
- BK, sanksi, dan berhalangan ibadah tetap diredaksi tanpa rincian pribadi.
  Kategori sensitif diperiksa lebih dahulu; jenis yang belum dikenal tetap
  umum. `data_tambahan` tidak disalin ke payload FCM.
- Scan masuk siswa memberi notifikasi NUSA kepada siswa dan orang tua aktif
  melalui relasi identitas, bukan berdasarkan role/nomor WhatsApp. Ketukan
  mobile memilih anak dan bulan yang sesuai; API tetap membatasi akses anak.
- Pengiriman WhatsApp otomatis dihentikan, termasuk job lama yang belum
  selesai. Riwayat terdahulu dan salin pesan grup manual tidak dihapus.
  Scan ulang tidak menggandakan notifikasi, dan scan tanpa akun penerima tetap
  berhasil. Status `tersimpan` tidak diklaim sebagai bukti FCM diterima HP.

Tidak ada migrasi database atau perubahan secret Firebase. Deploy backend
lebih dahulu, kosongkan cache, restart worker, lalu build/pasang APK baru.
Bukti verifikasi penyempurnaan ini: **1.306 tes Laravel lulus dengan 16.960
assertion, seluruh 370 tes Flutter lulus, analyzer bersih, dan APK debug
berhasil dibangun** menggunakan API HTTPS sekolah. Regression mencakup redaksi
payload FCM, teks asli API hanya untuk pemilik, status akun/perangkat, scan
terlambat/ulang, penerima berdasarkan relasi, job WhatsApp lama, dan pemilihan
anak dari notifikasi. Tidak ada error/failure/skipped pada suite Laravel;
JUnit tersimpan lokal di `storage/logs/regression-push-junit.xml`.
Tes memakai HTTP/Firebase palsu; belum ada deployment atau pengiriman push
nyata dari perubahan ini. Uji server sekolah/HP fisik serta hambatan rilis
signing dan privasi tetap wajib diselesaikan.
