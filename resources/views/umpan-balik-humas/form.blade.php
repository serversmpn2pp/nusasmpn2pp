@extends('layouts.app')
@section('title', 'Formulir Evaluasi Orang Tua - NUSA')
@section('uf-actions')<a class="button button-muted" href="{{ $formulir->exists ? route('umpan-balik-humas.show', $formulir) : route('umpan-balik-humas.index') }}">Kembali</a>@endsection
@section('content')
<main class="uf-page">
    @include('umpan-balik-humas._header', ['judulHalaman' => $formulir->exists ? 'Edit formulir evaluasi' : 'Tambah formulir evaluasi'])
    <form method="POST" action="{{ $formulir->exists ? route('umpan-balik-humas.update', $formulir) : route('umpan-balik-humas.store') }}" data-save id="uf-editor">
        @csrf
        @if($formulir->exists)@method('PUT')<input type="hidden" name="versi" value="{{ $formulir->versi }}">@else<input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan', $tokenPembuatan) }}">@endif
        <section class="uf-section"><h2>Identitas & periode</h2><div class="uf-grid">
            <div class="field uf-wide"><label for="judul">Judul evaluasi</label><input class="input" id="judul" name="judul" maxlength="180" value="{{ old('judul', $formulir->judul) }}" required></div>
            <div class="field"><label for="tahun_pelajaran_id">Tahun pelajaran</label><select class="select" id="tahun_pelajaran_id" name="tahun_pelajaran_id" required><option value="">Pilih tahun</option>@foreach($tahun as $t)<option value="{{ $t->id }}" @selected((string)old('tahun_pelajaran_id', $formulir->tahun_pelajaran_id) === (string)$t->id)>{{ $t->nama }}</option>@endforeach</select></div>
            <div class="field"><label for="penanggung_jawab">Penanggung jawab</label><input class="input" id="penanggung_jawab" name="penanggung_jawab" maxlength="180" value="{{ old('penanggung_jawab', $formulir->penanggung_jawab) }}" required></div>
            <div class="field"><label for="mulai_pada">Mulai pengisian (WIB)</label><input class="input" type="datetime-local" id="mulai_pada" name="mulai_pada" value="{{ old('mulai_pada', $formulir->mulai_pada->format('Y-m-d\TH:i')) }}" required></div>
            <div class="field"><label for="selesai_pada">Batas pengisian (WIB)</label><input class="input" type="datetime-local" id="selesai_pada" name="selesai_pada" value="{{ old('selesai_pada', $formulir->selesai_pada->format('Y-m-d\TH:i')) }}" required></div>
            <div class="field uf-wide"><label for="pengantar">Pengantar untuk orang tua</label><textarea class="input" id="pengantar" name="pengantar" maxlength="5000" required>{{ old('pengantar', $formulir->pengantar) }}</textarea></div>
        </div></section>
        <section class="uf-section"><h2>Sasaran orang tua</h2><div class="uf-grid">
            <div class="field"><label for="cakupan">Cakupan</label><select class="select" id="cakupan" name="cakupan">@foreach(\App\Models\UmpanBalikHumas::CAKUPAN as $k => $label)@if($k !== 'agenda' || $agenda->isNotEmpty())<option value="{{ $k }}" @selected(old('cakupan', $formulir->cakupan) === $k)>{{ $label }}</option>@endif @endforeach</select></div>
            <div class="field" data-cakupan="tingkat"><label for="tingkat">Tingkat</label><select class="select" id="tingkat" name="tingkat"><option value="">Pilih tingkat</option></select></div>
            <div class="field uf-wide" data-cakupan="kelas"><label>Kelas</label><div class="uf-classes">@foreach($kelas as $k)<label class="uf-check" data-tahun="{{ $k->tahun_pelajaran_id }}"><input type="checkbox" name="kelas_ids[]" value="{{ $k->id }}" @checked(in_array($k->id, (array)old('kelas_ids', $formulir->kelas_ids ?? [])))>{{ $k->nama }}</label>@endforeach</div></div>
            <div class="field uf-wide" data-cakupan="agenda"><label for="agenda_humas_id">Pertemuan</label><select class="select" id="agenda_humas_id" name="agenda_humas_id"><option value="">Pilih pertemuan</option>@foreach($agenda as $a)<option value="{{ $a->id }}" @selected((string)old('agenda_humas_id', $formulir->agenda_humas_id) === (string)$a->id)>{{ $a->waktu_mulai->format('d-m-Y') }} - {{ $a->judul }}</option>@endforeach</select></div>
        </div></section>
        <section class="uf-section"><div class="uf-toolbar"><h2>Pertanyaan</h2><button class="button button-muted" type="button" id="tambah-pertanyaan">Tambah pertanyaan</button></div><div id="daftar-pertanyaan"></div></section>
        @if($formulir->exists)<div class="field"><label for="alasan">Alasan perubahan</label><textarea class="input" name="alasan" id="alasan" minlength="5" maxlength="2000" required>{{ old('alasan') }}</textarea></div>@endif
        <div class="actions" style="margin-top:20px"><button class="button button-primary" type="submit">Simpan draf</button></div>
    </form>
