<nav class="tf-tabs" aria-label="Menu Rapor Tahfidz">
    <a href="{{ route('tahfidz.rapor.index') }}" @if(request()->routeIs('tahfidz.rapor.index')) aria-current="page" @endif><i data-lucide="users" aria-hidden="true"></i> Rapor kelompok</a>
    @if (app(\App\Services\Tahfidz\AksesTahfidz::class)->kelola(auth()->user()))
        <a href="{{ route('tahfidz.rapor.pengaturan', isset($kelompok) ? ['tahun' => $kelompok->tahun_pelajaran_id] : []) }}" @if(request()->routeIs('tahfidz.rapor.pengaturan')) aria-current="page" @endif><i data-lucide="settings-2" aria-hidden="true"></i> Target rapor</a>
    @endif
</nav>
