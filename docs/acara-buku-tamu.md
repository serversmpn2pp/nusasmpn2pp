# Buku Tamu Acara & Pameran

## Festival Literasi IV 2026

Acara: **Festival Literasi IV Kota Padang Panjang 2026**. Lokasi: **Lapangan Banca Laweh**.
Pengisian: **29 Oktober 2026 pukul 00.00.00 sampai 31 Oktober 2026 pukul 23.59.59 WIB**, inklusif.

Setelah pull pada server:

```sh
php artisan migrate --force
php artisan optimize:clear
php artisan humas:siapkan-festival-literasi-2026
```

Perintah terakhir menyiapkan satu acara dengan kode publik stabil `fl4padangpanjang2026nusa`. Aman diulang: tidak membuat duplikat dan tidak menimpa nama, jadwal, banner, atau status yang sudah diubah petugas. Tidak mengisi respons contoh. Acara hanya menerima pengisian dalam jadwal tersebut meskipun status sudah Dibuka. Draf tidak tersedia untuk publik; Ditutup selalu menghentikan pengisian baru.

Untuk **menerapkan foto sekolah baru yang disetujui pada festival ini**, termasuk jika sebelumnya pernah mengunggah foto lain, jalankan sekali `php artisan humas:siapkan-festival-literasi-2026 --perbarui-foto`. Opsi eksplisit ini hanya mengganti pilihan gambar/fokus pada festival yang berkode tetap tersebut, tidak mengubah jadwal/status/nama, tidak membuka ulang acara yang ditutup, dan tidak menyentuh acara lain. Berkas foto lama tetap di penyimpanan privat; referensinya tercatat pada riwayat. Pengulangan saat foto/fokus sudah sama tidak menaikkan versi atau menggandakan riwayat.

Buka **Humas > Acara & Pameran**, atau **Buku Tamu Digital > Acara & Pameran**. Pilih festival untuk melihat rekap, pengaturan, pratinjau, dan poster QR. Pratinjau petugas dapat dibuka sebelum acara dimulai; tombol kirim dinonaktifkan dan tidak memberikan tiket pengiriman.

## Alamat Server dan QR

QR tidak dikunci ke suatu domain dalam kode program. Domain dasar mengikuti **APP_URL** pada server, misalnya:

```dotenv
APP_URL=https://alamat-nusa-sekolah
```

Gunakan alamat yang benar-benar dapat diakses HP pengunjung, bukan `localhost` atau alamat komputer pribadi. Setelah mengubah APP_URL, jalankan `php artisan optimize:clear`, buka ulang halaman poster dari server, lalu cetak ulang QR. Kode acara tetap sama, tetapi QR cetak lama tidak dapat mengubah domainnya sendiri. Jangan mengubah domain selama acara tanpa menyiapkan pengalihan alamat lama.

Pada pengembangan lokal saja, URL browser loopback mengikuti port request bila APP_URL masih loopback. Lingkungan produksi tetap memakai APP_URL kanonik, tidak mengikuti header Host sembarang. Poster menampilkan peringatan bila alamat QR masih localhost/127.0.0.1.

Poster tersedia A4/A5 portrait, memakai foto sekolah atau banner acara, warna NUSA, QR hitam-putih vektor dengan ruang kosong sekelilingnya, nama acara, tempat, dan tanggal. **Cetak / Simpan PDF** menggunakan dialog browser. Matikan header/footer bawaan browser dan aktifkan background graphics untuk mempertahankan warna. QR SVG juga dapat diunduh terpisah. Tidak ada logo yang menutupi modul QR.

## Gambar Acara

Tampilan buku tamu memakai foto baru SMP Negeri 2 Padang Panjang sebagai gambar bawaan khusus modul ini, tanpa mengganti gambar halaman login atau mengubah banner unggahan yang sudah tersimpan. Foto bawaan sudah dioptimalkan menjadi WebP. Acara baru dapat memakai foto bawaan atau mengunggah gambar sendiri melalui **Pengaturan acara > Gambar acara**.

