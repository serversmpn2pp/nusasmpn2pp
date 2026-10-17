# Rencana isolasi Portal Try Out dari NUSA SMPN 2

Status: rancangan penerapan, 16 Oktober 2026. Portal dan pengamanan di bawah ini belum diimplementasikan atau diverifikasi pada server.

## Tujuan

Menyediakan try out lintas SMP dengan mode di sekolah dan di rumah, sambil mempertahankan NUSA sebagai sistem internal SMPN 2 Padang Panjang. Pengembangan portal dimulai di lingkungan terpisah dengan data fiktif.

## Temuan dari kode lokal

- Peserta CBT dan penempatan ujian terhubung ke `anggota_kelas`; alur masuk ujian menggunakan akun siswa internal. Rujukan: `app/Models/PesertaUjianCbt.php`, `app/Models/PenempatanPesertaUjianCbt.php`, dan `app/Http/Controllers/AksesUjianCbtController.php`.
- Contoh konfigurasi menggunakan database untuk sesi login, antrean pekerjaan, dan cache. Konfigurasi masing-masing juga dapat menunjuk koneksi tersendiri. Rujukan: `.env.example`, `config/session.php`, `config/queue.php`, dan `config/cache.php`.
- Jadwal otomatis NUSA mencakup pencadangan, pembinaan, persuratan, Humas, dan penyelesaian ujian kedaluwarsa. Menyalin lalu menjalankan semua jadwal tersebut tidak sesuai kebutuhan portal. Rujukan: `routes/console.php`.
- Layanan pencadangan dan pemulihan membaca konfigurasi koneksi aplikasi. Rujukan: `app/Services/Sistem/CadanganDatabaseService.php`.

Temuan ini berasal dari pembacaan berkas. Konfigurasi dan keadaan database produksi, hak akses server, serta keberhasilan cadangan aktual belum diperiksa. Tidak ada perintah aplikasi, migrasi, seeder, pencadangan, atau pemulihan database yang dijalankan dalam penyusunan dokumen ini.

## Arsitektur yang dipilih untuk rancangan

| Komponen | NUSA SMPN 2 | Portal Try Out |
| --- | --- | --- |
| Proyek aplikasi | Proyek NUSA yang sudah berjalan | Proyek dan jalur rilis tersendiri |
| Database | Database sekolah | Database portal tersendiri |
| Akun database | Akun NUSA | Akun portal dengan hak terbatas pada database portal |
| Data peserta | Data induk siswa internal | Identitas peserta dan pendaftaran per kegiatan |
| Sesi dan kunci aplikasi | Milik NUSA | Kunci baru, cookie, dan penyimpanan sesi tersendiri |
| Cache dan antrean | Milik NUSA | Penyimpanan serta worker portal tersendiri |
| Berkas dan cadangan | Lokasi NUSA | Lokasi dan kebijakan portal tersendiri |
| Tugas terjadwal | Layanan sekolah | Hanya tugas portal yang telah diperiksa |
| Lingkungan uji | Pengujian NUSA | Database dan akun uji portal dengan data fiktif |

Untuk penggunaan sekota, rekomendasi penempatan adalah server atau VM portal dengan database portal pada lingkungan tersendiri. Kapasitas tetap ditentukan melalui uji beban dan pemantauan.

Database berbeda pada server yang sama dapat memisahkan akses data jika hak aksesnya benar, tetapi masih berbagi CPU, RAM, disk, jaringan, dan kemungkinan gangguan layanan. Container pada host yang sama juga belum menghilangkan persaingan sumber daya. Subdomain berbeda atau branch Git sendiri tidak membentuk batas akses database.

## Batas akses yang harus diterapkan

1. Aplikasi portal hanya menyimpan kredensial database portal. Akun itu tidak memiliki hak membaca, menulis, mengubah struktur, atau memulihkan database NUSA.
2. Akses jaringan dari lingkungan portal ke database NUSA dibatasi. Tidak ada koneksi database tambahan, database link, atau sinkronisasi langsung ke NUSA pada penerapan awal.
3. Akun untuk perubahan struktur database dipisahkan dari akun aplikasi yang melayani peserta. Perubahan struktur hanya dijalankan melalui proses rilis portal dengan target yang telah diverifikasi.
4. Proyek baru dibuat dengan konfigurasi bersih. `.env`, kunci aplikasi, cache konfigurasi, dump database, berkas siswa, token, dan kredensial integrasi NUSA tidak disalin.
5. Cookie login dibatasi ke host portal; direktori berkas, cache, antrean, serta tujuan cadangan dan pemulihan tidak menggunakan lokasi NUSA. Worker dan scheduler mempunyai konfigurasi portal sendiri.
6. Panggilan ke layanan luar, termasuk WhatsApp, email, dan notifikasi, dinonaktifkan dalam pengembangan atau diarahkan ke penampung uji.
7. Pemeriksaan sebelum rilis memastikan identitas lingkungan dan target database portal sesuai daftar yang ditetapkan. Pemeriksaan ini melengkapi pembatasan hak database dan jaringan.

## Penggunaan kembali mesin CBT

Logika soal, penyajian, penskoran, batas waktu, dan analisis NUSA dapat diadaptasi secara bertahap ke proyek portal. Akses data siswa, pegawai, kelas, dan nilai sekolah diganti dengan model milik portal. Portal tidak membutuhkan akun eksternal dalam tabel siswa atau pegawai NUSA.

