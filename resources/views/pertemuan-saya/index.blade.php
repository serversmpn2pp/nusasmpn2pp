@extends('layouts.app')
@section('title', 'Pertemuan Saya')
@section('content')
    @include('agenda-humas._style')
    <div class="page-header"><div><p class="eyebrow">Orang Tua / Wali</p><h1 class="page-title">Pertemuan Saya</h1></div></div>
    @include('agenda-humas._messages')
    <nav class="agenda-tabs" aria-label="Daftar pertemuan">@foreach (['mendatang' => 'Undangan mendatang', 'riwayat' => 'Riwayat pertemuan'] as $kode => $label)<a href="{{ route('pertemuan-saya.index', ['tab' => $kode]) }}" @if ($tab === $kode) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
    <div class="agenda-table-shell"><table class="agenda-table agenda-table--list"><thead><tr><th>Pertemuan</th><th>Jadwal & tempat</th><th>Kehadiran</th><th></th></tr></thead><tbody>
        @forelse ($undangan as $peserta)
            <tr><td><strong>{{ $peserta->agenda->judul }}</strong>@foreach ($peserta->anak_undangan ?? [] as $anak)<small>{{ $anak['nama'] }} &middot; {{ $anak['kelas'] }}</small>@endforeach</td><td>{{ $peserta->agenda->waktu_mulai->locale('id')->translatedFormat('d F Y, H:i') }} WIB<small>{{ $peserta->agenda->tempat }}</small><small>{{ $peserta->agenda->labelWaktu() }}</small></td><td><span class="agenda-badge {{ $peserta->status_kehadiran === 'hadir' ? 'agenda-badge--selesai' : '' }}">{{ \App\Models\PesertaPertemuanHumas::KEHADIRAN[$peserta->status_kehadiran] }}</span></td><td>@if ($peserta->agenda->token_presensi)<a class="button button-primary button-sm" href="{{ route('pertemuan-saya.show', $peserta->agenda->token_presensi) }}">Buka pertemuan</a>@endif</td></tr>
        @empty<tr><td colspan="4" class="agenda-empty">Belum ada {{ $tab === 'mendatang' ? 'undangan mendatang' : 'riwayat pertemuan' }}.</td></tr>@endforelse
    </tbody></table></div><div style="margin-top:20px">{{ $undangan->links() }}</div>
@endsection
