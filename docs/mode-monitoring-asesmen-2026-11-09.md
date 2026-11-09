# Mode monitoring asesmen

Pembaruan 9 November 2026, mengikuti keputusan pengguna: asesmen baru default
**Catat saja** di web dan Android. Pengaturan hanya berlaku untuk `alur=kelas`;
kebijakan ujian terpusat tetap mengikuti pengaturan yang sudah ada.

## Pemakaian

- **Catat saja:** setiap kehilangan fokus yang terdeteksi di Android tetap
  dicatat, tanpa menahan sesi berdasarkan jumlah kejadian. Cocok untuk tryout
  mandiri di rumah. Pemulihan sesi dan Simpan & Keluar juga mengikuti mode catat.
- **Tahan setelah batas:** guru memilih mode ini, mengatur batas 1–20 kejadian
  (awal 3), lalu mencentang pemahaman konsekuensinya. Batas hanya ditampilkan pada
  mode tahan. Pembukaan oleh petugas tetap wajib menyertakan alasan 10–500 karakter.
- Mode dan batas dikunci setelah ada peserta mulai, sedang mengerjakan,
  terblokir, atau selesai. Status terblokir ikut diperiksa agar tidak dapat
  dipakai melewati penguncian kebijakan.
- Monitoring web dan Android menampilkan mode yang digunakan. Batas waktu,
  penyimpanan jawaban, identitas siswa, dan pemeriksaan akses tetap berlaku.

## Data dan kompatibilitas

Tidak ada migrasi baru atau perubahan massal pada asesmen lama. Pilihan lama
dipertahankan, termasuk saat klien tidak mengirim kolom mode pada pembaruan.
Asesmen lama yang sudah ditahan tidak otomatis dibuka atau diubah ke Catat saja.
Guru dapat menyesuaikan asesmen yang belum mulai melalui form edit.

Web/API memakai `tindakan_pindah_aplikasi=catat|tahan` dan
`batas_pindah_aplikasi`. Pemilihan tahan pada asesmen baru atau peralihan dari
catat harus menyertakan `konfirmasi_mode_tahan=true`; server menolak permintaan
tanpa konfirmasi. Ini mencegah aplikasi lama yang otomatis mengirim `tahan`
memilih mode tahan tanpa sepengetahuan guru. Pencatatan tetap aktif pada kedua mode.

Perbarui backend dan jalankan `php artisan optimize:clear`. Form serta label
Android sudah disertakan dalam AAB **1.0.8+9 yang dibangun ulang** pada 9 November
2026; gunakan berkas terbaru dengan checksum di panduan rilis. Jika aplikasi guru
lama menerima pesan konfirmasi mode, gunakan form web terbaru atau perbarui
aplikasi. Belum diterapkan ke server produksi atau diuji pada HP fisik.

Migrasi pengetatan yang sudah terbit sebelumnya tidak diubah atau diputar balik.
Jika migrasi lama itu masih tertunda di server, ia tetap memiliki efek historis
yang dijelaskan dalam panduan penahanan sebelumnya; periksa mode asesmen lama
setelah pembaruan sebelum digunakan.

## Verifikasi

- 76 tes backend / 1.296 assertion lulus: default web/API, konfirmasi tahan,
  pelestarian mode pada klien lama, penguncian setelah mulai, monitoring,
  enam interval keluar/kembali, pemulihan sesi, penyimpanan jawaban, dan regresi
  ujian terpusat. Satu fixture dikoreksi karena paket uji berisi satu soal.
- 70 tes Flutter lulus: default Catat saja, pilihan dan konfirmasi tahan,
  label monitoring, enam kehilangan fokus tanpa tahan, mode ketat, draft,
  pengawasan, dan kewajiban alasan pembukaan. Dua tes diperbaiki agar tombol
  digulir sepenuhnya ke area layar setelah penambahan label/form.
- Log di `mobile/build/security-audit-2026-11-09/`:
  `assessment-modes-backend.log`, `assessment-modes-flutter.log`, dan
  `assessment-modes-analyze.log`. Tes memakai SQLite dalam memori atau server
  tiruan; tidak mengubah data sekolah.
