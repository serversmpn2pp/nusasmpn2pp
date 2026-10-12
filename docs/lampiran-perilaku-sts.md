# Lampiran Perilaku dan Pembinaan pada Rapor STS

## Alur

1. Wali kelas menyimpan periode rapor STS seperti biasa.
2. Guru BK membuka **Kesiswaan & BK / Tugas Pembinaan > Lampiran Perilaku STS**.
3. Pilih kegiatan dan kelas. BK hanya memperoleh kelas dalam penugasan tingkatnya; administrator dan petugas pengesahan Wakil Kesiswaan dapat memeriksa lintas tingkat.
4. Buka siswa, periksa ringkasan kejadian dan teguran/tindak lanjut yang akan dibagikan, serta catatan pembinaan opsional.
5. Tetapkan Guru BK yang ditugaskan pada tingkat/tahun/tanggal rapor dan Wakil Kesiswaan. Jika hanya satu kandidat, pilihan otomatis terisi. Beberapa kandidat harus dipilih; tidak mengambil petugas pertama secara sembarang.
6. Centang konfirmasi dan simpan pemeriksaan. Siswa tanpa kasus tetap diperiksa dan mendapat lampiran tanpa catatan pelanggaran.
7. Wali kelas membuka **Rapor STS** lalu **Rapor + perilaku** per siswa atau **Cetak kelas + perilaku**. Pratinjau gabungan juga tersedia setelah pemeriksaan lampiran.

Cetak rapor nilai saja tetap tersedia. Lampiran tidak mengubah nilai, presensi, poin, atau keputusan kasus.

## Sumber dan Privasi

- Tahun pelajaran mengikuti kegiatan STS; periode kasus mengikuti awal dan batas rekap presensi yang disimpan pada rapor.
- Pelanggaran disahkan (termasuk presensi otomatis), keputusan pembinaan yang sudah ditetapkan, dan kejadian tanpa poin yang sudah selesai menjadi sumber pemeriksaan.
- Laporan menunggu verifikasi, dibatalkan, tidak terbukti, serta konseling biasa yang belum ditetapkan sebagai keputusan pembinaan tidak dicetak.
- Sanksi akumulasi poin tampil sebagai tindak lanjut, tanpa menambahkan poin untuk kedua kalinya.
- Kronologi lengkap, catatan rahasia, bukti, dan identitas siswa lain tidak dimasukkan otomatis. BK meninjau ringkasan publik maksimal 200 karakter per kolom dan catatan umum maksimal 600 karakter.
- Tanggal, poin, status, daftar sumber, dan ringkasan transaksi berasal dari server. Klien tidak dapat menambah/menghapus kejadian atau mengubah poin melalui form lampiran.
- Cetak/pratinjau gabungan hanya menggunakan ringkasan yang sudah diperiksa. BK tidak mendapatkan akses halaman nilai melalui halaman ini.
- Perubahan sumber, poin, periode, tanggal rapor, atau kandidat penandatangan membuat lampiran perlu diperiksa ulang. Versi pemeriksaan mencegah penyimpanan halaman lama menimpa pemeriksaan terbaru.
- Saldo dihitung dari transaksi tahun pelajaran sampai akhir hari batas periode, bukan saldo hari pencetakan. Poin masuk dan pengurangan/koreksi dihitung pada rentang laporan.

## Cetak

A4 portrait, kop dan dua logo mengikuti rapor STS. Orang tua/wali menandatangani garis kosong; nama/NIP Guru BK dan Wakil Kesiswaan berasal dari pegawai terpilih. Urutan cetak kelas: nilai siswa pertama, lampirannya, nilai siswa berikutnya, lampirannya. Kasus panjang dibagi ke lembar lanjutan dengan kop, identitas, dan tanda tangan; tidak dipotong atau dirangkum menjadi sebagian baris.

## Deployment

```powershell
php artisan migrate --force
php artisan optimize:clear
```

