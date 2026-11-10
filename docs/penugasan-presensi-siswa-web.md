# Penugasan sekolah untuk siswa terpilih — web

Fitur ditambahkan pada 10 November 2026 melalui **Kehadiran → Pengecualian
Presensi**. Pengelolaan tetap memakai kewenangan pengecualian presensi yang
sudah ada (`absensi.pengaturan_kelola`, akun aktif, tanpa perluasan otomatis
ke semua guru/wali kelas). Tidak ada perubahan aplikasi Flutter atau AAB.

## Pemakaian

1. Pilih tahun pelajaran dan jenis **Tugas sekolah**. Cakupan menjadi
   **Siswa tertentu — tugas sekolah**.
2. Tentukan tanggal mulai/selesai dan alasan, misalnya lomba atau upacara di
   luar sekolah. Maksimal 200 siswa dan 31 hari per penetapan.
3. Cari nama/NIS/NISN, filter kelas, lalu centang siswa. Pilihan lintas kelas
   tetap tersimpan saat filter berganti. Tersedia **Pilih semua hasil** dan
   **Kosongkan pilihan**, jumlah peserta, serta ringkasan nama.
4. Buka **Pratinjau dampak**. Periksa daftar peserta, catatan hadir penugasan,
   dan poin presensi yang dikecualikan. Tombol **Ubah isian** mempertahankan
   pilihan melalui sesi; daftar siswa tidak dikirim melalui URL panjang.
5. Terapkan setelah memeriksa pratinjau. Penetapan dan pembatalan mencatat akun,
   waktu, alasan, tanggal, serta peserta yang dipilih.

## Perilaku presensi

- Peserta dicatat **Hadir — Tugas sekolah** pada hari presensi aktif dalam
  periode. Jam masuk/pulang tidak diisi secara fiktif. Tanggal mendatang
  merupakan penugasan terjadwal; laporan sampai hari ini tidak menghitungnya
  sebagai kehadiran yang sudah terjadi.
- Tidak ada sanksi minimal 5 menit, poin tidak membawa kartu, atau alfa karena
  tidak scan bagi peserta pada periode tersebut. Siswa lain tetap memakai
  aturan normal.
- Poin presensi otomatis/legacy yang telanjur keliru disinkronkan, dengan
  transaksi koreksi. Poin pelanggaran lain tidak menjadi sasaran penugasan ini.
- Catatan Sakit/Izin pada peserta dan hari penugasan ikut dikoreksi menjadi
  Hadir — Tugas sekolah. Pratinjau menampilkan jumlah serta rincian siswa,
  kelas, tanggal, status lama, catatan, dan pencatat sebelumnya. Penetapan
  memakai konfirmasi pratinjau yang sudah ada; tidak perlu mengoreksi satu per
  satu. Catatan lama disimpan di salinan dasar serta riwayat, sedangkan tampilan
  aktif memakai keterangan tugas sekolah.
- Selama tugas aktif, perubahan menjadi Sakit/Izin/Alfa memerlukan pembatalan
  tugas terlebih dahulu. Koreksi jam aktual tetap tersedia.
- Scan sebenarnya tetap disimpan di waktu aktual; tidak mengaktifkan kembali
  sanksi terlambat selama penugasan. Keterangan siswa/orang tua dan notifikasi
  menjelaskan penugasan, tanpa pesan sanksi kartu.
- Penugasan tidak mengecualikan seluruh kelas dari evaluasi ibadah. Perhitungan
  siswa lain dan pengecualian sekolah/kelas yang lama tetap berlaku.

## Pembatalan dan riwayat

Data dasar presensi dipulihkan dan poin disinkronkan kembali. Scan serta koreksi
jam yang benar-benar terjadi tetap dipertahankan. Baris yang semata-mata dibuat
untuk tugas, tanpa bukti kehadiran, dikeluarkan dari presensi aktif sehingga
siswa kembali mengikuti perhitungan biasa. Jika ada catatan koreksi pada baris
tersebut, salinannya tersimpan di **Arsip catatan penugasan yang dibatalkan**.
Daftar peserta dan alasan pembatalan tetap tersimpan.
Untuk Sakit/Izin yang dikoreksi oleh penugasan, status dan catatan asal tetap
dibekukan meskipun kemudian ada scan nyata; pembatalan mengembalikannya dengan
bukti scan yang tetap tersimpan. Siswa/tanggal di luar penugasan tidak diubah.

Pratinjau terikat akun, kedaluwarsa dalam 15 menit, dan diperiksa ulang saat
diterapkan. Perubahan peserta/presensi/poin/jadwal membatalkan pratinjau yang
basi. Pengiriman ulang penetapan tidak menggandakan presensi atau notifikasi.

