@php
    $panitiaSiap = $panitiaSiap ?? $kegiatan->panitiaUjianCbt->isNotEmpty();
    $sesiSiap = $sesiSiap ?? $kegiatan->sesiKegiatanUjianCbt->isNotEmpty();
    $ruangSiap = $ruangSiap ?? $kegiatan->ruangKegiatanUjianCbt->isNotEmpty();
    $kelompokAlur = $kegiatan->kelompokPesertaKegiatanUjianCbt;
    $penetapanRuangSiap = $kelompokAlur->isNotEmpty();
    $pembagianPesertaSiap = $penetapanRuangSiap
        && $kelompokAlur->every(fn ($kelompok) => (int) ($kelompok->penempatan_peserta_ujian_cbt_count ?? $kelompok->jumlah_peserta) > 0);
    $jumlahJadwalAlur = $kegiatan->jadwalUjianCbt->count();
    $jadwalSiap = $jumlahJadwalAlur > 0;
    $jumlahPaketSiapAlur = $kegiatan->jadwalUjianCbt
        ->filter(fn ($jadwal) => $jadwal->ujianCbt && in_array($jadwal->ujianCbt->status, ['terjadwal', 'berlangsung', 'selesai'], true))
        ->count();
    $paketSiapAlur = $jadwalSiap && $jumlahPaketSiapAlur === $jumlahJadwalAlur;
    $jumlahPaketSelesaiAlur = $kegiatan->jadwalUjianCbt
        ->filter(fn ($jadwal) => $jadwal->ujianCbt?->status === 'selesai')
        ->count();
    $hasilSiapAlur = $jadwalSiap && $jumlahPaketSelesaiAlur > 0;
    $bolehBukaPersiapanAlur = $kegiatan->dapatDiaksesOleh(auth()->user());
@endphp

