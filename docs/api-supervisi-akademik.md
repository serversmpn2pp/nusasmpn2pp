# Supervisi Akademik - Tahap 1 sampai 4

Web: `/supervisi`, `/supervisi/program`, `/supervisi/instrumen`.
API: jalur yang sama dengan awalan `/api/v1`. Memakai Sanctum dengan ability
`mobile`, akun aktif, dan gerbang ganti kata sandi bawaan. Respons privat/no-store.

## Lingkup dan Hak Akses

- Tahap ini: program semester, instrumen berversi, penugasan, jadwal, penerbitan,
  perubahan, pembatalan, praobservasi, perangkat ajar privat, penilaian observasi,
  finalisasi, koreksi, refleksi, tindak lanjut, verifikasi, notifikasi, riwayat,
  rekap semester, cetak laporan individu/kolektif, dan ekspor Excel.
- UI Android khusus supervisi belum termasuk. API siap dipakai pengembangan Android; jangan
  mengiklankan rute native yang belum diimplementasikan sebagai menu tersedia.
- `supervisi.kelola`: administrator dan waka kurikulum secara bawaan.
- `supervisi.instrumen_kelola`: administrator dan waka kurikulum secara bawaan.
- `supervisi.lihat`: administrator, pimpinan, dan waka kurikulum secara bawaan.
- Guru/supervisor aktif tidak perlu diberi izin monitoring sekolah. Tanpa izin
  global, hanya sesi terbit miliknya/penugasannya yang terlihat. Draf tidak terlihat.
- Guru hanya mengisi praobservasi miliknya; hanya supervisor yang ditugaskan dapat
  menilai. Izin administrator tidak otomatis memberi hak mengisi atas nama mereka.
  Guru tidak dapat menugaskan diri, mengubah jadwal, atau mengakses sesi guru lain.
  Siswa/orang tua ditolak meski memiliki role tambahan administrator.

## Endpoint

| Metode | Sufiks setelah `/api/v1/supervisi` | Fungsi |
| --- | --- | --- |
| GET | `/` | Jadwal sesuai cakupan akun |
| GET | `/pilihan?tahun_pelajaran_id=ID` | Penugasan, referensi jam mengajar, supervisor aktif, versi instrumen terbit |
| GET, POST | `/program` | Daftar / tambah program |
| GET, PUT | `/program/{program}` | Rincian / ubah program |
| POST | `/program/{program}/jadwal` | Tambah 1-50 sesi draf secara atomik |
| POST | `/program/{program}/jadwal/terbitkan` | Terbitkan 1-100 draf terpilih |
| POST | `/program/{program}/jadwal/batalkan` | Batalkan sesi terpilih |
| GET, PUT | `/sesi/{sesi}` | Rincian / perubahan waktu, supervisor, tempat, topik |
| GET, POST | `/instrumen` | Daftar versi / buat paket instrumen baru |
| GET, PUT | `/instrumen/{instrumen}` | Rincian / ubah versi draf |
| POST | `/instrumen/{instrumen}/terbitkan` | Terbitkan dan kunci versi |
| POST | `/instrumen/{instrumen}/revisi` | Salin versi terbit menjadi draf versi berikutnya |
| POST | `/instrumen/{instrumen}/aktif` | Aktif/nonaktifkan paket, bukan menghapus riwayat |
| GET, PUT | `/sesi/{sesi}/praobservasi` | Formulir dan draf praobservasi guru |
| POST | `/sesi/{sesi}/praobservasi/kirim` | Kunci dan kirim kepada supervisor |
| POST | `/sesi/{sesi}/praobservasi/kembalikan` | Supervisor mengembalikan untuk diperbaiki |
| POST | `/sesi/{sesi}/lampiran` | Unggah satu perangkat ajar privat |
| GET, DELETE | `/sesi/{sesi}/lampiran/{lampiran}` | Unduh / hapus sesuai cakupan dan status |
| GET, PUT | `/sesi/{sesi}/penilaian` | Formulir dan draf skor supervisor |
| POST | `/sesi/{sesi}/penilaian/finalisasi` | Kunci hasil dan tampilkan kepada guru |
| POST | `/sesi/{sesi}/penilaian/buka-ulang` | Pengelola menarik hasil final untuk koreksi |

