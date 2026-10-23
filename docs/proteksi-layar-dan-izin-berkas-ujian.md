# Proteksi layar dan izin pemilih berkas ujian — 23 Oktober 2026

Paket lanjutan 24 Oktober: [nol toleransi dan draft jawaban](nol-toleransi-dan-draft-jawaban-mobile.md).
Hasil uji di dokumen ini mencatat paket 23 Oktober, bukan angka regresi terbaru.

Paket ini meneruskan perbaikan Mode Aman dan Simpan & Keluar. Berlaku untuk
pengerjaan siswa mobile pada ujian terpusat maupun asesmen kelas. Urutan kerja:
pemeriksaan proteksi layar dahulu, lalu pembatasan pengecualian pemilih berkas.
Kode disiapkan lokal; belum diterapkan ke server atau dibangun menjadi AAB baru.

## Proteksi layar gagal tertutup

- Kanal Android mengembalikan status berprotokol setelah operasi UI thread
  selesai, bukan respons kosong sebelum pemasangan flag selesai.
- `FLAG_SECURE` dibaca kembali dari atribut window. Android 12/API 31 ke atas
  harus berhasil menjalankan `setHideOverlayWindows`; Android 24–30 tetap
  memeriksa flag secure tanpa mengklaim punya perlindungan overlay API 31.
- Sebelum meminta mulai/lanjut ujian, aplikasi memasang dan memeriksa proteksi
  sesuai konfigurasi ujian. Kanal hilang, respons lama/malformed, flag tidak
  aktif, exception atau timeout tidak dianggap sukses. Permintaan mulai tidak
  dikirim bila pemeriksaan awal gagal.
- Soal tersembunyi sampai proteksi siap, termasuk setelah aplikasi kembali dari
  background dan pemilih berkas. Soal juga tidak ditampilkan dalam split-screen.
  Pemeriksaan berkala dilakukan setiap tiga detik saat foreground, tanpa
  menumpuk pemeriksaan native atau menerima hasil pemeriksaan generasi lama.
- Jika proteksi gagal selama pengerjaan, aplikasi mencatat `pemulihan` dengan
  pemicu `proteksi-layar-gagal`. Mode `tahan` memerlukan peninjauan pengawas,
  **tanpa menambah jumlah pelanggaran**. Mode `catat` tidak otomatis ditahan
  server, tetapi client tetap tidak membuka soal sebelum proteksi yang diminta
  dapat dikonfirmasi. Ada tombol Periksa Lagi dan petunjuk menghubungi pengawas.
- Tidak mengaktifkan semua opsi ujian secara diam-diam. Ujian tanpa pengamanan
  tetap mengikuti pengaturan sebelumnya.

Status ini bukan attestation perangkat. Laporan overlay berarti panggilan API
berhasil; bukan pembuktian semua overlay/rekaman di seluruh OEM dapat dicegah.
Fullscreen tetap best effort, bukan kiosk/lock-task, dan bukan komponen status
yang terverifikasi. Android API 36 membatasi mode tampilan aplikasi.

