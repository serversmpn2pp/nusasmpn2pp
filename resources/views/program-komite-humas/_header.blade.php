@include('komite-humas._style')
@include('program-komite-humas._style')
<div class="page-header">
    <div style="min-width:0"><p class="eyebrow">Humas / Komite Sekolah</p><h1 class="page-title">{{ $judulHalaman }}</h1><p class="agenda-muted">{{ $periode->nama }} &middot; {{ $periode->tanggal_mulai->format('d-m-Y') }} s.d. {{ $periode->tanggal_selesai->format('d-m-Y') }}</p></div>
    <div class="actions">@yield('program-actions')</div>
</div>
@include('agenda-humas._messages')
<nav class="agenda-tabs" aria-label="Bagian komite"><a href="{{ route('komite-humas.show', $periode) }}">Kepengurusan & SK</a><a href="{{ route('komite-humas.program.index', $periode) }}" aria-current="page">Program kerja & rapat</a></nav>
@if ($periode->status === 'arsip')<div class="alert alert-warning">Kepengurusan diarsipkan. Program kerja dan rapat tetap tersimpan sebagai riwayat.</div>@elseif ($periode->status === 'draf')<div class="alert alert-info">Kepengurusan masih draf. Program dapat disiapkan dengan status Direncanakan.</div>@endif