ID `instrumen` pada rute adalah ID **versi**, bukan ID keluarga/paket. Semua mutasi
memerlukan izin kelola yang sesuai. Batas 30 permintaan/menit; tindakan 15/menit.
Envelope `data`, sukses mutasi `message`; kesalahan standar `message`/`errors`.
Daftar berpaginasi memakai `data.daftar.data`, `current_page`, `last_page`, dan
`total`; parameter `halaman`, 20 baris/halaman. Daftar jadwal mendukung `program_id`,
`status`, `dari`, `sampai`, dan `kata_kunci`. Rincian program berisi `program`,
`sesi`, `hak_akses`, `riwayat`; rincian sesi berisi `sesi`, `instrumen`, `riwayat`.

## Program dan Instrumen

Program: `token_pembuatan` UUID, `nama`, `tujuan`, `tahun_pelajaran_id`, `semester`
(`ganjil`/`genap`), `tanggal_mulai`, `tanggal_selesai`, `status` (`draf`, `aktif`,
`diarsipkan`). PUT wajib `versi` terbaru dan `alasan` 10-1000 karakter. Program
harus mencakup semua jadwal belum dibatalkan. Tahun/semester dikunci setelah ada sesi.

Instrumen POST/PUT: `nama`, `catatan`, `skala` array `{skor,label}`, `kelompok`
array `{tahap,nama,indikator}`. `indikator` adalah array string. Tahap harus
memuat perencanaan dan pelaksanaan. Skala integer berurutan 0 sampai maksimum
1-10. Maksimal 40 kelompok dan 500 indikator total. Tidak ada batas tetap
30/49 indikator; maksimum tiap tahap dihitung dari jumlah indikator x skor tertinggi.
Bobot perencanaan/pelaksanaan 50:50 setelah normalisasi tiap tahap.
POST wajib `token_pembuatan` UUID; PUT wajib `versi`. Daftar array harus berindeks
berurutan. Mutasi publikasi/revisi/status wajib `versi`, `alasan`, `konfirmasi=true`.
Perubahan status paket juga wajib `aktif_sekarang` sesuai nilai terakhir dari GET.
Revisi tidak menimpa nama, indikator, skala, atau rumus versi terdahulu. Satu draf
revisi terbuka per keluarga instrumen. Sesi menyimpan FK versi instrumen terpilih.

## Payload Jadwal

```json
{
  "sesi": [{
    "token_pembuatan": "UUID-BARU",
    "guru_mata_pelajaran_id": 12,
    "versi_instrumen_supervisi_id": 1,
    "supervisor_pegawai_id": 5,
    "waktu_mulai": "2026-10-19T08:00",
    "waktu_selesai": "2026-10-19T09:00",
    "tempat": "Ruang VIII.A",
    "topik": "Sistem pernapasan manusia",
    "alasan_di_luar_jadwal": null
  }]
}
```

Waktu tanpa offset mengikuti `timezone` pada endpoint pilihan (bawaan Asia/Jakarta).
Respons waktu memakai ISO8601 dengan offset. Mulai/selesai harus pada satu hari
dan di dalam periode program. Guru, kelas, dan mapel berasal dari penugasan aktif
tahun pelajaran yang sama; supervisor harus berbeda dari guru. Keduanya harus
punya akun pegawai aktif. Referensi jadwal mengajar adalah mingguan, bukan kalender
libur. Alasan khusus minimal 10 karakter wajib jika rentang tidak tercakup penuh
oleh jadwal mengajar. Alasan ini **tidak** membypass bentrok guru/supervisor/kelas.
Supervisor dengan jam mengajar tumpang tindih ditolak. Rentang bersebelahan boleh.
Sesi draf turut memesan waktu; sesi dibatalkan tidak lagi memblokir slot.

