# Audit anti-kecurangan ujian NUSA Mobile — 23 Oktober 2026

## Status tindak lanjut

Pada 23 Oktober 2026, perbaikan lokal untuk **NUSA-EXAM-02** disiapkan di backend
beserta pengujian regresi. Lihat
[rincian perbaikan dan panduan penerapan](perbaikan-keamanan-menjodohkan-cbt.md).
Belum diterapkan ke produksi. Temuan lain belum diperbaiki. Isi audit di bawah
tetap mencatat kondisi awal pada HEAD `70d1e24`, bukan hasil audit ulang server.

## Kesimpulan

Fondasi kontrol akses dan integritas waktu/jawaban sudah ada, tetapi Mode Aman
belum dapat dijadikan satu-satunya pengamanan ujian berisiko tinggi. Ditemukan
**3 temuan prioritas tinggi (P1)** dan **2 temuan prioritas sedang (P2)** yang
dibuktikan dengan pengujian API/widget lokal. Pengecualian gangguan koneksi dan
pemilih berkas harus tetap membedakan gangguan teknis dari bukti kecurangan;
rekomendasi audit bukan menghukum siswa otomatis saat internet bermasalah.

Audit ini tidak memperbaiki kode aplikasi, tidak membuat AAB baru, tidak
mengubah konfigurasi ujian, dan tidak mengakses/mengubah data produksi.

## Ruang lingkup dan keterbatasan

- Kode lokal pada HEAD `70d1e24`, versi Flutter **1.0.3+4**, package
  `id.sch.smpn2padangpanjang.nusa`.
- Pelaksanaan siswa menggunakan `StudentExamView`, jurnal kejadian, kanal native
  Android, API Ujian Saya, middleware sesi, layanan Mode Aman, pengacakan soal,
  batas waktu, dan kewenangan pengawas. Asesmen kelas dan ujian terpusat memakai
  alur pengerjaan siswa yang sama.
- Pemeriksaan statis disertai uji lokal memakai akun/soal sintetis dan SQLite
  `:memory:`. Pembuktian antarmuka memakai Flutter widget tests, bukan tindakan
  pada HP siswa. Tidak ada permintaan serangan ke API sekolah yang sedang hidup.
- Tidak membuktikan kondisi fisik perangkat/OEM, sistem operasi yang dimodifikasi,
  aplikasi keyboard/accessibility pihak lain, atau seluruh konfigurasi produksi.
  Kesamaan kode/migrasi/pengaturan server produksi dengan repository belum diuji.
- Ini audit terarah anti-kecurangan, bukan sertifikasi/pentest menyeluruh seluruh
  NUSA. Pengujian yang lulus tidak berarti semua risiko keamanan sudah hilang.

## Ringkasan temuan

| ID | Prioritas | Temuan | Pembuktian |
| --- | --- | --- | --- |
| NUSA-EXAM-01 | P1 — tinggi | Simpan & Keluar menghentikan pengawasan tanpa merekam keluar | Widget: keluar, background 30 detik, buka ulang; tidak ada `keluar`, hanya pemulihan |
| NUSA-EXAM-02 | P1 — tinggi | Pasangan jawaban menjodohkan dapat disimpulkan dari pengacakan deterministik publik | API: pasangan benar direkonstruksi dari respons peserta pada kedua nilai `acak_jawaban` |
| NUSA-EXAM-03 | P1 — tinggi | Klaim retry/pemulihan dari client dapat meniadakan hitungan pelanggaran | API: tiga rangkaian tetap 0 kejadian dan jawaban tetap diterima |
| NUSA-EXAM-04 | P2 — sedang | Selama pemilih berkas terbuka, seluruh background menjadi pengecualian | Widget: 40 detik di background hanya menghasilkan catatan pemulihan |
| NUSA-EXAM-05 | P2 — sedang | Proteksi native gagal tetapi soal tetap ditampilkan | Widget: platform melempar error secure-screen; pertanyaan tetap terlihat |

## NUSA-EXAM-01 — Keluar dari pengerjaan melepas pengawasan

