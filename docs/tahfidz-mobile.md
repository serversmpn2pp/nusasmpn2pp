# Tahfidz native mobile

Menu berada di **Akademik → Penilaian** untuk pegawai yang berwenang. Siswa mendapat **Akademik → Penilaian → Tahfidz Saya**, sedangkan orang tua mendapat **Akademik → Pemantauan Anak → Tahfidz Anak Saya**. Menu dapat dipilih sebagai Akses Cepat sesuai kewenangan akun pegawai.

## Alur

- Pembimbing melihat kelompok yang ditugaskan kepadanya, membuka pertemuan, memilih siswa/surah, mencatat nilai 0–100 dan catatan, melihat riwayat/perkembangan, serta menyelesaikan pertemuan. Rangkuman dan perkembangan orang tua tersedia setelah pertemuan selesai.
- Setiap formulir setoran memiliki UUID yang dipertahankan saat mencoba simpan ulang setelah kegagalan jaringan. Layanan yang sama dengan web mencegah percobaan ganda dan menolak setoran baru setelah pertemuan selesai.
- Koordinator/administrator yang memiliki kewenangan Tahfidz mengelola kelompok dan anggota lintas kelas, target rapor per tingkat, publikasi/penarikan rapor, acara wisuda, serta penerbitan/publikasi/penarikan sertifikat.
- Perubahan kelompok/anggota memakai versi server. Daftar anggota yang disimpan selalu lengkap, termasuk pilihan yang tidak sedang terlihat karena pencarian. Siswa yang sudah berada dalam kelompok aktif lain tidak dapat dimasukkan diam-diam.
- Pembimbing memverifikasi kelulusan setelah setoran seluruh 37 surah Juz 30 terpenuhi. Penerbitan sertifikat dan pembagiannya ke orang tua merupakan tindakan terpisah.
- Rapor dapat dipratinjau pada mobile. **Pencetakan rapor, baik per siswa maupun kelompok, khusus versi web**; tombol cetaknya tidak ditampilkan pada mobile. Sertifikat tersedia sebagai dokumen native/PDF.
- Tombol Buka Pertemuan dan Daftar Surah memakai tombol besar selebar area isi, gradasi biru dan aksen kuning NUSA, ikon, serta keterangan singkat.
- Daftar Surah memuat teks Arab berharakat untuk seluruh 37 surah Juz 30 (564 ayat). Pembaca tersedia tanpa permintaan jaringan, dengan nomor ayat, lompat ke ayat, dan pengaturan ukuran huruf. Surah terpilih juga bisa dibuka dari formulir setoran tanpa membuang isian formulir.
- Orang tua hanya melihat anak yang terhubung dengan akunnya. Dokumen hanya tersedia setelah dipublikasikan dan diperiksa ulang lewat API saat dibuka. Pergantian anak mengabaikan respons lama yang terlambat. Catatan internal pembimbing tidak dikirim dalam perkembangan orang tua.

Siswa dapat membaca progres dan riwayat setoran dirinya yang pertemuannya sudah diselesaikan, serta membuka pembaca surah Arab. Identitas siswa selalu berasal dari akun login; pengiriman ID siswa/anggota pengganti ditolak. Tampilan siswa tidak memiliki pilihan anak, form input nilai, catatan internal pembimbing, atau akses dokumen yang dipublikasikan khusus orang tua. Hak koordinator tidak memberi hak mencatat/verifikasi sebagai pembimbing kelompok lain. API memeriksa identitas dan penugasan pada setiap permintaan, termasuk jika URL dipanggil langsung.

## API

Seluruh endpoint berada dalam `/api/v1`, memerlukan bearer token dengan kemampuan `mobile`, akun aktif, dan kata sandi nondefault:

- `GET /tahfidz` — kelompok sesuai penugasan, filter tahun/status/pencarian dan paginasi.
- `GET /tahfidz/referensi`, `POST /tahfidz`, `GET|PUT /tahfidz/{kelompok}`.
- `GET|PUT /tahfidz/{kelompok}/anggota` — pilihan dan simpan anggota lengkap dengan versi.
- `POST /tahfidz/{kelompok}/pertemuan`, `GET /tahfidz/{kelompok}/pertemuan/{pertemuan}`.
- `POST /tahfidz/{kelompok}/pertemuan/{pertemuan}/setoran|selesaikan`.
- `GET /tahfidz/{kelompok}/anggota/{anggota}` — perkembangan, riwayat, pratinjau rapor dan kelulusan.
- `GET /tahfidz/{kelompok}/rapor` — seluruh dokumen rapor kelompok; tidak memublikasikan dokumen.
- `POST /tahfidz/{kelompok}/anggota/{anggota}/rapor/publikasi|verifikasi`.
- `GET|POST /tahfidz/target-rapor`, `GET|POST /tahfidz/wisuda`, `PUT /tahfidz/wisuda/{wisuda}`.
- `POST /tahfidz/wisuda/kelulusan/{kelulusan}/terbitkan|publikasi`.
- `GET /tahfidz-anak?siswa={id}`, `GET /tahfidz-anak/rapor/{id}`, `GET /tahfidz-anak/sertifikat/{id}`.
- `GET /tahfidz-saya?riwayat={halaman}` — progres dan riwayat milik siswa yang login; tanpa parameter pemilihan identitas.

