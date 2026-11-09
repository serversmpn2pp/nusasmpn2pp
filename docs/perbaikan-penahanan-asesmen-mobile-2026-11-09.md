# Penahanan asesmen dan verifikasi ulang layar ujian

Tanggal: 9 November 2026. Tindak lanjut dua temuan dalam
[audit penahanan](audit-penahanan-asesmen-mobile-2026-11-09.md).

**Pembaruan kebijakan berikutnya:** asesmen baru kini default **Catat saja**,
dengan pilihan Tahan setelah batas dan konfirmasi guru. Bagian pengamanan
asesmen wajib di bawah mencatat implementasi sebelumnya; ketentuan aktif,
kompatibilitas, dan pengujiannya ada pada
[panduan mode monitoring asesmen](mode-monitoring-asesmen-2026-11-09.md).
Pengamanan ujian terpusat serta kewajiban alasan pembukaan tetap berlaku.

## Perilaku setelah perbaikan

- Asesmen kelas yang dibuat atau diperbarui melalui web maupun API selalu
  memakai deteksi keluar aktif, batas **3**, dan tindakan **tahan**.
  Parameter dari aplikasi lama tidak dapat menurunkan kebijakan ke catat saja,
  menonaktifkan deteksi, atau menaikkan batas. Form web dan Android menjelaskan
  aturan wajib ini; pilihan tindakan/toleransi Android yang menyesatkan dihapus.
- Pada Android, setiap interval kehilangan fokus yang terdeteksi dihitung
  sekali, termasuk interval singkat. Pemeriksaan proteksi perangkat dan
  konfirmasi keamanan server merupakan dua syarat terpisah untuk melihat soal.
- Setiap kehilangan fokus menutup tampilan soal dan menandai sesi harus
  diverifikasi ulang. Saat kembali, soal tetap tersembunyi selama proteksi
  Android diperiksa, jurnal belum selesai dikirim, atau sinkronisasi gagal.
- Setelah native siap tetapi server belum terkonfirmasi, tampil **Memeriksa
  status ujian**. Jika koneksi gagal, tampil pilihan **Periksa Status Ujian**;
  keberhasilan pemeriksaan native saja tidak membuka soal.
- Respons dari siklus fokus lama tidak membuka siklus baru. Aplikasi menunggu
  semua kejadian dalam jurnal selesai dikirim, termasuk kejadian baru yang
  masuk saat pengiriman sebelumnya masih berlangsung.
- Jika server mengembalikan ditahan, layar **Ujian sementara ditahan** tetap
  tampil. Siswa dapat memeriksa lagi setelah pengawas membuka sesi. Tidak ada
  penghapusan jawaban/draft, reset hitungan, tambahan waktu, atau pengumpulan
  otomatis sebagai hukuman baru.
- Layar pengerjaan dipakai bersama asesmen kelas dan ujian terpusat, sehingga
  perbaikan verifikasi berlaku pada keduanya. Bawaan kebijakan ujian terpusat
  sudah tahan; perbaikan ini tidak mengubah konfigurasi paket ujian terpusat.

Toleransi browser web yang telah ada tetap terpisah dari nol toleransi native.
Pengaturan acak soal/jawaban, satu perangkat, screenshot, dan fullscreen tetap
mengikuti pengaturan masing-masing; batas/tindakan asesmen wajib 3/tahan.

## Asesmen lama

Migrasi `2026_11_09_000001_perketat_penahanan_asesmen_kelas.php` menyesuaikan
asesmen `alur=kelas` berstatus draft, terjadwal, atau berlangsung:

- Deteksi menjadi aktif, batas menjadi 3, dan tindakan menjadi tahan.
- Peserta yang masih mengerjakan dan hitungannya sudah mencapai 3 akan ditahan,
  dengan catatan `kebijakan_diperketat` serta alasan batas kejadian.
- Jawaban, hitungan lama, waktu mulai, sisa waktu, dan heartbeat tidak dihapus
  atau direset. Migrasi tidak menambahkan kejadian keluar baru.
- Keputusan pengawas dihormati: jika kejadian lama sudah dicakup pembukaan
  pengawas dan tidak ada kejadian terhitung baru sesudahnya, peserta tidak
  ditahan ulang oleh migrasi. Kejadian baru tetap mengikuti aturan server.
