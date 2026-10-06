# API Humas Tahap 1 Dan 2

Base path: `/api/v1/humas`. Tersedia 70 endpoint (50 tahap pertama dan 20 dashboard/arsip),
menggunakan tabel Humas yang sudah ada.
Tidak ada migrasi baru untuk penambahan API ini. Layar/menu Android Humas tersedia
secara native; lihat `docs/mobile-humas.md` untuk cakupan fitur, hak akses, dan
langkah pemasangan APK terbaru.

## Autentikasi Dan Akses

Gunakan token dari login API NUSA, dengan ability `mobile`:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

Untuk upload, gunakan `multipart/form-data`, bukan JSON; biarkan HTTP client menentukan boundary.
Jangan mengirim token lewat query string, menyimpan kredensial di dokumen, atau membuka URL lampiran
tanpa header Authorization. Gunakan HTTPS pada server sekolah.

Semua endpoint memerlukan akun aktif dan kata sandi yang sudah diganti jika diwajibkan.
Petugas mengikuti izin NUSA; orang tua dan siswa tidak dapat mengakses endpoint petugas meskipun
secara keliru mendapatkan peran Humas. Endpoint `*-saya` memerlukan akun orang tua yang benar-benar
terhubung, bukan sekadar nama peran. Pengaduan hanya milik pelapor; undangan dan evaluasi juga
memeriksa keterhubungan anak saat ini.

| Status HTTP | Penanganan client |
| --- | --- |
| 200 | Berhasil, termasuk retry idempoten |
| 201 | Data baru dibuat |
| 401 | Token tidak valid atau akun nonaktif; login ulang |
| 403 | Ability/izin/jenis akun tidak sesuai; jangan retry otomatis |
| 404 | Data tidak ditemukan atau bukan milik akun tersebut |
| 422 | Validasi/alur/versi data ditolak; tampilkan `errors` dan muat ulang jika versi lama |
| 428 | Wajib mengganti kata sandi lewat API autentikasi |
| 429 | Batas pengiriman; ikuti header `Retry-After` |

Respons JSON yang berhasil dan lampiran memakai `Cache-Control: private, no-store`. Jangan menyimpan data privat
di cache bersama atau log analitik. Isi pesan/masukan harus ditampilkan sebagai teks, bukan HTML mentah.

## Respons Dan Paginasi

```json
{
  "data": {
    "items": [],
    "paginasi": {
      "halaman": 1,
      "halaman_terakhir": 1,
      "per_halaman": 15,
      "total": 0,
      "ada_halaman_berikutnya": false
    }
  }
}
```

Detail berada di `data`; mutasi dapat menambahkan `pesan` pada tingkat teratas.
Daftar menerima `halaman` (minimal 1) dan `per_halaman` (1-50). Timestamp respons ISO 8601
dengan zona waktu; input agenda/periode menggunakan `YYYY-MM-DDTHH:mm` dalam zona waktu server,
tanggal menggunakan `YYYY-MM-DD`. Jangan mengganti zona waktu input tanpa konversi.

## Dashboard (2 Endpoint)

Izin: `dashboard_humas.lihat`. Ringkasan tiap modul tetap memerlukan izin baca/kelola
modul tersebut; izin dashboard saja tidak membuka data seluruh Humas.

| Metode | Path | Kegunaan |
| --- | --- | --- |
| GET | `/dashboard` | Statistik, perhatian, distribusi status, tren bulanan, program dan agenda |
| GET | `/dashboard/referensi` | Tahun pelajaran dan pilihan periode |

Filter: `tahun_pelajaran_id`, `periode=tahunan|ganjil|genap|kustom`.
Kustom wajib mengirim `tanggal_mulai` dan `tanggal_selesai` dengan format `YYYY-MM-DD`,
berurutan dan kurang dari 24 bulan. Tanpa filter, gunakan tahun aktif;
jika tidak ada tahun aktif, gunakan 30 hari terakhir.

