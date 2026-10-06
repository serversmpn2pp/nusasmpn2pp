# Humas native NUSA Mobile

Implementasi menggunakan API `/api/v1/humas` yang sudah tersedia. Alur dan aturan
web tetap menjadi sumber kebenaran; tidak membuat tabel, role, atau alur
penanganan alternatif di Flutter.

## Menu yang tersedia

Membuka kategori Humas langsung menampilkan ringkasan dan kartu menu berikon,
bukan halaman dropdown bertingkat. Menu disaring oleh server menurut izin dan
identitas akun. Detail native juga memeriksa identitas/izin sebelum meminta API.

| Akun | Menu | Kemampuan native |
| --- | --- | --- |
| Petugas berizin | Dashboard Humas | Ringkasan periode/tahun, perhatian, agenda mendatang |
| Petugas berizin | Agenda & Pertemuan | Daftar, tambah/ubah agenda, notulen, status, peserta, koreksi presensi, tindak lanjut, akses/QR presensi orang tua, baca dokumen terkait |
| Petugas berizin | Pusat Dokumen Humas | Cari, baca, unduh, unggah, koreksi metadata, revisi berkas, arsip, unduh versi lama |
| Petugas berizin | Aspirasi & Pengaduan | Tiket, lampiran sesuai izin, riwayat, balasan resmi pengelola, disposisi, proses, usulan selesai, penutupan/pembukaan kembali sesuai status dan izin |
| Petugas berizin | Rekap Umpan Balik | Statistik agregat, masukan tertulis tanpa identitas, baca tindak lanjut, buka/tutup/perpanjang/arsip evaluasi |
| Orang tua terhubung | Undangan Pertemuan Saya | Undangan milik akun, kehadiran, scan QR dan konfirmasi hadir |
| Orang tua terhubung | Aspirasi & Pengaduan Saya | Kirim laporan dengan lampiran, pilihan kerahasiaan, status, riwayat komunikasi, informasi tambahan |
| Orang tua terhubung | Umpan Balik Saya | Formulir sasaran milik akun, jawaban satu kali, riwayat, ringkasan tindak lanjut publik |

`wakil_pimpinan_humas` mendapat rekomendasi akses cepat Humas jika berizin.
Akses cepat yang sudah diatur sendiri tidak diganti. Menu administrasi Humas
tidak ditampilkan kepada siswa/orang tua meskipun keliru diberi role petugas.
Role bernama `orang_tua` saja tidak memberikan identitas orang tua.

## Aturan implementasi

- Pencarian memakai jeda 550 ms; hasil permintaan lama tidak menimpa hasil baru.
- Daftar dan koleksi detail mengikuti paginasi server; tombol muat berikutnya
  tidak membatasi data ke halaman pertama.
- Dropdown mengikuti komponen NUSA, halaman dapat digulir, dan form tetap dapat
  digunakan saat keyboard terbuka. Pertanyaan evaluasi panjang tampil terpisah
  dari label input agar tidak terpotong.
- UUID laporan/pesan/jawaban dipertahankan selama form yang sama dibuka, termasuk
  ketika mencoba ulang setelah gangguan jaringan. Tombol simpan diblokir selama
  pengiriman. UUID ini **bukan** janji deduplikasi untuk agenda/dokumen, karena
  API keduanya tidak menggunakan UUID. Periksa daftar sebelum mengulang unggahan
  atau pembuatan agenda yang responsnya terputus.
- Koreksi membawa `versi`, `versi_presensi`, atau `sidik` asli. Penolakan konflik
  ditampilkan tanpa diam-diam mengganti versi dan menimpa perubahan orang lain.
- Unduhan menggunakan jalur API lokal dengan autentikasi, bukan menambahkan
  token ke URL berkas yang diberikan server. Lampiran pelapor tidak disimpan
  dalam cache bersama, log, atau penyimpanan preferensi aplikasi.
- QR hanya menerima URL pertemuan pada host API yang dikonfigurasi. Kamera
  menampilkan konfirmasi undangan sebelum mengirim kehadiran; bukan scanner kartu siswa.
