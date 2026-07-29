@extends('layouts.app')

@section('title', 'Detail Guru Mata Pelajaran - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $pengaturanMapel = $guruMataPelajaran->mataPelajaran?->pengaturanUntuk(
            (int) $guruMataPelajaran->tahun_pelajaran_id,
            (int) $guruMataPelajaran->kelas?->tingkat,
        );
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Detail guru mata pelajaran</h1>
        </div>

        <div class="actions">
            <a href="{{ route('guru-mata-pelajaran.index') }}" class="button button-muted">Kembali</a>
            @izin('guru_mapel.kelola')
                @if ($guruMataPelajaran->aktif && $guruMataPelajaran->jenis_penugasan === 'pengampu')
                    <a href="{{ route('guru-mata-pelajaran.ganti-guru', $guruMataPelajaran) }}" class="button button-primary">Ganti Guru</a>
                @endif
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
                        <dd>{{ $teks($pengaturanMapel?->kode) }}</dd>
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

            <section class="panel">
                <div class="panel-pad" style="border-bottom: 1px solid var(--line);">
                    <h2 class="panel-title">Riwayat Pergantian Guru</h2>
                    <p class="help-text" style="margin-top: 6px;">Pergantian dicatat tanpa memutus jadwal dan nilai pada penugasan ini.</p>
                </div>

                <div class="desktop-only table-wrap">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>Tanggal efektif</th>
                                <th>Guru lama</th>
                                <th>Guru baru</th>
                                <th>Alasan</th>
                                <th>Dicatat oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($guruMataPelajaran->riwayatPergantian as $riwayat)
                                <tr>
                                    <td>{{ $riwayat->tanggal_efektif?->translatedFormat('d F Y') ?: '-' }}</td>
                                    <td>{{ $riwayat->pegawaiLama?->nama_lengkap ?: '-' }}</td>
                                    <td>{{ $riwayat->pegawaiBaru?->nama_lengkap ?: '-' }}</td>
                                    <td style="white-space: pre-line;">{{ $riwayat->alasan }}</td>
                                    <td>{{ $riwayat->digantiOleh?->nama ?: 'Sistem' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="empty-state">Belum ada pergantian guru pada penugasan ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mobile-only mobile-list">
                    @forelse ($guruMataPelajaran->riwayatPergantian as $riwayat)
                        <article class="mobile-card">
                            <div class="mobile-card-head">
                                <div>
                                    <p class="person-name">{{ $riwayat->pegawaiLama?->nama_lengkap ?: '-' }} ke {{ $riwayat->pegawaiBaru?->nama_lengkap ?: '-' }}</p>
                                    <p class="person-meta">{{ $riwayat->tanggal_efektif?->translatedFormat('d F Y') ?: '-' }}</p>
                                </div>
                            </div>
                            <p style="margin-top: 12px; white-space: pre-line;">{{ $riwayat->alasan }}</p>
                            <p class="person-meta" style="margin-top: 8px;">Dicatat oleh {{ $riwayat->digantiOleh?->nama ?: 'Sistem' }}</p>
                        </article>
                    @empty
                        <div class="empty-state">Belum ada pergantian guru pada penugasan ini.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