PUT sesi hanya mengubah supervisor, waktu, tempat, topik, alasan khusus. Tambahkan
`versi` dan `alasan` perubahan. Guru, kelas, program, dan versi instrumen tidak
dipindahkan diam-diam; buat sesi baru jika penugasan itu berubah.
Supervisor tidak dapat dialihkan setelah penilaian dibuat. Hasil final juga
mengunci perubahan/pembatalan jadwal; pengelola harus membuka koreksi dahulu.

Penerbitan/pembatalan:

```json
{
  "sesi": [{"id": 4, "versi": 1}],
  "konfirmasi": true,
  "alasan": "Jadwal sudah diverifikasi bersama guru."
}
```

Program harus aktif untuk menerbitkan jadwal. Seluruh pilihan diverifikasi dalam
transaksi; satu sesi stale/bentrok/asing menggagalkan semuanya. Lock sekolah
menserialkan perubahan jadwal antarprogram. Request ulang pembuatan dengan UUID
dan payload identik tidak menduplikasi; UUID sama dengan payload lain ditolak.
Jangan retry mutasi versi otomatis. Kesalahan versi/validasi 422, cakupan/ability
403, sesi tidak terlihat 404, tanpa token 401, kata sandi awal 428, batas laju 429.

Notifikasi hanya saat jadwal terbit, diubah sesudah terbit, atau dibatalkan sesudah
terbit. Guru dan supervisor terkait mendapat notifikasi NUSA; penggantian supervisor
juga memberi tahu supervisor lama. Push memakai worker lama, setelah commit, jika
Firebase aktif. Tidak ada pengingat H-1 berkala atau layanan NSSM baru pada tahap ini.
Mobile belum memiliki halaman native supervisi, sehingga tujuan native push belum
diaktifkan; tautan web tersedia pada notifikasi. Riwayat API menampilkan 50 perubahan
terbaru tanpa token pembuatan/salinan internal. Seluruh audit tetap tersimpan.

## Praobservasi dan Penilaian

GET praobservasi/penilaian berisi `data.sesi` (termasuk `versi` jadwal),
`data.praobservasi`, `data.penilaian`, `data.instrumen`, `data.label_isian`, dan
`data.hak`. GET tidak membuat baris baru. Versi isian yang belum dibuat adalah 0.
Semua mutasi di bawah wajib menyertakan **`versi_sesi` dan `versi` isian** terbaru;
versi isian bertambah juga saat unggah/hapus lampiran. Respons mutasi berisi
`data` isian terbaru, `data.versi_sesi`, `data.hak`, dan `message`. Kesalahan stale
422; jangan retry otomatis dengan versi baru. Semua perubahan dikunci atomik
bersama jadwal, sehingga perubahan supervisor/pembatalan tidak dapat berlomba
dengan pengisian. Jadwal harus terbit dan program tidak diarsipkan.

PUT praobservasi (penggantian isian lengkap; kunci yang tidak dikirim menjadi kosong):

```json
{
  "versi_sesi": 2,
  "versi": 0,
  "isian": {
    "topik": "Sistem pernapasan manusia",
    "tujuan_pembelajaran": "Mengidentifikasi organ dan fungsi pernapasan.",
    "jumlah_peserta_didik": 30,
    "karakteristik_peserta_didik": null,
    "kegiatan_pembelajaran": "Mengamati model, diskusi dan presentasi.",
    "media_sumber_belajar": "Model organ dan buku pelajaran.",
    "rencana_asesmen": "Asesmen awal dan rubrik presentasi.",
    "fokus_supervisi": "Penguatan asesmen formatif."
  }
}
```

