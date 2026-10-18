<section class="panel panel-pad placement-class-card">
    <div class="placement-class-title">
        <div>
            <p class="eyebrow">Kelas tujuan</p>
            <h2 class="panel-title">{{ $kelasDipilih->nama }}</h2>
        </div>
        @if ($kelasDipilih->aktif)
            <span class="badge badge-active">Aktif</span>
        @else
            <span class="badge badge-inactive">Nonaktif</span>
        @endif
    </div>

    <dl class="quick-facts">
        <div>
            <dt>Tahun</dt>
            <dd>{{ $kelasDipilih->tahunPelajaran?->nama ?: '-' }}</dd>
        </div>
        <div>
            <dt>Wali kelas</dt>
            <dd>{{ $kelasDipilih->waliKelas?->nama_lengkap ?: '-' }}</dd>
        </div>
        <div>
            <dt>Kapasitas</dt>
            <dd>{{ $kelasDipilih->kapasitas ?: '-' }}</dd>
        </div>
        <div>
            <dt>Sisa kursi</dt>
            <dd>{{ $sisaKursi === null ? '-' : $sisaKursi }}</dd>
        </div>
    </dl>

    <div class="actions">
        <a href="{{ route('kelas.show', $kelasDipilih) }}" class="button button-muted">Detail kelas</a>
        @izin('kelas.kelola')
            <a href="{{ route('kelas.edit', $kelasDipilih) }}" class="button button-dark">Edit kelas</a>
        @endizin
    </div>
</section>
