@extends('layouts.app')
@section('title', 'Database Alumni - NUSA')
@section('alumni-actions')
    <a class="button button-muted" href="{{ route('alumni-humas.cetak', \Illuminate\Support\Arr::except($filter, 'tab')) }}" target="_blank" rel="noopener">Cetak rekap</a>
    @if(auth()->user()->memilikiIzin('alumni_humas.kelola'))<a class="button button-primary" href="{{ route('alumni-humas.create') }}">Tambah alumni</a>@endif
@endsection
@section('content')
<div class="publikasi-page alumni-page">
    @include('alumni-humas._header', ['judulHalaman' => 'Database alumni'])
    <div class="agenda-metrics">@foreach(['total' => 'Alumni sesuai filter', 'melanjutkan' => 'Melanjutkan pendidikan', 'tidak_melanjutkan' => 'Tidak melanjutkan', 'belum_terdata' => 'Belum terdata'] as $key => $label)<div class="agenda-metric"><span>{{ $label }}</span><strong>{{ $statistik[$key] }}</strong></div>@endforeach</div>
    <nav class="agenda-tabs" aria-label="Bagian alumni"><a href="{{ route('alumni-humas.index', array_replace($filter, ['tab' => 'data'])) }}" @if(($filter['tab'] ?? 'data') === 'data')aria-current="page"@endif>Data alumni</a><a href="{{ route('alumni-humas.index', array_replace($filter, ['tab' => 'statistik'])) }}" @if(($filter['tab'] ?? 'data') === 'statistik')aria-current="page"@endif>Statistik & sekolah lanjutan</a></nav>
    <section class="agenda-section"><form method="GET" class="alumni-filter"><input type="hidden" name="tab" value="{{ $filter['tab'] ?? 'data' }}">
        <div class="field"><label for="kata_kunci">Cari nama / NISN / sekolah</label><input class="input" id="kata_kunci" name="kata_kunci" maxlength="120" value="{{ $filter['kata_kunci'] ?? '' }}"></div>
        <div class="field"><label for="tahun_lulus">Angkatan (tahun lulus)</label><select class="select" id="tahun_lulus" name="tahun_lulus"><option value="">Semua angkatan</option>@foreach($tahun as $t)<option value="{{ $t }}" @selected((string)($filter['tahun_lulus'] ?? '') === (string)$t)>{{ $t }}</option>@endforeach</select></div>
        @foreach(['status_penelusuran' => ['Penelusuran', \App\Models\AlumniHumas::PENELUSURAN], 'jenis_sekolah' => ['Jenis sekolah', \App\Models\AlumniHumas::SEKOLAH], 'jenis_kelamin' => ['Jenis kelamin', ['L' => 'Laki-laki', 'P' => 'Perempuan']], 'status' => ['Status data', ['aktif' => 'Aktif', 'arsip' => 'Diarsipkan', 'semua' => 'Semua status']]] as $key => [$label, $pilihan])<div class="field"><label for="{{ $key }}">{{ $label }}</label><select class="select" id="{{ $key }}" name="{{ $key }}">@if($key !== 'status')<option value="">Semua</option>@endif @foreach($pilihan as $kode => $nama)<option value="{{ $kode }}" @selected(($filter[$key] ?? ($key === 'status' ? 'aktif' : '')) === $kode)>{{ $nama }}</option>@endforeach</select></div>@endforeach
        <div class="actions"><button type="submit" class="button button-primary">Terapkan</button><a class="button button-muted" href="{{ route('alumni-humas.index', ['tab' => $filter['tab'] ?? 'data']) }}">Reset</a></div>
    </form></section>
    @if(($filter['tab'] ?? 'data') === 'statistik')
        @include('alumni-humas._statistik')
    @else
        @forelse($daftar as $a)<article class="alumni-row"><div><h2><a href="{{ route('alumni-humas.show', $a) }}">{{ $a->nama_lengkap }}</a></h2><p>Angkatan {{ $a->tahun_lulus }} &middot; {{ $a->kelas_terakhir ?: 'Kelas belum tercatat' }}</p><p class="agenda-muted">NISN: {{ $a->nisn ?: '-' }} &middot; {{ $a->siswa_id ? 'Siswa NUSA' : 'Input manual' }}</p>@if($a->status === 'arsip')<span class="alumni-tag">Diarsipkan</span>@endif</div>
            <div><span class="alumni-tag alumni-tag--{{ $a->status_penelusuran }}">{{ \App\Models\AlumniHumas::PENELUSURAN[$a->status_penelusuran] }}</span>@if($a->status_penelusuran === 'melanjutkan')<p><strong>{{ $a->nama_sekolah }}</strong></p><p class="agenda-muted">{{ \App\Models\AlumniHumas::SEKOLAH[$a->jenis_sekolah] }}{{ $a->kota_sekolah ? ' - '.$a->kota_sekolah : '' }}</p>@endif</div>
            <a class="button button-muted" href="{{ route('alumni-humas.show', $a) }}">Buka alumni</a></article>@empty<div class="agenda-empty"><strong>Belum ada alumni yang sesuai.</strong></div>@endforelse
        {{ $daftar->links() }}
    @endif
    @if(auth()->user()->memilikiIzin('alumni_humas.ekspor'))<form class="alumni-export" method="POST" action="{{ route('alumni-humas.export') }}">@csrf @foreach($filter as $key => $value)@if(filled($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
        @if(auth()->user()->memilikiIzin('alumni_humas.kelola'))<label class="alumni-check"><input type="checkbox" name="sertakan_kontak" value="1">Sertakan nomor WA dan email privat</label>@endif
        <button type="submit" class="button button-muted">Ekspor Excel sesuai filter</button>
    </form>@endif
</div>
@endsection
