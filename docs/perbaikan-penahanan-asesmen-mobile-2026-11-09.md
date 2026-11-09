# Penahanan asesmen dan verifikasi ulang layar ujian

Tanggal: 9 November 2026. Tindak lanjut dua temuan dalam
[audit penahanan](audit-penahanan-asesmen-mobile-2026-11-09.md).

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

Setelah memperbarui kode backend di server sekolah, jalankan dari root Laravel:

```bash
php artisan migrate --force
php artisan optimize:clear
```

Untuk memeriksa hasil, buka detail asesmen/monitoring. Kebijakan harus 3/tahan;
peserta yang ditahan memiliki tombol Buka Ujian bagi petugas berwenang.
Reset Perangkat tidak menggantikan Buka Ujian.

Build dan distribusikan AAB baru untuk perubahan native. **AAB 1.0.7+8 tanggal
5 November belum berisi perbaikan ini.** Pekerjaan ini belum membuat AAB baru,
menjalankan migrasi produksi, atau mengubah data sekolah.

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
