# Publikasi Rapor STS

Publikasi rapor terpisah dari finalisasi CBT, penerapan nilai, dan publikasi nilai
per mapel. Rapor lama tidak otomatis diterbitkan ketika fitur ini dipasang.

## Pengelola

Administrator, wakil kurikulum, dan wali kelas dengan `nilai.rekap` mengelola
publikasi dalam cakupan kelas yang sudah berlaku. Buka Rapor STS, pilih kegiatan
dan kelas, lalu **Publikasi rapor**. Android menyediakan menu langsung
**Akademik → Penilaian → Publikasi Rapor STS** dan tombol publikasi pada halaman
Rapor STS. Pilihan kegiatan/kelas di menu langsung hanya berasal dari cakupan
pengelola. Mengganti kegiatan mengosongkan kelas lama, pilihan siswa, dan
konfirmasi; GET tidak menerbitkan rapor.

- Penerima: `orang_tua` (bawaan) atau `orang_tua_dan_siswa`. Siswa saja ditolak backend.
- Pilih satu/beberapa siswa atau semua yang siap. Konfirmasi eksplisit wajib.
- Lampiran perilaku tidak ikut secara bawaan. Jika disertakan, ringkasan setiap
  siswa terpilih harus sudah diperiksa BK dan masih sesuai sumber.
- Kesiapan mengikuti cetak resmi: periode tersimpan/sudah berakhir, wali kelas
  tersedia, dan kehadiran diperiksa dengan sumber yang masih sesuai.
- Nilai lengkap tidak diwajibkan. Nilai kosong tetap `Belum tersedia`, bukan nol.
- Publikasi kolektif atomik: satu siswa tidak siap, versi berubah, sidik sumber
  tidak sesuai, atau siswa di luar kelas menggagalkan seluruh pilihan.
- Pembatalan wajib alasan minimal 10 karakter dan konfirmasi. Riwayat tidak
  dihapus; alasan pembatalan pertama tidak ditimpa permintaan berulang.

## Salinan dan Koreksi

Tabel `publikasi_rapor_sts` menyimpan salinan data yang benar-benar tampil:
identitas, periode, nama mapel, nilai, kehadiran, keterlambatan, dan penandatangan.
Lampiran hanya berisi ringkasan yang diperiksa BK, bukan catatan konseling
privat, alasan koreksi internal, detail sumber, atau jawaban CBT.

Koreksi sumber tidak mengubah salinan yang terbit. Pengelola melihat **Ada
perubahan belum dipublikasikan**. Publikasi ulang membuat versi baru dan menutup
versi lama. Perubahan penerima/penyertaan lampiran juga perlu publikasi ulang.
Menerbitkan data/penerima yang sama dari konteks terbaru tidak membuat versi
atau notifikasi duplikat.

Permintaan memakai versi publikasi dan sidik konteks. Halaman yang terbuka
sebelum perubahan, penerbitan ulang, atau pembatalan harus dimuat ulang dan
dikonfirmasi kembali. Tidak ada retry mutasi otomatis.

## Penerima dan Privasi

- Menu web/mobile **Rapor STS Saya** untuk siswa dan **Rapor STS Anak Saya**
  untuk orang tua. Menu memiliki keadaan kosong sebelum publikasi.
- Orang tua melihat semua anak yang terhubung melalui `orang_tua_wali_siswa`,
  termasuk rapor historis yang masih dipublikasikan.
- Siswa hanya melihat rapornya sendiri dengan penerima `orang_tua_dan_siswa`.
- ID atau peran tambahan tidak membuka rapor orang lain. Versi lama/nonaktif
  ditolak dengan 404. Respons memakai `Cache-Control: private, no-store`.
- Web memakai format cetak STS yang sama. Android menampilkan ringkasan native
  dan cetak/simpan PDF A4 dengan logo dan lampiran opsional. Sebelum ekspor
  Android, izin diperiksa kembali ke server.
