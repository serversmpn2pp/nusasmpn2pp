@extends('layouts.app')

@section('title', 'Detail Mata Pelajaran - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $romawi = [7 => 'VII', 8 => 'VIII', 9 => 'IX'];
        $tahunDipilih = $tahunPelajaran->firstWhere('id', $tahunPelajaranId);
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Detail mata pelajaran</h1>
        </div>

        <div class="actions">
            <a href="{{ route('mata-pelajaran.index', ['tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-muted">Kembali</a>
            @izin('mata_pelajaran.kelola')
                <a href="{{ route('mata-pelajaran.edit', [$mataPelajaran, 'tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">MP</div>
                <h2>{{ $mataPelajaran->nama }}</h2>
                <p>{{ $mataPelajaran->kelompok ?: 'Kelompok belum diisi' }}</p>

                <div style="margin-top: 16px;">
                    <span class="badge {{ $mataPelajaran->aktif ? 'badge-active' : 'badge-inactive' }}">
                        {{ $mataPelajaran->aktif ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
            </div>

            @izin('mata_pelajaran.kelola')
                @if ($mataPelajaran->aktif)
                    <form action="{{ route('mata-pelajaran.destroy', $mataPelajaran) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan mata pelajaran ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <div class="page-header" style="margin-bottom: 18px;">
                    <div>
                        <h2 class="panel-title">Pengaturan per Tingkat</h2>
                        <p class="help-text">{{ $tahunDipilih?->nama ?? 'Tahun pelajaran belum tersedia' }}</p>
                    </div>
                    <form action="{{ route('mata-pelajaran.show', $mataPelajaran) }}" method="GET">
                        <select name="tahun_pelajaran_id" class="select" onchange="this.form.submit()" aria-label="Pilih tahun pelajaran">
                            @foreach ($tahunPelajaran as $item)
                                <option value="{{ $item->id }}" @selected((int) $tahunPelajaranId === (int) $item->id)>
                                    {{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <div class="detail-grid">
                    @forelse ($mataPelajaran->pengaturanTingkat as $pengaturan)
                        <div class="detail-item">
                            <dt>Kelas {{ $romawi[$pengaturan->tingkat] ?? $pengaturan->tingkat }}</dt>
                            <dd>
                                {{ $pengaturan->kode }} ·
                                {{ $mataPelajaran->menggunakanPredikat() ? 'Predikat SB/B/C/K' : 'KKM/KKTP '.($pengaturan->kkm ?? '-') }}
                            </dd>
                            <span class="badge {{ $pengaturan->aktif ? 'badge-active' : 'badge-inactive' }}" style="margin-top: 8px;">
                                {{ $pengaturan->aktif ? 'Digunakan' : 'Tidak digunakan' }}
                            </span>
                        </div>
                    @empty
                        <div class="detail-item span-2">
                            <dt>Pengaturan tingkat</dt>
                            <dd>Belum ada pengaturan untuk tahun pelajaran ini.</dd>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Mata Pelajaran</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Kelompok</dt>
                        <dd>{{ $teks($mataPelajaran->kelompok) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jenis penilaian</dt>
                        <dd>{{ $mataPelajaran->labelJenisPenilaian() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Urutan tampil</dt>
                        <dd>{{ $mataPelajaran->urutan }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $teks($mataPelajaran->keterangan) }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
@endsection