- Notifikasi Humas yang memiliki detail native diarahkan ke halaman terkait.
  Keberhasilan push tetap bergantung pada konfigurasi FCM dan worker yang sudah ada.

## Batas tahap ini dan rekomendasi berikutnya

Tidak semua modul Humas web dinyatakan tersedia di mobile. Penyusunan pertanyaan
evaluasi, penentuan sasaran undangan, hubungan dokumen ke agenda, bundel arsip
pertemuan, serta pengelolaan tindak lanjut evaluasi tetap melalui web.

Urutan native yang disarankan setelah pengujian pengguna:

1. **Publikasi & Persetujuan**: kirim bahan publikasi dari HP, pemeriksaan,
   persetujuan pimpinan, dan status terbit. Jangan menerbitkan otomatis tanpa
   alur persetujuan web.
2. **Buku Tamu Digital**: pencatatan tamu di meja penerima, tujuan kunjungan,
   penerima, dan waktu masuk/selesai; mengikuti izin penerima tamu web.
3. **Kemitraan & MoU**: kontak mitra, dokumen, masa berlaku, pengingat kedaluwarsa,
   dan agenda terkait; cocok untuk pemantauan saat petugas di luar sekolah.
4. **Bank Dokumentasi**: unggah foto kegiatan dengan metadata dan izin; terapkan
   kompresi/batas unggahan dan perlindungan foto siswa sebelum implementasi.

Portofolio akreditasi, laporan program yang panjang, konfigurasi media resmi,
dan pengelolaan arsip massal lebih nyaman tetap di web. API native untuk modul
lanjutan perlu disediakan dan diuji dahulu; tidak ada tombol placeholder yang
menyatakan modul itu sudah tersedia.

## Pengujian dan penerapan

Tes baru: `mobile/test/humas_view_test.dart` dan
`tests/Feature/Api/MenuHumasApiTest.php`. Tes notifikasi dan jumlah/menu katalog
yang sudah ada disesuaikan. Tes API Humas existing tetap dipakai untuk validasi
kepemilikan, privasi, konflik versi, dan layanan yang dipakai kedua platform.

Sesudah perubahan kode diperiksa dan di-pull ke server, jalankan migrasi Humas
web yang memang belum diterapkan serta hapus cache konfigurasi/rute:

```sh
php artisan migrate --force
php artisan optimize:clear
```

Jangan menjalankan ulang seluruh seeder di server produksi hanya untuk menu.
Bangun/pasang APK baru dari komputer pengembang agar halaman Flutter tersedia.
API dan konfigurasi menu juga harus sudah terpasang di server yang dipilih.

### Pembukaan dan penyegaran menu native

Kategori Humas dan tautan lama `/menu/humas` membuka dashboard yang sama
di `/humas`. Dashboard tidak lagi menjadi kartu perantara; ringkasannya
langsung tampil bersama menu Agenda, Dokumen, Pengaduan, dan Umpan Balik.
Kartu menggunakan komponen dan ikon NUSA yang sama dengan menu lain.
Kode kartu dipetakan ke modul native dan diperiksa lagi terhadap identitas
serta izin akun. Kartu tanpa rute/berstatus belum tersedia tidak menyebabkan
crash. Tarik muat ulang dan tombol refresh memperbarui katalog serta ringkasan;
ringkasan juga diperbarui setelah pengguna kembali dari submenu.

Tes widget memakai router aplikasi untuk memverifikasi klik semua kartu
petugas/orang tua sampai daftar dan detail, pengalihan tautan lama, penyegaran,
serta pemisahan menu petugas dan orang tua. Perubahan penyegaran/menu ini
tidak menambah migrasi atau mengubah API/backend.

Uji dengan petugas Humas, petugas penanganan yang ditugaskan, serta dua akun
orang tua berbeda. Uji kamera QR dan unggah/unduh di HP sungguhan; tes widget
tidak membuktikan perilaku kamera/izin penyimpanan setiap perangkat.
