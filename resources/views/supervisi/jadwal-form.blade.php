@extends('layouts.app')
@section('admin-module', 'supervisi')
@section('title','Penjadwalan Supervisi - NUSA')
@section('content')
<div class="sp-workspace"><div class="page-header"><div><p class="eyebrow">{{ $program->nama }}</p><h1 class="page-title">{{ $sesi ? 'Ubah jadwal' : 'Tambah jadwal supervisi' }}</h1></div><a class="button button-muted" href="{{ route('supervisi.program.show',$program) }}">Kembali ke program</a></div>@include('supervisi._style')
<form method="POST" id="sp-schedule-form" action="{{ $sesi ? route('supervisi.sesi.update',$sesi) : route('supervisi.jadwal.store',$program) }}">@csrf
@if($sesi) @method('PUT')<input type="hidden" name="versi" value="{{ $sesi->versi }}">@endif
<div id="sp-schedule-rows"></div>
@if(!$sesi)<button class="button button-muted" type="button" id="sp-add-schedule">Tambah sesi</button>@else<div class="field"><label for="sp-change-reason">Alasan perubahan jadwal / supervisor</label><textarea class="input" id="sp-change-reason" name="alasan" minlength="10" maxlength="1000" required>{{ old('alasan') }}</textarea></div>@endif
<div class="sp-footer"><p>{{ $sesi?->diterbitkan_pada ? 'Perubahan jadwal terbit akan diberitahukan kepada guru dan supervisor terkait.' : 'Status awal: draf. Belum ada notifikasi kepada guru dan supervisor.' }}</p><button class="button button-primary">{{ $sesi ? 'Simpan perubahan' : 'Simpan draf jadwal' }}</button></div>
</form></div>
<script>
@php
    $initialSchedule = $sesi ? [[...$sesi->only(['supervisor_pegawai_id','tempat','topik','alasan_di_luar_jadwal']), 'waktu_mulai'=>$sesi->waktu_mulai->format('Y-m-d\TH:i'),'waktu_selesai'=>$sesi->waktu_selesai->format('Y-m-d\TH:i')]] : old('sesi', []);
@endphp
(() => {
    const choices=@json($pilihan), editing=@json((bool)$sesi), root=document.querySelector('#sp-schedule-rows');
    const initial=@json($initialSchedule);
    const fixed=@json($sesi?->konteks);
    let count=0;
    const element=(tag,attrs={},text)=>{const el=document.createElement(tag);Object.entries(attrs).forEach(([k,v])=>el.setAttribute(k,v));if(text!==undefined)el.textContent=text;return el;};
    function add(data={}) {
        if(root.children.length>=50)return;
        const index=count++, prefix=editing?'':`sesi[${index}]`;
        const band=element('section',{class:'sp-band '+(index%2?'teal':'')});
        const head=element('div',{class:'sp-head'});head.append(element('h2',{},editing?'Penugasan supervisi':`Sesi ${index+1}`));
        if(!editing){const remove=element('button',{type:'button',class:'sp-icon',title:'Hapus sesi','aria-label':'Hapus sesi'},'\u00d7');remove.onclick=()=>{band.remove();rename();};head.append(remove);}
        const body=element('div',{class:'sp-body sp-grid'});band.append(head,body);
        function field(key,label,type='text',options=null){const id=`sp-session-${index}-${key}`,wrap=element('div',{class:'field'});wrap.append(element('label',{for:id},label));let input;
            if(options){input=element('select',{class:'select',id,required:''});input.append(element('option',{value:''},'Pilih '+label.toLowerCase()));options.forEach(o=>input.append(element('option',{value:o.id},o.label??o.nama_lengkap)));}
            else input=element('input',{class:'input',id,type});
            input.dataset.key=key;input.value=data[key]??'';if(['tempat','waktu_mulai','waktu_selesai'].includes(key))input.required=true;
            if(key==='tempat')input.maxLength=180;if(key==='topik')input.maxLength=500;if(key==='alasan_di_luar_jadwal'){input.minLength=10;input.maxLength=1000;wrap.classList.add('sp-wide');}
            wrap.append(input);body.append(wrap);return input;
        }
        if(!editing){const token=element('input',{type:'hidden','data-key':'token_pembuatan'});token.value=data.token_pembuatan||crypto.randomUUID();body.append(token);
            const assignment=field('guru_mata_pelajaran_id','Guru, mapel dan kelas','text',choices.penugasan);
            const instrument=field('versi_instrumen_supervisi_id','Instrumen','text',choices.instrumen.map(i=>({id:i.id,label:`${i.nama} - Versi ${i.nomor}`})));
            const schedule=element('p',{class:'sp-muted sp-wide','data-sp-reference':''});body.append(schedule);
            const sync=()=>{const g=choices.penugasan.find(g=>String(g.id)===assignment.value);schedule.textContent=g?.jadwal.length?'Jadwal mengajar: '+g.jadwal.map(j=>`${j.hari} ${j.mulai}-${j.selesai}`).join(' / '):'Belum ada referensi jadwal mengajar.';};assignment.addEventListener('change',sync);sync();
        } else {body.append(element('p',{class:'sp-wide'},`${fixed.guru} - ${fixed.mapel} - ${fixed.kelas}`));}
        field('supervisor_pegawai_id','Supervisor','text',choices.supervisor);
        field('tempat','Tempat / ruang');field('waktu_mulai','Waktu mulai','datetime-local');field('waktu_selesai','Waktu selesai','datetime-local');field('topik','Topik pembelajaran');field('alasan_di_luar_jadwal','Alasan penjadwalan khusus (di luar jam mengajar)');
        root.append(band);rename();
    }
    function rename(){[...root.children].forEach((b,i)=>{if(!editing)b.querySelector('h2').textContent=`Sesi ${i+1}`;b.querySelectorAll('[data-key]').forEach(f=>f.name=editing?f.dataset.key:`sesi[${i}][${f.dataset.key}]`);});}
    (initial.length?initial:[{}]).forEach(add);document.querySelector('#sp-add-schedule')?.addEventListener('click',()=>add());
    document.querySelector('#sp-schedule-form').addEventListener('submit',e=>{if(!root.children.length){e.preventDefault();alert('Tambahkan minimal satu sesi supervisi.');}});
})();
</script>
@endsection
