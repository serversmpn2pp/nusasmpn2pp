<div class="page-header">
    <div><p class="eyebrow">Supervisi Akademik</p><h1 class="page-title">{{ $judul }}</h1></div>
    <a class="button button-muted" href="{{ route('supervisi.sesi.show', $sesi['id']) }}">Rincian jadwal</a>
</div>
@include('supervisi._style')
<section class="sp-context">
    <div><span class="sp-context-label">Guru yang disupervisi</span><h2>{{ $sesi['konteks']['guru'] }}</h2><p>{{ $sesi['konteks']['mapel'] }} · {{ $sesi['konteks']['kelas'] }}</p></div>
    <dl><div><dt>Supervisor</dt><dd>{{ $sesi['supervisor'] }}</dd></div><div><dt>Jadwal</dt><dd>{{ \Carbon\Carbon::parse($sesi['waktu_mulai'])->translatedFormat('d F Y, H:i') }} - {{ \Carbon\Carbon::parse($sesi['waktu_selesai'])->format('H:i') }}</dd></div></dl>
</section>
<nav class="sp-stage-nav" aria-label="Tahapan supervisi">
    <a href="{{ route('supervisi.praobservasi.show', $sesi['id']) }}" @if(request()->routeIs('*.praobservasi.*')) aria-current="page" @endif><span class="sp-step">1</span> Praobservasi <span class="sp-badge {{ $praobservasi['status'] }}">{{ \App\Models\PraobservasiSupervisi::STATUS[$praobservasi['status']] }}</span></a>
    <a href="{{ route('supervisi.penilaian.show', $sesi['id']) }}" @if(request()->routeIs('*.penilaian.*')) aria-current="page" @endif><span class="sp-step">2</span> Penilaian <span class="sp-badge {{ $penilaian['status'] }}">{{ $penilaian['status'] === 'final' ? 'Final' : ($penilaian['status'] === 'draf' ? 'Draf supervisor' : 'Belum dinilai') }}</span></a>
    <a href="{{ route('supervisi.pascaobservasi.show', $sesi['id']) }}" @if(request()->routeIs('*.pascaobservasi.*', '*.refleksi.*', '*.tindak-lanjut.*')) aria-current="page" @endif><span class="sp-step">3</span> Pascaobservasi <span class="sp-badge {{ $pascaobservasi['status'] }}">{{ !($pascaobservasi['hasil_terbaru'] ?? true) && $pascaobservasi['status'] !== 'belum_diisi' ? 'Perlu penyesuaian' : \App\Models\PascaobservasiSupervisi::STATUS[$pascaobservasi['status']] }}</span></a>
</nav>
@if($sesi['status'] !== 'terjadwal')<p class="sp-note">Pengisian tidak tersedia untuk jadwal {{ \App\Models\SesiSupervisi::STATUS[$sesi['status']] }}.</p>@endif
