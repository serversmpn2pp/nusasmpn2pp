# Foto sekolah pada login dan beranda mobile — 27 Oktober 2026

Tampilan mengikuti referensi login dan beranda yang diberikan pengguna, dengan
foto asli `IMG_20201102_073147-scaled.jpg` sebagai identitas SMP Negeri 2 Padang
Panjang. Versi aktif dikemas di
`mobile/assets/images/school-facade-daylight.webp`, berukuran 1448 × 1086 dan
515.158 byte. Pengemasan WebP memakai kualitas 90. Aset didaftarkan di
`mobile/pubspec.yaml` dan tersedia sebelum login, tanpa unduhan foto dari server.
Versi sebelum penyesuaian warna, `school-facade.webp`, tetap tersimpan sebagai
referensi dan tidak didaftarkan sebagai aset aplikasi.

## Langit biru dan detail gedung

Pengguna meminta warna mendekati gambar referensi: langit biru, gedung yang
lebih jelas, dan tampilan yang tidak pucat. Foto diedit dengan tool `image_gen`
bawaan (mode edit, tanpa CLI/API key tambahan). Foto asli menjadi target edit;
gambar referensi beranda dipakai untuk pencahayaan dan warna. Hasil PNG kerja
disimpan di `mobile/build/photo-assets/school-facade-daylight.png`, lalu dikemas
menjadi aset WebP aktif. Foto asli di Downloads tidak diubah.

Gradasi putih pada bagian atas foto login dan sisi kanan beranda dikurangi.
Pelindung putih di belakang formulir, sapaan, dan teks ukuran besar tetap
tersedia. Tata letak, ukuran komponen, dan ikon tidak berubah pada penyesuaian ini.

Prompt final untuk tool bawaan:

> Edit Image 1 only (the original school photograph). Image 2 is a color/lighting reference only; do not include its app UI. Make the overcast sky naturally blue with a few soft white clouds, and make the building crisp, with gently sunny daylight, balanced contrast and richer natural greens, close to the photo appearance in Image 2. Preserve the original 4:3 landscape framing, camera perspective, exact school architecture, roof, windows, doors, trees, plants, flag, hills and all signage/text. The school name must remain SMP NEGERI 2 PADANG PANJANG. Do not add, remove, redesign or move any school element. No UI, logos, gradients, white haze, watermarks or invented text. Return just the subtly enhanced, photorealistic school photo, suitable as an Android app background.

## Login

Logo NUSA dan nama sekolah berada di atas foto gedung. Foto bertransisi lembut
ke area formulir dengan gradasi putih; warna teks dan komponen isian tetap memakai
tema NUSA. Ilustrasi sekolah/buku diganti foto dan tagline “Satu Nusa, Terhubung
untuk Bertumbuh”, dengan aksen kuning.

Formulir, controller, validasi, autofill, tombol tampilkan kata sandi, pengiriman
kredensial, serta tautan kebijakan privasi dan permintaan penghapusan memakai
alur sebelumnya. Ketika keyboard terbuka, ruang foto di antara identitas dan
formulir dipadatkan. Konten tetap dapat digulir pada layar pendek, landscape,
dan ukuran huruf besar.

## Beranda

Foto berada pada bagian atas beranda dan diarahkan ke kanan. Lapisan putih
melindungi keterbacaan sapaan, nama pengguna, tanggal, dan tagline di kiri.
Sapaan dan nama tetap berasal dari data akun; nama panjang dapat membungkus
baris tanpa dipotong. Bagian atas ikut bergulir bersama konten.

Kartu presensi, ringkasan ibadah, Akses Cepat satu baris, Semua Menu, jadwal,
dan navigasi tetap menggunakan komponen dan callback sebelumnya. Pemetaan
ikon, warna kelompok menu, pengaturan pintasan, hak akses, dan API tidak diubah.
Pembesaran huruf perangkat tetap dihormati.

## Pemeriksaan

```powershell
flutter pub get --offline --enforce-lockfile
flutter test --no-pub test/school_photo_identity_test.dart test/home_quick_access_layout_test.dart test/widget_test.dart test/privacy_policy_link_test.dart test/account_deletion_link_test.dart
flutter analyze --no-pub
```

