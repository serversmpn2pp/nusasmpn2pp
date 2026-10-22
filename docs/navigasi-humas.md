# Navigasi Humas

## Cakupan Tahap Pertama

Dashboard menampilkan tujuh bidang kerja dengan ikon dan warna yang berbeda.
Sidebar menggunakan daftar menu yang sama dari `NavigasiHumasService`.
Setiap bidang hanya tampil jika minimal satu layanan di dalamnya diizinkan.
Hak akses diperiksa per layanan, bukan berdasarkan nama role Humas semata.

| Bidang | Layanan |
| --- | --- |
| Dokumen Kedinasan Humas | Program kerja, laporan kinerja semester, pusat dokumen, portofolio akreditasi, direktori kontak |
| Dokumen Komite Sekolah | Kepengurusan/program komite, rapat komite |
| Hubungan Orang Tua/Wali | Pertemuan orang tua, umpan balik |
| Kemitraan & Kerja Sama | Mitra/MoU, pertemuan mitra |
| Publikasi & Promosi Sekolah | Publikasi/persetujuan, aset, media resmi, kliping, prestasi |
| Database & Portofolio Alumni | Database alumni |
| Aspirasi & Pengaduan | Tiket dan penanganan pengaduan |

Agenda umum, buku tamu, dan FAQ tetap tersedia sebagai layanan pendukung. Statistik,
periode, cetak ringkasan, URL modul, data, dan format cetak lama tetap dipertahankan.
Pertemuan komite/orang tua/mitra membuka agenda dengan filter jenis yang sudah
didukung. Pada halaman detail, satu menu aktif dipilih berdasarkan jenis agenda.
Label ruang alumni tidak menambahkan alur portofolio baru pada tahap ini.

## Audit Akses Lokal

Audit tanggal 22 Oktober 2026 dilakukan dalam transaksi PostgreSQL baca-saja,
lalu dibatalkan. Tidak ada role, izin, akun, atau data sekolah yang diubah.
Satu akun Wakil Humas ditemukan; akun dan profil pegawainya aktif. Role memiliki
izin pengelolaan seluruh modul Humas yang sudah tersedia, termasuk dashboard.

Izin `publikasi_humas.periksa` tetap terpisah dari role Wakil Humas. Persetujuan
konten tidak diberikan otomatis. Pengiriman aduan oleh orang tua, pembatasan
identitas pelapor, serta akses siswa/orang tua tidak diperluas oleh navigasi ini.
Audit ini tidak membuktikan status akun/izin pada server sekolah yang berbeda.

## Klasifikasi dan Kaitan Arsip

Tahap kedua memisahkan bidang urusan dari jenis dokumen. Setiap bidang pada
dashboard/sidebar memiliki tautan arsip berfilter urusan. Pusat dokumen tetap
menampilkan seluruh urusan yang boleh diakses oleh petugas dokumen.

Dokumen dapat dikaitkan dengan agenda, program Humas, program komite, dan surat
TU tanpa menyalin atau memindahkan berkas. Akses sumber tetap diperiksa; tautan
surat rahasia tidak diberikan kepada pengguna di luar cakupannya. Klasifikasi
bukan izin baru untuk membaca isi pengaduan atau data orang tua.

Arsip lama ditata melalui perintah terpisah, bukan dengan mengganti kategori
lama saat migrasi. Metadata hasil pemetaan tetap perlu dikonfirmasi petugas.
Lihat `arsip-humas.md` untuk aturan pemetaan dan pemasangan.

## API dan Batasan

API dashboard menambahkan metadata `ruang_kerja` dari sumber yang sama. Menu
native lama tetap kompatibel. Modul tanpa layar native tidak ditandai tersedia
untuk Android. Lihat `api-humas.md` untuk kontrak respons.

API dokumen mendukung klasifikasi, penataan kolektif, dan hubungan sumber.
Metadata arsip per bidang memakai menu native dokumen yang sama dengan filter
`urusan`. Penambahan kontrak API tidak otomatis memperbarui layar APK yang sudah
terpasang. Tahap operasional menambahkan direktori kontak pada bidang Kedinasan,
paket pertemuan di agenda, pendukung MoU, serta akses publikasi milik sendiri.
Lihat `operasional-humas.md`. FAQ dan laporan kinerja semester ditambahkan melalui
migrasi tahap keempat; lihat `faq-kinerja-humas.md`. FAQ tersedia di Akun Saya untuk
siswa/orang tua, dengan isi terbit sesuai pembaca, bukan navigasi petugas Humas.

## Setelah Pull

Tahap pertama hanya membutuhkan `php artisan optimize:clear`. Mulai tahap kedua,
cadangkan database, jalankan migrasi, dan pratinjau penataan arsip lama sebelum
menerapkannya. Urutan lengkap tersedia di `arsip-humas.md`. Tidak perlu menjalankan
seeder atau memasang ulang role untuk perubahan ini.

Tahap operasional membutuhkan migrasi `2026_10_22_000002_lengkapi_operasional_humas`.
Role Kontributor Publikasi disiapkan tanpa otomatis dipasangkan kepada guru mana
pun. Administrator dapat memasangnya melalui pengelolaan akun/role yang sudah ada.

FAQ/laporan semester membutuhkan migrasi `2026_10_22_000003_buat_faq_dan_kinerja_semester_humas`.
Tidak ada FAQ, laporan, atau isi kebijakan contoh yang ditambahkan ke data sekolah.