Guru dapat menyimpan draf sebagian. Pengiriman memerlukan topik, tujuan, jumlah
peserta didik (1-1000), kegiatan dan asesmen. Topik maksimal 500 karakter, isian
teks lain 5000. `kirim` wajib `konfirmasi=true`; `kembalikan` juga wajib `catatan`
10-3000 karakter. Draf/dikembalikan hanya dapat dilihat guru pemilik, termasuk
lampirannya; supervisor dan pengelola hanya melihat status sampai dikirim.
Setelah terkirim guru tidak dapat mengubah isian/lampiran sampai dikembalikan.
Hasil final melarang pengembalian praobservasi sampai koreksi dibuka.
Penilaian merekam `praobservasi_versi` yang ditinjau saat skor disimpan. Jika guru
memperbaiki dan mengirim ulang praobservasi, finalisasi ditolak sampai supervisor
meninjau serta menyimpan ulang penilaian terhadap versi praobservasi terbaru.

Lampiran: POST multipart dengan `berkas`, `versi_sesi`, `versi`. PDF, DOC/DOCX,
JPG/JPEG/PNG/WebP; MIME dan ekstensi diperiksa; maksimal 10 MB, total 5 berkas.
DELETE wajib kontrol versi yang sama. Respons daftar tidak memuat lokasi disk.
Unduhan memerlukan autentikasi dan cakupan sesi serta status isian yang sesuai;
`unduh` adalah path API relatif, bukan URL publik/bertanda tangan. Android harus
mengunduh menggunakan bearer token, bukan membuka URL tanpa autentikasi.

PUT penilaian (hanya supervisor penugasan, setelah praobservasi terkirim):

```json
{
  "versi_sesi": 2,
  "versi": 0,
  "tanggal_observasi": "2026-10-19",
  "nilai": [
    {"kelompok": 0, "indikator": 0, "skor": 4, "catatan": "Tujuan terukur."},
    {"kelompok": 0, "indikator": 1, "skor": null, "catatan": null}
  ],
  "kekuatan": "Siswa aktif berdiskusi.",
  "perbaikan": "Perkuat asesmen formatif.",
  "catatan": null
}
```

Indeks kelompok dan indikator **berbasis 0** mengacu pada `data.instrumen.kelompok`
dari versi yang melekat pada sesi, bukan instrumen versi terbaru. PUT mengganti
seluruh nilai/catatan; indikator yang tidak dikirim dianggap belum dinilai.
Duplikat/indeks asing ditolak; skor integer harus ada pada skala versi tersebut.
**Null/kosong bukan 0**: 0 adalah skor sah yang sudah diisi. Catatan indikator
2000 karakter, kekuatan/perbaikan/catatan umum masing-masing 5000. Maksimal 500
indikator. Klien web mengemas skor sebagai `nilai_json` untuk menghindari batas
PHP `max_input_vars`; API Android mengirim array `nilai` sebagai JSON biasa.

`ringkasan.tahap` berisi jumlah indikator, terisi, skor perolehan/maksimal,
lengkap, nilai (0-100), kategori, bobot. Nilai tahap/akhir yang belum lengkap
adalah null, bukan nol atau rata-rata parsial. Nilai akhir = rata-rata dua nilai
tahap yang masing-masing telah dinormalisasi, dibulatkan 2 desimal. Kategori
angka akhir: >=91 Sangat Baik, >=81 Baik, >=71 Cukup, selainnya Perlu Pembinaan.

Finalisasi wajib seluruh indikator, tanggal observasi sejak tanggal jadwal hingga
hari ini dan tidak melewati akhir program, kekuatan/perbaikan, serta
`konfirmasi=true`. Hanya supervisor penugasan dapat memfinalisasi. Draf nilai
hanya terlihat oleh supervisor, bukan guru atau administrator. Setelah final,
hasil terlihat kepada guru serta akun dengan cakupan monitoring terkait.

