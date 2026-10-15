@extends('layouts.app')
@section('title','Rekap Supervisi - NUSA')
@section('content')
@php
    $coverage = $filter['tab'] === 'belum_terjadwal';
    $query = collect($filter)->except('halaman')->filter(fn($v) => $v !== null && $v !== '')->all();
    $angka = fn($v) => $v === null ? '-' : number_format($v, 2, ',', '.');
@endphp
<div class="sp-workspace">
    <div class="page-header"><div><p class="eyebrow">Supervisi Akademik</p><h1 class="page-title">Rekap supervisi</h1></div><div class="sp-actions">
        <a class="button button-muted" href="{{ route('supervisi.rekap.export', $query) }}">Ekspor Excel</a>
        <a class="button button-primary" target="_blank" rel="noopener" href="{{ route('supervisi.rekap.cetak', $query) }}">Cetak rekap</a>
    </div></div>
    @include('supervisi._style')
    @if($global)<nav class="sp-stage-nav" aria-label="Jenis rekap">
        @foreach(['sesi'=>'Rekap sesi','belum_terjadwal'=>'Guru belum terjadwal'] as $tab=>$label)<a href="{{ route('supervisi.rekap.index', [...collect($query)->except(['tab','supervisor_id','tahap','terlambat'])->all(), 'tab'=>$tab]) }}" @if($filter['tab']===$tab) aria-current="page" @endif>{{ $label }}</a>@endforeach
    </nav>@endif
    <form method="get" action="{{ route('supervisi.rekap.index') }}" class="sp-filters sp-recap-filters" id="sp-recap-filter">
        <input type="hidden" name="tab" value="{{ $filter['tab'] }}">
        <div class="field"><label for="tahun">Tahun pelajaran</label><select class="select" name="tahun_pelajaran_id" id="tahun" data-reset-options>@foreach($pilihan['tahun'] as $v)<option value="{{ $v->id }}" @selected($filter['tahun_pelajaran_id']===$v->id)>{{ $v->nama }}</option>@endforeach</select></div>
        <div class="field"><label for="semester">Semester</label><select class="select" name="semester" id="semester" data-reset-options>@foreach(['ganjil','genap'] as $v)<option @selected($filter['semester']===$v) value="{{ $v }}">{{ ucfirst($v) }}</option>@endforeach</select></div>
        <div class="field"><label for="program">Program</label><select class="select" name="program_id" id="program" data-dependent><option value="">Semua program</option>@foreach($pilihan['program'] as $v)<option value="{{ $v->id }}" @selected(($filter['program_id']??null)==$v->id)>{{ $v->nama }}</option>@endforeach</select></div>
        <div class="field"><label for="guru">Guru</label><select class="select" name="guru_id" id="guru" data-dependent><option value="">Semua guru</option>@foreach($pilihan['guru'] as $v)<option value="{{ $v->id }}" @selected(($filter['guru_id']??null)==$v->id)>{{ $v->nama_lengkap }}</option>@endforeach</select></div>
        @unless($coverage)
        <div class="field"><label for="supervisor">Supervisor</label><select class="select" name="supervisor_id" id="supervisor" data-dependent><option value="">Semua supervisor</option>@foreach($pilihan['supervisor'] as $v)<option value="{{ $v->id }}" @selected(($filter['supervisor_id']??null)==$v->id)>{{ $v->nama_lengkap }}</option>@endforeach</select></div>
        <div class="field"><label for="tahap">Progres</label><select class="select" name="tahap" id="tahap"><option value="">Semua progres</option>@foreach($pilihan['tahap'] as $v=>$label)<option value="{{ $v }}" @selected(($filter['tahap']??null)===$v)>{{ $label }}</option>@endforeach</select></div>
        @endunless
        <div class="field"><label for="cari">Pencarian</label><input type="search" class="input" id="cari" name="kata_kunci" maxlength="120" value="{{ $filter['kata_kunci'] }}" placeholder="Guru, mapel, atau kelas"></div>
        <div class="sp-recap-filter-actions">@unless($coverage)<label class="sp-check"><input type="checkbox" name="terlambat" value="1" @checked($filter['terlambat'])>TL melewati tenggat</label>@endunless<div class="sp-actions"><button class="button button-primary">Terapkan</button><a class="button button-muted" href="{{ route('supervisi.rekap.index',['tab'=>$filter['tab']]) }}">Reset</a></div></div>
    </form>
    @if($coverage)
        <div class="sp-summary"><div class="sp-stat"><span>Guru aktif</span><strong>{{ $statistik['guru_aktif'] }}</strong></div><div class="sp-stat teal"><span>Sudah terjadwal</span><strong>{{ $statistik['sudah_terjadwal'] }}</strong></div><div class="sp-stat amber"><span>Belum terjadwal</span><strong>{{ $statistik['belum_terjadwal'] }}</strong></div></div>
        <section class="sp-band amber"><div class="sp-head"><h2>Guru belum memiliki jadwal terbit</h2><p>Penugasan mengajar aktif pada tahun terpilih. Jadwal draf dan dibatalkan tidak dihitung.</p></div></section>
        <div class="sp-table-wrap"><table class="sp-table"><thead><tr><th>No.</th><th>Guru</th><th>Mata pelajaran</th><th>Kelas</th><th>Status</th></tr></thead><tbody>@forelse($daftar as $r)<tr><td>{{ $daftar->firstItem()+$loop->index }}</td><td><strong>{{ $r['guru'] }}</strong></td><td>{{ $r['mapel'] }}</td><td>{{ $r['kelas'] }}</td><td><span class="sp-badge draf">Belum terjadwal</span></td></tr>@empty<tr><td colspan="5" class="sp-empty">Tidak ada guru belum terjadwal pada filter ini.</td></tr>@endforelse</tbody></table></div>
    @else
        <div class="sp-summary sp-recap-summary"><div class="sp-stat"><span>Sesi supervisi</span><strong>{{ $statistik['sesi'] }}</strong><span>{{ $statistik['guru'] }} guru</span></div><div class="sp-stat teal"><span>Hasil final</span><strong>{{ $statistik['hasil_final'] }}</strong><span>Rata-rata per sesi {{ $angka($statistik['rata_rata']) }}</span></div><div class="sp-stat"><span>Pascaobservasi selesai</span><strong>{{ $statistik['selesai'] }}</strong><span>{{ $statistik['belum_final'] }} sesi menunggu hasil final</span></div><div class="sp-stat amber"><span>TL melewati tenggat</span><strong>{{ $statistik['melewati_tenggat'] }}</strong><span>Rencana belum diajukan</span></div></div>
        <form method="post" action="{{ route('supervisi.laporan.kolektif') }}" target="_blank" id="sp-collective">@csrf
            <div class="sp-head sp-recap-toolbar"><div><h2>Hasil dan progres supervisi</h2><p>Nilai draf tidak dihitung. Setiap tahap berbobot 50%.</p></div><div class="sp-actions"><span id="sp-selected" aria-live="polite">0 dari 10 sesi dipilih</span><button class="button button-primary" id="sp-print-selected" disabled>Cetak laporan terpilih</button></div></div>
            <div class="sp-table-wrap"><table class="sp-table sp-recap-table"><colgroup><col style="width:4%"><col style="width:22%"><col style="width:13%"><col style="width:9%"><col style="width:9%"><col style="width:10%"><col style="width:20%"><col style="width:13%"></colgroup><thead><tr><th><input type="checkbox" id="sp-select-page" aria-label="Pilih sesi siap cetak pada halaman ini"></th><th>Guru / jadwal</th><th>Supervisor</th><th>Perencanaan</th><th>Pelaksanaan</th><th>Hasil final</th><th>Progres</th><th>Aksi</th></tr></thead><tbody>
            @forelse($daftar as $r)<tr><td>@if($r['bisa_cetak'])<input type="checkbox" name="sesi[]" value="{{ $r['id'] }}" aria-label="Pilih laporan {{ $r['guru'] }}" data-report-choice>@endif</td><td><strong>{{ $r['guru'] }}</strong><small>{{ $r['mapel'] }} · {{ $r['kelas'] }}<br>{{ $r['tanggal'] }} · {{ $r['program'] }}</small></td><td>{{ $r['supervisor'] }}</td><td class="sp-numeric">{{ $angka($r['nilai_perencanaan']) }}</td><td class="sp-numeric">{{ $angka($r['nilai_pelaksanaan']) }}</td><td class="sp-numeric"><strong>{{ $angka($r['nilai_akhir']) }}</strong><small>{{ $r['kategori'] ?? 'Belum final' }}</small></td><td><span class="sp-badge {{ $r['tahap']==='selesai'?'selesai':($r['bisa_cetak']?'ditugaskan':'draf') }}">{{ $r['label_tahap'] }}</span>@if($r['tindak_lanjut'])<small>TL {{ $r['tindak_lanjut_selesai'] }}/{{ $r['tindak_lanjut'] }} selesai</small>@endif @if($r['tindak_lanjut_terlambat'])<small class="sp-late">{{ $r['tindak_lanjut_terlambat'] }} melewati tenggat</small>@endif</td><td><div class="sp-actions"><a class="button button-muted" href="{{ route('supervisi.sesi.show',$r['id']) }}">Rincian</a>@if($r['bisa_cetak'])<a class="button button-muted" target="_blank" rel="noopener" href="{{ route('supervisi.laporan.cetak',$r['id']) }}">Cetak</a>@endif</div></td></tr>
            @empty<tr><td colspan="8" class="sp-empty">Belum ada sesi yang sesuai dengan filter.</td></tr>@endforelse
            </tbody></table></div>
        </form>
    @endif
    {{ $daftar->links() }}
