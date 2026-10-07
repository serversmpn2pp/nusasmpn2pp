# Poin presensi langsung

## Aturan

- Diaktifkan per tahun pelajaran melalui Pengaturan Poin Keterlambatan atau perintah aktivasi.
- Tanggal aktivasi berasal dari waktu server aplikasi. Kejadian sebelum tanggal itu tidak dikenai aturan baru, termasuk ketika sinkronisasi dipaksa.
- Terlambat: 15 poin tetap per kejadian, mulai satu detik setelah jam masuk. Menit untuk rekap dibulatkan ke atas: 1-60 detik menjadi 1 menit. Jam scan asli tetap tersimpan.
- Alfa: 25 poin per hari sekolah yang sudah berakhir, tanpa hadir, sakit, atau izin yang tercatat. Scan datang cukup sebagai hadir; tidak perlu menunggu scan pulang.
- Besaran poin dapat diatur. Rentang toleransi lama tidak digunakan pada mode langsung.
- Hari tidak aktif, tanggal di luar keanggotaan/tahun pelajaran, dan pengecualian presensi aktif tidak dikenai poin.
- Laporan pelanggaran manual dan laporan lama tetap menggunakan alur sebelumnya.
- Proses otomatis tidak membuat catatan scan atau baris presensi palsu untuk siswa yang tidak hadir.

## Koreksi

Pada Rekap Presensi Harian, buka laporan poin siswa. BK sesuai penugasan tingkat, Wakil Kesiswaan, atau administrator dapat menerima alasan dan membatalkan poin kejadian tersebut. Alasan wajib diisi, keputusan tercatat di riwayat, dan pembatalan tidak dapat dilewati melalui tombol hapus laporan lama.

Poin yang dikecualikan tidak kembali ketika scheduler berjalan atau waktu presensi dikoreksi. Jam scan dan status kehadiran tidak otomatis diubah hanya karena alasan diterima. Jika status kehadiran memang salah, gunakan Koreksi Presensi dengan catatan wajib. Misalnya, alfa yang dikoreksi menjadi izin akan otomatis mengembalikan poin alfa.

Pengecualian presensi yang ditetapkan setelah poin terlanjur tercatat juga menyinkronkan dan membatalkan poin terkait. Pencabutan pengecualian dapat memberlakukan kembali poin yang memang memenuhi aturan; pengecualian pribadi yang alasannya telah diterima tetap dipertahankan.

Saldo disesuaikan melalui transaksi koreksi per kejadian, tanpa menghapus transaksi lama atau mengurangi pelanggaran lain. Kunci unik, penguncian database, dan selisih saldo kejadian mencegah duplikasi.

Jika siswa sebelumnya sudah memakai penghargaan pengurangan poin, pembatalan pelanggaran tidak meninggalkan saldo negatif yang dapat menghapus poin pelanggaran berikutnya. Penyesuaian pengurangan terdahulu dicatat sebagai transaksi terpisah, bukan pelanggaran baru.

Jika saldo setelah koreksi tidak lagi memenuhi ambang sanksi, sanksi yang masih menunggu dan belum ditugaskan dibatalkan dengan riwayat. Sanksi yang sudah diproses atau selesai tidak diubah otomatis. Sanksi menunggu yang dibatalkan otomatis dapat terpicu kembali saat ada pelanggaran baru yang membuat saldo mencapai ambangnya.

## Server sekolah

Setelah pull:

```powershell
php artisan migrate --force
php artisan optimize:clear
php artisan pembinaan:aktifkan-poin-presensi --terlambat=15 --alfa=25
php artisan schedule:list
```

Perintah aktivasi tidak langsung memberi poin. Perintah yang diulang ketika sudah aktif tidak menggeser tanggal aktivasi. Aktivasi ulang setelah mode dimatikan dimulai dari tanggal baru, tanpa menghukum hari selama mode nonaktif.

Scheduler NSSM yang sudah ada harus tetap berjalan. Jadwal baru `pembinaan:proses-poin-presensi` berjalan setiap 15 menit. Terlambat diproses langsung setelah scan; alfa diproses pada putaran pertama setelah pergantian hari. Jika scheduler sempat mati, hari terlewat diproses sejak tanggal aktivasi sampai kemarin; kemajuan disimpan setelah setiap hari berhasil.

Restart layanan aplikasi PHP yang berjalan lama setelah deployment. Restart `nusa-scheduler` dapat dilakukan untuk memastikan proses scheduler memakai kode baru. Tidak perlu membuat layanan NSSM tambahan.

Untuk sinkronisasi segera (bukan aktivasi ulang):

```powershell
php artisan pembinaan:proses-poin-presensi
```

## API

- `PUT /api/v1/pengaturan-poin-keterlambatan/{tahun}` menerima `otomatis_langsung`, `poin_terlambat`, dan `poin_alfa`. Tanggal aktivasi tidak dapat ditentukan klien.
- Respons pengaturan menyertakan mode, tanggal aktivasi, dan kedua besaran poin.
- Rekap/detail presensi menyertakan `poin_presensi` dan kewenangan koreksi.
- `POST /api/v1/poin-presensi/{laporan}/koreksi` memerlukan token, kewenangan BK/Wakil sesuai cakupan, dan `alasan`.
- Koreksi presensi menerima jam `HH:MM` atau `HH:MM:SS`, tanpa menghilangkan detik pada formulir web.

## Verifikasi

Tes menggunakan SQLite dalam memori, terpisah dari database lokal/sekolah. Kasus mencakup aktivasi, kejadian lama, detik pertama, tepat waktu, alfa harian, sakit/izin, libur, koreksi, batas kewenangan, pengecualian PJJ, pengulangan scheduler, dan API.
