# NUSA Mobile

Aplikasi Android NUSA untuk SMP Negeri 2 Padang Panjang. Proyek ini berada
dalam repository yang sama dengan backend Laravel NUSA, tetapi memiliki proses
build dan dependensi sendiri.

## Menjalankan aplikasi

Pastikan backend Laravel berjalan dari root repository:

```powershell
php artisan migrate
php artisan serve
```

Jalankan aplikasi dari folder `mobile`:

```powershell
flutter pub get
flutter run -d emulator-5554 `
  --dart-define=APP_ENV=development `
  --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

Tanpa `--dart-define`, build development menggunakan
`http://10.0.2.2:8000/api/v1/`. Build staging dan production wajib menerima
`API_BASE_URL` berbasis HTTPS.

## Struktur utama

```text
lib/
├── app/       bootstrap, aplikasi, dan routing
├── core/      konfigurasi, error, network, storage, dan tema
├── features/  fitur berdasarkan kebutuhan pengguna
└── shared/    widget dan utilitas yang digunakan lintas fitur
```

Setiap fitur menggunakan pemisahan View, ViewModel, Repository, dan Service
sesuai kebutuhan. Domain layer hanya ditambahkan jika logika bisnis mobile
menjadi cukup kompleks.

## Pemeriksaan kualitas

```powershell
dart format --output=none --set-exit-if-changed lib test
flutter analyze
flutter test
flutter build apk --debug
```

Autentikasi mobile menggunakan token Laravel Sanctum. Token disimpan melalui
`TokenStorage` berbasis secure storage, dipulihkan saat aplikasi dibuka, dan
dihapus saat sesi tidak lagi valid. Secara default token berlaku selama 30 hari;
durasi dapat diubah melalui `SANCTUM_EXPIRATION` dalam satuan menit. Jangan
menyimpan token, kata sandi, maupun rahasia aplikasi di source code atau
penyimpanan biasa.

Endpoint autentikasi yang digunakan berada di bawah `/api/v1/auth`:

- `POST /login`
- `GET /saya`
- `PUT /kata-sandi`
- `POST /logout`

App shell setelah login menyediakan Beranda, Aktivitas, tombol utama NUSA,
Notifikasi, dan Profil. Tombol utama NUSA membuka katalog menu sesuai hak akses.
Data ringkasan diambil dari `GET /api/v1/beranda`, mencakup presensi pribadi
pegawai, piket hari ini, perwalian, tahun pelajaran, dan sepuluh notifikasi
terbaru milik pengguna.

Katalog administrasi diambil dari `GET /api/v1/menu`. Laravel menyaring item
menurut role dan izin pengguna sebelum mengirimkannya ke aplikasi. Menu dapat
dicari dan dikelompokkan; modul yang belum mempunyai halaman native ditandai
`Segera hadir`. Tarik layar ke bawah atau tekan tombol perbarui untuk memuat
ulang data pada tab aktif.

Modul Data Pegawai menyediakan ringkasan, pencarian, filter status dan jenis
pegawai, daftar, detail identitas/penugasan/akun, serta formulir tambah dan ubah
native. Endpoint yang digunakan adalah:

- `GET /api/v1/pegawai`
- `GET /api/v1/pegawai/{pegawai}`
- `POST /api/v1/pegawai`
- `PATCH /api/v1/pegawai/{pegawai}`

Daftar dan detail memerlukan izin `pegawai.lihat` atau `pegawai.kelola`,
sedangkan perubahan hanya tersedia untuk `pegawai.kelola`. NIP, NUPTK, NIK,
dan email divalidasi unik oleh server. Jika pegawai sudah memiliki akun login,
perubahan NIP akan menyinkronkan username akun di dalam transaksi yang sama.
Foto yang sudah ada tetap dipertahankan karena unggah foto belum termasuk
formulir mobile tahap ini.

Beranda juga membentuk Akses Cepat dan Semua Menu dari katalog tersebut, bukan
dari daftar hardcode. Karena itu pengguna hanya melihat kelompok dan pintasan
yang sesuai dengan izin akunnya. Modul Data Siswa merupakan modul administrasi
native pertama yang tersedia:

