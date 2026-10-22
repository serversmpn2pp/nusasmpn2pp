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
| Semua akun aktif | FAQ & Bantuan | Cari/filter urusan, baca FAQ terbit sesuai identitas; editor Humas dapat membuat, mengoreksi, menerbitkan, mengarsipkan, dan melihat riwayat |
| Petugas kinerja berizin | Laporan Kinerja Semester | Filter tahun/semester/status, baca snapshot/evaluasi, penyusunan draf, koreksi, perbarui rekap sumber, finalisasi/revisi sesuai izin |
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
- Arsip native menampilkan serta memfilter urusan/jenis dokumen. Form metadata
  dan unggahan memakai pilihan klasifikasi dari API; kategori lama tetap
  dipertahankan untuk kompatibilitas. Meninjau klasifikasi massal dan mengaitkan
  sumber baru tetap melalui web, bukan tindakan otomatis dari client.
  Menyimpan judul/metadata arsip lama tidak otomatis mengonfirmasi klasifikasi:
  kolom klasifikasi yang tidak berubah tidak dikirim ulang, kecuali petugas
  sengaja menyalakan konfirmasi. Dokumen baru memerlukan pilihan eksplisit.
- FAQ tersedia untuk siswa/orang tua, tetapi tidak memberikan hak pengelolaan
  sekalipun role petugas/administrator keliru terpasang. Laporan semester tetap
  khusus petugas berizin. Tombol pengelolaan laporan juga membutuhkan
  `dapat_kelola=true` dari server agar pencabutan izin sumber langsung dihormati.
- Finalisasi laporan sementara memerlukan konfirmasi eksplisit. Setelah konflik
  versi, client mempertahankan versi asli dan meminta pengguna memuat ulang;
  tidak menimpa data dengan mengambil versi baru diam-diam.
- Unduh lembar cetak laporan mengambil HTML dari endpoint cetak terautentikasi
  yang sama dengan web. Tidak membuat ulang PDF/rapor menggunakan tata letak
  Flutter yang berbeda. Berkas HTML dapat dibuka/cetak dari browser; PDF tetap
  melalui cetak web. Ini bukan fasilitas pencetakan PDF native tanpa browser.
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

## Batas Tahap Awal

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

Portofolio akreditasi, laporan pelaksanaan program yang panjang, konfigurasi media resmi,
dan pengelolaan arsip massal lebih nyaman dilakukan di web untuk pekerjaan
panjang. Cakupan native yang telah ditambahkan kemudian tercatat pada bagian
tahap 1 dan 2 di bawah; modul lain tetap berlabel Web sampai benar-benar tersedia.

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

### Penyelarasan Tujuh Bidang dan FAQ/Kinerja

Dashboard menampilkan bidang dari `ruang_kerja` API sesuai izin. Tautan yang sudah
native membuka modul yang sama, termasuk filter `urusan` pada arsip dan `jenis`
pada pertemuan. Layanan yang masih web diberi penanda Web, tidak dijadikan tombol
native palsu. FAQ serta laporan semester kini memiliki rute native
`/humas/faq` dan `/humas/kinerja`; API Humas kini berjumlah 179 endpoint, termasuk
dua endpoint riwayat untuk kontak dan publikasi native.

API FAQ/laporan/riwayat menggunakan `halaman` (mulai 1) dan `per_halaman` (1-50,
default 20). Alias lama `page` tetap diterima, dengan prioritas `halaman` bila
keduanya ada. Halaman web tetap menggunakan `page` dengan ukuran 15. Respons
FAQ menyediakan label urusan/pembaca/status dan `dapat_kelola`; laporan memiliki
label status/semester, penanda sementara, serta batas tanggal data.

Referensi laporan juga memberikan `dapat_himpun`, yang membutuhkan izin kelola
laporan dan akses program kerja sumber. Tombol tambah mengikuti keputusan server,
bukan hanya izin yang masih tersimpan pada sesi client. Daftar/riwayat lama
dihapus dari layar ketika pemuatan ulang ditolak karena autentikasi/izin.

Tes kontrak `HumasMobileAlignmentTest` memeriksa admin, Wakil Humas, pimpinan,
guru, TU, BK, kontributor publikasi, pegawai, dua akun orang tua, dan siswa.
Selain sebelas skenario identitas tersebut, seluruh role aktif dari database
pengujian diperiksa keselarasan izin, katalog menu, dan akses endpointnya.
Fixture `mobile/test/fixtures/humas_api_contract.json` hanya berasal dari database
pengujian sintetis, tanpa kata sandi atau bearer token, lalu dibaca langsung oleh
`mobile/test/humas_alignment_test.dart`. Untuk memperbarui fixture secara sengaja:

