@extends('layouts.app')

@section('title', 'Siswa Wali Saya - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Guru Wali</p>
            <h1 class="page-title">Siswa Wali Saya</h1>
            <p class="page-subtitle">Siswa lintas kelas yang menjadi tanggung jawab pendampingan Anda.</p>
        </div>
    </div>

    <form method="GET" class="panel panel-pad" style="margin-bottom: 20px;">
        <div class="form-grid">
            <div class="field span-2">
                <label for="kata_kunci">Cari siswa</label>
                <input id="kata_kunci" name="kata_kunci" value="{{ $kataKunci }}" class="input" placeholder="Nama atau NISN">
            </div>
        </div>
        <div class="actions" style="justify-content: flex-end; margin-top: 12px;">
            <a href="{{ route('siswa-wali-saya.index') }}" class="button button-muted">Reset</a>
            <button class="button button-dark" type="submit">Terapkan</button>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead><tr><th>Siswa</th><th>Kelas Saat Ini</th><th>Total Poin</th><th>Mulai Didampingi</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($penugasan as $item)
                        @php $anggota = $item->siswa?->anggotaKelas?->first(); @endphp
                        <tr>
                            <td><p class="person-name">{{ $item->siswa?->nama_lengkap }}</p><p class="person-meta">NISN {{ $item->siswa?->nisn ?: '-' }}</p></td>
                            <td>{{ $anggota?->kelas?->nama ?: '-' }}</td>
                            <td><strong>{{ max(0, (int) ($totalPoin[$item->siswa_id] ?? 0)) }}</strong></td>
                            <td>{{ $item->tanggal_mulai?->format('d/m/Y') }}</td>
                            <td><a href="{{ route('laporan-pembinaan-siswa.index', ['kata_kunci' => $item->siswa?->nisn ?: $item->siswa?->nama_lengkap]) }}" class="button button-muted button-sm">Riwayat</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Belum ada siswa yang ditugaskan kepada Anda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mobile-only mobile-list">
            @forelse ($penugasan as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head"><div><p class="person-name">{{ $item->siswa?->nama_lengkap }}</p><p class="person-meta">{{ $item->siswa?->anggotaKelas?->first()?->kelas?->nama ?: '-' }}</p></div><span class="badge badge-warning">{{ max(0, (int) ($totalPoin[$item->siswa_id] ?? 0)) }} poin</span></div>
                    <a href="{{ route('laporan-pembinaan-siswa.index', ['kata_kunci' => $item->siswa?->nisn ?: $item->siswa?->nama_lengkap]) }}" class="button button-muted button-sm" style="margin-top: 12px;">Riwayat</a>
                </article>
            @empty
                <div class="empty-state">Belum ada siswa yang ditugaskan kepada Anda.</div>
            @endforelse
        </div>
    </section>

    @if ($penugasan->hasPages()){{ $penugasan->links() }}@endif
@endsection
