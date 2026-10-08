## 1. Tentang NUSA

NUSA adalah aplikasi layanan pendidikan dan administrasi **SMP Negeri 2 Padang Panjang** untuk pegawai, guru, siswa, serta orang tua/wali yang memiliki akun sekolah. Naskah ini menjelaskan pengolahan data melalui NUSA Mobile dan layanan web/API sekolah yang mendukung fitur tersebut.

Pengelola: **SMP Negeri 2 Padang Panjang**. Kontak privasi: **official@smpn2padangpanjang.sch.id**. Identitas aplikasi Android: `id.sch.smpn2padangpanjang.nusa`.

Akun disediakan atau dikelola oleh petugas sekolah. Menu dan data yang dapat diakses berbeda menurut identitas akun, hubungan orang tua dengan anak, izin, dan penugasan yang berlaku.

## 2. Data yang diproses dan sumbernya

Jenis data bergantung pada pengguna dan fitur yang digunakan. Tidak semua data berikut dikumpulkan dari setiap pengguna.

- **Identitas dan akun:** nama, nama pengguna, NIP/NIS/NISN dan nomor identitas lain yang tercatat dalam administrasi sekolah, foto, jenis kelamin, tempat/tanggal lahir, agama, alamat, kontak, data kepegawaian, data autentikasi, dan status akun.
- **Hubungan keluarga:** identitas dan kontak orang tua/wali, hubungan dengan siswa, serta data keluarga untuk layanan sekolah.
- **Akademik:** kelas, jadwal, penugasan guru, nilai, rapor, jawaban survei dan saran, perangkat ajar, jawaban/berkas ujian, hasil ujian, dan riwayat asesmen. Jawaban survei pembelajaran tersimpan terkait akun siswa; survei tidak dinyatakan anonim.
- **Kehadiran:** waktu masuk/pulang, status hadir, keterlambatan, izin, sakit, alfa, hasil pemindaian kartu, dan koreksi beserta keterangannya. Waktu kedatangan berasal dari pencatatan presensi, bukan pelacakan GPS HP.
- **Kesiswaan dan BK:** laporan kejadian, pihak terkait, bukti/lampiran, pendampingan, poin, penghargaan, sanksi, dan tindak lanjut pembinaan.
- **Ibadah:** jadwal, kehadiran, status berhalangan, periode/konfirmasi berhalangan, dan catatan privat bila dicatat. Data ini dapat mengungkap informasi agama atau kesehatan dan memerlukan perlindungan khusus.
- **Layanan sekolah:** pengajuan/peminjaman barang, agenda dan presensi pertemuan, pengaduan, tindak lanjut, dokumen, dan umpan balik Humas.
- **Teknis dan keamanan:** alamat IP, informasi aplikasi/peramban/perangkat, waktu dan hasil login, token sesi, pengenal pemasangan aplikasi/token push, status baca notifikasi, serta peristiwa keamanan ujian. SDK juga dapat memproses diagnostik sebagaimana dijelaskan pada bagian 7.

Data berasal dari pengguna, orang tua/wali, petugas sekolah yang berwenang, impor administrasi sekolah, mesin presensi, dan proses teknis aplikasi. Berkas unggahan dapat memuat nama berkas dan metadata. Hindari mengunggah informasi pribadi pihak lain yang tidak diperlukan.

## 3. Tujuan penggunaan dan dasar pemrosesan

Data digunakan untuk menyediakan dan mengamankan akun; menjalankan layanan akademik, presensi, ujian, pembinaan, ibadah, dan administrasi; memberikan akses pemantauan anak kepada orang tua/wali yang terhubung; menindaklanjuti pengaduan; mengirim pemberitahuan; serta memeriksa gangguan dan penyalahgunaan.

Penggunaan aplikasi bukan persetujuan tanpa batas untuk penggunaan data di luar tujuan tersebut. Persetujuan tambahan diperlukan apabila diwajibkan untuk pemrosesan tertentu atau tujuan baru.

**Pelaksanaan perlindungan data:** sekolah masih perlu merinci dasar pemrosesan per tujuan, prosedur persetujuan orang tua/wali yang diperlukan untuk data anak/sensitif, serta ketentuan mengenai larangan penjualan data dan penggunaan untuk pemasaran. Pada kode aplikasi yang ditinjau tidak ditemukan integrasi iklan. Penerbitan kebijakan ini tidak menggantikan persetujuan atau pelaksanaan prosedur tersebut.

## 4. Akses data dan kondisi foto saat ini

Data layanan diakses oleh pengguna yang bersangkutan, orang tua/wali yang terhubung dengan anaknya, dan petugas sekolah sesuai kewenangan serta penugasannya. Guru mata pelajaran, wali kelas, guru wali, guru BK, petugas piket, panitia ujian, dan petugas layanan tidak otomatis memiliki cakupan yang sama.

