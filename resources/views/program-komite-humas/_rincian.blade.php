<dl class="agenda-facts">
    <div><dt>Jadwal program</dt><dd>{{ $dataProgram['tanggal_mulai'] }} s.d. {{ $dataProgram['tanggal_selesai'] }}</dd></div>
    <div><dt>Penanggung jawab</dt><dd>{{ $dataProgram['penanggung_jawab'] ?: 'Belum ditentukan' }}</dd></div>
    <div><dt>Status</dt><dd>{{ \App\Models\ProgramKomiteHumas::STATUS[$dataProgram['status']] }}</dd></div>
</dl>
<h3>Tujuan</h3><div class="agenda-text">{{ $dataProgram['tujuan'] }}</div>
<h3>Target hasil</h3><div class="agenda-text">{{ $dataProgram['target_hasil'] }}</div>
<h3>Capaian</h3><div class="agenda-text">{{ $dataProgram['capaian'] ?: 'Belum dicatat' }}</div>
@if ($dataProgram['catatan_evaluasi'])<h3>Evaluasi / alasan pembatalan</h3><div class="agenda-text">{{ $dataProgram['catatan_evaluasi'] }}</div>@endif
