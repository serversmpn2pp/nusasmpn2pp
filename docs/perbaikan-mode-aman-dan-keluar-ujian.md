# Mode Aman dan Simpan & Keluar — paket perbaikan

Tanggal: 23 Oktober 2026. Menangani NUSA-EXAM-01 dan NUSA-EXAM-03 dari
[audit anti-kecurangan](audit-keamanan-ujian-mobile-2026-10-23.md).

## Aturan setelah perbaikan

Mode ketat berarti `deteksi_pindah_tab=true` dan
`tindakan_pindah_aplikasi=tahan`. Konfigurasi paket ujian yang sudah ada tidak
diubah otomatis.

- Keluar/kembali yang diterima normal tetap dihitung menggunakan waktu server,
  toleransi, batas kejadian, dan deteksi pola keluar singkat yang sudah ada.
- UUID yang sudah diterima adalah duplikat: tidak dihitung dua kali dan tidak
  menahan ulang sesi yang sudah dibuka pengawas. Flag `dikirim_ulang` sendiri
  bukan bukti bahwa suatu UUID sudah diterima.
- Kejadian baru dengan klaim retry/tertunda, pemulihan dengan interval keluar
  yang belum selesai, atau pembukaan ulang sesi yang tidak jelas disimpan
  sebagai catatan peninjauan. Pada mode ketat, sesi ditahan untuk pemeriksaan
  pengawas, **tanpa menambah hitungan pelanggaran**. Mode `catat` tidak menahan.
- Heartbeat yang terputus lebih dari 120 detik tanpa penjelasan interval keluar
  juga diperiksa pada akses mulai/lanjut/detail, penyimpanan jawaban/berkas, dan
  pengumpulan mobile. Melewati endpoint heartbeat tidak melewati pemeriksaan.
- Interval keluar yang belum selesai menghalangi penulisan jawaban mode ketat.
  Membuka kertas atau mencoba mengumpulkan sebelum interval direkonsiliasi
  memerlukan peninjauan; heartbeat/pemulihan siswa tidak membuka tahanan.
- Klaim pemulihan tidak lagi menutup/menghapus interval keluar secara otomatis.
  Petugas yang berwenang menyelesaikan interval ambigu saat membuka tahanan;
  identitas petugas dan waktu keputusan dicatat. Jawaban dan jumlah kejadian
  yang sudah sah tetap ada. Buka Mode Aman bukan reset jawaban atau tambahan
  waktu. Deadline tetap milik server; habis waktu tetap dapat menyelesaikan ujian.

Alasan penahanan dikirim sebagai `keamanan.alasan_tahanan` dan pesan sebagai
`keamanan.pesan_tahanan`: `batas_kejadian`, `peninjauan_sesi`, atau
`keluar_pengerjaan`. Penahanan teknis bukan putusan siswa curang.

## Simpan & Keluar di mobile

1. Siswa diberi penjelasan bahwa keluar bukan mengumpulkan ujian dan waktu tetap
   berjalan. Pada mode ketat, untuk melanjutkan diperlukan pembukaan pengawas.
2. Aplikasi menyinkronkan jurnal, menyimpan jawaban, lalu mengirim kejadian
   `keluar_pengerjaan` dengan UUID tetap untuk retry.
3. Server mencatat permintaan dan menahan sesi mode ketat. Aplikasi hanya
   mengizinkan keluar setelah menerima konfirmasi tersebut. Jika simpan/jaringan
   gagal atau backend lama belum mengenal kejadian ini, halaman dipertahankan.
4. Pada mode catat, server menyimpan interval keluar, dan jurnal mengirim
   `kembali` ketika halaman dibuka lagi; bukan menggantinya dengan pengecualian
   pemulihan.

Jurnal membedakan retry per UUID dari kejadian baru yang tertunda; retry tidak
lagi menandai semua kejadian berikutnya sebagai retry. Urutan, penyimpanan
terenkripsi, serta pemisahan akun/token/server/peserta tetap dipertahankan.

Selama pengerjaan dengan pencatatan keluar aktif, router aplikasi menolak
navigasi ke halaman lain dan tidak membuka tujuan notifikasi. Notifikasi tetap
dapat dibuka lagi dari kotak masuk setelah keluar secara sah; tidak otomatis
diantrekan untuk dibuka. Dialog,
navigasi soal, dan pemilih berkas tetap dapat digunakan. Redirect autentikasi
atau penggantian kata sandi tetap diizinkan; penutupan halaman yang tidak
direncanakan menyisakan tanda pemulihan di jurnal. Router kini disegarkan saat
status autentikasi berubah, bukan dibuat ulang di tengah keputusan navigasi.

Draft jawaban yang belum terkirim dipertahankan di memori selama halaman
ditahan, lalu dicoba lagi setelah pengawas membuka. Halaman tidak ditinggalkan
jika draft tersebut belum dapat disimpan. Ini **bukan** penyimpanan draft tahan
force-stop/reboot: jawaban yang belum pernah sampai server masih bisa hilang
jika proses dibunuh. Jawaban yang sudah tersimpan di server tidak dihapus.

## Panduan pengawas

- Bedakan batas kejadian dari penahanan untuk pemeriksaan sesi atau permintaan
  Simpan & Keluar. Pesan rinci tampil pada mobile siswa dan kartu pengawas;
  API monitoring asesmen/pelaksanaan terpusat juga menyertakan rincian keamanan
  untuk peserta ditahan. Riwayat web mengenali kejadian Simpan & Keluar.
