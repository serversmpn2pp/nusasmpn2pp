# Persuratan & Arsip Android

## Fitur

Lima menu native mengikuti izin dan identitas pegawai aktif:

- Arsip Surat: daftar/filter/paginasi, surat masuk, keluar/tugas, edit draf,
  pengajuan, penarikan, pengiriman, pembatalan dan revisi dengan nomor baru.
- Pengesahan Surat: antrean pengesah terpilih, pemeriksaan berkas,
  pengesahan dengan konfirmasi, atau pengembalian dengan catatan.
- Surat & Penugasan Saya: surat untuk penerima, unduhan privat, konfirmasi baca.
- Monitoring Tindak Lanjut Surat: filter status/jenis/terlewat tenggat/pemeriksaan
  sendiri, disposisi dan perubahan penugasan sesuai kewenangan.
- Tindak Lanjut Surat Saya: baca instruksi, mulai, simpan progres, ajukan laporan,
  unggah bukti, serta lihat hasil pemeriksaan dan riwayat versi laporan.

TU tidak memperoleh kewenangan pengesahan/disposisi hanya karena dapat mengunggah
surat. Guru hanya melihat surat/tugasnya; siswa/orang tua tidak mendapat akses.
Tombol mengikuti `hak_akses` API; server tetap memvalidasi setiap tindakan.

## Kamera Dan Dokumen

Pada Berkas utama, Lampiran baru, atau Bukti baru pilih **Kamera / Galeri / Berkas**:

1. Ambil foto surat: membuka kamera HP. Periksa foto, lalu pilih **Gunakan foto**.
2. Pilih foto dari galeri: foto juga dikonfirmasi melalui pratinjau.
3. Pilih berkas: PDF atau dokumen yang sudah dibuat di Word dan aplikasi lain.
4. Isi metadata/nomor/penerima, lalu **Simpan**. Memilih foto tidak otomatis
   mengunggah, mengesahkan surat, atau mengirim notifikasi tugas.

Berkas utama menerima PDF/JPG/JPEG/PNG. Lampiran dan bukti juga menerima
DOC/DOCX/XLS/XLSX/PPT/PPTX, bukan format makro. Maksimal 10 MB per berkas dan
5 lampiran/bukti gabungan lama-baru. Foto dibatasi 2400 x 3200 piksel dengan
kualitas 90; tetap periksa keterbacaan sebelum menggunakan foto.
Surat fisik beberapa halaman dapat memakai foto utama plus lampiran, atau PDF.
Belum ada OCR, pemotongan otomatis, atau penggabungan foto menjadi PDF.

Penolakan izin kamera/foto tidak menghapus isian; pemilih berkas tetap dapat
dipakai. Pemulihan gambar kamera setelah Android mematikan aktivitas ditawarkan
ketika kamera dibuka kembali dan tetap memerlukan konfirmasi, bukan unggah otomatis.
Pemulihan seluruh isian formulir setelah proses aplikasi mati belum tersedia.

## Privasi Dan Konflik

Unduhan memakai Bearer melalui path relatif API, bukan browser publik. Tidak ada
token dalam URL. Berkas disimpan melalui pemilih lokasi HP. Hindari menyimpan
surat rahasia ke folder bersama. Akun yang berganti tidak dapat memakai isian
akun sebelumnya. Kamera/galeri baru menghasilkan foto; tidak memberi tanda tangan
elektronik tersertifikasi dan tidak mengganti proses pengesahan.

Perubahan surat/tugas memakai versi yang sedang dibuka. Konflik tidak diatasi
dengan menimpa versi terbaru atau retry otomatis. Isian/foto tetap tersedia untuk
kesalahan validasi/koneksi selama formulir masih terbuka; periksa arsip setelah
koneksi putus karena server mungkin sudah menerima permintaan.
Revisi surat sah memerlukan nomor dan berkas utama baru; riwayat lama dipertahankan.

## Deployment Dan Pengujian

Pasang APK yang dibangun dari perubahan ini. Deploy backend lalu:

```powershell
php artisan optimize:clear
php artisan queue:restart
```

Tidak ada migrasi/layanan NSSM tambahan jika dua migrasi awal TU sudah terpasang.
Worker Firebase yang sudah ada digunakan untuk push; notifikasi dalam NUSA tetap
tersimpan. Tujuan notifikasi diarahkan ke halaman native, bukan fallback browser.

```powershell
cd mobile
flutter analyze
flutter test
flutter build apk --debug
```

Tes widget memakai repository dan kamera simulasi, menguji multipart, izin,
konflik versi, pratinjau, filter/paginasi, rute dan ukuran layar 320/390/768.
API memakai SQLite sementara, storage fake dan push nonaktif. Uji penerimaan
di HP nyata tetap perlu: izin ditolak/diizinkan, kamera/galeri, unduh, koneksi putus,
foto beberapa halaman, dan notifikasi pada akun TU/pimpinan/guru.
