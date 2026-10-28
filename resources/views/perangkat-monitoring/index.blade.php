@extends('layouts.app')
@section('admin-module', 'kehadiran')
@section('title', 'Pengaturan Monitoring TV - NUSA')
@section('content')
<div class="page-header">
    <div><p class="eyebrow">Monitoring NUSA</p><h1 class="page-title">Pengaturan layar TV</h1></div>
    <div class="actions">
        @if ($saatIni)
        @if ($saatIni->mengizinkan('siswa'))<a class="button button-primary" href="{{ route('monitoring-siswa.index') }}" target="_blank" rel="noopener"><i data-lucide="monitor"></i> Buka layar TV siswa</a>@endif
        @if ($saatIni->mengizinkan('ujian'))<a class="button button-primary" href="{{ route('monitoring-ujian-tv.index') }}" target="_blank" rel="noopener"><i data-lucide="presentation"></i> Buka TV ujian</a>@endif
        @if ($saatIni->mengizinkan('pameran'))<a class="button button-primary" href="{{ route('monitoring-pameran.index') }}" target="_blank" rel="noopener"><i data-lucide="monitor-play"></i> Buka layar pameran</a>@endif
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="button button-muted" type="submit"><i data-lucide="log-out"></i> Keluar akun admin</button></form>@endif
    </div>