**Lokasi:**
[student_exam_view.dart:967](D:/nusasmpn2pp/mobile/lib/features/student_exam/presentation/student_exam_view.dart:967),
[dispose:86](D:/nusasmpn2pp/mobile/lib/features/student_exam/presentation/student_exam_view.dart:86).

`_confirmLeave()` menyimpan jawaban, mengizinkan navigasi keluar, lalu memanggil
`context.pop()`. Tidak ada pemanggilan pencatatan `keluar` atau permintaan izin
pengawas. Saat halaman dilepas, observer lifecycle dan timer heartbeat berhenti,
serta proteksi layar dilepas. Karena siswa masih berada di NUSA saat berpindah
halaman, peralihan tersebut sendiri tidak menghasilkan lifecycle background.

Pembuktian widget: tombol Simpan & Keluar mengembalikan siswa ke halaman induk,
melepas proteksi, lalu perpindahan background selama 30 detik tidak menghasilkan
kejadian `keluar`. Saat ujian dibuka ulang, jurnal hanya menghasilkan `pemulihan`,
yang diperlakukan server sebagai peninjauan, bukan tambahan kejadian. Perilaku
API untuk pemulihan tersebut diuji secara terpisah. Ini dapat dilakukan melalui
antarmuka normal; tidak memerlukan root, APK modifikasi, atau manipulasi API.

**Dampak:** siswa bisa berhenti sejenak untuk mencari bantuan dan melanjutkan,
tanpa mencapai ambang tiga kejadian keluar aplikasi. Timer server tetap berjalan.

**Rekomendasi:** pada ujian ketat, blokir keluar biasa atau jadikan izin keluar
pengawas sebagai transisi sesi di server. Terapkan pencatatan pada setiap
perpindahan keluar layar pengerjaan, termasuk navigasi internal/notifikasi,
bukan hanya callback lifecycle. Jangan hanya menambah dialog peringatan.

## NUSA-EXAM-02 — Pengacakan menjodohkan mengungkap relasi jawaban

**Lokasi:**
[PengacakPenyajianCbt.php:104](D:/nusasmpn2pp/app/Services/Cbt/PengacakPenyajianCbt.php:104),
[UjianSayaMobileService.php:503](D:/nusasmpn2pp/app/Services/Mobile/UjianSayaMobileService.php:503).

Opsi kanan dibentuk dari identitas `pasangan-<nomor>` dan `pengecoh-<urutan>`,
lalu diurutkan memakai SHA-256 atas konstanta dan ID ujian/peserta/soal. Semua
input untuk pengurutan ini tersedia atau dapat diturunkan dari respons peserta;
tidak ada seed rahasia server. Nomor pasangan kiri juga dikirim kepada peserta.

Meskipun field `kunci_jawaban` tidak dikirim, urutan ini dapat dihitung kembali
untuk menyimpulkan opsi kanan milik setiap nomor kiri. Pembuktian API memakai
dua pasangan dan satu pengecoh unik dengan teks sintetis tanpa makna semantik.
Pasangan benar berhasil direkonstruksi pada mode acak aktif maupun nonaktif,
hanya dengan respons peserta dan algoritme publik. Pengetahuan semantik materi
atau akses ke field kunci tidak diperlukan untuk kasus ini.

**Dampak:** kebocoran relasi jawaban pada jenis soal menjodohkan. Ini bukan
klaim bahwa seluruh kunci pilihan ganda/isian sudah bocor, dan pembuktian ini
tidak menggeneralisasi kasus data duplikat yang tidak diuji. Karena layanan
pengacakan juga dipakai web, area perbaikan perlu mencakup kedua saluran.

**Rekomendasi:** gunakan pengacakan dengan seed acak yang disimpan privat di
server, atau HMAC dengan secret server dan pemetaan stabil. Jangan mengirim
identitas opsi yang mengodekan pasangan benar. Hasil harus tetap konsisten
saat melanjutkan ujian/autosave dan dinilai terhadap pemetaan yang sama.

## NUSA-EXAM-03 — Metadata pemulihan dipercaya sebagai pengecualian sanksi

