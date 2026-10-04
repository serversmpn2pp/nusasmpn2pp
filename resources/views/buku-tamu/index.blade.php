@extends('layouts.app')
@section('title', 'Buku Tamu Digital - NUSA')
@section('content')
@include('buku-tamu._style')
<div class="tamu-page">
    <div class="page-header"><div><p class="eyebrow">Humas</p><h1 class="page-title">Buku Tamu Digital</h1></div>@if ($bolehCatat)<a class="button button-primary" href="{{ route('buku-tamu.create') }}">Catat kedatangan</a>@endif</div>
    @include('buku-tamu._messages')
    @if ($bolehRekap)
        <nav class="tamu-tabs" aria-label="Tampilan buku tamu"><a href="{{ route('buku-tamu.index') }}" @if (!$rekap) aria-current="page" @endif>Operasional hari ini</a><a href="{{ route('buku-tamu.index', ['tab' => 'rekap']) }}" @if ($rekap) aria-current="page" @endif>Rekap kunjungan</a></nav>
    @endif
    <div class="tamu-metrics">
        <div class="tamu-metric"><span>Total kunjungan</span><strong>{{ $ringkasan['total'] }}</strong></div>
        <div class="tamu-metric tamu-metric--active"><span>Masih berkunjung</span><strong>{{ $ringkasan['berkunjung'] }}</strong></div>
        <div class="tamu-metric"><span>Sudah pulang</span><strong>{{ $ringkasan['selesai'] }}</strong></div>
        <div class="tamu-metric tamu-metric--cancel"><span>Dibatalkan</span><strong>{{ $ringkasan['dibatalkan'] }}</strong></div>
    </div>
    <form method="GET" action="{{ route('buku-tamu.index') }}">
        @if ($rekap)<input type="hidden" name="tab" value="rekap">@endif
        <div class="tamu-filter">
            <div class="field"><label for="kata_kunci">Cari tamu</label><input id="kata_kunci" class="input" name="kata_kunci" maxlength="180" value="{{ $filter['kata_kunci'] ?? '' }}" placeholder="Nama, instansi, atau pihak yang dituju"></div>
            <div class="field"><label for="kategori">Kategori</label><select id="kategori" class="select" name="kategori"><option value="">Semua kategori</option>@foreach (\App\Models\KunjunganTamu::KATEGORI as $kode => $nama)<option value="{{ $kode }}" @selected(($filter['kategori'] ?? '') === $kode)>{{ $nama }}</option>@endforeach</select></div>
            <div class="field"><label for="status">Status</label><select id="status" class="select" name="status"><option value="">Semua status</option>@foreach (\App\Models\KunjunganTamu::STATUS as $kode => $nama)<option value="{{ $kode }}" @selected(($filter['status'] ?? '') === $kode)>{{ $nama }}</option>@endforeach</select></div>
        </div>
        @if ($rekap)
            <div class="tamu-period">
                <div class="field"><label for="periode">Periode laporan</label><select class="select" id="periode" name="periode" data-periode>@foreach (['hari_ini' => 'Hari ini', 'bulan_ini' => 'Bulan ini', 'semester' => 'Semester', 'rentang' => 'Rentang tanggal'] as $kode => $nama)<option value="{{ $kode }}" @selected($filter['periode'] === $kode)>{{ $nama }}</option>@endforeach</select></div>
                <div class="tamu-period-fields" data-period-fields="semester" @if ($filter['periode'] !== 'semester') hidden @endif>
                    <div class="field"><label for="tahun_pelajaran_id">Tahun pelajaran</label><select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select"><option value="">Pilih tahun pelajaran</option>@foreach ($tahunPelajaran as $tahun)<option value="{{ $tahun->id }}" @selected(($filter['tahun_pelajaran_id'] ?? '') == $tahun->id)>{{ $tahun->nama }}</option>@endforeach</select></div>
                    <div class="field"><label for="semester">Semester</label><select class="select" id="semester" name="semester"><option value="ganjil" @selected(($filter['semester'] ?? '') === 'ganjil')>Ganjil</option><option value="genap" @selected(($filter['semester'] ?? '') === 'genap')>Genap</option></select></div>
                </div>
                <div class="tamu-period-fields" data-period-fields="rentang" @if ($filter['periode'] !== 'rentang') hidden @endif>
                    <div class="field"><label for="dari">Mulai tanggal</label><input class="input" type="date" name="dari" id="dari" value="{{ $filter['dari'] }}"></div><div class="field"><label for="sampai">Sampai tanggal</label><input class="input" type="date" name="sampai" id="sampai" value="{{ $filter['sampai'] }}"></div>
                </div>
            </div>
        @endif
        <div class="tamu-filter-actions"><button class="button button-primary" type="submit">Tampilkan</button><a class="button button-muted" href="{{ route('buku-tamu.index', $rekap ? ['tab' => 'rekap'] : []) }}">Reset</a>
            @if ($rekap)<div class="tamu-actions"><a class="button button-muted" href="{{ route('buku-tamu.export', $filter) }}">Ekspor Excel</a><a class="button button-muted" href="{{ route('buku-tamu.cetak', $filter) }}" target="_blank" rel="noopener">Cetak laporan</a></div>@endif
        </div>
    </form>
    @if ($rekap)<p class="tamu-hint">{{ $filter['label_periode'] }} &middot; {{ \Illuminate\Support\Carbon::parse($filter['dari'])->format('d-m-Y') }} s.d. {{ \Illuminate\Support\Carbon::parse($filter['sampai'])->format('d-m-Y') }}</p>@endif
    @if ($ringkasan['kategori']->isNotEmpty())<div class="tamu-summary">@foreach ($ringkasan['kategori'] as $kategori => $jumlah)<span>{{ \App\Models\KunjunganTamu::KATEGORI[$kategori] ?? $kategori }} <strong>{{ $jumlah }}</strong></span>@endforeach</div>@endif
    @if ($kunjungan->isEmpty())
        <div class="tamu-empty">Belum ada kunjungan yang sesuai.</div>
    @else
        <table class="tamu-list"><thead><tr><th>Tamu</th><th>Keperluan & tujuan</th><th>Waktu</th><th>Status</th><th></th></tr></thead><tbody>
            @foreach ($kunjungan as $item)<tr>
                <td><a href="{{ route('buku-tamu.show', $item) }}">{{ $item->nama_tamu }}</a><small>{{ $item->instansi ?: 'Tamu perorangan' }}</small><small>{{ \App\Models\KunjunganTamu::KATEGORI[$item->kategori] ?? $item->kategori }}</small></td>
                <td data-label="Keperluan & tujuan">{{ \Illuminate\Support\Str::limit($item->keperluan, 180) }}<small>Tujuan: {{ $item->nama_tujuan }}</small></td>
                <td data-label="Waktu">{{ $item->waktu_datang->format('d-m-Y') }}<small>Datang {{ $item->waktu_datang->format('H:i') }} WIB</small><small>{{ $item->waktu_pulang ? 'Pulang '.$item->waktu_pulang->format($item->waktu_datang->isSameDay($item->waktu_pulang) ? 'H:i' : 'd-m-Y H:i').' WIB' : 'Pulang belum dicatat' }}</small></td>
                <td data-label="Status"><span class="tamu-badge tamu-badge--{{ $item->status }}">{{ \App\Models\KunjunganTamu::STATUS[$item->status] ?? $item->status }}</span>@if ($item->status === 'berkunjung' && !$item->waktu_datang->isToday())<small>Sejak {{ $item->waktu_datang->format('d-m-Y') }}</small>@endif<small>{{ $item->lampiran_count }} lampiran</small></td>
                <td><a class="button button-muted button-sm" href="{{ route('buku-tamu.show', $item) }}">Rincian</a></td>
            </tr>@endforeach
        </tbody></table>
    @endif
    <div style="margin-top:20px">{{ $kunjungan->links() }}</div>
</div>
@include('buku-tamu._script')
@endsection
