# Permintaan Penghapusan Akun/Data NUSA

## Ruang lingkup dan status

Implementasi ini menyediakan halaman publik, kanal permintaan melalui email resmi, dan tautan dari login/profil NUSA Mobile serta login web dan kebijakan privasi. **Tidak ada endpoint penghapusan, pengiriman email otomatis, migrasi, perubahan akun, atau penghapusan nilai/arsip.**

| Kebutuhan | Lokasi |
| --- | --- |
| URL yang disiapkan untuk Delete account URL Play Console | `https://nusa.smpn2padangpanjang.sch.id/penghapusan-akun` |
| Route lokal | `GET /penghapusan-akun` (juga HEAD) |
| Email penerima | `official@smpn2padangpanjang.sch.id` |
| Subjek | `Permintaan Penghapusan Akun/Data NUSA` |
| Naskah publik | `resources/legal/permintaan-penghapusan-nusa.md` |
| Akses aplikasi | Login dan Profil → Permintaan Penghapusan Akun/Data |

Halaman statis tidak membaca sesi atau basis data pengguna, tidak memantulkan query URL, tidak memuat skrip/formulir/tracker pihak ketiga, dan tidak membuat cookie. Email ditampilkan sebagai teks dan tautan mailto; tombol memakai contoh pesan kosong, **bukan identitas pengguna yang sedang login**. `Cache-Control: no-store, no-transform` dipakai pada halaman ini untuk mencegah penyamaran email Cloudflare tanpa mengizinkan JavaScript. Tautan mobile memakai origin/prefix instalasi API dengan query, fragment, dan kredensial dibuang.

Dokumen ini adalah **rancangan panduan operasional yang harus disahkan dan dijalankan sekolah**, bukan bukti bahwa layanan penghapusan sudah beroperasi. Naskah retensi 5 tahun belum diubah menjadi permanen. Jangan menyatakan layanan selesai atau memilih deklarasi Play Console hanya karena URL telah dibuat.

## Gerbang sebelum menggunakan tautan di Play Console

1. Kepala sekolah menunjuk petugas privasi/penerima email, pengganti ketika tidak hadir, dan pejabat peninjau keberatan. Tetapkan akses kotak surat yang terbatas dan pastikan permintaan benar-benar dipantau.
2. Sekolah menyepakati prosedur verifikasi identitas, batas waktu respons/pelaksanaan sesuai ketentuan hukum yang berlaku, dan jalur eskalasi. **Dokumen ini tidak menetapkan tenggat hukum atau janji layanan baru.** Setelah diputuskan, selaraskan informasi waktu pada halaman publik.
3. Bersama pengelola arsip/Dinas Pendidikan, petakan data NUSA ke jadwal retensi yang berlaku, termasuk arsip permanen, awal hitung retensi, foto/lampiran, BK/ibadah sensitif, log keamanan, cadangan, dan salinan penyedia teknis. “Data sekolah” tidak otomatis berarti semua data harus dipertahankan selamanya.
4. Tim teknis memastikan ada cara yang aman untuk benar-benar menghapus akun akses/data terkait yang memenuhi syarat setelah disetujui, terpisah dari arsip pendidikan. **Saat ini menu akun hanya menyediakan penonaktifan; kanal permintaan tidak menambah mesin penghapusan.** Jangan menyamakan status nonaktif dengan penghapusan.
5. Deploy kode web dan uji URL publik tanpa login dari perangkat/jaringan lain. Pastikan email terbaca tanpa JavaScript, halaman tidak terhalang login/Cloudflare challenge/geofencing, dan tidak ada data pribadi di URL.
6. Bangun dan uji AAB baru agar tautan login/profil ini tersedia pada aplikasi yang diunggah. AAB yang sudah ada belum berubah oleh edit kode ini; pembaruan Play Console biasanya membutuhkan versionCode lebih tinggi.
7. Uji satu permintaan menggunakan data contoh yang terisolasi: penerimaan, verifikasi, penilaian, keputusan, tindakan yang disetujui, dan jawaban akhir. Jangan menguji dengan menghapus data siswa/pegawai asli.

## Panduan penanganan petugas

### 1. Terima dan catat secara minimum

- Gunakan kotak surat resmi; jangan meneruskan permintaan atau lampiran ke grup/chat pribadi.
- Beri nomor referensi dan catat tanggal diterima, kontak pemohon, akun yang dimaksud, jenis permintaan, status verifikasi, keputusan, petugas, serta tanggal tindak lanjut. Jangan mencatat kata sandi/OTP atau menyalin semua data sensitif ke register.
- Bedakan: (a) hapus akun akses dan data terkait, (b) hapus data tertentu tanpa hapus akun, (c) akses/koreksi, atau (d) hanya nonaktifkan akun. Konfirmasikan jika maksud pemohon belum jelas.
- Akses permintaan dan masa simpan register permintaan harus dibatasi serta ditetapkan sekolah; jangan menyimpan email/verifikasi tanpa batas hanya karena permintaan berkaitan dengan penghapusan.