</main>
<template id="pertanyaan-template"><fieldset class="uf-question"><legend></legend><div class="uf-question-tools"><button class="uf-tool" type="button" data-up title="Naikkan urutan" aria-label="Naikkan urutan">&uarr;</button><button class="uf-tool" type="button" data-down title="Turunkan urutan" aria-label="Turunkan urutan">&darr;</button><button class="uf-tool" type="button" data-remove title="Hapus pertanyaan" aria-label="Hapus pertanyaan">&times;</button></div><div class="uf-grid"><div class="field uf-wide"><label data-label="teks">Pertanyaan</label><textarea class="input" data-field="teks" minlength="5" maxlength="1000" required></textarea></div><div class="field"><label data-label="jenis">Jenis jawaban</label><select class="select" data-field="jenis"><option value="skala">Skala kepuasan 1-4</option><option value="teks">Jawaban tertulis</option></select></div><label class="uf-check"><input type="checkbox" data-wajib>Wajib diisi<input type="hidden" data-field="wajib"></label></div></fieldset></template>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const initial = {{ \Illuminate\Support\Js::from(old('pertanyaan', $pertanyaan)) }}, list = document.querySelector('#daftar-pertanyaan'), addButton = document.querySelector('#tambah-pertanyaan');
    function renumber() {
        const rows = [...list.children];
        rows.forEach((row,i) => {
            row.querySelector('legend').textContent = `Pertanyaan ${i+1}`;
            row.querySelector('[data-up]').disabled = i === 0;
            row.querySelector('[data-down]').disabled = i === rows.length-1;
            row.querySelector('[data-remove]').disabled = rows.length === 1;
            row.querySelectorAll('[data-field]').forEach(input => { const key=input.dataset.field; input.name=`pertanyaan[${i}][${key}]`; input.id=`pertanyaan-${i}-${key}`; row.querySelector(`[data-label="${key}"]`)?.setAttribute('for',input.id); });
        });
        addButton.disabled = rows.length >= 30;
    }
    function add(data={jenis:'skala',teks:'',wajib:true}) {
        const row = document.querySelector('#pertanyaan-template').content.firstElementChild.cloneNode(true);
        row.querySelector('[data-field="teks"]').value=data.teks || '';
        row.querySelector('[data-field="jenis"]').value=['skala','teks'].includes(data.jenis)?data.jenis:'skala';
        const checkbox=row.querySelector('[data-wajib]'), hidden=row.querySelector('[data-field="wajib"]');
        checkbox.checked = data.wajib === true || data.wajib === 1 || data.wajib === '1';
        const sync=()=>{hidden.value=checkbox.checked?'1':'0';}; sync(); checkbox.addEventListener('change',sync);
        row.querySelector('[data-up]').addEventListener('click',()=>{if(row.previousElementSibling)list.insertBefore(row,row.previousElementSibling);renumber();});
        row.querySelector('[data-down]').addEventListener('click',()=>{if(row.nextElementSibling)list.insertBefore(row.nextElementSibling,row);renumber();});
        row.querySelector('[data-remove]').addEventListener('click',()=>{if(row.querySelector('[data-field="teks"]').value && !confirm('Hapus pertanyaan ini dari draf?'))return;row.remove();renumber();});
        list.append(row);renumber();
    }
    (Array.isArray(initial)?initial:[]).filter(data=>data && typeof data==='object' && !Array.isArray(data)).slice(0,30).forEach(add);
    if(!list.children.length)add(); addButton.addEventListener('click',()=>{if(list.children.length<30){add();list.lastElementChild.querySelector('textarea').focus();}});
    const year=document.querySelector('#tahun_pelajaran_id'), scope=document.querySelector('#cakupan'), level=document.querySelector('#tingkat');
    const classes={{ \Illuminate\Support\Js::from($kelas->map(fn($k)=>['tahun'=>$k->tahun_pelajaran_id,'tingkat'=>$k->tingkat])->all()) }};
    let selected={{ \Illuminate\Support\Js::from((string)old('tingkat',$formulir->tingkat)) }};
    function targets() {
        const current=level.value || selected; level.replaceChildren(new Option('Pilih tingkat',''));
        [...new Set(classes.filter(k=>String(k.tahun)===year.value).map(k=>k.tingkat))].sort((a,b)=>a-b).forEach(k=>level.add(new Option(String(k),String(k))));
        level.value=current; selected='';
        document.querySelectorAll('[data-cakupan]').forEach(section=>{const active=section.dataset.cakupan===scope.value;section.hidden=!active;section.querySelectorAll('input,select').forEach(input=>{input.disabled=!active;input.required=active && input.tagName==='SELECT';});});
        document.querySelectorAll('[data-tahun]').forEach(label=>{const visible=label.dataset.tahun===year.value;label.hidden=!visible;label.querySelector('input').disabled=!visible || scope.value!=='kelas';});
    }
    year.addEventListener('change',targets);scope.addEventListener('change',targets);targets();
    const start=document.querySelector('#mulai_pada'),end=document.querySelector('#selesai_pada');
    const dates=()=>{end.min=start.value;end.setCustomValidity(end.value && start.value && end.value<=start.value?'Batas waktu harus sesudah waktu mulai.':'');};
    start.addEventListener('change',dates);end.addEventListener('change',dates);dates();
});
</script>
@endsection
