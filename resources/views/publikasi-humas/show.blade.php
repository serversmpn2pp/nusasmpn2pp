@extends('layouts.app')
@section('title', 'Rincian Publikasi - NUSA')
@section('content')
@include('publikasi-humas._style')
@php
    $kelola = auth()->user()->memilikiIzin('publikasi_humas.kelola');
    $periksa = auth()->user()->memilikiIzin('publikasi_humas.periksa');
    $sendiri = in_array(auth()->id(), [$publikasi->dibuat_oleh_pengguna_id, $publikasi->diajukan_oleh_pengguna_id], true);
@endphp
<div class="publikasi-page">
    <div class="page-header"><div style="min-width:0"><p class="eyebrow">Humas / Publikasi</p><h1 class="page-title">{{ $publikasi->judul }}</h1></div><div class="actions"><a class="button button-muted" href="{{ route('publikasi-humas.index') }}">Daftar konten</a>@if ($kelola && $publikasi->bolehDiedit())<a class="button button-primary" href="{{ route('publikasi-humas.edit', $publikasi) }}">Edit draf</a>@endif</div></div>
    @include('agenda-humas._messages')
    <ol class="publikasi-workflow" aria-label="Tahapan publikasi">@foreach (\App\Models\PublikasiHumas::STATUS as $kode => $label)<li @if ($publikasi->status === $kode) aria-current="step" @endif>{{ $label }}</li>@endforeach</ol>
    <section class="agenda-section"><h2>Informasi konten</h2><dl class="agenda-facts">
        <div><dt>Status</dt><dd><span class="publikasi-status publikasi-status--{{ $publikasi->status }}">{{ \App\Models\PublikasiHumas::STATUS[$publikasi->status] }}</span></dd></div>
        <div><dt>Jenis / media tujuan</dt><dd>{{ \App\Models\PublikasiHumas::JENIS[$publikasi->jenis] }} &middot; {{ \App\Models\PublikasiHumas::KANAL[$publikasi->kanal] }}</dd></div>
        <div><dt>Pembuat</dt><dd>{{ $publikasi->pembuat?->nama ?: 'Akun tidak tersedia' }}</dd></div><div><dt>Rencana tanggal tayang</dt><dd>{{ $publikasi->rencana_tayang?->format('d-m-Y') ?: '-' }}</dd></div>
        <div><dt>Diajukan</dt><dd>{{ $publikasi->diajukan_pada?->format('d-m-Y H:i') ?: '-' }}</dd></div><div><dt>Pemeriksaan terakhir</dt><dd>{{ $publikasi->pemeriksa?->nama ?: '-' }}{{ $publikasi->diperiksa_pada ? ' - '.$publikasi->diperiksa_pada->format('d-m-Y H:i') : '' }}</dd></div>
        @if ($bolehAgenda && $publikasi->agenda)<div><dt>Agenda terkait</dt><dd><a href="{{ route('agenda-humas.show', $publikasi->agenda) }}">{{ $publikasi->agenda->judul }}</a></dd></div>@endif
    </dl></section>
    @if ($publikasi->catatan_pemeriksaan)<div class="publikasi-review"><strong>Catatan pemeriksaan</strong><div class="agenda-text">{{ $publikasi->catatan_pemeriksaan }}</div></div>@endif
    <section class="agenda-section"><h2>Naskah publikasi</h2>@if ($publikasi->ringkasan)<p>{{ $publikasi->ringkasan }}</p>@endif<div class="agenda-text">{{ $publikasi->isi }}</div><div class="agenda-actions"><a class="button button-muted" href="{{ route('publikasi-humas.naskah', $publikasi) }}">Unduh naskah</a></div></section>
    <section class="agenda-section"><h2>Foto konten</h2><div class="publikasi-photos">
        @forelse ($publikasi->lampiran->where('jenis', 'foto')->whereNull('dihapus_pada') as $foto)<figure class="publikasi-photo"><a href="{{ route('publikasi-humas.berkas', [$publikasi, $foto]) }}" target="_blank" rel="noopener"><img src="{{ route('publikasi-humas.berkas', [$publikasi, $foto]) }}" alt="Foto konten {{ $loop->iteration }}"></a><figcaption>{{ $foto->nama_file_asli }}</figcaption><a class="button button-muted button-sm" href="{{ route('publikasi-humas.berkas', [$publikasi, $foto, 'unduh' => 1]) }}">Unduh foto</a></figure>@empty<p class="agenda-muted">Belum ada foto.</p>@endforelse
    </div></section>
    @if ($bolehAset && $publikasi->aset->isNotEmpty())
        <section class="agenda-section"><h2>Aset promosi yang digunakan</h2>@foreach ($publikasi->aset as $item)
            @if ($bolehDokumen && $item->berkas && str_starts_with($item->berkas->tipe_file, 'image/'))<figure class="publikasi-photo" style="max-width:400px"><img src="{{ route('aset-promosi-humas.berkas', [$item->aset_promosi_humas_id, $item->berkas]) }}" alt="{{ $item->snapshot['nama'] }}"></figure>@endif
            @include('publikasi-humas._aset-rincian', ['dataAset' => $item->snapshot + ['aset_id' => $item->aset_promosi_humas_id, 'berkas_id' => $item->riwayat_dokumen_humas_id]])
        @endforeach</section>
    @endif
    @if ($kelola && $publikasi->bolehDiedit())
        <section class="agenda-section"><h2>Pengajuan pemeriksaan</h2><form method="POST" action="{{ route('publikasi-humas.tindakan', [$publikasi, 'ajukan']) }}" data-publikasi-submit>@csrf<input type="hidden" name="versi" value="{{ $publikasi->versi }}"><button class="button button-primary" type="submit">Ajukan ke pimpinan</button></form></section>
    @endif
    @if ($publikasi->status === 'diajukan')
        <section class="agenda-section"><h2>Pemeriksaan konten</h2>
            @if ($periksa && !$sendiri)
                <form method="POST" action="{{ route('publikasi-humas.pemeriksaan', [$publikasi, 'setujui']) }}" data-publikasi-submit>@csrf<input type="hidden" name="versi" value="{{ $publikasi->versi }}"><div class="field"><label for="catatan_setuju">Catatan persetujuan (opsional)</label><textarea class="textarea" id="catatan_setuju" name="catatan" rows="2" minlength="5" maxlength="2000">{{ old('catatan') }}</textarea></div><div class="agenda-actions"><button class="button button-primary" type="submit">Setujui konten</button></div></form>
                <details style="margin-top:22px"><summary style="cursor:pointer;font-weight:700">Minta perbaikan konten</summary><form method="POST" action="{{ route('publikasi-humas.pemeriksaan', [$publikasi, 'minta-revisi']) }}" data-publikasi-submit style="margin-top:16px">@csrf<input type="hidden" name="versi" value="{{ $publikasi->versi }}"><div class="field"><label for="catatan_revisi">Catatan perbaikan</label><textarea class="textarea" id="catatan_revisi" name="catatan" rows="3" minlength="5" maxlength="2000" required>{{ old('catatan') }}</textarea></div><div class="agenda-actions"><button class="button button-muted" type="submit">Kirim permintaan revisi</button></div></form></details>
            @elseif ($periksa && $sendiri)
                <p class="agenda-muted">Konten ini memerlukan pemeriksaan dari pimpinan lain karena Anda pembuat atau pengajunya.</p>
            @else
                <p class="agenda-muted">Menunggu pemeriksaan pimpinan.</p>
            @endif
            @if ($kelola)<form method="POST" action="{{ route('publikasi-humas.tindakan', [$publikasi, 'tarik']) }}" data-publikasi-submit style="margin-top:22px">@csrf<input type="hidden" name="versi" value="{{ $publikasi->versi }}"><button class="button button-muted" type="submit">Tarik pengajuan untuk diedit</button></form>@endif
        </section>
    @endif
    @if ($kelola && $publikasi->status === 'disetujui')
        <section class="agenda-section"><h2>Catat publikasi yang sudah tayang</h2>
            <form method="POST" enctype="multipart/form-data" action="{{ route('publikasi-humas.tindakan', [$publikasi, 'tayang']) }}" data-publikasi-upload>@csrf<input type="hidden" name="versi" value="{{ $publikasi->versi }}"><div class="agenda-field-grid">
                <div class="field span-2"><label for="url_tayang">Tautan publikasi</label><input class="input" type="url" id="url_tayang" name="url_tayang" maxlength="2000" value="{{ old('url_tayang') }}" required></div>
                <div class="field"><label for="waktu_tayang">Tanggal & jam tayang</label><input class="input" type="datetime-local" id="waktu_tayang" name="waktu_tayang" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('waktu_tayang', now()->format('Y-m-d\TH:i')) }}" required></div>
                <div class="field"><label for="bukti">Bukti tayang (opsional, PDF/gambar, maksimal 5 MB)</label><input class="file-input" id="bukti" name="bukti" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
            </div>@include('publikasi-humas._upload-state')<div class="agenda-actions"><button class="button button-primary" type="submit">Simpan bukti tayang</button></div></form>
            <details style="margin-top:22px"><summary style="cursor:pointer;font-weight:700">Buka revisi sebelum tayang</summary><form method="POST" action="{{ route('publikasi-humas.tindakan', [$publikasi, 'buka-revisi']) }}" data-publikasi-submit style="margin-top:16px">@csrf<input type="hidden" name="versi" value="{{ $publikasi->versi }}"><div class="field"><label for="alasan_revisi">Alasan revisi</label><textarea class="textarea" id="alasan_revisi" name="catatan" rows="2" minlength="5" maxlength="2000" required></textarea></div><p class="agenda-muted">Persetujuan sebelumnya tidak berlaku untuk naskah yang diubah.</p><button class="button button-muted" type="submit">Buka revisi</button></form></details>
        </section>
    @endif
    @if ($publikasi->status === 'tayang')<section class="agenda-section"><h2>Bukti tayang</h2><p>{{ $publikasi->waktu_tayang->format('d-m-Y H:i') }} WIB</p><a href="{{ $publikasi->url_tayang }}" target="_blank" rel="noopener noreferrer" style="overflow-wrap:anywhere">{{ $publikasi->url_tayang }}</a>@foreach ($publikasi->lampiran->where('jenis', 'bukti') as $bukti)<div class="agenda-actions"><a class="button button-muted" href="{{ route('publikasi-humas.berkas', [$publikasi, $bukti]) }}" target="_blank" rel="noopener">Buka bukti: {{ $bukti->nama_file_asli }}</a></div>@endforeach</section>@endif
    <section class="agenda-section"><h2>Riwayat konten & persetujuan</h2><ul class="publikasi-history">
        @foreach ($riwayat as $item)<li><strong>{{ $item->aksi }}</strong><p class="agenda-muted">{{ $item->pengguna?->nama ?: 'Akun tidak tersedia' }} &middot; {{ $item->created_at->format('d-m-Y H:i') }} &middot; Versi {{ $item->versi + 1 }}</p>@if ($item->catatan)<div class="agenda-text">{{ $item->catatan }}</div>@endif
            <details><summary>Lihat konten pada saat ini</summary><div class="publikasi-snapshot"><h3>{{ $item->snapshot['judul'] }}</h3><span class="publikasi-status">{{ \App\Models\PublikasiHumas::STATUS[$item->snapshot['status']] }}</span><p>{{ \App\Models\PublikasiHumas::KANAL[$item->snapshot['kanal']] }} &middot; {{ $item->snapshot['rencana_tayang'] ?: 'Belum dijadwalkan' }}</p>@if ($item->snapshot['ringkasan'])<p>{{ $item->snapshot['ringkasan'] }}</p>@endif<div class="agenda-text">{{ $item->snapshot['isi'] }}</div>@foreach ($item->snapshot['lampiran'] as $file)<div class="agenda-actions"><a href="{{ route('publikasi-humas.berkas', [$publikasi, $file['id'], 'unduh' => 1]) }}">{{ $file['nama_file_asli'] }}</a></div>@endforeach @if ($item->snapshot['url_tayang'])<p>{{ $item->snapshot['url_tayang'] }}</p>@endif</div></details>
            @if ($bolehAset && !empty($item->snapshot['aset']))<details><summary>Aset yang digunakan saat ini</summary>@foreach ($item->snapshot['aset'] as $dataAset)@include('publikasi-humas._aset-rincian')@endforeach</details>@endif
        </li>@endforeach
    </ul>{{ $riwayat->links() }}</section>
</div>
@include('publikasi-humas._scripts')
@endsection
