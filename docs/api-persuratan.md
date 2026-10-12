# API Persuratan & Arsip

Backend menyediakan 20 endpoint surat masuk, surat keluar/tugas, pengesahan,
surat pribadi, disposisi dan pelaporan. Memakai tabel, layanan domain, izin dan
notifikasi yang sama dengan web. Tidak ada migrasi baru untuk penambahan API ini.
Nomor tetap manual; surat final dibuat di luar NUSA lalu diunggah.

## Menu Mobile

`GET /api/v1/menu` menyediakan kelompok `persuratan-arsip`, berlabel
**Persuratan & Arsip**, sesuai izin dan identitas akun.

| Kode menu | Rute yang disiapkan | Endpoint daftar | Akses default |
| --- | --- | --- | --- |
| arsip-surat | /persuratan | /api/v1/persuratan | TU, pimpinan, administrator |
| pengesahan-surat | /persuratan/pengesahan | /api/v1/persuratan/pengesahan | Pimpinan, administrator |
| surat-saya | /surat-saya | /api/v1/surat-saya | Akun pegawai aktif |
| monitoring-tindak-lanjut-surat | /persuratan/tindak-lanjut | /api/v1/persuratan/tindak-lanjut | TU, pimpinan, administrator |
| tindak-lanjut-surat-saya | /tindak-lanjut-surat | /api/v1/tindak-lanjut-surat | Akun pegawai aktif |

`api_tersedia=true` menandai backend siap, sedangkan `status=segera_hadir`
menandai **layar native belum dibuat/terdaftar pada APK**. Kedua status berbeda.
Menu tidak dijadikan akses cepat sampai layar tersedia; pilihan akses cepat lama
tetap dijaga. Guru mendapat dua menu pribadi, TU empat, pimpinan lima,
administrator tanpa identitas pegawai tiga. Siswa/orang tua dan pegawai nonaktif
tidak mendapat menu ini walaupun keliru diberi role TU.

Tahap integrasi Android: bangun layar daftar/detail/formulir/unduhan, daftarkan rute
di router APK, tambahkan pemetaan tujuan notifikasi, lalu ubah status katalog menjadi
`tersedia` dan rilis APK baru. Backend ini tidak mengaktifkan layar pada APK lama.
Sementara itu fitur web tetap dapat dipakai seperti sebelumnya.

## Autentikasi Dan Privasi

Semua path berikut ber-prefix `/api/v1`. Token harus ber-ability `mobile`, akun aktif,
kata sandi sudah diganti jika diwajibkan dan memiliki identitas/izin yang sesuai:

```http
Authorization: Bearer <token>
Accept: application/json
```

Mutasi tanpa file memakai JSON; unggahan memakai `multipart/form-data` dengan
boundary dibuat HTTP client. Jangan mengirim token di URL. Gunakan HTTPS di sekolah.
Tanggal `YYYY-MM-DD`; timestamp respons ISO 8601 dengan zona waktu server.

| HTTP | Arti |
| --- | --- |
| 200 | Berhasil, termasuk konfirmasi baca idempoten |
| 201 | Surat / versi laporan baru dibuat |
| 401 | Token tidak valid atau akun nonaktif |
| 403 | Ability, identitas atau izin tidak sesuai |
| 404 | Data tidak ditemukan / di luar cakupan akses |
| 422 | Validasi, versi usang atau tindakan/status ditolak |
| 428 | Wajib mengganti kata sandi |
| 429 | Rate limit; ikuti Retry-After |

Respons sukses/unduhan memakai `Cache-Control: private, no-store`. Jangan menyimpan
data surat, token atau bukti pada log/analitik/cache bersama. Nomor, instruksi,
hasil dan catatan ditampilkan sebagai teks, bukan HTML mentah. Respons tidak
memuat path storage, SHA-256, kredensial atau snapshot internal riwayat.

## Respons Dan Paginasi