### 2. Verifikasi tanpa membuka data ke pihak yang belum berwenang

- Cocokkan pemohon menggunakan kanal dan data minimum yang sudah dimiliki sekolah. Jika memakai konfirmasi tambahan, jangan meminta kata sandi NUSA atau OTP login.
- Untuk orang tua/wali, verifikasi hubungan dan kewenangan atas siswa. Untuk perwakilan pegawai/alumni atau pihak lain, verifikasi kewenangan perwakilan. Usia/kondisi anak dan ketentuan yang berlaku perlu dipertimbangkan petugas.
- Jangan menganggap mengetahui NISN/NIP/username atau bisa mengirim email sebagai bukti tunggal kewenangan. Jangan membocorkan apakah akun/data tertentu ada kepada pihak yang belum diverifikasi.
- Pemohon yang tidak bisa login tetap harus mendapat jalur verifikasi alternatif; jangan mewajibkan pemasangan ulang aplikasi.

### 3. Petakan akun dan data terkait

Pisahkan akun autentikasi dari entitas siswa, pegawai, orang tua/wali, dan arsip pendidikan. Periksa cakupan berikut sesuai permintaan, **bukan daftar penghapusan otomatis**:

- kredensial akun, kata sandi awal tersimpan, remember token, sesi web, token API/Sanctum;
- token/perangkat push, preferensi, akses cepat, relasi peran, dan notifikasi pribadi;
- foto profil/identitas dan lampiran, termasuk berkas pada penyimpanan publik dan cache/CDN;
- catatan keamanan/log, data ujian, BK, berhalangan ibadah, komunikasi, pengajuan dan data layanan;
- data pada penyedia teknis, salinan cadangan, ekspor, cetakan, dan berkas yang telah dibagikan secara sah.

**Peringatan teknis:** model `Pengguna` terkait banyak tabel dan akun sistem memiliki perlindungan. Relasi siswa/pegawai/orang tua serta foreign key dapat memiliki efek cascade, restrict, atau null. Jangan menjalankan DELETE SQL massal, `$pengguna->delete()`, menghapus entitas siswa/pegawai, atau mengganti identitas menjadi akun sembarang sebagai solusi tanpa analisis relasi, rencana tindakan, dan pengujian data contoh. Hal itu dapat menghapus arsip atau merusak atribusi/audit.

### 4. Putuskan per kategori bersama pihak berwenang

- Untuk data yang tidak lagi diperlukan/tidak memiliki dasar penyimpanan: tentukan penghapusan atau anonimisasi yang benar-benar mencegah identifikasi jika sesuai ketentuan; perubahan nama/status saja bukan bukti anonimisasi.
- Untuk data yang harus dipertahankan: catat kategori, dasar/tujuan, masa simpan/status permanen sesuai JRA, dan akses setelah akun ditutup. Retensi 5 tahun maupun status “arsip sekolah” bukan alasan otomatis menolak semua permintaan.
- Nilai, rapor, riwayat pendidikan, presensi, atau arsip administrasi **tidak otomatis dihapus oleh permintaan**. Namun petugas tetap harus menilai dasar retensi masing-masing, bukan menjanjikan seluruh data selalu disimpan.
- Jelaskan perkiraan waktu, konsekuensi kehilangan akses, data yang dipertahankan, dan jalur keberatan kepada pemohon. Jika permintaan perlu klarifikasi atau verifikasi, jelaskan kebutuhan informasi tambahan secara minimum.
- Minta persetujuan pelaksanaan dari pihak sekolah yang berwenang; keputusan umum dalam dokumen ini bukan izin melakukan tindakan destruktif pada server.

### 5. Laksanakan hanya tindakan yang disetujui

- Tim teknis menyiapkan daftar perubahan yang tepat, risiko relasi, pengujian, dan langkah pemulihan yang terkendali. Hindari membuat cadangan baru tanpa batas sebagai alasan membatalkan penghapusan.
- Sesuai keputusan, cabut akses/sesi/token dan tangani data terkait yang memenuhi syarat. **Pencabutan akses adalah langkah pengamanan, bukan dengan sendirinya penghapusan selesai.**
- Jangan menyentuh nilai/arsip di luar daftar yang disetujui. Jangan mengubah hubungan identitas siswa/pegawai/orang tua sehingga pengguna lain memperoleh akses.
- Untuk penyedia teknis/cache/cadangan, lakukan langkah yang relevan dan catat masa simpan serta pembatasannya. Data yang sudah dihapus tidak boleh kembali ke layanan aktif hanya karena memulihkan cadangan; siapkan prosedur menerapkan kembali keputusan penghapusan.
- Verifikasi hasil dengan pemeriksaan minimum dan pastikan akun/data yang salah tidak terdampak. Jangan mengklaim “terhapus di semua tempat” bila belum dapat dibuktikan.

