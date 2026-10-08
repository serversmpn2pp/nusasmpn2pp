# Catatan internal penyusunan kebijakan privasi NUSA

Tanggal: 8 Oktober 2026. Dokumen internal pendamping
[draf kebijakan](D:/nusasmpn2pp/docs/kebijakan-privasi-nusa-draf.md), bukan teks untuk ditampilkan
sebagai kebijakan resmi pengguna.

Pembaruan status 8 Oktober 2026: pengguna menyetujui naskah publik dan meminta
label draf dihapus. Halaman kini menampilkan tanggal berlaku 8 Oktober 2026;
dokumen editorial tetap diarsipkan. Persetujuan ini tidak mengimplementasikan
pengamanan foto, retensi/penghapusan, atau prosedur perlindungan data anak.
Catatan penyusunan dan tindak lanjut teknis di bawah tetap menjadi riwayat
dan daftar pekerjaan, bukan klaim seluruh tindakan telah selesai.

## Cakupan dan keputusan yang sudah diterima

- Pengelola layanan: SMP Negeri 2 Padang Panjang.
- Email privasi dari pengguna: **official@smpn2padangpanjang.sch.id**.
- Masa simpan yang dipilih pengguna untuk data siswa, nilai, presensi, BK,
  dan log keamanan: **5 tahun**. Awal hitung dan pelaksanaannya belum
  ditetapkan; jangan menyebut penghapusan otomatis sudah berjalan.
- Draf berdasarkan pembacaan kode Flutter/Laravel serta informasi server
  sekolah yang menggunakan Cloudflare Tunnel. Tidak membuka database siswa,
  kredensial, Firebase Console, atau konfigurasi internal server produksi.
- Tahap ini hanya membuat dua dokumen. Tidak menerbitkan halaman publik,
  mengubah kode aplikasi/API/database, atau mengisi deklarasi Play Console.

## Fakta implementasi yang memengaruhi isi kebijakan