Pada tahap awal, perubahan portal dirilis hanya ke portal. Pembaruan mesin CBT yang digunakan bersama melalui peninjauan dan pengujian untuk masing-masing aplikasi, tanpa pembaruan otomatis NUSA saat portal dirilis.

Data minimum portal meliputi sekolah peserta, akun petugas, identitas peserta, kegiatan, pendaftaran peserta, jadwal, paket soal, ruang atau kelompok daring, penugasan pengawas, jawaban, hasil, dan riwayat tindakan petugas.

## Pelaksanaan ujian

- Mode sekolah: peserta ditetapkan ke sekolah pelaksana, ruang, sesi, dan meja; pengawas ruang memverifikasi kehadiran serta menangani kejadian.
- Mode rumah: peserta ditetapkan ke jadwal dan kelompok pengawasan daring dengan petugas yang bertanggung jawab; portal menyediakan check-in, pemantauan aktivitas ujian, dan permintaan bantuan.
- Mode ditetapkan petugas per pendaftaran/sesi, dengan riwayat perubahan. Peserta tidak mengubah sendiri mode ketika ujian berlangsung.
- Batas waktu mengikuti server. Pemulihan koneksi tidak otomatis menambah waktu atau membuktikan pelanggaran. Status penyimpanan jawaban harus terlihat.
- Hasil dan analisis menyimpan mode pelaksanaan, paket, dan aturan penskoran agar dapat disaring dan ditelaah dengan konteks yang tepat.

## Perpindahan data pada penerapan awal

- Sekolah mengimpor daftar peserta melalui berkas dengan format yang ditentukan portal.
- Peserta SMPN 2 dapat didaftarkan melalui mekanisme impor yang sama, menggunakan data minimum yang dipilih petugas.
- Soal yang dipilih dapat dipindahkan melalui ekspor/impor terkontrol setelah format dan hak penggunaannya diperiksa.
- Hasil try out tetap disimpan di portal. Penerapan awal tidak memiliki proses penulisan balik ke NUSA. Integrasi hasil kelak dirancang dan diuji sebagai pekerjaan tersendiri.
- Pengembangan dan pengujian menggunakan data fiktif, tanpa menyalin database produksi sekolah.

## Tahap penerapan dan bukti kelulusan

1. **Persiapan lingkungan terpisah.** Buat proyek portal dan database uji kosong. Catat lokasi proyek, identitas lingkungan, akun database, serta tempat penyimpanan. Verifikasi konfigurasi tanpa menampilkan sandi atau token.
2. **Pembatasan akses.** Periksa hak akun database dan aturan jaringan. Uji penolakan akses lintas database memakai database umpan di lingkungan uji; pemeriksaan tidak memerlukan akses ke data sekolah.
3. **Simulasi fitur.** Gunakan minimal dua sekolah fiktif untuk menguji impor peserta, akun, soal, jadwal, dua mode, pengawasan, penyimpanan jawaban, dan analisis. Uji bahwa operator sekolah A tidak dapat membaca atau mengubah peserta sekolah B melalui halaman, API, ekspor, atau berkas.
4. **Simulasi gangguan dan kapasitas.** Uji putus koneksi, jawaban tersimpan, sesi kedua, penggantian perangkat, kedaluwarsa, dan pemulihan. Uji beban dijalankan pada lingkungan portal, dengan target peserta serentak yang disepakati.
5. **Simulasi pencadangan dan penghentian.** Cadangkan database portal lalu pulihkan ke target uji portal yang berbeda. Buktikan bahwa penghentian layanan portal dan pengembalian versinya tidak membutuhkan pemulihan database NUSA. Perubahan struktur portal memerlukan prosedur pemulihan portal sendiri yang telah diuji.
6. **Uji coba terbatas.** Gunakan sejumlah kecil peserta sebelum perluasan. Pengelola meninjau hasil simulasi, kapasitas, prosedur bantuan, dan tanggung jawab petugas sebelum penggunaan sekota.

Keberadaan fitur backup dalam kode belum membuktikan cadangan terkini tersedia atau dapat dipulihkan. Keadaan cadangan NUSA perlu diverifikasi dalam pengelolaan operasionalnya sendiri; cadangan tersebut bukan pengganti isolasi portal.

## Kriteria siap digunakan

- Portal berjalan tanpa kredensial atau koneksi ke database NUSA.
- Hak akses database dan pembatasan jaringan sudah diperiksa; uji penolakan lintas database di lingkungan uji lulus.
- Perubahan skema, seeder, worker, scheduler, serta operasi backup/restore portal hanya menargetkan lingkungan portal yang ditentukan.
- Pembatasan akses antar sekolah dan kedua mode ujian lulus pengujian.
- Uji beban dan pemulihan cadangan portal mempunyai hasil yang tercatat.
- Jalur penghentian atau pemulihan portal tidak memerlukan perubahan pada layanan NUSA.

## Rujukan

[OWASP Database Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Database_Security_Cheat_Sheet.html) menganjurkan akun database per aplikasi, hak minimum, pembatasan jaringan, serta database dan akun terpisah untuk pengembangan, pengujian penerimaan, dan produksi. Rancangan di atas menerapkan prinsip tersebut pada pemisahan NUSA dan Portal Try Out.