- Pembaca Android memakai kartu nilai, kehadiran, periode dan identitas versi.
  **Rapor** dan **Lampiran Perilaku** dipisahkan menjadi pilihan tampilan.
  Lampiran hanya tersedia jika salinan yang diterbitkan menyertakannya. Tidak
  disertakannya lampiran tidak berarti siswa tidak memiliki kejadian.
- Daftar orang tua dapat disaring berdasarkan anak dan tahun pelajaran.
  Pergantian akun membuang data layar dan respons lama tidak boleh ditampilkan.
- Pembatalan menutup akses server. Salinan yang diunduh/dicetak atau sudah
  terlihat pada layar tidak dapat ditarik kembali dari perangkat penerima.

Notifikasi NUSA dikirim kepada akun aktif penerima saat versi baru diterbitkan.
Push memakai worker yang sudah ada jika Firebase aktif. Pesan push tidak memuat
nilai, kasus, atau poin. Tidak perlu layanan NSSM baru.

## API

Semua endpoint `/api/v1` memakai Sanctum `abilities:mobile`, akun aktif, dan
gerbang penggantian kata sandi bawaan.

| Metode | Jalur | Fungsi |
| --- | --- | --- |
| GET | `/publikasi-rapor-sts` | Pilihan kegiatan/kelas dan konteks publikasi untuk menu mobile |
| GET | `/rapor-sts/{kegiatan}/{kelas}/publikasi` | Kesiapan, sidik, versi, penerima, riwayat |
| POST | `/rapor-sts/{kegiatan}/{kelas}/publikasi` | Publikasi pilihan siswa |
| DELETE | `/rapor-sts/{kegiatan}/{kelas}/publikasi` | Batalkan pilihan siswa |
| GET | `/rapor-sts-saya` | Salinan aktif yang diizinkan untuk akun |
| GET | `/rapor-sts-saya/{id}` | Salinan rapor, bukan sumber terbaru |
| GET | `/rapor-sts-saya/{id}/cetak` | HTML cetak resmi yang dipublikasikan |

Pengelolaan API juga memerlukan `akun_pegawai`, `nilai.rekap`, dan cakupan STS.
Mutasi dibatasi 15 permintaan/menit. GET tidak menerbitkan rapor.

GET `/publikasi-rapor-sts` menerima `kegiatan_id` dan `kelas_id` opsional;
respons `data.filter`, `data.opsi`, `data.konteks` memakai pilihan STS dan
kontrak konteks publikasi yang sama. Tanpa kegiatan/kelas dalam cakupan,
`konteks` bernilai `null`. Filter ID di luar cakupan ditolak, bukan diganti
dengan kelas yang tidak diminta. Menu ini memerlukan aplikasi mobile versi
terbaru; AAB lama tidak mendapat layar baru hanya dari pembaruan backend.

```json
{
  "penerima": "orang_tua",
  "sertakan_perilaku": false,
  "konfirmasi": true,
  "siswa": [{"anggota_id": 12, "versi_publikasi": 0, "sidik_sumber": "64-karakter-dari-konteks"}]
}
```

Ambil `sidik_rapor`/`sidik_gabungan` dari GET sesuai pilihan lampiran; jangan
menghitung sidik di klien. Pembatalan mengirim `konfirmasi`, `alasan`, dan `siswa`
berisi `anggota_id`/`versi_publikasi`. Respons envelope `data`, kesalahan
`message`/`errors` Laravel: 422 untuk data/versi tidak sesuai, 403 tanpa cakupan,
404 untuk salinan tidak diizinkan, 429 batas laju.

## Pemasangan

Migrasi baru: `2026_10_13_000001_buat_publikasi_rapor_sts.php`. Setelah pull:

```powershell
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
```

`queue:restart` menyegarkan worker lama; layanan NSSM yang sudah disetel restart
worker otomatis tetap digunakan. Pasang APK terbaru. Cadangan PostgreSQL
seluruh schema public otomatis mencakup tabel baru, tanpa daftar tabel statis.

Pengujian memakai database sementara dengan push dimatikan, tidak menerbitkan
rapor atau mengirim notifikasi kepada siswa nyata.