<style>
    .central-steps {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 8px;
        margin: 18px 0 24px;
    }
    .central-step {
        display: grid;
        grid-template-columns: 30px minmax(0, 1fr);
        gap: 8px;
        align-items: center;
        min-height: 64px;
        padding: 10px;
        border: 1px solid var(--line);
        border-left: 4px solid var(--line);
        border-radius: 7px;
        background: #fff;
        color: inherit;
        text-decoration: none;
    }
    .central-step.complete { border-left-color: #15803d; background: #f4fbf6; }
    .central-step.active { border-color: var(--primary); border-left-color: var(--accent); background: var(--primary-soft); box-shadow: 0 0 0 2px rgba(21, 71, 122, .08); }
    .central-step.disabled { cursor: default; }
    .central-step > .central-step-number {
        display: grid;
        width: 30px;
        height: 30px;
        margin: 0;
        place-items: center;
        border-radius: 50%;
        background: var(--primary-soft);
        color: var(--primary-dark);
        font-size: .78rem;
        font-weight: 900;
        line-height: 1;
    }
    .central-step.complete .central-step-number { background: #dcf3e3; color: #166534; }
    .central-step.active .central-step-number { background: var(--primary); color: #fff; }
    .central-step-copy, .central-step-copy strong, .central-step-status { display: block; min-width: 0; }
    .central-step-copy strong { font-size: .75rem; line-height: 1.25; }
    .central-step-status { margin-top: 3px; color: var(--muted); font-size: .66rem; font-weight: 700; line-height: 1.25; }
    @media (max-width: 960px) { .central-steps { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 640px) { .central-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>

<nav class="central-steps" aria-label="Alur Ujian Terpusat">
    <a @if($bolehBukaPersiapanAlur) href="{{ ($bolehKelolaUtama ?? false) ? route('ujian-terpusat.edit', $kegiatan) : route('ujian-terpusat.show', ['kegiatanUjianCbt' => $kegiatan, 'tahap' => 2]) }}" @else aria-disabled="true" @endif class="central-step complete {{ $bolehBukaPersiapanAlur ? '' : 'disabled' }} {{ ($tahapAktif ?? 0) === 1 ? 'active' : '' }}"><span class="central-step-number">1</span><span class="central-step-copy"><strong>Informasi</strong><span class="central-step-status">Sudah diisi</span></span></a>
    <a @if($bolehBukaPersiapanAlur) href="{{ route('ujian-terpusat.show', ['kegiatanUjianCbt' => $kegiatan, 'tahap' => 2]) }}" @else aria-disabled="true" @endif class="central-step {{ $panitiaSiap ? 'complete' : '' }} {{ $bolehBukaPersiapanAlur ? '' : 'disabled' }} {{ ($tahapAktif ?? 0) === 2 ? 'active' : '' }}"><span class="central-step-number">2</span><span class="central-step-copy"><strong>Panitia</strong><span class="central-step-status">{{ $panitiaSiap ? 'Sudah diisi' : 'Belum diisi' }}</span></span></a>
    <a @if($bolehBukaPersiapanAlur) href="{{ route('ujian-terpusat.show', ['kegiatanUjianCbt' => $kegiatan, 'tahap' => 3]) }}" @else aria-disabled="true" @endif class="central-step {{ $sesiSiap ? 'complete' : '' }} {{ $bolehBukaPersiapanAlur ? '' : 'disabled' }} {{ ($tahapAktif ?? 0) === 3 ? 'active' : '' }}"><span class="central-step-number">3</span><span class="central-step-copy"><strong>Sesi</strong><span class="central-step-status">{{ $sesiSiap ? 'Sudah diisi' : 'Belum diisi' }}</span></span></a>
    <a @if($bolehBukaPersiapanAlur) href="{{ route('ujian-terpusat.show', ['kegiatanUjianCbt' => $kegiatan, 'tahap' => 4]) }}" @else aria-disabled="true" @endif class="central-step {{ $ruangSiap ? 'complete' : '' }} {{ $bolehBukaPersiapanAlur ? '' : 'disabled' }} {{ ($tahapAktif ?? 0) === 4 ? 'active' : '' }}"><span class="central-step-number">4</span><span class="central-step-copy"><strong>Ruang</strong><span class="central-step-status">{{ $ruangSiap ? 'Sudah diisi' : 'Belum diisi' }}</span></span></a>
    <a @if($bolehBukaPersiapanAlur) href="{{ route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 5]) }}" @else aria-disabled="true" @endif class="central-step {{ $penetapanRuangSiap ? 'complete' : '' }} {{ $bolehBukaPersiapanAlur ? '' : 'disabled' }} {{ ($tahapAktif ?? 0) === 5 ? 'active' : '' }}"><span class="central-step-number">5</span><span class="central-step-copy"><strong>Penetapan ruang</strong><span class="central-step-status">{{ $penetapanRuangSiap ? 'Sudah ditetapkan' : 'Belum ditetapkan' }}</span></span></a>
    <a @if($bolehBukaPersiapanAlur) href="{{ route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 6]) }}" @else aria-disabled="true" @endif class="central-step {{ $pembagianPesertaSiap ? 'complete' : '' }} {{ $bolehBukaPersiapanAlur ? '' : 'disabled' }} {{ ($tahapAktif ?? 0) === 6 ? 'active' : '' }}"><span class="central-step-number">6</span><span class="central-step-copy"><strong>Pembagian peserta</strong><span class="central-step-status">{{ $pembagianPesertaSiap ? 'Sudah dibagi' : 'Belum dibagi' }}</span></span></a>
    <a @if($bolehBukaPersiapanAlur) href="{{ route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 7]) }}" @else aria-disabled="true" @endif class="central-step {{ $jadwalSiap ? 'complete' : '' }} {{ $bolehBukaPersiapanAlur ? '' : 'disabled' }} {{ ($tahapAktif ?? 0) === 7 ? 'active' : '' }}"><span class="central-step-number">7</span><span class="central-step-copy"><strong>Jadwal ujian</strong><span class="central-step-status">{{ $jadwalSiap ? $jumlahJadwalAlur.' jadwal' : 'Belum disusun' }}</span></span></a>
    <a href="{{ route('paket-soal-terpusat.index', ['kegiatan' => $kegiatan->id]) }}" class="central-step {{ $paketSiapAlur ? 'complete' : '' }} {{ ($tahapAktif ?? 0) === 8 ? 'active' : '' }}"><span class="central-step-number">8</span><span class="central-step-copy"><strong>Paket soal</strong><span class="central-step-status">{{ $paketSiapAlur ? 'Semua siap' : $jumlahPaketSiapAlur.' siap' }}</span></span></a>
    <a href="{{ route('ujian-terpusat.pelaksanaan-nilai.index', $kegiatan) }}" class="central-step {{ $paketSiapAlur ? 'complete' : '' }} {{ ($tahapAktif ?? 0) === 9 ? 'active' : '' }}"><span class="central-step-number">9</span><span class="central-step-copy"><strong>Pelaksanaan</strong><span class="central-step-status">{{ $paketSiapAlur ? 'Siap dipantau' : 'Menunggu paket' }}</span></span></a>
    <a href="{{ route('ujian-terpusat.nilai-hasil.index', $kegiatan) }}" class="central-step {{ $hasilSiapAlur ? 'complete' : '' }} {{ ($tahapAktif ?? 0) === 10 ? 'active' : '' }}"><span class="central-step-number">10</span><span class="central-step-copy"><strong>Nilai & hasil</strong><span class="central-step-status">{{ $hasilSiapAlur ? $jumlahPaketSelesaiAlur.' paket selesai' : 'Menunggu ujian' }}</span></span></a>
</nav>
