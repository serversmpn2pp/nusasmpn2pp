<section class="tf-surface tf-section" aria-labelledby="tf-daftar-setoran">
    <header class="tf-band"><h2 id="tf-daftar-setoran">{{ $riwayatSiswa ? 'Riwayat setoran' : 'Setoran pertemuan' }}</h2><span>{{ $setoran->total() }} setoran</span></header>
    <ul class="tf-list">
        @forelse ($setoran as $item)
            <li class="tf-setoran-row">
                <div>
                    <p class="person-name">{{ $riwayatSiswa ? $item->surah->nama : $item->anggota->siswa->nama_lengkap }}</p>
                    <p class="person-meta">{{ $riwayatSiswa ? $item->pertemuan->tanggal->locale('id')->translatedFormat('j F Y') : $item->surah->nama }} &middot; {{ $item->penilai?->nama ?? 'Penilai tidak tersedia' }}</p>
                    @if (filled($item->catatan))<p class="tf-setoran-note">{{ $item->catatan }}</p>@endif
                </div>
                <div class="tf-setoran-score"><span>Nilai</span><strong>{{ $item->labelNilai() }}</strong></div>
                @if (! $riwayatSiswa)
                    <a href="{{ route('tahfidz.anggota.riwayat', [$kelompok, $item->anggota_tahfidz_id]) }}" class="button button-muted" aria-label="Riwayat {{ $item->anggota->siswa->nama_lengkap }}"><i data-lucide="history" aria-hidden="true"></i> Riwayat</a>
                @endif
            </li>
        @empty
            <li class="empty-state">Belum ada setoran.</li>
        @endforelse
    </ul>
</section>
@if ($setoran->hasPages())<div class="tf-pagination">{{ $setoran->links() }}</div>@endif