**Lokasi:**
[KeamananUjianService.php:89](D:/nusasmpn2pp/app/Services/Cbt/KeamananUjianService.php:89),
[UjianSayaController.php:153](D:/nusasmpn2pp/app/Http/Controllers/Api/V1/UjianSayaController.php:153),
[exam_security_journal.dart:146](D:/nusasmpn2pp/mobile/lib/features/student_exam/data/exam_security_journal.dart:146).

Server memperlakukan `dikirim_ulang=true` atau peristiwa `pemulihan` sebagai
peninjauan, kemudian keluar dari jalur hitungan kejadian. Pada pemulihan, kejadian
keluar yang belum ditutup juga ditutup dengan `dihitung=false`. Nilai ini diterima
dari payload client, tanpa bukti server bahwa request memang retry atau terjadi
gangguan jaringan sebelumnya. Mekanisme UUID sudah mencegah double counting,
tetapi belum membuktikan keaslian klaim gangguan.

Pembuktian API:

- Kontrol: tiga pasangan keluar/kembali normal, masing-masing 5 detik,
  menghasilkan 3 kejadian dan status `terblokir`.
- Tiga pasangan dengan UUID baru namun penanda retry dari client menghasilkan
  0 kejadian, 6 catatan peninjauan, status masih `sedang_mengerjakan`, dan server
  masih menerima jawaban.
- Tiga kejadian keluar yang benar-benar telah dicatat server dapat ditutup
  melalui peristiwa pemulihan tanpa sanksi, meskipun tidak ada gangguan yang
  dibuktikan pada fixture.

Di aplikasi standar, antrean yang tertunda lebih dari 30 detik atau pernah
dicoba kirim juga diberi penanda retry. Karena itu, risiko tidak hanya berasal
dari client yang sengaja dimodifikasi; pemutusan koneksi yang disengaja dapat
bercampur dengan gangguan jaringan yang sah. Catatannya **tidak hilang**, tetapi
sanksi otomatis ambang tiga kejadian tidak diterapkan pada rangkaian tersebut.

**Rekomendasi:** pisahkan deduplikasi dari keputusan sanksi. Tentukan state sesi
dan bukti kontinuitas di server; jangan jadikan klaim client sebagai izin otomatis
untuk melanjutkan. Untuk sesi yang tidak dapat diverifikasi, pertimbangkan jeda
terkontrol/perlu persetujuan pengawas, bukan menyatakan siswa curang atau memotong
nilai secara otomatis akibat jaringan buruk.

## NUSA-EXAM-04 — Pengecualian pemilih berkas terlalu luas

**Lokasi:**
[student_exam_view.dart:67](D:/nusasmpn2pp/mobile/lib/features/student_exam/presentation/student_exam_view.dart:67),
[pemilihan berkas:695](D:/nusasmpn2pp/mobile/lib/features/student_exam/presentation/student_exam_view.dart:695).

Selama `_pickingAnswerFile=true`, perubahan lifecycle non-resumed hanya menyalakan
penanda peninjauan. Heartbeat juga dihentikan. Tidak ada batas durasi untuk
pengecualian ini maupun pembuktian bahwa seluruh waktu di luar NUSA hanya dipakai
di pemilih sistem. Widget test mengonfirmasi background 40 detik selama picker
terbuka tidak menghasilkan kejadian `keluar`, dan penutupan picker menghasilkan
catatan `pemulihan` dengan pemicu pemilih berkas.

**Dampak:** pada ujian yang memiliki soal unggahan, aktivitas lain ketika picker
masih terbuka tidak dibedakan dari pemilihan berkas yang sah. Jalur fisik membuka
aplikasi lain dari picker perlu diuji lagi pada Android/OEM sasaran; audit ini
membuktikan logika pengecualian, bukan seluruh perilaku picker vendor.

**Rekomendasi:** buat state/batas waktu unggahan yang disetujui server. Jika
pengecualian melebihi kebijakan, kembalikan lewat pemeriksaan pengawas. Untuk
ujian sangat ketat, pertimbangkan mengumpulkan lampiran dalam sesi terpisah.

## NUSA-EXAM-05 — Gagal proteksi layar diperlakukan fail-open

**Lokasi:**
[student_exam_view.dart:671](D:/nusasmpn2pp/mobile/lib/features/student_exam/presentation/student_exam_view.dart:671),
[exam_security_platform.dart:27](D:/nusasmpn2pp/mobile/lib/features/student_exam/data/exam_security_platform.dart:27).

