@extends('layouts.app')
@section('admin-module', 'kehadiran')
@section('title', 'Perangkat Scanner - NUSA')
@section('content')
    <div class="page-header">
        <div><p class="eyebrow">Kehadiran</p><h1 class="page-title">Perangkat scanner</h1></div>
        <div class="actions">
            @if ($perangkatSaatIni?->mengizinkan('siswa'))<a class="button button-primary" href="{{ route('scan-absensi.index') }}" target="_blank" rel="noopener"><i data-lucide="scan-line" aria-hidden="true"></i> Scan siswa</a>@endif
            @if ($perangkatSaatIni?->mengizinkan('pegawai'))<a class="button button-primary" href="{{ route('scan-absensi-pegawai.index') }}" target="_blank" rel="noopener"><i data-lucide="scan-line" aria-hidden="true"></i> Scan pegawai</a>@endif
            @if ($perangkatSaatIni)<form method="POST" action="{{ route('logout') }}">@csrf<button class="button button-muted" type="submit"><i data-lucide="log-out" aria-hidden="true"></i> Keluar akun admin</button></form>@endif
        </div>
    </div>
    @if (session('berhasil'))<x-ui.alert type="success">{{ session('berhasil') }}</x-ui.alert>@endif
    @if ($errors->any())<x-ui.alert type="danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif

    <section class="panel panel-pad" style="margin-bottom:24px;">
        <h2 class="section-title">Browser pada komputer ini</h2>
        @if ($perangkatSaatIni)
            <x-ui.alert type="success" title="Scanner aktif">{{ $perangkatSaatIni->nama }} &middot; {{ \App\Models\PerangkatScanPresensi::CAKUPAN[$perangkatSaatIni->cakupan] }}</x-ui.alert>
        @else
            <x-ui.alert type="warning" title="Belum diaktifkan">Aktifkan dari browser yang akan digunakan pada komputer scanner.</x-ui.alert>
        @endif
        <form method="POST" action="{{ route('perangkat-scan.store') }}" id="aktivasiScanner">
            @csrf
            <div class="form-grid">
                <div class="field"><label for="nama">Nama perangkat</label><input class="input" name="nama" id="nama" maxlength="100" required placeholder="Contoh: Scanner gerbang utama" value="{{ old('nama', $perangkatSaatIni?->nama) }}"></div>
                <div class="field"><label for="cakupan">Akses presensi</label><select class="select" id="cakupan" name="cakupan">@foreach (\App\Models\PerangkatScanPresensi::CAKUPAN as $value => $label)<option value="{{ $value }}" @selected(old('cakupan', $perangkatSaatIni?->cakupan ?? $cakupanAwal) === $value)>{{ $label }}</option>@endforeach</select></div>
            </div>
            <label style="display:flex;gap:10px;align-items:flex-start;margin:18px 0;"><input type="checkbox" name="konfirmasi" value="1" required> <span>Browser ini digunakan pada komputer scanner sekolah yang dikelola petugas.</span></label>
            <div class="actions"><button class="button button-primary" type="submit"><i data-lucide="shield-check" aria-hidden="true"></i> {{ $perangkatSaatIni ? 'Perbarui aktivasi browser' : 'Aktifkan browser ini' }}</button></div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-pad"><h2 class="section-title">Daftar perangkat</h2></div>
        <div class="table-wrap"><table class="employee-table"><thead><tr><th>Perangkat</th><th>Akses</th><th>Terakhir terhubung</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @forelse ($daftar as $item)
            <tr>
                <td><p class="person-name">{{ $item->nama }}</p><p class="person-meta">Diaktifkan {{ $item->created_at->format('d/m/Y H:i') }} oleh {{ $item->pengaktif?->nama ?? 'Akun tidak tersedia' }}</p>@if ($perangkatSaatIni?->id === $item->id)<x-ui.status type="success">Browser ini</x-ui.status>@endif</td>
                <td>{{ \App\Models\PerangkatScanPresensi::CAKUPAN[$item->cakupan] }}</td>
                <td>{{ $item->terakhir_digunakan_pada?->format('d/m/Y H:i') ?? 'Belum digunakan' }}<p class="person-meta">{{ $item->ip_terakhir }}</p></td>
                <td><x-ui.status :type="$item->dicabut_pada ? 'neutral' : 'success'">{{ $item->dicabut_pada ? 'Dicabut' : 'Aktif' }}</x-ui.status>@if ($item->dicabut_pada)<p class="person-meta">{{ $item->dicabut_pada->format('d/m/Y H:i') }}<br>{{ $item->alasan_pencabutan }}</p>@endif</td>
                <td>@if (!$item->dicabut_pada)<button class="button button-danger" type="button" data-ui-dialog-open="cabut-scanner-{{ $item->id }}"><i data-lucide="shield-off" aria-hidden="true"></i> Cabut akses</button>@else<span class="muted">-</span>@endif</td>
            </tr>
        @empty<tr><td colspan="5"><x-ui.empty-state title="Belum ada perangkat scanner" /></td></tr>@endforelse
        </tbody></table></div>
        <div class="panel-pad">{{ $daftar->links() }}</div>
    </section>
    @foreach ($daftar->whereNull('dicabut_pada') as $item)
        <x-ui.dialog id="cabut-scanner-{{ $item->id }}" title="Cabut akses scanner">
            <p>{{ $item->nama }}</p>
            <form method="POST" action="{{ route('perangkat-scan.cabut', $item) }}">
                @csrf @method('DELETE')
                <div class="field"><label for="alasan-{{ $item->id }}">Alasan pencabutan</label><textarea class="textarea" id="alasan-{{ $item->id }}" name="alasan" minlength="5" maxlength="500" required></textarea></div>
                <div class="actions" style="margin-top:16px;"><button type="button" class="button button-muted" data-ui-dialog-close>Batal</button><button class="button button-danger" type="submit">Cabut akses</button></div>
            </form>
        </x-ui.dialog>
    @endforeach
    <script>document.getElementById('aktivasiScanner').addEventListener('submit', function () { this.querySelector('button[type="submit"]').disabled = true; });</script>
@endsection
