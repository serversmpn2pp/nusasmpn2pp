# Persuratan & Arsip TU - tahap pertama dan kedua

## Cakupan

- Arsip surat masuk: nomor surat asli, nomor agenda sekolah, tanggal surat/diterima,
  pengirim, perihal, sifat, berkas utama dan lampiran.
- Surat keluar dan surat tugas: nomor manual, tujuan, pengesah, periode/tempat/uraian
  tugas serta pilihan satu atau banyak guru/pegawai.
- Daftar arsip, pencarian nomor/perihal/pihak/pegawai, filter jenis/status/tahun/sifat.
- Pengajuan, penarikan, pengembalian, pengesahan, pencatatan pengiriman dan pembatalan.
- Notifikasi NUSA bagi pengesah, pembuat surat dan guru yang ditugaskan.
- Halaman Surat & Penugasan Saya, unduh berkas dan konfirmasi membaca.
- Revisi melalui surat baru yang ditautkan ke surat asal; riwayat metadata,
  keputusan, penerima dan berkas dipertahankan.

Tahap kedua menambahkan disposisi surat masuk, progres/laporan pelaksanaan surat
tugas, pemeriksaan laporan, bukti privat, monitoring dan pengingat tenggat.

Belum mencakup template pembuatan surat, penomoran otomatis,
atau tanda tangan elektronik tersertifikasi. API, katalog menu dan layar native
Android sudah tersedia, termasuk unggahan foto kamera/galeri untuk surat dan bukti.
Kontrak integrasinya ada di [API Persuratan](api-persuratan.md),
dan panduannya di [Persuratan Android](mobile-persuratan.md).
Halaman web responsif dapat digunakan di browser ponsel.
Arsip Humas yang sudah ada tidak dipindah atau diduplikasi secara otomatis.

## Akses

Role baru `tata_usaha` memiliki `surat.lihat`, `surat.kelola`, `surat.rahasia`
dan akses dasar beranda/profil/presensi pribadi. Tidak diberi `surat.sahkan`.
Role `pimpinan` memiliki lihat, sahkan, disposisi dan rahasia; tidak dapat mengedit draf.
Administrator memiliki akses administratif tetapi tidak melewati syarat pengesah
terpilih atau larangan pengesahan surat yang dibuatnya sendiri.

Untuk petugas TU yang sudah memiliki akun pegawai, tambahkan role **Tata Usaha**
dari Akun Pegawai / Role & Hak Akses. Tidak perlu membuat akun baru. Kepala Sekolah
memakai role **Pimpinan**. Jika perlu pejabat pengganti, berikan izin pengesahan
secara eksplisit dan pilih akun tersebut pada surat.

Penerima tugas harus pegawai aktif dengan akun pegawai NUSA aktif. Guru biasa
tidak memiliki akses arsip; hanya surat tugas yang pernah disahkan dan memiliki
penugasan terkait `pegawai_id` miliknya. Akun siswa/orang tua tetap ditolak walau
keliru diberi role pegawai/TU. Surat rahasia memerlukan izin tambahan pada arsip
dan pengesah; penerima yang sah tetap bisa membuka surat tugasnya sendiri.

## Alur dan nomor

Surat masuk langsung berstatus **Tercatat**. Surat keluar/tugas:
**Draf -> Menunggu pengesahan -> Disahkan -> Dikirim**. Pengiriman terakhir adalah
catatan TU tentang penyerahan/email, bukan pengiriman email/WhatsApp otomatis.
Notifikasi guru sudah dikirim saat pengesahan, tidak menunggu tombol Dikirim.

Nomor keluar dan nomor surat tugas berbagi ruang nomor unik per tahun tanggal surat.
Nomor masuk unik per pengirim + tahun tanggal surat + nomor asli; instansi berbeda
dapat memiliki nomor asli sama. Nomor agenda unik per **tahun tanggal diterima**.
Normalisasi spasi dan huruf besar serta indeks unik database mencegah nomor ganda.
Nomor surat yang dibatalkan tetap tercatat dan tidak dapat dipakai ulang.

