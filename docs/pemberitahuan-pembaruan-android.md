# Pemberitahuan pembaruan NUSA

Seluruh jenis akun memperoleh pemberitahuan di beranda saat Google Play
melaporkan ada versionCode lebih tinggi yang tersedia untuk akun/perangkat itu.
Pemeriksaan dilakukan saat beranda pertama dibuka dan saat kembali ke aplikasi,
dengan jeda minimal 10 menit. Tersedia **Perbarui** (membuka halaman resmi NUSA
di Google Play) dan **Nanti** (menyembunyikan pemberitahuan versi tersebut selama
sesi beranda berjalan). Pengguna tidak dipaksa keluar atau mengunduh pembaruan.

Pemeriksaan dan tombol toko dijaga selama pengerjaan ujian aktif. Tidak ada
dialog yang muncul di atas soal, pengalihan otomatis, atau push saat aplikasi
ditutup. Layanan Play yang gagal/tidak tersedia tidak menghalangi beranda.

Versi pertama yang memuat fitur ini adalah **1.0.8+9 yang dibangun ulang**.
Pengguna harus memasang versi tersebut dahulu agar mendapat pemberitahuan untuk
pembaruan berikutnya. Aplikasi lama yang belum berisi fitur ini tidak dapat
menampilkan pemberitahuan baru secara retroaktif.

Pengelola tidak perlu mengisi nomor versi di backend setiap merilis pembaruan;
hasil pemeriksaan berasal dari Play. Rilis draft, sedang review, atau belum
tersedia untuk akun/perangkat tidak dianggap sebagai pembaruan yang tersedia.
Sumber integrasi: [Google Play In-App Update](https://developer.android.com/guide/playcore/in-app-updates/kotlin-java).

Pengujian nyata perlu instalasi melalui Play (akun sudah memiliki aplikasi),
package serta signing yang sesuai, dan versi Play lebih tinggi dari versi yang
terpasang. Emulator/sideload biasa tidak membuktikan distribusi pembaruan Play.
Lihat [panduan pengujian Google](https://developer.android.com/guide/playcore/in-app-updates/test).

Tes lokal mencakup hasil tersedia/tidak tersedia, tombol toko, penundaan,
pembatasan pemeriksaan berulang, pengecualian saat ujian aktif, kegagalan layanan,
serta layar 320 × 640. Pengiriman melalui Play belum dilakukan oleh pekerjaan build.