Cakupan mencakup dekode aset lokal, login pada layar 320–844, keyboard terbuka,
landscape, huruf 200%, pesan gagal masuk, nama panjang, tombol notifikasi,
ikon asli, navigasi, serta pengaturan dan tautan kebijakan. Tes ringkasan ibadah
menggulir beranda untuk menemukan kartu yang dibangun secara lazy.

Hasil verifikasi: 68 tes terkait lulus dan Flutter analyzer tidak menemukan masalah.
Sesudah penyesuaian langit/warna, 20 tes foto sekolah dan Akses Cepat dijalankan
kembali dan lulus, termasuk dekode aset, keyboard, huruf besar, serta ikon/navigasi.

Hasil render widget produksi dengan data contoh:

- `mobile/build/login-school-photo.png`
- `mobile/build/home-quick-access-icon-row.png`

Perubahan ini tersedia di kode Android. Jalankan kembali aplikasi untuk
memuat paket aset yang baru. Tidak memerlukan perubahan backend atau migrasi.
AAB baru belum dibangun; versi rilis tidak dinaikkan pada perubahan tampilan ini.

## Penyelarasan bagian atas login dengan potongan referensi

Pengguna kemudian memberikan potongan referensi bagian atas login. Bagian ini
menggunakan aset tersendiri, `mobile/assets/images/school-login-header.webp`
(1122 × 1402, 583.268 byte), sehingga foto dan susunan beranda tetap memakai versi
sebelumnya. Aset tegak dibuat melalui edit/outpaint `image_gen` bawaan terhadap
foto daylight. Gambar potongan login pengguna hanya menjadi referensi bingkai;
logo, nama sekolah, dan sapaan tidak dipanggang ke dalam bitmap. PNG kerja ada di
`mobile/build/photo-assets/school-login-header.png`.

Pembingkaian baru menampilkan fasad hingga tangga masuk, dengan logo dan nama
sekolah di area langit. Tinggi kelompok identitas/sapaan mengikuti lebar layar,
dengan batas 350–525 dp; pada keyboard terbuka atau landscape, ruang header
dipadatkan. Sapaan muncul di bawah fasad. Gradasi melebur ke putih sebelum tepi
foto, dan tidak meninggalkan garis potong atau bayangan abu-abu. Warna transparan
pada gradasi memakai putih beralfa nol. Formulir, validasi, autentikasi, tautan,
ikon, dan navigasi tetap menggunakan implementasi yang sama.

Prompt final (tool bawaan, bukan CLI):

> Use case: precise-object-edit. Edit target Image 1 is the existing daylight photograph of SMP Negeri 2 Padang Panjang. Image 2 is a framing reference ONLY, not a UI to reproduce. Create a portrait 4:5 background photograph for a native mobile login header by outpainting more of the same blue sky and tree canopy ABOVE Image 1. Preserve the school architecture, façade proportions, perspective, windows, entrance, flag, plants, trees, and school sign exactly. Keep the original photograph at its original scale relative to the canvas width: do not zoom into the building. Composition: the roof line should be at about 40% of the final canvas height; the entrance steps should be at about 80%. Add roughly 28% of the source image WIDTH as extra sky/tree canopy above the source; add some foreground below as needed. The upper third has blue sky with soft clouds framed by existing trees, leaving a readable central sky area. Match the naturally bright but gentle daylight of the reference. Return ONLY the photograph. Do not include any UI, NUSA logo, greeting, text overlays, status bar, phone frame, rounded corners, white haze or gradients. Preserve existing photographic signage, especially the exact words SMP NEGERI 2 PADANG PANJANG. Do not redesign or invent school elements.

69 tes gabungan terkait login, beranda, akses cepat, dan tautan akun lulus.
Aset login baru ikut diperiksa dekodenya secara offline. Hasil lengkap ada di
`mobile/build/test-login-header-final.log`; pemeriksaan foto/login setelah
penyesuaian akhir dan analyzer dicatat pada log `test-login-header.log` dan
`analyze-login-header.log`.

