# Menu dan API Ujian & Asesmen mobile

## Jadwal dan Riwayat siswa/orang tua — 13 Oktober 2026

Ujian Saya (termasuk pintasan pusat CBT untuk akun siswa) dan Ujian Anak Saya
memakai dua tab dengan jumlah ujian. **Jadwal** selalu menjadi tampilan awal:
bagian Berlangsung (`kelompok=aktif`) di atas, kemudian Akan datang, masing-masing
dari jadwal terdekat. **Riwayat** (`kelompok=selesai`) diurutkan dari waktu akhir
terbaru, lalu waktu mulai; jadwal tanpa waktu berada di akhir. Jadwal kosong
memiliki tombol Lihat riwayat jika ada riwayat, bukan menampilkan semua ujian lama.

Pengelompokan mengikuti keputusan server, bukan jam/tanggal HP, agar susulan,
waktu tambahan, dan ujian tertahan tidak salah diarsipkan. Pilihan tab bertahan
saat muat ulang; mengganti anak kembali ke Jadwal. Ujian berpindah kelompok
sesuai status terbaru setelah siswa kembali dari layar pengerjaan,
karena daftar dimuat ulang ke server tanpa memulai/menyelesaikan ujian otomatis.
Selesai dikerjakan tetap
dibedakan dari Waktu berakhir dan Dibatalkan. API pusat menambahkan
`siswa.items[].status_pengerjaan` untuk warna status yang tepat; klien tetap
mendukung payload lama tanpa field ini. Endpoint, pembatasan akses pengerjaan,
publikasi hasil, dan pemantauan orang tua tidak berubah. Tidak ada migrasi baru.

Verifikasi pembaruan tab: **49 tes Flutter lulus** (termasuk pengerjaan native
dan jurnal keamanan), **34 tes backend lulus** (593 assertions) pada SQLite
sementara. Pratinjau memakai data contoh melalui `NUSA_EXAM_TABS_PREVIEW=1`
dan `NUSA_UI_FONT_DIR`, dengan `flutter test --no-pub
test/exam_tabs_view_test.dart`; PNG tersimpan di `mobile/build/exam-tabs-qa`.
Belum deployment, pemasangan emulator, atau pembangunan ulang AAB.

## Pembaruan 12 Oktober 2026

Fokus pembaruan adalah pusat navigasi mobile, terutama **Ujian Terpusat → Pelaksanaan → Nilai & Hasil**. Mesin ujian, penilaian, pembatasan satu sesi/perangkat, dan jurnal Mode Aman tidak diganti. Tampilan pusat menggunakan kartu berdeskripsi tanpa tinggi tetap, mendukung layar kecil dan pembesaran teks.

Tiga modul dapat dibuka langsung dari Menu → Ujian & Asesmen, atau dari kartu alur di pusat Ujian & Asesmen:

| Tahap | Halaman native | API daftar (GET) | Izin baca, salah satu |
| --- | --- | --- | --- |
| 1. Persiapan | `/ujian-terpusat` | `/api/v1/ujian-terpusat` | `cbt.panitia`, `cbt.terpusat_lihat`, `cbt.kelola` |
| 2. Pelaksanaan | `/pelaksanaan-ujian-terpusat` | `/api/v1/pelaksanaan-ujian-terpusat` | `cbt.panitia`, `cbt.terpusat_lihat`, `cbt.kelola` |
| 3. Nilai & Hasil | `/hasil-ujian-terpusat` | `/api/v1/hasil-ujian-terpusat` | `cbt.soal_kelola`, `cbt.panitia`, `cbt.terpusat_lihat`, `cbt.kelola` |

Katalog tunggal `config/cbt_mobile.php` memasok metadata tiga pintasan menu utama dan tujuh alat pada API `GET /api/v1/pusat-cbt`. Alat lainnya adalah Bank Soal, Asesmen Kelas, Paket Soal, dan Presensi Ujian. Simulasi CBT web, Rapor STS, dan Leger STS tidak ditandai sebagai fitur native baru oleh pembaruan ini.