`_applyPlatformSecurity()` menangkap seluruh error lalu melanjutkan tanpa
memblokir ujian atau memberi peringatan. Widget test dengan error native
`SECURE_SCREEN_FAILED` mengonfirmasi pertanyaan tetap ditampilkan. Ini kondisi
ketika API proteksi gagal; bukan klaim bahwa FLAG_SECURE selalu gagal di HP normal.

**Rekomendasi:** lakukan preflight proteksi sebelum menampilkan soal, tampilkan
status kemampuan perangkat, dan tentukan kebijakan fail-closed untuk ujian ketat.
Kirim kegagalan sebagai status teknis bagi pengawas, bukan pelanggaran siswa.

## Kontrol yang sudah baik pada bagian yang diperiksa

- Server memeriksa siswa/pemilik peserta; peserta lain tidak hanya disembunyikan
  di UI, tetapi ditolak oleh API.
- Pembatasan sesi memakai token Sanctum/identitas sesi web, bukan sekadar nama
  perangkat yang bisa diganti. Token berbeda serta perpindahan web/mobile ditolak
  ketika ikatan aktif. Middleware memakai transaksi dan kunci baris peserta.
- Penahanan Mode Aman disimpan server; jawaban saat berstatus tertahan ditolak.
  Tiga kejadian yang diproses normal benar-benar memblokir dalam uji kontrol.
- Batas waktu dan penolakan jawaban terlambat dihitung server. Mengubah jam HP
  tidak mengubah tenggat server. Kehilangan heartbeat tidak membuka ikatan sesi
  untuk token lain secara otomatis.
- Skor dihitung server; respons autosave tidak mengirim skor/kunci jawaban.
  Respons pengerjaan menyaring field soal, meskipun relasi menjodohkan masih
  dapat bocor lewat temuan EXAM-02.
- UUID kejadian, secure storage, antrean berurutan, dan catatan pemulihan menjaga
  bukti saat retry/restart. Gangguan jaringan tidak langsung dianggap curang.
- Screenshot memakai FLAG_SECURE; overlay diblokir Android 12+ saat pengerjaan;
  split-screen diperiksa berkala dan soal disembunyikan ketika terdeteksi.
- Pembukaan tahanan/reset memerlukan otorisasi dan menyimpan identitas petugas.
  Reset perangkat tidak otomatis menghapus jawaban, menambah waktu, atau membuka
  tahanan Mode Aman. Dokumen jawaban disimpan privat dengan validasi jenis/ukuran.

## Batas arsitektur yang bukan janji anti-curang mutlak

**Satu token bukan bukti satu HP fisik.** Ikatan saat ini mengunci sesi autentikasi.
Probe mengonfirmasi bearer yang sama tetap diterima dengan User-Agent berbeda;
ini berbeda dari token login kedua yang sudah ditolak. Login API menerima
kredensial dan label perangkat tanpa attestation aplikasi. Pada jalur yang
diperiksa belum ditemukan Play Integrity/proof-of-possession yang mengikat
permintaan ke aplikasi asli atau kunci perangkat. Secure storage membantu
melindungi token di HP normal, tetapi bukan attestation setiap pemanggilan API.

**Fullscreen bukan kiosk.** NUSA belum menjalankan lock-task/DPC. Kiosk yang kuat
lebih tepat untuk perangkat sekolah yang dikelola, bukan menjanjikan penguncian
penuh HP pribadi. Screen pinning biasa juga tidak setara kiosk terkelola.

**Perangkat kedua/pengawasan fisik:** kamera HP kedua, bantuan orang lain,
kredensial yang dibagikan, dan bahan cetak tidak dapat dicegah mutlak oleh aplikasi
di satu HP. Keyboard/accessibility/remote-control dan OEM perlu uji terpisah.
Jangan menambah pembacaan seluruh isi aplikasi pribadi sebagai pengganti kontrol
yang terarah; pertimbangkan privasi, aksesibilitas, dan dukungan perangkat.

