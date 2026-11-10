# Tautan notifikasi hasil pemeriksaan dan pengesahan poin

## Penyebab

Notifikasi `keputusan-bk:*` dan `keputusan-wakil:*` sebelumnya selalu dipetakan
ke halaman native **Laporan Saya**. Notifikasi ini juga diterima admin dan
petugas BK, sedangkan endpoint Laporan Saya mensyaratkan identitas pegawai
pelapor serta kepemilikan laporan. Admin tanpa relasi pegawai atau petugas BK
yang bukan pelapor menerima respons 403 meskipun boleh melihat detail umum.

## Perbaikan

- Pemetaan menggunakan akses penerima dari `AksesLaporanPembinaanService`.
  Admin, BK, wakil kesiswaan, dan akun dengan akses luas diarahkan ke detail
  **Daftar Laporan Siswa** yang sudah tersedia di aplikasi.
- Pegawai pelapor tetap diarahkan ke **Laporan Saya**. Pemeriksaan kepemilikan
  dan izin endpoint tidak dilonggarkan.
- Kotak notifikasi, verifikasi tujuan saat push diketuk, dan payload FCM memakai
  layanan pemetaan yang sama. Notifikasi lama ikut memakai tujuan terbaru saat
  dimuat ulang; tidak perlu mengirim ulang notifikasi.
- Tautan siswa, orang tua, wali kelas, serta notifikasi lain tetap memakai
  pemetaan yang sudah ada.

## Penerapan

Perbarui kode backend di server, lalu jalankan:

```bash
php artisan optimize:clear
php artisan queue:restart
```

Tidak ada migrasi database atau perubahan Flutter/AAB. Muat ulang daftar
notifikasi di aplikasi sebelum mencoba notifikasi lama. Pengiriman push
sebenarnya tetap mengikuti konfigurasi FCM dan worker server yang sudah ada.

## Verifikasi

Kasus sebelum perbaikan berhasil direproduksi melalui tes: admin menerima
`/laporan-saya/1`, padahal seharusnya `/daftar-laporan-siswa/1`.
Tes setelah perbaikan memeriksa tujuan dan detail yang dapat dibuka oleh admin
tanpa pegawai, admin pembuat laporan, BK, wakil kesiswaan, dan pegawai pelapor,
serta kotak notifikasi, kepemilikan notifikasi, dan payload FCM tiruan.
**34 tes / 349 assertion lulus**; pemeriksaan format PHP dan diff juga lulus.
Log pengujian lokal berada di
`storage/framework/testing/notifikasi-akses-2026-11-10/`.
Pengujian menggunakan SQLite dalam memori tanpa pengiriman push nyata.