- Asesmen selesai/nonaktif, peserta selesai, serta paket ujian terpusat tidak
  diubah. Menjalankan kembali migrasi tidak menggandakan catatan penahanan.
- Rollback tidak otomatis membuka sesi atau menghapus catatan keamanan.

Migrasi ini berpengaruh pada peserta yang masih mengerjakan dan sudah mencapai
batas; petugas perlu siap meninjau dan membuka sesi yang memang diperbolehkan.

## Penerapan server dan Android

### Pembukaan dari monitoring web dan native

Monitoring web kini menyediakan **Buka Ujian** di kolom Pengerjaan pada tabel
desktop dan pada kartu peserta di HP. Tindakan hanya muncul untuk peserta
terblokir dan akun yang memang boleh membuka sesi. Guru pembuat dapat membuka
asesmennya sendiri; administrator/petugas ujian terpusat mengikuti kewenangan
yang diperiksa `KeamananUjianService`. Peserta dari ujian lain ditolak.

Tombol memakai POST dengan CSRF serta konfirmasi, dan mencatat identitas
pembuka. Jawaban, hitungan kejadian, dan waktu mulai tidak direset. Setelah
petugas membuka sesi, siswa menekan **Periksa Status Ujian** di Android.

Monitoring native sudah memiliki tombol Buka Ujian. Pengujian tambahan
membuktikan tombol muncul pada peserta terblokir, meminta konfirmasi,
mengirim pembukaan (bukan Reset Perangkat), dan memperbarui status. API juga
diuji untuk guru pembuat serta penolakan guru lain. Tambahan ini lulus **68 tes
backend dan 6 tes monitoring native**. Log: `monitoring-unlock-tests.log`,
`web-monitoring-unlock.log`, dan `native-monitoring-unlock.log` pada direktori
bukti audit. Tes web awal membutuhkan koreksi fixture nomor peserta unik;
pengulangan tes tersebut lulus.

### Reset perangkat dari monitoring web

Monitoring CBT/asesmen menyediakan **Reset perangkat** pada kolom Pengerjaan
di desktop dan kartu peserta di HP. Buka tombol tersebut, isi alasan (10–500
karakter), lalu tekan **Konfirmasi reset perangkat**. Pastikan sesi lama ditutup.
Tindakan memakai endpoint web `keamanan-ujian.reset-perangkat` yang sudah ada,
dengan CSRF, validasi alasan, pembatasan permintaan, dan pemeriksaan kewenangan
oleh `KeamananUjianService`. Tombol ditampilkan kepada petugas berwenang saat
peserta sedang mengerjakan atau terblokir, dan ruang belum selesai.

Reset melepas ikatan perangkat/sesi serta mencatat petugas dan alasannya.
Jawaban, waktu mulai, hitungan kejadian, dan status tahanan tetap dipertahankan.
Jika peserta terblokir, **Buka Ujian** tetap diperlukan secara terpisah.
Auto refresh berhenti mengganti isi monitoring selama formulir tindakan terbuka,
termasuk jika formulir dibuka saat permintaan pembaruan sedang berjalan.

Verifikasi tambahan: **8 tes backend / 173 assertion lulus**, mencakup asesmen
tanpa ruang, ujian terpusat, penolakan pengguna pemantau, validasi alasan,
peserta/ruang selesai, pelestarian jawaban dan riwayat, serta masuk dari perangkat
pengganti melalui API. Log: `web-monitoring-reset.log` pada direktori bukti audit.

Tambahan tombol web tidak mengubah kode produksi Android. **AAB 1.0.8+9 tetap
dapat dipakai tanpa build ulang**; perbarui backend, bersihkan cache, lalu
muat ulang halaman monitoring web.

### Penyelarasan alasan pembukaan (perubahan berikutnya)

Semua pembukaan tahanan kini wajib menyertakan **Alasan pembukaan**, 10–500
karakter setelah spasi di awal/akhir dibuang. Berlaku pada monitoring CBT/
asesmen web, Tugas Pengawas web, monitoring asesmen Android, Tugas Pengawas
Android, dan pusat pelaksanaan ujian terpusat Android. Pembukaan memakai
`alasan_pembukaan` pada web maupun API; layanan inti juga menolak alasan kosong,
terlalu pendek, atau terlalu panjang. Tidak ada alasan otomatis untuk membuka
tahanan. Pembatalan formulir tidak mengirim pembukaan.

