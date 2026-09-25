<style>
    .supervisor-tabs { display:flex; gap:0; border-bottom:2px solid var(--line); margin:18px 0; }
    .supervisor-tabs a { flex:1; display:flex; gap:10px; align-items:center; padding:14px 12px; color:var(--muted); text-decoration:none; border-bottom:3px solid transparent; font-weight:800; }
    .supervisor-tabs a[aria-current=page] { color:var(--primary); border-color:var(--primary); background:var(--primary-soft); }
    .supervisor-tabs span { display:grid; place-items:center; width:30px; height:30px; flex:0 0 30px; border:1px solid currentColor; border-radius:50%; line-height:1; }
    .supervisor-preparation { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:24px; padding:8px 0 24px; }
    .supervisor-preparation h2 { margin:0 0 14px; font-size:1.1rem; }
    .supervisor-token { display:block; font-size:2.4rem; font-variant-numeric:tabular-nums; color:var(--primary); margin:12px 0; overflow-wrap:anywhere; }
    .supervisor-metrics { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; margin:18px 0; }
    .supervisor-metrics div { padding:12px; border-left:3px solid var(--primary); background:var(--primary-soft); }
    .supervisor-metrics span,.supervisor-metrics strong { display:block; }
    .supervisor-metrics strong { font-size:1.5rem; margin-top:5px; }
    .supervisor-tools { display:flex; align-items:end; flex-wrap:wrap; gap:12px; margin:18px 0; }
    .supervisor-tools .field { flex:1 1 200px; min-width:0; }
    .supervisor-participants { overflow-x:auto; border:1px solid var(--line); border-radius:8px; }
    .supervisor-participants table { width:100%; border-collapse:collapse; }
    .supervisor-participants th,.supervisor-participants td { padding:14px 16px; text-align:left; border-bottom:1px solid var(--line); }
    .supervisor-participants th { background:var(--primary-soft); }
    .supervisor-participants small { display:block; color:var(--muted); margin-top:4px; }
    .supervisor-participants tr:last-child td { border-bottom:0; }
    .supervisor-live { color:var(--muted); margin:0; }
    @media(max-width:900px) { .supervisor-metrics { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    @media(max-width:680px) { .supervisor-preparation { grid-template-columns:1fr; } .supervisor-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } .supervisor-tabs a { flex-direction:column; text-align:center; padding:12px 6px; font-size:.85rem; } .supervisor-participants th,.supervisor-participants td { padding:12px 8px; } }
    @media(max-width:560px) {
        .supervisor-participants { overflow:visible; border:0; background:transparent; }
        .supervisor-participants table,.supervisor-participants tbody { display:block; width:100%; }
        .supervisor-participants thead { display:none; }
        .supervisor-participants tbody { display:grid; gap:10px; }
        .supervisor-participants tr[data-supervisor-student] { display:block; overflow:hidden; border:1px solid var(--line); border-radius:8px; background:#fff; }
        .supervisor-participants tr[data-supervisor-student][hidden] { display:none !important; }
        .supervisor-participants td { display:grid; grid-template-columns:88px minmax(0,1fr); gap:10px; align-items:start; padding:10px 12px; border-bottom:1px solid var(--line); }
        .supervisor-participants td:last-child { border-bottom:0; }
        .supervisor-participants td::before { content:attr(data-label); color:var(--muted); font-size:.7rem; font-weight:800; text-transform:uppercase; }
        .supervisor-cell { min-width:0; overflow-wrap:anywhere; }
    }
</style>
<nav class="supervisor-tabs" aria-label="Tahap tugas pengawas">
    @foreach(['persiapan' => 'Persiapan', 'pantau' => 'Pantau siswa', 'bukti' => 'Bukti ujian'] as $key => $label)
        <a href="{{ route('tugas-pengawas-ujian.show', [$ruang, 'tahap' => $key] + request()->only('kembali')) }}" @if($tahap === $key) aria-current="page" @endif><span>{{ $loop->iteration }}</span>{{ $label }}</a>
    @endforeach
</nav>
@if($tahap === 'persiapan')
    <section class="supervisor-preparation">
        <div>
            <h2>Presensi ruang</h2>
            <p>{{ $pesertaPantau->count() }} peserta terdaftar · {{ $ruang->lokasi ?: 'Lokasi belum diisi' }}</p>
            @if($ruang->dapatMencatatPresensiOleh(auth()->user()))
                <a class="button button-primary" href="{{ route('presensi-ujian-cbt.show', [$ruang->ujianCbt, $ruang]) }}">Buka presensi ruang</a>
            @else
                <p class="alert">Akses pencatatan presensi belum diberikan. Hubungi panitia.</p>
            @endif
        </div>
        <div>
            <h2>Token ujian</h2>
            <span class="badge badge-muted">{{ $ruang->ujianCbt?->labelStatus() }}</span>
            <strong class="supervisor-token" id="supervisor-token">{{ $ruang->ujianCbt?->token ?: 'Belum tersedia' }}</strong>
            @if($ruang->ujianCbt?->token)
                <button type="button" class="button button-muted" id="supervisor-copy">Salin token</button>
                <span role="status" id="supervisor-copy-status"></span>
            @endif
        </div>
    </section>
    <div class="actions"><a class="button button-primary" href="{{ route('tugas-pengawas-ujian.show', [$ruang, 'tahap' => 'pantau']) }}">Lanjut ke pantau siswa</a></div>
@elseif($tahap === 'pantau')
    <div class="supervisor-tools">
        <div class="field"><label for="supervisor-search">Cari siswa</label><input class="input" id="supervisor-search" type="search" placeholder="Nama atau NISN" autocomplete="off"></div>
        <div class="field"><label for="supervisor-status">Status pengerjaan</label><select class="select" id="supervisor-status"><option value="">Semua siswa</option><option value="belum">Belum mulai</option><option value="sedang_mengerjakan">Sedang mengerjakan</option><option value="terblokir">Ditahan Mode Aman</option><option value="selesai">Selesai</option></select></div>
        <label><input type="checkbox" id="supervisor-auto" checked> Perbarui otomatis</label>
        <button type="button" class="button button-muted" id="supervisor-refresh">Perbarui sekarang</button>
    </div>
    <p class="supervisor-live" id="supervisor-live" role="status">Terakhir diperbarui {{ now()->format('H:i:s') }}</p>
    <div id="supervisor-monitor">
        <div class="supervisor-metrics">
            <div><span>Total peserta</span><strong>{{ $pesertaPantau->count() }}</strong></div>
            <div><span>Belum mulai</span><strong>{{ $pesertaPantau->whereNotIn('status', ['sedang_mengerjakan', 'terblokir', 'selesai'])->count() }}</strong></div>
            <div><span>Ditahan Mode Aman</span><strong>{{ $pesertaPantau->where('status', 'terblokir')->count() }}</strong></div>
            <div><span>Mengerjakan</span><strong>{{ $pesertaPantau->where('status', 'sedang_mengerjakan')->count() }}</strong></div>
            <div><span>Selesai</span><strong>{{ $pesertaPantau->where('status', 'selesai')->count() }}</strong></div>
        </div>
        <div class="supervisor-participants">
            <table>
                <thead><tr><th>Meja</th><th>Siswa</th><th>Status</th><th>Soal dengan jawaban tersimpan</th><th>Tindakan</th></tr></thead>
                <tbody>
                    @foreach($pesertaPantau as $peserta)
                        @php($siswa = $peserta->anggotaKelas?->siswa)
                        <tr data-supervisor-student data-search="{{ str($siswa?->nama_lengkap.' '.$siswa?->nisn)->lower() }}" data-status="{{ in_array($peserta->status, ['sedang_mengerjakan', 'terblokir', 'selesai']) ? $peserta->status : 'belum' }}">
                            <td data-label="Meja"><div class="supervisor-cell">{{ $peserta->nomor_meja ?: '-' }}</div></td>
                            <td data-label="Siswa"><div class="supervisor-cell"><strong>{{ $siswa?->nama_lengkap ?: '-' }}</strong><small>{{ $peserta->kelasUjianCbt?->kelas?->nama }} · {{ $siswa?->nisn }}</small></div></td>
                            <td data-label="Status"><div class="supervisor-cell"><span class="badge {{ $peserta->status === 'terblokir' ? 'badge-danger' : ($peserta->status === 'selesai' ? 'badge-active' : 'badge-muted') }}">{{ $peserta->status === 'terblokir' ? 'Ditahan Mode Aman' : $peserta->labelStatusPelaksanaan() }}</span><small>{{ \App\Models\PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN[$peserta->status_kehadiran_ujian ?: 'belum_absen'] ?? '-' }}</small>@if($peserta->jumlah_aktivitas_keamanan > 0)<small>{{ $peserta->jumlah_pindah_aplikasi }} kejadian dihitung · {{ $peserta->durasi_di_luar_aplikasi_detik }} detik</small>@endif</div></td>
                            <td data-label="Jawaban"><div class="supervisor-cell">{{ $peserta->jawaban_tersimpan }} / {{ $jumlahSoalPantau }}<small>Mulai {{ $peserta->waktu_mulai?->format('H:i') ?: '-' }} · Selesai {{ $peserta->waktu_selesai?->format('H:i') ?: '-' }}</small></div></td>
                            <td data-label="Tindakan"><div class="supervisor-cell">@if($peserta->jumlah_aktivitas_keamanan > 0 || $peserta->status === 'terblokir')<a class="button {{ in_array($peserta->id, $pesertaDapatDibuka, true) ? 'button-primary' : 'button-muted' }} button-sm" href="{{ route('tugas-pengawas-ujian.mode-aman.riwayat', [$ruang, $peserta]) }}">{{ in_array($peserta->id, $pesertaDapatDibuka, true) ? 'Tinjau & buka' : 'Riwayat' }}</a>@else<span class="muted">-</span>@endif</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="empty-state" id="supervisor-empty" hidden>Tidak ada siswa yang sesuai.</p>
        </div>
    </div>
@endif
@push('scripts')
<script>
(() => {
    document.getElementById('supervisor-copy')?.addEventListener('click', async () => {
        const token = document.getElementById('supervisor-token').textContent.trim();
        try { await navigator.clipboard.writeText(token); document.getElementById('supervisor-copy-status').textContent = 'Token tersalin'; }
        catch { window.prompt('Salin token ujian:', token); }
    });
    const monitor = document.getElementById('supervisor-monitor');
    if (!monitor) return;
    const search = document.getElementById('supervisor-search');
    const status = document.getElementById('supervisor-status');
    const live = document.getElementById('supervisor-live');
    const refresh = document.getElementById('supervisor-refresh');
    const filter = () => {
        let count = 0;
        monitor.querySelectorAll('[data-supervisor-student]').forEach(row => {
            row.hidden = !row.dataset.search.includes(search.value.trim().toLowerCase()) || (status.value !== '' && row.dataset.status !== status.value);
            if (!row.hidden) count++;
        });
        document.getElementById('supervisor-empty').hidden = count > 0;
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
            const response = await fetch(window.location.href, { cache:'no-store', signal:controller.signal });
            if (!response.ok) throw new Error();
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = html.getElementById('supervisor-monitor');
            if (!next) throw new Error();
            const scroll = monitor.querySelector('.supervisor-participants').scrollLeft;
            monitor.innerHTML = next.innerHTML;
            filter();
            monitor.querySelector('.supervisor-participants').scrollLeft = scroll;
            live.textContent = html.getElementById('supervisor-live').textContent;
        } catch {
            live.textContent = 'Data belum diperbarui. Periksa koneksi; data terakhir tetap ditampilkan.';
        } finally {
            clearTimeout(timer); pending = false; refresh.disabled = false;
        }
    };
    refresh.addEventListener('click', update);
    setInterval(() => { if (!document.hidden && document.getElementById('supervisor-auto').checked) update(); }, 15000);
})();
</script>
@endpush
