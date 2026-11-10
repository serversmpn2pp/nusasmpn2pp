# Riwayat notifikasi mobile

Pembaruan 10 November 2026.

## Pemakaian

- Halaman **Notifikasi** memuat 20 pesan pertama, urut dari yang terbaru.
- Tombol **Muat notifikasi lebih lama** menambahkan 20 pesan berikutnya sampai
  seluruh riwayat yang tersimpan dapat ditelusuri.
- Filter **Semua / Belum dibaca** berlaku pada seluruh riwayat akun, termasuk
  pesan yang belum dimuat. Jumlah belum dibaca juga dihitung dari seluruh akun.
- **Tandai semua dibaca** mencakup halaman lama. Membuka pesan lama tidak
  mereset daftar yang sudah dimuat. Pada filter Belum dibaca, pesan yang sudah
  dibuka dikeluarkan dari daftar.
- Tarik halaman atau tekan tombol perbarui untuk memuat daftar terbaru. Bila
  pemuatan halaman berikutnya gagal, daftar yang sudah terbuka tetap tersedia
  dan tombol dapat dicoba lagi.

Kartu, ikon, dan alur tujuan notifikasi yang sudah ada tetap digunakan.
Daftar memakai pembuatan kartu saat diperlukan agar riwayat panjang tetap
ringan. Hasil permintaan halaman lama tidak menimpa hasil filter yang lebih
baru; permintaan pemuatan ganda dicegah.

## API

`GET /api/v1/notifikasi` memerlukan token mobile, akun aktif, serta kata sandi
yang sudah memenuhi ketentuan akun. Parameter:

- `status`: `semua` (bawaan) atau `belum_dibaca`.
- `sebelum_id`: penanda halaman berikutnya dari respons sebelumnya.

Respons `data` berisi `items`, `jumlah_belum_dibaca`, `filter.status`, serta
`paginasi` dengan `per_halaman: 20`, `ada_lagi`, dan
`sebelum_id_berikutnya`. Penanda kosong berarti riwayat sudah habis.
Respons tidak boleh disimpan di cache bersama (`private, no-store`).

Urutan menggunakan `created_at` lalu `id` menurun. Pencarian halaman berikutnya
menggunakan posisi pesan terakhir, sehingga pesan baru tidak menggeser posisi
halaman lama. Penanda tetap valid setelah pesan ditandai dibaca. Semua query,
termasuk pemeriksaan penanda, dibatasi ke akun yang sedang login. Memuat daftar
tidak otomatis menandai pesan dibaca.

Ringkasan `/beranda` tetap memuat 10 pesan untuk kompatibilitas aplikasi lama;
halaman riwayat pada aplikasi baru memakai endpoint terpisah. Penentuan tujuan
admin/BK/pelapor tetap memakai layanan pemetaan yang sama dengan push.

## Penerapan

Deploy backend terlebih dahulu dan jalankan `php artisan optimize:clear`.
Lalu bangun serta distribusikan AAB yang memuat perubahan native ini.
Tidak ada migrasi database atau perubahan dependensi. Nomor versi dan AAB
belum diubah/dibangun ulang dalam pekerjaan ini.

Perubahan banner sementara (otomatis tutup, usap kiri/kanan, dan tombol ×)
tetap disertakan dalam kode native; lihat `banner-notifikasi-mobile.md`.

## Verifikasi

- API: 19 tes / 308 assertion lulus untuk riwayat, batas pemilik, filter,
  penanda halaman, notifikasi baru, baca semua, beranda, dan detail laporan.
- **73 tes mobile lulus**, mencakup pemuatan bertahap, retry, permintaan ganda,
  pergantian filter saat halaman lama belum selesai, penandaan pesan lama,
  baca semua, kontrak API, dan regresi shell/push.
- Analisis Dart, pemeriksaan format PHP, serta pemeriksaan diff bersih.
- Tampilan diperiksa pada lebar 320, 390, dan 800 px; tablet diuji dengan skala
  teks 1,5. Gambar contoh berada di `mobile/build/ui-preview/notifikasi*.png`.
- Pengujian memakai data contoh dan SQLite dalam memori, tanpa deployment atau
  pengiriman push ke akun/perangkat sekolah nyata.