Daftar ada di `data`: `items`, `paginasi`, `filter`, `statistik`,
`statistik_mengikuti_filter`, `referensi`. Statistik berkunci status, termasuk nol.
`paginasi`: `halaman`, `halaman_terakhir`, `per_halaman`, `total`,
`ada_halaman_berikutnya`. Input `halaman >= 1`, `per_halaman=1..50`, default 20.

Detail ada di `data`; mutasi menambahkan `pesan` pada tingkat teratas. Detail surat
berisi metadata, `versi`, berkas, penugasan, tindak lanjut dan `hak_akses`.
Arsip juga berisi `riwayat`, `surat_terkait` serta `penerima_disposisi` jika berwenang.
Detail pribadi hanya memuat penugasan/tindak lanjut akun sendiri, berkas aktif dan
tidak memuat penerima lain/riwayat surat. Tindak lanjut memuat instruksi, pemeriksa,
semua versi laporan/bukti, riwayat dan hak akses; `calon_pemeriksa` jika dapat diubah.

Tombol mengikuti `hak_akses`, tetapi server tetap memvalidasi kesiapan, status,
izin dan versi ketika diproses. Semua perubahan surat/tugas memerlukan versi terbaru
kecuali konfirmasi baca surat pribadi. Jika versi usang, muat ulang dan minta
pengguna meninjau ulang; jangan menimpa/retry keputusan secara diam-diam.

## Arsip Dan Pengesahan (10 Endpoint)

| Metode | Path | Fungsi |
| --- | --- | --- |
| GET | /persuratan | Arsip sesuai izin dan sifat surat |
| GET | /persuratan/referensi | Jenis/status/sifat/batas berkas/pilihan pegawai dan pengesah |
| GET | /persuratan/pengesahan | Pengajuan yang menunjuk akun ini sebagai pengesah |
| GET | /persuratan/tindak-lanjut | Monitoring sesuai cakupan arsip |
| POST | /persuratan | Catat surat masuk / draf keluar/tugas/revisi |
| GET | /persuratan/{surat} | Detail arsip |
| PATCH | /persuratan/{surat} | Ubah surat yang belum dikunci |
| POST | /persuratan/{surat}/tindakan/{aksi} | Ajukan/tarik/kembalikan/sahkan/kirim/batal |
| POST | /persuratan/{surat}/disposisi | Disposisi surat masuk |
| GET | /persuratan/{surat}/berkas/{berkas} | Unduh berkas arsip termasuk versi lama |

Filter arsip: `kata_kunci`, `jenis=masuk|keluar|tugas`, `status`,
`tahun=2000..2100`, `sifat=biasa|penting|rahasia`, `pemeriksaan_saya=0|1`.
Statistik arsip dihitung sebelum filter, seperti web. Pengesahan memaksa
`pemeriksaan_saya=1`. Surat rahasia memerlukan `surat.rahasia`; tidak hanya disembunyikan
di menu. Referensi pegawai/pengesah hanya diberikan dengan `surat.kelola`.

### Simpan Surat

Wajib: `jenis`, `nomor_surat`, `tanggal_surat`, `perihal`, `pihak`, `sifat`.
Surat masuk: tambah `nomor_agenda`, `tanggal_diterima >= tanggal_surat`.
Keluar/tugas: tambah `pengesah_pengguna_id`, berbeda dari pembuat.
Tugas: tambah `mulai_tugas`, `selesai_tugas >= mulai_tugas`, `tempat_tugas`,
`uraian_tugas`, `pegawai_ids[]` berbeda dan aktif/berakun (maksimum 100).
`keterangan` opsional. Batas: nomor 120, agenda 80, perihal/pihak/tempat 200,
keterangan/uraian 5000 karakter. Nomor ganda per register/tahun ditolak seperti web.

Pembuatan wajib `berkas_utama`: PDF/JPG/JPEG/PNG, maksimum 10 MB.
`lampiran[]` opsional, maksimum 5 aktif, masing-masing 10 MB:
PDF/gambar/DOC/DOCX/XLS/XLSX/PPT/PPTX, bukan format makro.
Validasi gagal membersihkan file yang baru diunggah, tidak menghapus berkas lama.