Respons: `filter`, `label_periode`, `metrik`, `perhatian`, `distribusi`,
`kolom_bulanan`, `bulan`, `jumlah_program`, `program`, `agenda`.
`metrik`, `distribusi`, `kolom_bulanan`, `bulan` dan `bulan.{YYYY-MM}.jumlah`
adalah objek dengan kunci kode, termasuk saat kosong. Program/agenda/perhatian berupa daftar.
Tanggal referensi tahun dan tanggal selesai program menggunakan `YYYY-MM-DD`.

Metrik mengikuti periode; perhatian menggunakan kondisi terkini, dan agenda mendatang
mencakup 14 hari ke depan seperti web. Metrik menyediakan `label`, `jumlah`, `dasar`,
dan `warna`; URL web tidak dikirim karena beberapa modul belum memiliki API detail.
Pengaduan, tamu, dan alumni hanya dihitung tanpa identitas atau isi laporan.

## Arsip Dokumen (10 Endpoint)

Izin baca: `dokumen_humas.lihat` atau `dokumen_humas.kelola`.
Unggah, ubah metadata, revisi dan perubahan status memerlukan kelola.

| Metode | Path | Kegunaan |
| --- | --- | --- |
| GET / POST | `/dokumen` | Daftar / unggah dokumen baru |
| GET | `/dokumen/referensi` | Kategori, status, masa berlaku dan batas berkas |
| GET / PATCH | `/dokumen/{id}` | Detail / ubah metadata tanpa berkas |
| POST | `/dokumen/{id}/revisi` | Unggah versi berkas baru |
| PATCH | `/dokumen/{id}/status` | Arsipkan atau aktifkan kembali |
| GET | `/dokumen/{id}/riwayat` | Riwayat versi, terbaru dahulu |
| GET | `/dokumen/{id}/unduh` | Unduh berkas terkini |
| GET | `/dokumen/{id}/riwayat/{versi}/unduh` | Unduh versi lama milik dokumen tersebut |

Filter daftar: `kata_kunci`, `kategori`, `status=aktif|arsip`,
`masa_berlaku=tanpa_batas|masih_berlaku|segera_berakhir|kedaluwarsa`.
Tanpa filter status, dokumen aktif dan arsip ditampilkan.
Segera berakhir mencakup hari ini sampai 30 hari berikutnya; masih berlaku berarti
tanggal akhir lebih dari 30 hari ke depan. Statistik daftar adalah seluruh dokumen aktif,
bukan hanya hasil filter: `aktif`, `segera_berakhir`, `kedaluwarsa`.

Input metadata wajib: `judul` (maksimal 180), `kategori`, `ingatkan_hari_sebelum` (0-365).
Opsional: `nomor_dokumen`, `deskripsi`, `berlaku_mulai`, `berlaku_sampai`.
Tanggal wajib format `YYYY-MM-DD`; tanggal akhir tidak boleh mendahului tanggal mulai.
Unggahan baru wajib `berkas`, status aktif dan pembuat ditetapkan server.
Batas berkas 20 MB; PDF, DOC/DOCX, XLS/XLSX, PPT/PPTX, JPG/JPEG/PNG/WebP,
diperiksa berdasarkan jenis berkas. Data disimpan privat, tidak melalui storage publik.

PATCH metadata mengirim seluruh metadata wajib dan `sidik` dari GET detail/daftar;
tidak menerima `berkas`. POST revisi menggunakan multipart, seluruh metadata wajib,
`sidik`, `berkas` dan `catatan_revisi` wajib (maksimal 1000).
Revisi menambah versi; perubahan metadata saja tidak menambah versi.
Versi lama tetap tersedia, termasuk saat dokumen diarsipkan.

```json
{
  "sidik": "<64 karakter heksadesimal dari GET dokumen>",
  "status": "arsip"
}
```

`sidik` adalah penanda perubahan, bukan nomor versi berkas. Data lama ditolak dengan
422 pada `errors.sidik`, termasuk setelah perubahan dari web. Muat ulang dan minta
pengguna memeriksa perubahan sebelum mengirim kembali; jangan mengganti sidik otomatis
lalu menimpa metadata. Dokumen lama tanpa riwayat menampilkan `versi_berkas: 0`.

