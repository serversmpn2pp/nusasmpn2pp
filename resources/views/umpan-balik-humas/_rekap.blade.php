@foreach($formulir->pertanyaan as $p)
@php($r = $rekap['pertanyaan'][$p->id])
<article class="uf-results"><h3>{{ $p->urutan }}. {{ $p->teks }}</h3>
    @if($p->jenis === 'skala')
        <div class="uf-stats"><div><span>Rata-rata (1-4)</span><strong>{{ $r['rata'] !== null ? number_format($r['rata'], 2, ',', '.') : '-' }}</strong></div><div><span>Puas / sangat puas</span><strong>{{ $r['puas'] !== null ? number_format($r['puas'], 1, ',', '.').'%' : '-' }}</strong></div><div><span>Memberi penilaian</span><strong>{{ $r['menilai'] }}</strong></div></div>
        <div class="uf-bars">@foreach([1,2,3,4] as $nilai)<div class="uf-bar"><span>{{ \App\Models\PertanyaanUmpanBalikHumas::SKALA[$nilai] }}</span><progress value="{{ $r['skala'][$nilai] }}" max="{{ max(1, $r['menilai']) }}" aria-label="{{ \App\Models\PertanyaanUmpanBalikHumas::SKALA[$nilai] }}"></progress><strong>{{ $r['skala'][$nilai] }}</strong></div>@endforeach</div>
        <p class="agenda-muted">Tidak menilai: {{ $r['skala'][0] }} &middot; Belum / tidak mengisi: {{ $rekap['respons'] - array_sum($r['skala']) }}</p>
    @else<p>{{ $r['teks'] }} jawaban tertulis</p>@if($r['teks'])<a class="button button-muted" href="{{ route('umpan-balik-humas.show', [$formulir, 'pertanyaan_id' => $p->id]) }}#jawaban-tertulis">Lihat jawaban tertulis</a>@endif
    @endif
</article>
@endforeach
