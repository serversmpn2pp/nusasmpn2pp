@extends('layouts.app')

@section('title', 'Detail Mata Pelajaran - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Detail mata pelajaran</h1>
        </div>

        <div class="actions">
            <a href="{{ route('mata-pelajaran.index') }}" class="button button-muted">Kembali</a>
            <a href="{{ route('mata-pelajaran.edit', $mataPelajaran) }}" class="button button-dark">Edit</a>
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
                <p>{{ $mataPelajaran->kode ?: 'Kode belum diisi' }}</p>

                <div style="margin-top: 16px;">
                    @if ($mataPelajaran->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @if ($mataPelajaran->aktif)
                <form action="{{ route('mata-pelajaran.destroy', $mataPelajaran) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan mata pelajaran ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                </form>
            @endif
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Mata Pelajaran</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Kode</dt>
                        <dd>{{ $teks($mataPelajaran->kode) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kelompok</dt>
                        <dd>{{ $teks($mataPelajaran->kelompok) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tingkat khusus</dt>
                        <dd>{{ $mataPelajaran->tingkat ? 'Kelas ' . $mataPelajaran->tingkat : 'Semua tingkat' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>KKM/KKTP</dt>
                        <dd>{{ $mataPelajaran->kkm ?? '-' }}</dd>
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

            <section class="panel panel-pad">
                <h2 class="panel-title">Catatan pengembangan</h2>
                <p class="help-text" style="margin-top: 8px;">Nanti mata pelajaran ini dapat dihubungkan dengan guru pengampu, kelas, tahun pelajaran, komponen nilai, dan rekap nilai.</p>
            </section>
        </div>
    </div>
@endsection
