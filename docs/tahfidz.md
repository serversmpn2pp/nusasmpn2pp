# Tahfidz NUSA

Kelompok, pertemuan, setoran, rekap perkembangan, rapor, wisuda, dan Tahfidz Anak tersedia di web. Halaman native Android belum tersedia.

## Penggunaan

1. Administrator dapat membuka **Tahfidz** di **Akademik > Penilaian**. Untuk mendelegasikan, tambahkan peran **Koordinator Tahfidz** pada akun pegawai yang ditunjuk. Pertahankan peran pegawai/guru yang sudah dipakai akun tersebut.
2. Pilih **Tambah kelompok**, pilih tahun pelajaran dan guru pembimbing, lalu simpan. Tahun pelajaran tidak dapat diganti setelah kelompok dibuat.
3. Pada **Atur anggota**, cari nama, NIS, atau NISN dan gunakan filter tingkat/kelas. Centang siswa satu per satu atau gunakan **Pilih semua hasil**. Centang dari filter sebelumnya tetap tersimpan sampai daftar disimpan.
4. Guru yang ditunjuk melihat **Tahfidz** di **Akademik > Penilaian** tanpa perlu peran baru. Ia dapat membuka pertemuan dan mencatat setoran untuk kelompok aktif bimbingannya, tetapi tidak dapat mengubah penugasan atau daftar anggota.

Peserta tidak diisi otomatis berdasarkan tingkat. Kelompok boleh berisi siswa lintas kelas, tetapi siswa hanya boleh berada di satu kelompok aktif pada tahun pelajaran yang sama. Siswa yang dikeluarkan tetap memiliki catatan keanggotaan nonaktif. Menonaktifkan kelompok menyimpan daftar lamanya; pengaktifan kembali ditolak bila ada anggota yang sudah masuk kelompok aktif lain.

