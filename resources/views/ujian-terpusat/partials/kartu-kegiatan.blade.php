@php
    $panitiaSiap = $kegiatan->panitia_ujian_cbt_count > 0;
    $sesiSiap = $kegiatan->sesi_kegiatan_ujian_cbt_count > 0;
    $ruangSiap = $kegiatan->ruang_kegiatan_ujian_cbt_count > 0;
@endphp

<article class="panel central-item {{ $riwayat ? 'is-history' : '' }}">
    <div>
        <div class="central-item-title">
            <div>
                <p class="eyebrow">{{ $kegiatan->jenisUjianCbt?->nama ?: 'Ujian Terpusat' }}</p>
                <h3>{{ $kegiatan->nama }}</h3>
            </div>
            <div class="central-item-status">
                <span class="badge {{ $statusKegiatan['badge'] }}">{{ $statusKegiatan['label'] }}</span>
                <span>Status administrasi: {{ $kegiatan->labelStatus() }}</span>
            </div>
        </div>
        <p class="help-text central-item-period">{{ $kegiatan->tahunPelajaran?->nama ?: '-' }} · Semester {{ ucfirst($kegiatan->semester) }} · {{ $kegiatan->labelPeriode() }}</p>
    </div>

    <div class="central-readiness" aria-label="Kesiapan {{ $kegiatan->nama }}">
        <div class="{{ $panitiaSiap ? 'complete' : '' }}"><strong>{{ $kegiatan->panitia_ujian_cbt_count }}</strong><span>Panitia</span></div>
        <div class="{{ $sesiSiap ? 'complete' : '' }}"><strong>{{ $kegiatan->sesi_kegiatan_ujian_cbt_count }}</strong><span>Sesi</span></div>
        <div class="{{ $ruangSiap ? 'complete' : '' }}"><strong>{{ $kegiatan->ruang_kegiatan_ujian_cbt_count }}</strong><span>Ruang</span></div>
    </div>

    <div class="actions">
        <a href="{{ route('ujian-terpusat.show', $kegiatan) }}" class="button {{ $riwayat ? 'button-muted' : 'button-primary' }}">
            {{ $riwayat ? 'Lihat kegiatan' : 'Buka persiapan' }}
        </a>
    </div>
</article>