Surat dikunci sejak diajukan. TU dapat menarik pengajuan atau pengesah mengembalikan
surat dengan alasan. Surat yang pernah disahkan tidak dapat diedit. Buat revisi
dengan nomor baru, berkas final baru, dan pengesahan ulang. Surat asal tetap berlaku
sampai revisi disahkan; pada saat itu surat asal dibatalkan atomik, penerima lama
diberi notifikasi pembatalan, dan penerima revisi diberi notifikasi penugasan.
Revisi lanjutan dibuat dari revisi terakhir, bukan dari surat asal yang sudah diganti.
Pembatalan setelah pengesahan hanya oleh pengesah yang ditunjuk (atau administrator).

Pengesahan ini persetujuan administratif di NUSA, bukan TTE tersertifikasi. Pejabat
harus memeriksa berkas final sesuai prosedur tanda tangan sekolah. Sistem tidak
menempelkan gambar tanda tangan ataupun menganggap berkas otomatis sah secara hukum.

## Berkas, riwayat, dan notifikasi

Berkas utama PDF/JPG/PNG, maksimal 10 MB. Lampiran maksimal 5 berkas aktif,
10 MB per berkas: PDF, gambar, dokumen Office tanpa ekstensi makro. Batas POST/upload
PHP dan web server tetap perlu cukup untuk jumlah berkas yang diunggah.
Berkas disimpan privat dalam `storage/app/private/persuratan`, bukan public/storage.
Unduhan memeriksa izin, kepemilikan surat, batas direktori, dan SHA-256. Guru tidak
dapat mengunduh berkas draf, surat orang lain, atau versi berkas yang sudah diganti.
File lama tetap tersimpan untuk audit TU; unggahan gagal dibersihkan.

Setiap perubahan dan keputusan membutuhkan versi terbaru. Keputusan berulang atau
formulir lama ditolak, dan notifikasi memakai kunci unik. Penerima notifikasi adalah
akun aktif; ringkasan push tidak memuat perihal, nomor, atau catatan rahasia.
Notifikasi tersimpan bersama transaksi surat. Push memakai queue worker yang sudah
ada dan hanya diantrekan sesudah commit jika Firebase push aktif. Push nyata belum
dikirim dalam pengujian lokal; tidak ada jaminan perangkat selalu menerima push.
Status **Sudah membaca** memerlukan tindakan guru, bukan sekadar sukses antre push
atau membuka notifikasi. Status membaca bukan bukti tugas telah dilaksanakan.

Backup database PostgreSQL yang mengambil seluruh schema otomatis mencakup delapan
tabel baru. **Dump database tidak berisi berkas unggahan**: cadangkan juga direktori
privat `persuratan` dan `persuratan-tindak-lanjut`, termasuk berkas lama,
bersama penyimpanan privat lain.

## Disposisi dan tindak lanjut

- Kepala Sekolah / pejabat dengan `surat.disposisi` membuka surat masuk, memilih
  penerima, instruksi dan tenggat. Pemberi disposisi menjadi pemeriksa hasil.
- Satu penerima memiliki satu tindak lanjut per surat. Penerima lain dapat ditambah
  kemudian. Surat masuk dikunci setelah ada disposisi, termasuk setelah dibatalkan;
  koreksi instruksi menggunakan pengaturan tindak lanjut, bukan mengganti surat diam-diam.
- Saat surat tugas disahkan, setiap penerima otomatis memiliki pelaporan. Tenggat
  awal adalah tanggal selesai tugas, pemeriksa awal adalah pengesah surat.
- Migrasi tahap kedua mengikutkan surat tugas lama yang masih disahkan/dikirim.
  Tidak mengirim ulang notifikasi pengesahan. Pengingat tenggatnya dapat dikirim
  oleh jadwal berikutnya jika sudah jatuh tempo; surat lama yang dibatalkan tidak diikutkan.
- Penerima membuka **Tindak Lanjut Surat Saya**, menandai sudah membaca, mulai
  mengerjakan, menyimpan progres atau mengajukan laporan. Membaca tidak berarti selesai.
- **Belum mulai -> Diproses -> Menunggu pemeriksaan -> Selesai**, atau
  **Menunggu pemeriksaan -> Perlu perbaikan -> Menunggu pemeriksaan**.
  Tidak ada penyelesaian otomatis saat tenggat habis; laporan terlambat tetap dapat diajukan.