## Kontrak pusat CBT

- `pengelolaan.alat` tetap tersedia untuk kompatibilitas aplikasi lama. Setiap alat memiliki `kode`, `label`, `deskripsi`, `kelompok`, `status`, `rute`, dan `api`.
- `pengelolaan.alur[].langkah[]` menambahkan pintasan tahap, menggunakan metadata alat yang sama dan `nomor` tahap. Langkah dan alur tanpa izin tidak dikirim. Akun yang hanya boleh mengakses hasil menerima tahap 3, bukan izin persiapan/pelaksanaan.
- Menu staf dan bagian pengelolaan pusat CBT tidak ditampilkan kepada akun siswa/orang tua, termasuk apabila akun tersebut keliru diberi izin CBT staf. Ini merupakan pemisahan katalog; otorisasi setiap endpoint modul tetap dilakukan oleh middleware dan layanan modul yang sudah ada.
- Payload katalog tidak berisi token ujian, kunci jawaban, atau kredensial. Respons pusat dan menu tidak boleh disimpan dalam cache.
- Aplikasi baru tetap menerima payload pusat lama tanpa `langkah`: pintasan disusun hanya dari `alat` yang benar-benar dikirim server. Rute native yang tidak dikenal atau status belum tersedia tidak dapat ditekan. Metadata API adalah informasi katalog, bukan pengganti pemeriksaan hak akses server.

## Fitur modul yang dipertahankan

- Persiapan: informasi kegiatan, panitia, sesi, ruang, penetapan ruang/kelompok, pembagian peserta, dan jadwal/paket.
- Pelaksanaan: monitoring peserta, ruang/pengawas, token sesuai izin, pembaruan otomatis, Mode Aman, pergantian pengawas, dan pembukaan susulan sesuai kewenangan.
- Hasil: rincian jadwal/peserta, koreksi uraian, finalisasi, publikasi, dan penerapan nilai sesuai cakupan pengelola. Hak baca tidak otomatis memberikan hak melakukan semua aksi ini.
- Siswa tetap melalui Ujian Saya; orang tua melalui Ujian Anak Saya. Pengawas tetap mengikuti penugasan ruangnya.

## Pengujian dan penerapan

Uji backend menggunakan SQLite `:memory:`: katalog, kesesuaian menu/endpoint, filter izin, pemisahan akun siswa/orang tua, serta regresi persiapan, pelaksanaan, dan hasil. Uji Flutter mencakup navigasi tiga tahap, kompatibilitas API lama, katalog terbatas, rute tidak dikenal/belum tersedia, dan layar 320 piksel pada skala teks 1, 1,5, serta 2.

Verifikasi lokal pembaruan ini: **55 uji backend lulus** (1.713 assertions), **76 uji Flutter lulus**, `flutter analyze --no-pub` tanpa temuan, dan pemeriksaan format/diff bersih. Regresi mencakup Ujian Saya, pengawas, presensi, orang tua, jurnal keamanan, bank/paket soal, serta navigasi aplikasi. Pratinjau visual diperiksa dengan data contoh, bukan bukti deployment atau pengujian perangkat sekolah.

Pratinjau visual dengan data contoh dapat dihasilkan melalui `NUSA_CBT_PREVIEW=1`, `NUSA_UI_FONT_DIR` menunjuk folder font Flutter, kemudian `flutter test --no-pub test/cbt_center_view_test.dart`. Gambar dihasilkan di `mobile/build/ui-preview/cbt-center.png` dan `cbt-central-flow.png`. Tidak memakai akun atau data sekolah.

Perubahan ini belum otomatis diterapkan ke server atau AAB. Backend perlu membawa `config/cbt_mobile.php`, `config/menu_mobile.php`, dan `app/Services/Mobile/PusatCbtMobileService.php`. Bila konfigurasi server dicache, regenerasikan cache konfigurasi sesuai prosedur deployment sekolah. Tidak ada migration/database baru. Untuk UI baru pada HP diperlukan build APK/AAB baru setelah menu/API lain selesai. AAB sebelumnya tidak berubah.
