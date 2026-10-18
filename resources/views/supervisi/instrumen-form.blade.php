@extends('layouts.app')
@section('admin-module', 'supervisi')
@section('title','Editor Instrumen Supervisi - NUSA')
@section('content')
<div class="sp-workspace"><div class="page-header"><div><p class="eyebrow">Supervisi Akademik</p><h1 class="page-title">{{ isset($data['id'])?'Edit draf instrumen':'Tambah instrumen' }}</h1></div><a class="button button-muted" href="{{ route('supervisi.instrumen.index') }}">Kembali</a></div>@include('supervisi._style')
<form method="POST" id="sp-instrument-form" action="{{ isset($data['id']) ? route('supervisi.instrumen.update',$data['id']) : route('supervisi.instrumen.store') }}">@csrf
@if(isset($data['id'])) @method('PUT')<input type="hidden" name="versi" value="{{ $data['versi'] }}">@else<input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan',$token) }}">@endif
<section class="sp-band"><div class="sp-head"><h2>Identitas instrumen</h2><span class="sp-badge draf">Draf{{ isset($data['nomor'])?' · Versi '.$data['nomor']:'' }}</span></div><div class="sp-body sp-grid"><div class="field sp-wide"><label for="sp-iname">Nama instrumen</label><input class="input" id="sp-iname" name="nama" maxlength="180" required value="{{ old('nama',$data['nama']) }}"></div><div class="field sp-wide"><label for="sp-inote">Catatan instrumen / revisi</label><textarea class="input" id="sp-inote" name="catatan" maxlength="3000" required>{{ old('catatan',$data['catatan']) }}</textarea></div></div></section>
<section class="sp-band amber"><div class="sp-head"><h2>Skala penilaian</h2><div class="sp-actions"><button class="button button-muted" type="button" id="sp-scale-add">Tambah skor</button><button class="sp-icon" type="button" id="sp-scale-remove" title="Hapus skor terakhir" aria-label="Hapus skor terakhir">&minus;</button></div></div><div class="sp-body"><div class="sp-scale" id="sp-scales"></div></div></section>
<div class="sp-summary"><div class="sp-stat"><span>Indikator perencanaan</span><strong id="sp-planning-count">0</strong><span id="sp-planning-max">Skor maksimal 0</span></div><div class="sp-stat teal"><span>Indikator pelaksanaan</span><strong id="sp-execution-count">0</strong><span id="sp-execution-max">Skor maksimal 0</span></div><div class="sp-stat amber"><span>Skor tertinggi per indikator</span><strong id="sp-max-score">4</strong></div></div>
<div id="sp-groups"></div><button class="button button-muted" type="button" id="sp-group-add">Tambah kelompok indikator</button>
<section class="sp-band teal"><div class="sp-head"><h2>Pertanyaan refleksi pascaobservasi</h2><span class="sp-badge" id="sp-reflection-count"></span></div><div class="sp-body"><ol class="sp-indicators" id="sp-reflection-prompts"></ol><button type="button" class="button button-muted" id="sp-reflection-add">Tambah pertanyaan refleksi</button></div></section>
<div class="sp-footer"><p id="sp-editor-state" role="status">Draf belum disimpan.</p><button class="button button-primary">Simpan draf instrumen</button></div></form></div>
<script>
@php $editorGroups = old('kelompok', $data['kelompok']); $editorScale = old('skala', $data['skala']); @endphp
@php $editorReflections = old('pertanyaan_refleksi', $data['pertanyaan_refleksi']); @endphp
(() => {
    const root=document.querySelector('#sp-groups'), scales=document.querySelector('#sp-scales'), form=document.querySelector('#sp-instrument-form');let serial=0;
    const groups=@json($editorGroups), scaleData=@json($editorScale);
    const el=(tag,attrs={},text)=>{const e=document.createElement(tag);Object.entries(attrs).forEach(([k,v])=>e.setAttribute(k,v));if(text!==undefined)e.textContent=text;return e;};
    const tool=(label,text,action)=>{const b=el('button',{type:'button',class:'sp-icon',title:label,'aria-label':label},text);b.onclick=action;return b;};
    const reflectionRoot = document.querySelector('#sp-reflection-prompts');
    function updateReflections() {
        [...reflectionRoot.children].forEach((row, i) => {
            row.querySelector('textarea').name = `pertanyaan_refleksi[${i}]`;
            row.querySelector('[data-remove-question]').disabled = reflectionRoot.children.length <= 1;
        });
        document.querySelector('#sp-reflection-count').textContent = `${reflectionRoot.children.length} pertanyaan`;
        document.querySelector('#sp-reflection-add').disabled = reflectionRoot.children.length >= 30;
    }
    function addReflection(value = '') {
        if (reflectionRoot.children.length >= 30) return;
        const row = el('li'), input = el('textarea', {class:'input', rows:'2', maxlength:'1000', required:'', 'aria-label':'Pertanyaan refleksi'});
        input.value = value;
        const up = tool('Pindahkan pertanyaan ke atas', '\u2191', () => { if (row.previousElementSibling) reflectionRoot.insertBefore(row, row.previousElementSibling); updateReflections(); });
        const remove = tool('Hapus pertanyaan refleksi', '\u00d7', () => { if (reflectionRoot.children.length > 1) row.remove(); updateReflections(); });
        remove.dataset.removeQuestion = '';
        row.append(input, up, remove);
        reflectionRoot.append(row);
        updateReflections();
    }
    const reflectionData = @json($editorReflections);
    reflectionData.forEach(addReflection);
    document.querySelector('#sp-reflection-add').onclick = () => addReflection();
    function update() {
        const totals = {perencanaan: 0, pelaksanaan: 0};
        [...root.children].forEach((group, i) => {
            group.querySelector('[data-stage]').name = `kelompok[${i}][tahap]`;
            group.querySelector('[data-gname]').name = `kelompok[${i}][nama]`;
            const stage = group.querySelector('[data-stage]').value;
            group.classList.toggle('teal', stage === 'pelaksanaan');
            group.querySelectorAll('[data-indicator]').forEach((input, n) => {
                input.name = `kelompok[${i}][indikator][${n}]`;
                totals[stage]++;
            });
        });
        [...scales.children].forEach((score, i) => {
            score.querySelector('input[type=hidden]').name = `skala[${i}][skor]`;
            score.querySelector('[data-scale-label]').name = `skala[${i}][label]`;
        });
        const max = scales.children.length - 1;
        document.querySelector('#sp-planning-count').textContent = totals.perencanaan;
        document.querySelector('#sp-execution-count').textContent = totals.pelaksanaan;
        document.querySelector('#sp-planning-max').textContent = `Skor maksimal ${totals.perencanaan * max}`;
        document.querySelector('#sp-execution-max').textContent = `Skor maksimal ${totals.pelaksanaan * max}`;
        document.querySelector('#sp-max-score').textContent = max;
        document.querySelector('#sp-scale-add').disabled = max >= 10;
        document.querySelector('#sp-scale-remove').disabled = max <= 1;
    }
    function addScore(label = '') {
        if (scales.children.length >= 11) return;
        const n = scales.children.length, id = `sp-score-${n}`, box = el('div');
        box.append(el('label', {for: id}, `Skor ${n}`));
        const value = el('input', {type: 'hidden', value: n});
        const input = el('textarea', {class: 'input', id, rows: '4', required: '', maxlength: '120', 'data-scale-label': ''});
        input.value = label;
        box.append(value, input);
        scales.append(box);
        update();
    }
    function addIndicator(list, value = '') {
        const item = el('li');
        const input = el('textarea', {class: 'input', rows: '2', required: '', maxlength: '1000', 'data-indicator': '', 'aria-label': 'Indikator penilaian'});
        input.value = value;
        item.append(input, tool('Hapus indikator', '\u00d7', () => { item.remove(); update(); }));
        list.append(item);
        update();
    }
    function addGroup(data = {tahap: 'perencanaan', nama: '', indikator: ['']}) {
        if (root.children.length >= 40) return;
        const n = serial++, group = el('section', {class: 'sp-band', 'data-group': ''});
        const head = el('div', {class: 'sp-head'}), actions = el('div', {class: 'sp-actions'});
        head.append(el('h2', {}, 'Kelompok indikator'));
        actions.append(
            tool('Pindahkan kelompok ke atas', '\u2191', () => {
                if (group.previousElementSibling) root.insertBefore(group, group.previousElementSibling);
                update();
            }),
            tool('Pindahkan kelompok ke bawah', '\u2193', () => {
                if (group.nextElementSibling) root.insertBefore(group.nextElementSibling, group);
                update();
            }),
            tool('Hapus kelompok', '\u00d7', () => {
                if (confirm('Hapus kelompok beserta indikatornya dari draf ini?')) { group.remove(); update(); }
            }),
        );
        head.append(actions);
        const body = el('div', {class: 'sp-body'}), grid = el('div', {class: 'sp-grid'});
        const stageWrap = el('div', {class: 'field'}), nameWrap = el('div', {class: 'field'});
        const stage = el('select', {class: 'select', id: `sp-stage-${n}`, 'data-stage': ''});
        const name = el('input', {class: 'input', id: `sp-gname-${n}`, required: '', maxlength: '180', 'data-gname': ''});
        ['perencanaan', 'pelaksanaan'].forEach(value => stage.append(el('option', {value}, value === 'perencanaan' ? 'Perencanaan / praobservasi' : 'Pelaksanaan / observasi')));
        stage.value = data.tahap;
        name.value = data.nama;
        stageWrap.append(el('label', {for: stage.id}, 'Tahap'), stage);
        nameWrap.append(el('label', {for: name.id}, 'Nama kelompok'), name);
        grid.append(stageWrap, nameWrap);
        const list = el('ol', {class: 'sp-indicators'});
        const add = el('button', {type: 'button', class: 'button button-muted', style: 'margin-top:16px'}, 'Tambah indikator');
        add.onclick = () => addIndicator(list);
        body.append(grid, list, add);
        group.append(head, body);
        root.append(group);
        stage.onchange = update;
        (data.indikator.length ? data.indikator : ['']).forEach(value => addIndicator(list, value));
        update();
    }
    scaleData.forEach(s=>addScore(s.label));(groups.length?groups:[{tahap:'perencanaan',nama:'Perencanaan pembelajaran',indikator:['']},{tahap:'pelaksanaan',nama:'Pelaksanaan pembelajaran',indikator:['']}]).forEach(addGroup);
    document.querySelector('#sp-group-add').onclick=()=>addGroup();document.querySelector('#sp-scale-add').onclick=()=>addScore();document.querySelector('#sp-scale-remove').onclick=()=>{if(scales.children.length>2){scales.lastElementChild.remove();update();}};
    form.addEventListener('input',()=>document.querySelector('#sp-editor-state').textContent='Ada perubahan yang belum disimpan.');form.addEventListener('submit',update);update();
})();
</script>
@endsection
