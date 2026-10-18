@extends('layouts.app')
@section('admin-module', 'supervisi')
@section('title', 'Penilaian Supervisi - NUSA')
@section('content')
<div class="sp-workspace">
    @include('supervisi._pengisian-header', ['judul' => $penilaian['status'] === 'final' ? 'Hasil supervisi' : 'Penilaian observasi'])
    @if($penilaian['terlihat'])
        @if($hak['isi_penilaian'] && $penilaian['versi'] > 0 && $penilaian['praobservasi_versi'] !== $praobservasi['versi'])<p class="sp-note">Praobservasi telah diperbaiki setelah penilaian terakhir. Tinjau praobservasi dan simpan ulang penilaian sebelum finalisasi.</p>@endif
        @php
            $ringkasan = $penilaian['ringkasan'];
            $nilaiTersimpan = collect(old('nilai', $penilaian['nilai']))->keyBy(fn($n) => $n['kelompok'].':'.$n['indikator']);
            $isi = $hak['isi_penilaian'];
            $nomorBaris = 0;
        @endphp
        <div class="sp-summary">
            @foreach(['perencanaan' => 'Perencanaan', 'pelaksanaan' => 'Pelaksanaan'] as $stage => $label)
                <div class="sp-stat {{ $stage === 'pelaksanaan' ? 'teal' : '' }}"><span>{{ $label }} · Bobot 50%</span><strong data-sp-count="{{ $stage }}">{{ $ringkasan['tahap'][$stage]['terisi'] }} / {{ $ringkasan['tahap'][$stage]['jumlah_indikator'] }}</strong><span>{{ $ringkasan['tahap'][$stage]['nilai'] !== null ? 'Nilai '.number_format($ringkasan['tahap'][$stage]['nilai'], 2, ',', '.') : 'Penilaian belum lengkap' }}</span></div>
            @endforeach
            <div class="sp-stat amber"><span>{{ $penilaian['status'] === 'final' ? 'Nilai akhir' : 'Nilai akhir draf tersimpan' }}</span><strong>{{ $ringkasan['nilai_akhir'] !== null ? number_format($ringkasan['nilai_akhir'], 2, ',', '.') : '-' }}</strong><span>{{ $ringkasan['kategori'] ?? 'Menunggu seluruh indikator' }}</span></div>
        </div>
        @if($isi)<form method="POST" action="{{ route('supervisi.penilaian.update', $sesi['id']) }}" data-sp-editor>@csrf @method('PUT')<input type="hidden" name="versi" value="{{ $penilaian['versi'] }}"><input type="hidden" name="versi_sesi" value="{{ $sesi['versi'] }}"><input type="hidden" name="nilai_json" value="[]">@endif
        <section class="sp-band amber"><div class="sp-head"><h2>Pelaksanaan observasi</h2><span class="sp-badge {{ $penilaian['status'] }}">{{ $penilaian['status'] === 'final' ? 'Hasil final' : 'Draf privat supervisor' }}</span></div><div class="sp-body sp-grid">
            <div class="field"><label for="sp-observation-date">Tanggal observasi sebenarnya</label>
                @if($isi)<input class="input" type="date" id="sp-observation-date" name="tanggal_observasi" value="{{ old('tanggal_observasi', $penilaian['tanggal_observasi']) }}" min="{{ \Carbon\Carbon::parse($sesi['waktu_mulai'])->toDateString() }}" max="{{ now()->toDateString() }}">@else<strong>{{ $penilaian['tanggal_observasi'] ? \Carbon\Carbon::parse($penilaian['tanggal_observasi'])->format('d-m-Y') : 'Belum diisi' }}</strong>@endif
            </div><div><span class="sp-muted">Instrumen penilaian</span><strong>{{ $instrumen['nama'] }} · Versi {{ $instrumen['nomor'] }}</strong></div>
        </div></section>
        <section class="sp-band"><div class="sp-head"><h2>Skala penilaian</h2></div><div class="sp-body sp-scale">@foreach($instrumen['skala'] as $scale)<div><strong>{{ $scale['skor'] }}</strong><span class="sp-muted">{{ $scale['label'] }}</span></div>@endforeach</div></section>
        @foreach(['perencanaan' => 'Perencanaan / praobservasi', 'pelaksanaan' => 'Pelaksanaan / observasi'] as $stage => $label)
            <section class="sp-band {{ $stage === 'pelaksanaan' ? 'teal' : '' }}"><div class="sp-head"><h2>{{ $label }}</h2><span class="sp-badge">Maksimal {{ $ringkasan['tahap'][$stage]['skor_maksimal'] }} poin</span></div><div class="sp-body sp-score-groups">
                @foreach($instrumen['kelompok'] as $g => $group)
                    @if($group['tahap'] === $stage)
                        <details data-sp-score-group class="sp-score-group" @if($loop->first || $errors->any()) open @endif><summary><span>{{ $group['nama'] }}</span><span class="sp-badge" data-sp-group-count>{{ collect($nilaiTersimpan)->filter(fn($n) => (int)$n['kelompok'] === $g && ($n['skor'] ?? null) !== null)->count() }} / {{ count($group['indikator']) }} dinilai</span></summary>
                            @foreach($group['indikator'] as $i => $indicator)
                                @php $n = $nilaiTersimpan->get($g.':'.$i); $r = $nomorBaris++; @endphp
                                <div class="sp-score-row">
                                    <div class="sp-indicator-label"><span class="sp-item-number">{{ $i + 1 }}</span><label for="sp-score-{{ $g }}-{{ $i }}">{{ $indicator }}</label></div>
                                    <div class="field sp-score-control"><span class="sp-muted">Skor</span>
                                        @if($isi)
                                            <input type="hidden" name="nilai[{{ $r }}][kelompok]" value="{{ $g }}"><input type="hidden" name="nilai[{{ $r }}][indikator]" value="{{ $i }}">
                                            <select class="select" name="nilai[{{ $r }}][skor]" id="sp-score-{{ $g }}-{{ $i }}" data-sp-score="{{ $stage }}"><option value="">Belum dinilai</option>@foreach($instrumen['skala'] as $scale)<option value="{{ $scale['skor'] }}" @selected(($n['skor'] ?? null) !== null && (string)$n['skor'] === (string)$scale['skor'])>{{ $scale['skor'] }} - {{ $scale['label'] }}</option>@endforeach</select>
                                        @else<strong class="sp-score-value">{{ $n['skor'] ?? '-' }}</strong>@endif
                                    </div>
                                    <div class="field sp-evidence"><label for="sp-evidence-{{ $g }}-{{ $i }}">Catatan / bukti pengamatan</label>@if($isi)<textarea class="input" rows="2" maxlength="2000" id="sp-evidence-{{ $g }}-{{ $i }}" name="nilai[{{ $r }}][catatan]">{{ $n['catatan'] ?? '' }}</textarea>@else<p class="sp-prose">{{ $n['catatan'] ?? '-' }}</p>@endif</div>
                                </div>
                            @endforeach
                        </details>
                    @endif
                @endforeach
            </div></section>
        @endforeach
        <section class="sp-band teal"><div class="sp-head"><h2>Umpan balik supervisor</h2></div><div class="sp-body sp-grid">
            @foreach(['kekuatan' => 'Kekuatan pembelajaran', 'perbaikan' => 'Saran perbaikan', 'catatan' => 'Catatan tambahan'] as $field => $label)
                <div class="field {{ $field === 'catatan' ? 'sp-wide' : '' }}"><label for="sp-feedback-{{ $field }}">{{ $label }}</label>@if($isi)<textarea class="input" rows="4" id="sp-feedback-{{ $field }}" name="{{ $field }}" maxlength="5000">{{ old($field, $penilaian[$field]) }}</textarea>@else<p class="sp-prose">{{ $penilaian[$field] ?: '-' }}</p>@endif</div>
            @endforeach
        </div></section>
        @if($isi)
            <div class="sp-savebar"><span data-sp-save-state role="status">{{ $penilaian['versi'] ? 'Draf tersimpan. Nilai ringkasan mengikuti draf terakhir.' : 'Belum ada draf tersimpan' }}</span><button class="button button-primary">Simpan draf penilaian</button></div></form>
            @if($penilaian['versi'] > 0)
                <section class="sp-band amber"><div class="sp-head"><h2>Finalisasi penilaian</h2><span class="sp-badge">{{ $ringkasan['lengkap'] ? 'Indikator lengkap' : 'Indikator belum lengkap' }}</span></div><form class="sp-body" method="POST" action="{{ route('supervisi.penilaian.tindakan', [$sesi['id'], 'finalisasi']) }}" data-sp-secondary>
                    @csrf <input type="hidden" name="versi" value="{{ $penilaian['versi'] }}"><input type="hidden" name="versi_sesi" value="{{ $sesi['versi'] }}"><label class="sp-check"><input type="checkbox" name="konfirmasi" value="1" required><span>Skor dan catatan sudah diperiksa. Hasil final dikunci dan ditampilkan kepada guru.</span></label><button class="button button-primary" @disabled(!$ringkasan['lengkap'])>Finalisasi hasil</button>
                </form></section>
            @endif
        @endif
        @if($hak['buka_ulang_penilaian'])
            <section class="sp-band amber"><div class="sp-head"><h2>Koreksi hasil final</h2></div><form class="sp-body" method="POST" action="{{ route('supervisi.penilaian.tindakan', [$sesi['id'], 'buka-ulang']) }}">@csrf<input type="hidden" name="versi" value="{{ $penilaian['versi'] }}"><input type="hidden" name="versi_sesi" value="{{ $sesi['versi'] }}"><div class="field"><label for="sp-reopen-reason">Alasan koreksi</label><textarea class="input" id="sp-reopen-reason" name="alasan" minlength="10" maxlength="3000" rows="3" required>{{ old('alasan') }}</textarea></div><label class="sp-check"><input type="checkbox" name="konfirmasi" value="1" required><span>Tarik hasil dari tampilan guru sampai supervisor memfinalisasi ulang. Riwayat hasil sebelumnya tetap disimpan.</span></label><button class="button button-muted">Buka koreksi penilaian</button></form></section>
        @endif
    @else
        <div class="sp-empty"><h2>Hasil belum tersedia</h2><p>{{ $praobservasi['status'] === 'terkirim' ? 'Penilaian supervisor belum difinalisasi.' : 'Praobservasi belum dikirim kepada supervisor.' }}</p></div>
    @endif
</div>
@include('supervisi._pengisian-script')
@endsection
