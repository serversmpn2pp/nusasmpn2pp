@extends('layouts.app')

@section('title', 'Detail Tahun Pelajaran - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $tanggal = fn (mixed $value) => $value ? $value->format('d-m-Y') : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Detail tahun pelajaran</h1>
        </div>

        <div class="actions">
            <a href="{{ route('kelas.create', ['tahun_pelajaran_id' => $tahunPelajaran->id]) }}" class="button button-primary">Tambah kelas</a>
            <a href="{{ route('tahun-pelajaran.index') }}" class="button button-muted">Kembali</a>
            <a href="{{ route('tahun-pelajaran.edit', $tahunPelajaran) }}" class="button button-dark">Edit</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">TP</div>
                <h2>{{ $tahunPelajaran->nama }}</h2>
                <p>{{ $tahunPelajaran->kelas_count }} kelas terdata</p>

                <div style="margin-top: 16px;">
                    @if ($tahunPelajaran->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @if ($tahunPelajaran->aktif)
                <form action="{{ route('tahun-pelajaran.destroy', $tahunPelajaran) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan tahun pelajaran ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                </form>
            @endif
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Tahun Pelajaran</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Tanggal mulai</dt>
                        <dd>{{ $tanggal($tahunPelajaran->tanggal_mulai) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tanggal selesai</dt>
                        <dd>{{ $tanggal($tahunPelajaran->tanggal_selesai) }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $teks($tahunPelajaran->keterangan) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel">
                <div class="desktop-only table-wrap">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>Kelas</th>
                                <th>Wali kelas</th>
                                <th>Anggota</th>
                                <th>Status</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($kelas as $item)
                                <tr>
                                    <td>
                                        <p class="person-name">{{ $item->nama }}</p>
                                        <p class="person-meta">Tingkat {{ $item->tingkat ?: '-' }}</p>
                                    </td>
                                    <td>{{ $item->waliKelas?->nama_lengkap ?: '-' }}</td>
                                    <td>{{ $item->anggota_kelas_count }} siswa</td>
                                    <td>
                                        @if ($item->aktif)
                                            <span class="badge badge-active">Aktif</span>
                                        @else
                                            <span class="badge badge-inactive">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="actions" style="justify-content: flex-end;">
                                            <a href="{{ route('kelas.show', $item) }}" class="button button-muted">Lihat</a>
                                            <a href="{{ route('kelas.edit', $item) }}" class="button button-dark">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="empty-state">Belum ada kelas untuk tahun pelajaran ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mobile-only mobile-list">
                    @forelse ($kelas as $item)
                        <article class="mobile-card">
                            <div class="mobile-card-head">
                                <div>
                                    <p class="person-name">{{ $item->nama }}</p>
                                    <p class="person-meta">{{ $item->waliKelas?->nama_lengkap ?: 'Wali kelas belum dipilih' }}</p>
                                </div>

                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </div>

                            <dl class="quick-facts">
                                <div>
                                    <dt>Tingkat</dt>
                                    <dd>{{ $item->tingkat ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt>Anggota</dt>
                                    <dd>{{ $item->anggota_kelas_count }} siswa</dd>
                                </div>
                            </dl>

                            <div class="actions" style="margin-top: 14px;">
                                <a href="{{ route('kelas.show', $item) }}" class="button button-muted">Lihat</a>
                                <a href="{{ route('kelas.edit', $item) }}" class="button button-dark">Edit</a>
                            </div>
                        </article>
                    @empty
                        <div class="empty-state">Belum ada kelas untuk tahun pelajaran ini.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
