# Nol toleransi dan draft jawaban mobile — 24 Oktober 2026

Perubahan lokal untuk pengerjaan siswa pada ujian terpusat dan asesmen kelas.
Meneruskan paket Mode Aman, proteksi layar, dan izin pemilih berkas sebelumnya.
Belum diterapkan ke produksi atau dibangun menjadi APK/AAB rilis baru.

## Nol toleransi mobile

- Bila deteksi pindah aplikasi aktif, setiap interval kehilangan fokus yang
  terdeteksi dihitung **satu kejadian sejak `keluar` diterima server**, termasuk
  durasi nol detik. Telepon yang mengalihkan fokus, layar mati, dan berpindah
  aplikasi tidak mendapat toleransi tiga detik.
- Transisi `inactive → hidden → paused` adalah satu interval sampai `resumed`,
  bukan tiga kejadian. Interval terbuka di server dan UUID penerimaan mencegah
  penghitungan ganda. Kejadian kembali menutup durasi, tidak menghitung lagi.
- Dua interval cepat tetap dicatat terpisah walaupun pemeriksaan proteksi atau
  sinkronisasi kembali pertama belum selesai. Penutupan interval tidak menunggu
  seluruh request jaringan sebelumnya selesai di client.
- Endpoint aktivitas keamanan menetapkan aturan ini di server. Metadata siswa
  tidak dapat meminta toleransi lebih panjang. **Kebijakan web tidak diubah**;
  nilai toleransi pada konfigurasi lama tetap dipakai untuk web.
- Respons keamanan menambah `nol_toleransi_mobile` dan `toleransi_mobile_detik`
  (0). Mobile baru menampilkan Nol toleransi. Nilai `toleransi_detik` lama
  dipertahankan untuk kompatibilitas/web.
- Batas jumlah dan tindakan tetap mengikuti ujian. Pada tindakan `tahan`, batas
  yang tercapai langsung menahan sesi. Membuka tahanan tidak menghapus hitungan,
  jawaban, atau mengulang waktu ujian. Pada tindakan `catat`, kejadian tetap
  dihitung tetapi tidak otomatis menahan hanya karena batas tercapai.
- Kejadian keluar yang baru diterima setelah offline/retry tetap dihitung sekali,
  sekaligus dapat memerlukan peninjauan teknis. UUID yang sudah diterima tidak
  dihitung ulang. Bukti terlambat saat sesi ditahan tidak membuka tahanan atau
  mengubah alasan tahanan menjadi seolah-olah batas tercapai.
- Pemulihan tanpa bukti keluar, kegagalan proteksi, serta jeda tak terjelaskan
  tetap merupakan peninjauan teknis; tidak mengarang jumlah kehilangan fokus.
  Force-stop yang terdeteksi saat pemulihan masih dapat langsung ditahan untuk
  pemeriksaan pengawas. Kejadian setelah deadline tidak menambah hitungan.

Pengecualian pemilih berkas **berizin server** dari paket sebelumnya tetap
berlaku (60 detik, maksimal tiga izin per soal sejak keputusan petugas). Alur
upload jaringan sendiri tidak mendapat pengecualian. Kode tidak membaca semua
panggilan telepon secara langsung: yang dihitung adalah hilangnya fokus/status
aktif yang dilaporkan platform. Tidak ada jaminan seluruh gangguan/force-stop
menghasilkan event OS.

## Draft aman di HP

`ExamAnswerDraftStore` memakai adapter `FlutterSecureStorage` yang sama dengan
jurnal/credential, dengan namespace terpisah. Library yang sudah terpasang
memakai cipher RSA OAEP + AES-GCM secara baku di Android. Manifest aplikasi
sudah menonaktifkan auto-backup. Tidak menambah library atau permission.

- Setiap perubahan jawaban/ragu dijadwalkan langsung ke penyimpanan terenkripsi,
  tidak menunggu debounce autosave jaringan 700 ms.
- Penulisan lokal berurutan. Label **Draft di HP** baru muncul setelah write
  lokal berhasil; ini **belum berarti tersimpan di server**. Ikon cloud selesai
  dan label Tersimpan dipakai setelah konfirmasi server serta pemrosesan ack.
- Penyimpanan dipisahkan berdasarkan server, token login, peserta, ID ujian, dan
  waktu mulai pengerjaan dari server. Draft akun/login/server lain tidak dimuat.
  Pengerjaan baru dengan identitas awal berbeda tidak memakai draft pengerjaan
  lama. Tidak menyimpan nama siswa, bank soal, kunci jawaban, atau token mentah
  dalam key. Yang disimpan hanya jawaban, ragu, tipe/ID soal, ID revisi lokal,
  serta versi jawaban server.
- Force-stop/restart dengan credential yang sama dapat memulihkan draft yang
  write-nya sudah berhasil. Saat pembukaan ulang, jurnal keamanan disinkronkan
  dahulu. Draft tidak ditampilkan/dikirim selama server menahan sesi; setelah
  pengawas membuka dan server memberikan kertas serta waktu yang sah, draft
  dapat dipulihkan dan di-autosave.
- Tidak membaca jam HP sebagai izin mengirim jawaban terlambat. Endpoint jawaban
  yang sama tetap memeriksa kepemilikan, ikatan sesi, status/tahanan, izin berkas,
  paket soal, dan deadline. Sesi selesai/deadline habis tidak dipulihkan menjadi
  pengerjaan. Draft dibersihkan setelah penyelesaian terkonfirmasi server.
- Draft soal yang sudah tidak termasuk paket/jenisnya berubah tidak dikirim.
  File jawaban yang belum selesai diunggah **tidak disimpan sebagai byte/path
  offline** dan perlu dipilih ulang; draft teks/ragu bukan antrean upload file.