Pratinjau native penuh: `mobile/build/login-school-photo.png`. Potongan bagian
atas: `mobile/build/login-school-header.png`. Pratinjau tidak meniru jam, kamera,
atau ikon status Android, karena bagian tersebut digambar oleh perangkat.

## Login native satu layar — 28 Oktober 2026

Implementasi terbaru mengganti tinggi header berbasis lebar dengan
`SliverFillRemaining` dan area foto fleksibel. Formulir serta footer menentukan
ruang yang dibutuhkan terlebih dahulu; foto mengisi sisanya. Pada ukuran huruf
normal, semua tindakan dan keterangan tampil tanpa perlu menggulir. Verifikasi
mencakup layar 320 × 568, 360 × 640, 390 × 844, dan 412 × 915 dengan area aman
status/navigasi masing-masing 24 dp. Tes memeriksa extent scroll nol, seluruh
tombol berada dalam viewport, dan gerakan drag tidak memindahkan halaman.

Logo login berukuran 64 dp, tulisan NUSA 34 sp, dan nama sekolah 15–16 sp.
Parameter ukuran tulisan pada `NusaBrand` bersifat opsional; nilai bawaan dan
pemetaan ikon beranda tetap sama. Teks merek dapat membungkus pada ruang sempit
dengan huruf sangat besar, tanpa mengecilkan ukuran huruf perangkat.

Blur dibuat dengan `ImageFiltered` native pada salinan foto yang sejajar dengan
foto utama, memakai sigma 5 dan mask vertikal yang memudar dari area identitas
ke gedung. Logo dan teks tidak berada di dalam filter. Area foto diberi clipping
agar hasil blur tidak meluber melewati ujung gradasi. Tidak ada foto baru yang
dibuat atau diubah pada pekerjaan ini.

Saat keyboard muncul, header memakai logo 40 dp, tulisan NUSA 26 sp, dan nama
sekolah 12 sp. Foto utama, subtitle, tagline, serta keterangan tim disembunyikan
sementara. Kedua tautan publik tetap tersedia. Scroll diaktifkan secara alami
bila ruang keyboard, landscape, pesan validasi, atau ukuran huruf besar membuat
konten tidak muat. Controller, nilai isian, fokus, dan pilihan tampilkan kata
sandi tetap dipertahankan. Pada layar sangat pendek, subtitle dekoratif dilepas
dan tautan footer disusun berdampingan agar semua tindakan tetap terlihat.

Tes perpindahan keyboard memeriksa kolom username, kata sandi, dan tombol Masuk
berada di atas keyboard pada viewport utama, serta isi kolom tidak hilang setelah
keyboard ditutup. Tes tautan penghapusan menunggu frame sesudah `ensureVisible`
sebelum melakukan tap pada layout sliver.

Pratinjau widget produksi terbaru:

- `mobile/build/login-one-screen-native.png`
- `mobile/build/login-keyboard-native.png` (area aplikasi di atas keyboard;
  keyboard Android sendiri tidak digambar dalam widget test)

Log pemeriksaan terkait: `mobile/build/test-login-native-final.log` dan
`mobile/build/analyze-login-native-final.log`. AAB belum dibangun dan nomor versi
tidak berubah pada pekerjaan ini.

Hasil pemeriksaan: 76 tes gabungan lulus. Setelah polesan gradasi putih akhir,
16 tes foto/login dijalankan ulang dan lulus (`mobile/build/test-login-adaptive.log`).

Foto latar mode keyboard kemudian diperjelas dengan opacity maksimum 24% di
bagian tengah. Mask gradasi membatasi area di belakang identitas menjadi sekitar
4–5%, lalu menurunkannya menjadi sekitar 14% di bawah agar tautan tetap terbaca.
Mode tanpa keyboard tetap memakai latar 4%. Susunan, ukuran, serta interaksi
formulir tidak berubah. 16 tes terkait dijalankan ulang dan lulus; log tersedia
di `mobile/build/test-login-keyboard-background.log`.