`buka-ulang` hanya untuk `supervisi.kelola`, wajib `alasan` 10-3000 karakter dan
`konfirmasi=true`. Salinan hasil final sebelumnya tersimpan di audit, hasil
ditarik dari guru selama koreksi, dan supervisor harus memfinalisasi ulang.
Penilaian tidak memberi status selesai otomatis hanya karena waktu jadwal lewat.
Notifikasi dikirim saat praobservasi dikirim/dikembalikan, hasil final, atau hasil
ditarik untuk koreksi. Penyimpanan draf/unggahan tidak mengirim notifikasi.

## Instalasi

```powershell
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
```

Migrasi baru `2026_10_15_000001_buat_fondasi_supervisi_akademik.php` membuat lima
tabel dan mengisi tiga izin serta satu instrumen awal 30/49 indikator. Tidak membuat
jadwal nyata atau mengirim notifikasi. Tidak perlu `db:seed` keseluruhan. Cadangan
PostgreSQL schema public mencakup tabel baru. Untuk pengujian visual:
`NUSA_CAPTURE_SUPERVISI=1 php artisan test tests/Feature/SupervisiAkademikTest.php tests/Feature/PengisianSupervisiTest.php tests/Feature/PascaobservasiSupervisiTest.php`,
kemudian jalankan `tests/Browser/supervisi.mjs` dengan Playwright lokal.

Migrasi tahap 2 `2026_10_15_000002_buat_pengisian_supervisi_akademik.php` menambah
tiga tabel isian, penilaian dan lampiran tanpa mengubah data nilai atau presensi.
Lampiran berada di `storage/app/private/supervisi`; sertakan penyimpanan privat
ini pada cadangan berkas. Cadangan database saja tidak memuat isi lampiran.

Migrasi tahap 3 `2026_10_15_000003_buat_pascaobservasi_supervisi.php` menambah tiga
tabel serta `pertanyaan_refleksi` pada versi instrumen. Versi lama memperoleh
tujuh pertanyaan awal dari template Excel; skor/indikator yang sudah ada tidak
diubah. Berkas bukti baru disimpan privat di `storage/app/private/supervisi-tindak-lanjut`.

## Pascaobservasi dan Tindak Lanjut

Semua jalur berikut mengikuti awalan `/api/v1/supervisi/sesi/{sesi}`. Web memakai
jalur sama tanpa `/api/v1`. Autentikasi dan cakupan tetap mengikuti sesi; siswa,
orang tua, akun lain, dan sesi draf tidak mendapatkan akses. Pengelola/pimpinan
dapat memonitor isian terkirim, bukan menulis atas nama guru/supervisor.

| Metode | Jalur | Fungsi |
| --- | --- | --- |
| GET | `/pascaobservasi` | Refleksi, rencana terlihat, progres, hak aksi |
| PUT | `/refleksi` | Simpan draf jawaban guru |
| POST | `/refleksi/{kirim,terima,kembalikan,sesuaikan-hasil}` | Pengiriman, tinjauan, penyesuaian hasil baru |
| POST | `/pascaobservasi/{selesaikan,buka-ulang}` | Tutup/buka pendampingan |
| POST | `/tindak-lanjut` | Buat satu draf rencana supervisor |
| POST | `/tindak-lanjut/terbitkan` | Terbitkan draf terpilih secara atomik |
| GET, PUT | `/tindak-lanjut/{tindak}` | Rincian / ubah draf rencana |
| PUT | `/tindak-lanjut/{tindak}/pelaksanaan` | Draf laporan pelaksanaan guru |
| POST | `/tindak-lanjut/{tindak}/{ajukan,verifikasi,kembalikan,batalkan,buka-ulang}` | Tindakan laporan/rencana |
| POST | `/tindak-lanjut/{tindak}/lampiran` | Unggah bukti pelaksanaan |
| GET, DELETE | `/tindak-lanjut/{tindak}/lampiran/{bukti}` | Unduh / hapus bukti yang masih dapat diedit |

