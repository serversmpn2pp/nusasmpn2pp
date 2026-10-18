# Standar Tampilan Admin NUSA

Tahap 2 melanjutkan kerangka admin tahap 1. Sumber gaya bersama adalah
`public/css/admin-components.css`, dimuat sekali oleh `layouts.app` untuk
seluruh akun terautentikasi setelah perluasan lintas role. Nama asset tetap
dipertahankan untuk kompatibilitas, bukan sebagai pemeriksaan role. Sidebar dan header memakai `admin-shell.css`; grafik dan
komposisi dashboard tetap memakai stylesheet dashboard. Halaman cetak, login,
ujian layar penuh tidak menerima stylesheet ini; pemindai dan CBT memakai stylesheet mandiri.

## Warna Dan Ukuran

Gunakan token `--ui-*`, bukan warna baru pada setiap fitur:

| Kegunaan | Warna utama | Latar pendukung |
| --- | --- | --- |
| Aksi utama / informasi | `--ui-blue` #15477A | `--ui-blue-soft` |
| Berhasil / aktif | `--ui-teal` #0F766E | `--ui-teal-soft` |
| Perlu perhatian / menunggu | `--ui-yellow` #F1C40F | `--ui-yellow-soft` |
| Gagal / tindakan berisiko | `--ui-red` | `--ui-red-soft` |
| Netral | `--ui-muted` | #EEF2F5 |

Teks peringatan menggunakan `--ui-warning-text`, bukan kuning di atas putih.
Selalu sertakan label status; jangan mengandalkan warna saja. Teks utama 16px,
kontrol 16px, tombol 14px, status dan kepala tabel 13px. Judul halaman 30px
(26px pada layar kecil). Kontrol utama minimal 44px; tombol tabel ringkas 36px.
Radius kontrol 6px dan bingkai alat 8px. Jangan mengecilkan huruf untuk membuat
label panjang muat: biarkan membungkus dan gunakan grid `minmax(0, 1fr)`.

## Tombol Dan Formulir

Tetap gunakan elemen HTML native dengan kelas bersama:

```blade
<button type="submit" class="button button-primary">Simpan perubahan</button>
<a href="{{ route('tahun-pelajaran.index') }}" class="button button-muted">Batal</a>
<button type="button" class="button button-danger">Batalkan surat</button>
```

`button-dark` merupakan alias lama untuk `button-primary`. `button-success`
tersedia untuk aksi penuntasan. Gunakan `button-sm` hanya untuk aksi ringkas.
Tombol ikon menggunakan `button button-muted button-icon`, ikon Lucide,
`aria-label`, dan `data-admin-tooltip`. Jangan mengganti teks aksi penting
dengan ikon yang tidak dikenal. Semua tombol bukan pengirim formulir harus
memakai `type="button"`.

Kontrol menggunakan `input`, `select`, `textarea`, atau `file-input`. Pertahankan
`name`, `old()`, CSRF, `required`, batas angka, dan validasi server. Label selalu
terhubung dengan `for` dan `id`. Kesalahan harus terhubung ke kontrol:

```blade
<label for="nama" class="form-label">Nama</label>
<input id="nama" name="nama" class="input @error('nama') is-invalid @enderror"
    value="{{ old('nama') }}"
    @error('nama') aria-invalid="true" aria-describedby="nama-error" @enderror>
@error('nama')<p id="nama-error" class="error-text">{{ $message }}</p>@enderror
```

Gunakan `disabled` native jika kontrol tidak tersedia. `readonly` tetap dapat
dipilih/disalin. `aria-busy="true"` menampilkan indikator pemrosesan, tetapi
pemilik alur tetap harus mencegah pengiriman ganda dan memulihkan kontrol saat
gagal. Gaya tidak menonaktifkan semua formulir secara otomatis. Tautan yang
belum tersedia sebaiknya berupa `span.button[aria-disabled=true]`, tanpa URL.

## Filter Dan Tabel

