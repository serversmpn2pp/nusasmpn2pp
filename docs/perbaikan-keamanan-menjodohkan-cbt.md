# Perbaikan kebocoran pasangan menjodohkan CBT

Tanggal: 23 Oktober 2026. Temuan: **NUSA-EXAM-02** pada
[audit ujian mobile](audit-keamanan-ujian-mobile-2026-10-23.md).

## Perubahan

`app/Services/Cbt/PengacakPenyajianCbt.php` sekarang mengurutkan bank opsi kanan
dengan HMAC-SHA-256 memakai kunci privat server melalui encrypter Laravel.
Konteksnya dipisahkan dengan domain `nusa-cbt-menjodohkan-v2`, ID ujian,
ID soal ujian, dan konteks peserta. Input opsi adalah teks yang dinormalisasi,
bukan identitas `pasangan-<nomor>` atau `pengecoh-<urutan>`.

Dengan bank opsi dan konteks yang sama, perubahan nomor/urutan pasangan kiri,
pemilik jawaban kanan, atau peran suatu opsi sebagai pengecoh tidak mengubah
urutan bank opsi. Pengurutan tidak lagi mengodekan hubungan pasangan benar.
Tidak ada kunci server, digest HMAC, atau identitas pasangan internal yang
ditambahkan ke respons siswa.

Aturan yang memaksa urutan berbeda dari urutan awal dihapus. Pada dua opsi,
aturan tersebut selalu menghasilkan pertukaran dan membocorkan hubungan
pasangan. Urutan baru boleh kebetulan sama dengan urutan penulisan; ini bukan
kegagalan pengacakan dan tidak boleh diganti dengan pertukaran paksa.

- `acak_jawaban=false`: bank opsi seragam antar peserta, tetapi tidak
  diturunkan dari nomor pasangan benar.
- `acak_jawaban=true`: pengurutan memakai konteks masing-masing peserta.
- Pengurutan stabil antar request/instance layanan selama kunci, konteks,
  pengaturan, dan isi opsi tidak berubah. Perbedaan urutan antar peserta bukan
  jaminan setiap peserta memperoleh permutasi unik.
- Kunci yang tidak tersedia tidak ditangani dengan fallback ke hash publik.
- Pengacakan nomor soal dan pilihan ganda tidak diubah dalam perbaikan ini.

Layanan yang sama digunakan oleh pengerjaan web dan API mobile untuk asesmen
kelas maupun ujian terpusat. Format respons tetap kompatibel dengan mobile
yang sudah beredar; tidak ada perubahan skema database atau dependensi baru.

## Jawaban lama, autosave, dan penilaian

Web dan mobile mengirim jawaban menjodohkan sebagai `nomor kiri => teks kanan`,
bukan huruf/posisi A/B/C. Perbaikan mempertahankan teks opsi, nomor kiri,
normalisasi duplikat, dan mekanisme koreksi yang sudah ada. Jawaban lama tidak
diubah, dihapus, atau dimigrasikan; autosave, melanjutkan ujian, dan koreksi
tetap mengacu pada teks dan kunci jawaban di server.

Huruf tampilan opsi dapat berubah sekali ketika beralih dari algoritme lama
ke algoritme baru, tetapi arti jawaban tersimpan tidak berubah. Jangan mengubah
isi soal atau kunci/pengaturan pengacakan selama ujian berjalan.

## Penerapan ke server

Perubahan ini hanya disiapkan dan diuji lokal; bukan konfirmasi sudah terpasang
di produksi.

1. Terapkan backend pada jeda ketika tidak ada ujian aktif. Respons lama yang
   sudah diterima/direkam peserta tidak dapat ditarik kembali oleh patch ini.
2. Pertahankan `APP_KEY` yang sudah digunakan. **Jangan menjalankan
   `key:generate` sebagai langkah pemasangan perbaikan ini.** Semua instance
   backend harus memakai kunci yang sama. Rotasi kunci mengubah urutan tampilan
   sehingga harus direncanakan di luar sesi ujian.
3. Pastikan seluruh instance/worker yang melayani ujian memuat kode baru sesuai
   prosedur deployment server; jangan membiarkan campuran kode lama dan baru.
4. Tidak ada migrasi/seed data yang diperlukan untuk patch ini. Tidak perlu
   membangun AAB baru khusus untuk perbaikan ini karena kontrak API tetap.
5. Uji dengan akun dan soal dummy: mulai, pilih pasangan, autosave, buka ulang,
   kumpulkan pada waktu yang diizinkan, lalu periksa skor dan media opsi.
   Coba mode seragam dan mode acak per peserta serta jalur web dan mobile.

Jika suatu paket sudah terpapar lewat respons algoritme lama, patch tidak
membuat kembali rahasia jawaban yang sudah diketahui peserta. Evaluasi paket
tersebut secara operasional untuk ujian berikutnya; jangan otomatis mengubah
nilai atau menuduh peserta curang.

## Pengujian regresi

- `tests/Unit/KeamananPilihanMenjodohkanCbtTest.php`: hasil HMAC dengan kunci
  sintetis, stabilitas lintas request, independensi dari hubungan pasangan,
  pengaruh rahasia server, dua opsi tanpa pertukaran paksa, mode pengacakan,
  duplikat/kosong, serta tidak ada fallback saat kunci hilang.
- `tests/Feature/Api/UjianSayaApiTest.php`: reproduksi serangan hash publik lama
  gagal merekonstruksi seluruh kunci fixture delapan pasangan dan satu pengecoh
  pada kedua mode. Memastikan kontrak pasangan/opsi tidak memuat identitas
  internal, jawaban format lama tetap dimuat, urutan stabil saat lanjut,
  autosave benar, serta tujuh dari delapan pasangan diberi skor 7 dari bobot 8.
- Uji serangan pada fixture tertentu bukan bukti bahwa tebakan acak mustahil
  kebetulan benar. Invarian utama adalah urutan tidak bergantung pada relasi
  pasangan benar dan pengurutan memakai rahasia server.

Sebelum patch, sembilan uji baru menghasilkan tujuh kegagalan yang membuktikan
perilaku rentan. Setelah patch, sembilan uji lulus dengan 93 assertion.

Verifikasi akhir lokal:

- Regresi PHP terarah: **99 uji lulus, 1.769 assertion**, termasuk sembilan uji
  baru di atas. Meliputi pengacakan, rincian pemetaan, Ujian Saya/Anak API,
  pengawas API, asesmen kelas API/web, pelaksanaan ujian terpusat API/web,
  serta pengerjaan dan koreksi CBT web. Database pengujian SQLite `:memory:`.
- Flutter: **19 uji lulus** dari `student_exam_view_test.dart`,
  `exam_security_journal_test.dart`, dan `exam_supervision_detail_view_test.dart`,
  termasuk tampilan menjodohkan yang mengirim teks jawaban.
- Pemeriksaan format tiga berkas PHP dengan Pint lulus; `git diff --check`
  tidak menemukan kesalahan whitespace.
- Tidak dilakukan deployment, pengujian API produksi, pengujian perangkat
  fisik, atau pembangunan AAB baru dalam pekerjaan ini.

## Batas cakupan

Patch ini menangani NUSA-EXAM-02 saja. Temuan Simpan & Keluar, metadata
retry/pemulihan, pengecualian pemilih berkas, dan kegagalan proteksi native pada
audit awal belum diperbaiki oleh perubahan ini. Ini bukan jaminan siswa tidak
dapat melakukan kecurangan melalui cara lain atau perangkat kedua.

Referensi primernya adalah [dokumentasi HMAC PHP](https://www.php.net/manual/en/function.hash-hmac.php).
