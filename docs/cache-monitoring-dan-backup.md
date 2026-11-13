# Cache Monitoring TV dan cadangan database

Monitoring Siswa dan Ujian tetap diperbarui setiap 5 detik. Monitoring Pameran
tetap setiap 3 detik. Cache memakai ulang kunci yang sama untuk setiap konteks
layar: siswa, kombinasi kegiatan/jadwal/sesi ujian, tahun dan tanggal pameran,
atau acara buku tamu. Pembatasan akses perangkat tetap berlaku.

Cadangan manual, otomatis, dan pra-pemulihan tetap memuat struktur
`public.cache`, tetapi tidak membawa isinya. Saat restore, cache dapat dibuat
ulang oleh aplikasi. Tabel lain, termasuk `cache_locks`, sesi, dan antrean,
tetap dicadangkan. File cadangan lama tidak berubah.

## Setelah kode dipasang

1. Tidak perlu migrasi, build AAB, atau perubahan jadwal backup. Bersihkan cache
   kompilasi Laravel tanpa mengosongkan cache aplikasi:

   ```powershell
   php artisan optimize:clear --except=cache
   ```

   Jika OPcache tidak memeriksa perubahan file, mulai ulang layanan PHP pada
   waktu pemeliharaan sebelum membersihkan cache monitoring lama.
2. Tampilkan jumlah entri kedaluwarsa, lalu hapus hanya jika hasilnya sesuai:

   ```powershell
   php artisan nusa:bersihkan-cache-monitoring
   php artisan nusa:bersihkan-cache-monitoring --hapus
   ```

   Tanpa `--hapus`, perintah hanya membaca tabel cache. Pembersihan memakai
   koneksi, tabel, dan awalan kunci penyimpanan cache database NUSA. Cache
   lain, cache yang belum kedaluwarsa, dan cadangan lama tidak ikut dihapus.
   Jika memulihkan cadangan lama dari instalasi dengan awalan cache berbeda,
   tambahkan `--awalan=...` saat pratinjau maupun penghapusan. Contoh untuk
   cadangan server NUSA yang dipulihkan ke lokal pada November 2026:

   ```powershell
   php artisan nusa:bersihkan-cache-monitoring --awalan=nusasmpn2pp-cache-
   php artisan nusa:bersihkan-cache-monitoring --awalan=nusasmpn2pp-cache- --hapus
   ```
3. Buat cadangan baru dan bandingkan ukurannya. Uji pemulihan ke database
   terpisah sebelum menghapus arsip lama.

   ```powershell
   php artisan nusa:cadangkan-database
   ```

## Uji integrasi PostgreSQL lokal

Tes opt-in membuat dan menghapus dua database QA dengan nama acak pada
PostgreSQL loopback. Akun uji perlu izin membuat database dan alat PostgreSQL
(`pg_dump`, `pg_restore`, dan `psql`). Database aplikasi tidak dipakai sebagai
sumber atau tujuan restore.

```powershell
$env:NUSA_TEST_PG_BACKUP = '1'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --filter=test_cadangan_baru_tanpa_isi_cache_dapat_dipulihkan
Remove-Item Env:NUSA_TEST_PG_BACKUP
```
