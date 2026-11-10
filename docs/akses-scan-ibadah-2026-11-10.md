# Pemulihan akses Scan Ibadah Siswa

Pemeriksaan 10 November 2026 menemukan `dapatMemindai()` hanya mengizinkan
Guru PL dan guru piket pada hari tugasnya. Akibatnya menu web dan katalog menu
Android menyembunyikan Scan Ibadah Siswa dari admin serta guru PAI. Admin tanpa
profil pegawai juga ditolak sebelum pemeriksaan perannya.

## Kebijakan setelah perbaikan

| Akun | Akses scanner ibadah umum |
|---|---|
| Administrator aktif | Diizinkan tanpa profil pegawai dan tanpa jadwal piket |
| Guru PAI | Diizinkan di luar jadwal piket, dengan izin `ibadah.scan` dan penugasan pengampu PAI aktif pada tahun pelajaran aktif |
| Guru PL | Mengikuti akses yang sudah ada, dengan izin `ibadah.scan` |
| Guru mata pelajaran lainnya | Tetap memerlukan izin `ibadah.scan` dan jadwal piket aktif untuk hari tersebut |
| Akun nonaktif/tanpa kewenangan | Ditolak |

PAI dikenali melalui mapel aktif dengan nama Pendidikan Agama Islam/Agama Islam
atau kode yang memuat PAI. Penugasan atau mapel nonaktif tidak memberi akses.

Pengecualian jadwal piket berbeda dari jam pelaksanaan ibadah. Semua petugas,
termasuk admin dan guru PAI, tetap mengikuti jadwal kegiatan aktif, tahun aktif,
hari kegiatan, serta jendela jam scan. Validasi QR, peserta wajib, dan pencegahan
scan ganda tetap dijalankan.

Scanner privat **Scan Berhalangan** memakai aturan terpisah. Menjadi guru PAI
tidak otomatis memberikan akses ke data berhalangan siswi.

## Web dan Android

Sidebar web, endpoint scanner, katalog menu mobile, dashboard scanner mobile,
dan penyimpanan scan memakai `AksesScanKegiatanIbadah`. Perbaikan dilakukan pada
backend bersama, beserta pesan penolakan yang menjelaskan kelompok petugas.

Android sudah mempunyai halaman native `/scan-kegiatan-ibadah`. Tidak ada kode,
ikon, atau rute Flutter yang perlu diubah; AAB baru tidak diperlukan untuk
perbaikan akses ini. Katalog menu diperoleh dari server. Setelah backend
diperbarui, muat ulang halaman web dan tarik untuk menyegarkan menu Android
atau tutup lalu buka kembali aplikasi.

Penerapan pada server setelah kode dideploy:

```bash
php artisan optimize:clear
```

Perubahan ini tidak menambah migrasi atau memerlukan seeding ulang. Deployment
server dan perubahan data/izin akun produksi tidak dilakukan dari audit lokal.

## Verifikasi

- Empat skenario regresi admin/PAI gagal sebelum perbaikan, mereproduksi menu
  hilang dan penolakan akses.
- **74 tes backend / 1.887 assertion lulus** setelah perbaikan. Meliputi menu
  web/API, pembukaan dan pencatatan scanner admin/PAI, guru PL, piket per hari,
  izin dan penugasan PAI, batas jam kegiatan, scan ganda, rekap, serta scanner
  berhalangan yang terpisah.
- **4 tes tampilan Flutter lulus**: layar sempit, hasil QR dan daftar terbaru,
  kamera tidak aktif saat jadwal tutup, dan ketentuan Sholat Jumat.
- Tes memakai SQLite sementara; pengujian kamera pada HP fisik/server produksi
  tidak dilakukan dalam pemeriksaan ini.

Log backend: `storage/framework/testing/scan-ibadah-2026-11-10/`.
Log Flutter: `mobile/build/test-scan-ibadah-access-2026-11-10.log`.
