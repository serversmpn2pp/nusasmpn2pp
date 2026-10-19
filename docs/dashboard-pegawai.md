# Dashboard Pegawai

Dashboard web pegawai (`beranda.index`) mengikuti pratinjau yang disetujui:
putih, biru #15477A, teal #0F766E, dan kuning #F1C40F. Sidebar, header,
profil, serta navigasi menggunakan kerangka bersama NUSA. Tidak ada pemilih
role atau angka contoh pada dashboard sebenarnya.

## Isi dan Lingkup

- Presensi pribadi bulan berjalan sampai hari ini: hadir, izin/sakit,
  jumlah terlambat, dan total menit terlambat. Catatan tanggal mendatang
  tidak dimasukkan. Hari tanpa catatan tidak diasumsikan hadir.
- Grafik keterlambatan pribadi: tujuh hari kalender terakhir atau bulan
  berjalan, dengan pilihan batang/garis dan rincian teks yang dapat dibuka.
  Hari tanpa scan hadir bernilai `null`, bukan nol. Scan tepat waktu bernilai
  nol; grafik batang menampilkan penanda kecil untuk membedakannya.
- Presensi hari ini menggunakan jadwal yang terhubung pada catatan scan.
  Jika belum ada catatan, prioritas jadwal adalah pegawai tertentu,
  jenis pegawai, lalu semua pegawai, sesuai proses scan.
- Agenda hanya penugasan sendiri: pelajaran aktif tahun ajaran aktif,
  supervisi yang sudah diterbitkan, dan surat tugas disahkan/dikirim yang
  berlangsung hari ini. Piket tetap tampil jika memang ditugaskan.
- Tindak lanjut mencakup komponen nilai aktif yang belum lengkap untuk
  siswa aktif, surat tugas belum dibaca, disposisi/laporan sendiri,
  draf surat sendiri bagi pengelola, pengesahan yang ditujukan ke akun,
  serta pengisian/refleksi/tindak lanjut supervisi sesuai hak penugasan.
  Angka ini adalah jumlah tindakan, bukan jumlah dokumen unik.
- Nilai nol tetap merupakan nilai terisi. Komponen nonaktif atau penilaian
  bertanggal mendatang tidak ditandai belum lengkap.
- Perwalian dan pembinaan mempertahankan sumber data serta lingkup
  kelas/siswa dampingan yang sebelumnya digunakan dashboard.
- Aktivitas terbaru berasal dari lima notifikasi akun login. Membukanya
  melalui endpoint notifikasi yang sudah ada, termasuk pemeriksaan pemilik
  dan keamanan tautan. Tidak ada penulisan data saat memuat dashboard.

Dashboard admin, siswa, orang tua, layar scan, cetak, dan ujian tidak
diganti oleh perubahan ini. Ini bukan perubahan kontrak API mobile.

## Berkas

- `app/Services/DashboardPegawaiService.php`: data personal, agenda, tugas.
- `resources/views/beranda/index.blade.php`: tampilan dashboard.
- `resources/views/beranda/partials/perwalian-pegawai.blade.php`: perwalian.
- `public/css/dashboard-pegawai.css`: gaya terisolasi dan responsif.
- `public/js/dashboard-pegawai.js`: Chart.js lokal, kontrol rentang/jenis.

## Pengujian

Jalankan `DashboardPegawaiTest` untuk angka, rentang tanggal, hak akses,
penugasan, nilai nol, perwalian, dan keadaan kosong.

Untuk pemeriksaan Chrome, set `NUSA_CAPTURE_DASHBOARD_PEGAWAI=1` ketika
menjalankan test tersebut, lalu jalankan `tests/Browser/dashboard-pegawai.mjs`
dengan `PLAYWRIGHT_MODULE` dan `PLAYWRIGHT_CHANNEL` sesuai mesin.
Fixture hanya menggunakan database pengujian; tidak membuat akun di
database sekolah. Audit memeriksa 4 keadaan akun pada 7 ukuran layar,
termasuk 1024x768, dengan kedua rentang dan jenis grafik.

## Penerapan

Tidak ada migrasi, seeder, atau build npm baru. Asset berada di `public/`
dan URL-nya menggunakan versi waktu berkas. Setelah pull, jalankan
`php artisan optimize:clear` dan muat ulang halaman.
