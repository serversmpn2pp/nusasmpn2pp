# Alur Operasional Humas

## Cakupan

Tahap ini melengkapi direktori kontak, persiapan dan bundel pertemuan yang sudah
ada, dokumen pendukung MoU, serta kontribusi publikasi. Tidak mengirim pesan
WhatsApp, membuat surat resmi otomatis, mengimpor nomor orang tua/alumni, atau
menerbitkan konten langsung ke media sosial.

## Direktori Kontak

Menu berada di Humas, bidang Dokumen Kedinasan. Data manual meliputi nama,
instansi, jabatan, urusan, nomor, email, dasar izin penggunaan, catatan, dan status.
Status arsip menggantikan penghapusan permanen. Sumber izin dicatat oleh petugas;
sistem tidak menganggap data sumber otomatis mendapat persetujuan pemiliknya.

- `kontak_humas.lihat`: identitas dasar, instansi, urusan dan status saja.
- `kontak_humas.kontak`: identitas serta nomor/email/dasar penggunaan/catatan.
- `kontak_humas.kelola`: akses lengkap, tambah, koreksi, dan pengarsipan.

Wakil Humas mendapat pengelolaan, pimpinan mendapat baca identitas. Izin lain
dapat dipasangkan administrator sesuai kebutuhan. Hak modul sumber lama tidak
diubah; direktori tidak menyalin atau membuka nomor dari modul sumber tersebut.
Tanpa izin rinci, field sensitif tidak dikirim ke HTML/API dan tidak menjadi
kolom pencarian. Riwayat menyimpan nama kolom berubah dan alasan, bukan salinan
nomor/email. Hindari menaruh nomor dalam nama, instansi, atau alasan perubahan.

API POST `/kontak`: `token_pembuatan` UUID, `nama`, `urusan`, `dasar_penggunaan`
(5-1000 karakter), `status=aktif|arsip`. Opsional `instansi`, `jabatan`,
`nomor_kontak`, `email`, `catatan`. PATCH mengirim seluruh metadata yang berlaku,
`versi`, `alasan` (5-1000), tanpa UUID. Versi usang ditolak 422. Retry pembuatan
oleh pembuat dengan UUID sama mengembalikan catatan sama. UUID milik akun lain
ditolak. Daftar menerima `kata_kunci`, `urusan`, `status`, `halaman`, `per_halaman`
(1-50). Detail menggunakan `kontak_terbatas` untuk menentukan penyajian field null.

## Paket Pertemuan

Tab **Paket Pertemuan** di agenda menampilkan undangan, peserta, presensi,
notulen/keputusan, dokumentasi, evaluasi, dan kesiapan bundel akhir. Status dibaca
dari data tersimpan, bukan checkbox kelengkapan yang dapat dicentang tanpa bukti.
Klasifikasi undangan/dokumentasi memakai `jenis_dokumen` pada arsip terkait.

Petugas dapat mencetak daftar hadir/notulen, mengunggah undangan/dokumentasi ke
arsip sekaligus mengaitkannya dengan agenda, serta membuka bundel akhir yang
sudah tersedia. Cetak memakai format lama; tidak menambah nomor surat resmi.
Evaluasi dan lampiran dibatasi menurut izin sumber. Nama peserta, respons teks,
QR/token, dan identitas pengisi evaluasi tidak masuk respons paket API.

GET `/agenda/{agenda}/paket` mengembalikan `agenda`, `komponen`, `lampiran`,
`hambatan_bundel`, `siap_bundel`, dan `hak_akses`. Status komponen `siap|belum|terbatas`.
Tidak adanya dokumentasi/evaluasi opsional tidak otomatis memblokir bundel.
Aturan pokok bundel tetap sama: agenda selesai, notulen lengkap, peserta ada,
dan seluruh presensi dicatat. Berkas pilihan tetap diperiksa saat unduh bundel.

## Pendukung Kemitraan

MoU utama tidak berubah. Halaman **Dokumen pendukung** menerima proposal,
korespondensi, laporan pelaksanaan, berita acara, dokumentasi, dan jenis lainnya.
Unggah melalui pusat dokumen, lalu tautkan versi terkini dokumen aktif yang
berkasnya tersedia. Pencarian menampilkan maksimal 30 hasil terbaru; gunakan
kata kunci untuk mempersempit pilihan. Tidak ada salinan berkas kedua.

Hubungan menyimpan ID versi berkas sehingga revisi arsip tidak mengubah bukti
yang telah ditautkan. Pelepasan menyimpan riwayat, bukan menghapus berkas. Versi
MoU dan versi metadata arsip bertambah, dan hubungan balik terlihat pada arsip
untuk pengguna berizin kemitraan. Penataan arsip lama dapat membaca bukti hubungan
ini; konflik tetap memerlukan tinjauan manual.

Baca memerlukan izin kemitraan dan baca/kelola dokumen. Mutasi juga memerlukan
`kemitraan_humas.kelola`. Tidak ada akses dari keluarga siswa. Penambahan pada
mitra arsip/MoU diakhiri ditolak; pelepasan tetap tersedia sesuai izin.