## Penerapan server

Deploy kode backend dan jalankan dari root Laravel:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
```

Migrasi baru: `2026_11_10_000001_penugasan_presensi_siswa_terpilih.php`.
Migrasi menambahkan cakupan siswa, relasi peserta, arsip pembatalan, serta
hubungan/salinan dasar penugasan pada presensi. Tidak mengubah data presensi
lama saat migrasi. Pengelolaan jenis baru belum ditawarkan melalui menu native;
data presensi hasil penetapan tetap konsisten karena diproses backend bersama.

## Verifikasi lokal

- **120 tes backend / 1.252 assertion lulus**, format PHP dan diff bersih.
- Tes mencakup pemilihan lintas kelas, pratinjau, penerapan ulang, poin 15 yang
  sudah tercatat, pembatalan, jam scan/koreksi aktual, catatan tanpa jam fiktif,
  siswa yang tidak dipilih, rentang tanggal mendatang, izin akun, notifikasi
  orang tua, dan evaluasi ibadah siswa lain.
- Regresi mencakup pengecualian sekolah/kelas lama, rapor STS, laporan, presensi
  manual, scanner, dan poin.
- Pemeriksaan Chrome headless memakai data contoh untuk pencarian, filter,
  pilihan lintas kelas, payload, pilih semua/kosongkan, dan layout 1365/390 px.
- Bukti pengujian berada di `storage/framework/testing/penugasan-2026-11-10/`.

Tidak ada deployment, migrasi produksi, atau pengiriman notifikasi ke akun
sekolah nyata selama pengujian lokal ini.

## Notifikasi orang tua di web dan Android

Saat penugasan ditetapkan, sistem membuat notifikasi untuk akun siswa dan
orang tua/wali aktif yang terhubung. Detail dalam akun memuat nama anak, kelas,
tanggal mulai/selesai, alasan kegiatan, dan keterangan bebas sanksi presensi.
Pembatalan juga menghasilkan notifikasi beserta alasannya. Kunci unik mencegah
pengiriman ulang penetapan yang sama menggandakan notifikasi.

Notifikasi ini tampil melalui lonceng/kotak notifikasi web dan tab Notifikasi
Android yang sudah ada. Push menggunakan judul **Penugasan sekolah anak Anda**,
ringkasan penugasan serta tanggal; rincian nama/kegiatan/alasan dibaca dalam
akun. Mengetuk notifikasi orang tua membuka **Kehadiran Anak Saya** untuk siswa
dan bulan yang sesuai. Notifikasi siswa membuka **Kehadiran Saya**.

Push memakai sistem FCM, registrasi perangkat, izin notifikasi HP, dan antrean
yang sudah tersedia. Setelah deployment, worker perlu memuat kode terbaru
(`queue:restart`, dengan pengelola worker yang sudah berjalan). Tidak perlu
mengganti AAB. Pekerjaan ini tidak memeriksa konfigurasi server produksi atau
mengirim push ke perangkat nyata.

Jika penugasan mengoreksi Sakit/Izin, orang tua mendapat notifikasi **Koreksi
kehadiran: tugas sekolah**, dengan status/tanggal sebelumnya, status baru,
identitas anak, dan alasan kegiatan. Push memakai ringkasan koreksi; catatan
Sakit/Izin lama tidak disalin ke layar kunci. Tidak ada migrasi tambahan untuk
penyempurnaan ini karena salinan dasar dan riwayat penugasan sudah tersedia.

Verifikasi lanjutan: **43 tes / 412 assertion lulus** untuk notifikasi web/API,
nama anak, kepemilikan akun, pemetaan halaman native, pesan aktif/pembatalan,
serta payload FCM tiruan. Log:
`storage/framework/testing/notifikasi-penugasan-2026-11-10/verified.log`.

Verifikasi koreksi Sakit/Izin: **151 tes / 1.552 assertion lulus**, mencakup
pratinjau tanpa perubahan data, koreksi terpilih, arsip pencatat/catatan asal,
pembatalan setelah scan nyata, tanggal di luar hari sekolah, pratinjau basi,
pengiriman ulang, dan pesan koreksi untuk orang tua. Regresi juga mencakup
pengecualian lama, presensi manual, poin, scanner, notifikasi web/API/FCM,
serta evaluasi ibadah. Pratinjau diperiksa di Chrome headless pada 1365/390 px.
Log dan gambar pemeriksaan:
`storage/framework/testing/koreksi-tugas-2026-11-10/`.
