@extends('layouts.app')
@section('admin-module', 'humas')
@section('title', 'Dashboard Humas - NUSA')
@section('content')
@include('dashboard-humas._style')
<div class="humas-dashboard">
    <header class="hd-head">
        <div><span class="hd-kicker">Humas</span><h1>Dashboard Humas</h1><p class="hd-muted">{{ $labelPeriode }} &middot; {{ $mulai->format('d-m-Y') }} s.d. {{ $selesai->format('d-m-Y') }}</p></div>
        <a class="button button-muted hd-command" href="{{ route('dashboard-humas.cetak', $filter) }}" target="_blank" rel="noopener"><i data-lucide="printer" aria-hidden="true"></i>Cetak ringkasan</a>
    </header>
    @if($ruangKerja)
    <section class="hd-workspace" aria-labelledby="hd-workspace-title">
        <div class="hd-workspace-heading"><h2 id="hd-workspace-title">Bidang kerja Humas</h2></div>
        <div class="hd-workspace-grid">
            @foreach($ruangKerja as $ruang)
            <article class="hd-workspace-card hd-workspace--{{ $ruang['warna'] }}" data-humas-workspace="{{ $ruang['kode'] }}" aria-labelledby="hd-workspace-{{ $ruang['kode'] }}">
                <header><span class="hd-workspace-icon"><i data-lucide="{{ $ruang['ikon'] }}" aria-hidden="true"></i></span><h3 id="hd-workspace-{{ $ruang['kode'] }}">{{ $ruang['label'] }}</h3></header>
                <ul>
                    @foreach($ruang['items'] as $item)
                    <li><a href="{{ route($item['route'], $item['parameters']) }}" data-humas-menu="{{ $item['kode'] }}"><i data-lucide="{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span><i class="hd-workspace-arrow" data-lucide="arrow-up-right" aria-hidden="true"></i></a></li>
                    @endforeach
                </ul>
            </article>
            @endforeach
        </div>
    </section>
    @endif
    @if($layananPendukung)
    <nav class="hd-support" aria-label="Layanan pendukung Humas">
        <span>Layanan pendukung</span>
        @foreach($layananPendukung as $item)
        <a href="{{ route($item['route'], $item['parameters']) }}" data-humas-menu="{{ $item['kode'] }}"><i data-lucide="{{ $item['icon'] }}" aria-hidden="true"></i>{{ $item['label'] }}<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
        @endforeach
    </nav>
    @endif
    <form method="GET" action="{{ route('dashboard-humas.index') }}" class="feature-filter hd-filter" data-dashboard-filter>
        <div class="field"><label for="tahun_pelajaran_id">Tahun pelajaran</label><select class="select" id="tahun_pelajaran_id" name="tahun_pelajaran_id">
            <option value="">Pilih tahun pelajaran</option>@foreach($tahun as $t)<option value="{{ $t->id }}" @selected((string)($filter['tahun_pelajaran_id'] ?? '') === (string)$t->id)>{{ $t->nama }}</option>@endforeach
        </select></div>
        <div class="field"><label for="periode">Periode</label><select class="select" id="periode" name="periode">
            @foreach(['tahunan' => 'Satu tahun pelajaran', 'ganjil' => 'Semester ganjil', 'genap' => 'Semester genap', 'kustom' => 'Rentang tanggal'] as $kode => $label)<option value="{{ $kode }}" @selected($filter['periode'] === $kode)>{{ $label }}</option>@endforeach
        </select></div>
        <div class="field"><label for="tanggal_mulai">Dari tanggal</label><input class="input" id="tanggal_mulai" name="tanggal_mulai" type="date" min="1900-01-01" max="2100-12-31" value="{{ $filter['tanggal_mulai'] }}" @disabled($filter['periode'] !== 'kustom')></div>
        <div class="field"><label for="tanggal_selesai">Sampai tanggal</label><input class="input" id="tanggal_selesai" name="tanggal_selesai" type="date" min="1900-01-01" max="2100-12-31" value="{{ $filter['tanggal_selesai'] }}" @disabled($filter['periode'] !== 'kustom')></div>
        <div class="actions"><button type="submit" class="button button-primary">Terapkan</button><a class="button button-muted" href="{{ route('dashboard-humas.index') }}">Reset</a></div>
    </form>
    @if(!$metrik)
        <p class="hd-empty">Belum ada ringkasan yang dapat ditampilkan untuk hak akses akun ini.</p>
    @else
        <div class="hd-section-head"><h2 style="font-size:1.08rem;margin:0">Capaian periode</h2><span class="hd-muted">Diperbarui {{ now()->format('d-m-Y H:i') }} WIB</span></div>
        <div class="hd-metrics">@foreach($metrik as $kode => $item)
            <a class="hd-metric hd-color-{{ $item['warna'] }}" href="{{ $item['url'] }}" data-metric="{{ $kode }}"><span class="hd-metric-label">{{ $item['label'] }}</span><strong>{{ number_format($item['jumlah'], 0, ',', '.') }}</strong><small>{{ $item['dasar'] }}</small></a>
        @endforeach</div>
        @if($distribusi)
        <div class="hd-grid">@foreach($distribusi as $bagian)
            <section class="hd-section"><div class="hd-section-head"><h2>{{ $bagian['judul'] }}</h2><span class="hd-muted">{{ array_sum($bagian['jumlah']) }} total</span></div>
                @foreach($bagian['label'] as $kode => $label)<div class="hd-status hd-status--{{ $kode }}"><span>{{ $label }}</span><div class="hd-track" aria-hidden="true"><span style="width:{{ array_sum($bagian['jumlah']) ? round($bagian['jumlah'][$kode] / array_sum($bagian['jumlah']) * 100, 2) : 0 }}%"></span></div><strong>{{ $bagian['jumlah'][$kode] }}</strong></div>@endforeach
            </section>
        @endforeach</div>
        @endif
        <div class="hd-grid">
            <section class="hd-section"><div class="hd-section-head"><h2>Perlu perhatian saat ini</h2><span class="hd-muted">Seluruh periode &middot; {{ today()->format('d-m-Y') }}</span></div>
                <ul class="hd-attention">@forelse($perhatian as $item)<li><a href="{{ $item['url'] }}"><span>{{ $item['label'] }}</span><span class="hd-count hd-count--{{ $item['warna'] }}">{{ $item['jumlah'] }}</span></a></li>@empty<li><p class="hd-empty">Tidak ada tindak lanjut yang perlu perhatian dari data yang dapat Anda akses.</p></li>@endforelse</ul>
            </section>
            @if(isset($distribusi['agenda']))<section class="hd-section"><div class="hd-section-head"><h2>Agenda terdekat</h2><span class="hd-muted">Hari ini sampai 14 hari ke depan</span></div>
                <ul class="hd-agenda">@forelse($agenda as $item)<li><time datetime="{{ $item->waktu_mulai->toIso8601String() }}">{{ $item->waktu_mulai->locale('id')->translatedFormat('D, d M Y') }} &middot; {{ $item->waktu_mulai->format('H:i') }}</time><a href="{{ route('agenda-humas.show', $item) }}">{{ $item->judul }}</a><p>{{ $item->tempat }} &middot; {{ \App\Models\AgendaHumas::JENIS[$item->jenis] }}</p></li>@empty<li><p class="hd-empty">Belum ada agenda terjadwal dalam 14 hari ke depan.</p></li>@endforelse</ul>
            </section>@endif
        </div>
        @if(isset($distribusi['program']))
        <section class="hd-section"><div class="hd-section-head"><h2>Pantauan program kerja</h2><a href="{{ route('program-kerja-humas.index', array_filter(['tahun_pelajaran_id' => $filter['tahun_pelajaran_id']])) }}">Semua program</a></div>
            <table class="hd-table hd-program-table"><colgroup><col class="hd-program-name"><col class="hd-program-status"><col class="hd-program-date"><col class="hd-program-result"></colgroup><thead><tr><th>Program</th><th>Status</th><th>Target selesai</th><th class="hd-number">Laporan final</th></tr></thead><tbody>
                @forelse($program as $item)<tr><td data-label="Program"><a href="{{ route('program-kerja-humas.show', $item) }}">{{ $item->nama }}</a></td><td data-label="Status"><div>{{ \App\Models\ProgramKerjaHumas::STATUS[$item->status] }}@if($item->terlambat())<small>Lewat target</small>@endif</div></td><td data-label="Target selesai">{{ $item->tanggal_selesai->format('d-m-Y') }}</td><td class="hd-number" data-label="Laporan final"><div>{{ $item->realisasi_periode }}<small>Target program: {{ $item->target_kegiatan }}</small></div></td></tr>@empty<tr><td colspan="4">Belum ada program pada periode ini.</td></tr>@endforelse
            </tbody></table><p class="hd-note">{{ min(8, $jumlahProgram) }} dari {{ $jumlahProgram }} program. Laporan final dihitung dalam periode terpilih; target adalah target keseluruhan program.</p>
        </section>
        @endif
        @if($kolomBulanan)
        <section class="hd-section"><div class="hd-section-head"><h2>Rekap bulanan</h2><span class="hd-muted">{{ $mulai->format('d-m-Y') }} s.d. {{ $selesai->format('d-m-Y') }}</span></div>
            @include('dashboard-humas._bulanan')
        </section>
        @endif
    @endif
</div>
@include('dashboard-humas._scripts')
@endsection