- Format JPG/JPEG, PNG, WebP; maksimal **4 MB**.
- Minimal **1000 x 600 px** (lebar x tinggi); maksimal **6000 px per sisi** dan **24 megapiksel**. Batas minimal berlaku pula untuk foto portrait: lebar minimal 1000 px, tinggi minimal 600 px.
- Rekomendasi landscape **1920 x 1080 px**, atau foto portrait **1200 x 1500 px**. Kedua ukuran rekomendasi bukan rasio wajib; gambar lain tetap diterima selama memenuhi batas.
- **Foto responsif**: gambar memenuhi area header dan tepinya bisa terpotong. Fokus vertikal desktop/HP dapat diatur terpisah (0% atas, 100% bawah). Pratinjau langsung tersedia sebelum disimpan.
- **Gambar utuh**: untuk poster/banner dengan teks; seluruh gambar dipertahankan, tanpa crop atau distorsi. Ruang kosong di sisi gambar dapat muncul. Fokus tidak digunakan dalam mode ini, tetapi nilai tersimpan dipertahankan.
- Pengaturan gambar berlaku per acara dan juga dipakai poster QR; ukuran kertas/QR tidak berubah. Mengganti foto atau fokus tidak mengubah URL/QR acara.

Validasi ukuran, format dan resolusi dijalankan di browser untuk memberi pesan cepat, lalu **diperiksa ulang di server** untuk web/API. Aturan baru diterapkan saat mengunggah gambar baru; berkas lama tidak dihapus atau ditolak saat hanya mengubah pengaturan lain. Pengiriman yang gagal validasi tidak menimpa gambar tersimpan. Foto yang dipilih di browser belum diunggah sampai Simpan acara ditekan.

## Formulir Pengunjung

Pengunjung memindai QR dengan kamera HP dan membuka formulir lewat browser, tanpa akun NUSA/Google dan tanpa APK. Isian: nama/panggilan, kategori, instansi opsional, minat (boleh beberapa), kepuasan stand (tepat satu nilai 1-5), penilaian NUSA opsional, saran opsional, dan persetujuan penggunaan data untuk pendataan/evaluasi acara. Tidak meminta NIK, NISN, WhatsApp, lokasi GPS, foto pengunjung, atau identitas perangkat.

Kategori: Pelajar, Pendidik / tenaga kependidikan, Orang tua / wali, **Instansi**, dan Masyarakat umum. Penambahan Instansi berlaku juga untuk referensi API, rekap, grafik, Excel, serta cetak tanpa mengubah kategori respons lama. Nama, kategori, dan waktu kunjungan ditayangkan pada perangkat Monitoring TV Pameran terdaftar, tanpa pilihan opt-in nama terpisah; formulir menginformasikannya. Saran dan data lainnya tetap hanya untuk petugas. Halaman publik buku tamu tidak menyediakan daftar pengunjung.

`Belum mencoba / tidak menilai NUSA` disimpan sebagai null dan tidak termasuk pembagi kepuasan NUSA. Setelah berhasil, halaman terima kasih tidak menampilkan ulang identitas/saran. Tombol Pengunjung berikutnya membuat formulir baru, sehingga satu tablet dapat dipakai bergantian. Kegagalan validasi mempertahankan isian pada sesi pengguna yang sama; data tidak disimpan di localStorage.

Koneksi internet tetap diperlukan. Tidak ada antrean offline atau sinkronisasi latar belakang yang dapat menggandakan pengiriman. Jika koneksi bermasalah, petugas bisa membantu melalui tablet stand dengan jaringan yang tersedia.

## Statistik dan Laporan

### Pilihan Minat per Acara

Di **Pengaturan acara > Pilihan minat pengunjung**, petugas berizin kelola dapat menambah pilihan (maksimal 30), mengubah nama (maksimal 60 karakter), memilih ikon, mengurutkan dengan tombol panah, dan mengaktifkan/nonaktifkan pilihan. Minimal satu pilihan aktif. Urutan disimpan sesuai daftar. Lima pilihan awal tetap tersedia pada acara lama, tanpa mengubah respons yang sudah masuk.

Pilihan yang belum pernah digunakan boleh dihapus. Pilihan yang sudah digunakan, termasuk pada respons yang dibatalkan, hanya boleh dinonaktifkan; kodenya tidak boleh dihapus/diganti. Nama boleh diperbaiki dan laporan menggunakan nama terbaru untuk kode yang sama. Kode merupakan identitas pilihan, bukan nama. Nonaktif menyembunyikan pilihan dari formulir tetapi tidak menghapus hitungan lama. Mengubah pilihan tidak mengubah URL/QR acara.

Formulir publik memvalidasi pilihan aktif milik acara yang dituju kembali saat menyimpan. Pilihan yang baru dinonaktifkan ditolak untuk kiriman baru meskipun formulir dibuka sebelumnya. Pengiriman berhasil yang diulang dengan tiket/isi sama tetap idempoten walau pilihan kemudian nonaktif. Pilihan dari acara lain tidak diterima.