Detail menyediakan `berkas: {nama, tipe, ukuran_byte, url}`; riwayat menyediakan
ID versi, nomor versi, catatan dan metadata unduhan. Lokasi penyimpanan dan ID pembuat
tidak dikirim. Unduhan perlu Authorization dan tidak dapat dibuka sebagai URL publik.

Pengaitan saat unggah opsional: `agenda_humas_id` (izin `agenda_humas.kelola`)
atau `kerja_sama_humas_id` (izin `kemitraan_humas.kelola`), tidak keduanya.
Agenda dibatalkan ditolak. Unggah MoU wajib `token_unggahan_mou` UUID yang dibuat client;
retry oleh pembuat dengan token yang sama mengembalikan dokumen yang sama (200),
tanpa duplikasi file. Mitra harus aktif; MoU yang diakhiri atau sudah memiliki berkas
memerlukan alur revisi yang sesuai.

## Dokumen Agenda (3 Endpoint)

| Metode | Path | Kegunaan |
| --- | --- | --- |
| GET / POST | `/agenda/{agenda}/dokumen` | Daftar / hubungkan dokumen tersimpan |
| DELETE | `/agenda/{agenda}/dokumen/{dokumen}` | Lepas hubungan, bukan hapus arsip |

Baca memerlukan izin agenda dan dokumen. POST/DELETE memerlukan kelola agenda
dan izin baca/kelola dokumen. POST: `dokumen_humas_id`.
Agenda dibatalkan tidak dapat diubah hubungan dokumennya.
Hubungan yang sama tidak berganda saat diulang. Melepas hubungan menjaga dokumen
dan seluruh versi berkasnya. Daftar mendukung paginasi.

## Bundel Pertemuan (5 Endpoint)

| Metode | Path | Kegunaan |
| --- | --- | --- |
| GET | `/agenda/{agenda}/bundel` | Kesiapan, hambatan, sidik dan hak akses |
| GET | `/agenda/{agenda}/bundel/dokumen` | Pilihan versi terkini dokumen aktif |
| GET | `/agenda/{agenda}/bundel/formulir` | Evaluasi pertemuan yang pernah dibuka, beserta jumlah respons |
| GET | `/agenda/{agenda}/bundel/riwayat` | Riwayat pembuatan bundel, hash dan ukuran |
| POST | `/agenda/{agenda}/bundel/unduh` | Buat dan unduh ZIP |

Baca memerlukan izin baca/kelola agenda; unduh juga memerlukan `agenda_humas.bundel`.
Pilihan dokumen dan evaluasi tetap memerlukan izin baca/kelola sumbernya.
Tanpa izin sumber, data sumber tidak dibaca/dikirim; pemilihan sumber tersebut ditolak.

Bundel siap ketika agenda selesai, pembahasan dan keputusan terisi, peserta tersedia,
dan seluruh kehadiran telah dicatat. Peserta izin/tidak hadir tetap dapat dibundel.
`siap_unduh` menunjukkan kesiapan dasar; berkas pilihan yang hilang atau terlalu besar
masih dapat menyebabkan 422 saat pembuatan.

```json
{
  "sidik": "<dari GET kesiapan/pilihan bundel>",
  "dokumen_ids": [31, 35],
  "formulir_ids": [7]
}
```

`dokumen_ids` berisi `id_versi` dari pilihan bundel, bukan ID dokumen.
`formulir_ids` berisi ID evaluasi dari pilihan bundel. Kedua daftar boleh kosong.
Pilihan dari agenda lain, versi lama, dokumen arsip, duplikat dan sidik lama ditolak.
Jika agenda/peserta/lampiran/evaluasi berubah, muat ulang seluruh pilihan;
jangan mengganti sidik saja sambil mempertahankan pilihan lama.