Alasan disimpan pada catatan `buka_mode_aman`, bersama identitas petugas dan
waktu pembukaan. Jawaban, waktu mulai, serta jumlah kejadian tidak direset.
Formulir web memakai konfirmasi dan penghentian auto refresh saat terbuka;
ketiga jalur Android memakai dialog yang sama, dapat digulir ketika keyboard
muncul. Alur Reset Perangkat tetap terpisah.

**Perubahan kewajiban alasan memerlukan backend dan build Android baru.**
AAB 1.0.8+9 pertama belum berisi formulir alasan maupun perataan soal.
Keduanya kini dimuat dalam **AAB 1.0.8+9 yang dibangun ulang** dengan nama sama;
gunakan checksum terbaru pada panduan rilis. Setelah backend diperbarui, aplikasi
petugas lama yang membuka ujian tanpa alasan akan menerima validasi 422;
gunakan web yang sudah diperbarui sampai aplikasi petugas ikut diperbarui.
Pengerjaan siswa tidak bergantung pada formulir alasan milik petugas.

Verifikasi: **93 tes backend / 1.668 assertion** dan **14 tes Flutter lulus**,
mencakup kewajiban alasan, hak petugas, pencatatan alasan, pelestarian sesi,
ketiga formulir native, pengiriman alasan lewat Dio, pembatalan, dan layar
320 × 640 saat keyboard muncul. Bukti:
`unlock-reason-backend.log`, `unlock-reason-flutter.log`, dan
`unlock-reason-analyze.log` pada direktori bukti audit. Build AAB terbaru
sudah selesai; pembaruan server produksi atau uji pada HP fisik belum dilakukan.

Setelah memperbarui kode backend di server sekolah, jalankan dari root Laravel:

```bash
php artisan migrate --force
php artisan optimize:clear
```

Untuk memeriksa hasil, buka detail asesmen/monitoring. Kebijakan harus 3/tahan;
peserta yang ditahan memiliki tombol Buka Ujian bagi petugas berwenang.
Reset Perangkat tidak menggantikan Buka Ujian.

Perbaikan native sudah dikemas pada **AAB 1.0.8+9 tanggal 9 November 2026**;
berkas dan catatan perubahannya ada di [panduan rilis Android](rilis-android-play-store.md).
**AAB 1.0.7+8 tanggal 5 November belum berisi perbaikan ini.** Pekerjaan build
belum mengunggah AAB ke Play, menjalankan migrasi produksi, atau mengubah data sekolah.

Uji perangkat sebelum publikasi: gunakan asesmen dari web, lakukan dua interval
keluar/kembali, lalu kejadian ketiga; soal harus tetap tertutup saat jaringan
diperlambat atau diputus. Pulihkan jaringan; tahanan harus tetap tampil sampai
dibuka petugas. Periksa bahwa draft dan waktu tetap mengikuti sesi server.
Ulangi pada ujian terpusat serta pemilih berkas yang memiliki izin sah.

## Verifikasi

- **66 tes backend / 928 assertion lulus**: asesmen web/API, ujian siswa,
  pelaksanaan terpusat, kebijakan server terhadap klien lama, serta migrasi dan
  pelestarian jawaban/keputusan pengawas.
- **84 tes Flutter lulus**: 53 tes pengerjaan/jurnal/proteksi/form asesmen dan
  31 tes draft, navigasi, pengawasan, monitoring asesmen, serta media soal.
- `flutter analyze --no-pub`: bersih. Pint lulus.
- Regresi baru mencakup respons penahanan ketiga yang ditunda pada alur kelas
  dan terpusat, kembali saat offline dan retry, dua siklus fokus cepat, serta
  pembukaan sah oleh pengawas. Tes offline lama disesuaikan: halaman ujian tetap
  terjaga dan UUID retry tetap sama, tetapi soal kini wajib tersembunyi.

Log ada di `mobile/build/security-audit-2026-11-09/`:
`backend-fix-tests.log`, `flutter-fix-tests.log`, `flutter-fix-regression.log`,
dan `flutter-fix-analyze.log`. Semua pengujian memakai data fiktif, SQLite dalam
memori, atau server tiruan. Verifikasi ini tidak menyatakan pengiriman AAB ke
Play atau pengujian fisik pada HP dalam laporan sudah dilakukan.