</div>
<script>
(()=>{
    const f=document.querySelector('#sp-recap-filter');
    f.querySelectorAll('[data-reset-options]').forEach(el=>el.addEventListener('change',()=>{f.querySelectorAll('[data-dependent]').forEach(s=>s.value='');}));
    f.querySelector('#program').addEventListener('change',()=>{f.querySelector('#guru').value='';const s=f.querySelector('#supervisor');if(s)s.value='';});
    const form=document.querySelector('#sp-collective');if(!form)return;
    const choices=[...form.querySelectorAll('[data-report-choice]')],all=form.querySelector('#sp-select-page'),button=form.querySelector('#sp-print-selected'),counter=form.querySelector('#sp-selected');
    function update(){const n=choices.filter(c=>c.checked).length;counter.textContent=`${n} dari 10 sesi dipilih`;button.disabled=n===0||n>10;all.checked=choices.length>0&&choices.every(c=>c.checked);all.indeterminate=n>0&&!all.checked;choices.forEach(c=>c.disabled=n>=10&&!c.checked);}
    all.disabled=choices.length===0;
    all.addEventListener('change',()=>{const on=!choices.some(c=>c.checked);choices.forEach((c,i)=>c.checked=on&&i<10);update();});choices.forEach(c=>c.addEventListener('change',update));
    form.addEventListener('submit',e=>{if(button.disabled)e.preventDefault();});update();
})();
</script>
@endsection