Batas: 200 lampiran, total ukuran berkas asli 200 MB, 20 formulir, 5.000 peserta.
GET pilihan dan riwayat mendukung paginasi. ZIP memuat HTML siap cetak, logo,
lampiran terpilih dan rekap skala evaluasi. Tidak memuat identitas pengisi,
jawaban teks, token, lokasi berkas server atau hasil tindak lanjut internal evaluasi.
Riwayat API tidak mengirim snapshot/ID pilihan mentah atau ID petugas; jumlah lampiran
dan formulir hanya dikirim sesuai izin sumber.

Client harus memperlakukan respons sukses sebagai file ZIP, bukan JSON; respons gagal
tetap JSON bila menggunakan Accept application/json. Simpan ke ruang aplikasi privat,
dan beri keputusan pengguna sebelum membagikan bundel yang mengandung peserta rapat.
ZIP server bersifat sementara dan dihapus setelah dikirim; setiap pembuatan dicatat.
Pembuatan ulang membuat catatan baru, bukan mengambil file ZIP lama dari riwayat.

## Agenda Petugas (15 Endpoint)

Izin baca: `agenda_humas.lihat` atau `agenda_humas.kelola`. Semua mutasi memerlukan izin kelola.

| Metode | Path | Kegunaan |
| --- | --- | --- |
| GET / POST | `/agenda` | Daftar / buat agenda |
| GET / PATCH | `/agenda/{id}` | Detail / ubah informasi agenda |
| PUT | `/agenda/{id}/notulen` | Pembahasan dan keputusan |
| PATCH | `/agenda/{id}/status` | Terjadwal, selesai, atau dibatalkan |
| GET / POST | `/agenda/{id}/peserta` | Daftar / tambah peserta manual |
| DELETE | `/agenda/{id}/peserta/{peserta}` | Hapus peserta yang belum dicatat hadir |
| PUT | `/agenda/{id}/presensi` | Simpan batch presensi, maksimal 50 baris |
| POST | `/agenda/{id}/undangan` | Undang akun orang tua berdasarkan kelas/tingkat/sekolah |
| PATCH | `/agenda/{id}/akses-presensi` | Buka/tutup QR dengan `dibuka` boolean |
| GET / POST | `/agenda/{id}/tindak-lanjut` | Daftar / tambah tindak lanjut |
| PATCH | `/agenda/{id}/tindak-lanjut/{tindak}` | Perbarui tindak lanjut |

Filter daftar: `kata_kunci`, `jenis`, `status`, `dari`, `sampai`. Pilihan jenis/status terdapat
dalam respons daftar. Detail mengirim token/link QR hanya kepada pengelola; agenda lama yang belum
memiliki token mengirim nilai null, dan token dibuat saat akses QR diatur.

Payload agenda: `judul`, `jenis`, `waktu_mulai`, `waktu_selesai`, `tempat`, `topik` wajib;
`sasaran`, `pemimpin`, `notulis`, `tautan_pertemuan` opsional. Pengaitan awal ke
`mitra_humas_id` atau `program_komite_humas_id` memerlukan izin sumber yang sesuai.
PATCH informasi agenda tidak mengubah status; gunakan endpoint status. Penyelesaian agenda
memerlukan pembahasan dan keputusan. Pembatalan memerlukan `alasan_pembatalan`.

```json
{
  "jumlah_baris": 1,
  "kehadiran": [
    {"id": 12, "versi_presensi": 0, "status_kehadiran": "hadir", "catatan": null}
  ]
}
```

Kirim `versi_presensi` dari GET peserta; batch ditolak seluruhnya jika ada peserta asing atau versi lama.
Tambah peserta: `peserta: [{nama, instansi?, peran?}]`, maksimal 100.
Undangan: `tahun_pelajaran_id`, `cakupan` (`kelas`, `tingkat`, `seluruh`), ditambah `kelas_ids`
atau `tingkat` sesuai cakupan. Respons: `baru`, `sudahAda`, `tanpaAkun`.
Tindak lanjut: `uraian`, `penanggung_jawab`, `batas_tanggal` opsional;
PATCH juga memerlukan `status`, dan `catatan` jika selesai.

