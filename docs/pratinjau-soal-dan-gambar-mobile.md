# Pratinjau soal dan gambar mobile — 25 Oktober 2026

## Temuan

Detail bank soal native sebelumnya hanya mengambil media utama (`media.gambar`,
`media.tabel`, `media.rumus`). Media pada stimulus, pilihan, pernyataan benar/salah,
dan kedua sisi pasangan belum dipetakan/ditampilkan di detail. Ujian siswa sudah
membaca media per bagian tersebut, tetapi belum menyediakan pembesar gambar.

URL gambar sekarang dibentuk dari disk `public`, sama dengan disk penyimpanan
unggahan soal, sehingga tidak bergantung pada default disk aplikasi.

Temuan ini berasal dari pemeriksaan kode dan pengujian lokal. Belum ada pemeriksaan
berkas/URL soal tertentu di server produksi atau uji perangkat fisik.

## Penggunaan

- **Bank Soal:** setiap kartu memiliki tombol **Pratinjau Siswa**.
- **Detail Soal:** tombol **Pratinjau Tampilan Siswa** tersedia di bawah identitas.
- **Paket Soal:** buka Pratinjau soal, lalu **Pratinjau Tampilan Siswa**.
- Guru dapat mencoba masukan/jawaban, menandai ragu, dan mengulang pratinjau.
  Jawaban percobaan hanya ada selama halaman dibuka; tidak disimpan sebagai
  jawaban siswa. Pratinjau unggah berkas tidak membuka pemilih berkas.
- Gambar pada detail, pratinjau, dan ujian siswa ditampilkan utuh (`contain`),
  dapat diketuk/diperbesar hingga 6× dan digeser. Tombol **Muat Ulang Gambar**
  tersedia jika pemuatan gagal.

Komponen `StudentExamQuestionView` dipakai bersama oleh pratinjau dan pengerjaan
siswa, mencakup delapan bentuk soal, gambar per bagian, tabel, serta rumus LaTeX.
Nomor soal pratinjau adalah 1; urutan pilihan pada ujian dapat berbeda mengikuti
pengacakan paket/peserta. Header waktu dan kemajuan ujian tidak disimulasikan.

## API dan keamanan

`GET /api/v1/bank-soal/{soalCbt}/pratinjau`

- Memerlukan token mobile serta izin baca/kelola CBT, sama dengan detail bank soal.
- Guru dibatasi pada mata pelajaran dan tingkat yang menjadi kewenangannya.
- Mengembalikan `data.mode=pratinjau` dan `data.soal` dalam format soal siswa.
- Memakai proyeksi konten yang sama dengan API pengerjaan siswa,
  `PenyajianSoalMobileService`. Tidak menyertakan kunci, pembahasan, rubrik,
  penanda pilihan benar, atau pasangan kanan yang melekat pada butir kiri.
- Pilihan menjodohkan tetap memakai pengurutan HMAC yang sudah dipakai ujian.
- Tidak membuat peserta/ujian, mengurangi waktu, menyimpan jawaban, atau menulis
  aktivitas keamanan. Respons `no-store`.
- Pembesar gambar tetap berada di jendela Flutter. Tidak membuka browser/galeri,
  tidak mematikan proteksi layar, dan tidak memberi pengecualian kehilangan fokus.
  Pembesar ditutup jika soal dilepas dari layar, misalnya ketika sesi ditahan.

Detail bank soal tetap merupakan tampilan khusus guru dengan kunci jawaban;
media stimulus/pilihan/pernyataan/pasangan kini ikut tersedia di sana.

## Penerapan dan pemeriksaan

Perbarui backend terlebih dahulu, termasuk route dan service baru, kemudian
pasang build mobile dari sumber terbaru. Tidak ada migrasi database atau
perubahan izin Android. APK/AAB yang dibuat sebelum perubahan ini belum
memuat tombol pratinjau maupun pembesar gambar.

### Pratinjau terus loading setelah hanya Android diperbarui

Android terbaru membutuhkan route pratinjau yang baru. Bila backend belum
diperbarui, route tersebut dapat mengembalikan HTTP 404. Retry otomatis provider
sebelumnya menahan status loading selama beberapa percobaan sehingga pesan
kegagalan tidak segera terlihat.

Pratinjau kini tidak mengulang otomatis. Respons gagal langsung menampilkan
pesan dan tombol **Coba Lagi**. Permintaan dibatasi total 30 detik dan dibatalkan
jika batas itu tercapai; respons terlambat tidak membuka soal. Respons yang
tidak memuat soal yang diminta juga ditolak.

Setelah berkas backend terbaru tersinkron di server, jalankan dari folder proyek:

```sh
php artisan optimize:clear
php artisan route:list --path=api/v1/bank-soal
```

Pastikan daftar menampilkan `GET|HEAD api/v1/bank-soal/{soalCbt}/pratinjau`.
Tidak diperlukan migrasi khusus untuk fitur pratinjau. Sesudah backend siap,
tekan **Coba Lagi** atau buka kembali pratinjau. Perbaikan status loading ini
memerlukan hot restart/build Android baru, tetapi API pratinjau tetap harus
tersedia di backend.

Jika gambar soal tertentu tetap gagal:

1. Periksa gambar di detail dan pratinjau. HTTP 404 menunjukkan URL/berkas tidak
   tersedia; HTTP 403 menunjukkan akses ditolak. Coba muat ulang untuk gangguan
   jaringan sementara.
2. Di server, pastikan path media ada di `storage/app/public`, `public/storage`
   menunjuk ke direktori itu, dan web server boleh membacanya. Buat tautan yang
   belum tersedia dengan `php artisan storage:link` sesuai prosedur deploy.
3. Pastikan konfigurasi URL disk public/`APP_URL` menggunakan domain HTTPS yang
   dapat diakses Android. Segarkan cache konfigurasi/route sesuai prosedur deploy.
4. Gambar beresolusi rendah tetap terbatas kualitas sumbernya. Unggah ulang
   sumber yang lebih tajam jika teks diagram sudah buram pada berkas asli.

Pengujian mencakup kesamaan konten API pratinjau dengan soal siswa, izin dan
lingkup guru, media per bagian, tanpa perubahan data ujian, delapan jenis soal,
layar 320 px, zoom tanpa mengubah pilihan jawaban, dan pemulihan pemuatan gambar
setelah HTTP 404. Contoh visual memakai diagram sintetis, bukan data siswa.
