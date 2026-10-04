@extends('layouts.app')
@section('title', 'Undangan Orang Tua - Agenda Humas')
@section('content')
    @include('agenda-humas._style')
    <div class="page-header"><div class="agenda-heading"><p class="eyebrow">Humas / Undangan Pertemuan</p><h1 class="page-title">Pilih orang tua / wali</h1><p class="help-text">{{ $agendaHumas->judul }}</p></div><a class="button button-muted" href="{{ route('agenda-humas.show', [$agendaHumas, 'tab' => 'qr']) }}">Kembali</a></div>
    @include('agenda-humas._messages')
    @if ($agendaHumas->status !== 'terjadwal')<div class="alert alert-warning">Agenda tidak terjadwal. Undangan baru tidak dapat ditambahkan.</div>@endif
    <section class="agenda-section">
        <form method="GET" action="{{ route('agenda-humas.undangan', $agendaHumas) }}" class="agenda-actions" style="margin-top:0">
            <div class="field"><label for="tahun_undangan">Tahun pelajaran</label><select id="tahun_undangan" name="tahun_pelajaran_id" class="select">@foreach ($tahunPelajaran as $tahun)<option value="{{ $tahun->id }}" @selected($tahunId === $tahun->id)>{{ $tahun->nama }}</option>@endforeach</select></div>
            <button class="button button-muted">Muat pilihan kelas</button>
        </form>
        <form method="GET" action="{{ route('agenda-humas.undangan', $agendaHumas) }}" style="margin-top:24px">
            <input type="hidden" name="tahun_pelajaran_id" value="{{ $tahunId }}">
            <fieldset class="agenda-scope"><legend>Cakupan undangan</legend>
                @foreach (['kelas' => 'Per kelas', 'tingkat' => 'Per tingkat', 'seluruh' => 'Seluruh sekolah'] as $kode => $label)
                    <label><input type="radio" name="cakupan" value="{{ $kode }}" @checked(request('cakupan', 'kelas') === $kode)> {{ $label }}</label>
                @endforeach
            </fieldset>
            <div data-scope="kelas" class="agenda-class-options">@forelse ($kelas as $item)<label><input type="checkbox" name="kelas_ids[]" value="{{ $item->id }}" @checked(in_array($item->id, (array) request('kelas_ids', [])))> {{ $item->nama }}</label>@empty<p class="agenda-muted">Belum ada kelas aktif pada tahun pelajaran ini.</p>@endforelse</div>
            <div class="field" data-scope="tingkat"><label for="tingkat_undangan">Tingkat</label><select class="select" name="tingkat" id="tingkat_undangan">@foreach ($kelas->pluck('tingkat')->unique() as $tingkat)<option value="{{ $tingkat }}" @selected((int) request('tingkat') === $tingkat)>Tingkat {{ $tingkat }}</option>@endforeach</select></div>
            <div class="agenda-actions"><button class="button button-primary" @disabled($kelas->isEmpty())>Pratinjau undangan</button></div>
        </form>
    </section>
    @if ($pratinjau)
        <div class="agenda-metrics agenda-metrics--three"><div class="agenda-metric"><span>Siswa dalam pilihan</span><strong>{{ $pratinjau['jumlahSiswa'] }}</strong></div><div class="agenda-metric"><span>Akun orang tua aktif</span><strong>{{ $pratinjau['undangan']->count() }}</strong></div><div class="agenda-metric agenda-metric--warning"><span>Siswa tanpa akun orang tua aktif</span><strong>{{ $pratinjau['tanpaAkun']->count() }}</strong></div></div>
        <section class="agenda-section">
            <h2>Pratinjau penerima</h2>
            <div class="table-wrap agenda-preview-list"><table class="agenda-table"><thead><tr><th>Orang tua / wali</th><th>Siswa & kelas</th></tr></thead><tbody>@forelse ($pratinjau['undangan'] as $item)<tr><td>{{ $item['nama'] }}</td><td>@foreach ($item['anak'] as $anak)<span class="agenda-child">{{ $anak['nama'] }} &middot; {{ $anak['kelas'] }}</span>@endforeach</td></tr>@empty<tr><td colspan="2">Tidak ada penerima pada pilihan ini.</td></tr>@endforelse</tbody></table></div>
            @if ($pratinjau['undangan']->isNotEmpty() && $agendaHumas->status === 'terjadwal')
                <form method="POST" action="{{ route('agenda-humas.undangan.store', $agendaHumas) }}" data-agenda-submit class="agenda-actions">
                    @csrf
                    @foreach ($filter as $key => $value)@if (is_array($value))@foreach ($value as $id)<input type="hidden" name="{{ $key }}[]" value="{{ $id }}">@endforeach @else<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
                    <button class="button button-primary">Tambahkan {{ $pratinjau['undangan']->count() }} undangan</button>
                </form>
            @endif
        </section>
        @if ($pratinjau['tanpaAkun']->isNotEmpty())<section class="agenda-section"><h2 class="agenda-warning">Belum bisa menggunakan QR</h2><div class="table-wrap agenda-preview-list"><table class="agenda-table"><thead><tr><th>Siswa</th><th>Kelas</th></tr></thead><tbody>@foreach ($pratinjau['tanpaAkun'] as $anak)<tr><td>{{ $anak['nama'] }}</td><td>{{ $anak['kelas'] }}</td></tr>@endforeach</tbody></table></div><div class="agenda-actions"><a class="button button-muted" href="{{ route('agenda-humas.show', [$agendaHumas, 'tab' => 'peserta']) }}">Tambah peserta manual</a></div></section>@endif
    @endif
    @include('agenda-humas._scripts')
@endsection
