# Aturan poin presensi manual dan katalog siswa

## Pengaturan sekolah

Pada web buka **Pengaturan Poin Keterlambatan**. Pada Android buka
**Menu → Kesiswaan & BK → Pengaturan → Poin Keterlambatan**,
pilih tahun pelajaran, kemudian bagian **Presensi manual tanpa kartu**.
Pilih jenis pelanggaran aktif. Poin diambil dari master jenis pelanggaran,
bukan diisi oleh petugas saat mencatat kehadiran. Pilihan menampilkan kode,
nama, dan jumlah poin. Pengguna tetap memerlukan izin `poin_siswa.pengaturan`.

Contoh: pilih “Tidak membawa kartu pelajar” dengan bobot 10 poin. Pencatatan
Hadir tanpa scan berikutnya mendapat 10 poin. Jika master diubah menjadi 20,
pencatatan baru memakai 20; pencatatan lama tetap 10. Mengganti pilihan jenis
pelanggaran juga hanya berlaku pada pencatatan berikutnya.

- Aturan manual terpisah dari poin keterlambatan scan biasa dan poin alfa.
- Tetap minimal 5 menit; keterlambatan sebenarnya yang lebih besar digunakan.
- Satu kejadian manual tidak mendapat tambahan poin terlambat biasa lagi.
- Alasan tetap hanya “Tidak membawa kartu pelajar” dan “Kartu pelajar hilang”.
- Pengecualian presensi, penerimaan alasan, koreksi Sakit/Izin, dan pembatalan
  poin yang sudah ada tetap berlaku.
- Nama, kode, kategori, tingkat, dan poin aturan dibekukan pada kehadiran.
  Sinkronisasi ulang tidak mengganti riwayat dengan master terbaru.
- Aturan/kategori yang sedang digunakan pada tahun aktif harus diganti dalam
  pengaturan presensi manual dahulu sebelum dinonaktifkan.
- Keterangan presensi, notifikasi dalam aplikasi, dan isi push presensi manual
  menyertakan aturan dan poin aktual, alasan kartu, serta saran menghubungi admin
  untuk memperoleh/mengganti kartu. Catatan privat petugas tidak dimasukkan ke push.

Aturan bawaan baru adalah `PRESENSI_MANUAL`, 15 poin. Migrasi mengambil bobot
riwayat lama dari hasil presensi/butir laporan yang sudah ada tanpa mengubah
transaksi poin. Nilai nol yang sudah diproses juga dipertahankan.

## Menu akun siswa

**Menu → Kesiswaan & BK → Informasi Siswa → Aturan Pelanggaran & Poin**.
Halaman native menampilkan aturan aktif, nama/kode, kategori, tingkat, dan poin,
dengan pencarian, filter kategori, pagination, serta penyegaran.
Katalog mengambil master terbaru dari server dan bukan riwayat/saldo pribadi.
Siswa hanya membaca; tidak memperoleh izin pengaturan atau data siswa lain.

Endpoint: `GET /api/v1/aturan-pelanggaran-poin`, autentikasi akun siswa.
Filter: `cari` (maksimal 100 karakter), `kategori_id`, `halaman`; 30 aturan/halaman.
Endpoint tidak menyediakan tambah/ubah/hapus. Akun non-siswa ditolak.

## Penerapan

Deploy backend yang menyertai perubahan ini, lalu jalankan pada server:

```powershell
php artisan migrate --force
php artisan optimize:clear
```

Migrasi baru: `2026_11_09_000002_aturan_poin_presensi_manual.php`.
Jangan menjalankan `migrate:fresh` pada server. Deploy/migrasi produksi tidak
dilakukan dari pekerjaan lokal ini.

AAB Android perlu diperbarui untuk menampilkan pemilih aturan dan menu katalog.
Nama/versi tetap **app-release-1.0.8-9.aab / 1.0.8+9**, atas konfirmasi pengelola
bahwa versi 9 sebelumnya belum diunggah. Klien lama yang tidak mengirim ID aturan
tidak menimpa pengaturan baru; perhitungan tetap dilakukan oleh backend.

Ubah bobot master atau pilihan aturan di kemudian hari cukup melalui server;
tidak perlu membangun AAB lagi selama struktur fiturnya tetap sama.