### 6. Jawab dan tutup permintaan

- Kirim keputusan/hasil melalui kontak yang telah diverifikasi, tanpa lampiran data sensitif yang tidak perlu.
- Bedakan hasil: selesai, selesai sebagian dengan retensi yang dijelaskan, menunggu verifikasi, atau ditolak dengan dasar serta jalur peninjauan. Jangan menulis selesai jika hanya nonaktif.
- Catat penanggung jawab dan hasil pemeriksaan. Pengelola meninjau keterlambatan, keberatan, dan kecocokan informasi publik dengan layanan yang benar-benar dijalankan.

## Contoh jawaban petugas

### Penerimaan — sesuaikan setelah verifikasi awal

> Kami menerima permintaan privasi NUSA Anda pada [tanggal], referensi [nomor]. Kami akan memverifikasi identitas/kewenangan dan meninjau cakupan permintaan. Jangan kirim kata sandi atau OTP. Kami akan menjelaskan informasi tambahan yang diperlukan, keputusan per kategori data, dan perkiraan waktu pelaksanaan melalui kontak yang telah diverifikasi.

### Keputusan/hasil — jangan kirim sebelum benar-benar sesuai keadaan

> Permintaan [referensi] memiliki hasil [status]. Data yang [disetujui untuk dihapus / telah dihapus dan diverifikasi]: [rincian minimum]. Data yang tetap disimpan: [kategori], dengan dasar/tujuan [dasar] dan masa simpan/status [retensi]. Dampak akses: [penjelasan]. Pelaksanaan/pemeriksaan: [tanggal atau perkiraan]. Untuk keberatan, minta peninjauan melalui official@smpn2padangpanjang.sch.id dengan menyebut referensi ini.

## Verifikasi teknis dan penerapan

Tes lokal (basis data pengujian SQLite memory, bukan data sekolah):

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/PermintaanPenghapusanTest.php tests/Feature/KebijakanPrivasiTest.php
```

Tes mobile:

```powershell
cd mobile
flutter test test/account_deletion_link_test.dart test/privacy_policy_link_test.dart test/profile_page_test.dart --no-pub
```

Deploy mengikuti prosedur server sekolah, termasuk penyegaran route/view cache yang biasa dipakai. Halaman harus benar-benar aktif di produksi sebelum URL dimasukkan sebagai kanal fungsional. Tidak perlu migrasi basis data untuk perubahan ini. **Deploy, pembangunan AAB, unggah Play Console, pengesahan SOP, dan penghapusan data bukan bagian yang otomatis dilakukan oleh penambahan kode ini.**

Rujukan resmi: [persyaratan penghapusan akun Google Play](https://support.google.com/googleplay/android-developer/answer/13327111?hl=en-EN), [UU Pelindungan Data Pribadi](https://jdih.komdigi.go.id/produk_hukum/view/id/832/t/crc32/), dan [Perwali Padang Panjang Nomor 16 Tahun 2025 beserta JRA](https://peraturan.bpk.go.id/Details/326880/perwali-kota-padang-panjang-no-16-tahun-2025). Rujukan bukan pengganti penilaian penerapan kategori arsip dan kewajiban hukum oleh sekolah.

### Hasil pemeriksaan lokal 12 Oktober 2026

- Tes mobile tautan penghapusan, tautan privasi, dan profil: **21 tes lulus**.
- `flutter analyze --no-pub`: **No issues found**.
- Tes PHP halaman penghapusan dan kebijakan privasi: **14 tes lulus, 137 assertion**. Pemeriksaan meliputi halaman publik tanpa sesi/query basis data/cookie, email tanpa identitas akun, metode hanya GET/HEAD, input URL tidak dipantulkan, akses login/privasi, dan tautan naskah pada URL dengan trailing slash.
- `git diff --check`: tidak ada masalah whitespace.
- Akses URL produksi belum dapat diverifikasi melalui alat penelusuran. Pemeriksaan lokal tidak membuktikan deploy/Cloudflare/kotak surat/prosedur petugas sudah berjalan. AAB tidak dibangun ulang pada pekerjaan ini.
