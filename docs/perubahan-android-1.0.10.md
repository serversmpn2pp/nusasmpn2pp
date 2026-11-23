# NUSA Android 1.0.10 (11)

Perubahan sejak AAB lokal **1.0.9 (10)**. Untuk kolom catatan rilis di Play
Console, gunakan [versi singkat bahasa Indonesia](catatan-rilis-1.0.10.txt).

## Yang Baru Di Android

- **Tahfidz:** pada pertemuan terbuka, pembimbing bisa memilih satu siswa,
  memilih beberapa surah atau semua 37 surah Juz 30, lalu menyimpan nilai
  sekaligus. Isian kosong dilewati dan nilai 0 tetap dicatat. Setoran terdahulu
  tidak ditimpa, dan percobaan ulang batch yang sama tidak menggandakan data.
  Progres siswa dan orang tua tetap mengikuti penyelesaian pertemuan.
- **Kartu Pelajar:** sisi depan dan belakang mengikuti latar, logo, foto,
  warna, dan tata letak baku web. Pemegang izin cetak dapat mengunduh PNG per
  sisi atau PDF dua halaman, masing-masing berukuran 53,98 x 85,60 mm. QR
  memuat NISN yang sama; akun hanya-lihat tetap dapat membuka pratinjau.
- **Presensi siswa:** guru piket terjadwal dapat mengoreksi catatan manual
  hari berjalan sesuai izin. Untuk lupa atau hilang kartu, pilih **Hadir
  tepat waktu** atau **Hadir terlambat** tanpa mengisi jam datang. Tidak ada
  tambahan minimal 5 menit. Poin mengikuti aturan tanpa kartu, bukan poin
  keterlambatan scanner. Scan yang sah tetap memakai jam dan aturan scanner.
  Notifikasi siswa/wali menyebut alasan dan poin aktual; **Scan bermasalah**
  tetap masuk verifikasi, dan riwayat lama tidak diubah massal.
- **Soal matematika:** pecahan, pangkat, akar, dan notasi matematika dirender
  di pratinjau Bank Soal serta tampilan soal ujian Android, termasuk stimulus,
  pilihan jawaban, dan soal menjodohkan. Data jawaban dan penilaian tidak
  diubah untuk keperluan tampilan.
- **Bank Soal:** tersedia filter folder, termasuk soal yang belum ditempatkan
  dalam folder. Urutan bawaan dari soal paling baru dibuat; guru dapat
  membaliknya ke terlama dahulu.

## Backend Pendamping

Perubahan repositori sejak AAB sebelumnya juga mencakup penataan folder,
penyusunan paket, pengurutan jadwal, dan pemeriksaan riwayat sebelum penghapusan
jadwal ujian terpusat; Input Nilai Kolektif di web dan ekspor Excel nilai akhir
sementara dengan bobot; serta Profil Siswa Terpadu sesuai cakupan akses.
Perubahan web tersebut tidak menjadi layar native baru hanya karena AAB
diunggah.

Perbarui backend sesuai prosedur sekolah **sebelum** mendistribusikan AAB.
Sejak AAB sebelumnya ada migrasi relasi folder ujian terpusat
(`2026_11_16_000001_tautkan_folder_soal_ke_ujian_terpusat.php`) dan tabel batch
Tahfidz (`2026_11_19_000001_buat_batch_setoran_tahfidz.php`). Pada server
sekolah, periksa migrasi yang tertunda dan jalankan prosedur deploy standar:

```sh
php artisan migrate --force
php artisan optimize:clear
```

Penyimpanan kolektif Tahfidz, filter Bank Soal, dan aturan presensi baru
membutuhkan backend terbaru. Pembaruan desain dan unduh Kartu Pelajar sendiri
tidak membutuhkan migrasi atau perubahan API. Uji dari jalur internal Google
Play sebelum publikasi. Dokumen ini tidak menandakan AAB sudah diunggah.
