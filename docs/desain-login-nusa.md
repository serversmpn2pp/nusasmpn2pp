# Desain Login NUSA

Halaman login mengikuti pratinjau sketsa yang disetujui: dominan putih,
biru `#15477A`, teal `#0F766E`, dan highlight kuning `#F1C40F`.
CSS dibatasi oleh `.nusa-login`, hanya dimuat pada halaman login.
Kolom username dan kata sandi menggunakan garis biru 2 px, isian biru sangat
muda, dan indikator fokus teal tanpa mengubah ukuran kolom.

## Aset

- Logo memakai `public/images/logo-nusa.png` yang sudah tersedia.
- `public/images/nusa/login/sekolah-sketsa.webp` adalah sketsa dari foto SMP
  Negeri 2 Padang Panjang yang diberikan pengguna. Sumber sketsa dibuat dengan
  image_gen bawaan, kemudian dikonversi ke WebP tanpa mengubah komposisi.
- Prompt sketsa: ubah foto sekolah yang sama menjadi sketsa arsitektur pensil
  biru-teal di atas putih; pertahankan proporsi gedung, pintu, jendela, papan
  nama, tiang bendera, pepohonan dan tangga; gunakan garis dan arsiran yang
  terlihat, tanpa menambahkan bangunan, formulir, atau watermark.
- Ikon lokal diekspor dari data ikon resmi Lucide 1.8.0. Lisensinya disertakan
  pada folder yang sama. Tidak ada dependensi runtime atau CDN baru.

## Perilaku

Form tetap POST ke `login.store`, menyertakan CSRF, username, password, dan
checkbox `ingat`. Tidak ada perubahan controller, rute, API, role, sesi, atau
database. Komponen kata sandi dan skrip pemasangan PWA tetap digunakan.
Username lama dipulihkan setelah validasi gagal, password tidak diisi ulang.
JavaScript login hanya mengatur status pengiriman; form tetap bekerja tanpa
JavaScript dan tidak memakai penyimpanan browser untuk kredensial.

Login menggunakan `translate="no"`, kelas `notranslate`, dan meta Google
`notranslate`, mengikuti pola halaman CBT. Penanda diwariskan ke formulir,
label, placeholder dan pesan dinamis. Ini merupakan permintaan kepada alat
terjemahan yang mendukungnya, bukan jaminan untuk memblokir semua ekstensi
atau terjemahan manual. Tidak ada perubahan pengaturan browser pengguna.

Tombol pemasangan PWA diposisikan setelah baris kebijakan privasi dan
penghapusan akun. Tampilannya hanya ikon unduh dan teks tanpa bingkai.
Skrip PWA yang sama tetap mengatur kemunculannya sesuai ketersediaan
pemasangan dari browser, termasuk menyembunyikannya pada mode aplikasi.

## Pengujian

```powershell
php artisan test --filter='LoginTampilanTest|LoginAlurTest|AutentikasiTest|AktivitasLoginTest|AkunSiswaTest|AkunOrangTuaTest|IdentitasSesiPenggunaTest|AutentikasiApiTest'
```

Untuk pemeriksaan browser, buat fixture dari aplikasi dengan
`NUSA_CAPTURE_LOGIN=1` saat menjalankan `LoginTampilanTest`, lalu jalankan
`tests/Browser/login.mjs` dengan Playwright dan Chrome yang tersedia.
`PLAYWRIGHT_MODULE` dapat menunjuk modul Playwright yang sudah terpasang.
`LOGIN_LIVE_URL` opsional untuk memeriksa GET halaman login server lokal;
pengujian tersebut tidak mengirim kredensial ke server lokal.

Hasil screenshot dan audit disimpan di `storage/logs/login-qa`.

## Setelah Pull

```powershell
php artisan view:clear
```

Tidak memerlukan migrasi, seeder, build frontend, atau layanan NSSM baru.
CSS dan JavaScript memiliki versi berdasarkan waktu perubahan berkas.