Perubahan wajib **seluruh metadata formulir**, `versi`, `catatan_revisi` (maks. 2000);
PATCH bukan pembaruan sebagian field. Berkas utama baru opsional. Penggantian
menonaktifkan versi lama, tidak menghapusnya. `hapus_lampiran[]` harus ID lampiran
aktif milik surat ini. Tidak ada penghapusan surat/berkas permanen lewat API.

Untuk perubahan dengan file gunakan **POST method spoofing**, karena PHP tidak
selalu mem-parsing file pada PATCH multipart:

```http
POST /api/v1/persuratan/123
Content-Type: multipart/form-data; boundary=<dibuat-client>
```

Field: `_method=PATCH`, metadata lengkap, `versi`, `catatan_revisi`, dan file baru.
Masuk ke handler PATCH yang sama. Surat masuk dikunci setelah disposisi,
keluar/tugas sejak pengajuan; surat yang pernah sah tidak dapat ditimpa.
Revisi: POST baru, jenis sama, nomor/berkas utama baru, `revisi_dari_id`, `versi_asal`.
Revisi sah membatalkan surat asal dan tugas aktifnya atomik, tanpa menghapus laporan.

### Keputusan Dan Disposisi

`aksi`: `ajukan`, `tarik`, `kembalikan`, `sahkan`, `kirim`, `batal`. Wajib `versi`.
Tarik/kembalikan/kirim/batal wajib `catatan` (maks. 2000). Sahkan wajib
`konfirmasi_berkas=true`. Pengesahan hanya pejabat terpilih, bukan pembuat sendiri;
administrator tidak melewati syarat ini. Pembatalan setelah sah oleh pengesah/admin.
Pengesahan administratif bukan tanda tangan elektronik tersertifikasi.

Disposisi wajib `versi`, `pegawai_ids[]`, `instruksi` (maks. 5000),
`batas_tanggal >= hari ini`. Memerlukan `surat.disposisi`, surat masuk aktif.
Tidak boleh ke diri sendiri atau menduplikasi penerima. Setiap penerima mendapat
tindak lanjut; pemberi disposisi menjadi pemeriksa hasilnya.

## Surat Pribadi (4 Endpoint)

| Metode | Path | Fungsi |
| --- | --- | --- |
| GET | /surat-saya | Surat tugas sendiri yang pernah sah |
| GET | /surat-saya/{surat} | Detail pribadi |
| POST | /surat-saya/{surat}/baca | Konfirmasi baca idempoten, tanpa versi |
| GET | /surat-saya/{surat}/berkas/{berkas} | Unduh berkas aktif surat sendiri |

Filter: `kata_kunci` (nomor/perihal), `status=disahkan|dikirim|dibatalkan`,
`belum_dibaca=0|1`; filter arsip lain ditolak. Draf tidak muncul. Surat rahasia yang
ditujukan ke penerima tetap dapat dibuka, tanpa memberi akses arsip rahasia.
Pembatalan terlihat sebagai riwayat, tetapi tidak dapat dikonfirmasi baca.
Konfirmasi membaca tidak menyelesaikan tugas dan tidak menandai notifikasi terbaca.

## Tindak Lanjut (6 Endpoint)

| Metode | Path | Fungsi |
| --- | --- | --- |
| GET | /tindak-lanjut-surat | Disposisi/pelaksanaan tugas sendiri |
| GET | /tindak-lanjut-surat/{tindakLanjut} | Detail, laporan, bukti, riwayat, hak akses |
| POST | /tindak-lanjut-surat/{tindakLanjut}/laporan | Progres atau pengajuan laporan |
| POST | /tindak-lanjut-surat/{tindakLanjut}/tindakan/{aksi} | Baca/mulai/ubah/terima/kembalikan/batal |
| GET | /tindak-lanjut-surat/{tindakLanjut}/bukti/{bukti} | Unduh bukti dalam cakupan akses |
| GET | /tindak-lanjut-surat/{tindakLanjut}/berkas/{berkas} | Unduh berkas aktif surat induk |

