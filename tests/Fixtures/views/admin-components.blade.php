@extends('layouts.app')
@section('title', 'Standar Komponen - NUSA')
@section('content')
    <div class="page-header"><div><p class="eyebrow">NUSA</p><h1 class="page-title">Standar komponen</h1></div><button type="button" class="button button-primary" data-ui-dialog-open="contoh-dialog" aria-haspopup="dialog" aria-controls="contoh-dialog">Lihat rincian</button></div>
    <div class="stats-grid"><div class="panel stat"><p class="stat-label">Total</p><p class="stat-value">128</p></div><div class="panel stat active"><p class="stat-label">Aktif</p><p class="stat-value">120</p></div><div class="panel stat inactive"><p class="stat-label">Menunggu</p><p class="stat-value">8</p></div></div>
    <form method="GET" action="{{ route('tahun-pelajaran.index') }}" class="panel panel-pad ui-filter">
        <div class="filter-grid"><div class="field"><label for="pencarian">Pencarian</label><input class="input" id="pencarian" name="kata_kunci" type="search" placeholder="Nama atau nomor dokumen"></div><div class="field"><label for="status">Status</label><select id="status" name="status" class="select"><option>Semua status</option><option>Aktif</option></select></div><div class="actions"><button class="button button-primary" type="submit">Terapkan</button><a class="button button-muted" href="{{ route('tahun-pelajaran.index') }}">Reset</a></div></div>
    </form>
    <section aria-label="Kontrol formulir" class="panel panel-pad" style="margin-bottom:24px">
        <h2 class="panel-title">Informasi dokumen</h2>
        <div class="form-grid">
            <div class="field"><label for="normal">Nama dokumen</label><input id="normal" class="input" placeholder="Masukkan nama dokumen"><p class="help-text">Nama ditampilkan pada arsip.</p></div>
            <div class="field"><label for="invalid">Nomor surat</label><input id="invalid" class="input is-invalid" aria-invalid="true" aria-describedby="invalid-error"><p class="error-text" id="invalid-error">Nomor surat wajib diisi.</p></div>
            <div class="field"><label for="readonly">Tahun pelajaran</label><input id="readonly" class="input" value="2026/2027" readonly></div>
            <div class="field"><label for="disabled">Unit tujuan</label><select id="disabled" class="select" disabled><option>Belum tersedia</option></select></div>
            <div class="field"><label for="file">Lampiran</label><input id="file" class="file-input" type="file"></div>
            <div class="field"><label for="catatan">Catatan</label><textarea id="catatan" class="textarea" placeholder="Catatan tambahan"></textarea></div>
        </div>
        <label class="status-toggle"><span class="form-label">Aktifkan dokumen</span><input type="checkbox" checked></label>
        <div class="actions" style="margin-top:20px">
            <button class="button button-primary" type="button" id="primary">Simpan perubahan</button><button class="button button-muted" type="button">Batal</button><button class="button button-success" type="button">Selesaikan</button><button class="button button-danger" type="button">Batalkan surat</button><button class="button button-primary" type="button" id="disabled-button" disabled>Tidak tersedia</button><span class="button button-muted" aria-disabled="true">Sebelumnya</span><button class="button button-muted" type="button" id="busy-button" aria-busy="true" disabled>Menyimpan</button>
            <button class="button button-muted button-icon" type="button" aria-label="Unduh dokumen" data-admin-tooltip="Unduh dokumen"><i data-lucide="download" aria-hidden="true"></i></button>
        </div>
    </section>
    <section aria-label="Status dokumen" class="actions" style="margin-bottom:24px"><x-ui.status type="info">Tercatat</x-ui.status><x-ui.status type="success">Disahkan</x-ui.status><x-ui.status type="warning">Menunggu pengesahan</x-ui.status><x-ui.status type="danger">Dibatalkan</x-ui.status><x-ui.status>Draf</x-ui.status></section>
    <section aria-label="Status persuratan" class="actions" style="margin-bottom:24px"><span class="surat-status surat-status--draf">Draf surat</span><span class="surat-status surat-status--tercatat">Tercatat</span><span class="surat-status surat-status--disahkan">Disahkan</span><span class="surat-status surat-status--diajukan">Diajukan</span><span class="surat-status surat-status--dibatalkan">Dibatalkan</span></section>
    <x-ui.alert title="Informasi periode">Periode pelaporan berlangsung sampai 30 Oktober.</x-ui.alert>
    <x-ui.alert type="success" title="Perubahan tersimpan.">Dokumen berhasil diperbarui.</x-ui.alert>
    <x-ui.alert type="warning" title="Menunggu pengesahan.">Surat belum dikirim kepada penerima.</x-ui.alert>
    <x-ui.alert type="danger" title="Data belum dapat disimpan."><ul><li>Nomor surat wajib diisi.</li><li>Periksa kembali tanggal surat.</li></ul></x-ui.alert>
    <section class="ui-table-shell" aria-label="Daftar dokumen" style="margin-bottom:24px"><div class="table-wrap" tabindex="0" role="region" aria-label="Tabel dokumen"><table class="ui-table"><caption>Dokumen terbaru</caption><thead><tr><th scope="col">Nomor dan perihal</th><th scope="col">Tanggal</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead><tbody><tr><td><strong>421 / 001 / SMPN2</strong><p class="person-meta">Undangan rapat orang tua</p></td><td>18 Oktober 2026</td><td><x-ui.status type="success">Disahkan</x-ui.status></td><td><button type="button" class="button button-muted button-sm" data-ui-dialog-open="contoh-dialog">Buka</button></td></tr><tr><td><strong>421 / 002 / SMPN2</strong><p class="person-meta">Surat tugas kegiatan sekolah</p></td><td>19 Oktober 2026</td><td><x-ui.status type="warning">Menunggu pengesahan</x-ui.status></td><td><button type="button" class="button button-muted button-sm" disabled>Buka</button></td></tr></tbody></table></div></section>
    <section class="panel"><x-ui.empty-state title="Belum ada dokumen yang sesuai." description="Coba gunakan kata kunci lain atau ubah filter pencarian." icon="file-search"><x-slot:actions><button type="button" class="button button-muted">Reset filter</button></x-slot:actions></x-ui.empty-state></section>
    <x-ui.dialog id="contoh-dialog" title="Rincian dokumen">
        <p>Undangan rapat orang tua</p><div class="field" style="margin-top:16px"><label for="dialog-catatan">Catatan pemeriksaan</label><textarea id="dialog-catatan" class="textarea" placeholder="Tambahkan catatan"></textarea></div>
        <x-slot:footer><button type="button" class="button button-muted" data-ui-dialog-close>Tutup</button><button type="button" class="button button-primary" data-ui-dialog-close>Selesai</button></x-slot:footer>
    </x-ui.dialog>
@endsection
