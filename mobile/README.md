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
