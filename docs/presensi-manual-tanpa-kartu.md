# Presensi Hadir manual tanpa kartu

Di **Piket Saya** atau **Koreksi Presensi** (web dan Android), petugas memilih alasan **Tidak membawa kartu pelajar** atau **Kartu pelajar hilang** dan status **Hadir tepat waktu** atau **Hadir terlambat**. Tidak ada isian jam datang, dan tidak ada lagi sanksi minimal 5 menit. Jam simpan tetap dicatat terpisah pada riwayat koreksi bersama akun petugas.

Pilihan terlambat masuk hitungan rekap tanpa durasi keterlambatan. Notifikasi siswa dan orang tua terhubung menyebut status, alasan kartu, serta poin aktual, tanpa mengarang jam atau durasi. Catatan bebas petugas tetap tersimpan dalam riwayat audit.

## Poin dan koreksi

- Jumlah poin mengikuti jenis pelanggaran pada bagian **Presensi manual tanpa kartu** di Pengaturan Poin Keterlambatan (bawaan `PRESENSI_MANUAL`). Jika aturannya 5 poin, kedua pilihan status mendapat 5 poin, bukan 5 poin ditambah poin keterlambatan scan. Nilai 0 dan riwayat aturan yang sudah dibekukan tetap dihormati.
- Sanksi Hadir manual langsung diproses, termasuk ketika mode otomatis untuk **scan biasa** belum diaktifkan. Pengaturan scan biasa tidak diubah.
- Jadwal presensi aktif, periode keanggotaan/tahun pelajaran, pengecualian presensi, dan pembatalan poin yang sudah disetujui tetap diperiksa. Hari PJJ/libur dengan pengecualian aktif tidak menghasilkan poin.
- Penyimpanan ulang dan sinkronisasi tidak menggandakan poin. Perubahan ke Sakit/Izin/Alfa membatalkan poin yang terkait dan membersihkan sanksi manual. Scan pulang tidak menghapus sanksi Hadir manual sebelumnya.
- Koreksi catatan dengan scan masuk berhasil tidak dikenai sanksi tanpa kartu. Hak akses admin, wali kelas, dan guru piket tetap mengikuti kewenangan semula.
- **Scan bermasalah** masih dapat dicatat dengan jam datang sebagai **Perlu verifikasi**, tanpa poin sampai ada hasil pemeriksaan. Scan masuk yang sah tetap memakai jam asli dan aturan keterlambatan scanner.
- Data lama tidak dikonversi massal. Catatan dan transaksi poin lama dipertahankan, kecuali petugas mengoreksi kejadian tersebut.

## Pemasangan

Deploy backend **sebelum** merilis AAB baru. Perubahan ini tidak menambah migrasi. Di direktori proyek server:

```sh
php artisan optimize:clear
php artisan queue:restart
```

Restart worker berlaku jika server menjalankan queue worker untuk push. Tidak ada perubahan dependensi maupun tugas scheduler baru. Pengiriman push tetap mengikuti konfigurasi Firebase/worker yang sudah ada.

Pada alur tanpa kartu, API menerima `alasan_hadir_manual` dan `status_masuk_manual` (`tepat_waktu` atau `terlambat`). `jam_masuk` tidak diperlukan. Klien lama yang masih mengirim jam datang eksplisit dapat memperoleh klasifikasi berdasarkan jadwal aktif, tanpa menyimpan jam itu sebagai presensi tanpa kartu. Tanpa pilihan status atau jam datang valid dari klien lama, server meminta penggunaan web atau Android terbaru.
