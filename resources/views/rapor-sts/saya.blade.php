@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Rapor STS')
@section('content')
    <div class="page-header"><div><p class="eyebrow">Penilaian</p><h1 class="page-title">{{ $data['mode'] === 'orang_tua' ? 'Rapor STS Anak Saya' : 'Rapor STS Saya' }}</h1></div></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Siswa</th><th>Periode STS</th><th>Publikasi</th><th>Rapor</th></tr></thead><tbody>
        @forelse($data['items'] as $r)<tr><td><strong>{{ $r['siswa']['nama'] }}</strong><div class="help-text">{{ $r['kelas']['nama'] }}</div></td>
            <td>{{ $r['kegiatan']['nama'] }}<div class="help-text">{{ $r['kegiatan']['tahun_pelajaran'] }} · Semester {{ ucfirst($r['kegiatan']['semester']) }}</div></td>
            <td>Versi {{ $r['versi'] }}<div class="help-text">{{ \Carbon\Carbon::parse($r['dipublikasikan_pada'])->format('d-m-Y H:i') }}</div></td>
            <td><a class="button button-primary" href="{{ route('rapor-sts-saya.show', $r['id']) }}">Lihat rapor</a>@if($r['sertakan_perilaku'])<div class="help-text">Dengan lampiran perilaku</div>@endif</td></tr>
        @empty<tr><td colspan="4" style="text-align:center;padding:40px">Belum ada Rapor STS yang dipublikasikan untuk akun ini.</td></tr>@endforelse
    </tbody></table></div>
@endsection
