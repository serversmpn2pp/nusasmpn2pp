# API STS untuk Android

Backend menyediakan Rapor STS, Leger STS, dan Lampiran Perilaku STS.
Perhitungan nilai, ranking, presensi, pilihan mapel, dan pemeriksaan menggunakan
layanan yang sama dengan web. Penambahan API ini tidak membutuhkan migration
baru; migration fitur STS web tetap harus sudah dijalankan.

## Akses dan menu

Semua endpoint berada di `/api/v1`, memerlukan token dengan ability `mobile`,
akun aktif, dan kata sandi yang tidak sedang wajib diganti. Kirim token lewat
header `Authorization: Bearer ...`, bukan parameter URL. Akun siswa/orang tua
tidak boleh mengakses fitur administrasi ini, termasuk ketika role pegawai
tersisip pada akun tersebut.

- Rapor/Leger: administrator, Wakil Kurikulum berizin `nilai.rekap`, atau wali
  kelas berizin `nilai.rekap` untuk kelas yang ditugaskan kepadanya.
- Pengaturan mapel seluruh tingkat: administrator atau Wakil Kurikulum saja.
- Lampiran Perilaku: administrator, Guru BK berizin sesuai penugasan tingkat,
  atau petugas berizin pengesahan Wakil Kesiswaan.
- Ranking tingkat wali kelas hanya meliputi kelas dalam cakupannya. Respons
  `cakupan_tingkat_lengkap: false` berarti bukan ranking paralel seluruh tingkat.

`GET /api/v1/menu` memuat tiga entri STS sesuai akses akun. Ketiganya memiliki
`api` (alamat endpoint) dan `api_tersedia: true`. Status `tersedia` tetap berarti
layar native Android sudah diimplementasikan, bukan sekadar API sudah ada.
Ketiganya kini memiliki layar native dan `status: tersedia`.
Rapor/Leger berada di **Akademik → Penilaian**. Pembaruan backend tidak otomatis
menambah layar ke APK lama; distribusikan build Android yang memuat rute
`/rapor-sts` dan `/leger-sts` bersamaan dengan aktivasi menu di server.

### Cakupan layar mobile Rapor/Leger

- Pemilihan kegiatan STS dan kelas sesuai opsi server; perubahan kegiatan
  membuang filter kelas/tingkat lama agar tidak mengambil cakupan yang salah.
- Rapor: jumlah siswa, kelengkapan nilai final, kesiapan cetak, periode, wali
  kelas, pencarian/filter siswa, rincian nilai, rekap dan status pemeriksaan
  kehadiran, catatan koreksi, serta kesiapan lampiran perilaku.
- Leger: mode kelas/tingkat, rekap nilai, ranking sesuai urutan server,
  penanda draf/ranking sementara, peringatan cakupan tingkat terbatas,
  statistik mapel dan perbandingan kelas. Jumlah/rata-rata/ranking tidak
  dihitung ulang di perangkat. Nilai nol tetap ditampilkan, nilai kosong `—`.
- Halaman dan rincian ini **hanya baca**, bukan dokumen cetak resmi. Pengaturan
  periode/mapel, koreksi kehadiran, pengecualian, cetak rapor/leger, dan kandidat
  penghargaan tetap melalui web. API mutasi/cetak/penghargaan di bawah tetap
  tersedia, tetapi belum disambungkan ke tombol mobile.
- Tarik halaman atau muat ulang untuk membaca pembaruan server. Kegagalan
  akses tidak mempertahankan data kelas lama; coba lagi menggunakan filter
  terakhir atau kembali ke pilihan awal. Tidak ada cache persisten nilai.

## Rapor STS

```text
GET /api/v1/rapor-sts
PUT /api/v1/rapor-sts/{kegiatan}/{kelas}/pengaturan
PUT /api/v1/rapor-sts/{kegiatan}/{kelas}/kehadiran
PUT /api/v1/rapor-sts/{kegiatan}/{kelas}/pengecualian
PUT /api/v1/rapor-sts/{kegiatan}/{kelas}/mapel
GET /api/v1/rapor-sts/{kegiatan}/{kelas}/cetak
```

GET menerima `kegiatan_id`, `kelas_id`, dan opsional `anggota_id` (ID anggota
kelas, **bukan** ID siswa). Jika filter kegiatan/kelas tidak dikirim, dipilih
kegiatan terbaru dan kelas pertama dalam cakupan akses. Filter di luar cakupan
ditolak, tidak diganti diam-diam dengan kelas lain. Respons:

```json
{
  "data": {
    "filter": {"kegiatan_id": 1, "kelas_id": 1},
    "opsi": {"kegiatan": [], "kelas": [], "tingkat": []},
    "konteks": {
      "periode": {"tersimpan": true, "versi": 1, "awal": "2026-07-01", "akhir": "2026-09-20", "tanggal_rapor": "2026-10-02", "sudah_berakhir": true},
      "siswa": []
    }
  }
}
```

`konteks` bernilai `null` bila belum ada kegiatan/kelas dalam cakupan. Konteks
juga berisi kegiatan, kelas, wali kelas, mapel, ringkasan kesiapan cetak, dan
pengaturan mapel bagi petugas yang boleh mengubahnya. Baris siswa berisi:

- `id`: anggota kelas; `siswa_id`, `nama`, `nomor_absen`, dan identitas kelas minimal.
- `nilai`: angka desimal, `null` untuk belum tersedia; nol tetap nilai sah.
  Status, keterangan, sumber nilai, dan kondisi pengecualian disertakan.
- `jumlah`, `rata`, `nilai_lengkap`, `nilai_tuntas`: aturan rapor web tetap berlaku.
  Draf tidak dimasukkan ke nilai rapor.
- `kehadiran`, `rekap_awal`, `sidik_sumber`, `catatan_koreksi`, `diperiksa`,
  `sumber_berubah`, `siap_cetak`, `lampiran_perilaku_siap`.
- `keterlambatan.jumlah` dan `keterlambatan.total_menit`.

Penyimpanan periode:

```json
{"versi": 0, "tanggal_awal_presensi": "2026-07-01", "tanggal_akhir_presensi": "2026-09-20", "tanggal_rapor": "2026-10-02"}
```

Pemeriksaan kehadiran (kunci `siswa` adalah ID anggota kelas):

```json
{
  "versi": 1,
  "siswa": {
    "42": {"sakit": 0, "izin": 1, "alfa": 0, "sidik_sumber": "salin-hash-64-karakter-dari-GET", "diperiksa": true, "catatan_koreksi": null}
  }
}
```

Koreksi angka yang berbeda dari `rekap_awal` wajib diberi alasan. Koreksi rapor
tidak mengubah scan/presensi harian. Versi dan sidik sumber basi ditolak; seluruh
penyimpanan kolektif dibatalkan jika salah satu baris bermasalah. Setelah berhasil,
muat ulang GET untuk memperoleh versi terbaru; jangan retry otomatis dengan versi
baru tanpa pemeriksaan ulang.

Pengecualian tidak mengikuti ujian:

```json
{"versi": 2, "anggota_id": 42, "pengecualian": {"7": {"tidak_mengikuti": true, "alasan": "Tidak mengikuti ujian maupun susulan.", "sidik_kondisi": "salin-dari-nilai-mapel-GET"}}}
```

Kunci pengecualian adalah ID mapel. Kondisi peserta, hasil final, jadwal, dan
susulan tetap diverifikasi server. `tidak_mengikuti: false` mencabut pengecualian.

Pemilihan mapel:

```json
{"versi_mapel": 0, "sidik_mapel": "salin-dari-pengaturan_mapel-GET", "mapel_ids": [7, 8], "alasan_mapel": "Mapel lain tidak mengikuti STS."}
```

Mapel bersumber CBT/STS manual aktif tidak boleh disembunyikan. Pengaturan
berlaku untuk seluruh kelas pada tingkat tersebut, termasuk leger/statistik.

## Leger dan Penghargaan

```text
GET /api/v1/leger-sts
GET /api/v1/leger-sts/penghargaan
GET /api/v1/leger-sts/cetak
```

Filter leger: `mode=kelas|tingkat` (default kelas), `kegiatan_id`, `kelas_id`,
`tingkat=1..12`. Respons JSON memakai `data.filter`, `data.opsi`, `data.konteks`.
Konteks memuat daftar siswa/nilai, ranking, ringkasan kelas/tingkat, distribusi,
statistik mapel/per kelas, mapel tertinggi, dan `jumlah_mapel_pembagi`.

