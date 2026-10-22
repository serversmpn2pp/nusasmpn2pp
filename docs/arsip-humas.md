# Klasifikasi dan Hubungan Arsip Humas

## Model Data

`urusan` menjawab bidang pemilik arsip: kedinasan, komite, orang tua, kemitraan,
publikasi, alumni, atau pengaduan. `jenis_dokumen` menjawab bentuk dokumen, seperti
surat masuk, surat tugas, notulen, laporan, MoU, SK, dan dokumentasi kegiatan.
Pilihan resmi berada di `DokumenHumas::URUSAN` dan `JENIS_DOKUMEN`.

`kategori` lama tetap disimpan untuk kompatibilitas modul/client lama. Tidak ada
penggantian nama, penghapusan, atau pemindahan berkas. Riwayat revisi berkas tetap
terpisah dari riwayat klasifikasi dan hubungan.

Satu dokumen dapat berkaitan dengan beberapa agenda, program Humas, program
komite, dan surat TU. Hubungan adalah referensi, bukan salinan. Bukti laporan
program dan MoU yang telah ada tetap memakai struktur sumbernya. Tidak ada
pembuatan program/rapat/surat fiktif berdasarkan nama berkas.

## Alur Petugas

1. Buka pusat dokumen atau tautan arsip dalam salah satu dari tujuh bidang Humas.
2. Gunakan filter urusan, jenis, status klasifikasi, kategori lama, dan kata kunci.
3. Tinjau arsip lama melalui status **Perlu ditinjau**. Isi urusan/jenis pada edit
   dokumen, atau pilih arsip sejenis untuk konfirmasi kolektif (maksimal 50).
4. Tambahkan hubungan pada detail dokumen setelah mencari agenda/program/surat.
   Setiap tambah/lepas memerlukan catatan dan dicatat dalam riwayat.

Konfirmasi menyimpan akun dan waktu pemeriksaan. Perubahan kolektif menggunakan
sidik versi per dokumen; satu konflik membatalkan seluruh batch. Versi metadata
bertambah tanpa membuat versi berkas baru. Jangan menaruh rincian rahasia surat
dalam judul/deskripsi/alasan arsip yang dibaca petugas dokumen lain.

## Arsip Lama

Migrasi hanya menambah kolom/tabel. Penataan lama dijalankan terpisah:

```powershell
php artisan humas:tata-arsip
php artisan humas:tata-arsip --terapkan
```

Tanpa `--terapkan`, perintah hanya menampilkan hasil pemeriksaan. Dengan opsi
tersebut, metadata diisi dari kategori dan hubungan yang sudah tersimpan:

- Kategori notulen/surat/program menentukan jenis, bukan otomatis bidang urusannya.
- Agenda komite/orang tua/kemitraan dapat membantu menentukan urusan.
- MoU terkait, SK komite, kliping, bukti laporan, dan program terkait menjadi bukti
  pemetaan yang relevan, bukan hasil tebakan dari judul.
- Bila beberapa sumber memberi urusan/jenis yang bertentangan, field tersebut
  dibiarkan kosong. Petugas menentukan pilihan yang benar.
- Dokumen yang telah dikonfirmasi manual tidak ditimpa. Menjalankan ulang tanpa
  perubahan sumber tidak membuat perubahan metadata atau riwayat baru.

Hasil pemetaan tetap **Perlu ditinjau**, meskipun kedua field berhasil ditemukan.
Petugas tetap perlu memeriksa kebenarannya. Perintah tidak mengubah kategori lama,
isi berkas, versi berkas, status arsip, atau data sumber.

## Hak Akses

Baca dokumen memerlukan `dokumen_humas.lihat` atau `dokumen_humas.kelola`.
Klasifikasi dan mutasi hubungan memerlukan kelola dokumen; sumber agenda/program
juga memerlukan izin kelolanya. Surat mengikuti cakupan persuratan, penugasan,
dan disposisi yang telah berlaku. Tautan/detail sumber tidak ditampilkan tanpa
hak baca sumber. Akun siswa/orang tua tidak dapat mengakses arsip petugas meskipun
secara keliru memiliki role Humas. Tidak ada seeder/grant izin baru.

Urusan pengaduan tidak membuka data tiket, identitas pelapor, atau lampiran
pengaduan. Tidak ada penyalinan otomatis lampiran pengaduan ke pusat dokumen.

## Pemasangan Server

Cadangkan database lebih dahulu, lalu pasang skema dan pratinjau pemetaan:

```powershell
php artisan nusa:cadangkan-database
php artisan migrate --force
php artisan humas:tata-arsip
```

Periksa ringkasan sebelum menerapkan dan membersihkan cache:

```powershell
php artisan humas:tata-arsip --terapkan
php artisan optimize:clear
```

Migrasi baru: `2026_10_22_000001_tambah_klasifikasi_dan_kaitan_dokumen_humas`.
Tidak perlu menjalankan seeder, mengunggah ulang dokumen, atau mengganti role.
Backup database tidak mencakup berkas unggahan; pencadangan berkas tetap terpisah.
Perubahan backend/API tersedia untuk client baru; APK lama tidak otomatis mendapat
kontrol klasifikasi/kaitan tanpa pembaruan layar aplikasinya.

## Pengujian

`KlasifikasiDokumenHumasTest` mencakup transaksi kolektif, konflik sidik, backfill
konservatif/idempoten, kompatibilitas API lama, hubungan balik, dan cakupan surat
rahasia. `tests/Browser/klasifikasi-humas.mjs` memeriksa halaman daftar/tambah/edit/
detail serta hubungan balik di program Humas, komite, dan surat pada enam lebar
layar dan interaksi pilihan kolektif. Fixture browser
dibuat oleh tes dengan `NUSA_CAPTURE_KLASIFIKASI_HUMAS=1`.

Verifikasi 22 Oktober 2026: 440 tes Humas/modul terkait lulus (9.061 assertion),
42 kondisi tampilan arsip/hubungan balik dan 6 pengiriman kolektif simulasi lulus.
Dashboard tujuh bidang diperiksa pada 8 lebar layar; kerangka admin (240 kondisi)
dan tema lintas akun (288 kondisi) juga lulus. Pengujian API/fitur menggunakan
SQLite terisolasi dan fixture browser, bukan uji APK pada perangkat nyata.

Di PostgreSQL lokal, cadangan pengaman dibuat sebelum migrasi. Tiga arsip lama
tetap ada; dua mendapat pemetaan metadata dan semuanya tetap perlu ditinjau.
Sidik kategori/status/lokasi berkas serta seluruh riwayat versi sama sebelum dan
sesudah penataan. File fisik ketiganya sudah tidak tersedia di lokal sebelum
penataan; tidak ada file yang dihapus oleh proses ini. Pratinjau ulang melaporkan
nol perubahan. Kondisi server sekolah harus diperiksa terpisah saat pemasangan.