## Pertemuan Orang Tua (4 Endpoint)

| Metode | Path | Kegunaan |
| --- | --- | --- |
| GET | `/pertemuan-saya?tab=mendatang` | Undangan milik akun; tab lain `riwayat` |
| GET | `/pertemuan-saya/{agenda}` | Detail undangan sendiri |
| GET | `/pertemuan-saya/scan/{token}` | Baca QR, belum mencatat hadir |
| POST | `/pertemuan-saya/scan/{token}/hadir` | Konfirmasi hadir, tanpa payload peserta |

QR web memuat URL `/presensi-pertemuan/{token}`. Client mengambil segmen token 64 karakter
dari QR NUSA, lalu memanggil endpoint scan; jangan melakukan POST otomatis hanya karena scan berhasil.
Tampilkan konfirmasi kepada pengguna. Kehadiran tidak berganda saat diulang; status izin/manual
tidak ditimpa QR. Catatan presensi internal, notulen, token QR, dan daftar peserta lain tidak dikirim.

## Pengaduan Petugas (11 Endpoint)

| Metode | Path | Kegunaan |
| --- | --- | --- |
| GET / POST | `/pengaduan` | Daftar sesuai izin / catat tiket internal |
| GET | `/pengaduan/referensi` | Pilihan input, token UUID, calon petugas; pengelola saja |
| GET / PATCH | `/pengaduan/{tiket}` | Detail / koreksi tiket internal |
| GET | `/pengaduan/{tiket}/riwayat` | Jejak penanganan tanpa identitas/snapshot pelapor |
| GET | `/pengaduan/{tiket}/pesan` | Informasi pelapor dan balasan resmi |
| POST | `/pengaduan/{tiket}/balasan` | Balasan resmi pengelola kepada orang tua |
| POST | `/pengaduan/{tiket}/tindakan/{aksi}` | Penanganan tiket |
| POST | `/pengaduan/{tiket}/lampiran` | Tambah bukti privat pengelola |
| GET | `/pengaduan/{tiket}/lampiran/{lampiran}` | Unduh dengan Authorization |

Pengelola: `pengaduan_humas.kelola`. Pemantau: `pengaduan_humas.lihat`, tanpa identitas/lampiran.
Petugas: `pengaduan_humas.tangani`, hanya tiket penugasannya. Filter: `status` (termasuk `aktif`,
`semua`), `jenis`, `kategori`, `kata_kunci`, `tugas` (`semua`, `saya`).

Pembuatan internal: `token_pembuatan` UUID, `judul`, `jenis`, `kategori`, `kanal`,
`tanggal_diterima`, `isi`, `anonim`, `prioritas`, serta `nama_pelapor` jika tidak anonim.
`kontak_pelapor` dan `lampiran[]` opsional. Kanal akun orang tua tidak dapat dipalsukan melalui endpoint ini.
Koreksi memerlukan seluruh kolom input yang berlaku, `versi`, `catatan_perubahan`; tidak menerima lampiran.
Laporan asli orang tua tidak dapat dikoreksi, gunakan balasan/permintaan informasi.

Aksi: `disposisi`, `tarik`, `proses`, `menunggu`, `usulkan-selesai`, `selesaikan`, `tutup`,
`buka-kembali`. Kirim `versi`, `catatan`; disposisi juga memerlukan `petugas_pengguna_id` dan
`batas_tanggal`. Petugas hanya melakukan aksi operasional sesuai penugasannya. Penutupan laporan
orang tua yang akunnya masih tersedia memerlukan balasan resmi terlebih dahulu.

Balasan: `token_pengiriman` UUID, `versi`, `isi_pesan` (5-3000 karakter).
Tambah lampiran: `versi`, `catatan_perubahan`, `lampiran[]`. Maksimal 3 berkas per request,
10 berkas per tiket, 10 MB per berkas; PDF/JPG/JPEG/PNG/WebP, diperiksa MIME dan ekstensi.

## Pengaduan Orang Tua (7 Endpoint)

