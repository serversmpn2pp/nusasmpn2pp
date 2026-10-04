@extends('layouts.app')
@section('title', 'Publikasi & Persetujuan - NUSA')
@section('content')
@include('publikasi-humas._style')
<div class="publikasi-page">
    <div class="page-header"><div><p class="eyebrow">Humas</p><h1 class="page-title">Publikasi & Persetujuan</h1></div>
        @izin('publikasi_humas.kelola')<a class="button button-primary" href="{{ route('publikasi-humas.create') }}">Buat draf</a>@endizin
    </div>
    @include('agenda-humas._messages')
    <p class="agenda-muted">Seluruh konten sekolah</p>
    <div class="agenda-metrics">
        @foreach (['diajukan' => 'Menunggu pemeriksaan', 'revisi' => 'Perlu revisi', 'disetujui' => 'Siap ditayangkan', 'tayang' => 'Sudah tayang'] as $kode => $label)
            <div class="agenda-metric"><span>{{ $label }}</span><strong>{{ $statistik[$kode] ?? 0 }}</strong></div>
        @endforeach
    </div>
    <nav class="agenda-tabs" aria-label="Status publikasi">
        <a href="{{ route('publikasi-humas.index') }}" @if (empty($filter['status'])) aria-current="page" @endif>Semua</a>
        @foreach (\App\Models\PublikasiHumas::STATUS as $kode => $label)
            <a href="{{ route('publikasi-humas.index', ['status' => $kode]) }}" @if (($filter['status'] ?? '') === $kode) aria-current="page" @endif>{{ $label }} <span class="agenda-count">{{ $statistik[$kode] ?? 0 }}</span></a>
        @endforeach
    </nav>
    <form method="GET" action="{{ route('publikasi-humas.index') }}">
        <div class="publikasi-filter">
            <div class="field"><label for="kata_kunci">Cari judul / ringkasan</label><input id="kata_kunci" name="kata_kunci" class="input" maxlength="120" value="{{ $filter['kata_kunci'] ?? '' }}"></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status" class="select"><option value="">Semua status</option>@foreach (\App\Models\PublikasiHumas::STATUS as $kode => $label)<option value="{{ $kode }}" @selected(($filter['status'] ?? '') === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="jenis">Jenis konten</label><select id="jenis" name="jenis" class="select"><option value="">Semua jenis</option>@foreach (\App\Models\PublikasiHumas::JENIS as $kode => $label)<option value="{{ $kode }}" @selected(($filter['jenis'] ?? '') === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="kanal">Media tujuan</label><select id="kanal" name="kanal" class="select"><option value="">Semua media</option>@foreach (\App\Models\PublikasiHumas::KANAL as $kode => $label)<option value="{{ $kode }}" @selected(($filter['kanal'] ?? '') === $kode)>{{ $label }}</option>@endforeach</select></div>
        </div>
        <div class="agenda-actions" style="margin-bottom:22px"><button type="submit" class="button button-primary">Tampilkan</button><a class="button button-muted" href="{{ route('publikasi-humas.index') }}">Reset</a></div>
    </form>
    <table class="agenda-table agenda-table--list publikasi-table"><thead><tr><th>Konten</th><th>Media / rencana</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse ($daftar as $item)
            <tr><td><a href="{{ route('publikasi-humas.show', $item) }}">{{ $item->judul }}</a><small>{{ \App\Models\PublikasiHumas::JENIS[$item->jenis] }} &middot; {{ $item->jumlah_foto }} foto</small><small>{{ $item->pembuat?->nama ?: 'Akun tidak tersedia' }}</small></td>
                <td data-label="Media">{{ \App\Models\PublikasiHumas::KANAL[$item->kanal] }}<small>{{ $item->rencana_tayang?->format('d-m-Y') ?: 'Belum dijadwalkan' }}</small></td>
                <td data-label="Status"><span class="publikasi-status publikasi-status--{{ $item->status }}">{{ \App\Models\PublikasiHumas::STATUS[$item->status] }}</span><small>{{ $item->updated_at->format('d-m-Y H:i') }}</small></td>
                <td><a class="button button-muted button-sm" href="{{ route('publikasi-humas.show', $item) }}">Buka</a></td></tr>
        @empty
            <tr><td colspan="4" class="agenda-empty">Belum ada konten yang sesuai.</td></tr>
        @endforelse
    </tbody></table>
    <div style="margin-top:18px">{{ $daftar->links() }}</div>
</div>
@endsection