POST `/kemitraan/{mou}/pendukung`: `versi` MoU terkini, `id_versi` dari pilihan,
`jenis`, `catatan` 5-1000. DELETE juga memerlukan `versi` dan `catatan`. Respons
mutasi berisi ID/versi MoU terbaru. GET daftar berpaginasi, termasuk hubungan
yang telah dilepas. Unduh memakai endpoint versi dokumen lama dengan token.
Jangan retry mutasi memakai versi lama; muat ulang data untuk memeriksa hasil.

## Kontributor Publikasi

Role **Kontributor Publikasi** hanya memiliki `publikasi_humas.kontribusi`. Tidak
dipasangkan otomatis kepada seluruh guru. Administrator dapat menambahkan role
ini ke akun pegawai yang ditugaskan lewat pengelolaan akun/role yang sudah ada.

Kontributor dapat membuat draf/foto, membaca statistik dan riwayat kirimannya,
memperbaiki draf/revisi, mengajukan dan menarik pengajuan sendiri. Kontributor
tidak mendapat arsip seluruh publikasi, agenda, bank aset, atau kontak otomatis.
ID publikasi/foto/naskah akun lain ditolak. Draf dikunci selama pemeriksaan dan
setelah persetujuan; publikasi tayang tidak bisa diedit sembarangan.

Editor Humas tetap dapat mengelola seluruh konten dan mencatat bukti tayang.
Pemeriksaan memerlukan `publikasi_humas.periksa`; pembuat/pengaju tidak boleh
memeriksa konten sendiri. Keputusan dikirim ke editor dan pembuat yang masih
berhak. Pengajuan diberitahukan kepada editor/pemeriksa, bukan semua kontributor.
Notifikasi memakai antrean NUSA yang sudah ada; tidak menambah layanan baru.

API `/publikasi` mendukung daftar, referensi, draf/foto, detail, koreksi,
tindakan, unduh foto/bukti, dan naskah. Daftar memakai `kata_kunci`, `status`,
`jenis`, `kanal`, `halaman`, `per_halaman` (1-50), dengan statistik sesuai cakupan.
Referensi mengembalikan UUID dan `cakupan=milik_sendiri|seluruh`.

POST: `token_pembuatan` UUID, `judul`, `jenis=berita|pengumuman|rilis_pers`,
`isi` (10-20000), `kanal` dari referensi; opsional `ringkasan`, `rencana_tayang`,
`agenda_humas_id` berizin, `foto[]` (maksimal 5, JPG/PNG/WebP, 2 MB per foto).
PATCH memakai metadata yang sama tanpa UUID dan `versi`; mendukung `hapus_foto[]`.
Untuk koreksi berkas multipart kirim POST dengan `_method=PATCH` agar PHP membaca
unggahan. Retry pembuatan menggunakan UUID sama, bukan UUID baru.

Tindakan: POST `/publikasi/{id}/tindakan/{aksi}` dengan `versi` dan `catatan`.
`ajukan|tarik` tersedia untuk kontributor pemilik/editor. `setujui|minta-revisi`
khusus pemeriksa non-pembuat/non-pengaju. `buka-revisi|tayang` khusus editor.
Catatan wajib untuk meminta/membuka revisi. Tayang juga memerlukan `url_tayang`,
`waktu_tayang` (tanggal/jam lokal, tidak masa depan), opsional bukti PDF/gambar
maksimal 5 MB. Detail mengirim hak akses per tindakan dan URL lampiran privat.
Pilihan aset memakai `cari`, tetap memerlukan izin bank aset dan dokumen sumber.
Snapshot aset tidak otomatis dikirim kepada kontributor tanpa izin sumber.

## Pemasangan dan Batasan

```powershell
php artisan nusa:cadangkan-database
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
```

Migrasi baru `2026_10_22_000002_lengkapi_operasional_humas` menambah tabel dan izin,
tanpa mengubah surat/arsip lama. Tidak perlu menjalankan seeder atau memindahkan
berkas. `queue:restart` meminta worker keluar setelah pekerjaan aktif selesai;
pastikan NSSM/supervisor worker NUSA menghidupkannya kembali sebagaimana biasa.
Tidak perlu menambah worker atau kanal pesan baru.

Tersedia 93 endpoint Humas, 19 di antaranya ditambahkan tahap ini. API baru tidak
berarti APK lama sudah mempunyai layar native direktori, pendukung, atau publikasi.
Menu native yang sudah ada tetap kompatibel; jangan mengiklankan layar yang belum
diimplementasikan. Tes menggunakan SQLite terisolasi dan fixture browser, bukan
uji perangkat Android nyata atau beban server sekolah.

## Verifikasi Lokal

Tanggal 22 Oktober 2026: 451 tes regresi Humas/modul terkait lulus dengan 9.285
assertion. Pengujian browser mencakup 54 kondisi operasional baru, publikasi pada
empat ukuran layar, dashboard pada delapan lebar, bundel/cetak pada tujuh lebar,
serta kerangka admin (240 kondisi) dan tema lintas akun (288 kondisi).

Backup PostgreSQL dibuat sebelum migrasi. Sidik data dokumen/versi, agenda, MoU,
publikasi, dan 916 pemasangan role pengguna sama sebelum/sesudah migrasi. Direktori
dan pendukung masih kosong; tidak ditambahkan data contoh. Tidak ada akun yang
otomatis mendapat role Kontributor Publikasi. Izin Humas/pimpinan/kontributor baru
diperiksa langsung pada role lokal. Server sekolah harus diverifikasi terpisah.
