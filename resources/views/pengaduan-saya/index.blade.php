@extends('layouts.app')
@section('title', 'Aspirasi & Pengaduan Saya - NUSA')
@section('content')
@include('pengaduan-humas._style')
<div class="publikasi-page pengaduan-page">
    <div class="page-header"><div><p class="eyebrow">Layanan Orang Tua</p><h1 class="page-title">Aspirasi & Pengaduan Saya</h1></div><a class="button button-primary" href="{{ route('pengaduan-saya.create') }}">Kirim laporan</a></div>
    @include('agenda-humas._messages')
    <div class="agenda-metrics agenda-metrics--three">
        @foreach (['total' => 'Laporan saya', 'aktif' => 'Dalam penanganan', 'selesai' => 'Selesai / ditutup'] as $key => $label)<div class="agenda-metric"><span>{{ $label }}</span><strong>{{ $statistik[$key] }}</strong></div>@endforeach
    </div>
    <section class="agenda-section">
        <form method="GET" action="{{ route('pengaduan-saya.index') }}" class="agenda-field-grid">
            <div class="field"><label for="kata_kunci">Cari laporan</label><input class="input" id="kata_kunci" name="kata_kunci" maxlength="120" value="{{ $filter['kata_kunci'] ?? '' }}"></div>
            <div class="field"><label for="status">Status</label><select class="select" id="status" name="status">@foreach (['semua' => 'Semua laporan', 'aktif' => 'Dalam penanganan', 'selesai' => 'Selesai / ditutup'] as $value => $label)<option value="{{ $value }}" @selected(($filter['status'] ?? 'semua') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="agenda-actions"><button class="button button-muted" type="submit">Tampilkan</button><a href="{{ route('pengaduan-saya.index') }}">Reset</a></div>
        </form>
    </section>
    <section class="agenda-section">
        <ul class="pengaduan-list">
            @forelse ($daftar as $tiket)
                <li class="pengaduan-row">
                    <div><span class="agenda-muted">{{ $tiket->nomor }} &middot; {{ $tiket->tanggal_diterima->format('d-m-Y') }}</span><h2><a href="{{ route('pengaduan-saya.show', $tiket->id) }}">{{ $tiket->judul }}</a></h2><p class="agenda-muted">{{ \App\Models\PengaduanHumas::JENIS[$tiket->jenis] }} &middot; {{ \App\Models\PengaduanHumas::KATEGORI[$tiket->kategori] }}</p></div>
                    <div><span class="pengaduan-status pengaduan-status--{{ $tiket->status }}">{{ \App\Models\PengaduanHumas::STATUS_ORANG_TUA[$tiket->status] }}</span></div>
                    <div class="agenda-actions"><a class="button button-muted" href="{{ route('pengaduan-saya.show', $tiket->id) }}">Lihat laporan</a></div>
                </li>
            @empty
                <li class="agenda-empty"><h2>Belum ada laporan</h2><p class="agenda-muted">{{ empty($filter['kata_kunci']) && ($filter['status'] ?? 'semua') === 'semua' ? 'Belum ada aspirasi atau pengaduan yang Anda kirim.' : 'Tidak ada laporan yang sesuai dengan filter.' }}</p></li>
            @endforelse
        </ul>
        {{ $daftar->links() }}
    </section>
</div>
@endsection
