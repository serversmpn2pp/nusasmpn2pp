# Verifikasi alfa rapor STS — 25 Oktober 2026

## Hasil penelusuran

Kegagalan lama berada pada harapan tes setelah pengecualian PJJ diterapkan.
Hitungan awal sumber alfa **sudah benar, yaitu 4**. Ringkasan kegagalan yang
hanya menampilkan “4 vs 0” sebelumnya keliru ditafsirkan sebagai kegagalan
hitungan awal.

Tes memakai lima hari sekolah, 14–18 September 2026. Siswa tercatat hadir satu
hari, sehingga empat hari tanpa catatan menjadi alfa otomatis. Setelah periode
itu diberi pengecualian PJJ, empat hari tersebut tidak lagi menjadi alfa, dan
sumber berubah menjadi 0. Catatan hadir maupun presensi manual tetap ada.

Tes lama menyimpan angka rapor 4 dengan `rekap_sumber.alfa=4`, lalu mengharapkan
angka rapor tetap 4 setelah sumber menjadi 0. Kedua angka yang sama tersebut
merupakan salinan rekap, bukan koreksi manual. Perilaku produksi yang sudah
ada justru mengikuti sumber terbaru untuk salinan tanpa koreksi dan tetap
mempertahankan angka yang sengaja dikoreksi wali kelas.

## Pengujian yang diperjelas

| Skenario | Sumber awal | Angka disimpan | Sumber setelah PJJ | Angka ditampilkan setelah PJJ |
|---|---:|---:|---:|---:|
| Salinan rekap tanpa koreksi | 4 | 4 | 0 | 0 |
| Koreksi manual dengan alasan | 4 | 3 | 0 | 3, perlu pemeriksaan ulang |

Tes koreksi manual kini benar-benar memasukkan angka yang berbeda dari sumber
beserta alasan koreksinya. Tes terpisah memeriksa pembaruan salinan rekap
tanpa koreksi. Keduanya mempertahankan pemeriksaan bahwa sumber awal tetap 4.

Kedua skenario juga memeriksa bahwa:

- Perubahan sumber ditandai dan status pemeriksaan lama menjadi tidak berlaku.
- Wali kelas perlu memeriksa kembali sebelum rapor dinyatakan siap.
- Membuka/menghitung rapor tidak menulis ulang pemeriksaan yang tersimpan.
- Presensi asli tidak berubah akibat pembacaan rapor atau penerapan pengecualian.

## Dampak penerapan

Perubahan untuk temuan ini terbatas pada tes regresi dan dokumentasi.
`RaporStsService` dan aturan alfa produksi tidak diubah. Tidak ada migrasi,
perubahan data sekolah, penghitungan ulang massal, atau penghapusan koreksi.
Pemeriksaan memakai SQLite di memori dan data sintetis. AAB baru belum dibangun.

## Verifikasi akhir

**219 tes / 2.820 assertion lulus** pada regresi gabungan pengecualian presensi,
rapor STS, publikasi rapor, laporan/rincian/persentase presensi, API ujian dan
bank soal, scanner tanpa sesi, serta monitoring TV siswa/ujian.
Tes sementara untuk diagnosis telah dihapus. Pemeriksaan Pint dan whitespace
tidak menemukan masalah.
