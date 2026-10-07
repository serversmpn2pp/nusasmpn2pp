# Nilai STS manual untuk mapel non-CBT

## Alur guru

1. Buka Komponen Nilai dan gunakan satu komponen aktif berjenis **STS** untuk penugasan kelas, mata pelajaran, tahun pelajaran, dan semester yang sesuai. Nama bebas, misalnya `Ujian Praktik PJOK`.
2. Buka Input Nilai, pilih komponen tersebut, isi nilai 0-100 (koma atau titik, maksimal dua desimal), lalu simpan. Komponen dan nilai yang sudah ada tidak perlu dibuat ulang.
3. Periksa daftar siswa dan tekan **Finalisasi STS manual** pada bagian **Nilai STS untuk rapor**.
4. Wali kelas membuka Rapor STS pada kegiatan, kelas, tahun, dan semester yang sama. Nilai manual final otomatis digunakan bersama hasil CBT final. Rekap kehadiran tetap harus diperiksa sebelum cetak.
5. Leger kelas, leger tingkat, statistik, ranking, dan kandidat penghargaan memakai sumber nilai yang sama.

Daftar mapel yang diikutkan dapat ditetapkan admin/waka kurikulum melalui [pemilihan mapel rapor STS](mapel-rapor-sts.md). Mapel dengan komponen STS aktif, termasuk praktik, tetap wajib diikutkan.

Finalisasi manual bukan publikasi kepada siswa. **Publikasikan nilai** tetap mengatur publikasi komponen semester. Tombol finalisasi dinonaktifkan selama ada perubahan nilai/catatan yang belum disimpan.

## Cetak dengan nilai belum lengkap

- Rapor STS dapat dicetak per siswa maupun seluruh kelas tanpa menunggu seluruh nilai lengkap, termasuk jika semua nilai siswa belum tersedia.
- Periode rapor harus disimpan dan periode presensi sudah berakhir, wali kelas telah ditetapkan, serta pemeriksaan kehadiran siswa yang akan dicetak telah disimpan. Persyaratan ini tidak berubah.
- Nilai yang belum tersedia dicetak sebagai `-` dengan keterangan **Belum tersedia**, bukan nol. Nilai yang sudah tersedia, termasuk nilai nol yang sah, tetap ditampilkan.
- Jumlah dan rata-rata tetap `-` selama ada nilai/keterangan yang belum lengkap. Ranking keseluruhan tetap hanya untuk siswa dengan nilai lengkap; izin cetak tidak membuat nilai menjadi final atau mempublikasikannya.
- Keterangan **Tidak mengikuti STS** yang sebelumnya ditetapkan secara sah tetap berlaku. Tidak ada penandaan otomatis berdasarkan nilai kosong.

## Pengaman

- Hasil CBT tetap menjadi sumber jika mapel memiliki jadwal STS CBT yang tidak dibatalkan untuk kelas tersebut. Setelah diterapkan, rapor membaca nilai terbaru pada komponen tujuan, termasuk koreksi yang sudah disimpan di Input Nilai; lihat [koreksi nilai CBT pada rapor](koreksi-nilai-cbt-rapor.md). Nilai manual tidak dapat melewati paket CBT yang belum siap, hasil yang belum final, atau peserta yang belum selesai.
- Komponen yang sudah dihubungkan ke CBT tidak dapat difinalisasi manual.
- Pemilihan berdasarkan penugasan kelas/mapel/tahun dan semester, bukan nama komponen. Tidak ada perataan atau pemilihan sembarang apabila ditemukan beberapa komponen STS aktif untuk cakupan yang sama.
- Finalisasi dapat dilakukan bila setidaknya satu siswa aktif memiliki nilai. Nilai siswa yang kosong tetap belum tersedia, bukan nol. Rapor siswa tersebut belum lengkap dan belum memenuhi syarat ranking keseluruhan.
- Koreksi nilai/catatan melalui web maupun API membatalkan finalisasi manual jika isinya berubah. Penyimpanan tanpa perubahan tidak membatalkannya.
- Perubahan daftar siswa, penugasan, atau data nilai melalui jalur lain membuat persetujuan lama tidak berlaku. Guru perlu memeriksa dan memfinalisasi ulang.
- Perubahan metadata/nonaktif komponen melalui layanan pengelolaan komponen membatalkan finalisasi. Nilai yang sudah tersimpan tidak dihapus.
- Cakupan guru tetap mengikuti penugasan masing-masing. Waktu dan pengguna yang memfinalisasi dicatat oleh server, bukan dari masukan klien.