Tabel baru: `lampiran_perilaku_sts`. Backup PostgreSQL yang mencakup seluruh schema public otomatis menyertakan tabel ini. Tidak perlu layanan NSSM baru dan tidak mengubah aktivasi/besaran poin presensi. Jika PHP dijalankan sebagai layanan berumur panjang, muat ulang layanan aplikasi sesuai prosedur deployment sekolah.

## Verifikasi

```powershell
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/LampiranPerilakuStsTest.php
```

Audit browser menggunakan fixture Blade dengan database pengujian, bukan akun siswa nyata: `tests/Browser/perilaku-sts-print.mjs`. Memeriksa tampilan desktop/mobile, penandatangan, konfirmasi pemeriksaan, logo, urutan halaman, PDF A4, dan kasus panjang tanpa pemotongan.

## Mobile native — 12 Oktober 2026

Menu **Kesiswaan & BK → Lampiran Perilaku STS** tersedia bagi administrator,
Guru BK berizin, serta Wakil Kesiswaan berizin. Menu tidak diberikan kepada
siswa/orang tua, walaupun secara keliru mereka mendapatkan peran/izin BK.
Guru BK tetap hanya memperoleh kelas pada tingkat/tahun penugasannya.

- Memilih kegiatan STS/kelas dan isi lampiran (semua terverifikasi atau poin final).
- Membuka ringkasan siswa; hanya kejadian/tindak lanjut publik dan catatan umum
  yang dapat diedit. Tanggal, sumber, status, dan poin tidak dapat diedit.
- Pemeriksaan tunggal atau kolektif; setiap siswa harus dikonfirmasi sendiri.
  Jika kandidat penandatangan lebih dari satu, petugas harus memilihnya.
- Pemeriksaan lama tidak otomatis ditimpa: versi dan sidik sumber tetap berasal
  dari snapshot yang dibuka. Konflik meminta muat ulang dan pemeriksaan ulang,
  bukan retry otomatis dengan versi baru. Penyimpanan kolektif bersifat atomik.
- Halaman baca-saja ketika periode belum selesai atau petugas/penandatangan
  belum sesuai. Perubahan belum disimpan meminta konfirmasi sebelum ditutup.
- Pencetakan rapor dan lampiran gabungan tetap melalui wali kelas pada web.

API mobile menggunakan aturan yang sama pada `LampiranPerilakuStsService`:

```text
GET /api/v1/lampiran-perilaku-sts?kegiatan_id=…&kelas_id=…
PUT /api/v1/lampiran-perilaku-sts/{kegiatan}/{kelas}/isi
PUT /api/v1/lampiran-perilaku-sts/{kegiatan}/{kelas}/pemeriksaan
```

Semua endpoint memerlukan token aktif dengan ability `mobile`, akun pegawai
atau admin, serta izin pemeriksaan. Respons memakai allowlist dan `no-store`;
bukan serialisasi model siswa/laporan penuh. Kronologi, catatan rahasia,
bukti, NISN, NIP, dan nilai tidak dimasukkan ke respons fitur ini.

Deployment memerlukan pembaruan controller API, `routes/api.php`, dan
`config/menu_mobile.php` pada server. Muat ulang route/config cache dan layanan
berumur panjang melalui prosedur deployment sekolah. AAB saja tidak menambah
endpoint/menu di server. Tidak ada migration baru dalam penambahan mobile ini;
migration STS web yang sudah ada tetap menjadi prasyarat. Tidak menjalankan
migration atau mengubah server/database produksi saat membangun AAB.

Pengujian tambahan: `tests/Feature/Api/LampiranPerilakuStsApiTest.php`
(11 tes) dan `mobile/test/sts_behavior_view_test.dart` (10 tes), menggunakan
data contoh. Meliputi cakupan BK, akun tanpa hak akses, kebocoran data rahasia,
konfirmasi, sumber/versi berubah, rollback kolektif, pilihan penandatangan,
dan layar 320 px dengan teks diperbesar. Uji end-to-end pada HP terhadap server
yang sudah diperbarui tetap diperlukan.