Daftar surah menggunakan nomor 78-114 sesuai [Juz 30](https://quran.com/id/juz/30), disimpan sebagai referensi lokal agar dapat dipakai tanpa layanan luar.

## Pertemuan dan setoran

1. Dari detail kelompok, pilih tanggal dan tekan **Buka pertemuan**. Kelompok hanya punya satu pertemuan per tanggal. Memilih tanggal yang sama membuka pertemuan yang sudah ada. Tanggal harus berada dalam tahun pelajaran kelompok dan tidak boleh di masa depan.
2. Pilih siswa dan surah, masukkan nilai 0-100 (maksimal dua angka desimal; koma atau titik diterima), lalu tekan **Simpan setoran**. Catatan bersifat opsional.
3. Setoran surah yang sama, baik pada pertemuan yang sama maupun berbeda, disimpan sebagai catatan baru. Pengiriman ulang formulir yang sama tidak membuat catatan rangkap.
4. Pilih **Riwayat** pada siswa untuk melihat tanggal, surah, penilai, nilai, dan catatan setoran. Riwayat lama tetap tersimpan jika keanggotaan atau pembimbing berubah. Administrator dan koordinator dapat melihat pertemuan dan riwayat; pencatatan dilakukan oleh pembimbing yang ditugaskan.

## Rekap perkembangan

1. Buka kelompok dan pilih **Rekap perkembangan** untuk melihat siswa yang belum mulai, sedang berjalan, atau sudah menyetor seluruh surah. Ringkasan menghitung anggota yang masih tercatat pada kelompok. Pencarian dan filter hanya mengubah daftar yang tampil.
2. Tekan **Rekap** pada nama siswa untuk melihat daftar Juz 30. Gunakan **Sudah disetor** atau **Belum disetor** untuk memisahkan daftar. Untuk setiap surah yang sudah disetor, rekap menampilkan jumlah setoran, tanggal, dan nilai terbaru.
3. Surah yang disetor ulang dihitung satu kali dalam progres, tetapi semua percobaan tetap masuk ke **Riwayat setoran**. Nilai 0 adalah nilai yang tercatat, bukan status belum setor. Nilai terbaru mengikuti tanggal pertemuan; jika tanggalnya sama, setoran terakhir yang dicatat ditampilkan.

Rekap memakai akses kelompok yang sama seperti pertemuan. Anggota yang sudah keluar tidak masuk ringkasan kelompok, tetapi rekap lamanya tetap bisa dibuka dari riwayat setoran kelompok asal. Tahap rekap tidak memerlukan migrasi tambahan bila Tahap 2 sudah terpasang.

## Rapor Tahfidz

1. Administrator atau Koordinator Tahfidz membuka **Akademik > Penilaian > Rapor Tahfidz > Target rapor**, memilih tahun pelajaran dan tingkat, lalu menyimpan jumlah target, tanggal rapor, dan batas predikat. Angka awal yang ditawarkan adalah 10, 20, dan 25 untuk tingkat 7, 8, dan 9; tidak berlaku sebelum disimpan.
2. Dalam mode **Surah ditentukan koordinator**, jumlah surah terpilih harus sama dengan target. Dalam mode **Siswa bebas memilih surah**, sistem mengambil nilai tertinggi sejumlah target, setelah mengambil satu nilai terbaru per surah.
3. Nilai berasal dari setoran siswa pada tahun pelajaran tersebut sampai tanggal rapor, termasuk dari kelompok sebelumnya. Jumlah nilai selalu dibagi target, bukan jumlah yang sudah disetor. Slot kosong ditulis **Belum disetor**; rapor tanpa setoran sama sekali tidak menampilkan rata-rata atau predikat.
4. Di menu **Rapor Tahfidz**, pilih kelompok lalu tekan **Cetak semua rapor**. Pratinjau gabungan berisi semua anggota yang masih tercatat pada kelompok, dengan halaman A4 baru untuk setiap siswa. Pembimbing hanya dapat membuka kelompok aktif bimbingannya.
5. Siswa dengan setoran belum lengkap tetap ikut dicetak sesuai target tingkat masing-masing. Jika kelas atau target tingkat belum tersedia, lengkapi daftar yang ditandai sebelum mencetak seluruh kelompok.
6. Pratinjau satu siswa tetap tersedia dari daftar rapor kelompok maupun rekap siswa. Cetak gabungan dan cetak satu siswa memakai pengaturan dan setoran terkini; mencetak tidak memublikasikan nilai ke akun siswa atau orang tua.

## Wisuda dan sertifikat

1. Capaian kelulusan menghitung 37 surah unik lintas kelompok dan tahun pelajaran. Tidak ada batas nilai minimum; kelulusan harus diverifikasi pembimbing pada kelompok aktif di tahun pelajaran aktif.
2. Pembimbing menekan **Verifikasi kelulusan** dari rekap siswa. Koordinator membuat acara di **Wisuda**, memilih acara untuk siswa terverifikasi, lalu menekan **Terbitkan sertifikat**.
3. Sertifikat bernomor unik dapat dicetak A4 mendatar. Nama penerima disimpan saat penerbitan dan data acara terkunci setelah ada sertifikat. Pengulangan penerbitan untuk acara yang sama tidak membuat nomor baru.

## Akun orang tua dan notifikasi

- Pembimbing memilih **Selesaikan pertemuan** setelah memeriksa setoran. Pertemuan yang selesai tidak menerima setoran baru. NUSA mengirim satu rangkuman per siswa yang menyetor kepada akun siswa aktif dan setiap akun orang tua/wali aktif yang terhubung, tanpa menggandakan notifikasi jika tombol diproses ulang. Setoran lama tidak otomatis dibagikan saat migrasi.
- Orang tua membuka **Informasi Anak > Tahfidz**, dan dapat memilih anak apabila lebih dari satu terhubung. Progres dan riwayat hanya memuat setoran dari pertemuan yang sudah selesai. Nilai dan tanggal ditampilkan tanpa catatan internal.
- Dari pratinjau rapor, administrator atau koordinator memilih **Bagikan rapor ke orang tua**. Versi yang dibagikan merupakan salinan pratinjau dan tidak berubah saat nilai atau pengaturan target berubah. Untuk memperbarui versi, periksa pratinjau lalu pilih **Bagikan ulang rapor**.
- Di **Wisuda**, sertifikat yang sudah diterbitkan masih bersifat internal sampai koordinator memilih **Bagikan ke orang tua**. Rapor dan sertifikat dapat ditarik secara terpisah; tautan dokumen orang tua tidak dapat dibuka lagi setelah ditarik.
- Rangkuman dan publikasi memakai notifikasi NUSA dan push Firebase setelah transaksi berhasil. Siswa mendapat kabar rapor/sertifikat telah dibagikan kepada orang tua, tanpa mengubah izin dokumen. Pesan layar kunci tidak memuat nama, nilai, atau catatan internal. Tautan mobile membuka Tahfidz Saya untuk siswa dan Tahfidz Anak Saya untuk wali; tautan web siswa tetap ke kotak notifikasi. Konfigurasi dan verifikasi perangkat dijelaskan di `docs/tahfidz-mobile.md`.

## Pemasangan

```bash
php artisan migrate --force
php artisan optimize:clear
```

Migrasi menyiapkan tabel Tahfidz, daftar surah, dan peran Koordinator Tahfidz. Tidak membuat kelompok, target, nilai, acara, sertifikat, atau penugasan akun nyata. Tidak memerlukan build frontend atau AAB baru.

## Pengujian

```powershell
$env:NUSA_CAPTURE_TAHFIDZ='1'
php artisan test --filter=TahfidzTest
node tests/Browser/tahfidz.mjs
```

Pengujian browser memakai HTML yang dirender oleh tes SQLite, bukan kelompok nyata. `PLAYWRIGHT_MODULE` dan `PLAYWRIGHT_CHANNEL` dapat disetel bila Playwright memakai runtime di luar proyek.
