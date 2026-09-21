@extends('layouts.app')

@section('title', 'Tugas Pengawas Ujian Susulan - NUSA')

@push('styles')
    <style>
        .retake-supervisor-page { display:grid; gap:18px; }
        .retake-supervisor-hero { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(250px,.55fr); overflow:hidden; padding:0; background:var(--primary); color:#fff; }
        .retake-supervisor-main,.retake-supervisor-token { padding:22px 24px; }
        .retake-supervisor-main h2 { margin:5px 0 0; color:#fff; font-size:1.35rem; }
        .retake-supervisor-main p { margin:8px 0 0; color:rgba(255,255,255,.84); }
        .retake-supervisor-token { display:grid; align-content:center; border-left:1px solid rgba(255,255,255,.2); background:rgba(255,255,255,.08); }
        .retake-supervisor-token span,.retake-supervisor-token strong { display:block; }
        .retake-supervisor-token span { color:rgba(255,255,255,.72); font-size:.72rem; font-weight:800; text-transform:uppercase; }
        .retake-supervisor-token strong { margin:5px 0 10px; font-size:2rem; font-variant-numeric:tabular-nums; letter-spacing:0; }
        .retake-supervisor-token .button { justify-self:start; }
        .retake-info { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); border:1px solid var(--line); background:#fff; }
        .retake-info > div { min-width:0; padding:15px 17px; border-right:1px solid var(--line); }
        .retake-info > div:last-child { border-right:0; }
        .retake-info span,.retake-info strong { display:block; }
        .retake-info span { color:var(--muted); font-size:.72rem; }
        .retake-info strong { margin-top:4px; color:var(--primary-dark); font-size:.9rem; overflow-wrap:anywhere; }
        .retake-steps { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .retake-step { display:grid; grid-template-columns:34px minmax(0,1fr); gap:10px; align-items:center; padding:12px; border:1px solid var(--line); border-left:4px solid var(--primary); background:#fff; }
        .retake-step > span { display:grid; width:34px; height:34px; place-items:center; border-radius:50%; background:var(--primary-soft); color:var(--primary-dark); font-weight:900; }
        .retake-step strong,.retake-step small { display:block; }
        .retake-step small { margin-top:2px; color:var(--muted); }
        .retake-monitor,.retake-monitor-data { display:grid; gap:14px; }
        .retake-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
        .retake-metric { padding:13px 15px; border:1px solid var(--line); background:#fff; }
        .retake-metric span,.retake-metric strong { display:block; }
        .retake-metric span { color:var(--muted); font-size:.72rem; }
        .retake-metric strong { margin-top:4px; color:var(--primary-dark); font-size:1.45rem; }
        .retake-tools { display:flex; align-items:end; flex-wrap:wrap; gap:12px; padding:14px; border:1px solid var(--line); background:#f8fafc; }
        .retake-tools .field { flex:1 1 220px; min-width:0; }
        .retake-tools > label { padding-bottom:10px; white-space:nowrap; }
        .retake-live { margin:0; color:var(--muted); font-size:.78rem; }
        .retake-table { overflow-x:auto; border:1px solid var(--line); background:#fff; }
        .retake-table table { width:100%; min-width:780px; border-collapse:collapse; }
        .retake-table th,.retake-table td { padding:13px 14px; text-align:left; border-bottom:1px solid var(--line); vertical-align:middle; }
        .retake-table th { background:var(--primary-soft); color:var(--primary-dark); font-size:.76rem; }
        .retake-table tr:last-child td { border-bottom:0; }
        .retake-table strong,.retake-table small { display:block; }
        .retake-table small { margin-top:3px; color:var(--muted); line-height:1.4; }
        .retake-cancelled { padding:13px 15px; border:1px solid #f1c7c7; background:#fff7f7; color:#7f1d1d; }
        @media(max-width:820px) { .retake-supervisor-hero { grid-template-columns:1fr; } .retake-supervisor-token { border-top:1px solid rgba(255,255,255,.2); border-left:0; } .retake-info,.retake-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } .retake-info > div:nth-child(2) { border-right:0; } .retake-info > div:nth-child(-n+2) { border-bottom:1px solid var(--line); } }
        @media(max-width:560px) { .retake-info,.retake-metrics,.retake-steps { grid-template-columns:1fr; } .retake-info > div { border-right:0; border-bottom:1px solid var(--line); } .retake-info > div:last-child { border-bottom:0; } .retake-tools { align-items:stretch; flex-direction:column; } .retake-tools .field,.retake-tools .button { width:100%; } }
    </style>
@endpush

@section('content')
    @php
        $mulai = $pesertaPertama->susulan_mulai;
        $selesai = $pesertaPertama->susulan_selesai;
        $belumMulai = $pesertaPantau->reject(fn ($peserta) => in_array($peserta->status, ['sedang_mengerjakan', 'selesai'], true))->count();
        $sedang = $pesertaPantau->where('status', 'sedang_mengerjakan')->count();
        $sudahSelesai = $pesertaPantau->where('status', 'selesai')->count();
    @endphp

    <div class="retake-supervisor-page">
        <div class="page-header" style="margin-bottom:0;">
            <div><p class="eyebrow">Ujian & Asesmen</p><h1 class="page-title">Tugas Pengawas Susulan</h1><p class="page-subtitle">Informasi penting dan pemantauan peserta dalam satu halaman.</p></div>
            <a href="{{ route('tugas-pengawas-ujian.index') }}" class="button button-muted">Kembali ke tugas</a>
        </div>

        @if (session('berhasil')) <div class="alert">{{ session('berhasil') }}</div> @endif

        <section class="panel retake-supervisor-hero">
            <div class="retake-supervisor-main">
                <span class="badge {{ $kelas_status }}">{{ $label_status }}</span>
                <h2>{{ $ujian?->mataPelajaran?->nama ?: 'Mata pelajaran belum ditentukan' }} · Tingkat {{ $ujian?->tingkat ?: '-' }}</h2>
                <p>{{ $jadwal?->kegiatanUjianCbt?->nama ?: $ujian?->nama }} · Ujian susulan menggunakan paket dan komponen nilai ujian utama.</p>
            </div>
            <div class="retake-supervisor-token">
                <span>Token ujian susulan</span>
                <strong id="retake-token">{{ $pesertaPertama->token_susulan ?: 'Tidak tersedia' }}</strong>
                @if($pesertaPertama->token_susulan)<button type="button" class="button button-muted" id="retake-copy">Salin token</button><span id="retake-copy-status" role="status"></span>@endif
            </div>
        </section>

        <section class="retake-info" aria-label="Informasi pelaksanaan">
            <div><span>Tanggal</span><strong>{{ $mulai?->locale('id')->translatedFormat('l, d F Y') ?: '-' }}</strong></div>
            <div><span>Waktu</span><strong>{{ $mulai?->format('H:i') ?: '-' }}-{{ $selesai?->format('H:i') ?: '-' }} WIB</strong></div>
            <div><span>Ruang</span><strong>{{ $pesertaPertama->ruang_susulan ?: 'Belum ditentukan' }}</strong></div>
            <div><span>Pengawas</span><strong>{{ $pengawas?->nama_lengkap ?: 'Belum ditentukan' }}</strong></div>
        </section>

        <section class="retake-steps" aria-label="Alur pengawasan">
            <div class="retake-step"><span>1</span><div><strong>Siapkan ruang dan bagikan token</strong><small>Pastikan hanya peserta pada daftar ini yang menerima token.</small></div></div>
            <div class="retake-step"><span>2</span><div><strong>Pantau sampai selesai</strong><small>Status dan jumlah jawaban diperbarui otomatis setiap 15 detik.</small></div></div>
        </section>

        <section class="retake-monitor" id="retake-monitor">
            <div class="retake-tools">
                <div class="field"><label for="retake-search">Cari siswa</label><input id="retake-search" class="input" type="search" placeholder="Nama atau NISN" autocomplete="off"></div>
                <div class="field"><label for="retake-status">Status pengerjaan</label><select id="retake-status" class="select"><option value="">Semua siswa</option><option value="belum">Belum mulai</option><option value="sedang_mengerjakan">Mengerjakan</option><option value="selesai">Selesai</option></select></div>
                <label><input type="checkbox" id="retake-auto" checked> Perbarui otomatis</label>
                <button type="button" class="button button-muted" id="retake-refresh">Perbarui sekarang</button>
            </div>
            <p class="retake-live" id="retake-live" role="status">Terakhir diperbarui {{ now()->format('H:i:s') }}</p>

            <div class="retake-monitor-data" id="retake-monitor-data">
                <div class="retake-metrics">
                    <div class="retake-metric"><span>Total peserta</span><strong>{{ $pesertaPantau->count() }}</strong></div>
                    <div class="retake-metric"><span>Belum mulai</span><strong>{{ $belumMulai }}</strong></div>
                    <div class="retake-metric"><span>Mengerjakan</span><strong>{{ $sedang }}</strong></div>
                    <div class="retake-metric"><span>Selesai</span><strong>{{ $sudahSelesai }}</strong></div>
                </div>

                <div class="retake-table">
                    <table>
                        <thead><tr><th>Peserta</th><th>Ketidakhadiran awal</th><th>Status susulan</th><th>Jawaban tersimpan</th><th>Waktu</th></tr></thead>
                        <tbody>
                            @foreach($pesertaPantau as $peserta)
                                @php
                                    $siswa = $peserta->anggotaKelas?->siswa;
                                    $statusPantau = $peserta->status === 'selesai' ? 'selesai' : ($peserta->status === 'sedang_mengerjakan' ? 'sedang_mengerjakan' : 'belum');
                                    $labelPantau = match($statusPantau) { 'selesai' => 'Selesai', 'sedang_mengerjakan' => 'Mengerjakan', default => ($mulai && now()->lt($mulai) ? 'Menunggu jadwal' : 'Belum mulai') };
                                @endphp
                                <tr data-retake-student data-search="{{ str($siswa?->nama_lengkap.' '.$siswa?->nisn)->lower() }}" data-status="{{ $statusPantau }}">
                                    <td><strong>{{ $siswa?->nama_lengkap ?: '-' }}</strong><small>{{ $peserta->kelasUjianCbt?->kelas?->nama }} · NISN {{ $siswa?->nisn ?: '-' }}</small></td>
                                    <td><span class="badge {{ $peserta->status_kehadiran_ujian === 'alfa' ? 'badge-danger' : 'badge-warning' }}">{{ $peserta->labelStatusKehadiranUjian() }}</span><small>Riwayat ujian utama</small></td>
                                    <td><span class="badge {{ $statusPantau === 'selesai' ? 'badge-active' : ($statusPantau === 'sedang_mengerjakan' ? 'badge-warning' : 'badge-muted') }}">{{ $labelPantau }}</span></td>
                                    <td><strong>{{ $peserta->jawaban_tersimpan }} / {{ $jumlahSoalPantau }}</strong></td>
                                    <td><strong>{{ $peserta->waktu_mulai?->format('H:i:s') ?: '-' }}</strong><small>Selesai {{ $peserta->waktu_selesai?->format('H:i:s') ?: '-' }}</small></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="empty-state" id="retake-empty" hidden>Tidak ada siswa yang sesuai dengan pencarian.</p>
                </div>
            </div>
        </section>

        @if($pesertaDibatalkan->isNotEmpty())
            <div class="retake-cancelled"><strong>{{ $pesertaDibatalkan->count() }} peserta dikeluarkan dari jadwal ini oleh panitia.</strong></div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            document.getElementById('retake-copy')?.addEventListener('click', async () => {
                const token = document.getElementById('retake-token').textContent.trim();
                try {
                    await navigator.clipboard.writeText(token);
                    document.getElementById('retake-copy-status').textContent = 'Token tersalin';
                } catch {
                    window.prompt('Salin token ujian:', token);
                }
            });

            const monitorData = document.getElementById('retake-monitor-data');
            const search = document.getElementById('retake-search');
            const status = document.getElementById('retake-status');
            const live = document.getElementById('retake-live');
            const refresh = document.getElementById('retake-refresh');
            if (!monitorData || !search || !status || !live || !refresh) return;

            const filter = () => {
                let terlihat = 0;
                monitorData.querySelectorAll('[data-retake-student]').forEach((row) => {
                    row.hidden = !row.dataset.search.includes(search.value.trim().toLowerCase()) || (status.value && row.dataset.status !== status.value);
                    if (!row.hidden) terlihat++;
                });
                monitorData.querySelector('#retake-empty').hidden = terlihat > 0;
            };
            search.addEventListener('input', filter);
            status.addEventListener('change', filter);
            filter();

            let pending = false;
            const update = async () => {
                if (pending) return;
                pending = true;
                refresh.disabled = true;
                const controller = new AbortController();
                const timer = setTimeout(() => controller.abort(), 10000);
                live.textContent = 'Memperbarui data...';
                try {
                    const response = await fetch(window.location.href, { cache: 'no-store', signal: controller.signal });
                    if (!response.ok) throw new Error();
                    const html = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const next = html.getElementById('retake-monitor-data');
                    if (!next) throw new Error();
                    monitorData.innerHTML = next.innerHTML;
                    live.textContent = html.getElementById('retake-live').textContent;
                    filter();
                } catch {
                    live.textContent = 'Data belum diperbarui. Periksa koneksi; data terakhir tetap ditampilkan.';
                } finally {
                    clearTimeout(timer);
                    pending = false;
                    refresh.disabled = false;
                }
            };
            refresh.addEventListener('click', update);
            setInterval(() => {
                if (!document.hidden && document.getElementById('retake-auto')?.checked) update();
            }, 15000);
        })();
    </script>
@endpush