| Area | Bukti kode | Implikasi kebijakan |
| --- | --- | --- |
| Identitas siswa/pegawai | [Siswa](D:/nusasmpn2pp/app/Models/Siswa.php), [Pegawai](D:/nusasmpn2pp/app/Models/Pegawai.php), [OrangTuaWali](D:/nusasmpn2pp/app/Models/OrangTuaWali.php), [Pengguna](D:/nusasmpn2pp/app/Models/Pengguna.php) | Ada identitas/kontak/keluarga/agama dan relasi akun-anak; bukan aplikasi yang tidak mengumpulkan data pribadi. |
| Login | [AutentikasiController API](D:/nusasmpn2pp/app/Http/Controllers/Api/V1/AutentikasiController.php), [RiwayatLogin](D:/nusasmpn2pp/app/Models/RiwayatLogin.php) | Mencatat IP, user agent, waktu, identitas dan hasil login; jangan menyatakan hanya nama yang diproses. |
| Sandi | [Pengguna](D:/nusasmpn2pp/app/Models/Pengguna.php) | Sandi utama di-hash, tetapi sandi awal dikelola terenkripsi sampai diganti; jangan menjanjikan seluruh data sandi hanya berupa hash. |
| Survei | [SurveiPembelajaran](D:/nusasmpn2pp/app/Models/SurveiPembelajaran.php) | Relasi siswa disimpan. Jangan menyebut survei anonim hanya karena tampilan guru berbentuk agregat. |
| Berhalangan ibadah | [PeriodeBerhalanganIbadah](D:/nusasmpn2pp/app/Models/PeriodeBerhalanganIbadah.php), [PresensiBerhalanganIbadah](D:/nusasmpn2pp/app/Models/PresensiBerhalanganIbadah.php) | Ada periode, konfirmasi, catatan privat, dan metadata scan; dapat mengungkap agama/kesehatan. |
| Berkas privat | [Bukti pembinaan](D:/nusasmpn2pp/app/Services/Pembinaan/SimpanBuktiLaporanService.php), [lampiran Humas](D:/nusasmpn2pp/app/Services/Humas/LampiranPengaduanHumasService.php), [berkas jawaban ujian](D:/nusasmpn2pp/app/Services/Cbt/JawabanBerkasUjianCbtService.php) | Lampiran fitur ini memakai penyimpanan privat; jangan menyamaratakannya dengan foto profil. |
| Foto profil/identitas | [FotoProfilService](D:/nusasmpn2pp/app/Services/FotoProfilService.php), [FotoIdentitasMobileService](D:/nusasmpn2pp/app/Services/Mobile/FotoIdentitasMobileService.php), [filesystems](D:/nusasmpn2pp/config/filesystems.php) | Foto disimpan pada disk `public`, URL menggunakan jalur media publik. Perlu tindak lanjut sebelum publikasi, lihat bagian berikut. |
| Izin Android | [AndroidManifest](D:/nusasmpn2pp/mobile/android/app/src/main/AndroidManifest.xml) | Kamera, notifikasi, jaringan dan perlindungan overlay. Tidak ditemukan permintaan GPS, kontak, SMS, atau mikrofon dalam manifest aplikasi. |
| Push | [FCM service](D:/nusasmpn2pp/app/Services/Notifikasi/FirebaseCloudMessagingService.php), [isi push](D:/nusasmpn2pp/app/Services/Notifikasi/IsiPushNotifikasiService.php), [push device](D:/nusasmpn2pp/mobile/lib/features/push_notifications/application/push_device_service.dart) | Token, isi pesan dan ID/tujuan dikirim ke FCM; isi rutin jelas, isi sensitif diringkas. Penolakan izin tampilan tidak menjamin seluruh aktivitas SDK berhenti. |
| Kedatangan siswa | [NotifikasiAbsensiSiswaService](D:/nusasmpn2pp/app/Services/Notifikasi/NotifikasiAbsensiSiswaService.php) | Jalur otomatis yang ditinjau menggunakan notifikasi NUSA. Konfigurasi WhatsApp lama bukan bukti masih digunakan. Berbagi/salin manual tetap perlu kewenangan. |
| Data lokal | [Token storage](D:/nusasmpn2pp/mobile/lib/core/storage/token_storage.dart), [device identity](D:/nusasmpn2pp/mobile/lib/core/storage/device_identity.dart), [exam security journal](D:/nusasmpn2pp/mobile/lib/features/student_exam/data/exam_security_journal.dart) | Token aman, label acak bukan IMEI, jurnal ujian terenkripsi; belum membuktikan semua cache/jurnal terhapus otomatis setelah logout/ujian. |
| SDK | [pubspec](D:/nusasmpn2pp/mobile/pubspec.yaml), implementasi Android `mobile_scanner` yang terpasang | Firebase Messaging dan ML Kit barcode. Tidak ditemukan integrasi Firebase Analytics, Crashlytics, atau iklan dalam kode/dependensi langsung yang ditinjau; ML Kit tetap dapat mengirim diagnostik teknis. |
| Retensi | [Logging](D:/nusasmpn2pp/config/logging.php), [cadangan database](D:/nusasmpn2pp/config/cadangan_database.php), [console](D:/nusasmpn2pp/routes/console.php) | Konfigurasi retensi tertentu bukan kebijakan 5 tahun semua data. Tidak ditemukan mekanisme umum penghapusan seluruh kategori setelah 5 tahun. Nilai konfigurasi bukan bukti setelan efektif server. |

Pembacaan kode bukan audit akses produksi menyeluruh. Contohnya, perlindungan
media tambahan di proxy/server belum diperiksa, dan perubahan konfigurasi SDK
di Console dapat mengubah data yang diproses.

## Keputusan/tindakan sebelum draf disahkan

### 1. Lindungi foto profil dan foto identitas

**Prioritas sebelum rilis publik:** kode menyimpan foto di disk publik dan
menyajikan URL tanpa pemeriksaan izin khusus pada pengambilan medianya.
URL sulit ditebak bukan autentikasi. Ini potensi paparan foto, terutama anak;
bukan bukti foto siswa tertentu telah diakses orang luar.

Rekomendasi: sajikan foto melalui media privat dengan pemeriksaan identitas/
kepemilikan/izin dan kebijakan cache yang sesuai. Periksa pula URL lama,
salinan, cache proxy, serta foto yang tercetak pada kartu. Jangan sekadar
menyembunyikan menu atau menambahkan kalimat persetujuan untuk menutupi
perlindungan yang belum memadai. Tindak lanjut kode memerlukan pekerjaan
terpisah; belum dilaksanakan dalam penyusunan draf ini.

