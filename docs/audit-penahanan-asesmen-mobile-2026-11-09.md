# Audit penahanan asesmen web yang dikerjakan pada Android

**Tindak lanjut:** kedua temuan sudah diperbaiki dan diuji pada kode dalam
[perbaikan penahanan 9 November](perbaikan-penahanan-asesmen-mobile-2026-11-09.md).
Penerapan memerlukan migrasi backend serta distribusi AAB baru. Uraian di bawah
merupakan bukti kondisi sebelum perbaikan pada commit yang dicatat.

Tanggal: 9 November 2026. Sumber diperiksa pada HEAD `3cc83e2`, versi mobile
`1.0.7+8`. Pemicu: laporan asesmen dibuat melalui web, dikerjakan dan dimonitor
melalui Android; jumlah keluar aplikasi mencapai 3–6 tetapi peserta tetap
mengerjakan. Layar proteksi Android muncul singkat lalu soal terlihat kembali.

## Kesimpulan

Ada dua temuan prioritas tinggi: kebijakan bawaan asesmen web hanya mencatat,
dan tampilan soal tidak menunggu rekonsiliasi keamanan server saat aplikasi
kembali aktif. Keduanya direproduksi dengan data fiktif dan server tiruan/
SQLite dalam memori. Data asesmen produksi tidak dibaca atau diubah.

## NUSA-EXAM-20261109-01 — P1: asesmen web tidak menerapkan penahanan yang diharapkan

Ini ketidaksesuaian kebijakan asesmen dengan aturan sekolah yang diminta,
bukan kegagalan aritmetika penghitung keluar aplikasi.

- Form web `resources/views/asesmen-kelas-cbt/partials/form.blade.php:217`
  hanya menawarkan **Catat keluar halaman**. Tidak ada pemilihan tindakan
  catat/tahan atau batas kejadian di form tersebut.
- Validasi dan `dataUjian()` dalam
  `app/Http/Controllers/AsesmenKelasCbtController.php:146` / `:262` tidak
  menyimpan `tindakan_pindah_aplikasi` atau `batas_pindah_aplikasi`.
- Skema `database/migrations/2026_09_05_000001_bangun_mode_aman_ujian_native.php:16`
  memakai bawaan `catat`. Pembaruan kebijakan ketat dalam migrasi yang sama
  hanya menargetkan alur `terpusat`, bukan asesmen kelas.
- Form native juga memulai `_appSwitchAction = 'catat'` pada
  `mobile/lib/features/class_assessment/presentation/class_assessment_form_view.dart:40`,
  tetapi menyediakan pilihan **Tahan ujian, perlu dibuka guru**. API mobile
  menerima pilihan tersebut; fallback layanan tetap `catat` pada
  `app/Services/Mobile/AsesmenKelasMobileService.php:311`.
- `KeamananUjianService::tambahKejadian()` pada
  `app/Services/Cbt/KeamananUjianService.php:442` mengubah status menjadi
  `terblokir` hanya jika tindakan ujian adalah `tahan` dan hitungan mencapai
  batas. Nol toleransi mobile menghapus toleransi durasi kejadian; tidak
  mengubah tindakan `catat` menjadi `tahan`.

Bukti reproduksi:

```text
AUDIT web-create: detect=1, limit=3, action=catat
AUDIT catat-six: incidents=6, status=sedang_mengerjakan
```

Bahkan ketika pengujian mengirim `tindakan_pindah_aplikasi=tahan` ke endpoint
web, atribut tidak disimpan. Perilaku ini sesuai jalur pembuatan yang dilaporkan
pengguna dan menjelaskan angka kejadian pada monitoring tanpa tombol Buka Ujian.
Monitoring menampilkan tombol buka hanya untuk `status=terblokir`; Reset Perangkat
merupakan tindakan berbeda dan tidak menggantikan pembukaan tahanan.

Status konfigurasi asesmen tertentu pada server sekolah belum diverifikasi
langsung. Bukti di atas berasal dari menjalankan jalur produksi kode dengan
data uji terisolasi, bukan mengambil kesimpulan konfigurasi database dari foto.

## NUSA-EXAM-20261109-02 — P1: soal terbuka sebelum status server terkonfirmasi

Alur pada `mobile/lib/features/student_exam/presentation/student_exam_view.dart`:

1. Saat kehilangan fokus, `_screenReady` direset dan kejadian ditulis ke jurnal.
2. `_resumeWithProtection()` pada baris 1182 memulai `_handleResume()` lalu
   memeriksa proteksi native; keduanya dapat berlangsung bersamaan.
3. `_applyPlatformSecurity()` mengubah `_screenReady=true` pada baris 1254
   segera setelah pemeriksaan Android berhasil.
4. `_content()` pada baris 245 memeriksa proteksi native dan status sesi lokal,
   tetapi tidak menutup soal karena `_resuming`, `_awayReported`, atau
   `_securitySyncPending`. Sesi lokal masih `pengerjaan` selama respons server
   belum diterima.