GET halaman berisi `data.sesi`, `data.pascaobservasi`, status tahap sebelumnya,
dan label rencana. GET rincian tindak lanjut juga memuat `data.rencana`.
Mutasi mengembalikan DTO objek yang diubah dalam `data` dan `data.versi_sesi`.
DTO tidak memuat lokasi file, token pembuatan, sidik atau salinan audit internal.
Semua respons privat/no-store. Mutasi draf dibatasi 30/menit, tindakan/berkas
15/menit. Kesalahan validasi/versi 422, salah aktor 403, objek di luar cakupan 404.

Seluruh mutasi tahap 3 wajib **`versi_sesi`, `penilaian_versi`, `versi`**.
`penilaian_versi` harus versi hasil final saat ini. `versi` adalah versi objek
refleksi/tugas yang diubah (0 untuk pembuatan); untuk penerbitan kolektif,
`versi` adalah versi refleksi, sementara `rencana[*].versi` adalah versi tugas.
Konflik versi tidak boleh di-retry otomatis dengan angka baru. Penguncian
transaksi bersama tahap 1/2 mencegah koreksi hasil beradu dengan pascaobservasi.

### Refleksi

PUT `/refleksi` memakai `jawaban` berupa array string/null berurutan, berbasis 0
sesuai `pertanyaan` dari GET. Maksimal 5000 karakter per jawaban. Draf boleh
sebagian; PUT mengganti seluruh jawaban dan sisa yang tidak dikirim menjadi
kosong. Pengiriman wajib seluruh pertanyaan terjawab, `konfirmasi=true`.
Draf/dikembalikan privat guru; supervisor/pengelola hanya melihat status.
Supervisor hanya meninjau refleksi terkirim (`terima` atau `kembalikan`).
Pengembalian wajib `catatan` 10-3000 karakter; penerimaan boleh tanpa catatan.
Refleksi yang diterima tidak dapat diedit langsung oleh guru.

Instrumen mendukung `pertanyaan_refleksi` (1-30 string berbeda, maks 1000
karakter per pertanyaan) pada POST/PUT versi draf. Klien lama yang tidak
mengirim kunci ini tetap memakai konfigurasi tersimpan/default. Versi terbit
tetap terkunci; revisi menyalin pertanyaan. Isian guru menyimpan snapshot
pertanyaan, sehingga pertanyaan tidak bergeser saat instrumen lain diperbarui.

### Rencana dan Pelaksanaan

Hanya supervisor yang ditugaskan membuat rencana, setelah refleksi diterima.
POST rencana wajib UUID `token_pembuatan` baru, `refleksi_versi` terakhir,
kontrol versi di atas, `temuan`, dan opsional `rencana_perbaikan`,
`bentuk_pendampingan`, `indikator_keberhasilan`, `tenggat` (YYYY-MM-DD).
Teks maksimal 3000 karakter. Maksimal 50 rencana tidak dibatalkan per versi
hasil. UUID/payload identik tidak menduplikasi; UUID sama/data berbeda ditolak.
PUT hanya untuk draf rencana. Rencana terbit tidak dapat diubah diam-diam;
batalkan dengan alasan lalu buat pengganti bila isi rencana berubah.

Penerbitan kolektif:

```json
{
  "versi_sesi": 2,
  "penilaian_versi": 2,
  "versi": 3,
  "konfirmasi": true,
  "rencana": [{"id": 1, "versi": 1}, {"id": 2, "versi": 1}]
}
```

Seluruh isi rencana dan tenggat wajib lengkap saat terbit; tenggat minimal
hari ini. Satu baris stale/tidak lengkap/asing menggagalkan seluruh pilihan,
termasuk notifikasi. Draf rencana privat supervisor, tidak terlihat kepada
guru atau pengelola. Rencana terbit terlihat sesuai cakupan monitoring.