| Metode | Path | Kegunaan |
| --- | --- | --- |
| GET / POST | `/pengaduan-saya` | Daftar sendiri / kirim laporan |
| GET | `/pengaduan-saya/referensi` | Jenis/kategori, UUID baru, batas lampiran |
| GET | `/pengaduan-saya/{tiket}` | Detail sendiri dan metadata lampiran pelapor |
| GET | `/pengaduan-saya/{tiket}/pesan` | Percakapan resmi, berpaginasi |
| POST | `/pengaduan-saya/{tiket}/informasi` | Tambah informasi pada tiket aktif |
| GET | `/pengaduan-saya/{tiket}/lampiran/{lampiran}` | Unduh hanya lampiran pelapor sendiri |

Daftar: `status=aktif|selesai|semua`, `kata_kunci`. Pembuatan: `token_pembuatan` UUID,
`judul`, `jenis`, `kategori`, `isi` (10-5000 karakter), `rahasiakan_identitas` boolean,
`lampiran[]` opsional (maksimal 3). Identitas, kanal, tanggal, pemilik, prioritas dan status
ditentukan server; input palsu diabaikan. Catatan/hasil penanganan internal tidak dikirim.
Informasi tambahan: `token_pengiriman`, `versi`, `isi_pesan`; tidak menerima lampiran tambahan.

## Instrumen Umpan Balik Petugas (10 Endpoint)

| Metode | Path | Kegunaan |
| --- | --- | --- |
| GET / POST | `/umpan-balik` | Daftar / buat draf instrumen |
| GET | `/umpan-balik/referensi` | Token, cakupan, tahun, kelas aktif |
| GET / PATCH | `/umpan-balik/{formulir}` | Detail/rekap / ubah draf |
| PATCH | `/umpan-balik/{formulir}/status` | Buka, tutup, arsipkan, atau perpanjang |
| GET | `/umpan-balik/{formulir}/jawaban-teks/{pertanyaan}` | Masukan teks anonim, berpaginasi |
| GET / POST | `/umpan-balik/{formulir}/tindak-lanjut` | Daftar / tambah tindak lanjut |
| PATCH | `/umpan-balik/{formulir}/tindak-lanjut/{tindak}` | Perbarui tindak lanjut |

Izin: `umpan_balik_humas.lihat`/`kelola`; semua mutasi memerlukan kelola. Formulir bersumber
dari agenda memerlukan izin baca/kelola agenda juga. Filter: `kata_kunci`, `status`, `tahun_pelajaran_id`.

Pembuatan: `token_pembuatan`, `tahun_pelajaran_id`, `judul`, `pengantar`, `penanggung_jawab`,
`cakupan` (`seluruh`, `kelas`, `tingkat`, `agenda`), `mulai_pada`, `selesai_pada`,
`pertanyaan: [{jenis: skala|teks, teks, wajib}]` (1-30 pertanyaan). Tambahkan `kelas_ids`,
`tingkat`, atau `agenda_humas_id` sesuai cakupan. PATCH draf juga memerlukan `versi`, `alasan`.
Setelah pernah dibuka, pertanyaan/sasaran terkunci; pembukaan membuat snapshot sasaran sekali.
Status: `versi`, `status` (`aktif`, `ditutup`, `arsip`), `alasan`; perpanjangan memerlukan
`selesai_pada` baru. Versi usang menghasilkan error `formulir`.

Tindak lanjut: `versi`, `token_pembuatan`, `uraian`, `penanggung_jawab`, `batas_tanggal`,
`status`, `hasil`, `bagikan_ringkasan`. PATCH juga memerlukan `alasan`.
Status selesai memerlukan hasil; ringkasan hanya dapat dibagikan bila selesai dan memiliki
`ringkasan_publik`. Rekap skala mengecualikan nilai 0 dari rata-rata, bukan menganggapnya nilai buruk.

## Umpan Balik Orang Tua (3 Endpoint)