- Statistik hanya memakai respons **valid**, dalam rentang tanggal dan kategori terpilih. Pencarian nama, nilai kepuasan, dan status respons menyaring daftar, bukan mengubah statistik acara. Judul ringkasan secara eksplisit menyebut respons valid.
- Jumlah adalah **pengunjung/respons tercatat**, bukan bukti jumlah orang unik, hitungan scan QR, atau verifikasi kehadiran fisik. Satu orang yang membuka formulir baru dapat mengisi lagi; petugas dapat membatalkan duplikat/spam dengan alasan.
- Puas terhadap stand = `(jumlah nilai 4 dan 5 / seluruh penilaian stand valid) * 100%`. Netral tetap ada di pembagi, bukan dianggap puas.
- Kepuasan NUSA memakai pembagi hanya respons yang memberikan nilai NUSA 1-5. Tanpa penilaian, persentase tampil `-`, bukan 0%/100% fiktif.
- Grafik: kunjungan harian, pola jam kunjungan seluruh tanggal terpilih (WIB), distribusi kepuasan, dan kategori pengunjung. Minat dapat lebih dari satu sehingga jumlah minat tidak harus sama dengan jumlah pengunjung.
- Excel XLSX memuat sheet Ringkasan dan Pengunjung. Input tamu ditulis sebagai teks, bukan formula. Maksimal 10.000 respons per ekspor; cetak maksimal 1.000 per filter. Cetak laporan mengikuti filter daftar dan menyertakan ringkasan yang dijelaskan cakupannya.
- Pembatalan/pemulihan respons memerlukan izin kelola, alasan, dan versi data. Tidak menghapus respons; riwayat disimpan dan statistik disesuaikan.

## Akses dan Keamanan

Menggunakan izin yang sudah ada: `buku_tamu.lihat` untuk rekap/ekspor/cetak, `buku_tamu.kelola` untuk mengelola acara dan status respons. Administrator dan Wakil Humas mengikuti izin tersebut; pimpinan dapat membaca sesuai izin. Izin mencatat tamu operasional milik satpam/guru piket tidak memberi akses rekap pameran. Identitas siswa/orang tua tetap ditolak pada administrasi walau salah diberi role pegawai.

Formulir publik hanya membuka acara non-draf melalui kode publik. Tidak ada endpoint publik untuk daftar pengunjung, komentar, Excel, atau statistik. Banner diunggah untuk publikasi; file di penyimpanan privat diberikan melalui endpoint gambar tervalidasi. API memiliki endpoint banner berizin agar tidak bergantung pada cookie web.

Pengiriman menggunakan CSRF web, tiket terenkripsi terikat acara dan UUID kiriman (berlaku empat jam), honeypot, validasi daftar pilihan/panjang isian, dan pembatasan laju. 180 request/menit/IP pada jalur publik, ditambah 90 pengiriman/menit/IP; batas mendukung pengunjung berbagi jaringan stand, bukan satu respons per HP/IP. Tiket tidak mengandung PII.

Kunci `(acara, UUID kiriman)` unik di database. Kunci yang diulang dengan isi sama tidak menambah respons; isi berbeda ditolak. Pengiriman dan penutupan acara memakai lock acara yang sama. Status/periode diperiksa kembali saat POST, bukan hanya saat formulir dibuka. Respons berhasil yang dikirim ulang dengan tiket yang masih valid tetap dapat dikonfirmasi setelah acara ditutup tanpa menambah data.

Seluruh nama/saran ditampilkan sebagai teks ter-escape. Identitas dan komentar hanya untuk petugas berizin, tidak ada testimoni publik otomatis. Rute publik memakai no-store, noindex, frame denial dan nosniff. Uji salinan foto/banner dan CSV/XLSX dilakukan tanpa data siswa asli.

## API Administrasi

Dasar: `/api/v1/humas/acara-buku-tamu`, memakai Sanctum bearer token dengan ability `mobile`, akun aktif, dan izin yang sama seperti web.

