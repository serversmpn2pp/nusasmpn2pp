# Akses Koreksi Presensi Siswa

## Aturan

Izin koreksi tidak cukup berdiri sendiri. `KoreksiPresensiSiswaService` memeriksa akun aktif, role operasional, dan penugasan kelas sebelum menampilkan tombol maupun menyimpan perubahan web/API.

| Akun | Kewenangan |
| --- | --- |
| Administrator | Koreksi penuh semua kelas. |
| Waka Kesiswaan dengan `absensi.koreksi` | Koreksi penuh semua kelas. |
| Wali kelas dengan `absensi.koreksi` | Hanya kelas dengan `wali_kelas_id` milik pegawai tersebut. Role tanpa penugasan kelas tidak cukup. |
| Guru mapel, Guru Wali, Panitia Ujian | Tidak mendapat kewenangan koreksi dari role tersebut. Guru Wali bukan Wali Kelas. |
| Guru piket terjadwal | Tetap menggunakan menu Kehadiran Siswa Piket / Piket Saya. Hanya hari bertugas pada tahun aktif; aturan scan dan sumber catatan tetap berlaku. Tidak diberi izin koreksi penuh lewat rekap. |
| Guru PL dengan `absensi.koreksi_hari_ini` | Pengecualian terbatas yang sudah ada: hari berjalan, wajib catatan, dan tidak mengubah hasil scan. Salah pemasangan izin penuh tidak menghapus batas ini. |

Wali kelas yang juga menjadi BK atau Waka Kurikulum tidak memperoleh cakupan koreksi seluruh kelas. Cakupan lihat rekap yang lebih luas karena tugas lain tidak sama dengan kewenangan mengubah presensi. Hanya administrator atau Waka Kesiswaan yang mendapat cakupan koreksi seluruh kelas.

Peran dan izin yang diperlukan harus aktif. Akun siswa/orang tua yang salah dipasangi role tidak dapat mengoreksi presensi. Semua koreksi tetap melalui perhitungan keterlambatan, sinkronisasi poin, dan riwayat yang sudah ada. Batas hasil scan diperiksa lagi setelah catatan dikunci dalam transaksi.

## Web dan Android

- Formulir koreksi web dan penyimpanan web/API memakai evaluasi akses yang sama.
- Tabel desktop serta daftar web HP menampilkan tautan koreksi hanya pada siswa yang diizinkan.
- JSON `hak_akses.dapat_koreksi`, `items[].koreksi`, dan `hak_akses` detail mengikuti kebijakan ini. Struktur respons tidak berubah.
- Permintaan langsung ke URL atau API tidak bisa melewati kebijakan walau pengguna membuka layar lama.
- Izin lihat/laporan yang memang sah tetap dipertahankan; petugas baca-saja tidak memperoleh tombol koreksi.
- Migrasi pembatasan izin saja tidak memerlukan build AAB baru.

## Sumber jam dan riwayat

- Di **Rekap Presensi Siswa**, sumber masuk dan pulang ditampilkan terpisah. Label `Scanner` memerlukan log scan berhasil untuk jenis dan jam yang cocok dengan catatan saat ini. Jika sumber tidak bisa dipastikan, tampil `Sumber belum diketahui`.
- Buka **Rincian** di web atau ketuk kartu siswa di Android untuk melihat nama akun dan waktu pencatatan/koreksi. Rincian mengikuti cakupan baca rekap dan tidak menghitung ulang sanksi.
- API menambah `presensi.sumber_masuk`, `presensi.sumber_pulang` dan pasangan `*_label`, serta `riwayat[].dibuat_pada_label` pada detail. `sumber` dan `sumber_label` lama tetap tersedia untuk klien terdahulu.
- Tampilan sumber jam tidak memerlukan migrasi. Android perlu build baru untuk menampilkan kedua sumber; aplikasi versi lama tetap dapat memakai API.

## Scan bermasalah

- Pilih **Hadir > Scan bermasalah**, isi jam datang serta catatan. Siswa tetap hadir dan tidak mendapat sanksi atau poin presensi selama kasus **Perlu verifikasi**. Gunakan pilihan lupa/hilang hanya jika sudah terkonfirmasi.
- Admin atau Wakil Kesiswaan dengan `absensi.koreksi` dapat menemukan kasus lewat filter **Perlu verifikasi** di rekap tanggal kejadian, lalu membuka **Verifikasi**. Catat dasar keputusan. Jika buktinya kurang, biarkan kasus menunggu.
- Gunakan log berhasil bila sesuai waktu kedatangan, atau konfirmasi gangguan dengan jam datang sebenarnya. Scan susulan saat menunggu menjadi bukti tanpa langsung mengganti jam masuk. Scan pulang tetap tersimpan dan tidak dianggap bukti masuk.
- Setelah verifikasi, jam masuk terverifikasi mengikuti aturan keterlambatan biasa. Sanksi minimal dan poin kartu hanya diterapkan jika kartu lupa/hilang terkonfirmasi. Riwayat mencatat hasil, akun, dan waktu.
- Tidak ada migrasi baru atau pemrosesan ulang massal. Rilis backend sebelum versi Android yang mendukung alur ini.

## Pemasangan

```bash
php artisan migrate --force
php artisan optimize:clear
```

Migrasi `2026_11_02_000001_batasi_izin_koreksi_presensi_guru_mapel` hanya mencabut `absensi.koreksi` dan `absensi.koreksi_hari_ini` dari role `guru_mapel`. Izin lain, relasi akun dengan role, jadwal piket, riwayat, presensi, dan poin tidak diubah. Seeder bawaan memang tidak memberikan kedua izin koreksi tersebut kepada Guru Mapel.

Migrasi idempoten dan tidak memasang ulang izin berlebihan saat rollback. Pembatasan backend tetap bekerja jika izin tersebut kemudian keliru dipasang kembali. Setelah memasang perubahan, muat ulang menu/halaman; keluar dan masuk kembali bila tampilan menu lama masih tersimpan di aplikasi.

## Pengujian

`AksesKoreksiPresensiSiswaTest` menguji salah konfigurasi izin, semua status hadir/sakit/izin/alfa, penolakan tanpa perubahan absensi/riwayat/poin, wali kelas multi-role, wali tanpa penugasan, admin/Waka Kesiswaan, Guru PL, akun salah role, dan migrasi terarah. Regresi juga mencakup alur piket web/API, rekap native, serta poin presensi.