- Pejabat pemeriksa yang ditunjuk (atau administrator berwenang) menerima atau
  mengembalikan laporan. TU hanya memantau; bukan otomatis menjadi pemeriksa.
  Akun/pegawai penerima tidak boleh menerima laporannya sendiri, termasuk lewat akun lain.
- Pemeriksa dapat memperbarui instruksi/tenggat/pemeriksa dengan alasan sebelum
  laporan diajukan. Pemeriksa aktif yang lain dapat dipilih, termasuk jika penerima
  juga pengesah surat tugasnya sendiri. Administrator dapat menangani penggantian
  pemeriksa yang sudah nonaktif/dicabut izinnya. Surat asli tetap tidak diubah.
- Tugas selesai tidak dapat dibatalkan atau dibuka kembali melalui pengaturan ini.
  Pembatalan/revisi surat induk membatalkan tugas aktif; laporan yang sudah selesai
  tetap tersimpan sebagai riwayat dengan penanda bahwa surat induknya dibatalkan.
- Setiap simpan progres/pengajuan membuat versi laporan baru. Bukti versi terakhir
  dapat disertakan kembali atau dilepas dari versi baru; versi lama tidak dihapus.
  Maksimal 5 berkas per laporan, 10 MB per berkas, jenis sama dengan lampiran surat.
- Penerima hanya melihat tugas, laporan dan bukti miliknya, termasuk disposisi
  rahasia yang ditujukan kepadanya. Akses arsip/monitoring rahasia tetap memerlukan
  `surat.rahasia`. Seluruh bukti disimpan privat dan diperiksa SHA-256 saat unduhan/penerimaan.

Menu **Monitoring Tindak Lanjut Surat** memiliki pencarian, filter jenis/status,
melewati tenggat dan menunggu pemeriksaan saya. Jumlah ringkasan mengikuti filter.
Status surat (tercatat/disahkan/dikirim) terpisah dari status pelaksanaannya.

Notifikasi diberikan saat disposisi, pengajuan laporan, perubahan instruksi,
penerimaan/perbaikan/pembatalan. Pengingat otomatis pukul **07.15**, mulai H-2 dan
satu pengingat lagi setelah lewat tenggat, bukan pesan baru setiap hari.
Laporan yang sudah diajukan diingatkan ke pemeriksa, bukan menagih guru kembali.
Kunci pengingat mengikuti tugas, tenggat dan versi laporan; menjalankan perintah
berulang tidak menggandakan notifikasi yang sama. Tidak ada email/WhatsApp otomatis.

```powershell
php artisan surat:ingatkan-tindak-lanjut
```

## Deployment

Jalankan migrasi fitur ini saja jika belum ingin menjalankan migrasi lain yang pending:

```powershell
php artisan migrate --path=database/migrations/2026_10_12_000001_buat_persuratan_tu.php --force
php artisan migrate --path=database/migrations/2026_10_12_000002_buat_tindak_lanjut_surat_tu.php --force
php artisan optimize:clear
php artisan queue:restart
```

Pastikan worker notifikasi tetap berjalan, storage dapat ditulis, role TU ditambahkan
ke akun yang tepat, dan akun Kepala Sekolah aktif serta memiliki izin pengesahan.
Tidak perlu menjalankan ulang seeder penuh pada server yang sudah berisi data.
Scheduler NSSM yang sudah menjalankan `schedule:work` tetap digunakan; tidak perlu
membuat layanan baru. Pastikan `php artisan schedule:list` memuat pengingat surat
pukul 07.15 dan queue worker hidup. `queue:restart` membuat worker memuat kode terbaru.

Halaman: `/persuratan`, `/persuratan/pengesahan`, `/surat-saya`,
`/persuratan/tindak-lanjut`, `/tindak-lanjut-surat`.

## Verifikasi

Pengujian pada database SQLite sementara: arsip/nomor, identitas dan role, surat
rahasia, berkas privat dan integritas, status/versi/rollback, pengesahan terpilih,
notifikasi tanpa penggandaan, revisi/pembatalan, membaca idempoten, rendering/filter
dan escape XSS. Tidak mengirim notifikasi ke guru sebenarnya.

```powershell
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/PersuratanTest.php
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/TindakLanjutSuratTest.php
```
