@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Target Rapor Tahfidz - NUSA')
@include('tahfidz._assets')
@push('scripts')
    <script src="{{ asset('js/tahfidz-target.js') }}?v={{ filemtime(public_path('js/tahfidz-target.js')) }}" defer></script>
@endpush

@section('content')
    <div class="tf-page">
        <div class="page-header"><div><p class="eyebrow">Tahfidz / Rapor</p><h1 class="page-title">Target rapor</h1></div></div>
        @include('tahfidz._rapor-nav')
        @include('tahfidz._pesan')
        @if ($tahun->isEmpty())
            <p class="empty-state">Belum ada tahun pelajaran.</p>
        @else
            @php
                $periode = $tahun->firstWhere('id', $terpilih);
                $tanggal = $target?->tanggal_rapor?->toDateString() ?? $periode?->tanggal_selesai?->toDateString() ?? today()->toDateString();
                $pilihan = old('surah', $target?->surah ?? []);
            @endphp
            <form method="GET" action="{{ route('tahfidz.rapor.pengaturan') }}" class="feature-filter tf-filters">
                <div class="field"><label for="tf-tahun-target">Tahun pelajaran</label><select id="tf-tahun-target" name="tahun" class="select">
                    @foreach ($tahun as $item)<option value="{{ $item->id }}" @selected((int) $terpilih === $item->id)>{{ $item->nama }}</option>@endforeach
                </select></div>
                <div class="field"><label for="tf-tingkat-target">Tingkat</label><select id="tf-tingkat-target" name="tingkat" class="select">
                    @foreach ([7, 8, 9] as $item)<option value="{{ $item }}" @selected($tingkat === $item)>Tingkat {{ $item }}</option>@endforeach
                </select></div>
                <button type="submit" class="button button-muted">Tampilkan</button>
            </form>

            <form method="POST" action="{{ route('tahfidz.rapor.simpan') }}" class="tf-surface" data-tf-target-form>
                @csrf
                <input type="hidden" name="tahun_pelajaran_id" value="{{ $terpilih }}">
                <input type="hidden" name="tingkat" value="{{ $tingkat }}">
                <header class="tf-band"><h2>Tingkat {{ $tingkat }}</h2><span>{{ $periode?->nama }}</span></header>
                <div class="tf-form-body form-grid">
                    <div class="field"><label for="tf-target">Target / pembagi</label><input id="tf-target" name="target" class="input" type="number" min="1" max="37" required value="{{ old('target', $target?->target ?? [7 => 10, 8 => 20, 9 => 25][$tingkat]) }}"></div>
                    <div class="field"><label for="tf-tanggal-rapor">Tanggal rapor</label><input id="tf-tanggal-rapor" name="tanggal_rapor" class="input" type="date" required value="{{ old('tanggal_rapor', $tanggal) }}" @if($periode?->tanggal_mulai) min="{{ $periode->tanggal_mulai->toDateString() }}" @endif @if($periode?->tanggal_selesai) max="{{ $periode->tanggal_selesai->toDateString() }}" @endif></div>
                    <div class="field"><label for="tf-mode-target">Pilihan surah</label><select id="tf-mode-target" name="mode" class="select" data-tf-target-mode>
                        <option value="tetap" @selected(old('mode', $target?->mode) === 'tetap')>Surah ditentukan koordinator</option>
                        <option value="bebas" @selected(old('mode', $target?->mode ?? 'bebas') === 'bebas')>Siswa bebas memilih surah</option>
                    </select></div>
                </div>
                <fieldset class="tf-target-fieldset" data-tf-target-choices>
                    <legend>Surah target <span class="badge badge-muted" data-tf-target-count></span></legend>
                    <div class="tf-target-options">
                        @foreach ($surah as $item)
                            <label><input name="surah[]" type="checkbox" value="{{ $item->nomor }}" @checked(is_array($pilihan) && in_array($item->nomor, $pilihan))><span>{{ $item->nomor }}. {{ $item->nama }}</span></label>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset class="tf-target-fieldset">
                    <legend>Batas predikat</legend>
                    <div class="tf-thresholds">
                        @foreach (['cukup' => ['Cukup', 70, 1, 98], 'baik' => ['Baik', 80, 2, 99], 'sangat_baik' => ['Sangat Baik', 90, 3, 100]] as $kode => [$label, $bawaan, $min, $max])
                            @php($field = 'batas_'.$kode)
                            <div class="field"><label for="tf-{{ $field }}">{{ $label }} mulai dari</label><input id="tf-{{ $field }}" name="{{ $field }}" class="input" type="number" min="{{ $min }}" max="{{ $max }}" required value="{{ old($field, $target?->{$field} ?? $bawaan) }}"></div>
                        @endforeach
                    </div>
                </fieldset>
                <div class="tf-save"><button type="submit" class="button button-primary"><i data-lucide="save" aria-hidden="true"></i> Simpan target</button></div>
            </form>
        @endif
    </div>
@endsection
