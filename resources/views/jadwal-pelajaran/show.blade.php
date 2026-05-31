@extends('layouts.app')

@section('title', 'Detail Jadwal Pelajaran - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $guruMapel = $jadwalPelajaran->guruMataPelajaran;
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Detail jadwal pelajaran</h1>
        </div>

        <div class="actions">
            <a href="{{ route('jadwal-pelajaran.index') }}" class="button button-muted">Kembali</a>
            @izin('jadwal.kelola')
                <a href="{{ route('jadwal-pelajaran.edit', $jadwalPelajaran) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">JP</div>
                <h2>{{ $guruMapel?->mataPelajaran?->nama ?? 'Jadwal pelajaran' }}</h2>
                <p>{{ $jadwalPelajaran->kelas?->nama ?? '-' }}</p>

                <div style="margin-top: 16px;">
                    @if ($jadwalPelajaran->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @izin('jadwal.kelola')
                @if ($jadwalPelajaran->aktif)
                    <form action="{{ route('jadwal-pelajaran.destroy', $jadwalPelajaran) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan jadwal pelajaran ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Jadwal</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Tahun pelajaran</dt>
                        <dd>{{ $jadwalPelajaran->tahunPelajaran?->nama ?? '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kelas</dt>
                        <dd>{{ $jadwalPelajaran->kelas?->nama ?? '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Hari</dt>
                        <dd>{{ $jadwalPelajaran->labelHari() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jam</dt>
                        <dd>{{ $jadwalPelajaran->jamPelajaran?->labelJam() ?? '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Mata pelajaran</dt>
                        <dd>{{ $guruMapel?->mataPelajaran?->nama ?? '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Guru</dt>
                        <dd>{{ $guruMapel?->pegawai?->nama_lengkap ?? '-' }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $teks($jadwalPelajaran->keterangan) }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
@endsection
