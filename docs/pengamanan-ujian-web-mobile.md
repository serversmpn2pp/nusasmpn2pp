# Pengamanan pengerjaan ujian web dan Android

## Pembaruan 24 Oktober 2026 — nol toleransi dan draft

Lihat [aturan terbaru mobile dan panduan penerapan](nol-toleransi-dan-draft-jawaban-mobile.md).
Kehilangan fokus mobile yang terdeteksi dihitung sekali saat keluar diterima,
tanpa toleransi durasi, termasuk bukti yang tertunda/retry. UUID duplikat tidak
menambah hitungan. Pemulihan ambigu tanpa bukti kehilangan fokus tetap ditinjau
tanpa mengarang insiden. Pemilih berkas berizin tetap dikecualikan. Draft jawaban
disimpan terenkripsi dan dipulihkan hanya setelah server mengizinkan, dengan
validasi versi agar draft lama tidak menimpa jawaban server secara otomatis.

## Perubahan 7 Oktober 2026

Berlaku untuk asesmen kelas dan ujian terpusat melalui alur Ujian Saya yang sama. Kontrak identitas siswa/pengguna dan pemeriksaan kepemilikan peserta tidak diubah.

- Jika `batasi_satu_perangkat` aktif, server mengikat peserta ke satu sesi autentikasi: token Sanctum untuk mobile atau identitas acak dalam sesi web. Nama perangkat tetap menjadi label, bukan credential penguncian. Membuka web saat mobile aktif, browser kedua, maupun token mobile kedua ditolak walaupun labelnya sama.
- Pengikatan pertama, pemeriksaan sesi, dan penyimpanan jawaban dijalankan dengan kunci baris peserta dalam transaksi. Sesi tidak kedaluwarsa otomatis karena kehilangan heartbeat; koneksi buruk tidak memberi perangkat lain kesempatan mengambil alih.
- Token login yang sama/browser yang sama dapat melanjutkan setelah koneksi pulih. Logout/login ulang, menghapus data aplikasi, atau berganti perangkat dapat membutuhkan reset oleh petugas. Reset tidak menghapus jawaban, tidak menambah waktu, dan tidak membuka tahanan Mode Aman. Siswa harus menutup sesi lama; perangkat berikutnya yang membuka ujian mengambil ikatan baru.
- Jalur reset/susulan/waktu tambahan yang sudah membersihkan `perangkat_terakhir` juga membersihkan ikatan sesi. Reset perangkat ruang ujian, tombol monitoring asesmen kelas, dan riwayat Mode Aman web menyimpan siapa yang melakukan reset.

## Antrean kejadian Android

Ketika deteksi pindah aplikasi aktif, kejadian disimpan dalam Flutter Secure Storage **sebelum** dikirim. Ruang penyimpanan dibedakan berdasarkan server, token login, dan peserta; nama kunci memakai SHA-256, bukan token mentah. Tidak ada nama/jawaban siswa di jurnal.

- Kejadian memiliki UUID. Server menyimpan tanda penerimaan sehingga retry setelah respons hilang tidak menghitung kejadian dua kali.
- Kejadian dikirim berurutan saat aplikasi kembali aktif/heartbeat berikutnya. Request jaringan yang tertahan tidak menghalangi penulisan kejadian baru.
- Marker sesi yang masih aktif saat aplikasi dibuka ulang menghasilkan catatan pemulihan, termasuk kemungkinan penghentian paksa. Ini tidak membuktikan aplikasi lain dipakai.
- Heartbeat hanya dikirim ketika halaman aktif di foreground, bukan saat aplikasi ditinggalkan atau pemilih berkas sedang terbuka.
- Retry, pemulihan sesi, dan jeda heartbeat yang tidak terjelaskan lebih dari dua menit dicatat **perlu ditinjau**, tanpa sanksi otomatis. Rangkaian yang tertunda ikut ditandai, termasuk kejadian kembali setelah request keluar gagal. Keluar/kembali yang diterima normal tetap mengikuti aturan Mode Aman, walaupun heartbeat tidak berjalan selama background. Jam klien hanya informasi pendukung; waktu ujian tetap dikendalikan server.
- Pemilih berkas sistem memakai izin server sementara: lihat pembaruan proteksi layar/izin berkas di bawah. Klaim pemulihan client sendiri tidak memberi pengecualian.
- Catatan peninjauan terlihat di monitoring asesmen, pelaksanaan terpusat, dan pengawas ruang; rincian tersedia dalam riwayat Mode Aman web.

## Proteksi perangkat dan batasannya

