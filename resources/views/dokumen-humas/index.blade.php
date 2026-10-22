@extends('layouts.app')
@section('admin-module', 'humas')

@section('title', 'Pusat Dokumen Humas - NUSA')

@section('content')
    <style>
        .humas-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:20px; }
        .humas-filter { display:grid; grid-template-columns:minmax(200px,1fr) minmax(170px,.7fr) minmax(150px,.6fr) auto; gap:12px; align-items:end; }
        .humas-table { width:100%; border-collapse:collapse; }
        .humas-table th,.humas-table td { border-bottom:1px solid var(--line); padding:12px 10px; text-align:left; vertical-align:top; }
        .humas-table th { color:var(--muted); font-size:.8rem; font-weight:800; }
        .humas-table tr:last-child td { border-bottom:0; }
        .humas-title { color:var(--primary-dark); font-weight:800; }
        .humas-row-actions { display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; }
        .humas-expiry { display:block; margin-top:4px; color:var(--muted); font-size:.8rem; }
        @media(max-width:800px) { .humas-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:560px) { .humas-filter { grid-template-columns:1fr; } }
    </style>
    @include('dokumen-humas._klasifikasi-style')

    <div class="page-header">
        <div>
            <p class="eyebrow">Humas</p>
            <h1 class="page-title">Pusat Dokumen Humas</h1>
        </div>
        @izin('dokumen_humas.kelola')
            <a href="{{ route('dokumen-humas.create', array_filter(['urusan' => $filter['urusan'] ?? null, 'jenis_dokumen' => $filter['jenis_dokumen'] ?? null])) }}" class="button button-primary">Tambah dokumen</a>
        @endizin
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Perubahan belum tersimpan.</strong><ul>@foreach($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach</ul></div>@endif

    <div class="humas-summary">
        <article class="panel stat active"><p class="stat-label">Dokumen aktif</p><p class="stat-value">{{ number_format($jumlahAktif, 0, ',', '.') }}</p></article>
        <article class="panel stat inactive"><p class="stat-label">Segera berakhir, 30 hari</p><p class="stat-value">{{ number_format($jumlahSegeraBerakhir, 0, ',', '.') }}</p></article>
        <article class="panel stat"><p class="stat-label">Kedaluwarsa</p><p class="stat-value">{{ number_format($jumlahKedaluwarsa, 0, ',', '.') }}</p></article>
        <a class="panel stat inactive" href="{{ route('dokumen-humas.index', ['klasifikasi' => 'perlu_tinjau']) }}"><p class="stat-label">Klasifikasi perlu ditinjau</p><p class="stat-value">{{ number_format($jumlahPerluTinjau, 0, ',', '.') }}</p></a>
    </div>

    <form action="{{ route('dokumen-humas.index') }}" method="GET" class="feature-filter panel panel-pad" style="margin-bottom:18px;">
        <div class="humas-filter">
            <div class="field"><label for="urusan">Bidang urusan</label><select id="urusan" name="urusan" class="select"><option value="">Semua urusan</option>@foreach(\App\Models\DokumenHumas::URUSAN as $kode => $label)<option value="{{ $kode }}" @selected(($filter['urusan'] ?? '') === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="jenis_dokumen">Jenis dokumen</label><select id="jenis_dokumen" name="jenis_dokumen" class="select"><option value="">Semua jenis</option>@foreach(\App\Models\DokumenHumas::JENIS_DOKUMEN as $kode => $label)<option value="{{ $kode }}" @selected(($filter['jenis_dokumen'] ?? '') === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field">
                <label for="kata_kunci">Cari dokumen</label>
                <input id="kata_kunci" name="kata_kunci" type="search" class="input" value="{{ $filter['kata_kunci'] ?? '' }}" placeholder="Judul, nomor, atau keterangan">
            </div>
            <div class="field">
                <label for="kategori">Kategori lama</label>
                <select id="kategori" name="kategori" class="select">
                    <option value="">Semua kategori</option>
                    @foreach ($kategori as $kode => $label)
                        <option value="{{ $kode }}" @selected(($filter['kategori'] ?? '') === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field"><label for="klasifikasi">Pemeriksaan klasifikasi</label><select id="klasifikasi" name="klasifikasi" class="select"><option value="">Semua klasifikasi</option><option value="perlu_tinjau" @selected(($filter['klasifikasi'] ?? '') === 'perlu_tinjau')>Perlu ditinjau</option><option value="diperiksa" @selected(($filter['klasifikasi'] ?? '') === 'diperiksa')>Sudah diperiksa</option></select></div>
            <div class="field">
                <label for="status">Status arsip</label>
                <select id="status" name="status" class="select">
                    <option value="">Semua status</option>
                    @foreach ($status as $kode => $label)
                        <option value="{{ $kode }}" @selected(($filter['status'] ?? '') === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                @if ($filter)
                    <a href="{{ route('dokumen-humas.index') }}" class="button button-muted">Reset</a>
                @endif
            </div>
        </div>
    </form>

    @izin('dokumen_humas.kelola')
    <form id="klasifikasi-kolektif" action="{{ route('dokumen-humas.klasifikasi-kolektif') }}" method="POST" class="dh-bulk" data-document-bulk hidden>
        @csrf @method('PATCH')
        <header><h2>Klasifikasi kolektif</h2><span class="badge badge-inactive" data-document-count role="status" aria-live="polite">0 dokumen dipilih</span></header>
        <div class="dh-bulk-grid">
            <div class="field"><label for="bulk-urusan">Bidang urusan</label><select id="bulk-urusan" name="urusan" class="select" required><option value="">Pilih urusan</option>@foreach(\App\Models\DokumenHumas::URUSAN as $kode => $label)<option value="{{ $kode }}" @selected(old('urusan') === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="bulk-jenis">Jenis dokumen</label><select id="bulk-jenis" name="jenis_dokumen" class="select" required><option value="">Pilih jenis</option>@foreach(\App\Models\DokumenHumas::JENIS_DOKUMEN as $kode => $label)<option value="{{ $kode }}" @selected(old('jenis_dokumen') === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field dh-bulk-reason"><label for="bulk-alasan">Alasan / catatan pemeriksaan</label><input id="bulk-alasan" class="input" name="alasan" value="{{ old('alasan') }}" minlength="5" maxlength="1000" required></div>
        </div>
        <div class="actions"><button class="button button-primary dh-button" type="submit"><i data-lucide="check-check" aria-hidden="true"></i>Konfirmasi klasifikasi</button></div>
    </form>
    @endizin

    <section class="panel panel-pad feature-section">
        @if ($dokumen->isEmpty())
            <div class="empty-state">Belum ada dokumen yang sesuai. Tambahkan arsip pertama untuk mulai mengelola dokumen Humas.</div>
        @else
            @izin('dokumen_humas.kelola')<label style="display:flex;align-items:center;gap:10px;margin-bottom:14px"><input type="checkbox" class="dh-check" data-documents-all>Pilih semua pada halaman ini</label>@endizin
            <div class="table-wrap">
                <table class="humas-table">
                    <thead>
                        <tr>@izin('dokumen_humas.kelola')<th aria-label="Pilihan dokumen"></th>@endizin<th>Dokumen</th><th>Klasifikasi</th><th>Nomor</th><th>Masa berlaku</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($dokumen as $item)
                            @php
                                $masaBerlaku = $item->statusMasaBerlaku();
                                $badgeMasa = match ($masaBerlaku) {
                                    'Kedaluwarsa' => 'badge-danger',
                                    'Segera berakhir' => 'badge-inactive',
                                    'Masih berlaku' => 'badge-active',
                                    default => 'badge-muted',
                                };
                            @endphp
                            <tr>
                                @izin('dokumen_humas.kelola')<td class="dh-selection"><input type="checkbox" class="dh-check" form="klasifikasi-kolektif" name="ids[]" value="{{ $item->id }}" data-document-select aria-label="Pilih {{ $item->judul }}" @checked(in_array((string)$item->id, array_map('strval', (array)old('ids', [])), true))><input type="hidden" form="klasifikasi-kolektif" name="sidik[{{ $item->id }}]" value="{{ app(\App\Services\Humas\KelolaDokumenHumasService::class)->sidik($item) }}"></td>@endizin
                                <td data-label="Dokumen">
                                    <a class="humas-title" href="{{ route('dokumen-humas.show', $item) }}">{{ $item->judul }}</a>
                                    <span class="humas-expiry">Diperbarui {{ $item->updated_at?->format('d M Y') }}</span>
                                </td>
                                <td data-label="Klasifikasi"><strong>{{ \App\Models\DokumenHumas::URUSAN[$item->urusan] ?? 'Urusan belum ditentukan' }}</strong><span class="dh-type">{{ \App\Models\DokumenHumas::JENIS_DOKUMEN[$item->jenis_dokumen] ?? 'Jenis belum ditentukan' }}</span><span class="badge {{ $item->perluDitinjau() ? 'badge-inactive' : 'badge-active' }}">{{ $item->perluDitinjau() ? 'Perlu ditinjau' : 'Sudah diperiksa' }}</span></td>
                                <td data-label="Nomor">{{ $item->nomor_dokumen ?: '-' }}</td>
                                <td data-label="Masa berlaku">
                                    @if ($item->berlaku_sampai)
                                        <span class="badge {{ $badgeMasa }}">{{ $masaBerlaku }}</span>
                                        <span class="humas-expiry">s.d. {{ $item->berlaku_sampai->format('d M Y') }}</span>
                                    @else
                                        <span class="badge badge-muted">Tanpa batas</span>
                                    @endif
                                </td>
                                <td data-label="Status"><span class="badge {{ $item->status === 'aktif' ? 'badge-active' : 'badge-muted' }}">{{ $status[$item->status] ?? $item->status }}</span></td>
                                <td data-label="Aksi"><div class="humas-row-actions"><a class="button button-muted button-sm" href="{{ route('dokumen-humas.show', $item) }}">Buka</a></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top:16px;">{{ $dokumen->links() }}</div>
        @endif
    </section>
    <script>
    (() => {
        const form=document.querySelector('[data-document-bulk]'), all=document.querySelector('[data-documents-all]');
        const rows=[...document.querySelectorAll('[data-document-select]')];
        if(!form||!all)return;
        const update=()=>{const count=rows.filter(row=>row.checked).length;form.hidden=count===0;form.querySelector('[data-document-count]').textContent=count+' dokumen dipilih';all.checked=count>0&&count===rows.length;all.indeterminate=count>0&&count<rows.length;};
        all.addEventListener('change',()=>{rows.forEach(row=>row.checked=all.checked);update();});
        rows.forEach(row=>row.addEventListener('change',update));update();
    })();
    </script>
@endsection
