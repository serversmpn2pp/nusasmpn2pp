# Tema NUSA Lintas Role

## Prinsip

`layouts.app` menggunakan `$temaAntarmukaNusa = auth()->check()`. Penanda visual
ini terpisah dari `administrator()`, pemeriksaan izin, pemilihan menu, dan
pembatasan data di controller/service. Semua pemeriksaan tersebut tetap ada.
Asset dan class `admin-*` yang sudah ada dipertahankan untuk kompatibilitas;
nama class tersebut bukan tanda bahwa akun memiliki hak admin.

- Guru, TU dan pimpinan tetap memakai dashboard data pegawai serta menu sesuai
  penugasan/izin, bukan statistik global administrator.
- Siswa tetap memperoleh data dirinya sendiri. Orang tua hanya memperoleh
  anak yang terhubung; parameter anak asing tidak membuka data anak tersebut.
- Pilihan sidebar, pencarian menu, notifikasi, tooltip dan komponen kontrol
  menggunakan implementasi yang sama. Preferensi sidebar tetap terpisah per akun.
- Filter/bagian formulir kelompok yang sudah ditandai tetap memakai stylesheet
  kelompok. Halaman pribadi yang belum mempunyai penanda memakai fallback
  `pribadi`. Dashboard admin dan Data Sekolah tidak menerima fallback tersebut.
- `nusa-account-pages.css` merapikan dashboard dan ringkasan pribadi tanpa
  menambah data, grafik, atau kewenangan baru.

## Pemindai Mandiri

Empat terminal memuat `partials.scanner-theme` dan `nusa-scanner.css`:
presensi siswa, presensi pegawai, kegiatan ibadah, dan berhalangan ibadah.
Tidak ada sidebar aplikasi di terminal. ID kontrol, CSRF, endpoint, pembaca
kartu, antrean, waktu server, validasi duplikat dan masa scan tidak diubah.

Pemindai ibadah tetap memerlukan petugas yang diizinkan berdasarkan penugasan.
Pemindai berhalangan tetap privat: tidak menambahkan daftar scan terbaru.
Warna teks/status mempunyai kontras terhadap latar putih; keberhasilan,
duplikat, penolakan dan kegagalan jaringan tetap mempunyai label yang berbeda.

## CBT Dan Cetak

`cbt.layout` tetap terpisah dari kerangka aplikasi. `nusa-exam.css` hanya
menata warna, fokus keyboard, pilihan jawaban dan keterbacaan kontrol. Tidak
mengubah timer, syarat pengumpulan, autosave, status jawaban sebagian, mode aman,
fullscreen, focus guard maupun perlindungan terjemahan. Tidak menambahkan sidebar.

View cetak rapor/leger/lampiran perilaku tetap mandiri. Ukuran kertas, pembagian
halaman, tanda tangan dan data rapor tidak diubah oleh tema aplikasi.

## Pengujian

- `TampilanLintasRoleTest`: menu/asset lintas role, nilai guru sesuai penugasan,
  pembatasan anak, izin sistem, terminal scan aktif/tutup dan privasi.
- Fixture: `NUSA_CAPTURE_ROLE_THEME=1`, di `storage/framework/testing/role-theme`.
- `tests/Browser/role-theme.mjs`: enam ukuran layar; drawer/hidden sidebar tiap
  role; RFID berhasil/duplikat/gagal/offline; penolakan izin kamera, mulai/stop,
  pratinjau video serta input NISN cadangan. Aliran video virtual berasal dari
  frame canvas dan respons HTTP disimulasikan; tidak menulis presensi ke server.
- Rapor diperiksa dengan `rapor-sts-print.mjs` dan `perilaku-sts-print.mjs`:
  A4, urutan halaman siswa/lampiran, paginasi catatan panjang, logo, nilai kosong
  dan tidak terpotong.
- CBT diperiksa dengan `cbt-phone.mjs` serta `cbt-security-field.mjs` menggunakan
  fixture Blade. Regresi mencakup autosave, offline/retry, jawaban sebagian,
  modal, pengumpulan serta kejadian keluar halaman.

Audit dan screenshot disimpan di `storage/logs/role-theme-qa` dan direktori audit
masing-masing. Pengujian PHP memakai SQLite sementara, bukan database lokal.
Tidak ada migrasi atau perubahan role akun nyata yang diperlukan.

Kamera fisik HP dan pembaca kartu di sekolah tetap perlu uji penerimaan pada
perangkat sebenarnya; kamera virtual tidak memverifikasi driver/perizinan OS HP.