Guru mengisi PUT `/pelaksanaan`: `tanggal_pelaksanaan` YYYY-MM-DD,
`laporan_pelaksanaan`, `hasil_pelaksanaan` (masing-masing maksimal 5000
karakter), dengan kontrol versi tugas. Draf parsial boleh, dan hanya terlihat
guru. `ajukan` memerlukan tanggal sejak observasi sampai hari ini, laporan
dan hasil lengkap, serta konfirmasi. Laporan dikunci sesudah diajukan.
Lewat tenggat ditandai tetapi tidak menolak laporan dan tidak memberi poin
pelanggaran. Bukti bersifat opsional: maksimal 5 berkas x 10 MB, MIME dan
ekstensi PDF/DOC/DOCX/JPG/JPEG/PNG/WebP. Upload/hapus turut menaikkan versi
tugas, hanya saat laporan dapat diedit. Unduhan menggunakan bearer token,
bukan URL publik; bukti draf hanya terlihat guru, bukti diajukan/selesai
tersedia kepada supervisor/pengelola sesuai cakupan.

Supervisor `verifikasi` atau `kembalikan` hanya laporan diajukan. Wajib
konfirmasi dan `catatan` 10-3000 karakter. Rencana belum selesai dapat
`batalkan` dengan alasan; rencana selesai dapat `buka-ulang` menjadi perlu
perbaikan. Salinan dan riwayat tetap disimpan, bukan dihapus.

### Penyelesaian dan Koreksi

`pascaobservasi/selesaikan` hanya supervisor, refleksi harus diterima,
seluruh rencana versi hasil terbaru (termasuk draf) wajib selesai atau
dibatalkan. Tanpa rencana aktif wajib `tanpa_tindak_lanjut=true`; kesimpulan
`catatan` 20-3000 karakter dan konfirmasi selalu wajib. Selesai mengunci
seluruh aksi tugas. `pascaobservasi/buka-ulang` hanya supervisor/pengelola,
dengan alasan 20-3000 karakter dan konfirmasi, mengembalikan status diterima.

Hasil observasi yang dibuka untuk koreksi membekukan seluruh mutasi tahap 3.
Setelah finalisasi ulang, versi hasil berbeda: refleksi/rencana lama tetap
tersimpan, bertanda arsip dan tidak dihitung sebagai progres hasil terbaru.
Guru harus `refleksi/sesuaikan-hasil` dengan catatan 10-3000 karakter serta
konfirmasi, lalu meninjau dan mengirim ulang refleksinya. Supervisor menerima
ulang dan menyusun rencana baru bila perlu; rencana lama tidak dipindahkan
diam-diam ke hasil baru. Notifikasi hanya pada pengiriman/tinjauan, terbit,
pengajuan/verifikasi/pengembalian/pembatalan rencana terbit, tutup/buka ulang;
penyimpanan draf/unggahan tidak mengirim notifikasi. Memakai worker lama,
tanpa scheduler/NSSM tambahan; pengingat tenggat berkala belum dibuat.

## Tahap 4: Rekap dan Laporan

Web masuk melalui `/supervisi/rekap`, tab Rekap & laporan, atau menu Rekap
Supervisi untuk pemantau sekolah. Guru/supervisor menggunakan tab yang sama
dengan cakupan sesi terbit miliknya/penugasannya, bukan seluruh sekolah.

| Metode | Sufiks setelah `/api/v1/supervisi` | Keluaran |
| --- | --- | --- |
| GET | `/rekap` | Filter, pilihan sesuai akses, statistik, paginator 20 baris |
| GET | `/rekap/cetak` | HTML cetak A4 landscape, semua baris sesuai filter |
| GET | `/rekap/excel` | Unduhan XLSX, angka numerik dan filter yang sama |
| GET | `/sesi/{sesi}/laporan` | JSON laporan hasil final lengkap |
| GET | `/sesi/{sesi}/laporan/cetak` | HTML cetak A4 portrait satu sesi |
| POST | `/laporan/cetak` | HTML cetak kolektif, body `{"sesi":[1,2]}` |

