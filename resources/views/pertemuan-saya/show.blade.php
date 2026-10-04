@extends('layouts.app')
@section('title', 'Presensi Pertemuan')
@section('content')
    @include('agenda-humas._style')
    <div class="page-header"><div><p class="eyebrow">Orang Tua / Wali</p><h1 class="page-title">Presensi Pertemuan</h1></div><a class="button button-muted" href="{{ route('pertemuan-saya.index') }}">Pertemuan Saya</a></div>
    @include('agenda-humas._messages')
    @if (! $peserta)
        <div class="alert alert-warning"><strong>Akun Anda tidak terdaftar dalam undangan ini.</strong> Hubungi petugas Humas atau gunakan akun orang tua yang diundang.</div>
    @else
        <section class="agenda-section agenda-confirmation">
            <h2>{{ $agendaHumas->judul }}</h2>
            <dl class="agenda-facts">
                <div><dt>Hari / tanggal</dt><dd>{{ $agendaHumas->waktu_mulai->locale('id')->translatedFormat('l, d F Y') }}</dd></div>
                <div><dt>Waktu</dt><dd>{{ $agendaHumas->waktu_mulai->format('H:i') }} - {{ $agendaHumas->waktu_selesai->format('H:i') }} WIB</dd></div>
                <div><dt>Tempat</dt><dd>{{ $agendaHumas->tempat }}</dd></div>
                <div><dt>Peserta</dt><dd>{{ $peserta->nama }}</dd></div>
            </dl>
            <div class="agenda-line">@foreach ($peserta->anak_undangan ?? [] as $anak)<span class="agenda-child">{{ $anak['nama'] }} &middot; {{ $anak['kelas'] }}</span>@endforeach</div>
            @if ($peserta->status_kehadiran === 'hadir')
                <div class="agenda-line"><span class="agenda-badge agenda-badge--selesai">Kehadiran telah tercatat</span><p>{{ $peserta->hadir_pada?->locale('id')->translatedFormat('d F Y, H:i') }} WIB</p></div>
            @elseif ($agendaHumas->status === 'dibatalkan')
                <div class="alert alert-warning">Pertemuan dibatalkan.</div>
            @elseif (! $agendaHumas->menerimaPresensiQr())
                <div class="alert alert-warning">{{ $agendaHumas->status === 'selesai' ? 'Pertemuan sudah selesai.' : 'Presensi belum dibuka atau sudah ditutup oleh Humas.' }}</div>
            @elseif ($peserta->status_kehadiran !== 'belum_dicatat')
                <div class="alert alert-warning">Kehadiran dicatat sebagai <strong>{{ \App\Models\PesertaPertemuanHumas::KEHADIRAN[$peserta->status_kehadiran] }}</strong>. Hubungi Humas bila perlu koreksi.</div>
            @else
                <form method="POST" action="{{ route('pertemuan-saya.hadir', $agendaHumas->token_presensi) }}" class="agenda-actions" data-agenda-submit>@csrf<button class="button button-primary">Konfirmasi hadir</button></form>
            @endif
        </section>
    @endif
    @include('agenda-humas._scripts')
@endsection
