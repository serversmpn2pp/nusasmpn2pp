# Koreksi Persentase Presensi Siswa

## Perhitungan

Persentase dihitung dari jumlah hadir dibagi **hari wajib presensi siswa**, bukan seluruh hari terjadwal sebelum pengecualian.

Contoh September 2026: 22 hari terjadwal, 5 hari PJJ tanpa kewajiban scan, 17 hari hadir = **17/17 = 100%**. Hari PJJ tidak diubah menjadi hadir dan tidak menambah alfa.

Pada contoh kelas VIII.A Bilingual: 29 siswa, 486 hadir, 4 alfa, 3 sakit, dan 145 hari-siswa pengecualian menghasilkan 493 hari-siswa wajib dan rata-rata **98,6%**. Hasil ini juga diverifikasi dengan pembacaan agregat database lokal, tanpa perubahan presensi.

- Pengecualian mengikuti tahun pelajaran, kelas/global, rentang tanggal, dan status aktif. Pengecualian batal/tahun lain/akhir pekan tidak mengurangi hari wajib.
- Catatan eksplisit tetap menang seperti rekap harian. Bila pada hari PJJ sudah ada catatan hadir/izin/sakit/alfa, catatan tersebut tetap dihitung beserta hari yang dicatat. Tidak menghapus alfa manual atau menghasilkan persentase lebih dari 100%.
- Tanggal sebelum masuk/setelah keluar keanggotaan tidak masuk pembilang, pembagi, ataupun rincian. Catatan kelas lama tidak digabung ke kelas sekarang.
- Seluruh periode tanpa kewajiban ditampilkan `-`, bukan 100% fiktif atau 0% yang menyesatkan. Nilai numerik API tetap 0 untuk kompatibilitas; klien memeriksa `hari_efektif == 0`. Siswa tanpa hari wajib tidak menurunkan rata-rata siswa lain.
- Rata-rata kelas tetap rata-rata persentase per siswa yang memiliki hari wajib, mengikuti kebijakan laporan sebelumnya, bukan mengganti ke rata-rata berbobot.

## Web, API, Mobile, dan Excel

`LaporanPresensiSiswaService` menjadi sumber bersama web, detail, API Android, dan ekspor Excel. Item memuat `hari_efektif` (pembagi), `hari_kalender`, `pengecualian`, dan `belum_scan`.

Ringkasan memuat `hari_efektif_min` dan `hari_efektif` (maksimum) untuk menampilkan rentang bila tiap siswa berbeda kewajiban, serta `total_hari_wajib` dan `hari_kalender`. Metadata periode detail memakai hari wajib siswa tersebut. Kalender tetap tersedia untuk rincian PJJ.

Web dan aplikasi Android menampilkan hari wajib dan jumlah pengecualian. Excel mempertahankan urutan kolom lama, mengganti arti/judul hari efektif menjadi hari wajib, dan menambahkan kolom **Hari Pengecualian** di paling kanan. Tidak mengubah cetak rapor STS.

## Penerapan

Setelah pull backend:

```sh
php artisan optimize:clear
```

Perbaikan ini tidak menambah migrasi/seeder dan tidak perlu menghitung ulang atau mengubah data presensi. Android lama langsung mendapat angka persentase yang sudah benar dari API; rincian/label baru dan tampilan `-` saat tanpa kewajiban memerlukan APK terbaru.

APK pengujian dibangun dengan `flutter build apk --debug --no-pub`, pada `mobile/build/app/outputs/flutter-apk/app-debug.apk`.

## Verifikasi

- Sepuluh tes `PersentasePengecualianPresensiTest`: kasus September, kesesuaian web/API/Excel, kelas, catatan manual, tanpa kewajiban, masa keanggotaan, perpindahan kelas, periode, dan query tanpa N+1.
- Empat tes Flutter laporan presensi lulus; analisis statis fitur tidak menemukan masalah.
- Dua belas pemeriksaan browser mencakup daftar/detail/seluruh periode PJJ pada empat ukuran layar, tanpa overflow horizontal.
- Regresi laporan/izin/pengecualian: 54 dari 55 tes lulus. Satu kegagalan lama pada `PengecualianPresensiSiswaTest::test_rapor_menghitung_sumber_baru_tetapi_tidak_menimpa_koreksi_yang_disimpan` masih ada (alfa sumber rapor diharapkan 4, aktual 0). Jalur `RaporStsService` tersebut tidak diubah dalam perbaikan laporan persentase ini.
