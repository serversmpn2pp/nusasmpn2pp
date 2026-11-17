@if ($soal->isEmpty())
    <div class="package-empty">Tidak ada soal siap yang cocok dengan filter.</div>
@else
    <ul class="package-question-list">
        @foreach ($soal as $item)
            @include('paket-soal-ujian-terpusat.partials.baris-soal', ['dariBank' => true, 'sedangMenyusun' => true])
        @endforeach
    </ul>
    @if ($soal->hasPages())
        <nav class="package-bank-pagination" aria-label="Halaman Bank Soal">
            <button type="button" class="button button-muted" data-bank-page="{{ $soal->currentPage() - 1 }}" @disabled($soal->onFirstPage()) aria-label="Soal halaman sebelumnya" title="Halaman sebelumnya"><i data-lucide="chevron-left" aria-hidden="true"></i></button>
            <span>Hal. {{ $soal->currentPage() }} dari {{ $soal->lastPage() }}</span>
            <button type="button" class="button button-muted" data-bank-page="{{ $soal->currentPage() + 1 }}" @disabled(! $soal->hasMorePages()) aria-label="Soal halaman berikutnya" title="Halaman berikutnya"><i data-lucide="chevron-right" aria-hidden="true"></i></button>
        </nav>
    @endif
@endif
