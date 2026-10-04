@extends('layouts.app')
@section('title', 'Kunjungan '.$tamu->nama_tamu.' - NUSA')
@section('content')
@include('buku-tamu._style')
<div class="tamu-page">
    <div class="page-header"><div><p class="eyebrow">Buku Tamu Digital</p><h1 class="page-title" style="overflow-wrap:anywhere">{{ $tamu->nama_tamu }}</h1></div><div class="tamu-actions"><a class="button button-muted" href="{{ route('buku-tamu.index') }}">Daftar tamu</a>@if ($bolehKelola && $tamu->status !== 'dibatalkan')<a class="button button-muted" href="{{ route('buku-tamu.edit', $tamu) }}">Koreksi data</a>@endif</div></div>
    @include('buku-tamu._messages')
    <div class="tamu-actions" style="margin:20px 0"><span class="tamu-badge tamu-badge--{{ $tamu->status }}">{{ \App\Models\KunjunganTamu::STATUS[$tamu->status] }}</span>
        @if ($bolehCatat && $tamu->status === 'berkunjung')<form method="POST" action="{{ route('buku-tamu.pulang', $tamu) }}" onsubmit="return confirm('Catat kepulangan tamu sekarang?')">@csrf @method('PATCH')<button class="button button-primary" type="submit">Tandai sudah pulang</button></form>@endif
    </div>
    @if ($tamu->status === 'dibatalkan')<div class="alert alert-danger"><strong>Kunjungan dibatalkan.</strong> {{ $tamu->alasan_pembatalan }}</div>@endif
    <section class="tamu-section"><h2>Rincian kunjungan</h2><dl class="tamu-details">
        <div><dt>Instansi / asal</dt><dd>{{ $tamu->instansi ?: 'Tamu perorangan' }}</dd></div><div><dt>Jabatan</dt><dd>{{ $tamu->jabatan ?: '-' }}</dd></div><div><dt>Nomor WhatsApp</dt><dd>{{ $tamu->nomor_wa ?: '-' }}</dd></div>
        <div class="tamu-wide"><dt>Alamat instansi / asal</dt><dd>{{ $tamu->alamat_instansi ?: '-' }}</dd></div>
        <div><dt>Kategori</dt><dd>{{ \App\Models\KunjunganTamu::KATEGORI[$tamu->kategori] }}</dd></div><div><dt>Pihak yang dituju</dt><dd>{{ $tamu->nama_tujuan }}</dd></div><div><dt>Pencatat kedatangan</dt><dd>{{ $tamu->pencatat?->nama ?: 'Akun tidak tersedia' }}</dd></div>
        <div><dt>Datang</dt><dd>{{ $tamu->waktu_datang->format('d-m-Y H:i:s') }} WIB</dd></div><div><dt>Pulang</dt><dd>{{ $tamu->waktu_pulang ? $tamu->waktu_pulang->format('d-m-Y H:i:s').' WIB' : 'Belum dicatat' }}</dd></div><div><dt>Durasi {{ $tamu->status === 'berkunjung' ? 'sampai saat ini' : '' }}</dt><dd>{{ $tamu->durasiMenit() === null ? '-' : $tamu->durasiMenit().' menit' }}</dd></div>
        <div class="tamu-wide"><dt>Keperluan</dt><dd>{{ $tamu->keperluan }}</dd></div><div class="tamu-wide"><dt>Catatan petugas</dt><dd>{{ $tamu->catatan ?: '-' }}</dd></div>
    </dl></section>
    <section class="tamu-section"><h2>Lampiran kunjungan <small style="font-size:12px;font-weight:400">{{ $tamu->lampiran->count() }} / 20 berkas</small></h2>
        @if ($tamu->lampiran->isEmpty())<p class="tamu-hint">Belum ada lampiran.</p>@else<div class="tamu-files">@foreach ($tamu->lampiran as $file)<article class="tamu-file">
            @if (in_array($file->tipe_file, ['image/jpeg', 'image/png', 'image/webp'], true))<a href="{{ route('buku-tamu.lampiran.pratinjau', [$tamu, $file]) }}" target="_blank" rel="noopener"><img src="{{ route('buku-tamu.lampiran.pratinjau', [$tamu, $file]) }}" alt="{{ \App\Models\LampiranKunjunganTamu::JENIS[$file->jenis] }}" loading="lazy"></a>@endif
            <span class="tamu-badge">{{ \App\Models\LampiranKunjunganTamu::JENIS[$file->jenis] }}</span><strong>{{ $file->nama_file_asli }}</strong><p class="tamu-hint">{{ number_format($file->ukuran_file / 1024, 0, ',', '.') }} KB &middot; {{ $file->created_at->format('d-m-Y H:i') }}</p>
            <div class="tamu-actions"><a class="button button-muted button-sm" href="{{ route('buku-tamu.lampiran.unduh', [$tamu, $file]) }}">Unduh</a>@if ($bolehKelola && $tamu->status !== 'dibatalkan')<form method="POST" action="{{ route('buku-tamu.lampiran.destroy', [$tamu, $file]) }}" onsubmit="return confirm('Hapus lampiran ini? Penghapusan tetap dicatat dalam riwayat.')">@csrf @method('DELETE')<button class="button button-muted button-sm" type="submit">Hapus</button></form>@endif</div>
        </article>@endforeach</div>@endif
        @if ($bolehCatat && $tamu->status !== 'dibatalkan' && $tamu->lampiran->count() < 20)
            <form method="POST" action="{{ route('buku-tamu.lampiran.store', $tamu) }}" enctype="multipart/form-data" data-tamu-upload style="margin-top:24px">@csrf<input type="hidden" name="token_unggahan" value="{{ old('token_unggahan', $tokenUnggahan) }}">@include('buku-tamu._upload')<div class="tamu-footer"><button type="submit" class="button button-primary">Unggah lampiran</button></div></form>
        @endif
    </section>
    @if ($riwayat)
        <section class="tamu-section"><h2>Riwayat pencatatan</h2><ol class="tamu-history">@foreach ($riwayat as $item)<li>
            <strong>{{ \App\Models\RiwayatKunjunganTamu::AKSI[$item->aksi] ?? $item->aksi }}</strong><p class="tamu-hint">{{ $item->pengguna?->nama ?: 'Akun tidak tersedia' }} &middot; <time>{{ $item->created_at->format('d-m-Y H:i:s') }} WIB</time></p>
            <details><summary>Rincian perubahan</summary>
                @php
                    $labelAudit = ['nama_tamu' => 'Nama tamu', 'instansi' => 'Instansi', 'alamat_instansi' => 'Alamat', 'jabatan' => 'Jabatan', 'nomor_wa' => 'Nomor WhatsApp', 'kategori' => 'Kategori', 'keperluan' => 'Keperluan', 'nama_tujuan' => 'Pihak yang dituju', 'waktu_datang' => 'Datang', 'waktu_pulang' => 'Pulang', 'status' => 'Status', 'catatan' => 'Catatan', 'alasan_pembatalan' => 'Alasan pembatalan', 'jumlah' => 'Jumlah berkas', 'berkas' => 'Nama berkas', 'nama_file' => 'Nama berkas', 'jenis' => 'Jenis berkas'];
                    $teksAudit = function ($key, $nilai) {
                        if ($nilai === null || $nilai === '') return '-';
                        if (is_array($nilai)) return implode(', ', $nilai);
                        if ($key === 'status') return \App\Models\KunjunganTamu::STATUS[$nilai] ?? $nilai;
                        if ($key === 'kategori') return \App\Models\KunjunganTamu::KATEGORI[$nilai] ?? $nilai;
                        if ($key === 'jenis') return \App\Models\LampiranKunjunganTamu::JENIS[$nilai] ?? $nilai;
                        if (in_array($key, ['waktu_datang', 'waktu_pulang'])) return \Illuminate\Support\Carbon::parse($nilai)->timezone(config('app.timezone'))->format('d-m-Y H:i:s');
                        return $nilai;
                    };
                @endphp
                @foreach ($labelAudit as $key => $label)
                    @if (($item->data_sebelum[$key] ?? null) !== ($item->data_sesudah[$key] ?? null))<div class="tamu-audit"><strong>{{ $label }}</strong><div><span class="tamu-audit-label">Sebelum: </span>{{ $teksAudit($key, $item->data_sebelum[$key] ?? null) }}</div><div><span class="tamu-audit-label">Sesudah: </span>{{ $teksAudit($key, $item->data_sesudah[$key] ?? null) }}</div></div>@endif
                @endforeach
            </details>
        </li>@endforeach</ol><div style="margin-top:18px">{{ $riwayat->links() }}</div></section>
    @endif
    @if ($bolehKelola && $tamu->status !== 'dibatalkan')<details class="tamu-cancel"><summary>Batalkan pencatatan kunjungan</summary><form method="POST" action="{{ route('buku-tamu.batalkan', $tamu) }}" onsubmit="return confirm('Batalkan kunjungan ini? Data dan riwayat tetap disimpan.')">@csrf @method('PATCH')<div class="field"><label for="alasan_pembatalan">Alasan pembatalan</label><textarea class="textarea" id="alasan_pembatalan" name="alasan_pembatalan" rows="2" minlength="5" maxlength="1000" required>{{ old('alasan_pembatalan') }}</textarea></div><button class="button button-muted" style="margin-top:12px" type="submit">Batalkan kunjungan</button></form></details>@endif
</div>
@include('buku-tamu._script')
@endsection
