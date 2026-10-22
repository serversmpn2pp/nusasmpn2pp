# FAQ dan Laporan Kinerja Semester Humas

## Akses dan Navigasi

- Web FAQ: `/faq-humas`. Akun aktif yang sudah masuk dapat membaca FAQ terbit untuk
  pembaca `semua`, ditambah `pegawai`, `siswa`, atau `orang_tua` sesuai identitas
  akun sebenarnya. Tidak ada halaman publik tanpa login.
- Humas: FAQ & Bantuan berada di layanan pendukung; Laporan Kinerja Semester ada
  di Dokumen Kedinasan. Siswa/orang tua mendapat FAQ di Akun Saya.
- `faq_humas.kelola`: menyusun, mengubah, menerbitkan, mengarsipkan, dan melihat
  riwayat FAQ. Default Wakil Humas; administrator mengikuti akses sistem.
- `kinerja_humas.lihat`: membaca/cetak laporan, default Wakil Humas dan pimpinan.
  `kinerja_humas.kelola`: menyusun/finalisasi/revisi, default Wakil Humas.
- Identitas siswa/orang tua tidak boleh mengelola FAQ atau membaca laporan
  kinerja meskipun keliru mendapat role Humas. Tidak ada pemasangan role ke akun
  atau perubahan penugasan pengguna secara otomatis.
- Semua hasil privat memakai `Cache-Control: private, no-store`. Izin sumber
  agregat/program diperiksa ulang pada web, API, cetak, dan laporan final tersimpan.
  Pemegang izin laporan saja mendapat salinan terbatas, bukan semua data sumber.

## FAQ

FAQ berisi pertanyaan, jawaban teks biasa (bukan HTML), urusan, pembaca, dan urutan
tampil 0-999. Tersedia pencarian pertanyaan/jawaban dan filter urusan. Editor juga
dapat memfilter status dan pembaca. Tidak ada konten/kebijakan yang dibuat otomatis.

Penyimpanan selalu menghasilkan `draf`. Mengedit FAQ terbit menariknya dari akun
pembaca sampai diterbitkan ulang. Status: `draf`, `terbit`, `arsip`; FAQ arsip
harus dikembalikan ke draf sebelum diterbitkan. Perubahan/status memerlukan `versi`
terkini dan `alasan` 5-1000 karakter. Riwayat menyimpan salinan setiap versi;
antarmuka/API riwayat mengembalikan metadata tindakan, bukan salinan isi lama.

## Laporan Semester

Web: `/kinerja-humas`. Satu laporan per tahun pelajaran dan semester
`ganjil|genap`, dapat direvisi, tidak dibuat duplikat. Tahun pelajaran mengikuti
tanggal yang sudah dikonfigurasi NUSA; pembatas semester sama dengan dashboard:
ganjil sampai 31 Desember, genap mulai 1 Januari berikutnya. Semester yang belum
dimulai ditolak. Batas data adalah akhir semester atau hari ini, yang lebih awal.

Saat menghimpun, NUSA menyimpan snapshot: indikator agregat, program beserta
target/realisasi, dan rekap bulanan. PostgreSQL menggunakan transaksi repeatable
read. Program harus sesuai tahun dan semester/tahunan, beririsan dengan semester,
dan tidak dibatalkan. Semua program dicakup (maksimal 500; lebih banyak ditolak
dengan pesan, bukan dipotong diam-diam). Realisasi hanya laporan kegiatan final
dengan tanggal selesai di rentang data. Target program tahunan tetap target
keseluruhan, bukan target yang dianggap sudah dibagi per semester.

Indikator lain memakai dasar perhitungan dashboard yang dicantumkan per indikator.
Status adalah keadaan sumber saat dihimpun, bukan rekonstruksi keadaan historis.
FAQ/kinerja tidak mengubah rumus dashboard lama. Formulir evaluasi dihitung dari
tanggal pertama dibuka, respons dari tanggal dikirim, sesuai tahun pelajaran.
Identitas/isi jawaban orang tua, isi pengaduan, nomor pribadi, dan path berkas
privat tidak disalin. Agregat pengaduan membutuhkan izin lihat/kelola seluruh
pengaduan; izin menangani tiket tertentu saja tidak memberikan agregat sekolah.

Snapshot tidak mengikuti perubahan sumber otomatis. Edit draf hanya memperbarui
judul, tanggal, narasi, dan penandatangan; tombol Perbarui Rekap Sumber menghimpun
ulang secara eksplisit. Tanggal laporan tidak boleh mendahului batas data saat
disimpan/finalisasi. Ringkasan, capaian, kendala, tindak lanjut, rekomendasi, dan
nama pimpinan harus terisi sebelum finalisasi.

Laporan di tengah semester diberi label SEMENTARA pada web/API/cetak. Finalisasi
sementara memerlukan `konfirmasi_sementara=true`. Label tetap sementara meskipun
kalender telah melewati akhir semester; buka revisi dan perbarui rekap bila ingin
melengkapi datanya. Laporan final terkunci sampai Buka Revisi dengan alasan.
Setiap tindakan/version disimpan dalam audit, termasuk snapshot lama.