Referensi platform: [Android fraud-prevention activities](https://developer.android.com/security/fraud-prevention/activities),
[FLAG_SECURE](https://developer.android.com/reference/android/view/WindowManager.LayoutParams#FLAG_SECURE),
[setHideOverlayWindows](https://developer.android.com/reference/android/view/Window#setHideOverlayWindows(boolean)).

## Izin berkas dibatasi server

Client tidak lagi mengecualikan seluruh perubahan lifecycle hanya berdasarkan
boolean “sedang memilih berkas”. Alur baru:

1. Simpan jawaban tertunda dan sinkronkan jurnal keamanan terlebih dahulu.
2. Minta izin khusus soal `upload_file` yang benar-benar termasuk paket peserta.
3. Server membuat UUID izin, terikat peserta, soal, dan identitas token Sanctum
   yang terautentikasi. Label perangkat yang dapat diedit bukan bukti identitas.
4. Sembunyikan soal, lalu buka pemilih. Hanya perubahan lifecycle selama izin
   hidup yang mendapat pengecualian. Waktu lokal memakai Stopwatch, bukan jam HP;
   seluruh waktu perjalanan request dikurangi secara konservatif dari sisa izin.
5. Tutup izin untuk hasil dipilih, dibatalkan, atau gagal. Setelah mendapat
   konfirmasi server, periksa lagi proteksi layar, lalu unggah bila masih sah.
   **Upload jaringan itu sendiri tidak mendapat pengecualian pindah aplikasi.**

Baku konfigurasi pada `config/keamanan_ujian_mobile.php`:

- `izin_berkas_detik`: **60 detik** (dibatasi kode antara 10–90 detik, lalu
  dipotong lagi oleh deadline ujian).
- `izin_berkas_per_soal`: **3 izin per soal** sejak keputusan terakhir Buka
  Mode Aman/reset perangkat oleh petugas. Pembatalan tetap memakai satu izin;
  tidak ada pembaruan jendela tanpa batas dari siswa.

Perubahan batas ini memerlukan review kebijakan sekolah dan refresh cache
konfigurasi. Jangan memperpanjang tanpa mempertimbangkan risiko pengecualian.

### API

Keduanya memakai autentikasi siswa, pemeriksaan kepemilikan dan middleware
ikatan sesi, serta lock peserta sepanjang transaksi:

- `POST /api/v1/ujian-saya/{peserta}/izin-berkas`:
  `soal_ujian_cbt_id`, `permintaan_id` UUID, `perangkat`.
- `POST /api/v1/ujian-saya/{peserta}/izin-berkas/selesai`:
  `izin_id` UUID, `hasil` (`dipilih`, `dibatalkan`, `gagal`, `masa_habis`),
  `perangkat`.

Respons `data` berisi `protocol: 1`, `diterima`, `mode`, `keamanan`, serta
`izin` dengan ID, ID soal, sisa detik dan batas waktu. Hash sesi/metadata internal
tidak dikirim dalam DTO siswa. Awal izin memakai 20 request/menit, penutupan 30.

Retry `permintaan_id` sama tidak membuat izin baru atau memperpanjang deadline.
Penutupan bersifat idempoten: hasil pertama yang diterima tetap berlaku.
Izin tidak dapat diambil/ditutup token login lain walaupun opsi satu perangkat
dimatikan. Keputusan ini bukan pembuktian perangkat fisik yang sama.

### Kegagalan dan pemulihan

- Satu izin terbuka per peserta. Penulisan jawaban dan upload ditolak selama
  izin masih terbuka. Jawaban/file lama tidak dihapus.
- Izin kedaluwarsa, buka ulang kertas sebelum izin ditutup, atau kuota habis
  menghasilkan catatan peninjauan. Mode ketat ditahan, **bukan ditambah
  pelanggaran otomatis**. Deadline tetap berjalan dan tetap bisa menyelesaikan
  ujian. Mode catat hanya mencatat gangguan/menolak izin tambahan.
- Saat timer lokal habis, pengecualian langsung dicabut dan aplikasi berusaha
  menutup izin `masa_habis`. Future pemilih OS tetap ditunggu; tidak dibuang agar
  siswa tidak bisa membuka pemilih kedua sementara yang pertama masih hidup.
- Jika respons awal/akhir hilang, soal tetap disembunyikan. Tombol Konfirmasi
  Kembali ke Ujian mengulangi ID yang sama. Berkas pilihan yang belum diunggah
  perlu dipilih ulang; file yang sudah tersimpan di server tetap ada.
- Heartbeat tidak dapat mencuci izin kedaluwarsa. Hanya awal izin sah dan
  penutupan tepat waktu yang dapat memperbarui kontinuitas sesi. Interval keluar
  biasa tidak dihapus oleh izin ini.
- Buka Mode Aman/reset perangkat oleh petugas menutup izin terbuka dengan jejak
  aktor, tanpa menghapus jawaban, mengulang hitungan insiden, atau menambah waktu.
  Reset perangkat tetap tidak otomatis membuka status ditahan.
- Aktivitas `pemilih_berkas` dan penyebab peninjauan terlihat di riwayat Mode
  Aman web; ringkasan peninjauan/tahanan tetap tersedia untuk pengawas mobile.

## Kompatibilitas dan penerapan

Tidak menambah migrasi, seeder, dependensi, permission Android, atau perubahan
upload key/APP_KEY. Izin memakai tabel aktivitas keamanan yang sudah ada.

Terapkan di luar ujian aktif: backend baru dahulu, refresh cache/reload proses
PHP sesuai prosedur sekolah, kemudian APK/AAB baru untuk closed testing.
Pastikan semua siswa memakai versi baru sebelum ujian. Client lama yang hanya
mengirim `pemulihan: pemilih-berkas` kini memerlukan peninjauan pada mode ketat;
client baru tidak membuka picker bila backend belum menyediakan protokol izin.
AAB 1.0.3+4 yang dibuat sebelum patch keamanan **tidak memuat perbaikan ini**.
Perubahan kanal Kotlin memerlukan rebuild penuh APK/`flutter run`, bukan hot
reload. Jangan menguji Dart baru dengan kanal native lama lalu menonaktifkan
gerbang proteksi untuk melewati pesan gagal.

## Verifikasi dan uji penerimaan

Pengujian otomatis memakai data sintetis, SQLite `:memory:`, mock platform dan
widget Flutter, bukan akun/data produksi. Kompilasi Kotlin debug memeriksa
kompatibilitas kode native, bukan keberhasilan mencegah rekaman pada HP fisik.

- Seluruh **825 pengujian Flutter lulus** pada source final.
- **118 pengujian backend lulus, 1.982 assertion** dengan SQLite `:memory:`:
  pengerjaan/penilaian web dan mobile, pengawas, asesmen kelas, ujian terpusat,
  serta regresi menjodohkan. Kasus baru mencakup izin terikat soal/token,
  deduplikasi tanpa perpanjangan, kuota/pembukaan petugas, kedaluwarsa,
  penolakan penulisan yang tidak rollback, mode catat, deadline, serta riwayat
  pengawas tanpa metadata hash sesi.
- **42 pengujian terarah** (platform, view ujian, jurnal dan router) lulus;
  termasuk timeout, kanal hilang, flag tidak aktif, resume, izin gagal/offline,
  ID retry tetap, penutupan tak terkonfirmasi, serta picker lewat batas.
- `flutter analyze --no-pub`: **tidak ada temuan**.
- `:app:compileDebugKotlin --offline`: **BUILD SUCCESSFUL**, tanpa AAB rilis.
- Pint berkas PHP yang diperiksa lulus dan `git diff --check` bersih.
- Log lokal: `mobile/build/test-security-complete.log`,
  `mobile/build/test-screen-protection.log`,
  `mobile/build/analyze-screen-protection.log`, dan
  `mobile/build/compile-screen-protection.log` (artefak build yang diabaikan Git).

Sebelum dipakai ujian, uji pada HP fisik Android 24–30 dan 31+, termasuk perangkat
sekolah yang sebenarnya:

1. Mulai/lanjut normal, screenshot/rekaman, split-screen, overlay, dan kembali
   dari background. Tidak boleh ada flash soal sebelum pemeriksaan selesai.
2. Simulasikan kanal/flag proteksi gagal: awal ujian tidak dimulai; kegagalan di
   tengah ujian menjadi peninjauan teknis tanpa penambahan insiden.
3. Pilih dan batalkan berkas dalam 60 detik; proteksi kembali sebelum soal dan
   upload. Coba keluar aplikasi ketika upload jaringan: aturan biasa berlaku.
4. Biarkan picker lebih dari 60 detik, ulangi pembukaan tiga kali, putus jaringan
   sebelum/sesudah izin, dan hilangkan respons. Tidak boleh muncul izin kedua,
   pembaruan deadline sendiri, atau soal sebelum rekonsiliasi.
5. Force-stop saat picker, buka kembali, lalu petugas berwenang memulihkan.
   Periksa jejak aktor, file/jawaban lama, penghitung dan deadline tetap benar.
6. Uji pemilih penyimpanan/cloud yang lambat dan kebutuhan aksesibilitas. Jika
   60 detik tidak memadai, tentukan batas yang disetujui sekolah, bukan memberi
   jalan pengecualian tanpa batas kepada client.

Tidak ada klaim 100% anti-kecurangan: izin tidak membuktikan siswa hanya berada
di DocumentsUI sepanjang jendelanya. Custom/root client, credential dicuri,
keyboard/accessibility dan HP/kamera kedua masih memerlukan pengawasan dan
kontrol terpisah. Tidak membaca isi/daftar aplikasi pribadi atau menambah
Play Integrity/kiosk dalam paket ini.