</div>
@if (session('berhasil'))<x-ui.alert type="success">{{ session('berhasil') }}</x-ui.alert>@endif
@if ($errors->any())<x-ui.alert type="danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif
<section class="panel panel-pad" style="margin-bottom:24px">
    <h2 class="section-title">Aktivasi browser TV</h2>
    <x-ui.alert :type="$saatIni ? 'success' : 'warning'" :title="$saatIni ? 'Browser ini aktif' : 'Browser belum aktif'">{{ $saatIni?->nama ?? 'Aktifkan di browser komputer yang terhubung ke TV ruang guru.' }}</x-ui.alert>
    <p>Monitoring diperbarui setiap 5 detik dan tetap berjalan setelah akun admin keluar. Pilih Mode pameran untuk layar publik; monitoring siswa/ujian biasa ditujukan untuk ruang guru. Kredensial scanner tidak dapat digunakan untuk layar ini.</p>
    <form method="POST" action="{{ route('perangkat-monitoring.store') }}">
        @csrf
        <div class="field"><label for="nama">Nama layar</label><input class="input" id="nama" name="nama" required maxlength="100" value="{{ old('nama', $saatIni?->nama) }}" placeholder="Contoh: TV ruang guru"></div>
        <div class="form-grid" style="margin-top:16px">
            <div class="field"><label for="cakupan">Akses layar</label><select class="select" id="cakupan" name="cakupan">@foreach (\App\Models\PerangkatMonitoring::CAKUPAN as $value => $label)<option value="{{ $value }}" @selected(old('cakupan', $saatIni?->cakupan ?? $cakupanAwal) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field" id="kegiatan-field"><label for="kegiatan">Kegiatan ujian yang diizinkan</label><select class="select" id="kegiatan" name="kegiatan_ujian_cbt_id"><option value="">Pilih kegiatan ujian</option>@foreach ($kegiatan as $item)<option value="{{ $item->id }}" @selected((string) old('kegiatan_ujian_cbt_id', $saatIni?->kegiatan_ujian_cbt_id) === (string) $item->id)>{{ $item->nama }}</option>@endforeach</select></div>
        </div>
        <div id="pameran-fields" class="feature-filter" style="margin-top:16px" hidden>
            <div class="form-grid"><div class="field"><label for="acara_buku_tamu_id">Acara buku tamu</label><select class="select" id="acara_buku_tamu_id" name="acara_buku_tamu_id"><option value="">Pilih acara</option>@foreach($acaraPameran as $acara)<option value="{{ $acara->id }}" @selected((string)old('acara_buku_tamu_id', $saatIni?->acara_buku_tamu_id) === (string)$acara->id)>{{ $acara->nama }}</option>@endforeach</select></div>
            <div class="field"><label for="pameran_tanggal">Tanggal aktivitas awal</label><input class="input" id="pameran_tanggal" name="pameran_tanggal" type="date" min="{{ $batasTanggalPameran['min'] }}" max="{{ $batasTanggalPameran['max'] }}" value="{{ old('pameran_tanggal', $saatIni?->pameran_tanggal?->toDateString() ?? $batasTanggalPameran['max']) }}"></div></div>
            <label style="display:flex;gap:10px;margin-top:16px"><input type="checkbox" name="pameran_identitas" value="1" @checked(old('pameran_identitas', $saatIni?->pameran_identitas))>Tampilkan nama dan foto siswa; sekolah sudah memastikan izin penayangannya.</label>
            <p class="person-meta">Bawaan: identitas disamarkan. Konseling, kronologi, kontak, dan catatan pribadi tetap tidak ditampilkan. Statistik pengunjung tidak memuat identitas atau komentar tamu.</p>
        </div>
        <label style="display:flex;gap:10px;margin:18px 0"><input type="checkbox" name="konfirmasi" value="1" required><span id="confirmation-label">Layar berada di area terbatas untuk guru dan dikelola sekolah.</span></label>
        <button class="button button-primary" type="submit"><i data-lucide="shield-check"></i> {{ $saatIni ? 'Perbarui aktivasi' : 'Aktifkan browser ini' }}</button>
    </form>
</section>
<section class="panel">
    <div class="panel-pad"><h2 class="section-title">Perangkat monitoring</h2></div>
    <div class="table-wrap"><table class="employee-table"><thead><tr><th>Layar</th><th>Terakhir terhubung</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    @forelse ($daftar as $item)
    <tr><td><strong>{{ $item->nama }}</strong><p class="person-meta">{{ \App\Models\PerangkatMonitoring::CAKUPAN[$item->cakupan] ?? 'Aktivitas siswa' }}</p>@if ($item->kegiatanUjianCbt)<p class="person-meta">{{ $item->kegiatanUjianCbt->nama }}</p>@endif @if ($saatIni?->id === $item->id)<p><x-ui.status type="success">Browser ini</x-ui.status></p>@endif</td>
        <td>{{ $item->terakhir_digunakan_pada?->format('d/m/Y H:i') ?? 'Belum digunakan' }}</td>
        <td><x-ui.status :type="$item->dicabut_pada ? 'neutral' : 'success'">{{ $item->dicabut_pada ? 'Dicabut' : 'Aktif' }}</x-ui.status>@if ($item->dicabut_pada)<p>{{ $item->alasan_pencabutan }}</p>@endif</td>
        <td>@if (!$item->dicabut_pada)<button class="button button-danger" type="button" data-ui-dialog-open="cabut-monitor-{{ $item->id }}"><i data-lucide="shield-off"></i> Cabut akses</button>@endif</td></tr>
    @empty<tr><td colspan="4"><x-ui.empty-state title="Belum ada layar monitoring" /></td></tr>@endforelse
    </tbody></table></div><div class="panel-pad">{{ $daftar->links() }}</div>
</section>
@foreach ($daftar->whereNull('dicabut_pada') as $item)
<x-ui.dialog id="cabut-monitor-{{ $item->id }}" title="Cabut akses monitoring">
    <p>{{ $item->nama }}</p><form method="POST" action="{{ route('perangkat-monitoring.cabut', $item) }}">@csrf @method('DELETE')
        <div class="field"><label for="alasan-{{ $item->id }}">Alasan</label><textarea class="textarea" id="alasan-{{ $item->id }}" name="alasan" required minlength="5" maxlength="500"></textarea></div>
        <div class="actions" style="margin-top:16px"><button type="button" class="button button-muted" data-ui-dialog-close>Batal</button><button class="button button-danger" type="submit">Cabut akses</button></div>
    </form>
</x-ui.dialog>
@endforeach
<script>
(() => { const scope=document.getElementById('cakupan'),field=document.getElementById('kegiatan-field'),select=document.getElementById('kegiatan'),pameran=document.getElementById('pameran-fields');function update(){field.hidden=!['ujian','keduanya'].includes(scope.value);select.required=!field.hidden;select.disabled=field.hidden;pameran.hidden=scope.value!=='pameran';pameran.querySelectorAll('input,select').forEach(el=>{el.disabled=pameran.hidden;el.required=!pameran.hidden&&el.type!=='checkbox';});document.getElementById('confirmation-label').textContent=pameran.hidden?'Layar berada di area terbatas untuk guru dan dikelola sekolah.':'Layar pameran dikelola sekolah dan data yang ditayangkan sudah diperiksa untuk area publik.';}scope.addEventListener('change',update);update(); })();
</script>
@endsection
