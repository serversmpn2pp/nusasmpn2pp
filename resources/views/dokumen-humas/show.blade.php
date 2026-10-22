@extends('layouts.app')
@section('admin-module', 'humas')

@section('title', e($dokumenHumas->judul).' - Dokumen Humas')

@section('content')
    @include('dokumen-humas._klasifikasi-style')
    <div class="page-header">
        <div>
            <p class="eyebrow">Humas / {{ \App\Models\DokumenHumas::KATEGORI[$dokumenHumas->kategori] ?? 'Dokumen' }}</p>
            <h1 class="page-title">{{ $dokumenHumas->judul }}</h1>
        </div>
        <div class="actions">
            <a href="{{ route('dokumen-humas.index') }}" class="button button-muted">Kembali</a>
            <a href="{{ route('dokumen-humas.unduh', $dokumenHumas) }}" class="button button-primary">Unduh berkas terbaru</a>
            @izin('dokumen_humas.kelola')
                <a href="{{ route('dokumen-humas.edit', $dokumenHumas) }}" class="button button-muted">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Perubahan belum tersimpan.</strong><ul>@foreach($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach</ul></div>@endif
    <section class="dh-classification" aria-labelledby="dh-class-title">
        <h2 id="dh-class-title">Klasifikasi dokumen <span class="badge {{ $dokumenHumas->perluDitinjau() ? 'badge-inactive' : 'badge-active' }}">{{ $dokumenHumas->perluDitinjau() ? 'Perlu ditinjau' : 'Sudah diperiksa' }}</span></h2>
        <dl class="quick-facts"><div><dt>Bidang urusan</dt><dd>{{ \App\Models\DokumenHumas::URUSAN[$dokumenHumas->urusan] ?? 'Belum ditentukan' }}</dd></div><div><dt>Jenis dokumen</dt><dd>{{ \App\Models\DokumenHumas::JENIS_DOKUMEN[$dokumenHumas->jenis_dokumen] ?? 'Belum ditentukan' }}</dd></div><div><dt>Kategori lama</dt><dd>{{ \App\Models\DokumenHumas::KATEGORI[$dokumenHumas->kategori] ?? $dokumenHumas->kategori }}</dd></div><div><dt>Pemeriksaan</dt><dd>{{ $dokumenHumas->klasifikasi_diperiksa_pada?->format('d M Y H:i') ?? 'Belum dikonfirmasi petugas' }}</dd></div></dl>
    </section>
    @if ($mouTerhubung->isNotEmpty())<div class="actions" style="margin-bottom:20px"><span>MoU terkait:</span>@foreach ($mouTerhubung as $mou)<a href="{{ route('kemitraan-humas.mou.show', [$mou->mitra, $mou]) }}">{{ $mou->mitra->nama }} &middot; {{ $mou->judul }}</a>@endforeach</div>@endif

    <div class="detail-shell">
        <aside class="section-stack">
            <section class="panel panel-pad feature-section">
                <h2 class="panel-title">Status dan masa berlaku</h2>
                <dl class="quick-facts" style="margin-top:14px;">
                    <div><dt>Status arsip</dt><dd>{{ \App\Models\DokumenHumas::STATUS[$dokumenHumas->status] ?? $dokumenHumas->status }}</dd></div>
                    <div><dt>Masa berlaku</dt><dd>{{ $dokumenHumas->statusMasaBerlaku() }}</dd></div>
                    <div><dt>Berlaku mulai</dt><dd>{{ $dokumenHumas->berlaku_mulai?->format('d M Y') ?? 'Tidak ditentukan' }}</dd></div>
                    <div><dt>Berlaku sampai</dt><dd>{{ $dokumenHumas->berlaku_sampai?->format('d M Y') ?? 'Tanpa batas' }}</dd></div>
                    <div><dt>Pengingat</dt><dd>{{ $dokumenHumas->ingatkan_hari_sebelum }} hari sebelumnya</dd></div>
                </dl>
                @izin('dokumen_humas.kelola')
                    <form action="{{ route('dokumen-humas.status', $dokumenHumas) }}" method="POST" style="margin-top:16px;">
                        @csrf @method('PATCH')
                        <input type="hidden" name="sidik" value="{{ $sidik }}">
                        <input type="hidden" name="status" value="{{ $dokumenHumas->status === 'aktif' ? 'arsip' : 'aktif' }}">
                        <button type="submit" class="button button-muted button-full">{{ $dokumenHumas->status === 'aktif' ? 'Pindahkan ke arsip' : 'Aktifkan kembali' }}</button>
                    </form>
                @endizin
            </section>
            <section class="panel panel-pad feature-section">
                <h2 class="panel-title">Berkas terbaru</h2>
                <p style="margin:10px 0 0;font-weight:800;overflow-wrap:anywhere;">{{ $dokumenHumas->nama_file_asli }}</p>
                <p class="help-text">{{ $dokumenHumas->ukuranFileTampil() }} · {{ $dokumenHumas->updated_at?->format('d M Y H:i') }}</p>
                <a href="{{ route('dokumen-humas.unduh', $dokumenHumas) }}" class="button button-muted button-full" style="margin-top:14px;">Unduh</a>
            </section>
        </aside>

        <div class="section-stack">
            <section class="dh-relations" aria-labelledby="dh-relations-title">
                <h2 id="dh-relations-title">Rapat, program, dan surat terkait</h2>
                <ul>@forelse($hubungan as $item)
                    <li><div><small>{{ $item['kelompok'] }}</small><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></div>
                        @if($item['dapat_lepas'])<form class="dh-unlink" method="POST" action="{{ route('dokumen-humas.kaitan', $dokumenHumas) }}">@csrf @method('PATCH')<input type="hidden" name="sidik" value="{{ $sidik }}"><input type="hidden" name="aksi" value="lepas"><input type="hidden" name="jenis_kaitan" value="{{ $item['jenis'] }}"><input type="hidden" name="target_id" value="{{ $item['id'] }}"><input class="input" name="alasan" aria-label="Alasan melepas {{ $item['label'] }}" placeholder="Alasan melepas" minlength="5" maxlength="1000" required><button class="button button-muted dh-button" type="submit" title="Lepas keterkaitan"><i data-lucide="unlink" aria-hidden="true"></i>Lepas</button></form>@endif
                    </li>
                @empty<li><span class="help-text">Belum ada kaitan yang dapat ditampilkan untuk akun ini.</span></li>@endforelse</ul>
                @if($jenisKaitan)
                <div class="dh-link-form">
                    <form method="GET" action="{{ route('dokumen-humas.show', $dokumenHumas) }}">
                        <div class="form-grid"><div class="field"><label for="jenis-kaitan">Jenis kaitan</label><select id="jenis-kaitan" class="select" name="jenis_kaitan">@foreach($jenisKaitan as $kode => $label)<option value="{{ $kode }}" @selected($pilihanJenisKaitan === $kode)>{{ $label }}</option>@endforeach</select></div><div class="field"><label for="cari-kaitan">Cari rapat / program / surat</label><input class="input" id="cari-kaitan" name="cari_kaitan" value="{{ $filterKaitan['cari_kaitan'] ?? '' }}" maxlength="120"></div></div><div class="actions"><button class="button button-muted dh-button" type="submit"><i data-lucide="search" aria-hidden="true"></i>Cari</button></div>
                    </form>
                    <form method="POST" action="{{ route('dokumen-humas.kaitan', $dokumenHumas) }}" data-document-link>
                        @csrf @method('PATCH')<input type="hidden" name="sidik" value="{{ $sidik }}"><input type="hidden" name="aksi" value="tambah"><input type="hidden" name="jenis_kaitan" value="{{ $pilihanJenisKaitan }}">
                        <div class="form-grid"><div class="field span-2"><label for="target-kaitan">{{ $jenisKaitan[$pilihanJenisKaitan] }}</label><select id="target-kaitan" class="select" name="target_id" required><option value="">{{ $pilihanKaitan ? 'Pilih kaitan' : 'Tidak ada pilihan yang sesuai' }}</option>@foreach($pilihanKaitan as $pilihan)<option value="{{ $pilihan['id'] }}">{{ $pilihan['label'] }}</option>@endforeach</select></div><div class="field span-2"><label for="alasan-kaitan">Catatan keterkaitan</label><input id="alasan-kaitan" class="input" name="alasan" value="{{ old('alasan') }}" minlength="5" maxlength="1000" required></div></div>
                        <div class="actions"><button class="button button-primary dh-button" type="submit" @disabled(!$pilihanKaitan || $dokumenHumas->status !== 'aktif')><i data-lucide="link" aria-hidden="true"></i>Hubungkan dokumen</button></div>
                    </form>
                </div>
                @endif
            </section>
            <section class="panel panel-pad feature-section">
                <h2 class="panel-title">Rincian</h2>
                <dl class="quick-facts" style="margin-top:14px;">
                    <div><dt>Nomor dokumen</dt><dd>{{ $dokumenHumas->nomor_dokumen ?: 'Tidak dicantumkan' }}</dd></div>
                    <div><dt>Dibuat oleh</dt><dd>{{ $dokumenHumas->pembuat?->nama ?: 'Pengguna tidak tersedia' }}</dd></div>
                    <div><dt>Terakhir diperbarui oleh</dt><dd>{{ $dokumenHumas->pengubah?->nama ?: 'Pengguna tidak tersedia' }}</dd></div>
                </dl>
                @if ($dokumenHumas->deskripsi)
                    <div style="margin-top:16px;white-space:pre-line;">{{ $dokumenHumas->deskripsi }}</div>
                @endif
            </section>

            <section class="panel panel-pad feature-section">
                <h2 class="panel-title">Riwayat berkas</h2>
                <p class="help-text">Setiap revisi disimpan sebagai versi agar berkas sebelumnya tetap dapat ditelusuri.</p>
                <div class="table-wrap" style="margin-top:12px;">
                    <table class="data-table">
                        <thead><tr><th>Versi</th><th>Berkas</th><th>Diunggah</th><th>Catatan</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($dokumenHumas->riwayat as $versi)
                                <tr>
                                    <td>v{{ $versi->versi }}{{ $loop->first ? ' · Terbaru' : '' }}</td>
                                    <td>{{ $versi->nama_file_asli }}<br><span class="help-text">{{ $versi->ukuranFileTampil() }}</span></td>
                                    <td>{{ $versi->diunggah_pada?->format('d M Y H:i') }}<br><span class="help-text">{{ $versi->pengunggah?->nama ?: 'Pengguna tidak tersedia' }}</span></td>
                                    <td>{{ $versi->catatan ?: '-' }}</td>
                                    <td><a class="button button-muted button-sm" href="{{ route('dokumen-humas.riwayat.unduh', [$dokumenHumas, $versi]) }}">Unduh</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty-state">Belum ada riwayat berkas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="dh-relations"><h2>Riwayat klasifikasi dan kaitan</h2><ul class="dh-audit-list">
                @forelse($riwayatKlasifikasi as $catatan)
                <li><strong>{{ ['dokumen_dibuat' => 'Dokumen diunggah', 'klasifikasi_diperbarui' => 'Klasifikasi diperbarui', 'klasifikasi_kolektif' => 'Klasifikasi dikonfirmasi kolektif', 'penataan_arsip_lama' => 'Penataan arsip lama', 'kaitan_tambah' => 'Kaitan ditambahkan', 'kaitan_lepas' => 'Kaitan dilepas'][$catatan->aksi] ?? 'Informasi diperbarui' }}</strong><div>{{ $catatan->alasan }}</div><small>{{ $catatan->created_at?->format('d M Y H:i') }} &middot; {{ $catatan->pengguna?->nama ?? 'Sistem' }}</small></li>
                @empty<li class="help-text">Belum ada perubahan klasifikasi atau kaitan.</li>@endforelse
            </ul>{{ $riwayatKlasifikasi->links() }}</section>
        </div>
    </div>
@endsection
