# Data Sekolah: Percontohan Tahap 3

Kelompok ini menerapkan komponen tahap 2 pada sembilan menu: Tahun Pelajaran,
Pegawai, Siswa, Kelas, Penempatan Siswa, Kenaikan Kelas, Foto Identitas, Kartu
Pegawai, dan Kartu Pelajar. Halaman tambah, edit, detail, dan import termasuk
dalam cakupannya.

## Batas Dan Sumber Gaya

`layouts.app` memuat `admin-data-sekolah.css` untuk akun yang berhak pada
route kelompok tersebut. Selector menggunakan `nusa-admin-shell.admin-school-data`.
Dashboard dan kelompok lain tetap memakai tahap 1/2. Susunan umum, warna dan
kontrol tetap mengacu pada `komponen-tampilan-admin.md`, bukan sistem warna baru.
Tidak ada perubahan skema, perhitungan, endpoint, izin atau cakupan data.

## Pola Halaman

- Halaman daftar: judul/aksi, ringkasan berwarna, filter, judul daftar/jumlah
  hasil, tabel/daftar ponsel, pagination. Tetap memakai pagination server dan
  filter otomatis yang sudah ada.
- Tabel utama memakai lima kolom. Tempat/tanggal lahir siswa digabung ke sel
  nama; tahun pelajaran kelas digabung ke sel kelas. Informasi tetap tersedia.
- `school-table--people`, `school-table--kelas`, dan `school-table--tahun`
  mengatur proporsi kolom, bukan lebar minimum besar yang memaksa gulir kanan.
- Aksi tabel memakai `x-ui.action-link`: ikon Lucide, label aksesibel, tooltip,
  dan tombol berukuran tetap. Pemanggil tetap wajib membungkus dengan izin
  yang sesuai. Ikon dan tooltip kini konsisten untuk seluruh role yang berhak.
- Detail memakai ringkasan identitas mendatar di atas, diikuti bagian informasi
  dan tabel selebar ruang kerja. Bagian informasi/formulir memakai
  `school-data-section` berupa bidang sederhana, bukan kartu di dalam kartu.
- Formulir mempertahankan semua nama isian, nilai lama, CSRF, unggahan, dan
  validasi. Pesan kesalahan dihubungkan ke kontrol melalui ID dan ARIA.
- Import dan ringkasan proses memakai metrik sebaris, tanpa kartu bertumpuk.

## Alur Khusus

Penempatan menampilkan ringkasan kelas tujuan di atas, daftar siswa tersedia dan
anggota dalam dua area kerja pada desktop, kemudian bertumpuk di ponsel.
Ringkasan kelas menggunakan partial yang sama pada seluruh role. Pemilihan
massal, pembatalan pilihan, data lama, kapasitas,
urutan absen, dan konfirmasi pengeluaran tetap menggunakan alur sebelumnya.
Penghitung pilihan hanya membantu tampilan, bukan pengganti validasi server.

Kenaikan tetap mempunyai tahap pemilihan asal/tujuan, pemilihan per siswa dan
konfirmasi proses. Tabelnya tetap menjadi formulir per siswa di layar kecil.

Foto identitas mempertahankan unggah otomatis, endpoint, batas berkas dan
ringkasan dinamis. Tab diberi penanda halaman aktif; ukuran teks dan ruangnya
disamakan dengan standar admin.

Kartu mempunyai satu tombol cetak utama pada admin, nama di atas pasangan
pratinjau dan tampilan depan/belakang yang tetap jelas. Bagian kartu fisik
53,98 x 85,60 mm dan SVG QR tidak diubah. Tata letak baru hanya berlaku pada
media layar. Nama tambahan tidak dicetak; wadah gulir admin dibebaskan ketika
mencetak agar kartu tidak terpotong. Ekspor tetap menggunakan elemen
`data-card-side` yang sama, tanpa menyertakan bingkai/nama tambahan.

## Verifikasi

`TampilanDataSekolahTest` mencakup seluruh halaman, keadaan kosong, pagination,
akun hanya-lihat, validasi, simpan, dan pembatasan aset. Fixture dihasilkan pada
SQLite pengujian, tidak pada database lokal NUSA.

```powershell
$env:NUSA_CAPTURE_DATA_SEKOLAH='1'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --filter=TampilanDataSekolahTest
node tests/Browser/data-sekolah.mjs
```

Browser memeriksa 39 keadaan halaman pada tujuh ukuran layar, nama panjang,
lebar tabel, gambar, pemilihan massal, dialog cetak dan ukuran fisik kartu pada
media layar/cetak. Tangkapan layar dan audit berada di
`storage/logs/data-sekolah-qa`. Semua permintaan tulis browser diblokir.
`PLAYWRIGHT_MODULE` dan `PLAYWRIGHT_CHANNEL` dapat dipasang sesuai runtime lokal.