- Tidak menghapus draft hanya karena request jaringan dimulai. Ack hanya
  menghapus revisi lokal yang dikonfirmasi. Ack jawaban lama tidak menghapus
  perubahan yang lebih baru selama request berlangsung.
- Logout membersihkan isi draft pada namespace credential saat itu sebelum
  credential dilepas. Namespace tersebut ditutup untuk write/ack terlambat.
  Draft tidak dijanjikan pulih setelah logout/login ulang, hapus data aplikasi,
  uninstall, ganti perangkat, atau hilangnya kunci penyimpanan OS.

### Versi server dan konflik

Kertas ujian dan hasil penyimpanan menambah `versi_jawaban`, sebuah HMAC opaque
atas identitas peserta/soal dan snapshot jawaban tersimpan. Bukan kunci soal atau
izin memperpanjang waktu. Mobile baru mengirim versi dasar tersebut pada
`PUT /api/v1/ujian-saya/{peserta}/jawaban`.

Server membandingkan versi saat lock peserta masih dipegang. Versi lama ditolak
dengan validasi `versi_jawaban`, sehingga draft tidak otomatis menimpa jawaban
server yang sudah berubah. Field bersifat opsional demi kompatibilitas API lama;
client baru mensyaratkan dukungan versi untuk membuka penyimpanan draft.

Jika respons autosave hilang tetapi isi server sudah sama dengan draft, draft
diakui selesai tanpa mengirim ulang/menimpa. Jika berbeda dan versi dasarnya
sudah berubah, soal ditutup oleh layar konflik: siswa memilih Pulihkan Draft HP
atau Pakai Jawaban Server. Pemulihan mengambil snapshot baru dahulu dan tetap
melalui validasi versi/status/deadline. Perubahan server berikutnya tetap bisa
menolak request; pilihan siswa tidak melewati pemeriksaan tersebut.

### Batas penyimpanan

Penyimpanan rusak/tidak tersedia tidak dianggap sukses atau diam-diam menjadi
draft kosong. Halaman meminta pemeriksaan ulang; perubahan yang masih ada di
memori tidak sengaja dibuang. Jangan tutup/hapus data aplikasi ketika write
belum berhasil. Panjang envelope dibatasi 2.097.152 unit string per pengerjaan; input teks
dibatasi 10.000 karakter oleh UI dan tetap divalidasi server.

Tidak bisa menjamin karakter terakhir selamat bila OS menghentikan proses
sebelum write selesai, perangkat kehabisan ruang, atau media/kunci OS rusak.
Unit/widget tests menggunakan storage palsu; ini bukan bukti fisik keberhasilan
enkripsi/ketahanan seluruh OEM. Perangkat root/custom client tetap merupakan
keterbatasan, bukan ditutup oleh penyimpanan ini.

## Penerapan

Tidak menambah migrasi/seeder, dependensi, izin Android, atau mengganti APP_KEY.
Jangan mengubah APP_KEY: seluruh instance backend harus memakai kunci yang sama.

1. Terapkan backend lengkap di luar ujian aktif, refresh cache/reload layanan
   sesuai prosedur sekolah. Backend nol toleransi juga menghitung kejadian yang
   dilaporkan AAB lama, walaupun UI lama belum menjelaskan nol toleransi.
2. Bangun aplikasi baru dan uji sebelum digunakan siswa. Aplikasi baru terhadap
   backend tanpa versi jawaban sengaja tidak membuka soal melalui gerbang draft.
3. Pastikan pengaturan deteksi aktif dan tindakan Tahan bila sekolah menghendaki
   penahanan saat batas tercapai. Perubahan ini tidak mengubah massal konfigurasi
   ujian atau data produksi.
4. Lakukan uji HP fisik dengan akun/soal dummy: telepon, layar mati, keluar kurang
   dari satu detik, siklus cepat, offline, respons hilang, force-stop, pembukaan
   pengawas, konflik versi, logout, dan deadline. Periksa hitungan sekali per
   interval, pemulihan draft, serta tidak ada tambahan waktu atau overwrite lama.
5. Setelah lulus, bangun AAB baru dengan versionCode lebih tinggi dan uji melalui
   closed testing. Tidak ada AAB baru/deployment yang dilakukan pada paket ini.

## Verifikasi lokal

- **121 pengujian backend lulus, 2.053 assertion**, memakai SQLite `:memory:`.
  Termasuk penghitungan saat keluar nol detik, UUID/interval duplikat, penahanan
  saat ambang tercapai, bukti tertunda, pembukaan petugas, versi jawaban stabil,
  penolakan overwrite versi lama, serta pemulihan yang dibatasi status/deadline.
- Regresi web, pengawas, asesmen kelas, ujian terpusat, penilaian, dan pengacakan
  menjodohkan ikut diuji. Tidak ada migrasi atau data produksi yang dijalankan.
- Pint berkas PHP yang diubah lulus; `git diff --check` bersih.
- Seluruh **839 pengujian Flutter lulus**; `flutter analyze --no-pub` tidak
  menemukan masalah. Kasus baru mencakup persistensi antar instance, isolasi
  credential/server/peserta/pengerjaan, ack lama versus draft baru, logout,
  data/storage rusak, dua siklus fokus cepat, simulasi restart saat ditahan,
  respons autosave hilang, konflik versi, dan pemulihan penyimpanan yang gagal.
- Log Flutter lokal berada di `mobile/build/test-zero-tolerance-complete.log`
  dan `mobile/build/analyze-zero-tolerance-drafts.log` (bukan source Git).
