@extends('layouts.app')
@section('title', 'Database Prestasi - NUSA')
@section('prestasi-actions')
    <a class="button button-muted" href="{{ route('prestasi-sekolah.cetak', \Illuminate\Support\Arr::except($filter, 'tab')) }}" target="_blank" rel="noopener">Cetak rekap</a>
    @izin('prestasi_sekolah.kelola')<a class="button button-primary" href="{{ route('prestasi-sekolah.create') }}">Tambah prestasi</a>@endizin
@endsection
@section('content')
<div class="publikasi-page prestasi-page">
    @include('prestasi-sekolah._header', ['judulHalaman' => 'Database prestasi sekolah'])
    <div class="agenda-metrics">@foreach(['total' => 'Prestasi sesuai filter', 'terverifikasi' => 'Terverifikasi', 'siswa' => 'Siswa NUSA berprestasi', 'sekolah' => 'Prestasi atas nama sekolah'] as $key => $label)<div class="agenda-metric"><span>{{ $label }}</span><strong>{{ $statistik[$key] }}</strong></div>@endforeach</div>
    <nav class="agenda-tabs" aria-label="Bagian prestasi"><a href="{{ route('prestasi-sekolah.index', array_replace($filter, ['tab' => 'data'])) }}" @if(($filter['tab'] ?? 'data') === 'data') aria-current="page" @endif>Data prestasi</a><a href="{{ route('prestasi-sekolah.index', array_replace($filter, ['tab' => 'statistik'])) }}" @if(($filter['tab'] ?? 'data') === 'statistik') aria-current="page" @endif>Statistik prestasi</a></nav>
    <section class="agenda-section"><form class="prestasi-filter" method="GET"><input type="hidden" name="tab" value="{{ $filter['tab'] ?? 'data' }}"><div class="field"><label for="kata_kunci">Cari kegiatan / penerima / capaian</label><input class="input" id="kata_kunci" name="kata_kunci" maxlength="120" value="{{ $filter['kata_kunci'] ?? '' }}"></div>
        <div class="field"><label for="tahun">Tahun prestasi</label><select class="select" id="tahun" name="tahun"><option value="">Semua tahun</option>@foreach($tahun as $t)<option value="{{ $t }}" @selected((string)($filter['tahun'] ?? '') === (string)$t)>{{ $t }}</option>@endforeach</select></div>
        <div class="field"><label for="tahun_pelajaran_id">Tahun pelajaran</label><select class="select" id="tahun_pelajaran_id" name="tahun_pelajaran_id"><option value="">Semua tahun pelajaran</option>@foreach($tahunPelajaran as $t)<option value="{{ $t->id }}" @selected((string)($filter['tahun_pelajaran_id'] ?? '') === (string)$t->id)>{{ $t->nama }}</option>@endforeach</select></div>
        @foreach(['kategori' => ['Kategori', \App\Models\PrestasiSekolah::KATEGORI], 'tingkat' => ['Tingkat', \App\Models\PrestasiSekolah::TINGKAT], 'perolehan' => ['Perolehan', \App\Models\PrestasiSekolah::PEROLEHAN], 'penerima' => ['Jenis penerima', \App\Models\PrestasiSekolah::PENERIMA], 'status' => ['Status data', ['aktif' => 'Draf & terverifikasi', ...\App\Models\PrestasiSekolah::STATUS, 'semua' => 'Semua status']]] as $key => [$label, $options])<div class="field"><label for="{{ $key }}">{{ $label }}</label><select class="select" id="{{ $key }}" name="{{ $key }}">@if($key !== 'status')<option value="">Semua</option>@endif @foreach($options as $code => $name)<option value="{{ $code }}" @selected(($filter[$key] ?? ($key === 'status' ? 'aktif' : '')) === $code)>{{ $name }}</option>@endforeach</select></div>@endforeach
        <div class="actions"><button type="submit" class="button button-primary">Terapkan</button><a class="button button-muted" href="{{ route('prestasi-sekolah.index', ['tab' => $filter['tab'] ?? 'data']) }}">Reset</a></div>
    </form></section>
    @if(($filter['tab'] ?? 'data') === 'statistik')
        @include('prestasi-sekolah._statistik')
    @else
        @forelse($daftar as $p)<article class="prestasi-row"><div><span class="prestasi-badge prestasi-badge--{{ $p->status }}">{{ \App\Models\PrestasiSekolah::STATUS[$p->status] }}</span><h2><a href="{{ route('prestasi-sekolah.show', $p) }}">{{ $p->capaian }}</a></h2><p><strong>{{ $p->nama_kegiatan }}</strong></p><p class="agenda-muted">{{ $p->tanggal_prestasi->translatedFormat('d M Y') }} &middot; {{ \App\Models\PrestasiSekolah::TINGKAT[$p->tingkat] }} &middot; {{ \App\Models\PrestasiSekolah::KATEGORI[$p->kategori] }}</p></div>
            <div><p><strong>{{ $p->penerima === 'sekolah' ? 'SMP Negeri 2 Padang Panjang' : ($p->bentuk === 'tim' ? $p->nama_tim : $p->peserta->first()?->nama) }}</strong></p><p class="agenda-muted">{{ \App\Models\PrestasiSekolah::PENERIMA[$p->penerima] }} &middot; {{ \App\Models\PrestasiSekolah::BENTUK[$p->bentuk] }} @if($p->bentuk === 'tim') &middot; {{ $p->peserta->count() }} penerima @endif</p></div><a class="button button-muted" href="{{ route('prestasi-sekolah.show', $p) }}">Buka prestasi</a></article>@empty<div class="agenda-empty"><strong>Belum ada prestasi yang sesuai filter.</strong></div>@endforelse
        {{ $daftar->links() }}
    @endif
    @izin('prestasi_sekolah.ekspor')<form method="POST" action="{{ route('prestasi-sekolah.export') }}" class="agenda-actions">@csrf @foreach($filter as $key => $value)@if(filled($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach<button type="submit" class="button button-muted">Ekspor Excel sesuai filter</button></form>@endizin
</div>
@endsection
