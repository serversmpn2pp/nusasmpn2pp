# Banner notifikasi di dalam aplikasi

Perubahan 10 November 2026 pada `NusaPushNotificationRuntime`.

Banner bertombol **Buka** sebelumnya mengikuti perilaku bawaan Flutter: tetap
muncul sampai ditutup dan hanya dapat diusap ke bawah. Sekarang banner:

- Hilang otomatis sekitar 6 detik setelah tampil.
- Dapat ditutup dengan usap ke kiri/kanan atau tombol **×**.
- Tetap menyediakan tombol **Buka** melalui verifikasi tujuan milik akun.

Menutup banner atau membiarkannya habis waktu tidak menghapus notifikasi dan
tidak menandainya dibaca. Pesan tetap tersedia di halaman Notifikasi.
Perubahan hanya berlaku pada banner saat aplikasi terbuka; pengaturan
notifikasi sistem Android tidak diubah.

Perubahan ini memerlukan build dan distribusi AAB terbaru untuk pengguna
Play Store. Tidak ada perubahan backend atau database. AAB belum dibangun
ulang dalam pekerjaan ini.

Verifikasi: 32 tes `push_notification_security_test.dart` lulus, mencakup
waktu tampil, usap dua arah, tombol tutup/Buka, layar kecil, serta pemeriksaan
akun dan tujuan notifikasi.