Catatan privat berhalangan ibadah dan rincian pembinaan bukan informasi umum. Hak melihat ringkasan tidak selalu mencakup hak membaca catatan privat. Penyedia teknis pada bagian 7 memproses data yang diperlukan untuk layanannya. Pengungkapan lain harus berdasarkan kewenangan, persetujuan yang diperlukan, atau kewajiban hukum yang berlaku.

**Perhatian tentang foto:** kode yang ditinjau masih menyimpan dan menyajikan foto profil/identitas melalui media publik. Jika konfigurasi tersebut digunakan di server, pihak yang mengetahui tautan foto dapat membukanya tanpa login. Pengamanan akses foto belum diterapkan pada tahap ini. Jangan membagikan tautan foto atau kartu identitas di luar keperluan yang sah. Kebijakan ini tidak menjanjikan bahwa semua foto sudah terlindungi oleh login.

## 5. Kamera, foto/berkas, dan izin notifikasi

- **Kamera** digunakan saat pemindaian QR/barcode atau pengambilan foto untuk diunggah, misalnya presensi ibadah oleh petugas, presensi pertemuan, inventaris, foto profil, dan bukti. Aliran pemindaian yang ditinjau mengirim hasil pembacaan kode ke layanan sekolah, bukan gambar kamera. Pengambilan foto untuk diunggah merupakan tindakan terpisah.
- **Foto dan berkas** dipilih pengguna melalui pemilih berkas/foto, bukan pengunggahan seluruh galeri. Materi yang dikirim disimpan untuk fitur terkait.
- **Notifikasi** memungkinkan pemberitahuan NUSA tampil di Android. Izin dapat diubah melalui pengaturan HP; pemberitahuan dalam aplikasi tetap dapat diperiksa saat tersambung ke server.

Menolak izin membatasi fitur yang membutuhkan izin tersebut. Penolakan izin notifikasi mengatur penampilan pemberitahuan dan tidak menjamin seluruh pemrosesan teknis Firebase berhenti. Pada versi yang ditinjau, NUSA tidak meminta akses lokasi GPS, kontak HP, SMS, atau mikrofon.

## 6. Keamanan ujian

Saat ujian berlangsung, NUSA dapat mencatat perubahan keadaan aplikasi, perpindahan ke latar belakang, putusnya sesi, keterlambatan sinkronisasi, indikasi tampilan multi-jendela, serta waktu/peristiwa keamanan terkait. Sebagian peristiwa disimpan di HP dan dikirim ketika jaringan tersedia.

Tujuannya menjaga kesinambungan sesi dan membantu pemeriksaan integritas ujian. Kejadian tertentu dapat memicu peringatan atau penahanan sesi sesuai aturan ujian. Gangguan perangkat, koneksi, atau aplikasi terhenti memerlukan pemeriksaan konteks oleh petugas, bukan dengan sendirinya bukti kecurangan.

Fitur ini tidak membaca nama/isi aplikasi lain, pesan pribadi, atau riwayat penjelajahan di aplikasi lain. Versi yang ditinjau tidak menggunakan rekaman suara, video pengawasan berkelanjutan, atau pengenalan wajah untuk mengawasi ujian.

## 7. Penyedia layanan teknis