Cetak tersedia sebagai HTML A4 dengan kop sekolah, tabel, evaluasi, dan ruang
tanda tangan Kepala Sekolah serta Wakil Humas. Nama/NIP diisi petugas, bukan
sertifikat tanda tangan elektronik/persetujuan pimpinan. Cetak dari browser dapat
disimpan sebagai PDF. Draf dan salinan terbatas diberi penanda yang sesuai.

## Kontrak API Tambahan

Base `/api/v1/humas`; autentikasi Sanctum ability `mobile`, akun aktif dan kata
sandi sesuai ketentuan. JSON sukses dibungkus `data`. Paginasi mengikuti API
Humas (`data.items`, `data.paginasi`). Konflik versi/validasi/alur: HTTP422;
izin tidak cukup:403; sumber FAQ yang tidak boleh dibaca:404; akun nonaktif:401.

| Metode | Path | Keterangan |
| --- | --- | --- |
| GET | `/faq` | Paginasi; `kata_kunci`, `urusan`; editor: `sasaran`, `status` |
| GET | `/faq/referensi` | Urusan dan hak akses; editor: sasaran/status/token |
| POST | `/faq` | Simpan draf dengan UUID `token_pembuatan` |
| GET | `/faq/{faq}` | Detail yang sesuai pembaca/editor |
| PATCH | `/faq/{faq}` | Isi lengkap, `versi`, `alasan`; kembali draf |
| PATCH | `/faq/{faq}/status` | `versi`, `status`, `alasan` |
| GET | `/faq/{faq}/riwayat` | Metadata audit, khusus editor |
| GET | `/kinerja` | Paginasi; filter tahun_pelajaran_id/semester/status |
| GET | `/kinerja/referensi` | Tahun/semester/status/hak akses/token untuk pengelola |
| GET | `/kinerja/pratinjau` | Pengelola; tahun_pelajaran_id dan semester; tanpa menyimpan |
| POST | `/kinerja` | Himpun snapshot dan simpan draf |
| GET | `/kinerja/{kinerja}` | Narasi dan snapshot sesuai izin sumber saat ini |
| PATCH | `/kinerja/{kinerja}` | Isi narasi/penandatangan/tanggal lengkap, versi, alasan |
| POST | `/kinerja/{kinerja}/tindakan/{aksi}` | versi/alasan; aksi perbarui-data/finalisasi/buka-revisi |
| GET | `/kinerja/{kinerja}/riwayat` | Metadata audit, dipaginasi |
| GET | `/kinerja/{kinerja}/cetak` | HTML privat, bukan JSON/PDF biner |

POST kinerja memerlukan `token_pembuatan` UUID, `tahun_pelajaran_id`, `semester`,
`judul`, `tanggal_laporan` YYYY-MM-DD, `nama_penyusun`. Opsional pada draf:
`ringkasan`, `capaian`, `kendala`, `tindak_lanjut`, `rekomendasi`, `nama_pimpinan`,
`nip_penyusun`, `nip_pimpinan`. Kelima narasi dan nama pimpinan wajib saat final.
Periode tidak diubah lewat PATCH. Isi snapshot/status/versi tidak boleh ditulis
client; server menghitung/mengelolanya. Token hanya untuk create, retry token yang
sama oleh pembuat yang sama mengembalikan data yang ada tanpa menggandakannya.

FAQ memerlukan `pertanyaan`, `jawaban`, `urusan`, `sasaran`, `urutan`. Token tidak
boleh dikirim saat update. Tampilkan jawaban/narasi API sebagai teks ter-escape.
API baru belum menambahkan menu atau layar native pada APK; jumlah endpoint
Humas kini 109, dengan 16 tambahan FAQ/kinerja.

## Pemasangan

```powershell
php artisan nusa:cadangkan-database
php artisan migrate --force
php artisan optimize:clear
```

Migrasi baru: `2026_10_22_000003_buat_faq_dan_kinerja_semester_humas.php`.
Tidak perlu seeder, backfill, atau menghapus arsip lama. Tidak memerlukan build
Vite untuk Blade/CSS ini. FAQ/laporan baru kosong sampai petugas mengisinya.

## Verifikasi

```powershell
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --filter=FaqKinerjaHumasTest
$env:NUSA_CAPTURE_FAQ_KINERJA='1'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --filter=FaqKinerjaHumasTest
node tests/Browser/faq-kinerja-humas.mjs
```

Tes browser memakai HTML hasil Blade dari database SQLite pengujian, enam lebar
320-1920px, keadaan kosong/draf/final/form/pembaca orang tua, validasi konfirmasi,
accordion FAQ, gambar kop, dan cetak A4. Tidak mengirim perubahan ke data sekolah.