- `GET /api/v1/siswa` untuk daftar, pencarian, filter status, dan pagination
- `GET /api/v1/siswa/{siswa}` untuk detail identitas, kelas aktif, serta data
  orang tua/wali

Kedua endpoint memerlukan izin `siswa.lihat` atau `siswa.kelola`. Akun wali
kelas tetap dibatasi hanya pada siswa di kelas yang diwalinya.

Modul Data Kelas menggunakan endpoint berikut:

- `GET /api/v1/kelas` untuk daftar, pencarian nama kelas/wali kelas, filter
  status dan tahun pelajaran, serta pagination
- `GET /api/v1/kelas/{kelas}` untuk detail tahun pelajaran, wali kelas,
  kapasitas, anggota aktif, riwayat anggota, dan jadwal mingguan
- `GET /api/v1/kelas/{kelas}/calon-anggota` untuk mencari siswa aktif yang
  belum ditempatkan pada tahun pelajaran kelas
- `POST /api/v1/kelas/{kelas}/anggota` untuk menambahkan anggota
- `PATCH /api/v1/kelas/{kelas}/anggota/{anggota}` untuk mengubah tanggal masuk
  dan keterangan anggota
- `DELETE /api/v1/kelas/{kelas}/anggota/{anggota}` untuk mengeluarkan anggota
- `GET /api/v1/kelas/{kelas}/jadwal/pilihan` untuk mengambil penugasan guru
  mapel dan kegiatan yang tersedia bagi kelas
- `PUT /api/v1/kelas/{kelas}/jadwal/{jam}` untuk mengisi, mengubah, atau
  mengosongkan satu slot pelajaran

Endpoint baca memerlukan izin `kelas.lihat` atau `kelas.kelola`, sedangkan
perubahan anggota hanya tersedia untuk `kelas.kelola`. Jadwal hanya dikirim
jika pengguna memiliki `jadwal.lihat` atau `jadwal.kelola`, dan perubahan
jadwal hanya tersedia untuk `jadwal.kelola`. Server menolak slot nonpelajaran,
penugasan yang bukan milik kelas, kegiatan yang tidak sesuai tingkat, dan
benturan guru. Pembatasan kelas wali juga diterapkan oleh server sehingga tidak
hanya bergantung pada UI.

Modul Jam Pelajaran menyediakan daftar slot mingguan, filter hari/status,
penambahan slot untuk beberapa hari sekaligus, serta perubahan waktu, jenis,
status, dan keterangan. Endpoint yang digunakan adalah:

- `GET /api/v1/jam-pelajaran`
- `POST /api/v1/jam-pelajaran`
- `PATCH /api/v1/jam-pelajaran/{jamPelajaran}`

Modul ini khusus administrator. Saat slot baru disisipkan, server menggeser
nomor slot berikutnya tanpa mengganti ID, sehingga referensi jadwal yang sudah
ada tetap aman. Batas slot per hari mengikuti backend, yaitu 20 slot.

Modul Mata Pelajaran menampilkan ringkasan, pencarian, filter status, tingkat,
dan tahun pelajaran, serta formulir tambah/ubah native. Kode dan KKM/KKTP
disimpan per tingkat VII, VIII, dan IX pada tahun pelajaran yang dipilih.
Kokurikuler, Ekstrakurikuler, dan Pengembangan Diri otomatis memakai predikat
SB/B/C/K tanpa KKM. Endpoint yang digunakan adalah:

- `GET /api/v1/mata-pelajaran`
- `GET /api/v1/mata-pelajaran/referensi`
- `POST /api/v1/mata-pelajaran`
- `PATCH /api/v1/mata-pelajaran/{mataPelajaran}`

Daftar memerlukan izin `mata_pelajaran.lihat` atau `mata_pelajaran.kelola`.
Form tambah dan ubah hanya tersedia untuk `mata_pelajaran.kelola`. Server
memastikan nama unik, kode unik per tahun, minimal satu tingkat aktif, serta
KKM/KKTP 0–100 untuk mata pelajaran dengan penilaian angka.

