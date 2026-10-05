# Pemilihan mata pelajaran rapor STS

## Cakupan dan penggunaan

Pada **Rapor STS**, pilih kegiatan dan kelas, kemudian buka **Mata pelajaran rapor STS** di atas pengaturan periode.

Administrator atau waka kurikulum dapat mencentang mapel yang diikutkan dan menghapus centang mapel yang tidak menyelenggarakan STS. Isi alasan penetapan dan simpan. Konfirmasi menyebutkan bahwa perubahan berlaku untuk semua kelas pada tingkat yang sama.

Pengaturan terpisah untuk setiap kegiatan STS dan tingkat. Tidak ada pengecualian berbeda per siswa atau pilihan yang diubah sendiri oleh wali kelas. Wali kelas dapat melihat pilihan dan riwayatnya, tetapi tidak dapat mengubahnya.

Sebelum ada pengaturan, perilaku lama tetap berlaku: semua mapel angka dari penugasan/jadwal ditampilkan. Pramuka atau mapel predikat tidak termasuk dalam pilihan ini.

## Aturan nilai

- Mapel diikutkan tetap memerlukan nilai STS final dari CBT atau komponen manual. Kekosongan nilai tidak diperlakukan sebagai nol.
- Mapel dikecualikan tidak ditampilkan pada rapor dan leger serta tidak menjadi syarat kelengkapan, jumlah, rata-rata, statistik, atau kandidat penghargaan.
- Contoh: dari 12 mapel, 1 dikecualikan. Setelah 11 nilai final lengkap, jumlah dihitung dari 11 nilai dan rata-rata dibagi 11.
- Data soal, peserta, nilai, komponen, presensi, dan finalisasi yang sudah tersimpan tidak dihapus atau diubah oleh pemilihan mapel.
- Rapor dan leger memakai layanan sumber yang sama. Pemilihan berlaku juga pada cetak rapor, cetak leger kelas/tingkat, ranking, dan statistik penghargaan.
- Ranking keseluruhan tetap mensyaratkan semua mapel yang diikutkan lengkap. Ranking tingkat memakai gabungan mapel kelas dalam tingkat tersebut; jika cakupan pelajaran kelas berbeda, nilai yang belum ada tetap menghalangi ranking keseluruhan. Pilihan ini bukan cara mengabaikan nilai belum diisi.
- Pengecualian individual `Tidak mengikuti STS` tetap merupakan proses terpisah. Pengaturan mapel untuk tingkat bukan penetapan siswa tidak hadir.
- Pemeriksaan kehadiran, wali kelas, periode presensi, serta persyaratan cetak lainnya tetap berlaku.

## Mapel dengan sumber STS

Mapel yang memiliki jadwal CBT tidak dibatalkan atau komponen STS aktif ditandai **Wajib**. Centangnya terkunci dan server menolak upaya mengeluarkannya, meskipun hasil belum final atau nilai belum diisi.

PJOK dan Keminangkabauan yang menyelenggarakan STS praktik tetap diikutkan. Nilainya berasal dari komponen STS manual yang difinalisasi, bukan dikeluarkan karena tidak menggunakan CBT.

Jika suatu mapel benar-benar tidak diujikan tetapi sudah terlanjur dibuatkan sumber STS, perbaiki sumber tersebut terlebih dahulu: batalkan jadwal CBT yang tidak digunakan atau nonaktifkan komponen STS yang keliru. Penilaian lain seperti formatif tidak mengunci pilihan ini.

Jika sumber STS baru dibuat setelah mapel dikecualikan, mapel otomatis kembali diikutkan untuk seluruh kelas pada tingkat tersebut. Halaman menampilkan peringatan untuk memeriksa kembali penetapan. Riwayat pengecualian lama tetap tersimpan; persetujuan lama tidak diam-diam menyembunyikan nilai ujian baru.

Mapel baru dari penugasan baru juga otomatis diikutkan sampai ditetapkan pengecualiannya. Sistem menyimpan daftar pengecualian eksplisit, bukan whitelist yang menyembunyikan semua mapel baru.

## Riwayat dan perubahan bersamaan

Setiap perubahan pilihan mencatat versi, mapel yang dikecualikan sebelum/sesudah, alasan, pengguna, dan waktu. Halaman menampilkan lima riwayat penetapan terbaru. Menyimpan ulang pilihan yang sama tidak membuat riwayat duplikat.

Formulir memuat versi penetapan dan sidik daftar mapel/sumber STS. Perubahan di halaman lain, penugasan baru, sumber STS baru, atau perubahan kelas menyebabkan formulir lama ditolak dengan pesan muat ulang, bukan menimpa perubahan tersebut.

Pilihan yang belum disimpan memunculkan penanda perubahan dan memblokir tombol cetak final. Konfirmasi diperlukan sebelum menyimpan pilihan atau meninggalkan perubahan pilihan untuk menyimpan formulir lain.

## Server dan migrasi

Migrasi `2026_10_05_000005_create_pengaturan_mapel_rapor_sts` menambahkan tabel `pengaturan_mapel_rapor_sts` dan `riwayat_mapel_rapor_sts`. Tidak ada penetapan pengecualian otomatis saat migrasi.

Cadangkan database sebelum memperbarui server sekolah, lalu:

```powershell
git pull
php artisan migrate --force
php artisan optimize:clear
```

Gunakan mekanisme pemeliharaan layanan PHP yang sudah diterapkan sekolah apabila OPcache tidak memeriksa perubahan kode. Backup PostgreSQL penuh mencakup kedua tabel baru; validasi backup/restore NUSA menggunakan inventaris tabel database.

## Pengujian

```powershell
$env:NUSA_CAPTURE_MAPEL_UI='1'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/MapelRaporStsTest.php tests/Feature/RaporStsTest.php tests/Feature/StsManualTest.php
node tests/Browser/mapel-rapor-sts.mjs
```

Pengujian memakai database SQLite sementara dan fixture Blade, bukan mengubah nilai atau pilihan mapel pada data sekolah. Cakupannya termasuk perhitungan dan cetak kelas/tingkat, hak akses, sumber STS wajib, riwayat, formulir lama, tampilan desktop/HP, dan perubahan belum disimpan.