| Metode | Path | Kegunaan |
| --- | --- | --- |
| GET | `/umpan-balik-saya?tab=aktif` | Formulir milik akun; tab `riwayat`, `semua` juga tersedia |
| GET | `/umpan-balik-saya/{formulir}` | Pertanyaan, jawaban sendiri, token, ringkasan publik |
| POST | `/umpan-balik-saya/{formulir}` | Kirim jawaban sekali |

```json
{
  "token_pengiriman": "00000000-0000-4000-8000-000000000001",
  "jawaban": {"12": 4, "13": "Masukan perbaikan layanan sekolah."}
}
```

Kunci jawaban adalah ID pertanyaan dari GET detail. Skala: integer 0-4; 0 berarti tidak menilai.
Teks maksimal 2000 karakter, pertanyaan wajib harus dijawab. ID asing/struktur tidak valid ditolak.
Pengiriman hanya selama periode aktif (`mulai <= sekarang < selesai`). Jawaban yang telah dikirim
tidak dapat diedit. Orang tua tidak menerima rekap seluruh responden, nama akun lain, catatan hasil
internal, ataupun snapshot sasaran. Arsip yang telah dijawab tetap dapat dibaca oleh pemiliknya.

## Retry, Versi, Dan Batas Pengiriman

Simpan satu UUID untuk satu operasi sampai respons berhasil diketahui. Saat koneksi putus, gunakan
UUID yang sama untuk retry pembuatan pengaduan/instrumen, pesan, jawaban evaluasi, dan pembuatan
tindak lanjut evaluasi. Jangan meminta UUID baru setiap retry. Token yang sudah digunakan oleh
akun/data lain ditolak. Balasan idempoten tetap diterima walau versi telah berubah akibat kiriman pertama.

Agenda, tindak lanjut agenda, unggahan dokumen umum dan bundel belum memakai UUID idempotensi;
jangan retry POST secara buta.
GET ulang untuk memastikan hasil. Upload tambahan petugas menggunakan versi, bukan UUID;
jika hasilnya tidak diketahui, muat ulang metadata sebelum mencoba lagi.

Batas umum Humas 120 request/menit/akun; pembuatan agenda 30, konfirmasi QR 20,
pembuatan tiket/laporan, balasan/informasi, dan kiriman jawaban masing-masing 15 request/menit/akun.
Penghitung pengiriman dan akses umum terpisah. Tidak perlu polling setiap detik; gunakan refresh
manual atau interval yang wajar dan backoff pada 429.
Unggah dokumen baru dan unggah revisi masing-masing 15 request/menit/akun;
pembuatan bundel 6 request/menit/akun. Batas ini terpisah dari akses umum dan tetap
menghitung request yang ditolak. Setelah koneksi putus, periksa detail/riwayat dahulu.

## Verifikasi Dan Tahap Berikutnya

Tes kontrak/security/alur: `tests/Feature/Api/HumasApiTest.php` dan
`tests/Feature/Api/HumasDashboardArsipApiTest.php`. Tes web Humas tetap dijalankan
karena kedua kanal berbagi layanan command dan transaksi yang sama. Pengujian otomatis menggunakan
SQLite terisolasi; belum merupakan uji Android pada perangkat nyata maupun uji beban server sekolah.

Verifikasi implementasi: 47 tes API Humas (23 tahap pertama, 24 dashboard/arsip),
468 tes pada seluruh suite API, dan 318 tes regresi Humas/modul terkait lulus.
Daftar rute aplikasi memuat 70 endpoint; GET dashboard/dokumen tanpa token pada server lokal
mengembalikan 401 JSON. Seluruh rute memakai autentikasi, ability mobile, pemeriksaan
akun aktif dan kewajiban mengganti kata sandi.

Tahap berikutnya: API buku tamu, kemitraan,
publikasi/aset/media/kliping, komite/program kerja, alumni/prestasi, portofolio akreditasi;
kemudian layar Android dan menu sesuai kemampuan yang benar-benar sudah diimplementasikan.
Kesiapan 70 endpoint ini tidak berarti seluruh modul Humas atau versi Android telah selesai.
