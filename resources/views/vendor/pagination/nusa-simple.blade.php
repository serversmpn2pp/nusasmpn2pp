@if ($paginator->hasPages())
    <nav class="pagination-simple" role="navigation" aria-label="Navigasi halaman">
        <span>Halaman {{ $paginator->currentPage() }}</span>

        <div class="actions">
            @if ($paginator->onFirstPage())
                <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
            @else
                <a class="button button-muted" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="button button-muted" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
            @else
                <span class="button button-muted" aria-disabled="true">Berikutnya</span>
            @endif
        </div>
    </nav>
@endif