5. Ketika sinkronisasi gagal, `_flushSecurity()` mengatur
   `_securitySyncPending=true`. `_running()` hanya menampilkan peringatan koneksi
   pada baris 493; soal tetap dirender.

Pemeriksaan native hanya mengembalikan status proteksi perangkat (`FLAG_SECURE`,
overlay, dan multi-window). Pemeriksaan tersebut tidak mengetahui apakah
pengawas sudah membuka tahanan server.

Reproduksi pertama memakai dua interval keluar/kembali normal. Pada kejadian
ketiga, server tiruan menandai sesi tertahan tetapi menunda respons. Setelah
aplikasi kembali aktif dan proteksi native berhasil, hasilnya:

```text
AUDIT pending-third-hold: departures=3, serverLocked=true, questionsVisible=true
```

Setelah respons penahanan diterima, tampilan berubah ke **Ujian sementara
ditahan** dan soal disembunyikan. Ini membatasi kesimpulan audit: yang terbukti
adalah kebocoran tampilan selama respons tertunda, bukan pembukaan otomatis
setelah aplikasi sudah menerima dan menyimpan status ditahan.

Reproduksi kedua memutus jaringan sinkronisasi keamanan setelah ujian dibuka:

```text
AUDIT offline-resume: questionsVisible=true
```

Dengan demikian, durasi keterlihatan soal bisa lebih lama daripada kedipan
layar proteksi, terutama ketika jaringan lambat atau gagal. Pemeriksaan native
yang cepat tidak boleh dipakai sebagai izin membuka kembali soal.

Audit ini membuktikan kebocoran tampilan. Tidak ditemukan bukti dari reproduksi
ini bahwa jawaban dapat diterima server ketika peserta sudah `terblokir`.
Draft lokal dan penerimaan jawaban server adalah dua hal berbeda.

## Pemeriksaan pembanding

Empat tes backend yang sudah ada lulus (95 assertion):

- Mode `tahan`: kejadian ketiga menahan sesi, penulisan jawaban ditolak,
  dan pengawas berwenang dapat membukanya.
- Nol toleransi: keluar nol detik dihitung sekali per interval/UUID.
- Interval keluar yang belum direkonsiliasi menghalangi penulisan jawaban.
- Pemulihan draft mengikuti izin pengawas dan deadline server.

Tes pembanding mode `catat` juga lulus: enam kejadian memang tetap membiarkan
sesi mengerjakan. Artinya penahanan server bekerja ketika kebijakan benar-benar
`tahan`; status deteksi saja tidak cukup.

## Urutan perbaikan yang disarankan

1. Samakan kebijakan web/API/native untuk asesmen yang harus ketat: deteksi
   aktif, tindakan tahan, batas 3. Form web harus menampilkan kebijakan efektif,
   memvalidasi, dan menyimpannya. Tinjau asesmen lama secara terarah sebelum
   mengubah konfigurasi; jangan menghapus hitungan/jawaban/riwayat atau
   mengubah seluruh ujian yang sedang berlangsung secara diam-diam.
2. Tutup tampilan soal segera pada setiap kehilangan fokus. Saat kembali,
   soal baru boleh tampil setelah proteksi native berhasil **dan** seluruh
   jurnal terkirim serta server mengonfirmasi sesi boleh mengerjakan. Respons
   tertunda/gagal tetap berada pada layar verifikasi, dengan pilihan mencoba
   kembali. Gunakan penanda generasi agar respons siklus fokus lama tidak
   membuka siklus baru.
3. Jika status ditahan sudah diterima, pertahankan sampai konfirmasi server
   setelah pembukaan sah petugas. Lindungi jawaban/draft dan waktu server.
4. Masukkan reproduksi latency/offline ke tes regresi permanen, lalu uji pada
   perangkat fisik dengan asesmen yang dibuat dari web. Perbaikan server/form
   memerlukan deploy backend; perbaikan tampilan native memerlukan AAB baru.

Sementara sebelum perbaikan, kebijakan **tahan** dapat dipilih melalui Edit
Asesmen pada Android untuk persiapan asesmen berikutnya. Ini tidak menutup
celah tampilan latency/offline dan tidak menggantikan perbaikan native.

## Bukti lokal dan batas audit

Artefak dalam `mobile/build/security-audit-2026-11-09/`:

- `student_exam_audit_repro_test.dart` dan `flutter-repro.log`: dua reproduksi
  gagal terhadap harapan soal tersembunyi, sesuai temuan.
- `AuditAsesmenWebTest.php`, `AuditUjianModeTest.php`, `backend-repro.log`:
  reproduksi web gagal menyimpan tindakan tahan; pembanding catat enam kali lulus.
- `backend-controls.log`: empat tes kontrol backend lulus.

Tes reproduksi sengaja berada di direktori build terpisah, tidak dimasukkan ke
suite rutin. Tidak ada perubahan kode fungsional, pembuatan AAB, deploy,
pengiriman notifikasi, atau perubahan data sekolah pada pekerjaan audit ini.
Pengujian tidak mencakup instalasi/rekaman trafik HP pada foto maupun akses
langsung konfigurasi produksi. Nama siswa dari foto tidak disalin ke data uji.