Filter daftar/monitoring: `kata_kunci`, `jenis=disposisi|tugas`, `status`,
`terlambat=0|1`, paginasi. Monitoring menerima `pemeriksaan_saya=0|1`, daftar pribadi
menolaknya. Statistik mengikuti filter. Lewat tenggat tidak termasuk tugas
selesai/dibatalkan atau menunggu pemeriksa, dan tidak otomatis menutup tugas.
Status: belum_mulai/diproses/menunggu_pemeriksaan/perlu_perbaikan/selesai/dibatalkan.

Laporan multipart wajib `versi`, `aksi=draf|ajukan`, `hasil` (maks. 10000).
`bukti[]` opsional: maksimum 5 gabungan dengan `bukti_lama[]`, masing-masing 10 MB,
format sama dengan lampiran surat. ID bukti lama wajib berasal dari **versi laporan
terakhir tugas ini**. Setiap simpan membuat versi baru; bukti/hasil lama tidak dihapus.
Pengajuan memerlukan pemeriksa aktif/berwenang dan bukan penerima sendiri.

Tindakan wajib `versi`. Baca idempoten dengan versi terbaru, mulai mengubah ke diproses;
keduanya tidak menyelesaikan tugas. Terima/kembalikan hanya untuk laporan diajukan
oleh pemeriksa yang ditunjuk, bukan penerima sendiri, termasuk akun lain pegawai sama.
TU hanya memantau. Kembalikan/ubah/batal wajib `catatan`. Ubah juga wajib `instruksi`,
`batas_tanggal`, `pemeriksa_pengguna_id` berwenang; pilihan di `calon_pemeriksa`.
Instruksi tidak dapat diubah saat diperiksa/selesai/dibatalkan. Tugas selesai tidak
dibuka ulang. Pembatalan surat memblokir pengerjaan baru, tanpa menghapus laporan.

## Berkas, Notifikasi Dan Rate Limit

Unduh `berkas.url`, `berkas_surat.url`, `laporan[].bukti[].url` dengan header Bearer.
Server memeriksa akses parent, keaktifan berkas pribadi, lokasi privat dan SHA-256.
File hilang/berubah, ID silang antar surat/tugas atau di luar izin ditolak.
Membuka GET detail/berkas tidak mengubah status baca/tugas.

Alur notifikasi sama dengan web: pengajuan ke pengesah, penugasan saat sah,
disposisi, pengajuan/keputusan laporan, perubahan dan pembatalan. Pencatatan kirim
tidak mengulang notifikasi penugasan. Push memakai worker Firebase yang sudah ada,
jika aktif, dengan isi generik. Tidak ada WhatsApp/email otomatis.
Selama layar native belum terdaftar, endpoint tujuan notifikasi mengembalikan
`tautan_mobile=null` untuk surat; APK tetap dapat menampilkan isi notifikasi melalui
fallback yang sudah ada. Tidak mengarahkan pengguna ke rute native yang belum dibuat.

Batas umum 120 request/menit per akun. Buat/ubah surat/laporan 15/menit per operasi;
tindakan/disposisi/baca 30/menit per operasi. Ikuti Retry-After pada 429.
Jangan retry unggahan setelah putus jaringan tanpa memeriksa nomor/versi yang sudah
tercatat. POST buat surat tidak memakai idempotency-key; nomor unik mencegah duplikasi.

## Deployment Dan Verifikasi

Sesudah pull backend:

```powershell
php artisan optimize:clear
php artisan queue:restart
```

Tidak perlu migrasi/layanan NSSM baru jika dua migrasi TU awal sudah terpasang.
Cadangkan database **dan** berkas privat; dump database tidak berisi unggahan.
Pengujian memakai SQLite sementara, storage fake dan push nonaktif:

```powershell
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/Api/PersuratanApiTest.php
```