```powershell
$env:NUSA_CAPTURE_HUMAS_MOBILE='1'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --filter=HumasMobileAlignmentTest
cd mobile
flutter analyze --no-pub
flutter test --no-pub test/humas_view_test.dart test/humas_alignment_test.dart
```

Tes mencakup paginasi halaman kedua, status draf langsung setelah create,
pencabutan izin, UUID retry, status final terkunci, konfirmasi sementara, filter
arsip dari bidang, akses cetak, dan lebar 320/390/1024px dengan skala teks 1-2.
HTML cetak API juga dibandingkan dengan HTML web untuk tiga role petugas.

Pembaruan ini tidak menambah migrasi. Setelah pull backend gunakan
`php artisan optimize:clear`; APK perlu dibangun dan dipasang ulang. Jangan
menjalankan seeder atau memperluas role pengguna hanya untuk menampilkan menu.

### Konsistensi Menu Humas Android

Header dan grid Humas memakai `NusaMenuGroupHeader` / `NusaSubmenuGrid` yang
sama dengan Data Sekolah. Tujuh bidang dari API tampil sebagai judul bagian
dan kartu ikon langsung, tanpa accordion. Menu native yang sudah tercantum
dalam bidang tidak digandakan sebagai pintasan. Arsip/pertemuan dengan filter
berbeda tetap memiliki tombol tersendiri, dan filter `urusan`/`jenis` tetap
diteruskan ke halaman native.

Fitur yang masih web memiliki ikon dan penanda `Web`; menekannya memberi
informasi ketersediaan web tanpa mencoba rute/API yang belum didukung Android.
Jumlah fitur pada header menghitung tombol unik yang benar-benar ditampilkan.
Saat dashboard gagal dimuat, menu native dari katalog tetap dapat digunakan.
Akun tanpa izin dashboard tidak meminta endpoint dashboard dan tidak menerima
bidang petugas. Katalog yang sudah mencabut akses native mengalahkan tautan
dashboard lama. Refresh, periode ringkasan, dan navigasi kembali tetap tersedia.

Grid menyesuaikan tinggi kartu dan jumlah kolom untuk teks besar. Tes kontrak
memeriksa sebelas identitas akun, deduplikasi, filter arsip, fitur web, serta
lebar 320/390/600/1024px dengan skala teks 1 dan 2. Ini perubahan Flutter;
pasang APK hasil build baru untuk melihatnya di HP. Tidak ada migrasi baru.

### Native Tahap 1: Direktori Kontak dan Publikasi

- `/humas/kontak`: pencarian, filter urusan/status, daftar, detail, tambah/edit,
  pengarsipan melalui status, serta riwayat perubahan berhalaman.
- Telepon, salin nomor dan email hanya tampil jika server mengizinkan kontak
  privat. Identitas kontak tetap dapat dibaca oleh pemantau berizin lihat.
- `/humas/publikasi`: pencarian, filter jenis/media/status, detail naskah dan
  foto privat, tambah/edit draf, serta riwayat perubahan berhalaman.
- Foto dapat diambil dari kamera, galeri atau berkas HP. Maksimal 5 foto aktif,
  2 MB/foto. Kesalahan pemilihan/unggah tidak menghapus isi formulir.
- Agenda terkait dapat dicari sesuai izin; aset promosi dapat dipilih/diubah
  secara eksplisit, tanpa mengganti snapshot lama ketika hanya mengedit naskah.
- Ajukan, tarik, minta revisi, setujui, buka revisi dan catat bukti tayang mengikuti
  hak/status dari server. Kontributor hanya mengakses miliknya, tidak menyetujui
  sendiri atau mencatat tayang. Notifikasi publikasi membuka detail native.
- Unduhan foto/bukti/naskah selalu memakai endpoint lokal dengan autentikasi,
  bukan menambahkan token ke URL yang dikirim server.

UUID pembuatan tetap sama ketika mencoba ulang. Edit/tindakan mempertahankan
versi asli; konflik meminta pengguna memuat ulang, tidak menimpa perubahan orang
lain. Penyimpanan menonaktifkan kendali berkas dan tindakan ganda.

