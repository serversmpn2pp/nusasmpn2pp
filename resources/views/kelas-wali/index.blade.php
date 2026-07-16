@extends('layouts.app')

@section('title', 'Kelas Wali - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $tanggal = fn (mixed $value) => $value ? $value->format('d-m-Y') : '-';
        $tempatTanggalLahir = function ($siswa) use ($teks, $tanggal) {
            $tempat = $teks($siswa?->tempat_lahir);
            $lahir = $tanggal($siswa?->tanggal_lahir);

            if ($tempat === '-' && $lahir === '-') {
                return '-';
            }

            return trim($tempat . ', ' . $lahir, ' ,-');
        };
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Wali Kelas</p>
            <h1 class="page-title">Kelas Wali</h1>
        </div>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Kelas diwali</p>
            <p class="stat-value">{{ $ringkasan['kelas'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Siswa aktif</p>
            <p class="stat-value">{{ $ringkasan['siswa'] }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">L / P</p>
            <p class="stat-value">{{ $ringkasan['laki_laki'] }} / {{ $ringkasan['perempuan'] }}</p>
        </div>
    </div>

    @forelse ($kelasWali as $kelas)
        <section class="panel panel-pad" style="margin-bottom: 24px;">
            <div class="page-header" style="align-items: center; margin-bottom: 18px;">
                <div>
                    <p class="eyebrow">{{ $kelas->tahunPelajaran?->nama ?: 'Tahun pelajaran belum tersedia' }}</p>
                    <h2 class="page-title" style="font-size: 1.35rem;">{{ $kelas->nama }}</h2>
                    <p class="help-text" style="margin-top: 6px;">
                        Wali kelas: {{ $kelas->waliKelas?->nama_lengkap ?: '-' }}.
                        {{ $kelas->jumlah_siswa }} siswa aktif.
                    </p>
                </div>

                <div class="actions">
                    <a href="{{ route('rekap-absensi-harian.index', ['tahun_pelajaran_id' => $kelas->tahun_pelajaran_id, 'kelas_id' => $kelas->id]) }}" class="button button-muted">Rekap absensi</a>
                    <a href="{{ route('laporan-absensi.index', ['tahun_pelajaran_id' => $kelas->tahun_pelajaran_id, 'kelas_id' => $kelas->id]) }}" class="button button-muted">Laporan absensi</a>
                    <a href="{{ route('laporan-pembinaan-siswa.index', ['tahun_pelajaran_id' => $kelas->tahun_pelajaran_id, 'kelas_id' => $kelas->id]) }}" class="button button-primary">Pembinaan</a>
                </div>
            </div>

            <div class="desktop-only table-wrap">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Siswa</th>
                            <th>NIS / NISN</th>
                            <th>JK</th>
                            <th>Tempat, tanggal lahir</th>
                            <th>Orang tua</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($kelas->anggotaKelas as $anggota)
                            @php($siswa = $anggota->siswa)
                            <tr>
                                <td>{{ $anggota->nomor_absen ?: '-' }}</td>
                                <td>
                                    <div class="person">
                                        <div class="avatar avatar-sm">
                                            @if ($siswa?->foto)
                                                <img src="{{ asset('storage/' . $siswa->foto) }}" alt="Foto {{ $siswa->nama_lengkap }}">
                                            @else
                                                {{ strtoupper(mb_substr($siswa?->nama_lengkap ?: '-', 0, 1)) }}
                                            @endif
                                        </div>
                                        <div>
                                            <p class="person-name">{{ $siswa?->nama_lengkap ?: '-' }}</p>
                                            <p class="person-meta">{{ $siswa?->aktif ? 'Aktif' : 'Nonaktif' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $teks($siswa?->nis) }}</div>
                                    <div class="muted">{{ $teks($siswa?->nisn) }}</div>
                                </td>
                                <td>{{ $siswa?->jenis_kelamin === 'L' ? 'L' : ($siswa?->jenis_kelamin === 'P' ? 'P' : '-') }}</td>
                                <td>{{ $tempatTanggalLahir($siswa) }}</td>
                                <td>
                                    <div>{{ $teks($siswa?->nama_ayah) }}</div>
                                    <div class="muted">{{ $teks($siswa?->nama_ibu) }}</div>
                                </td>
                                <td>
                                    <div class="actions" style="justify-content: flex-end;">
                                        @if ($siswa)
                                            <a href="{{ route('siswa.show', $siswa) }}" class="button button-muted">Lihat</a>
                                        @else
                                            <span class="button button-muted" aria-disabled="true">Lihat</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="empty-state">Belum ada siswa aktif di kelas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mobile-only mobile-list">
                @forelse ($kelas->anggotaKelas as $anggota)
                    @php($siswa = $anggota->siswa)
                    <article class="mobile-card">
                        <div class="mobile-card-main">
                            <div class="avatar avatar-md">
                                @if ($siswa?->foto)
                                    <img src="{{ asset('storage/' . $siswa->foto) }}" alt="Foto {{ $siswa->nama_lengkap }}">
                                @else
                                    {{ strtoupper(mb_substr($siswa?->nama_lengkap ?: '-', 0, 1)) }}
                                @endif
                            </div>
                            <div style="min-width: 0;">
                                <p class="person-name">{{ $anggota->nomor_absen ? $anggota->nomor_absen . '. ' : '' }}{{ $siswa?->nama_lengkap ?: '-' }}</p>
                                <p class="person-meta">NISN {{ $teks($siswa?->nisn) }}</p>
                            </div>
                        </div>

                        <dl class="quick-facts">
                            <div>
                                <dt>NIS</dt>
                                <dd>{{ $teks($siswa?->nis) }}</dd>
                            </div>
                            <div>
                                <dt>JK</dt>
                                <dd>{{ $siswa?->jenis_kelamin === 'L' ? 'Laki-laki' : ($siswa?->jenis_kelamin === 'P' ? 'Perempuan' : '-') }}</dd>
                            </div>
                            <div>
                                <dt>Lahir</dt>
                                <dd>{{ $tempatTanggalLahir($siswa) }}</dd>
                            </div>
                            <div>
                                <dt>Orang tua</dt>
                                <dd>{{ $teks($siswa?->nama_ayah ?: $siswa?->nama_ibu) }}</dd>
                            </div>
                        </dl>

                        <div class="actions" style="margin-top: 14px;">
                            @if ($siswa)
                                <a href="{{ route('siswa.show', $siswa) }}" class="button button-muted">Lihat</a>
                            @else
                                <span class="button button-muted" aria-disabled="true">Lihat</span>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="empty-state">Belum ada siswa aktif di kelas ini.</div>
                @endforelse
            </div>
        </section>
    @empty
        <section class="panel">
            <div class="empty-state">Belum ada kelas yang terhubung dengan akun wali kelas ini.</div>
        </section>
    @endforelse
@endsection
