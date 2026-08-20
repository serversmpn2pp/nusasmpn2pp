@extends('layouts.app')

@section('title', 'Konfirmasi Privat - NUSA')

@section('content')
    <style>
        .private-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin:18px 0; }
        .private-stat { padding:16px; border:1px solid var(--line); border-radius:7px; background:#fff; }
        .private-stat.attention { border-color:#e7b800; background:#fff9db; }
        .private-stat strong { display:block; margin-top:5px; color:var(--primary-dark); font-size:1.7rem; line-height:1; }
        .private-filter { display:grid; grid-template-columns:minmax(190px,.42fr) minmax(260px,1fr) auto; gap:12px; align-items:end; }
        .student-line { display:flex; align-items:center; gap:11px; min-width:0; }
        .student-line img { width:42px; height:52px; object-fit:cover; border:1px solid var(--line); border-radius:6px; background:#eef2f6; flex:0 0 auto; }
        .private-warning { padding:13px 14px; border-left:4px solid var(--warning); border-radius:6px; background:#fff9df; color:#614b00; line-height:1.5; }
        .due-days { color:#9a3412; font-weight:900; }
        @media (max-width:760px) {
            .private-summary { grid-template-columns:1fr; }
            .private-filter { grid-template-columns:1fr; }
            .private-filter .button { width:100%; }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Pendamping Ibadah Siswi</p>
            <h1 class="page-title">Konfirmasi Privat</h1>
            <p class="page-subtitle">Tindak lanjuti catatan yang melewati batas melalui percakapan pribadi dan tanpa pemeriksaan fisik.</p>
        </div>
        <a href="{{ route('scan-berhalangan-ibadah.index') }}" class="button button-muted" target="_blank" rel="noopener">Buka scanner</a>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="private-warning">
        <strong>Jaga kerahasiaan siswi.</strong> Lakukan percakapan secara tenang, jangan meminta bukti fisik, dan tulis catatan seperlunya tanpa rincian medis.
    </div>

    <div class="private-summary">
        <div class="private-stat attention"><span>Perlu dikonfirmasi</span><strong>{{ $jumlahPerluKonfirmasi }}</strong></div>
        <div class="private-stat"><span>Sedang dipantau</span><strong>{{ $jumlahDipantau }}</strong></div>
        <div class="private-stat"><span>Selesai bulan ini</span><strong>{{ $jumlahSelesaiBulanIni }}</strong></div>
    </div>

    <section class="panel panel-pad">
        <form method="GET" action="{{ route('konfirmasi-berhalangan-ibadah.index') }}" class="private-filter" data-auto-filter>
            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select" data-auto-submit>
                    <option value="">Semua kelas dalam cakupan</option>
                    @foreach ($daftarKelas as $kelas)
                        <option value="{{ $kelas->id }}" @selected($kelasId === $kelas->id)>{{ $kelas->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="cari">Cari siswi</label>
                <input id="cari" name="cari" class="input" value="{{ request('cari') }}" placeholder="Nama atau NISN" autocomplete="off" data-auto-search>
            </div>
            <a href="{{ route('konfirmasi-berhalangan-ibadah.index') }}" class="button button-muted">Reset</a>
        </form>
    </section>

    <section class="panel" style="margin-top:18px;">
        <div class="panel-pad" style="border-bottom:1px solid var(--line);">
            <h2 class="panel-title">Memerlukan konfirmasi</h2>
            <p class="help-text">Urutan dimulai dari catatan yang paling lama menunggu.</p>
        </div>

        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead><tr><th>Siswi</th><th>Periode</th><th>Presensi tercatat</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($daftarPeriode as $periode)
                        @php($hariKe = $periode->tanggal_mulai->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1)
                        <tr>
                            <td>
                                <div class="student-line">
                                    <img src="{{ $periode->siswa?->foto ? asset('storage/'.$periode->siswa->foto) : asset('images/kartu-pelajar/default-user.png') }}" alt="">
                                    <div><p class="person-name">{{ $periode->siswa?->nama_lengkap ?? '-' }}</p><p class="person-meta">{{ $periode->siswa?->nisn ?? '-' }} · {{ $periode->kelas?->nama ?? '-' }}</p></div>
                                </div>
                            </td>
                            <td><strong>{{ $periode->tanggal_mulai->format('d/m/Y') }}</strong><p class="person-meta due-days">Hari ke-{{ $hariKe }}</p></td>
                            <td>{{ $periode->presensi_harian_count }} hari</td>
                            <td><span class="badge badge-warning">Perlu konfirmasi</span><p class="person-meta">Sejak {{ $periode->perlu_konfirmasi_sejak?->format('d/m/Y') }}</p></td>
                            <td class="text-right"><a href="{{ route('konfirmasi-berhalangan-ibadah.show', $periode) }}" class="button button-primary button-sm">Konfirmasi</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Tidak ada siswi yang memerlukan konfirmasi saat ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($daftarPeriode as $periode)
                @php($hariKe = $periode->tanggal_mulai->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1)
                <article class="mobile-card">
                    <div class="student-line">
                        <img src="{{ $periode->siswa?->foto ? asset('storage/'.$periode->siswa->foto) : asset('images/kartu-pelajar/default-user.png') }}" alt="">
                        <div><p class="person-name">{{ $periode->siswa?->nama_lengkap ?? '-' }}</p><p class="person-meta">{{ $periode->kelas?->nama ?? '-' }} · {{ $periode->siswa?->nisn ?? '-' }}</p></div>
                    </div>
                    <div class="detail-grid" style="margin-top:13px;">
                        <div><span>Mulai</span><strong>{{ $periode->tanggal_mulai->format('d/m/Y') }}</strong></div>
                        <div><span>Durasi</span><strong class="due-days">Hari ke-{{ $hariKe }}</strong></div>
                        <div><span>Presensi</span><strong>{{ $periode->presensi_harian_count }} hari</strong></div>
                        <div><span>Menunggu sejak</span><strong>{{ $periode->perlu_konfirmasi_sejak?->format('d/m/Y') }}</strong></div>
                    </div>
                    <a href="{{ route('konfirmasi-berhalangan-ibadah.show', $periode) }}" class="button button-primary button-full" style="margin-top:14px;">Konfirmasi secara privat</a>
                </article>
            @empty
                <div class="empty-state">Tidak ada siswi yang memerlukan konfirmasi saat ini.</div>
            @endforelse
        </div>

        @if ($daftarPeriode->hasPages())
            <div class="panel-pad" style="border-top:1px solid var(--line);">{{ $daftarPeriode->links() }}</div>
        @endif
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-auto-filter]');
            if (!form) return;

            form.querySelectorAll('[data-auto-submit]').forEach((input) => input.addEventListener('change', () => form.requestSubmit()));
            const pencarian = form.querySelector('[data-auto-search]');
            let timer;
            pencarian?.addEventListener('input', () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(() => form.requestSubmit(), 450);
            });
        })();
    </script>
@endpush