Semua jalur mengikuti autentikasi/cakupan yang sama dengan tahap sebelumnya.
Cetak/ekspor dibatasi 10 permintaan/menit. Cetak merupakan HTML autentikasi
untuk dialog Cetak / Simpan PDF di browser, bukan endpoint PDF biner server.
Klien mobile dapat memakai JSON laporan atau mengunduh HTML dengan header
Authorization; jangan mengirim token melalui query URL.

Filter rekap: `tahun_pelajaran_id`, `semester` (ganjil/genap), `program_id`,
`guru_id`, `supervisor_id`, `tahap`, `kata_kunci` (maksimal 120 karakter),
`terlambat` (0/1), `tab` (sesi/belum_terjadwal), dan `halaman` (mulai 1).
Default tahun aktif/terbaru yang boleh diakses dan semester berdasarkan bulan
sekarang. Kosongkan filter turunan ketika mengganti tahun/semester/program.
Pilihan ID di luar cakupan atau periode menghasilkan 404, bukan mengabaikan
filter secara diam-diam. Nilai `tahap` dan labelnya disediakan di `pilihan.tahap`.

`statistik` dihitung untuk seluruh hasil filter, bukan hanya halaman aktif.
Nilai final menggunakan instrumen versi sesi: normalisasi perencanaan dan
pelaksanaan, masing-masing 50%. Draf, sesi dibatalkan, hasil tidak lengkap,
dan hasil yang tidak sejalan dengan versi praobservasi tidak dihitung.
Nol adalah nilai sah; nilai kosong tetap null. Rata-rata adalah per sesi final,
bukan pemeringkatan guru. Penyesuaian refleksi setelah koreksi hasil ditandai
terpisah dan tidak dihitung sebagai pascaobservasi selesai.

Tab `belum_terjadwal` hanya untuk pemantau global. Acuannya pegawai aktif
dengan penugasan mengajar, kelas, dan mapel aktif pada tahun terpilih,
dideduplikasi per pegawai. Jadwal harus berstatus terjadwal dan telah terbit
pada semester/program terpilih; jadwal draf/dibatalkan tidak dihitung.
Ini bukan target historis resmi program. Filter guru/pencarian tetap berlaku;
filter supervisor/progres/tenggat tidak kompatibel (422).

Rekap dibatasi 1.500 sesi sebelum filter progres/pencarian dihitung; gunakan
filter program/guru/supervisor untuk mempersempit. Cetak kolektif maksimal
10 ID unik dengan isi gabungan maksimal 2 MB. Pilihan yang tidak berhak,
belum final, atau tidak lengkap menolak seluruh batch, bukan mencetak parsial.
Laporan bisa dicetak sebelum pascaobservasi selesai, dengan status aktual.
Laporan hanya menyertakan praobservasi terkirim, penilaian final, refleksi
terkirim/diterima/selesai yang sejalan dengan hasil terbaru, dan rencana terbit
untuk versi hasil tersebut. Laporan pelaksanaan/buktinya hanya setelah diajukan
atau diverifikasi. Draf milik pembaca sendiri pun tidak dimasukkan ke cetak.
Berkas pendukung dicantumkan sebagai nama berkas, bukan disisipkan sebagai
halaman atau membocorkan lokasi penyimpanan. Semua narasi di-escape.

Operasi laporan tidak mengubah status/versi dan tidak mengirim notifikasi.
Cetak memakai kunci transaksi penulis supervisi untuk konsistensi salinan.
Tahap ini tidak memerlukan migrasi baru. Regresi: `LaporanSupervisiTest`;
QA browser/cetak: `tests/Browser/supervisi-print.mjs`, dengan fixture
`NUSA_CAPTURE_SUPERVISI=1 php artisan test --filter=Supervisi`.
