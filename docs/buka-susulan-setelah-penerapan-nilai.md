# Buka untuk Susulan setelah nilai diterapkan

Fitur ini hanya untuk administrator CBT atau panitia yang berwenang dalam kegiatan ujian. Nilai 0 tidak otomatis memberi hak susulan.

## Pemakaian

1. Web: buka **Ujian Terpusat → Pelaksanaan → jadwal/mapel → Ketidakhadiran & ujian susulan → Buka untuk Susulan**.
2. Pilih satu siswa, isi alasan minimal 10 karakter, lalu setujui konfirmasi.
3. Siswa masuk ke daftar penjadwalan susulan. Tentukan waktu, ruang resmi, dan pengawas seperti alur susulan biasa.
4. Setelah pengerjaan dan koreksi susulan selesai, terapkan nilai kembali. Publikasikan kembali nilai akademik mapel jika sudah siap.

Pada mobile, tombol **Buka untuk Susulan** ada pada kartu peserta di **Pusat Pelaksanaan → Monitoring Peserta**. Penetapan jadwal, ruang, dan pengawas susulan masih melalui halaman web yang sudah tersedia.

## Dampak dan perlindungan

- Penerapan dibatalkan hanya untuk siswa dan komponen nilai yang dipilih; baris nilai tidak dihapus. Nilai/predikat target dikosongkan selama menunggu susulan.
- Nilai siswa lain tidak berubah. Publikasi nilai akademik mapel/semester kembali ke draf mengikuti aturan perubahan nilai NUSA; finalisasi paket ujian tidak perlu dibatalkan.
- Nilai lama, keadaan peserta sebelum pembukaan, alasan, petugas, dan waktu disimpan di `riwayat_pembukaan_susulan_cbt`. Riwayat dapat dibaca panitia melalui halaman Pelaksanaan.
- Jawaban yang sudah tersimpan tetap ada. Ikatan perangkat lama dilepas agar siswa bisa memakai HP pengganti.
- Siswa yang menunggu susulan dilewati saat penerapan nilai, termasuk peserta selesai otomatis karena waktu habis dan peserta yang sebelumnya terlanjur diterapkan.
- Nilai yang berubah setelah penerapan atau dipakai peserta hasil lain ditolak agar perubahan manual tidak tertimpa.
- Hasil lama siswa yang masih menunggu susulan tidak ditampilkan sebagai hasil final kepada siswa/orang tua.

## Pemasangan di server

Setelah kode di-push dan di-pull pada server sekolah, jalankan dari folder Laravel:

```sh
php artisan migrate --force
php artisan optimize:clear
```

Migration hanya menambahkan tabel riwayat. Jangan menggunakan `migrate:fresh` pada server sekolah. Build/install APK baru diperlukan untuk tombol mobile; fitur web dapat digunakan setelah pembaruan server. Tidak diperlukan perubahan konfigurasi Firebase.

Perubahan kode ini tidak secara otomatis membuka peserta mana pun di database server. Panitia tetap memilih dan menyetujui siswa melalui fitur tersebut.
