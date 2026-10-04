@extends('layouts.app')
@section('title', 'Rekap Umpan Balik Orang Tua - NUSA')
@section('uf-actions')<a class="button button-muted" href="{{ route('umpan-balik-humas.index') }}">Daftar formulir</a><a class="button button-muted" href="{{ route('umpan-balik-humas.cetak', $formulir) }}" target="_blank" rel="noopener">Cetak rekap</a>@endsection
@section('content')
<main class="uf-page">
    @include('umpan-balik-humas._header', ['judulHalaman' => $formulir->judul])
    <span class="uf-badge uf-badge--{{ $formulir->status }}">{{ $formulir->labelStatus() }}</span>
    <dl class="uf-facts"><div><dt>Tahun pelajaran</dt><dd>{{ $formulir->tahunPelajaran->nama }}</dd></div><div><dt>Sasaran</dt><dd>{{ \App\Models\UmpanBalikHumas::CAKUPAN[$formulir->cakupan] }}</dd></div><div><dt>Penanggung jawab</dt><dd>{{ $formulir->penanggung_jawab }}</dd></div></dl>
    <p class="agenda-muted">{{ $formulir->mulai_pada->format('d-m-Y H:i') }} s.d. {{ $formulir->selesai_pada->format('d-m-Y H:i') }} WIB</p>
    <p class="agenda-text">{{ $formulir->pengantar }}</p>
    @if($preview !== null)<p class="uf-note">Sasaran saat ini: <strong>{{ $preview['undangan']->count() }} akun orang tua aktif</strong>. @if($formulir->cakupan !== 'agenda'){{ $preview['tanpaAkun']->count() }} siswa pada cakupan ini belum memiliki akun orang tua aktif.@endif</p>@endif
    @if($formulir->dibuka_pada)<p class="uf-note">Pertanyaan dan sasaran sudah dikunci. Rekap tidak menampilkan nama akun; jawaban tertulis dapat memuat identitas yang dituliskan sendiri oleh pengisi.</p>@endif
    <div class="agenda-metrics"><div class="agenda-metric"><span>Sasaran saat dibuka</span><strong>{{ $rekap['sasaran'] }}</strong></div><div class="agenda-metric"><span>Respons terkirim</span><strong>{{ $rekap['respons'] }}</strong></div><div class="agenda-metric"><span>Tingkat respons</span><strong>{{ number_format($rekap['persen'],1,',','.') }}%</strong></div><div class="agenda-metric"><span>Belum mengirim</span><strong>{{ $rekap['sasaran'] - $rekap['respons'] }}</strong></div></div>
    @if($bolehKelola && !$formulir->dibuka_pada && $formulir->status === 'draf')<div class="actions" style="margin-bottom:20px"><a class="button button-muted" href="{{ route('umpan-balik-humas.edit', $formulir) }}">Edit pertanyaan & sasaran</a></div>@endif
    <section class="uf-section"><h2>Rekap per pertanyaan</h2><p class="agenda-muted">Rata-rata dan persentase puas hanya memakai nilai 1-4; pilihan Tidak menilai dan jawaban kosong tidak dihitung.</p>@include('umpan-balik-humas._rekap')</section>
    @if($jawabanTeks !== null)<section class="uf-section" id="jawaban-tertulis"><h2>Jawaban tertulis</h2><h3>{{ $pilihanTeks->teks }}</h3><ol class="uf-comments" start="{{ $jawabanTeks->firstItem() }}">@foreach($jawabanTeks as $j)<li>{{ $j->teks }}</li>@endforeach</ol>{{ $jawabanTeks->fragment('jawaban-tertulis')->links() }}</section>@endif
    <section class="uf-section"><h2>Tindak lanjut perbaikan</h2>
        @forelse($formulir->tindakLanjut as $t)<article class="uf-results"><span class="uf-badge uf-badge--{{ $t->status }}">{{ \App\Models\TindakLanjutUmpanBalikHumas::STATUS[$t->status] }}</span>@if($t->terlambat()) <span class="uf-badge uf-badge--diproses">Lewat tenggat</span>@endif
            <h3 style="margin-top:12px">{{ $t->uraian }}</h3><p class="agenda-muted">{{ $t->penanggung_jawab }} &middot; Batas {{ $t->batas_tanggal->format('d-m-Y') }}</p>@if($t->hasil)<p class="agenda-text">{{ $t->hasil }}</p>@endif
            @if($t->bagikan_ringkasan)<p class="uf-note"><strong>Ringkasan untuk orang tua</strong><br>{{ $t->ringkasan_publik }}</p>@endif
            @if($bolehKelola && $formulir->status !== 'arsip')<details class="uf-disclosure" @if(old('form_tindak') === (string)$t->id) open @endif><summary>Edit tindak lanjut</summary>@include('umpan-balik-humas._tindak-form', ['tindak' => $t])</details>@endif
        </article>@empty<p class="agenda-muted">Belum ada tindak lanjut yang dicatat.</p>@endforelse
        @if($bolehKelola && $formulir->dibuka_pada && $formulir->status !== 'arsip')<details class="uf-disclosure" @if(old('form_tindak') === 'baru') open @endif><summary>Tambah tindak lanjut</summary>@include('umpan-balik-humas._tindak-form', ['tindak' => null])</details>@endif
    </section>
    @if($bolehKelola)<details class="uf-disclosure"><summary>Status & periode pengisian</summary>
        <form method="POST" action="{{ route('umpan-balik-humas.status', $formulir) }}" class="uf-grid" data-save data-confirm="Simpan perubahan status ini? Pertanyaan dan sasaran dikunci setelah formulir dibuka.">@csrf<input type="hidden" name="versi" value="{{ $formulir->versi }}">
            <div class="field"><label for="status_baru">Tindakan</label><select class="select" id="status_baru" name="status">
                @if($formulir->status === 'draf')<option value="aktif">Buka formulir</option><option value="arsip">Arsipkan draf</option>
                @elseif($formulir->status === 'aktif')<option value="ditutup">Tutup pengisian</option><option value="aktif">Perpanjang periode</option><option value="arsip">Arsipkan</option>
                @elseif($formulir->status === 'ditutup')<option value="aktif">Buka kembali</option><option value="arsip">Arsipkan</option>
                @else<option value="{{ $formulir->dibuka_pada ? 'ditutup' : 'draf' }}">Pulihkan dari arsip</option>@endif
            </select></div>
            @if($formulir->dibuka_pada)<div class="field" id="batas-baru-field"><label for="batas_baru">Batas pengisian baru (WIB)</label><input class="input" type="datetime-local" name="selesai_pada" id="batas_baru" value="{{ $formulir->selesai_pada->format('Y-m-d\TH:i') }}"></div>@endif
            <div class="field uf-wide"><label for="alasan_status">Alasan / catatan perubahan</label><textarea class="input" id="alasan_status" name="alasan" minlength="5" maxlength="2000" required></textarea></div><div class="actions uf-wide"><button class="button button-primary" type="submit">Simpan status</button></div>
        </form>
    </details>@endif
    <details class="uf-disclosure" @if(request()->has('riwayat')) open @endif><summary>Riwayat pengelolaan</summary><ul class="uf-history">@foreach($riwayat as $r)<li><strong>{{ $r->aksi }}</strong><p class="agenda-muted">{{ $r->created_at->format('d-m-Y H:i') }} &middot; {{ $r->pengguna?->nama ?? 'Akun tidak tersedia' }} &middot; versi {{ $r->versi }}</p>@if($r->catatan)<p>{{ $r->catatan }}</p>@endif</li>@endforeach</ul>{{ $riwayat->links() }}</details>
</main>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form:has([data-share])').forEach(form => {
        const state=form.querySelector('[data-follow-status]'),share=form.querySelector('[data-share]'),section=form.querySelector('[data-public]'),text=section.querySelector('textarea'),result=form.querySelector('[data-follow-result]');
        const sync=()=>{result.required=state.value==='selesai';share.disabled=state.value!=='selesai';if(share.disabled)share.checked=false;section.hidden=!share.checked;text.disabled=!share.checked;text.required=share.checked;};
        state.addEventListener('change',sync);share.addEventListener('change',sync);sync();
    });
    const status=document.querySelector('#status_baru'),field=document.querySelector('#batas-baru-field');
    if(status && field){const sync=()=>{field.hidden=status.value!=='aktif';field.querySelector('input').disabled=field.hidden;field.querySelector('input').required=!field.hidden;};status.addEventListener('change',sync);sync();}
});
</script>
@endsection
