# Koreksi nilai CBT pada rapor

## Sumber nilai

- Sebelum ada nilai pada komponen tujuan maupun riwayat penerapan nilai, Rapor STS membaca skor CBT yang sudah final untuk peserta yang selesai.
- Nilai yang sudah tersimpan pada komponen tujuan di Input Nilai menjadi acuan akademik, termasuk isian manual untuk siswa yang tidak mengikuti CBT. Nilai otomatis terbaca pada Rapor STS, pratinjau/cetak, leger kelas/tingkat, statistik, ranking, dan kandidat penghargaan.
- Komponen tujuan dipilih melalui hubungan kelas ujian, bukan nama komponen atau komponen STS lain. Kelas, mapel, tahun pelajaran, semester, jenis STS, serta status aktif penugasan dan komponen harus sesuai.
- Nilai nol tetap sah. Nilai yang dikosongkan/dihapus setelah penerapan menjadi belum lengkap; rapor tidak kembali diam-diam ke skor asli CBT. Nilai yang diisi kembali dibaca dari komponen dan siswa yang sesuai, meskipun ID nilai berubah.
- Paket CBT tetap harus sudah difinalisasi. Peserta yang masih sedang mengerjakan tidak ditampilkan sebagai nilai final. Siswa berstatus aktif/nonaktif yang tidak mengerjakan CBT boleh mendapat nilai akademik melalui Input Nilai tanpa mengubah status ujiannya menjadi selesai. Siswa yang belum memiliki baris peserta ujian juga dapat dibaca dari komponen tujuan kelas ujian yang sesuai.
- Keterangan pengecualian "Tidak mengikuti STS" tidak digunakan ketika sudah ada nilai manual yang sah. Riwayat pengecualiannya tetap disimpan; nilai manual tidak menghapus alasan atau rekam ujian.
- Jawaban, skor asli CBT, dan hasil analisis soal tidak diubah oleh koreksi melalui Input Nilai.

## Alur guru

1. Finalisasikan hasil CBT dan terapkan ke komponen tujuan yang benar.
2. Buka Input Nilai, pilih komponen tersebut, ubah nilai, lalu simpan. Koma atau titik diterima dengan maksimal dua desimal.
3. Muat ulang Rapor STS atau Leger STS. Tidak perlu menerapkan ulang CBT untuk membaca koreksi yang sudah disimpan.
4. Publikasi nilai kepada siswa tetap terpisah. Perubahan melalui Input Nilai menjadikan publikasi komponen semester sebagai draf; publikasikan kembali apabila nilai hendak dibuka kepada siswa.

Menekan **Terapkan nilai** lagi menjalankan ulang penyalinan skor CBT dan dapat menimpa koreksi pada komponen. Jangan menggunakannya sekadar untuk memperbarui rapor.

Untuk siswa yang tidak mengikuti CBT, isi nilai pada **komponen STS tujuan yang sama**, lalu simpan dan muat ulang rapor. Tidak perlu membuat komponen tambahan, mengubah status peserta, atau menerapkan ulang CBT. Nilai yang sudah terlanjur disimpan akan terbaca setelah pembaruan kode. Isian kosong tetap "Belum tersedia"; angka nol merupakan nilai yang sah.

Untuk STS praktik/non-CBT, alur [finalisasi STS manual](sts-manual.md) tetap berlaku. Koreksi nilai manual non-CBT perlu difinalisasi ulang sebelum muncul sebagai nilai final pada rapor.

## SAS/SAJ

Rekap Nilai Rapor web dan API mobile sudah membaca nilai komponen `sas_saj`, bukan menghitung ulang skor jawaban CBT. Koreksi pada komponen yang benar otomatis memengaruhi kategori SAS/SAJ serta nilai akhir sesuai skema bobot. Uji regresi mencakup penerapan CBT, koreksi melalui API, rekap web/API, dan keutuhan jawaban asli untuk SAS ganjil, SAS genap, dan SAJ.

## Penerapan

Tidak ada perubahan tabel atau migrasi baru. Koreksi yang sudah tersimpan tidak perlu diinput ulang.

```powershell
git pull
php artisan optimize:clear
```

Jika OPcache tidak memeriksa perubahan berkas, mulai ulang layanan PHP/NSSM pada waktu pemeliharaan. Tidak perlu menerapkan ulang nilai CBT.

## Pengujian

```powershell
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/RaporStsTest.php tests/Feature/StsManualTest.php tests/Feature/MapelRaporStsTest.php tests/Feature/Api/RekapNilaiRaporApiTest.php tests/Feature/Api/InputNilaiApiTest.php
```

Pengujian menggunakan database SQLite terisolasi, bukan data sekolah.