Operasi tulis menggunakan validasi dan layanan web yang sama. Respons API berupa JSON dan diberi `Cache-Control: private, no-store`. Publikasi rapor memerlukan sidik SHA-256 hasil pratinjau server sehingga dokumen yang sudah berubah harus diperiksa lagi. Push Tahfidz diarahkan ke layar native dengan anak yang sesuai.

## Notifikasi siswa dan orang tua/wali

- **Pertemuan selesai:** satu ringkasan per siswa yang menyetor, ke akun siswa aktif dan seluruh akun orang tua/wali aktif yang terhubung. Menyimpan setoran saja belum mengirim notifikasi.
- **Rapor dipublikasikan / sertifikat dibagikan:** orang tua mendapat kabar dokumen tersedia; siswa mendapat kabar dokumen telah dibagikan kepada orang tua/wali. Dokumen tetap mengikuti izin publikasi yang sudah ada; pemberitahuan tidak memberi siswa akses ke endpoint dokumen orang tua.
- Notifikasi tersimpan dalam kotak notifikasi NUSA. Jika `FIREBASE_PUSH_ENABLED=true`, proyek/kredensial Firebase dan queue worker server sudah dikonfigurasi, notifikasi baru mengantrekan push **setelah transaksi berhasil**. Tidak ada pengiriman ketika transaksi dibatalkan. Tidak memerlukan migrasi baru.
- Push memakai judul dan pesan khusus peristiwa tanpa nama, nilai, atau catatan internal pada layar kunci. Saat aplikasi terbuka, alur foreground NUSA menampilkan pemberitahuan dengan tombol **Buka**; saat di latar belakang, FCM menggunakan channel Android `nusa_notifications`.
- Ketukan siswa membuka **Tahfidz Saya**; ketukan orang tua membuka **Tahfidz Anak Saya** dengan anak yang benar. API memeriksa kembali pemilik notifikasi sebelum menampilkan detail dan menandai baca. Tautan web siswa membuka kotak notifikasi, sehingga tidak mengarah ke halaman web yang belum tersedia.
- Pemrosesan ulang pertemuan/publikasi yang sama tidak menggandakan inbox atau push. Membagikan versi rapor baru atau membagikan ulang setelah ditarik merupakan publikasi baru. Pertemuan lama yang sudah selesai tidak dikirim ulang otomatis.

Verifikasi pada perangkat setelah deploy: login pada akun siswa dan wali yang terhubung, izinkan notifikasi Android, catat setoran pada pertemuan uji lalu selesaikan pertemuannya. Periksa inbox dan push kedua akun serta halaman tujuan. Ulangi tindakan penyelesaian dan pastikan tidak ada notifikasi tambahan. Pengujian otomatis memakai antrean/HTTP Firebase tiruan, bukan mengirim pesan ke pengguna sungguhan.

## Pemasangan

Deploy backend/API dan bersihkan cache (`php artisan optimize:clear`) sebelum memakai build mobile baru. Jalankan migrasi standar jika modul Tahfidz web belum dipasang pada server. Jika memakai queue worker untuk push, jalankan `php artisan queue:restart` setelah deploy. Tidak ada dependensi atau migrasi tambahan khusus integrasi mobile ini.

Build Android/AAB baru diperlukan untuk menampilkan layar native ini pada aplikasi yang telah terpasang. Versi aplikasi dan berkas AAB belum dinaikkan/dibangun oleh perubahan modul ini.

## Teks Arab dan font

Teks memakai Tanzil Uthmani 1.1 dari https://tanzil.net/, disertakan utuh per ayat dalam `mobile/assets/quran/juz30.json` tanpa normalisasi atau perubahan harakat. Basmalah pada awal ayat pertama ditata sebagai pembuka tanpa nomor; rangkaian karakter dari sumber tetap dipertahankan, termasuk variasi harakat pembuka surah 95 dan 97. Metadata nomor/nama/jumlah ayat memakai metadata resmi Tanzil.

Font Amiri Quran diambil dari repositori Google Fonts, berlisensi SIL Open Font License. Sumber dan lisensi dapat dibuka di halaman daftar/pembaca surah. Teks dan font dibundel dalam aplikasi agar tidak bergantung pada API Al-Qur’an eksternal saat dipakai.

Skrip `scripts/import-tahfidz-quran.py` memeriksa 6.236 ayat sumber, mengekstrak surah 78–114, memverifikasi 37 surah/564 ayat, dan membandingkan seluruh ayat hasil JSON dengan byte UTF-8 ayat sumber. Sidik berkas sumber dicantumkan di metadata JSON. Sumber, lisensi, dan langkah regenerasi ada di `mobile/assets/quran/README.md`.
