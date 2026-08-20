@extends('layouts.app')

@section('title', 'Rekap Berhalangan - NUSA')

@section('content')
    @php
        $parameterCetak = array_filter([
            'bulan' => $bulan,
            'kelas_id' => $kelasId,
            'status' => $status !== 'semua' ? $status : null,
            'cari' => $cari ?: null,
        ], fn ($nilai) => filled($nilai));
        $labelStatus = fn ($item) => match ($item) {
            'aktif' => 'Sedang dipantau',
            'perlu_konfirmasi' => 'Perlu konfirmasi',
            'selesai' => 'Selesai',
            default => str($item)->headline(),
        };
        $kelasStatus = fn ($item) => match ($item) {
            'aktif' => 'badge-active',
            'perlu_konfirmasi' => 'badge-warning',
            default => 'badge-muted',
        };
        $caraSelesai = fn ($item) => match ($item) {
            'scan_ibadah' => 'Scan ibadah biasa',
            'konfirmasi_privat' => 'Konfirmasi privat',
            default => $item ? str($item)->headline() : '-',
        };
    @endphp

    <style>
        .private-recap-filter { display:grid; grid-template-columns:minmax(170px,.7fr) minmax(170px,.8fr) minmax(190px,.8fr) minmax(230px,1.2fr) auto; gap:12px; align-items:end; }
        .private-recap-stats { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; margin:18px 0; }
        .private-recap-stat { min-height:92px; padding:15px; border:1px solid var(--line); border-radius:7px; background:#fff; }
        .private-recap-stat strong { display:block; margin-top:5px; color:var(--primary-dark); font-size:1.65rem; line-height:1; }
        .private-recap-stat.attention { border-color:#e7b800; background:#fff9db; }
        .private-recap-person { display:flex; align-items:center; gap:10px; min-width:230px; }
        .private-recap-person img { width:40px; height:50px; object-fit:cover; border:1px solid var(--line); border-radius:6px; background:#eef2f6; }
        .private-recap-note { padding:12px 14px; border-left:4px solid var(--warning); border-radius:6px; background:#fff9df; color:#614b00; line-height:1.5; }
        @media (max-width:1080px) { .private-recap-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } .private-recap-filter .actions { grid-column:1/-1; } .private-recap-stats { grid-template-columns:repeat(3,minmax(0,1fr)); } }
        @media (max-width:680px) { .private-recap-filter,.private-recap-stats { grid-template-columns:1fr; } .private-recap-filter .actions { grid-column:auto; } .private-recap-filter .button { width:100%; } }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Pendamping Ibadah Siswi</p>
            <h1 class="page-title">Rekap Berhalangan</h1>
            <p class="page-subtitle">Ringkasan privat periode berhalangan pada tahun pelajaran {{ $tahunPelajaran->nama }}.</p>
        </div>
        <div class="actions">
            <a href="{{ route('konfirmasi-berhalangan-ibadah.index') }}" class="button button-muted">Konfirmasi privat</a>
            <a href="{{ route('rekap-berhalangan-ibadah.cetak', $parameterCetak) }}" class="button button-primary" target="_blank" rel="noopener">Cetak / Simpan PDF</a>
        </div>
    </div>

    <div class="private-recap-note">
        <strong>Dokumen internal dan privat.</strong> Rekap tidak menampilkan isi catatan percakapan dan tidak digabungkan ke laporan ibadah umum.
    </div>

    <form method="GET" action="{{ route('rekap-berhalangan-ibadah.index') }}" class="panel panel-pad" style="margin-top:18px;" data-auto-filter>
        <div class="private-recap-filter">
            <div class="field">
                <label for="bulan">Bulan</label>
                <input id="bulan" name="bulan" type="month" value="{{ $bulan }}" min="{{ $bulanMinimum }}" max="{{ $bulanMaksimum }}" class="input @error('bulan') is-invalid @enderror" data-auto-submit>
            </div>
            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select" data-auto-submit>
                    <option value="">Semua kelas dalam cakupan</option>
                    @foreach ($daftarKelas as $kelas)<option value="{{ $kelas->id }}" @selected($kelasId === $kelas->id)>{{ $kelas->nama }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select" data-auto-submit>
                    @foreach ($daftarStatus as $nilai => $label)<option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="cari">Cari siswi</label>
                <input id="cari" name="cari" class="input" value="{{ $cari }}" placeholder="Nama atau NISN" autocomplete="off" data-auto-search>
            </div>
            <div class="actions"><a href="{{ route('rekap-berhalangan-ibadah.index') }}" class="button button-muted">Reset</a></div>
        </div>
    </form>

    <div class="private-recap-stats">
        <div class="private-recap-stat"><span>Periode tercatat</span><strong>{{ $ringkasan['periode'] }}</strong></div>
        <div class="private-recap-stat"><span>Siswi terpantau</span><strong>{{ $ringkasan['siswi'] }}</strong></div>
        <div class="private-recap-stat"><span>Sedang dipantau</span><strong>{{ $ringkasan['aktif'] }}</strong></div>
        <div class="private-recap-stat attention"><span>Perlu konfirmasi</span><strong>{{ $ringkasan['perlu_konfirmasi'] }}</strong></div>
        <div class="private-recap-stat"><span>Selesai</span><strong>{{ $ringkasan['selesai'] }}</strong></div>
    </div>

    <section class="panel">
        <div class="panel-pad" style="border-bottom:1px solid var(--line);">
            <h2 class="panel-title">{{ $bulanLabel }}{{ $kelasDipilih ? ' · '.$kelasDipilih->nama : '' }}</h2>
            <p class="help-text">Jumlah presensi dan konfirmasi pada tabel dihitung khusus dalam bulan yang dipilih.</p>
        </div>

        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead><tr><th>Siswi</th><th>Periode</th><th>Durasi</th><th>Scan bulan ini</th><th>Konfirmasi terakhir</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($daftarPeriode as $periode)
                        @php
                            $akhirDurasi = $periode->tanggal_selesai ?: now();
                            $durasi = $periode->tanggal_mulai->copy()->startOfDay()->diffInDays($akhirDurasi->copy()->startOfDay()) + 1;
                        @endphp
                        <tr>
                            <td><div class="private-recap-person"><img src="{{ $periode->siswa?->foto ? asset('storage/'.$periode->siswa->foto) : asset('images/kartu-pelajar/default-user.png') }}" alt=""><div><p class="person-name">{{ $periode->siswa?->nama_lengkap ?? '-' }}</p><p class="person-meta">{{ $periode->siswa?->nisn ?? '-' }} · {{ $periode->kelas?->nama ?? '-' }}</p></div></div></td>
                            <td><strong>{{ $periode->tanggal_mulai->format('d/m/Y') }}</strong><p class="person-meta">s.d. {{ $periode->tanggal_selesai?->format('d/m/Y') ?? 'sekarang' }}</p></td>
                            <td>{{ $durasi }} hari</td>
                            <td><strong>{{ $periode->presensi_bulan_count }} hari</strong><p class="person-meta">{{ $periode->konfirmasi_bulan_count }} konfirmasi</p></td>
                            <td>@if($periode->konfirmasiTerakhir)<strong>{{ $periode->konfirmasiTerakhir->dikonfirmasi_pada->format('d/m/Y') }}</strong><p class="person-meta">{{ $hasilKonfirmasi[$periode->konfirmasiTerakhir->hasil] ?? '-' }}</p>@else<span class="person-meta">Belum ada</span>@endif</td>
                            <td><span class="badge {{ $kelasStatus($periode->status) }}">{{ $labelStatus($periode->status) }}</span>@if($periode->status === 'selesai')<p class="person-meta">{{ $caraSelesai($periode->cara_selesai) }}</p>@endif</td>
                            <td class="text-right"><a href="{{ route('konfirmasi-berhalangan-ibadah.show', $periode) }}" class="button button-muted button-sm">Lihat riwayat</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">Belum ada periode berhalangan pada pilihan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($daftarPeriode as $periode)
                @php
                    $akhirDurasi = $periode->tanggal_selesai ?: now();
                    $durasi = $periode->tanggal_mulai->copy()->startOfDay()->diffInDays($akhirDurasi->copy()->startOfDay()) + 1;
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div><p class="person-name">{{ $periode->siswa?->nama_lengkap ?? '-' }}</p><p class="person-meta">{{ $periode->kelas?->nama ?? '-' }} · {{ $periode->siswa?->nisn ?? '-' }}</p></div>
                        <span class="badge {{ $kelasStatus($periode->status) }}">{{ $labelStatus($periode->status) }}</span>
                    </div>
                    <div class="detail-grid" style="margin-top:13px;">
                        <div><span>Mulai</span><strong>{{ $periode->tanggal_mulai->format('d/m/Y') }}</strong></div>
                        <div><span>Durasi</span><strong>{{ $durasi }} hari</strong></div>
                        <div><span>Scan bulan ini</span><strong>{{ $periode->presensi_bulan_count }} hari</strong></div>
                        <div><span>Konfirmasi</span><strong>{{ $periode->konfirmasi_bulan_count }} kali</strong></div>
                    </div>
                    <a href="{{ route('konfirmasi-berhalangan-ibadah.show', $periode) }}" class="button button-muted button-full" style="margin-top:14px;">Lihat riwayat</a>
                </article>
            @empty
                <div class="empty-state">Belum ada periode berhalangan pada pilihan ini.</div>
            @endforelse
        </div>

        @if ($daftarPeriode->hasPages())<div class="panel-pad" style="border-top:1px solid var(--line);">{{ $daftarPeriode->links() }}</div>@endif
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-auto-filter]');
            if (!form) return;
            form.querySelectorAll('[data-auto-submit]').forEach((item) => item.addEventListener('change', () => form.requestSubmit()));
            const cari = form.querySelector('[data-auto-search]');
            let timer;
            cari?.addEventListener('input', () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(() => form.requestSubmit(), 450);
            });
        })();
    </script>
@endpush
