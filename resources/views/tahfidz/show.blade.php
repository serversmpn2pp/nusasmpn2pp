@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Detail Kelompok Tahfidz - NUSA')
@include('tahfidz._assets')

@section('content')
    <div class="tf-page">
        <div class="page-header">
            <div><p class="eyebrow">Tahfidz / Kelompok</p><h1 class="page-title">{{ $kelompok->nama }}</h1></div>
            <div class="actions">
                <a href="{{ route('tahfidz.index', ['tahun' => $kelompok->tahun_pelajaran_id]) }}" class="button button-muted"><i data-lucide="arrow-left" aria-hidden="true"></i> Kelompok</a>
                <a href="{{ route('tahfidz.rekap.kelompok', $kelompok) }}" class="button button-muted"><i data-lucide="chart-no-axes-column" aria-hidden="true"></i> Rekap perkembangan</a>
                @if ($bolehKelola)
                    <a href="{{ route('tahfidz.edit', $kelompok) }}" class="button button-muted"><i data-lucide="pencil" aria-hidden="true"></i> Edit kelompok</a>
                    @if ($kelompok->aktif)
                        <a href="{{ route('tahfidz.anggota.edit', $kelompok) }}" class="button button-primary"><i data-lucide="users" aria-hidden="true"></i> Atur anggota</a>
                    @endif
                @endif
            </div>
        </div>
        @include('tahfidz._pesan')

        <dl class="tf-facts">
            <div><dt>Pembimbing</dt><dd>{{ $kelompok->pembimbing->nama_lengkap }}</dd></div>
            <div><dt>Tahun pelajaran</dt><dd>{{ $kelompok->tahunPelajaran->nama }}</dd></div>
            <div><dt>Status kelompok</dt><dd><span class="badge {{ $kelompok->aktif ? 'badge-active' : 'badge-inactive' }}">{{ $kelompok->aktif ? 'Aktif' : 'Nonaktif' }}</span></dd></div>
        </dl>

        <section class="tf-surface tf-section" aria-labelledby="tf-pertemuan">
            <header class="tf-band"><h2 id="tf-pertemuan">Pertemuan mingguan</h2><span>{{ $pertemuan->total() }} pertemuan</span></header>
            @if ($bolehCatat)
                @php
                    $hariIni = today()->toDateString();
                    $awal = $kelompok->tahunPelajaran->tanggal_mulai?->toDateString();
                    $akhir = min($hariIni, $kelompok->tahunPelajaran->tanggal_selesai?->toDateString() ?? $hariIni);
                    $tanggal = old('tanggal', $akhir === $hariIni && (! $awal || $awal <= $hariIni) ? $hariIni : '');
                @endphp
                <form method="POST" action="{{ route('tahfidz.pertemuan.store', $kelompok) }}" class="tf-meeting-form">
                    @csrf
                    <div class="field"><label for="tf-tanggal">Tanggal pertemuan</label>
                        <input id="tf-tanggal" type="date" name="tanggal" class="input" value="{{ is_string($tanggal) ? $tanggal : '' }}" @if($awal) min="{{ $awal }}" @endif max="{{ $akhir }}" required>
                    </div>
                    <button type="submit" class="button button-primary"><i data-lucide="calendar-plus" aria-hidden="true"></i> Buka pertemuan</button>
                </form>
            @endif
            <ul class="tf-list">
                @forelse ($pertemuan as $sesi)
                    <li class="tf-student-row">
                        <div><p class="person-name">{{ $sesi->tanggal->locale('id')->translatedFormat('l, j F Y') }}</p><p class="person-meta">{{ $sesi->setoran_count }} setoran &middot; {{ $sesi->selesai_pada ? 'Selesai' : 'Terbuka' }}</p></div>
                        <a href="{{ route('tahfidz.pertemuan.show', [$kelompok, $sesi]) }}" class="button button-muted">Lihat pertemuan <i data-lucide="chevron-right" aria-hidden="true"></i></a>
                    </li>
                @empty
                    <li class="empty-state">Belum ada pertemuan.</li>
                @endforelse
            </ul>
        </section>
        @if ($pertemuan->hasPages())<div class="tf-pagination">{{ $pertemuan->links() }}</div>@endif

        <section class="tf-surface tf-section" aria-labelledby="tf-anggota">
            <header class="tf-band"><h2 id="tf-anggota">Anggota kelompok</h2><span>{{ $anggota->count() }} siswa</span></header>
            <ul class="tf-list">
                @forelse ($anggota as $siswa)
                    <li class="tf-student-row">
                        <div><p class="person-name">{{ $siswa->nama_lengkap }}</p><p class="person-meta">{{ $siswa->anggotaKelas->first()?->kelas?->nama ?? 'Belum ada kelas' }}</p></div>
                        @if ($anggotaIds->has($siswa->id))
                            <a href="{{ route('tahfidz.anggota.riwayat', [$kelompok, $anggotaIds->get($siswa->id)]) }}" class="button button-muted" aria-label="Riwayat {{ $siswa->nama_lengkap }}"><i data-lucide="history" aria-hidden="true"></i> Riwayat</a>
                        @endif
                    </li>
                @empty
                    <li class="empty-state">Belum ada anggota pada kelompok ini.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
