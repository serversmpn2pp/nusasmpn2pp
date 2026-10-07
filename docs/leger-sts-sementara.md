# Leger STS dan ranking sementara

## Perhitungan

- Jumlah leger adalah penjumlahan nilai yang tersedia.
- Rata-rata leger = jumlah nilai tersedia / jumlah mapel yang ditetapkan.
- Mapel yang dikecualikan pada pengaturan Rapor STS tidak menjadi pembagi.
- Pada ranking paralel, semua siswa memakai daftar dan jumlah mapel tingkat
  yang sama, termasuk jika suatu mapel belum tersedia pada kelasnya.
- Nilai kosong tetap null dan ditampilkan `-` atau `TM` untuk pengecualian
  tidak mengikuti STS. Perhitungan ini tidak menyimpan nilai nol pengganti.
- Nilai nol yang benar-benar tersimpan tetap sah dan masuk perhitungan.
- Siswa tanpa satu pun nilai tidak diberi jumlah, rata-rata, atau ranking.
- Rata-rata dibulatkan dua desimal. Nilai sama mendapat ranking sama;
  urutan berikutnya dilewati (1, 1, 3).

Contoh: 8 nilai berjumlah 640 dari 11 mapel menghasilkan rata-rata sementara
58,18, bukan 80. Setiap baris menunjukkan jumlah mapel tersedia, final, dan draf.

## Sumber dan status

Leger memakai hasil final maupun nilai draf valid yang sudah tersedia:

- CBT: hanya pengerjaan selesai dengan skor yang telah dikoreksi lengkap,
  atau nilai pada komponen STS terhubung yang valid. Pengerjaan berjalan dan
  skor bermasalah tidak dimasukkan.
- STS manual: satu komponen aktif yang valid untuk mapel, kelas, tahun ajaran,
  dan semester tersebut. Komponen ambigu atau data tidak valid tetap kosong.
- Nilai belum final, atau nilai manual yang berubah setelah finalisasi,
  diberi tanda Draf. Tidak ada finalisasi, penerapan, atau publikasi otomatis.

Ranking kelas dan paralel diberi penanda sementara selama ada siswa yang
nilainya belum lengkap dan final. Hasil dihitung ulang ketika halaman dimuat
setelah nilai masuk, dikoreksi, atau difinalisasi. Penanda dan kelengkapan
nilai juga tampil pada cetakan A4 landscape.

Rata-rata kelas/tingkat dan sebaran memakai rata-rata leger siswa yang memiliki
nilai. Siswa tanpa nilai tidak termasuk penyebut statistik tersebut.
Statistik tiap mapel memakai nilai tersedia pada mapel itu, tidak mengganti
nilai kosong dengan nol; jumlah final dan draf ditampilkan terpisah.
Persentase kelengkapan kelas tetap menunjukkan siswa yang lengkap dan final.

## Penghargaan dan rapor

Ranking sementara tidak langsung digunakan untuk penghargaan. Kandidat umum
disaring dari siswa yang semua mapelnya lengkap dan final, kemudian diranking
ulang dalam kelompok tersebut. Kandidat per mapel hanya memakai nilai final
mapel terkait. Penetapan penghargaan tetap memerlukan verifikasi sekolah.

Perubahan ini hanya mengubah leger. Rapor STS tetap memakai sumber nilai final,
dan aturan jumlah/rata-rata rapor yang sudah ada tidak diubah.

## Penerapan

Tidak ada tabel atau migrasi baru. Setelah pull, jalankan `php artisan optimize:clear`.

Uji regresi: `php vendor/phpunit/phpunit/phpunit tests/Feature/RaporStsTest.php`
dan `php vendor/phpunit/phpunit/phpunit tests/Feature/MapelRaporStsTest.php`.
Fixture UI: set `NUSA_CAPTURE_LEGER_SEMENTARA=1` ketika menjalankan tes rapor,
lalu jalankan `node tests/Browser/leger-sts-sementara.mjs` dengan Playwright dan pdf-lib.
