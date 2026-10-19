# Dashboard Siswa dan Orang Tua

Dashboard siswa dan orang tua memakai komponen yang sama dengan dashboard
pegawai: putih, biru #15477A, teal #0F766E, dan kuning #F1C40F. Nama berkas
asset bersama tetap `dashboard-pegawai.css` / `dashboard-pegawai.js` untuk
kompatibilitas. Selector tampilannya kini `.account-home`, sedangkan
`.pegawai-home` tetap ada pada halaman pegawai untuk kompatibilitas tes.

## Isi

- Identitas dan foto siswa, kelas, tahun pelajaran, dan status presensi hari ini.
- Ringkasan bulan berjalan sampai hari ini: hadir, terlambat, publikasi nilai,
  dan poin resmi. Catatan tanggal mendatang tidak dihitung.
- Jadwal pelajaran hari ini sesuai kelas anak/siswa.
- Siswa mendapatkan tautan Ujian Saya. Orang tua mendapatkan presensi anak,
  aspirasi/pengaduan, umpan balik, dan pertemuan, tanpa tombol pengerjaan ujian.
- Grafik kedatangan tujuh hari kalender terakhir atau bulan berjalan,
  dengan pilihan batang/garis dan rincian teks. Catatan tanpa jam masuk
  tidak digambar sebagai kehadiran nol menit.
- Presensi ibadah, wali kelas/guru wali, ringkasan poin beserta riwayat,
  dan notifikasi pribadi tetap tersedia.
- Notifikasi dibuka melalui POST endpoint notifikasi yang sudah ada;
  pemeriksaan pemilik serta keamanan tautan tetap digunakan.

## Pembatasan Data

Tidak ada pemilih siswa atau role baru. Pemilihan anak tetap mengikuti akun
orang tua yang sudah terhubung dan anak acuan yang dipakai halaman lain.
Parameter `siswa_id` atau `anak_id` di URL tidak mengubah lingkup dashboard.

Rapor menggunakan `PublikasiRaporStsService::untukPenerima`, bukan nilai live
atau draf. Publikasi nonaktif tidak terlihat. Publikasi orang tua saja tidak
ditampilkan kepada siswa. Ringkasan dan daftar rapor di dashboard hanya
untuk anak yang sedang ditampilkan; halaman daftar rapor tetap mengikuti
aturan penerima yang sudah ada. Tidak ada perubahan mekanisme publikasi,
format cetak, API mobile, atau izin role.

## Pengujian dan Penerapan

`DashboardKeluargaTest` menguji lingkup siswa/anak, publikasi rapor, nilai draf,
notifikasi pribadi, tanggal mendatang, pergantian bulan, dan keadaan kosong.
Jalankan dengan `NUSA_CAPTURE_DASHBOARD_KELUARGA=1` untuk menghasilkan fixture.

Pemeriksaan Chrome menggunakan `tests/Browser/dashboard-pegawai.mjs --keluarga`
dengan `PLAYWRIGHT_MODULE` dan `PLAYWRIGHT_CHANNEL` sesuai lingkungan.
Audit memakai fixture database pengujian, bukan akun/data sekolah.

Setelah pull cukup `php artisan optimize:clear`, lalu muat ulang halaman.
Tidak ada migrasi, seeder, atau build npm baru.
