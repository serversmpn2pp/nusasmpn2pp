# Keterbacaan Akses Cepat — 27 Oktober 2026

Beranda menampilkan empat ikon Akses Cepat dalam satu baris pada ukuran teks
normal, tanpa kotak besar. Ikon Material Rounded, warna kelompok, dan ukuran
ikon 32 mengikuti tampilan asli yang disetujui. Jarak antarpintasan 8, label
berukuran dasar 14 dan dapat membungkus baris. Tidak ada pengecilan huruf otomatis
atau pemotongan nama dengan elipsis.

Beberapa pintasan memiliki label beranda yang lebih pendek:

| Kode menu | Label beranda |
| --- | --- |
| `jadwal-mengajar-saya` | Jadwal |
| `siswa-wali-saya` | Siswa Wali |
| `input-nilai` | Input Nilai |
| `laporan-siswa-wali` | Laporan Wali |
| `rekap-presensi-siswa` | Presensi Siswa |
| `pemeriksaan-pengesahan` | Periksa & Sahkan |

Nama lengkap tetap tersimpan di katalog, ditampilkan pada halaman Atur, dibacakan
oleh pembaca layar, dan muncul sebagai tooltip ketika ditekan lama. Menu lain
memakai label katalog utuh. Penyesuaian ini hanya berlaku di Akses Cepat;
label halaman tujuan dan Semua Menu tetap memakai komponen sebelumnya.

Lebar sel menyesuaikan ukuran teks perangkat. Jika empat kolom tidak cukup,
susunan menjadi dua atau satu kolom sehingga ukuran huruf tetap dihormati.
Jumlah pilihan 0–4, urutan pengguna, callback navigasi, ikon, dan warna tetap
berasal dari katalog yang sama. Tidak ada perubahan API, hak akses, atau
penyimpanan pilihan pengguna.

Verifikasi mencakup lebar 320–600 dan skala teks hingga 200%, ikon asli dan
warnanya, tujuan menu, tekan lama, pilihan ganjil/kosong, serta alur
pilih–urutkan–simpan–reset pada tes aplikasi. Contoh hasil render dari widget
produksi dengan data sintetis berada di `mobile/build/home-quick-access-icon-row.png`.

Hasil tes: 12 tes tata letak/pintasan dan 32 tes aplikasi yang mencakup
navigasi serta pengaturan menu lulus. Flutter analyzer tidak menemukan masalah.

```powershell
flutter test --no-pub test/home_quick_access_layout_test.dart test/widget_test.dart
flutter analyze --no-pub
```

Perubahan tersedia di kode mobile dan memerlukan build Android baru untuk
dipasang di HP. AAB 1.0.4+5 yang dibuat pada 25 Oktober merupakan artefak sebelum
perubahan ini dan tetap disimpan sebagai riwayat rilis.