| Metode / jalur | Kegunaan |
| --- | --- |
| GET `/` | Daftar acara, `q`, `status`, `halaman`/`page` |
| GET `/referensi` | Pilihan status/kategori/minat/nilai, UUID pembuatan, hak akses |
| POST `/` | Membuat acara; multipart untuk banner |
| GET `/{id}` | Pengaturan, rekap, daftar pengunjung berizin, paginasi |
| PATCH `/{id}` | Mengubah acara, wajib `versi` |
| POST `/{id}` | Alternatif multipart pembaruan banner, tetap wajib `versi` |
| GET `/{id}/statistik` | Statistik tanpa PII |
| GET `/{id}/banner` | Berkas gambar berizin |
| GET `/{id}/poster` | HTML poster, `ukuran=A4` atau `A5` |
| GET `/{id}/pratinjau` | HTML pratinjau tanpa pengiriman |
| GET `/{id}/qr` | SVG QR |
| GET `/{id}/export` | XLSX |
| GET `/{id}/cetak` | HTML laporan siap cetak |
| PATCH `/{id}/respon/{respon}/status` | `status`, `alasan`, `versi`; parent binding diperiksa |

Tanggal admin memakai `YYYY-MM-DDTHH:mm` WIB; batas selesai mencakup detik terakhir menit tersebut. Filter rekap: `dari`, `sampai`, `kategori`, `q`, `kepuasan`, `status_respon=valid|dibatalkan`, `page`/`halaman`. Rentang harus berada dalam tanggal acara. Semua JSON administrasi dibungkus `data`, no-store. API sudah siap; menu Android ditandai `segera_hadir` sampai layar pengelolaan native dibuat, tidak diklaim native tersedia. Formulir publik HP dan pengelolaan web responsif dapat dipakai sekarang.

`POST /` dan `PATCH /{id}` menerima `pilihan_minat` berupa daftar terurut `{kode, nama, ikon, aktif}`. Contoh: `[{"kode":"robotika","nama":"Robotika siswa","ikon":"flask-conical","aktif":true}]`. Kode unik memakai huruf kecil, angka, underscore, diawali huruf, maksimal 64 karakter. Gunakan kode stabil saat mengganti nama/urutan; buat kode baru untuk pilihan baru. Semua pilihan (termasuk nonaktif) dikirim ketika menyimpan konfigurasi. Pada multipart, kirim `pilihan_minat[0][kode]` dan seterusnya, `aktif=1|0`. Array kosong, kode/nama duplikat, ikon di luar referensi, dan penghapusan kode terpakai ditolak 422. Pembaruan tetap wajib `versi` dan memakai lock acara yang sama dengan pengiriman publik.

Jika `pilihan_minat` tidak dikirim, pengaturan saat ini dipertahankan (acara baru memakai default), untuk kompatibilitas klien lama. Referensi menyediakan `pilihan_minat_default`, `ikon_minat`, `maksimal_pilihan_minat`; `minat` lama hanya pilihan default, bukan pilihan per acara. Respons acara memuat `acara.pilihan_minat`; detail menambahkan `minat_terpakai` agar klien menonaktifkan tombol hapus. Statistik mengembalikan `minat` (kode/jumlah) dan `label_minat` (kode/nama), termasuk pilihan nonaktif.

Referensi juga menyediakan `syarat_banner`: format, batas berkas/resolusi, ukuran rekomendasi, mode dan fokus default. Create/update menerima `banner_tampilan=potong|utuh`, `banner_fokus_desktop` dan `banner_fokus_mobile` (integer 0-100), serta `banner` (multipart). Respons `acara` memuat ketiga pengaturan gambar. Field yang tidak dikirim tetap dipertahankan untuk kompatibilitas klien lama; acara baru memakai `potong`, desktop 66%, HP 74%. Pengaturan yang tidak valid ditolak 422, termasuk pengiriman langsung via API tanpa validasi browser.

## Verifikasi

```powershell
$env:NUSA_CAPTURE_ACARA='1'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/AcaraBukuTamuTest.php
$env:PLAYWRIGHT_MODULE='PATH/node_modules/playwright/index.mjs'
$env:PLAYWRIGHT_CHANNEL='msedge'
$env:PDFINFO='PATH/pdfinfo.exe'
$env:PDFTOPPM='PATH/pdftoppm.exe'
node tests/Browser/acara-buku-tamu.mjs
```

Fixture dan PDF di `storage/framework/testing/acara-buku-tamu` / `storage/logs/acara-buku-tamu` hanya data sintetis, bukan poster produksi. Browser QA mencakup empat viewport, alur formulir, radio tunggal, multi-minat, privasi setelah sukses, dialog koreksi, grafik tidak kosong, decode QR dari layar dan PDF hasil cetak, A4/A5 satu halaman serta nama acara panjang. Untuk stand, cetak poster festival dari **server yang sudah dipasang** agar domain dan kode acara benar.
