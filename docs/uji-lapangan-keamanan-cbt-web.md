# Uji lapangan Mode Aman CBT web

Gunakan paket **Simulasi CBT**, akun siswa uji, dan satu pengawas. Jangan memakai
ujian bernilai. Catat jenis HP, versi browser, jaringan, jam kejadian, hasil di
halaman siswa, serta hasil di Pantau siswa/Riwayat Mode Aman.

## Yang sudah diaudit otomatis

- Web mencatat `visibilitychange`, `pagehide`, dan kembalinya fokus; heartbeat
  dikirim selama halaman terlihat. Kejadian yang melewati toleransi dihitung
  oleh server, dan batas kejadian dapat menahan ujian.
- Untuk paket terpusat, nilai bawaan saat ini adalah 3 detik dan 3 kejadian.
  Nilai tersebut ditetapkan saat paket dibuat, belum dapat diubah panitia dari
  halaman paket.
- Form jawaban terkunci saat ditahan; pengawas dapat melihat riwayat dan harus
  menulis alasan untuk membuka kembali melalui web. Waktu ujian tetap berjalan.
- Pengaturan layar penuh dan pembatasan tangkapan layar bukan jaminan pada
  browser web. Keberhasilannya pada aplikasi perlu diuji terpisah.

## Langkah uji di sekolah

| No. | Skenario | Hasil yang perlu diperiksa |
| --- | --- | --- |
| 1 | Kerjakan soal selama 2 menit tanpa berpindah aplikasi. | Jawaban tersimpan; tidak ada peringatan atau kejadian. |
| 2 | Pindah ke aplikasi lain kurang dari 3 detik, lalu kembali. | Riwayat boleh mencatat durasi, tetapi jumlah kejadian dihitung tidak bertambah. |
| 3 | Pindah lebih dari 3 detik, lalu kembali. | Satu kejadian dihitung, waktu/durasi tampil di riwayat, siswa mendapat peringatan. |
| 4 | Ulangi langkah 3 sampai tiga kejadian. | Ujian ditahan; jawaban tidak hilang dan tidak bisa diubah saat ditahan. |
| 5 | Pengawas meninjau riwayat, mengisi alasan, lalu membuka akses. | Nama pengawas, waktu, dan alasan tersimpan; siswa bisa melanjutkan dengan sisa waktu sebenarnya. |
| 6 | Terima notifikasi atau panggilan saat ujian; coba juga kunci layar. | Catat apakah browser menjadi tersembunyi dan apakah muncul peringatan yang tidak layak. |
| 7 | Putuskan Wi-Fi/data ketika jawaban baru diisi, lalu sambungkan kembali. | Siswa tahu jawaban belum tersimpan; penyimpanan pulih dan tidak ada sanksi palsu. |
| 8 | Muat ulang halaman atau tutup browser secara paksa, lalu buka lagi. | Jawaban sebelumnya ada, waktu tidak kembali ke awal, riwayat tidak menghasilkan hitungan palsu. |
| 9 | Coba pada Chrome Android dan browser HP lain yang dipakai siswa. | Hasil konsisten pada perangkat sekolah, termasuk ukuran teks dan tombol. |

Lakukan tiap skenario minimal dua kali dengan jaringan sekolah. Ulangi langkah
6-8 pada satu perangkat dengan jaringan lambat. Jika jawaban hilang, siswa
tertahan tanpa kejadian yang layak, atau pengawas tidak dapat membuka akses,
tunda penerapan Mode Aman ketat sampai masalahnya diperbaiki. Jangan memakai
catatan pindah halaman sebagai satu-satunya dasar menyimpulkan kecurangan.