Gunakan `form.panel.panel-pad.ui-filter` dengan `filter-grid` atau grid khusus
fitur. Filter berupa area alat dengan latar biru muda, bukan beberapa kartu
bersarang. Aksi Terapkan/Reset tetap berada di dalam filter. Gunakan
`ui-filter-count` untuk jumlah hasil. Metode GET, nama parameter, dan query
pagination tidak berubah. Grid filter khusus fitur tetap boleh dipertahankan.

Tabel umum memakai `employee-table`; tabel baru atau tabel fitur khusus dapat
menambahkan `ui-table` untuk memakai warna, tipografi, dan keadaan baris yang
sama. Gunakan `th scope="col"`, label aksesibel/caption dan pembungkus
`table-wrap`. Untuk tabel yang perlu gulir, gunakan `tabindex="0"`,
`role="region"`, dan `aria-label` pada pembungkus. Jangan menghapus kolom atau
logika sortir/pagination hanya demi tampilan. Tampilan daftar seluler khusus
fitur tetap dipertahankan. `ui-table-shell` menyediakan bingkai tabel baru.

## Status, Pesan, Dan Kondisi Kosong

```blade
<x-ui.status type="success">Disahkan</x-ui.status>
<x-ui.status type="warning">Menunggu pengesahan</x-ui.status>
<x-ui.alert type="success" title="Perubahan tersimpan.">Data berhasil diperbarui.</x-ui.alert>
<x-ui.alert type="danger" title="Data belum dapat disimpan.">
    <ul><li>Nomor surat wajib diisi.</li></ul>
</x-ui.alert>
<x-ui.empty-state title="Belum ada surat." description="Coba ubah filter." icon="mail-search" />
```

Status mendukung `info`, `success`, `warning`, `danger`, `neutral`. Pesan mendukung
empat jenis pertama; gagal memakai `role=alert`, lainnya `role=status`.
Kelas lama `badge-active`, `badge-warning`, `badge-danger`, `badge-muted`, dan
`alert-*` tetap didukung. Jangan menebak status dari isi teks dengan JavaScript.
Slot dan atribut Blade tetap di-escape secara normal. Jangan memasukkan HTML
mentah dari pengguna. Slot opsional `actions` pada kondisi kosong dapat berisi
tombol yang memang diizinkan bagi pengguna, bukan melewati pemeriksaan izin.

## Dialog

Untuk dialog baru pada halaman admin:

```blade
<button type="button" class="button button-muted" data-ui-dialog-open="rincian"
    aria-haspopup="dialog" aria-controls="rincian">Lihat rincian</button>
<x-ui.dialog id="rincian" title="Rincian surat">
    <p>Konten rincian.</p>
    <x-slot:footer>
        <button type="button" class="button button-muted" data-ui-dialog-close>Tutup</button>
    </x-slot:footer>
</x-ui.dialog>
```

ID harus unik. `showModal()` native menangani lapisan modal dan Escape;
`admin-components.js` menjaga siklus Tab dan mengembalikan fokus ke pemicu. Klik backdrop tidak menutup
dialog untuk menghindari kehilangan isian. Dialog lama tetap menggunakan
pengendali masing-masing, tetapi mendapatkan warna bingkai dan backdrop umum.
Jangan menambahkan pengendali baru ke dialog lama tanpa memindahkan alur lamanya.

## Batas Tahap Dan Pemeriksaan

Kelas dasar memperbarui kontrol yang sudah dipakai di seluruh halaman admin.
Tahun Pelajaran dan Persuratan mengadopsi pola pesan, kosong dan filter sebagai
contoh nyata. Gaya khusus tiap modul yang belum memakai kelas dasar akan
dirapikan pada tahap pembaruan modul, bukan dipaksa dengan selector semua
`table`, `input`, atau `button`. Tema baru tidak mengubah hak akses atau data.

Contoh lengkap semua keadaan tersedia pada fixture uji
`tests/Fixtures/views/admin-components.blade.php`, tanpa route/menu publik.
`KomponenTampilanAdminTest` menguji render, scope aset, validasi dan simpan;
`tests/Browser/admin-components.mjs` memeriksa fokus, warna, kontrol, dialog,
luapan, dan desktop/seluler dari hasil render Blade sebenarnya.
