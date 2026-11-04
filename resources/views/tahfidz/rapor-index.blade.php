@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Rapor Tahfidz - NUSA')
@include('tahfidz._assets')

@section('content')
    <div class="tf-page">
        <div class="page-header"><div><p class="eyebrow">Akademik / Penilaian</p><h1 class="page-title">Rapor Tahfidz</h1></div></div>
        @include('tahfidz._rapor-nav')
        @include('tahfidz._pesan')

        @if (! $kelompok)
            <p class="empty-state">Belum ada kelompok yang bisa dicetak.</p>
        @else
            <form method="GET" action="{{ route('tahfidz.rapor.index') }}" class="feature-filter tf-filters tf-filter-kelompok-rapor">
                <div class="field"><label for="tf-rapor-kelompok">Kelompok Tahfidz</label><select id="tf-rapor-kelompok" name="kelompok" class="select">
                    @foreach ($kelompokList->groupBy('tahun_pelajaran_id') as $items)
                        <optgroup label="{{ $items->first()->tahunPelajaran->nama }}">
                            @foreach ($items as $item)<option value="{{ $item->id }}" @selected($kelompok->id === $item->id)>{{ $item->nama }}{{ $item->aktif ? '' : ' (nonaktif)' }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select></div>
                <button class="button button-muted" type="submit">Tampilkan</button>
            </form>

            <dl class="tf-facts tf-rekap-metrics tf-rekap-siswa-metrics">
                <div><dt>Anggota kelompok</dt><dd>{{ $daftar->count() }}</dd></div>
                <div><dt>Siap dicetak</dt><dd>{{ $siap }}</dd></div>
                <div><dt>Perlu pengaturan</dt><dd>{{ $daftar->count() - $siap }}</dd></div>
            </dl>

            <section class="tf-surface" aria-labelledby="tf-rapor-daftar">
                <header class="tf-band tf-rapor-band">
                    <div><h2 id="tf-rapor-daftar">{{ $kelompok->nama }}</h2><p class="person-meta">{{ $kelompok->tahunPelajaran->nama }} &middot; {{ $kelompok->pembimbing->nama_lengkap }}</p></div>
                    @if ($daftar->isNotEmpty() && $siap === $daftar->count())
                        <a href="{{ route('tahfidz.rapor.kelompok.cetak', $kelompok) }}" class="button button-primary" target="_blank" rel="noopener"><i data-lucide="printer" aria-hidden="true"></i> Cetak semua rapor</a>
                    @else
                        <button type="button" class="button button-muted" disabled><i data-lucide="printer" aria-hidden="true"></i> Cetak semua rapor</button>
                    @endif
                </header>
                @if ($siap < $daftar->count())
                    <p class="tf-rapor-warning">Lengkapi kelas dan target rapor yang belum tersedia sebelum mencetak seluruh kelompok.
                        @if ($bolehKelola)<a href="{{ route('tahfidz.rapor.pengaturan', ['tahun' => $kelompok->tahun_pelajaran_id]) }}">Atur target</a>@endif
                    </p>
                @endif
                <ul class="tf-list">
                    @forelse ($daftar as $item)
                        <li class="tf-rapor-row">
                            <div><p class="person-name">{{ $item['anggota']->siswa->nama_lengkap }}</p><p class="person-meta">{{ $item['kelas']?->nama ?? 'Belum ada kelas' }}</p></div>
                            <div>
                                @if ($item['target'])
                                    <p class="person-name">{{ $item['target']->target }} surah target</p><p class="person-meta">Tanggal rapor {{ $item['target']->tanggal_rapor->format('d/m/Y') }}</p>
                                @else
                                    <span class="badge badge-inactive">Perlu pengaturan</span><p class="person-meta">{{ $item['pesan'] }}</p>
                                @endif
                            </div>
                            @if ($item['target'])<a href="{{ route('tahfidz.rapor.cetak', ['kelompok' => $kelompok, 'anggota' => $item['anggota'], 'asal' => 'kelompok']) }}" class="button button-muted" aria-label="Pratinjau rapor {{ $item['anggota']->siswa->nama_lengkap }}"><i data-lucide="file-text" aria-hidden="true"></i> Pratinjau</a>@endif
                        </li>
                    @empty
                        <li class="empty-state">Belum ada anggota pada kelompok ini.</li>
                    @endforelse
                </ul>
            </section>
        @endif
    </div>
@endsection