- `jumlah_leger` menjumlah nilai tersedia. `rata_leger` dibagi jumlah mapel
  yang ditetapkan, bukan hanya jumlah nilai yang sudah masuk.
- Draf boleh dipakai pada ranking sementara dan tetap berlabel `draf: true`.
- Siswa tanpa satu pun nilai tidak diberi ranking; nilai nol sah bisa diberi ranking.
- `ranking_sementara` dan `status_ranking` membedakan hasil sementara/final.
- Nilai seri memakai ranking kompetisi seperti web (1, 1, 3).
- Semua pengurutan/perhitungan berasal dari `LegerStsService`, bukan kalkulasi baru.

Penghargaan menerima filter yang sama ditambah `kategori=keseluruhan|mapel`,
`mapel_id`, dan `batas=1|3|10` (default 10). Kandidat selalu **nilai final**:
keseluruhan harus lengkap final; kategori mapel mensyaratkan nilai mapel itu final.
Siswa seri di batas ranking tetap disertakan. Respons menyediakan `kandidat`,
`ringkasan`, `ringkasan_mapel`, dan `hanya_nilai_final: true`.

## Lampiran Perilaku

```text
GET /api/v1/lampiran-perilaku-sts
PUT /api/v1/lampiran-perilaku-sts/{kegiatan}/{kelas}/isi
PUT /api/v1/lampiran-perilaku-sts/{kegiatan}/{kelas}/pemeriksaan
```

Kontrak/alur lengkap: [lampiran-perilaku-sts.md](lampiran-perilaku-sts.md).
Guru BK tetap mengikuti tingkat penugasan. Respons tidak mengirim kronologi,
catatan rahasia BK, atau model siswa/laporan penuh.

## Cetak dan Kesalahan

Endpoint cetak mengembalikan **HTML print-friendly**, bukan JSON atau PDF.
Unduh dengan header Authorization dan `Accept: application/json`; respons
berhasil tetap HTML, sedangkan kegagalan validasi menjadi JSON 422, bukan
redirect ke login/web. Tampilkan HTML memakai mekanisme dokumen yang tepercaya
di aplikasi; jangan membagikan token melalui link.

- Rapor: opsional `anggota_id`, `pratinjau=1`, `perilaku=1`. Nilai belum lengkap
  tetap dapat dicetak sebagai "Belum tersedia". Cetak final membutuhkan periode
  tersimpan/berakhir, wali kelas, dan kehadiran yang diperiksa.
- Gabungan perilaku memerlukan lampiran yang sudah diperiksa dan masih cocok
  dengan sumber terbaru, termasuk untuk pratinjau gabungan.
- Leger cetak: wajib `mode`, `kegiatan_id`, serta `kelas_id` untuk mode kelas
  atau `tingkat` untuk mode tingkat. Tetap memakai template web.
- 401: belum login; 403: ability/izin/cakupan tidak sesuai; 404: data tidak
  tersedia dalam cakupan; 422: input/kesiapan/snapshot tidak sesuai. Pengaturan
  wajib ganti kata sandi mengikuti middleware API yang sudah berlaku.
- Data sensitif memakai `Cache-Control: private, no-store`; respons JSON
  menggunakan allowlist, bukan serialisasi model Eloquent secara langsung.

## Aktivasi Backend

Pada server yang sudah menerima seluruh file pembaruan:

```powershell
php artisan migrate:status
php artisan optimize:clear
php artisan route:list --path=api/v1/rapor-sts
php artisan route:list --path=api/v1/leger-sts
php artisan route:list --path=api/v1/lampiran-perilaku-sts
```

Jika migration STS lama belum dijalankan, lakukan backup dan jalankan
`php artisan migrate --force` sesuai prosedur deployment sekolah. Jangan
menganggap build APK/AAB telah memperbarui backend. Tidak ada migration baru
dalam penambahan API ini. Server PHP/Octane/worker berumur panjang yang tidak
memuat ulang kode perlu direstart pada waktu pemeliharaan sesuai konfigurasinya.

Verifikasi: `RaporLegerStsApiTest`, `LampiranPerilakuStsApiTest`, `MenuApiTest`,
serta regresi `RaporStsTest`, `StsManualTest`, `MapelRaporStsTest`, dan
`LampiranPerilakuStsTest`. Pengujian menggunakan SQLite sementara, bukan data sekolah.