- Periksa siswa, koneksi/perangkat, waktu dan catatan kejadian. Jangan langsung
  menyamakan penahanan teknis dengan kecurangan atau memberikan nilai nol.
- Pulihkan koneksi dan biarkan jurnal tersinkron saat halaman ditahan sebelum
  membuka sesi. Bukti baru yang baru masuk setelah pembukaan tetap dapat
  memerlukan peninjauan lagi; jangan menghapus jurnal untuk menghindarinya.
- Gunakan Buka Mode Aman setelah pemeriksaan. Reset perangkat hanya bila perlu
  mengganti perangkat; reset perangkat tidak otomatis membuka tahanan.
- Pastikan siswa memeriksa bahwa draft terkirim setelah melanjutkan. Permintaan
  tambahan waktu, jika memang diperlukan, mengikuti mekanisme resmi yang sudah
  ada dan merupakan keputusan terpisah.

## Deployment dan kompatibilitas

Paket ini baru disiapkan di kode lokal, bukan deployment atau AAB baru.

1. Jadwalkan penerapan di luar ujian aktif, terutama karena mode ketat sekarang
   benar-benar menghentikan kelanjutan sesi yang perlu diperiksa.
2. Terapkan backend lebih dahulu. Tidak ada migrasi, seeder, dependensi baru,
   atau perubahan `APP_KEY` untuk paket ini. Gunakan prosedur refresh cache dan
   reload layanan PHP sekolah seperti biasa; pastikan semua instance memakai
   kode yang sama.
3. Bangun APK/AAB baru, uji di closed testing, lalu minta siswa dan pengawas
   memperbarui aplikasi sebelum ujian. AAB lama tidak memperoleh dialog,
   konfirmasi server, penjaga navigasi, atau perbaikan jurnal secara otomatis.
   Backend baru tetap menerima format lama, tetapi flag retry lama yang ambigu
   dapat memerlukan pemeriksaan pengawas.
4. Jangan menganggap backend saja menutup semua jalur Simpan & Keluar pada AAB
   lama: kembalinya aplikasi lama dalam jeda pendek tanpa kejadian keluar tetap
   merupakan keterbatasan client lama. APK baru terhadap backend lama tidak dapat
   memperoleh konfirmasi keluar mode ketat; ini sengaja gagal tertutup.

Uji penerimaan dengan data dummy pada HP fisik: keluar/kembali normal hingga
batas, klik Simpan & Keluar, jaringan putus/pulih, respons server hilang lalu
retry, force-stop dan buka ulang, notifikasi saat ujian, pembukaan oleh pengawas,
draft belum tersimpan, serta deadline. Pastikan skor/waktu tidak berubah karena
pembukaan tahanan, dan petugas yang tidak berwenang tidak dapat membuka.

## Batas cakupan

Paket lanjutan [nol toleransi dan draft jawaban mobile](nol-toleransi-dan-draft-jawaban-mobile.md)
memperbarui aturan kejadian keluar dan menambahkan draft terenkripsi. Pernyataan
tanpa hitungan pada paket awal tidak berlaku untuk bukti kehilangan fokus mobile
yang baru diterima: bukti tersebut sekarang dihitung sekali, termasuk saat retry.

Tidak mengubah penilaian, bank soal, atau perbaikan HMAC menjodohkan sebelumnya.
Tidak membuat app attestation/Play Integrity, kiosk perangkat, deteksi semua
keyboard/accessibility, atau pengawasan perangkat kedua. Pengecualian pemilih
berkas dan kegagalan proteksi native ditangani pada paket lanjutan
[proteksi layar dan izin berkas](proteksi-layar-dan-izin-berkas-ujian.md).
Ketentuan dan hasil pengujian di dokumen ini mencatat paket Mode Aman awal;
paket lanjutan memperketat picker dengan izin server, bukan klaim pemulihan client.

Tidak ada pengujian serangan ke API produksi atau pengujian HP fisik dalam
pekerjaan ini. Pengujian otomatis lokal tidak menjamin seluruh OEM/perangkat
atau jalur native Navigator yang mungkin ditambahkan di masa depan.

## Verifikasi lokal

- **109 pengujian backend lulus, 1.875 assertion** dengan SQLite `:memory:`.
  Cakupannya pengerjaan/penilaian web dan mobile, pengawas, asesmen kelas,
  pelaksanaan ujian terpusat, serta regresi pengacakan menjodohkan sebelumnya.
  Kasus baru meliputi UUID duplikat versus klaim retry baru, pemulihan palsu,
  penahanan yang tidak rollback saat jawaban ditolak, akses langsung tanpa
  heartbeat, interval keluar belum selesai, pembukaan oleh petugas, mode catat,
  deadline, dan deteksi tanpa ikatan satu perangkat.
- **Seluruh 809 pengujian Flutter lulus**, termasuk router aplikasi sebenarnya,
  startup/login/menu, go/push selama ujian, offline saat Simpan & Keluar,
  backend yang tidak mengonfirmasi tahan, draft saat dibuka kembali,
  pemisahan retry dari tertunda, serta jurnal kembali setelah keluar terencana.
- `flutter analyze --no-pub`: **tidak ada temuan**. Pint delapan berkas PHP
  yang diubah lulus; `git diff --check` bersih.
- Log Flutter lokal: `mobile/build/test-security-mode-aman.log` dan
  `mobile/build/analyze-security-mode-aman.log` (artefak build, bukan source).
- Tidak membangun APK/AAB, mengunggah ke Play Console, melakukan deployment,
  menjalankan migrasi produksi, atau mengubah konfigurasi ujian sekolah.