**Google Firebase Cloud Messaging dan Firebase Installations** digunakan untuk push. Google memproses token/pengenal pemasangan, informasi teknis aplikasi/perangkat, serta isi pesan dan pengenal/tujuan notifikasi yang dikirim sekolah. Ini tidak berarti seluruh basis data akademik disalin ke Firebase. Lihat [penjelasan data Firebase](https://firebase.google.com/docs/android/play-data-disclosure) dan [privasi Firebase](https://firebase.google.com/support/privacy).

**Google ML Kit**, melalui komponen pemindai barcode, memproses gambar dan hasil pengenalan di perangkat, bukan mengirim gambar pemindaian ke Google. SDK dapat mengirim informasi teknis aplikasi/perangkat, pengenal pemasangan, diagnostik, kinerja, dan peristiwa pemindaian. Pembesaran otomatis juga dapat memproses pengenal sesi, perubahan zoom, dan koordinat area barcode dalam gambar; ini bukan lokasi GPS. Lihat [ketentuan ML Kit](https://developers.google.com/ml-kit/terms) dan [penjelasan data ML Kit](https://developers.google.com/ml-kit/android-data-disclosure).

**Cloudflare** menghubungkan akses ke server sekolah dan memproses data jaringan seperti alamat IP dan lalu lintas permintaan, termasuk konten yang melewati infrastrukturnya sesuai layanan yang digunakan. Lihat [kebijakan Cloudflare](https://www.cloudflare.com/privacypolicy/).

Penyedia tersebut dapat memproses data melalui infrastruktur di luar Indonesia. Penyimpanan utama pada server sekolah tidak berarti seluruh pemrosesan hanya berlangsung di sekolah atau Indonesia.

## 8. Isi dan keterlihatan notifikasi

Push rutin dapat menyampaikan informasi seperti “Anak Anda tercatat hadir tepat waktu pukul 06.54 WIB” atau pemberitahuan publikasi nilai. Push sensitif terkait BK, sanksi, dan ibadah privat menggunakan ringkasan tanpa rincian pribadi; rincian tersedia setelah masuk ke NUSA dan lolos pemeriksaan akses.

Pemberitahuan dapat terlihat di panel notifikasi atau layar kunci, bergantung pada pengaturan HP. Pengguna yang berbagi perangkat perlu mengatur pratinjau notifikasi dan keluar setelah digunakan.

Pemberitahuan otomatis kedatangan siswa menggunakan NUSA, bukan WhatsApp. Jika pengguna memilih menyalin, mengunduh, mencetak, atau membagikan laporan ke aplikasi lain, salinan itu berada di luar kontrol akses NUSA dan harus digunakan sesuai kewenangan serta perlindungan data sekolah.

## 9. Penyimpanan, keamanan, dan masa simpan

Data utama disimpan pada layanan server yang dikelola sekolah. Aplikasi produksi menggunakan HTTPS untuk komunikasi dengan API. Token login disimpan melalui penyimpanan aman perangkat; jurnal keamanan ujian lokal disimpan terenkripsi. Akses API menggunakan autentikasi dan pemeriksaan kewenangan. Tidak semua berkas memiliki perlindungan yang sama; perhatikan kondisi foto pada bagian 4.

Tidak ada sistem yang dapat dijamin bebas risiko. Naskah ini tidak menyatakan seluruh basis data/cadangan terenkripsi atau layanan menggunakan enkripsi ujung-ke-ujung.

Masa penyimpanan yang dipilih pengelola untuk **data siswa, nilai, presensi, BK, serta log keamanan adalah 5 tahun**. Awal perhitungan per kategori, cakupan foto/lampiran dan kategori lain, pengecualian arsip yang sah, serta jadwal penghapusan/anonimisasi dan pembersihan cadangan **masih perlu ditetapkan sekolah**.

Penetapan masa simpan tersebut tidak berarti penghapusan otomatis sudah tersedia. Menonaktifkan akun, keluar, membersihkan data HP, atau menghapus aplikasi tidak dengan sendirinya menghapus arsip server maupun salinan unduhan/cetakan.

## 10. Permintaan akses, koreksi, dan penghapusan

Pengguna atau orang tua/wali yang berwenang dapat mengirim pertanyaan dan permintaan privasi ke **official@smpn2padangpanjang.sch.id**, dengan subjek **“Permintaan Privasi NUSA”**. Jelaskan nama, identitas akun, hubungan dengan siswa bila relevan, permintaan, serta cara untuk dihubungi.

Jangan mengirim kata sandi, kode OTP, atau dokumen identitas lengkap dalam email awal. Verifikasi tambahan perlu dilakukan oleh petugas sekolah dengan informasi minimum yang memadai.

Permintaan dapat mencakup akses/koreksi, pembatasan pemrosesan, penarikan persetujuan untuk pemrosesan yang bergantung pada persetujuan, serta penghapusan akun/data. Bila sebagian arsip wajib dipertahankan, sekolah perlu menjelaskan kategori, dasar/tujuan, dan jangka waktunya. Masa simpan 5 tahun bukan alasan otomatis menolak setiap permintaan.

Penghapusan berbeda dari penonaktifan akun. Saat ini tidak dinyatakan ada tombol penghapusan mandiri di aplikasi. **Penetapan petugas, prosedur verifikasi, waktu tanggapan sesuai ketentuan yang berlaku, dan jalur eskalasi masih perlu ditindaklanjuti sekolah.**

## 11. Perlindungan data anak

NUSA digunakan juga oleh siswa yang belum dewasa. Pemrosesan perlu dibatasi pada kebutuhan layanan sekolah dengan perlindungan tambahan. Orang tua/wali dapat menghubungi sekolah mengenai data anak yang berada dalam kewenangannya. Hubungan akun orang tua-anak harus diverifikasi sebelum akses diberikan.

**Prosedur persetujuan orang tua/wali yang diperlukan, pemrosesan data sensitif, dan penggunaan foto anak masih perlu ditetapkan dan dilaksanakan sekolah.** Foto untuk layanan internal tidak otomatis boleh dipublikasikan secara terbuka. Penerbitan kebijakan ini bukan bukti prosedur tersebut sudah dilaksanakan.

## 12. Perubahan dan kontak

Perubahan kebijakan perlu diumumkan melalui halaman ini beserta tanggal berlakunya. Perubahan penting pada tujuan, akses, atau pemrosesan data memerlukan pemberitahuan dan persetujuan baru bila diwajibkan; tidak dianggap disetujui hanya karena pengguna membuka aplikasi.

Kontak privasi: **Pengelola NUSA — SMP Negeri 2 Padang Panjang**, **official@smpn2padangpanjang.sch.id**.

Kebijakan ini berlaku mulai **8 Oktober 2026**.