`HumasNativeTahapSatuTest` menguji role aktif, kontak privat, riwayat, referensi
agenda lama, kontributor, multipart foto/bukti dan tautan notifikasi. Fixture
`humas_native_tahap_satu.json` dihasilkan dari database pengujian sintetis dengan
`NUSA_CAPTURE_HUMAS_MOBILE=1`, lalu dipakai tes Flutter
`humas_native_tahap_satu_test.dart`. Tes layar mencakup 320/390/600/1024px dan
skala teks 1-2. Tangkapan native dapat dibuat dengan `NUSA_CAPTURE_HUMAS_WIDGETS=1`.

Tidak ada migrasi atau seeder baru untuk tahap ini. Setelah pull backend jalankan
`php artisan optimize:clear` dan pasang APK baru. Kamera/galeri, izin perangkat,
unduhan dan notifikasi tetap perlu uji penerimaan di HP Android sungguhan.
Modul Humas lain yang masih berlabel Web belum termasuk tahap ini.

### Native Tahap 2: Program, Komite, Kemitraan, Akreditasi

Empat menu native baru: `/humas/program`, `/humas/komite`, `/humas/mitra`,
dan `/humas/akreditasi`. Katalog menu dan tujuh bidang Humas mengikuti izin
yang sama. Halaman turunan dibuka dari detail induknya, bukan sebagai menu Web.

- Program kerja: cari/filter, tambah/edit program, target dan realisasi final,
  laporan pelaksanaan, bukti PDF/gambar, finalisasi, pembatalan, buka revisi,
  riwayat dan unduhan lembar cetak yang sama dengan web.
- Komite: periode, pengurus, nomor/tanggal SK, unggah SK atau pilih arsip,
  aktivasi dan pengarsipan, program komite, hubungan rapat serta riwayat.
  Pengurus tersimpan tidak dihapus; nonaktifkan agar riwayatnya tetap ada.
  Nomor telepon pengurus hanya dikirim kepada pengelola.
- Kemitraan: daftar mitra dan MoU, pengingat masa berlaku, unggah/pilih dokumen
  MoU, aktivasi/pengakhiran, agenda terkait serta dokumen pendukung ber-versi.
  Pelepasan hubungan tidak menghapus dokumen atau agenda aslinya.
- Akreditasi: portofolio, butir dinamis, target bukti, tautkan versi dokumen,
  periksa butir, status siap/arsip/buka revisi, riwayat, cetak dan bundel ZIP.
  Bundel tetap memerlukan izin ekspor dan akses dokumen. Portofolio siap dikunci.

Semua daftar memakai `halaman` dan `per_halaman` (1-50). UUID pembuatan dan
versi asli dipertahankan ketika mencoba ulang; konflik tidak ditimpa otomatis.
Pembacaan sumber dokumen/agenda membutuhkan izin sumber tersebut, terpisah
dari izin modul. Siswa/orang tua ditolak meskipun salah diberi role petugas.

Mutasi API menggunakan controller/layanan operasional web yang sama, bukan
aturan persetujuan baru. DTO mobile tidak mengirim snapshot audit mentah,
token pembuatan, lokasi file server atau nomor pengurus untuk pembaca biasa.
Unduhan memakai endpoint lokal dengan autentikasi. Cetakan tidak didesain ulang.

`HumasNativeTahapDuaTest` memeriksa semua role aktif, alur laporan final, komite,
MoU idempoten, pemeriksaan akreditasi, hubungan sumber, cetak dan paginasi.
Fixture sintetis `humas_native_tahap_dua.json` dipakai oleh tes Flutter
`humas_native_tahap_dua_test.dart` untuk menu, halaman turunan, konflik versi,
retry, privasi dan ukuran 320/390/600/1024px dengan skala teks 1-2.

Penerapan tahap ini memerlukan migrasi tambahan, tidak memerlukan seeder:

```sh
php artisan migrate --force
php artisan optimize:clear
```

Pasang APK baru setelah backend diperbarui. Pengujian kamera, pemilih berkas,
unduhan dan notifikasi pada HP sungguhan tetap menjadi uji penerimaan perangkat.

Verifikasi tahap 1: 414 tes backend Humas/menu/notifikasi (8.563 assertions),
640 tes Flutter, dan `flutter analyze --no-pub` lolos. Tidak ada perubahan
template cetak dalam tahap ini. APK debug dibuat ulang sesudah verifikasi.

Verifikasi tahap 2: 423 tes backend Humas/menu/notifikasi (9.469 assertions),
722 tes Flutter dan analisis statis lolos. Sebanyak 78 tes widget khusus tahap 2
mencakup formulir, hak akses, hubungan sumber dan halaman turunan.
HTML cetak program, laporan dan akreditasi identik dengan web untuk admin,
Humas dan pimpinan. Pengujian memakai SQLite/fixture sintetis, bukan data sekolah.
