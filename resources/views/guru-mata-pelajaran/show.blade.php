@extends('layouts.app')

@section('title', 'Detail Guru Mata Pelajaran - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Detail guru mata pelajaran</h1>
        </div>

        <div class="actions">
            <a href="{{ route('guru-mata-pelajaran.index') }}" class="button button-muted">Kembali</a>
            @izin('guru_mapel.kelola')
                <a href="{{ route('guru-mata-pelajaran.edit', $guruMataPelajaran) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">GM</div>
                <h2>{{ $guruMataPelajaran->pegawai?->nama_lengkap ?: '-' }}</h2>
                <p>{{ $guruMataPelajaran->mataPelajaran?->nama ?: '-' }}</p>

                <div style="margin-top: 16px;">
                    @if ($guruMataPelajaran->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @izin('guru_mapel.kelola')
                @if ($guruMataPelajaran->aktif)
                    <form action="{{ route('guru-mata-pelajaran.destroy', $guruMataPelajaran) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan penugasan ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Penugasan</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Tahun pelajaran</dt>
                        <dd>{{ $guruMataPelajaran->tahunPelajaran?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kelas</dt>
                        <dd>{{ $guruMataPelajaran->kelas?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Mata pelajaran</dt>
                        <dd>{{ $guruMataPelajaran->mataPelajaran?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kode mapel</dt>
                        <dd>{{ $teks($guruMataPelajaran->mataPelajaran?->kode) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Guru</dt>
                        <dd>{{ $guruMataPelajaran->pegawai?->nama_lengkap ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>NIP</dt>
                        <dd>{{ $teks($guruMataPelajaran->pegawai?->nip) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jenis penugasan</dt>
                        <dd>{{ ucfirst($guruMataPelajaran->jenis_penugasan) }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $teks($guruMataPelajaran->keterangan) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Catatan pengembangan</h2>
                <p class="help-text" style="margin-top: 8px;">Penugasan ini nanti menjadi pintu masuk guru saat memilih kelas dan mata pelajaran untuk input nilai.</p>
            </section>
        </div>
    </div>
@endsection
