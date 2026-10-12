# API Pengecualian Presensi

Endpoint menggunakan layanan domain yang sama dengan web, bukan penghapusan
baris presensi. Menu `pengecualian-presensi` pada kelompok `kehadiran` berstatus
`tersedia`, rute Android `/pengecualian-presensi`, API `/api/v1/pengecualian-presensi`.

## Akses Dan Endpoint

Token Sanctum ber-ability `mobile`, akun aktif, kata sandi bukan default, dan izin
`absensi.pengaturan_kelola`. Siswa/orang tua (walaupun keliru diberi role admin)
dan wali kelas dengan cakupan terbatas tidak mendapat akses atau menu ini.
API Pengaturan Presensi Siswa menambahkan `hak_akses.dapat_kelola_pengecualian`
untuk menampilkan tombol pintas hanya bagi pengelola yang berwenang.
Semua respons privat menggunakan `Cache-Control: private, no-store`.

Path berikut relatif terhadap `/api/v1/pengecualian-presensi`:

| Metode | Path | Fungsi |
| --- | --- | --- |
| GET | / | Riwayat, ringkasan, filter dan paginasi |
| GET | /referensi | Tahun, kelas, jenis dan batas penetapan |
| GET | /{id} | Detail, dampak saat penetapan dan audit pembuat/pembatal |
| POST | /pratinjau | Hitung dampak, tanpa mengubah data |
| POST | / | Terapkan token pratinjau yang telah dikonfirmasi |
| POST | /{id}/batalkan | Batalkan dengan alasan dan konfirmasi |

Header: `Authorization: Bearer <token>`, `Accept: application/json`.
Tidak ada endpoint edit/hapus permanen. Batalkan lalu buat penetapan pengganti.

## Riwayat Dan Referensi

GET riwayat menerima `tahun_pelajaran_id`, `kelas_id`, `kata_kunci` (alasan,
maksimal 100 karakter), `status=semua|aktif|dibatalkan`, `halaman` (mulai 1),
dan `per_halaman` (default 20, maksimal 50). Filter kelas juga menyertakan
penetapan seluruh sekolah yang berlaku pada kelas tersebut.
`data.items`, `ringkasan`, `paginasi`, `filter` dan `hak_akses` membentuk respons.
Ringkasan mengikuti seluruh filter. Paginasi memuat `halaman`, `halaman_terakhir`,
`per_halaman`, `total`, dan `ada_halaman_berikutnya`.

Referensi menyediakan `tahun_pelajaran` (ID, nama, aktif, tanggal awal/akhir),
`kelas` (ID, nama, tingkat, ID tahun), `jenis`, `batas_pratinjau_menit=15`,
`maksimal_hari=367`, dan hak akses. Kelas harus berasal dari tahun yang dipilih.

## Pratinjau Dan Penerapan

```json
{
  "tahun_pelajaran_id": 1,
  "kelas_id": null,
  "tanggal_mulai": "2026-09-14",
  "tanggal_selesai": "2026-09-18",
  "jenis": "pjj",
  "alasan": "PJJ akibat bencana asap sesuai keputusan sekolah."
}
```

`kelas_id=null` berarti seluruh sekolah. Jenis: `pjj`, `libur_khusus`,
`keadaan_darurat`, `gangguan_scanner`. Alasan 10-1000 karakter, tanggal `YYYY-MM-DD`,
rentang dalam tahun pelajaran, maksimal 367 hari. Tahun harus memiliki tanggal.
Penetapan aktif yang bertumpuk pada cakupan sama ditolak.

Respons `data` berisi `isian`, tahun/kelas/cakupan, jenis label, `dampak`,
`siswa` minimal (nama, kelas, nomor absen, ID keanggotaan dan `alfa_dibatalkan`),
`peringatan`, `token` terenkripsi, dan `berlaku_sampai`.
Daftar siswa pratinjau dikirim seluruhnya dalam cakupan; Android mencari,
menyaring yang terdampak, dan menampilkan 20 baris per tahap secara lokal.
Tidak ada NISN, model mentah, sidik internal atau kredensial akun pada respons.

Dampak mencakup siswa dalam cakupan/terdampak, alfa otomatis dikecualikan,
hari aktif terlewati, catatan tetap tersimpan, dan alfa manual yang tetap berlaku.
Pratinjau bukan bukti hadir dan tidak mengubah presensi/poin.
Terapkan dengan POST `/`:

```json
{"token": "<token-pratinjau>", "konfirmasi": true}
```

Token terikat akun dan sumber data, berlaku 15 menit. Sumber berubah, token rusak,
token akun lain, atau kedaluwarsa menghasilkan 422 pada `errors.token`.
Android mempertahankan isian, meminta pratinjau baru dan konfirmasi baru;
tidak memperbarui token atau menerapkan secara otomatis.
Hasil pertama 201, pengulangan token yang sama 200 dengan ID sama, tanpa duplikasi.
Sesudah koneksi putus, periksa riwayat atau ulang secara manual dengan token sama.
Token dari penetapan yang sudah dibatalkan tidak boleh dipakai kembali.

## Pembatalan Dan Audit

```json
{"alasan_pembatalan": "Periode keliru menurut keputusan sekolah.", "konfirmasi": true}
```

Alasan 10-1000 karakter dan konfirmasi wajib. Pembatalan idempoten, alasan pembatalan
pertama tidak ditimpa. Respons detail memuat status, tanggal, alasan, pembuat/waktu,
pembatal/waktu/alasan, dampak historis dan `hak_akses.dapat_batalkan`.
Tidak mengekspos kunci pratinjau atau token pada detail/riwayat.

Penetapan/pembatalan menyinkronkan poin presensi otomatis melalui layanan yang
sudah ada. Scan, sakit, izin, alfa manual dan keterlambatan asli tetap tersimpan.
Koreksi rapor yang sudah disimpan tidak ditimpa; periksa ulang sumbernya.

## Batas Dan Deployment

Batas umum 60 request/menit/akun. Pratinjau 10/menit, terapkan dan batalkan
masing-masing 15/menit. Ikuti `Retry-After` pada 429, tanpa retry otomatis.

Tidak ada migrasi tambahan jika tabel pengecualian awal sudah tersedia.
Deploy backend, jalankan `php artisan optimize:clear`; gunakan worker notifikasi
lama untuk koreksi poin. Pasang APK baru untuk layar native dan tombol pintas.
Tidak ada pengecualian yang diterapkan pada data sekolah selama pengembangan ini.

Tes API menggunakan SQLite sementara dan push nonaktif. Tes widget memakai
repository simulasi untuk memeriksa konfirmasi, pergantian cakupan/tahun, konflik,
pengulangan manual, pembatalan, hak akses dan layar kecil.
