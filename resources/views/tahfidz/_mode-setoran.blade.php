<nav class="tf-tabs" aria-label="Mode input setoran">
    <a href="{{ route('tahfidz.pertemuan.show', [$kelompok, $pertemuan]) }}" @if ($modeSetoran === 'tunggal') aria-current="page" @endif>
        <i data-lucide="list-plus" aria-hidden="true"></i> Per setoran
    </a>
    <a href="{{ route('tahfidz.kolektif.index', [$kelompok, $pertemuan]) }}" @if ($modeSetoran === 'kolektif') aria-current="page" @endif>
        <i data-lucide="table-2" aria-hidden="true"></i> Input kolektif
    </a>
</nav>
