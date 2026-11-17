@php
    $dariBank = $dariBank ?? false;
    $skor = (float) ($skor ?? $item->skor_maksimal);
@endphp
<li class="package-question" data-package-question data-question-id="{{ $item->id }}" data-question-score="{{ $skor }}">
    @if ($dariBank)
        <label class="package-question-picker" data-bank-picker>
            <input type="checkbox" class="package-question-check" data-bank-check aria-label="Pilih soal {{ $item->kode }}">
        </label>
    @else
        @if ($sedangMenyusun)
            <input type="hidden" name="soal[{{ $item->id }}][dipilih]" value="1">
        @endif
    @endif
    <span class="package-question-position" data-package-position @if ($dariBank) hidden @endif>{{ $nomor ?? '' }}</span>
    <div class="package-question-text">
        <strong>{{ $item->kode }}</strong>
        <p>{{ str(strip_tags($item->pertanyaan))->squish()->limit(220) }}</p>
        @if ($topik = $item->topik ?: $item->materi)<small>{{ $topik }}</small>@endif
        @if ($dariBank && $item->folders->isNotEmpty())
            <small data-bank-folders>{{ $item->folders->pluck('nama')->join(', ') }}</small>
        @endif
    </div>
    <div class="package-question-meta">
        <strong>{{ $item->labelJenis() }}</strong>
        <small>{{ $item->labelKesulitan() }} &middot; {{ number_format($skor, 0, ',', '.') }} skor</small>
        @if ($dariBank)
            <small data-bank-in-package hidden>Sudah di paket</small>
        @elseif (! $item->aktif || $item->status !== 'siap')
            <small class="badge badge-warning">Soal tidak siap</small>
        @endif
    </div>
    <div class="package-question-actions">
        <button type="button" class="button button-muted" data-question-preview aria-haspopup="dialog">
            <i data-lucide="eye" aria-hidden="true"></i> Pratinjau
        </button>
        @if ($sedangMenyusun)
            <div class="package-question-tools" data-package-tools @if ($dariBank) hidden @endif>
                <button type="button" class="button button-muted" data-package-move="up" title="Naikkan soal" aria-label="Naikkan soal {{ $item->kode }}"><i data-lucide="arrow-up" aria-hidden="true"></i></button>
                <button type="button" class="button button-muted" data-package-move="down" title="Turunkan soal" aria-label="Turunkan soal {{ $item->kode }}"><i data-lucide="arrow-down" aria-hidden="true"></i></button>
                <button type="button" class="button button-danger" data-package-remove title="Lepas dari paket" aria-label="Lepas soal {{ $item->kode }} dari paket"><i data-lucide="x" aria-hidden="true"></i></button>
            </div>
        @endif
    </div>
    @include('soal-cbt.partials.preview-source', ['previewDalamBaris' => true])
</li>
