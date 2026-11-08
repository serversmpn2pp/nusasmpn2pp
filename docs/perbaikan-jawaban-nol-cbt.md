# Jawaban angka nol pada CBT

## Perbaikan

- Penyimpanan Bank Soal web dan API mempertahankan teks `0`, termasuk pilihan ganda dan pilihan ganda kompleks. Pilihan yang benar-benar kosong tetap disaring, dan minimal dua pilihan tetap wajib.
- Kode pilihan dan kunci tidak diganti atau dinomori ulang. Pengacakan tetap mengirim kode jawaban aslinya.
- Kunci isian singkat/numerik dan pengecoh menjodohkan pada API tidak lagi membuang teks `0`. Koreksi otomatis membedakan jawaban kosong dari angka nol.
- Pratinjau dan halaman ujian memakai isi yang sudah disimpan. API tetap tidak mengirim kunci ke siswa.

## Penerapan

Setelah memperbarui backend server:

```bash
php artisan optimize:clear
```

Tidak ada migrasi, perubahan aset JavaScript, atau perubahan kode aplikasi Flutter. Perbaikan ini tidak memerlukan build AAB ulang. Buka ulang halaman soal/pratinjau atau muat ulang data di aplikasi untuk mengambil isi terbaru.

## Soal lama

Pilihan `0` yang sebelumnya tersaring memang tidak tersimpan di database. Sistem tidak menebak teks yang sudah hilang atau mengubah soal lama secara otomatis. Guru perlu memeriksa soal, mengisi kembali pilihan pada kode semula, memeriksa kunci, lalu menyimpannya.

Perbaikan kode tidak menghitung ulang hasil ujian yang sudah selesai secara otomatis. Jangan mengubah soal yang sedang dikerjakan siswa; periksa dampak terhadap hasil bersama panitia sebelum menerapkan koreksi pada ujian yang sudah dipakai.