Setelah perbaikan, uji akses tanpa login, akun lain, orang tua-anak yang tidak
terhubung, dan akun nonaktif; perbarui paragraf foto di kebijakan berdasarkan
hasilnya. Jangan mempublikasikan janji “semua foto hanya terlihat setelah
login” sebelum perilaku server diverifikasi.

### 2. Operasionalkan masa simpan 5 tahun

Jangan menambahkan 5 tahun ke konfigurasi acak dan menganggap retensi selesai.
Sekolah perlu menyetujui jadwal per kategori:

| Kategori yang sudah dipilih | Masa simpan | Keputusan yang masih diperlukan |
| --- | --- | --- |
| Data siswa | 5 tahun | Awal hitung dan bagian arsip identitas/pendidikan yang wajib dipertahankan |
| Nilai/rapor/ujian | 5 tahun untuk nilai; cakupan jawaban/berkas ujian perlu pengesahan | Awal hitung tahun ajaran/publikasi dan aturan arsip rapor |
| Presensi | 5 tahun | Awal hitung per catatan/tahun ajaran; cakupan siswa, pegawai, ibadah dan pertemuan |
| BK | 5 tahun | Awal hitung kasus ditutup/akhir penugasan, bukti, catatan privat dan alasan retensi khusus |
| Log keamanan | 5 tahun | Jenis log yang diperlukan, awal hitung per peristiwa, akses terbatas, proporsionalitas retensi |

Usulan awal hitung di tabel **belum keputusan sekolah**. Konfirmasikan masa
simpan untuk akun/identitas pegawai dan orang tua, survei, Humas, peminjaman,
foto/lampiran, notifikasi, token perangkat, cache dan jurnal lokal. Data yang
lebih singkat kegunaannya tidak otomatis harus disimpan 5 tahun.

Tetapkan tindakan akhir: hapus atau anonimisasi yang tidak dapat dipulihkan
menjadi identitas. Tetapkan cakupan cadangan, siklus pembersihan, salinan yang
diekspor, penanganan pemulihan backup, pengecualian arsip yang sah, dan bukti
pelaksanaan. Tidak ada perubahan/penghapusan database yang dilakukan sekarang.

### 3. Tetapkan penanggung jawab dan layanan permintaan

- Pastikan email resmi dipantau; tentukan petugas, alur verifikasi minimum,
  waktu tanggapan dan eskalasi sesuai ketentuan yang berlaku.
- Tentukan tata cara akses, koreksi, penarikan persetujuan, penghapusan
  akun/data, serta penjelasan arsip yang memang masih wajib disimpan.
- Jangan meminta kata sandi/OTP atau salinan identitas lengkap melalui email
  awal. Menonaktifkan akun bukan penghapusan data.
- Saat ditinjau, akun disediakan sekolah dan tidak ditemukan pendaftaran
  mandiri pengguna. Pastikan jawaban Play Console mengikuti alur nyata;
  jangan menganggap semua aplikasi sekolah bebas ketentuan penghapusan.
  Jika aplikasi menawarkan pembuatan akun yang masuk cakupan persyaratan
  Google, siapkan sarana permintaan di dalam aplikasi dan di luar aplikasi.

### 4. Sahkan pemrosesan data anak dan data sensitif

Sekolah perlu meninjau dasar pemrosesan, kebutuhan/minimisasi data, prosedur
persetujuan orang tua/wali yang diperlukan, data kesehatan/agama dan BK,
serta penggunaan foto anak. Kebijakan privasi tidak dengan sendirinya
memperoleh persetujuan. Tetapkan pula penanganan insiden dan pemberitahuan
sesuai kewajiban yang berlaku; jangan menjanjikan prosedur yang tidak dijalankan.

Konfirmasikan komitmen tidak menjual data/tidak memakai iklan komersial,
nama pengelola yang cocok dengan listing, tanggal berlaku, serta akses
penyedia/petugas server dan penyimpanan cadangan. Draf bukan pendapat hukum
atau jaminan kepatuhan; pengesahan perlu peninjauan institusi yang berwenang.

### 5. Cocokkan SDK, pengungkapan, dan Data Safety

