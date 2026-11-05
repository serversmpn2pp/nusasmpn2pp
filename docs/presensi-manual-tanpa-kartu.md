# Presensi Hadir manual tanpa kartu

Pencatatan **Hadir** baru melalui Piket Saya atau Koreksi Presensi siswa (web/API mobile) dikenai keterlambatan **minimal 5 menit**. Jika keterlambatan sebenarnya lebih besar, nilai yang lebih besar dipakai. Waktu tidak ditambah lima menit: jam masuk tetap merupakan jam input pertama pada menu piket atau jam kedatangan yang dicatat petugas berwenang pada formulir koreksi.

Petugas memilih salah satu dari dua alasan: tidak membawa kartu pelajar atau kartu pelajar hilang. Keterangan bebas petugas tetap disimpan tersendiri. Alasan, menit efektif, dan hasil penerapan poin disertakan dalam riwayat perubahan, halaman presensi siswa/orang tua, serta notifikasi NUSA kepada akun yang terhubung. Pesan juga menyarankan menghubungi admin untuk mendapatkan atau mengganti kartu pelajar.

## Poin dan koreksi

- Jumlah poin mengikuti `poin_terlambat` pada pengaturan tahun pelajaran; jika belum ada pengaturan, nilai bawaan **15 poin**. Nilai pengaturan 0 tetap dihormati.
- Sanksi Hadir manual langsung diproses, termasuk ketika mode otomatis untuk **scan biasa** belum diaktifkan. Pengaturan scan biasa tidak diubah.
- Jadwal presensi aktif, periode keanggotaan/tahun pelajaran, pengecualian presensi, dan pembatalan poin yang sudah disetujui tetap diperiksa. Hari PJJ/libur dengan pengecualian aktif tidak menghasilkan poin.
- Penyimpanan ulang dan sinkronisasi tidak menggandakan poin. Perubahan ke Sakit/Izin/Alfa membatalkan poin yang terkait dan membersihkan sanksi manual. Scan pulang tidak menghapus sanksi Hadir manual sebelumnya.
- Koreksi catatan dengan scan masuk berhasil tidak dikenai sanksi tanpa kartu. Hak akses admin, wali kelas, dan guru piket tetap mengikuti kewenangan semula.
- Data lama tidak diberi sanksi manual secara massal. Migrasi hanya menambahkan kolom dengan nilai awal 0/null; aturan diterapkan ketika petugas menyimpan Hadir manual.

## Pemasangan

Deploy backend beserta migrasi **sebelum** memakai aplikasi mobile baru. Di direktori proyek server:

```sh
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
```

Restart worker berlaku jika server menjalankan queue worker untuk push. Tidak ada perubahan dependensi maupun tugas scheduler baru. Pengiriman push tetap mengikuti konfigurasi Firebase/worker yang sudah ada. Backend juga menambahkan keterangan sanksi pada riwayat kehadiran yang dibaca aplikasi lama; pilihan alasan terstruktur dan keterangan pada kartu Hari Ini memerlukan build Android yang baru.

API menerima `alasan_hadir_manual` opsional agar aplikasi lama yang belum mengirim alasan tetap kompatibel. Jika tidak dikirim dan belum ada alasan yang valid, pilihan awal adalah tidak membawa kartu pelajar. Alasan umum input manual petugas dari data lama tetap bisa dibaca; ketika formulirnya dibuka, pilihan awal menjadi tidak membawa kartu pelajar. Nilai menit minimum dan poin ditentukan server, bukan dari payload klien.
