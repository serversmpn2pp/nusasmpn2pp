@extends('layouts.app')

@section('title', 'Detail Jam Pelajaran - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Detail jam pelajaran</h1>
        </div>

        <div class="actions">
            <a href="{{ route('jam-pelajaran.index') }}" class="button button-muted">Kembali</a>
            @izin('jadwal.kelola')
                <a href="{{ route('jam-pelajaran.edit', $jamPelajaran) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">JM</div>
                <h2>{{ $jamPelajaran->label ?: 'Jam ' . $jamPelajaran->nomor_jam }}</h2>
                <p>{{ $jamPelajaran->labelHari() }}</p>

                <div style="margin-top: 16px;">
                    @if ($jamPelajaran->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @izin('jadwal.kelola')
                @if ($jamPelajaran->aktif)
                    <form action="{{ route('jam-pelajaran.destroy', $jamPelajaran) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan jam pelajaran ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Jam</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Hari</dt>
                        <dd>{{ $jamPelajaran->labelHari() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Nomor jam</dt>
                        <dd>{{ $jamPelajaran->nomor_jam }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Waktu</dt>
                        <dd>{{ $jamPelajaran->formatJam($jamPelajaran->jam_mulai) }} - {{ $jamPelajaran->formatJam($jamPelajaran->jam_selesai) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jenis</dt>
                        <dd>{{ $jamPelajaran->labelJenis() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Dipakai jadwal</dt>
                        <dd>{{ $jamPelajaran->jadwal_pelajaran_count ?? 0 }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $teks($jamPelajaran->keterangan) }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
@endsection
