<section class="tf-surface" aria-labelledby="tf-rekap-surah">
    <header class="tf-band"><h2 id="tf-rekap-surah">Perkembangan Juz 30</h2><span>{{ $baris->count() }} surah</span></header>
    <ol class="tf-list">
        @forelse ($baris as $item)
            @php($terbaru = $item['terbaru'])
            <li class="tf-setoran-row tf-rekap-surah-row">
                <div class="tf-rekap-surah-info">
                    <span class="tf-number">{{ $item['surah']->nomor }}</span>
                    <div>
                        <p class="person-name">{{ $item['surah']->nama }}</p>
                        @if ($terbaru)
                            <p class="person-meta">@if(isset($terbaru->jumlah_setoran)){{ $terbaru->jumlah_setoran }} setoran &middot; @endif{{ \Illuminate\Support\Carbon::parse($terbaru->tanggal_setoran)->locale('id')->translatedFormat('j M Y') }}</p>
                        @else
                            <p class="person-meta">Belum disetor</p>
                        @endif
                    </div>
                </div>
                @if ($terbaru)<div class="tf-setoran-score"><span>Nilai terbaru</span><strong>{{ $terbaru->labelNilai() }}</strong></div>@endif
            </li>
        @empty
            <li class="empty-state">{{ $status === 'sudah' ? 'Belum ada surah yang disetor.' : ($status === 'belum' ? 'Semua surah sudah disetor.' : 'Daftar surah belum tersedia.') }}</li>
        @endforelse
    </ol>
</section>