Pemblokiran screenshot tetap mengikuti pengaturan ujian. Android 12+ menyembunyikan overlay aplikasi lain selama halaman ujian aktif. Mode Aman memeriksa split-screen secara berkala; soal disembunyikan sampai layar terbagi ditutup, dan kejadian dicatat untuk ditinjau.

Fullscreen hanya tampilan, **bukan kiosk/lock-task**. Target Android API 36 memaksakan edge-to-edge. Proteksi ini tidak menjamin 100% bebas kecurangan: HP kedua, perangkat dimodifikasi/root, dan credential yang dicuri tetap membutuhkan prosedur pengawasan. Tidak ada pemantauan isi aplikasi lain atau daftar aplikasi pribadi.

## Pembaruan 23 Oktober 2026 — Mode Aman dan Simpan & Keluar

Ketentuan retry/pemulihan diperketat dalam
[paket Mode Aman dan Simpan & Keluar](perbaikan-mode-aman-dan-keluar-ujian.md).
Gangguan yang belum dapat dipastikan tidak menambah hitungan pelanggaran,
tetapi pada mode `tahan` kini memerlukan pemeriksaan pengawas sebelum lanjut.
Klaim pemulihan tidak lagi menutup interval keluar secara otomatis.

API aktivitas keamanan mobile menerima kejadian tambahan `keluar_pengerjaan`
dan metadata boolean opsional `tertunda`. `dikirim_ulang` berlaku per UUID;
UUID penerimaan server menentukan deduplikasi. Respons keamanan menambahkan
`alasan_tahanan` dan `pesan_tahanan`. Backend lama harus diperbarui sebelum
APK baru dipakai untuk alur Simpan & Keluar mode ketat.

Patch ini tidak menambah migrasi/dependensi dan belum dibangun menjadi AAB;
daftar migrasi di bagian berikut adalah kebutuhan fitur pengamanan awal,
bukan migrasi baru untuk patch 23 Oktober.

## Pembaruan 23 Oktober 2026 — proteksi layar dan izin berkas

Lihat [rincian protokol, batas, dan uji penerimaan](proteksi-layar-dan-izin-berkas-ujian.md).
Kegagalan proteksi native tidak lagi dianggap sukses. Soal tersembunyi sampai
status layar aman dapat dikonfirmasi; kegagalan saat ujian menjadi peninjauan,
bukan otomatis menambah insiden. API baru `izin-berkas` dan
`izin-berkas/selesai` memberi izin awal 60 detik, maksimal tiga per soal sejak
keputusan petugas terakhir. Soal/penulisan jawaban dibatasi sampai izin ditutup.
Backend dan APK/AAB perlu diperbarui bersama, di luar ujian aktif. Tidak ada
migrasi atau permission Android baru untuk patch ini.

## Penerapan di server dan HP

Lakukan saat tidak ada ujian aktif. Cadangkan database dan pull kode di server sekolah, lalu dari folder Laravel:

```powershell
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
```

Pastikan task worker tetap berjalan. Tidak ada data produksi yang dimigrasi otomatis oleh proses pengembangan ini. Migration menambah kolom ikatan sesi dan tabel tanda penerimaan kejadian; tidak menghapus jawaban.

Build dan pasang APK baru untuk jurnal kejadian, proteksi overlay, split-screen, serta tombol reset asesmen kelas. Ikatan server juga berlaku pada APK lama, tetapi APK lama tidak memiliki jurnal tahan gangguan. Jangan merilis sebagai sepenuhnya bebas kecurangan.

## Uji penerimaan pada HP sebelum digunakan

1. Siswa mulai dari mobile, lalu mencoba web/token login kedua: harus ditolak. Ulangi dengan web sebagai sesi pertama.
2. Putuskan jaringan, keluar/kembali, lalu pulihkan: kejadian terkirim tanpa refresh dan tidak dihitung ganda. Gangguan ambigu pada mode ketat memerlukan peninjauan pengawas tanpa penambahan insiden.
3. Hentikan paksa aplikasi, buka kembali dengan login yang sama: jawaban server tetap tersedia dan pemulihan ditandai untuk pemeriksaan.
4. Buka pemilih berkas dan batalkan/pilih berkas: tidak terkena sanksi otomatis hanya karena dialog sistem.
5. Coba split-screen dan screenshot; ulangi pada Android 12+ untuk overlay. Soal tidak tersedia dalam split-screen saat Mode Aman aktif.
6. Pengawas/guru pengelola melakukan reset, siswa melanjutkan di perangkat pengganti. Siswa/guru tak ditugaskan tidak boleh mereset; jawaban tidak hilang.
7. Uji batas waktu dan pengumpulan saat jaringan terputus. Waktu server tidak boleh diperpanjang dengan mengubah jam HP.