Dokumentasi Android menjelaskan bahwa FLAG_SECURE bukan perlindungan overlay
universal. Play Integrity dapat memberikan sinyal aplikasi asli, integritas
perangkat, dan risiko aplikasi lain yang perlu diverifikasi server; ini lapisan
tambahan, bukan jaminan tanpa kecurangan.

## Bukti pengujian lokal

| Kelompok | Hasil | Arti |
| --- | --- | --- |
| Regresi API siswa/orang tua/pengawas/asesmen/pelaksanaan | 33 tes / 450 assertion lulus | Kontrol/alur lama pada subset yang dijalankan tetap bekerja |
| Regresi widget ujian/jurnal/pengawas | 19 tes lulus | Perilaku existing sesuai ekspektasi tes lama |
| Probe API audit | 5 tes / 52 assertion lulus | Mencakup satu kontrol normal dan reproduksi perilaku tidak aman/batas sesi |
| Probe widget audit | 3 tes lulus | Mereproduksi keluar tanpa kejadian, picker, dan fail-open proteksi |

**Lulusnya probe bukan berarti celah sudah diperbaiki.** Assertion probe sengaja
memastikan perilaku rentan saat ini agar temuan bisa direproduksi. Berkas lokal
di `mobile/build/security-audit/` berisi probe PHP/Dart dan log; folder build
diabaikan Git dan bukan source aplikasi atau data produksi.

## Prioritas tindakan yang disarankan, belum dilaksanakan

1. **Sebelum memakai Mode Aman sebagai kontrol utama:** perbaiki EXAM-01, EXAM-02,
   dan EXAM-03; tambah tes regresi yang menolak jalur tersebut. Perbaikan nomor 2
   berada di backend bersama sehingga perlu diuji pula pada web.
2. Batasi pengecualian picker dan tangani kegagalan proteksi secara eksplisit;
   tampilkan catatan peninjauan/kegagalan kepada pengawas dengan aksi yang jelas.
3. Tambahkan profil ujian ketat yang mensyaratkan konfigurasi nyata: pembatasan
   sesi, deteksi keluar, tindakan tahan, ambang kejadian, proteksi layar, serta
   pengacakan. Angka 3 tidak berlaku otomatis bila tindakan masih `catat` atau
   deteksi keluar dimatikan. Audit belum membaca pengaturan ujian produksi.
4. Pertimbangkan Play Integrity dan pengikatan permintaan sensitif ke sesi/kunci
   perangkat. Tetapkan kebijakan khusus saluran web; jangan menganggap menambah
   attestation mobile saja sudah melindungi semua pintu masuk ujian.
5. Uji lapangan dengan akun dummy pada beberapa HP Android: navigasi keluar,
   offline/retry, force-stop, picker ke aplikasi lain, split-screen/overlay,
   screenshot/rekaman, keyboard, dan reset pengawas. Untuk perangkat sekolah,
   evaluasi DPC/lock-task disertai SOP pengawasan dan penanganan gangguan.
6. Setelah perbaikan disetujui dan diterapkan, deploy backend/migrasi yang memang
   diperlukan serta bangun AAB versi baru. Audit ini sendiri tidak mengubah rilis
   atau track closed testing.

## Referensi utama

- [Panduan pengamanan NUSA yang sudah ada](D:/nusasmpn2pp/docs/pengamanan-ujian-web-mobile.md)
- [SesiPengerjaanUjianService.php](D:/nusasmpn2pp/app/Services/Cbt/SesiPengerjaanUjianService.php)
- [Middleware sesi ujian](D:/nusasmpn2pp/app/Http/Middleware/SesiPengerjaanUjian.php)
- [Batas waktu server](D:/nusasmpn2pp/app/Services/Cbt/BatasWaktuPesertaUjianCbtService.php)
- [Login API](D:/nusasmpn2pp/app/Http/Controllers/Api/V1/AutentikasiController.php:18)
- [Android: secure sensitive activities](https://developer.android.com/security/fraud-prevention/activities)
- [Android: Play Integrity overview](https://developer.android.com/google/play/integrity/overview)
- [Android Enterprise: lock task mode](https://developer.android.com/work/dpc/dedicated-devices/lock-task-mode)
