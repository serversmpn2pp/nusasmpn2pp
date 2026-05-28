@extends('layouts.app')

@section('title', 'Detail Skema Bobot Nilai - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian</p>
            <h1 class="page-title">Detail skema bobot nilai</h1>
        </div>

        <div class="actions">
            <a href="{{ route('skema-bobot-nilai.index') }}" class="button button-muted">Kembali</a>
            @izin('nilai.skema_kelola')
                <a href="{{ route('skema-bobot-nilai.edit', $skemaBobotNilai) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">BN</div>
                <h2>{{ $skemaBobotNilai->tahunPelajaran?->nama ?: '-' }}</h2>
                <p>{{ ucfirst($skemaBobotNilai->semester) }} - {{ $skemaBobotNilai->labelTingkat() }}</p>

                <div style="margin-top: 16px;">
                    @if ($skemaBobotNilai->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @izin('nilai.skema_kelola')
                @if ($skemaBobotNilai->aktif)
                    <form action="{{ route('skema-bobot-nilai.destroy', $skemaBobotNilai) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan skema bobot nilai ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Ruang Lingkup</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Tahun pelajaran</dt>
                        <dd>{{ $skemaBobotNilai->tahunPelajaran?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Semester</dt>
                        <dd>{{ ucfirst($skemaBobotNilai->semester) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tingkat</dt>
                        <dd>{{ $skemaBobotNilai->labelTingkat() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Label akhir</dt>
                        <dd>{{ $skemaBobotNilai->labelNilaiAkhir() }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $teks($skemaBobotNilai->keterangan) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Bobot Nilai Rapor</h2>
                <div class="stats-grid" style="margin: 16px 0 0;">
                    <div class="panel stat">
                        <p class="stat-label">Formatif</p>
                        <p class="stat-value">{{ $skemaBobotNilai->bobot_formatif }}%</p>
                    </div>
                    <div class="panel stat">
                        <p class="stat-label">Sumatif</p>
                        <p class="stat-value">{{ $skemaBobotNilai->bobot_sumatif }}%</p>
                    </div>
                    <div class="panel stat">
                        <p class="stat-label">STS</p>
                        <p class="stat-value">{{ $skemaBobotNilai->bobot_sts }}%</p>
                    </div>
                    <div class="panel stat active">
                        <p class="stat-label">{{ $skemaBobotNilai->labelNilaiAkhir() }}</p>
                        <p class="stat-value">{{ $skemaBobotNilai->bobot_sas_saj }}%</p>
                    </div>
                </div>

                <div class="alert" style="margin: 16px 0 0;">Total bobot: {{ $skemaBobotNilai->totalBobot() }}%</div>
            </section>
        </div>
    </div>
@endsection