Modul Guru Mata Pelajaran mengelola penugasan guru, mata pelajaran, kelas, tahun
pelajaran, jenis penugasan, status, dan catatan. Endpoint yang digunakan adalah:

- `GET /api/v1/guru-mata-pelajaran`
- `GET /api/v1/guru-mata-pelajaran/referensi`
- `POST /api/v1/guru-mata-pelajaran` untuk penugasan ke satu atau beberapa kelas
- `PATCH /api/v1/guru-mata-pelajaran/{guruMataPelajaran}`

Daftar memerlukan izin `guru_mapel.lihat` atau `guru_mapel.kelola`. Form tambah
dan ubah hanya tersedia untuk `guru_mapel.kelola`. Server memastikan kelas dan
mata pelajaran tersedia pada tahun pelajaran serta tingkat yang dipilih sebelum
menyimpan penugasan. Penugasan ini langsung menjadi pilihan pada pengelolaan
Jadwal Pelajaran kelas.

Modul Akun Pegawai tersedia melalui menu **Sistem → Akun Pegawai**. Modul ini
menyediakan ringkasan akun, pencarian, filter status, pembuatan akun tunggal dan
massal, pengaturan role, aktivasi/nonaktivasi akun, serta reset kata sandi.
Endpoint yang digunakan adalah:

- `GET /api/v1/akun-pegawai`
- `GET /api/v1/akun-pegawai/{pegawai}`
- `POST /api/v1/akun-pegawai/{pegawai}`
- `POST /api/v1/akun-pegawai/buat-massal`
- `PATCH /api/v1/akun-pegawai/{pegawai}/peran`
- `PATCH /api/v1/akun-pegawai/{pegawai}/status`
- `PATCH /api/v1/akun-pegawai/{pegawai}/reset-kata-sandi`

Hak baca memakai izin `akun.lihat` atau `akun.kelola`, sedangkan seluruh
perubahan membutuhkan `akun.kelola`. Username mengikuti NIP tanpa spasi, role
dasar **Pegawai** selalu dipertahankan, dan operasi berisiko selalu meminta
konfirmasi.

Modul Tahun Pelajaran menampilkan ringkasan periode, tahun yang sedang aktif,
jumlah kelas per tahun, pencarian, filter status, serta formulir tambah/ubah
native. Endpoint yang digunakan adalah:

- `GET /api/v1/tahun-pelajaran`
- `POST /api/v1/tahun-pelajaran`
- `PATCH /api/v1/tahun-pelajaran/{tahunPelajaran}`

Daftar memerlukan izin `tahun_pelajaran.lihat` atau
`tahun_pelajaran.kelola`, sedangkan perubahan hanya tersedia untuk
`tahun_pelajaran.kelola`. Nama tahun harus unik dan tanggal selesai tidak boleh
mendahului tanggal mulai. Saat sebuah tahun ditetapkan aktif, server memakai
transaksi untuk menonaktifkan tahun aktif sebelumnya sehingga hanya ada satu
periode aktif pada satu waktu. Aplikasi selalu meminta konfirmasi sebelum
pergantian tersebut.

Design system mobile menggunakan `NusaColors` dengan biru `#15477A` dan kuning
`#F1C40F`. Logo berada di `assets/images/logo-nusa.png`, sedangkan ilustrasi
sekolah dan pendidikan dibuat sebagai vector painter agar tetap ringan dan
responsif. Splash memiliki jeda singkat untuk transisi session restore; alur
autentikasi tetap menggunakan session token Sanctum yang sama.

Seluruh pilihan berbentuk dropdown field wajib menggunakan
`NusaDropdownField` dari `shared/widgets/nusa_form_widgets.dart`. Komponen ini
menjaga popup sejajar dan selebar field, memberi radius serta bayangan yang
konsisten, dan membatasi tinggi daftar panjang agar tetap dapat di-scroll di
dalam halaman maupun modal. `PopupMenuButton` terpisah hanya digunakan untuk
menu aksi kontekstual, bukan sebagai input formulir.
