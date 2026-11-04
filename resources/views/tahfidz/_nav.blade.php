<nav class="tf-tabs" aria-label="Menu Tahfidz">
    <a href="{{ route('tahfidz.index') }}" @if(request()->routeIs('tahfidz.index')) aria-current="page" @endif>
        <i data-lucide="users" aria-hidden="true"></i> Kelompok
    </a>
    <a href="{{ route('tahfidz.surah') }}" @if(request()->routeIs('tahfidz.surah')) aria-current="page" @endif>
        <i data-lucide="book-open" aria-hidden="true"></i> Surah Juz 30
    </a>
    @if (app(\App\Services\Tahfidz\AksesTahfidz::class)->kelola(auth()->user()))
        <a href="{{ route('tahfidz.wisuda.index') }}" @if(request()->routeIs('tahfidz.wisuda.index')) aria-current="page" @endif><i data-lucide="graduation-cap" aria-hidden="true"></i> Wisuda</a>
    @endif
</nav>
