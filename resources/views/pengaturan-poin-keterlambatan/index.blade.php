@extends('layouts.app')

@section('title', 'Poin Keterlambatan - NUSA')

@section('content')
    <style>
        .late-rule-list { display: flex; flex-wrap: wrap; gap: 7px; }
        .late-rule-chip { background: #eef5fb; border: 1px solid #c9d9e8; border-radius: 6px; color: #15477a; display: inline-flex; font-size: .78rem; font-weight: 800; padding: 6px 9px; }
        .late-rule-chip.zero { background: #f8fafc; border-color: var(--line); color: #64748b; }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Poin keterlambatan</h1>
            <p class="page-subtitle">Aturan otomatis berdasarkan tahun pelajaran.</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width: 880px;">
                <thead>
                    <tr>
                        <th>Tahun pelajaran</th>
                        <th>Status</th>
                        <th>Rentang dan poin</th>
                        <th>Diperbarui</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarTahunPelajaran as $tahun)
                        @php $pengaturan = $tahun->pengaturanPoinKeterlambatan; @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $tahun->nama }}</p>
                                <p class="person-meta">{{ $tahun->aktif ? 'Tahun pelajaran aktif' : 'Arsip' }}</p>
                            </td>
                            <td>
                                <span class="badge {{ $pengaturan?->aktif ? 'badge-active' : 'badge-muted' }}">
                                    {{ $pengaturan?->aktif ? 'Otomatis aktif' : 'Belum aktif' }}
                                </span>
                            </td>
                            <td>
                                @if ($pengaturan?->rentangPoinKeterlambatan?->isNotEmpty())
                                    <div class="late-rule-list">
                                        @foreach ($pengaturan->rentangPoinKeterlambatan as $rentang)
                                            <span class="late-rule-chip {{ $rentang->poin === 0 ? 'zero' : '' }}">{{ $rentang->labelRentang() }}: {{ $rentang->poin }} poin</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="person-meta">Belum disimpan</span>
                                @endif
                            </td>
                            <td>
                                <p class="person-name">{{ $pengaturan?->updated_at?->format('d/m/Y H:i') ?? '-' }}</p>
                                <p class="person-meta">{{ $pengaturan?->diperbaruiOlehPengguna?->nama ?? '-' }}</p>
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('pengaturan-poin-keterlambatan.edit', $tahun) }}" class="button button-dark button-sm">Atur</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Belum ada tahun pelajaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($daftarTahunPelajaran as $tahun)
                @php $pengaturan = $tahun->pengaturanPoinKeterlambatan; @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $tahun->nama }}</p>
                            <p class="person-meta">{{ $tahun->aktif ? 'Tahun aktif' : 'Arsip' }}</p>
                        </div>
                        <span class="badge {{ $pengaturan?->aktif ? 'badge-active' : 'badge-muted' }}">{{ $pengaturan?->aktif ? 'Aktif' : 'Belum aktif' }}</span>
                    </div>

                    @if ($pengaturan?->rentangPoinKeterlambatan?->isNotEmpty())
                        <div class="late-rule-list" style="margin-top: 13px;">
                            @foreach ($pengaturan->rentangPoinKeterlambatan as $rentang)
                                <span class="late-rule-chip {{ $rentang->poin === 0 ? 'zero' : '' }}">{{ $rentang->labelRentang() }}: {{ $rentang->poin }} poin</span>
                            @endforeach
                        </div>
                    @else
                        <p class="help-text" style="margin-top: 12px;">Belum ada rentang yang disimpan.</p>
                    @endif

                    <a href="{{ route('pengaturan-poin-keterlambatan.edit', $tahun) }}" class="button button-dark button-full" style="margin-top: 14px;">Atur poin</a>
                </article>
            @empty
                <div class="empty-state">Belum ada tahun pelajaran.</div>
            @endforelse
        </div>
    </section>

    <div style="margin-top: 18px;">{{ $daftarTahunPelajaran->links() }}</div>
@endsection