## Pembaruan server

Cadangkan database terlebih dahulu dan jalankan pembaruan pada waktu yang tidak mengganggu ujian.

```powershell
git pull
php artisan migrate --force
php artisan optimize:clear
```

Migrasi `2026_10_05_000004_tambah_finalisasi_sts_manual` menambahkan tiga kolom nullable pada `komponen_nilai`: waktu finalisasi, pengguna finalisasi, dan sidik data yang disetujui. Komponen lama tetap draf manual sampai diperiksa dan difinalisasi oleh guru. Tidak ada pemindahan atau penulisan ulang nilai lama.

Jika PHP dijalankan sebagai layanan NSSM dan OPcache tidak memeriksa perubahan file, mulai ulang layanan PHP saat pemeliharaan agar kode baru terbaca. Tidak diperlukan perubahan Nginx khusus fitur ini.

## API mobile

Endpoint memerlukan token Sanctum berkemampuan `mobile`, akun aktif yang memenuhi kebijakan akun, izin `nilai.input`, dan cakupan penugasan yang sesuai.

`GET /api/v1/input-nilai?guru_mata_pelajaran_id={id}&semester=ganjil&komponen_nilai_id={id}`

Respons menambahkan `data.sts_manual` untuk komponen STS angka; selain itu null. Isinya:

- `sidik`: identitas data yang sedang diperiksa; kirim kembali saat finalisasi.
- `dapat_finalisasi`, `difinalisasi`, `berubah`, `menggunakan_cbt`.
- `hambatan`, `jumlah_siswa`, `jumlah_terisi`, `difinalisasi_pada`.

`PATCH /api/v1/input-nilai/{komponenNilai}/sts-manual`

```json
{
  "sidik": "<64 karakter sidik dari respons GET terbaru>",
  "difinalisasi": true
}
```

Gunakan `difinalisasi: false` untuk menjadikan draf STS. Respons sukses mengembalikan status terbaru dalam `data`, tanpa daftar nilai siswa tambahan. Perubahan data sejak pemeriksaan menghasilkan 422 dengan `errors.sidik`; muat ulang dan periksa kembali. Hambatan finalisasi menghasilkan `errors.sts_manual`. Cakupan guru lain menghasilkan 404; tanpa izin menghasilkan 403. Respons sukses berheader `Cache-Control: no-store`.

`POST /api/v1/input-nilai` menambahkan `data.finalisasi_sts_dibatalkan` agar klien mengetahui perlunya finalisasi ulang setelah koreksi. Layar Android perlu menghubungkan status dan tindakan ini; endpoint tersedia, bukan perubahan APK.

## Verifikasi

```powershell
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/StsManualTest.php tests/Feature/RaporStsTest.php tests/Feature/InputNilaiFilterTest.php
$env:NUSA_CAPTURE_NILAI_UI='1'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/StsManualTest.php tests/Feature/InputNilaiFilterTest.php
node tests/Browser/input-nilai.mjs
```

Pengujian menggunakan database SQLite sementara dan siswa contoh, bukan data sekolah. Browser menggunakan HTML hasil render Blade dan memeriksa tampilan desktop/HP, luapan tabel, konfirmasi finalisasi, serta pengaman perubahan yang belum disimpan.
