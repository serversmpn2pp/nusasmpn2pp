# Tema Kelompok Fitur Admin

Kelanjutan kerangka admin, komponen bersama, dan percontohan Data Sekolah.
Tema telah diperluas dari administrator ke seluruh akun terautentikasi. Izin, menu, endpoint/API, filter data,
perhitungan, validasi, dan proses penyimpanan tidak diubah.

## Penerapan

Halaman memilih kelompok melalui section Blade `admin-module`. Layout memeriksa
allowlist kelompok serta status autentikasi sebelum memuat
`public/css/admin-feature-pages.css`. Asset memakai versi `filemtime`.
Tidak ada daftar izin/rute baru yang perlu disinkronkan dengan menu.

282 halaman memakai penanda kelompok:

| Kelompok | Cakupan |
| --- | --- |
| Akademik | Mapel, penugasan, jadwal, komponen/bobot/input nilai, rapor/leger STS, perangkat ajar, survei |
| Kehadiran | Jadwal, rekap harian/laporan siswa dan pegawai, ibadah, pengecualian presensi |
| Kesiswaan & BK | Laporan, pemeriksaan, penanganan, poin, sanksi, pendampingan, penugasan dan pengaturan |
| Ujian & Asesmen | Pusat CBT, ujian terpusat, asesmen kelas, bank/pemilihan soal, monitoring, hasil dan presensi ujian |
| Humas | Dashboard, arsip, program, agenda, kemitraan, komite, alumni, prestasi, publikasi, pengaduan dan evaluasi |
| Supervisi | Instrumen, program, jadwal, praobservasi, penilaian, pascaobservasi, tindak lanjut dan rekap |
| Persuratan | Arsip, form/detail surat, disposisi dan tindak lanjut |
| Sarpras | Master, inventaris/unit, stok, pengadaan, peminjaman/pengembalian dan laporan |
| Sistem | Akun pegawai/siswa/orang tua, peran, aktivitas login, backup/restore |

Lampiran perilaku STS memakai kelompok BK, meskipun view berada di folder rapor.
Master kategori/satuan/sumber inventaris menggunakan view `inventaris-master`.

## Pola Tampilan

- Judul, aksi halaman, ringkasan, filter berwarna biru muda, dan area data memakai ritme jarak bersama.
- Form GET yang merupakan filter diberi `feature-filter`; POST dan submit pengesahan tidak diubah.
- Bagian formulir/detail standar memakai `feature-section`: bidang putih penuh,
  judul berwarna, tanpa menambahkan kartu di dalam kartu.
- Biru untuk identitas/aksi utama, teal untuk kesiapan/berhasil, kuning untuk
  perhatian/draf; status berbahaya tetap merah. Warna tidak menggantikan label.
- Tabel khusus Humas, Supervisi dan Persuratan memakai alias tipografi bersama.
  Struktur kolom, editor soal, formulir penilaian, dialog dan tampilan mobile
  bawaan setiap fitur tetap dipertahankan.

## Halaman Padat

- Input nilai tetap satu kolom penuh. Identitas komponen dan publikasi terpisah
  dari daftar nilai. Input desimal/predikat/catatan memiliki nama aksesibel per siswa.
  Desktop: header tabel dan baris simpan tetap terjangkau saat menggulir daftar.
  Ponsel: daftar mengikuti susunan per siswa; simpan tidak menutupi isi.
- Presensi siswa/pegawai dan monitoring CBT memakai `feature-worktable`:
  gulir di area tabel dengan header serta identitas tetap terlihat.
  Area memiliki nama aksesibel dan dapat difokuskan melalui keyboard.
  Daftar mobile yang sudah tersedia tidak diganti dengan tabel desktop.
- Leger tetap matriks dengan kolom ranking/nama tetap; angka dan metadata
  diperjelas tanpa mengubah pembagi, ranking sementara, atau nilai kosong.
- Identitas ujian, token, status kesiapan dan progres jawaban diperjelas;
  polling, batas waktu, koreksi, dan pemberian waktu tambahan tidak diubah.

## Batas Dan Verifikasi

CSS tambahan dibatasi `@media screen`, `nusa-admin-shell` dan
`admin-feature-pages`. Dashboard admin, Data Sekolah, login,
hasil cetak, kartu/barcode fisik, pemindai mandiri, serta layar pengerjaan
ujian siswa tidak menerima aturan tata letak ini.

- `TampilanKelompokFiturAdminTest`: render daftar, formulir dan data operasional,
  satu asset/satu sidebar, token CSRF, input desimal dan pembatasan akun nonadmin.
- `NUSA_CAPTURE_FEATURE_GROUPS=1` menghasilkan fixture Blade nyata pada
  `storage/framework/testing/feature-groups`, menggunakan SQLite pengujian.
- `tests/Browser/feature-groups.mjs`: desktop/ponsel, luapan teks/halaman,
  asset, ukuran kontrol, input desimal, perlindungan perubahan belum disimpan,
  header tabel dan identitas yang dipertahankan saat menggulir.
  Hasil/screenshot berada di `storage/logs/feature-groups-qa`.

Untuk halaman baru, gunakan penanda kelompok dan komponen UI bersama.
Jangan menambahkan aturan global untuk semua `table`, `input`, `dialog`,
`canvas`, atau layout cetak. Perubahan alur kerja merupakan pekerjaan terpisah
dari penerapan tema.