FCM bergantung pada Firebase Installations; periksa pengenal pemasangan,
token, informasi teknis, isi pesan, serta pengenal/tujuan yang dikirim NUSA.
Jangan menyatakan seluruh push anonim atau hanya berupa pesan generik.
Periksa konfigurasi tambahan di Firebase Console; integrasi BigQuery/Analytics
tidak boleh dianggap aktif maupun nonaktif hanya dari sebagian kode.

ML Kit memproses gambar pemindaian di perangkat, tetapi dapat mengirim
diagnostik. Auto-zoom yang dipakai pemindai memiliki data teknis sesi/zoom/
area barcode. Jangan mengisi deklarasi seolah kamera berarti seluruh video
diunggah, atau sebaliknya seolah SDK tidak mengirim data apa pun.

Cloudflare memproses lalu lintas layanan sekolah; jangan menjanjikan seluruh
pemrosesan berada di Indonesia atau tidak melibatkan pihak ketiga.

Form Data Safety harus mengikuti definisi Google untuk data dikumpulkan,
dibagikan, tujuan, keharusan/opsional, dan pengecualian penyedia layanan.
Daftar pihak pemroses pada kebijakan tidak otomatis berarti setiap kategori
harus dicentang “dibagikan” dalam formulir. Periksa umur pengguna sesungguhnya
dan ketentuan anak/Families yang relevan, bukan memilih 18+ untuk melewati audit.

### 6. Publikasi setelah isi dan implementasi cocok

Setelah semua penanda draf selesai dan sekolah mengesahkan isinya:

1. Terbitkan halaman HTML publik yang aktif, tanpa login, bukan PDF, dan
   dapat diakses reviewer/pengguna. Jalur `/kebijakan-privasi` dan tautan
   aplikasi sudah diimplementasikan lokal pada tindak lanjut 8 Oktober 2026,
   masih menampilkan status draf; deployment/akses produksi belum diverifikasi.
2. Tambahkan tautan atau teks kebijakan dalam aplikasi; pertimbangkan akses
   sebelum login dan dari profil/pengaturan. SDK/pemrosesan yang memerlukan
   pengungkapan menonjol atau persetujuan perlu alur tersendiri sebelum
   pemrosesan, bukan hanya tautan di footer.
3. Isi URL kebijakan dan Data Safety di Play Console dengan isi yang konsisten.
   Jika ada kebutuhan perubahan kode Flutter, bangun AAB baru setelah perubahan.
4. Uji URL, media, permintaan privasi, akses antar-akun, izin kamera/notifikasi,
   data anak dan push rutin/sensitif pada artefak release serta server produksi.

Dokumen ini belum menutup temuan R02 pada audit rilis: akses halaman publik
dan tautan dalam aplikasi sudah diimplementasikan lokal, tetapi pengesahan
naskah, deployment, AAB baru dan verifikasi produksi belum selesai. Lihat
[rincian implementasi akses](D:/nusasmpn2pp/docs/halaman-kebijakan-privasi-nusa.md).
Tidak ada pernyataan aplikasi sudah lolos kebijakan atau pasti diterima Google Play.

## Rujukan resmi

Diakses 8 Oktober 2026:

- [Google Play: User Data, Privacy Policy dan Account Deletion](https://support.google.com/googleplay/android-developer/answer/10144311?hl=en): isi kebijakan harus sesuai pemrosesan nyata, tersedia di aplikasi/Play Console, dan memuat retensi serta penghapusan.
- [Google Play: Account Deletion FAQ](https://support.google.com/googleplay/android-developer/answer/13327111?hl=en): tinjau cakupan alur pembuatan akun sebelum menentukan sarana penghapusan.
- [Firebase: Android data disclosure](https://firebase.google.com/docs/android/play-data-disclosure) dan [privasi Firebase](https://firebase.google.com/support/privacy): periksa FCM, Installations dan konfigurasi tambahan SDK.
- [ML Kit: ketentuan](https://developers.google.com/ml-kit/terms) dan [Android data disclosure](https://developers.google.com/ml-kit/android-data-disclosure): bedakan pemrosesan gambar lokal dari diagnostik SDK.
- [Cloudflare: Privacy Policy](https://www.cloudflare.com/privacypolicy/): pemrosesan data pengguna akhir/lalu lintas melalui penyedia jaringan.
