@extends('layouts.app')
@section('admin-module', 'supervisi')
@section('title','Rincian Program Supervisi - NUSA')
@section('content')
<div class="sp-workspace"><div class="page-header"><div><p class="eyebrow">Supervisi Akademik</p><h1 class="page-title">{{ $program['nama'] }}</h1></div><div class="sp-actions">@if($hak_akses['kelola'])<a class="button button-muted" href="{{ route('supervisi.program.edit',$program['id']) }}">Edit program</a>@if($program['status']!=='diarsipkan')<a class="button button-primary" href="{{ route('supervisi.jadwal.create',$program['id']) }}">Tambah jadwal</a>@endif @endif</div></div>@include('supervisi._style')
<section class="sp-band"><div class="sp-head"><h2>{{ $program['tahun_pelajaran'] }} · Semester {{ ucfirst($program['semester']) }}</h2><span class="sp-badge {{ $program['status'] }}">{{ \App\Models\ProgramSupervisi::STATUS[$program['status']] }}</span></div><div class="sp-body"><p style="margin-top:0">{{ $program['tujuan'] }}</p><span class="sp-muted">{{ \Carbon\Carbon::parse($program['tanggal_mulai'])->format('d-m-Y') }} s.d. {{ \Carbon\Carbon::parse($program['tanggal_selesai'])->format('d-m-Y') }}</span></div></section>
<section class="sp-band teal"><div class="sp-head"><h2>Jadwal dan penugasan</h2><span>{{ count($sesi) }} sesi</span></div><div class="sp-body">
@if($hak_akses['kelola'] && $program['status']!=='diarsipkan')<form method="POST" action="{{ route('supervisi.jadwal.tindakan',[$program['id'],'terbitkan']) }}" id="sp-bulk">@csrf
<div class="sp-toolbar" style="margin-bottom:18px"><label class="sp-check"><input type="checkbox" id="sp-all">Pilih semua sesi aktif</label><div class="sp-actions"><button class="button button-muted" type="button" data-sp-pick="draf">Pilih draf</button><button class="button button-muted" type="button" data-sp-pick="terjadwal">Pilih terjadwal</button><span id="sp-selected" role="status">0 sesi dipilih</span></div></div>
@include('supervisi._sesi-table',['items'=>$sesi,'pilih'=>true])
<div class="sp-grid" style="margin-top:20px"><div class="field sp-wide"><label for="sp-action-reason">Catatan penerbitan / alasan pembatalan</label><textarea class="input" id="sp-action-reason" name="alasan" minlength="10" maxlength="1000" required>{{ old('alasan') }}</textarea></div></div>
<label class="sp-check"><input type="checkbox" name="konfirmasi" value="1" required>Saya sudah memeriksa guru, supervisor, instrumen, dan waktu sesi yang dipilih.</label>
<div class="sp-footer"><button class="button button-danger" formaction="{{ route('supervisi.jadwal.tindakan',[$program['id'],'batalkan']) }}">Batalkan sesi terpilih</button><button class="button button-primary" @disabled($program['status']!=='aktif')>Terbitkan jadwal terpilih</button></div>
</form>@else @include('supervisi._sesi-table',['items'=>$sesi,'pilih'=>false]) @endif
</div></section>@include('supervisi._riwayat')
</div>
<script>
(() => {
    const form = document.querySelector('#sp-bulk');
    if (!form) return;
    const all = document.querySelector('#sp-all');
    const boxes = [...form.querySelectorAll('[data-sp-select]:not(:disabled)')];
    const sync = () => {
        boxes.forEach(box => box.closest('tr').querySelectorAll('[data-sp-field]').forEach(field => field.disabled = !box.checked));
        const count = boxes.filter(box => box.checked).length;
        document.querySelector('#sp-selected').textContent = `${count} sesi dipilih`;
        all.checked = boxes.length > 0 && count === boxes.length;
        all.indeterminate = count > 0 && count < boxes.length;
    };
    all.onchange = () => { boxes.forEach(box => box.checked = all.checked); sync(); };
    boxes.forEach(box => box.onchange = sync);
    form.querySelectorAll('[data-sp-pick]').forEach(button => button.onclick = () => {
        boxes.forEach(box => box.checked = box.closest('tr').dataset.spStatus === button.dataset.spPick);
        sync();
    });
    form.onsubmit = event => {
        if (!boxes.some(box => box.checked)) {
            event.preventDefault();
            alert('Pilih minimal satu sesi.');
        }
    };
    sync();
})();
</script>
@endsection
