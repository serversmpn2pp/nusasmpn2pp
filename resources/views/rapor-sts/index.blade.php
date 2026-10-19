@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Rapor STS - NUSA')
@section('content')
    <style>
        .sts-section { padding: 22px 0; border-bottom: 1px solid var(--line); min-width: 0; }
        .sts-section h2 { margin: 0 0 16px; font-size: 1.05rem; }
        .sts-section .button:disabled { opacity: .5; cursor: not-allowed; }
        .sts-filters, .sts-dates { display: grid; grid-template-columns: minmax(220px, 2fr) minmax(150px, 1fr) auto; gap: 16px; align-items: end; }
        .sts-dates { grid-template-columns: repeat(3, minmax(150px, 1fr)); }
        .sts-summary { display: flex; flex-wrap: wrap; gap: 16px 32px; padding: 18px 0; border-bottom: 1px solid var(--line); }
        .sts-summary strong { font-size: 1.25rem; margin-right: 8px; }
        .sts-summary span { color: var(--muted); font-size: .9rem; }
        .sts-table-wrap { overflow-x: auto; max-width: 100%; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; }
        .sts-table { width: 100%; min-width: 900px; table-layout: fixed; border-collapse: collapse; font-size: .88rem; }
        .sts-table th, .sts-table td { padding: 14px; vertical-align: top; border-bottom: 1px solid #dbe2ea; text-align: left; }
        .sts-table th { background: #f0f4f8; color: #334155; font-size: .8rem; font-weight: 700; }
        .sts-table td + td, .sts-table th + th { border-left: 1px solid #edf0f4; }
        .sts-table tbody tr:last-child td { border-bottom: 0; }
        .sts-table tbody tr:hover { background: #fafcfe; }
        .sts-table .sts-number { width: 100%; height: 42px; min-height: 42px; padding: 6px 4px; text-align: center; font-size: .95rem; font-variant-numeric: tabular-nums; }
        .sts-table .sts-note { display: block; width: 100%; height: 68px; min-height: 68px; padding: 9px 10px; resize: vertical; font-size: .85rem; }
        .sts-original { display: block; color: #64748b; font-size: .75rem; line-height: 1.5; margin-top: 6px; }
        .sts-person { overflow-wrap: anywhere; }
        .sts-identity { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; line-height: 1.5; }
        .sts-order { display: grid; place-items: center; flex: 0 0 28px; height: 28px; border: 1px solid var(--line); border-radius: 4px; background: #f4f7fa; color: #64748b; font-size: .75rem; font-weight: 600; }
        .sts-identity strong { padding-top: 3px; font-size: .88rem; }
        .sts-status { display: block; margin: 8px 0; font-size: .8rem; line-height: 1.5; }
        .sts-badge { display: inline-flex; align-items: center; gap: 6px; width: fit-content; max-width: 100%; padding: 4px 8px; border-radius: 4px; background: #fff7e0; color: #805500; font-size: .75rem; font-weight: 600; line-height: 1.4; }
        .sts-badge.is-complete { background: #eaf6ef; color: #236443; }
        .sts-attendance { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
        .sts-attendance label { display: block; margin-bottom: 7px; font-size: .78rem; color: #475569; font-weight: 600; text-align: center; }
        .sts-attendance .sts-original { text-align: center; }
        .sts-lateness { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin: 12px 0 0; padding-top: 10px; border-top: 1px solid #dbe2ea; }
        .sts-lateness dt { color: #64748b; font-size: .75rem; line-height: 1.5; }
        .sts-lateness dd { margin: 3px 0 0; font-size: .85rem; font-weight: 600; font-variant-numeric: tabular-nums; }
        .sts-check { display: flex; gap: 8px; align-items: center; font-size: .85rem; cursor: pointer; line-height: 1.4; margin: 0; }
        .sts-check input { width: 18px; height: 18px; margin: 0; flex: 0 0 auto; accent-color: var(--primary); }
        .sts-review { padding: 10px; border: 1px solid #cbd5e1; border-radius: 5px; background: #fff; min-height: 42px; }
        .sts-review:has(input:checked) { border-color: #9fcab0; background: #edf8f1; color: #236443; }
        .sts-review-state { color: #64748b; }
        .sts-review-state.is-pending { color: #946200; }
        .sts-field-error { display: block; margin-top: 7px; color: #a12b23; font-size: .85rem; line-height: 1.5; }
        .sts-table [aria-invalid="true"] { border: 2px solid #b7382f; background: #fffafa; }
        .sts-row-has-error > td { background: #fffaf4; }
        .sts-feedback { margin-bottom: 18px; }
        .sts-feedback ul { margin: 8px 0 0; padding-left: 20px; }
        .sts-actions { display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: center; padding: 14px 0; }
        .sts-table .button { min-height: 38px; padding: 8px 10px; font-size: .8rem; border-radius: 5px; }
        .sts-detail-button { margin-top: 10px; }
        .sts-report-actions { display: flex; flex-direction: column; gap: 8px; }
        .sts-report-actions .button { width: 100%; min-width: 0; max-width: 100%; text-align: center; white-space: normal; overflow-wrap: anywhere; line-height: 1.4; }
        .sts-notice { padding: 12px 16px; border-left: 3px solid var(--primary); background: var(--primary-soft); font-size: .85rem; margin: 16px 0; }
        .sts-reset { display: block; background: none; border: 0; margin-top: 8px; padding: 4px 0; color: var(--primary); cursor: pointer; font: inherit; font-size: .78rem; font-weight: 600; text-align: left; text-decoration: underline; text-underline-offset: 3px; }
        .sts-reset:disabled { cursor: not-allowed; opacity: .5; }
        .sts-dialog { width: min(780px, calc(100vw - 32px)); max-width: none; max-height: calc(100dvh - 48px); padding: 0; border: 1px solid #cbd5e1; border-radius: 8px; color: var(--text); background: #fff; box-shadow: 0 24px 64px #0f172a33; }
        .sts-dialog[open] { display: flex; flex-direction: column; }
        .sts-dialog::backdrop { background: #172b46a6; }
        .sts-dialog-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; padding: 20px 24px; border-bottom: 1px solid var(--line); flex: 0 0 auto; }
        .sts-dialog-header h2 { font-size: 1.15rem; margin: 0 0 6px; }
        .sts-dialog-header p { margin: 0; color: #64748b; font-size: .85rem; overflow-wrap: anywhere; }
        .sts-dialog-header button { flex-shrink: 0; }
        .sts-dialog-body { overflow: auto; min-height: 0; padding: 20px 24px; }
        .sts-grade-summary { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; font-size: .85rem; color: #475569; }
        .sts-grade-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: .85rem; }
        .sts-grade-table th, .sts-grade-table td { text-align: left; padding: 12px; border-bottom: 1px solid var(--line); overflow-wrap: anywhere; }
        .sts-grade-table th { background: #f0f4f8; color: #475569; font-size: .78rem; }
        .sts-grade-table .sts-grade-score { width: 90px; text-align: right; font-variant-numeric: tabular-nums; }
        .sts-grade-table td:last-child { color: #64748b; font-size: .8rem; }
        .sts-grade-table tfoot th { background: #fff; font-size: .85rem; }
        .sts-dialog-footnote { margin: 16px 0 0; font-size: .8rem; color: #64748b; }
        .sts-exception { margin-top: 10px; }
        .sts-exception .sts-check { font-size: .8rem; font-weight: 600; }
        .sts-exception-reason { display: block; margin-top: 10px; font-size: .78rem; color: #475569; }
        .sts-exception-reason textarea { display: block; width: 100%; min-height: 76px; margin-top: 6px; padding: 8px; resize: vertical; font-size: .8rem; }
        .sts-exception-reason[hidden] { display: none; }
        .sts-exception-save { display: flex; justify-content: flex-end; gap: 12px; align-items: center; padding-top: 16px; margin-top: 16px; border-top: 1px solid var(--line); }
        .sts-exception-save .button:disabled { opacity: .5; cursor: not-allowed; }
        .sts-exception-error { padding: 10px 12px; border-left: 3px solid #b91c1c; color: #991b1b; background: #fff1f2; font-size: .85rem; }
        .sts-exception-stale { color: #946200; font-size: .78rem; margin: 8px 0; }
        .sts-unsaved-warning { padding: 14px 24px; border-top: 1px solid #e7cd89; background: #fff8e8; font-size: .85rem; flex: 0 0 auto; }
        .sts-unsaved-warning p { margin: 0 0 10px; }
        .sts-unsaved-warning .actions { justify-content: flex-end; }
        .sts-workspace .sts-filters { padding: 20px 24px; background: #fff; }
        .sts-workspace .sts-table-wrap { border-radius: 0; border-color: #c6ddd4; }
        .sts-table th { background: #e5f1ec; color: #28564a; }
        .sts-table tbody tr:nth-child(even) { background: #f5f9f7; }
        .sts-table tbody tr:hover { background: #edf4fa; }
        .sts-table tbody tr:has([data-sts-reviewed]:checked) .sts-order { background: #d8eee1; border-color: #afd0bc; color: #286040; }
        .sts-workspace .sts-subject-section > details > summary { display:flex; align-items:center; gap:14px; cursor:pointer; padding:18px 24px; background:#e6edf5; list-style:none; }
        .sts-subject-section > details > summary::-webkit-details-marker { display:none; }
        .sts-subject-section > details > summary::after { content:'+'; font-size:1.3rem; color:var(--primary); margin-left:auto; }
        .sts-subject-section > details[open] > summary::after { content:'-'; }
        .sts-subject-section > details > .sts-band-body { background:#f7f9fc; }
        .sts-subject-section > details > summary > span { display:inline-flex; flex-direction:column; gap:5px; vertical-align:middle; margin:0 14px 0 4px; }
        .sts-subject-section > details > summary > .sts-badge { display:inline-flex; }
        .sts-subject-scope { font-size:.8rem; color:var(--muted); }
        .sts-subject-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); column-gap:24px; margin:16px 0; }
        .sts-subject-option { display:grid; grid-template-columns:18px minmax(0,1fr) auto; align-items:center; gap:10px; border-bottom:1px solid var(--line); padding:14px 12px; margin:0; cursor:pointer; font-size:.85rem; background:#edf3f9; }
        .sts-subject-option:has(input:not(:checked)) { background:#fff3d8; }
        .sts-subject-option input { width:18px; height:18px; margin:0; accent-color:var(--primary); }
        .sts-subject-option strong { display:block; overflow-wrap:anywhere; }
        .sts-subject-option:has(input:not(:checked)) strong { color:var(--muted); }
        .sts-subject-required { color:#236443; font-size:.75rem; }
        .sts-subject-footer { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:end; gap:20px; }
        .sts-subject-footer textarea { width:100%; resize:vertical; }
        .sts-subject-history { margin-top:12px; font-size:.8rem; }
        .sts-subject-history summary { cursor:pointer; }
        .sts-subject-history li { padding:8px 0; }
        .sts-subject-history p { margin:5px 0 0; overflow-wrap:anywhere; }
        .sts-subject-section [hidden] { display:none !important; }
        @media (max-width: 950px) { .sts-dates { grid-template-columns: repeat(2, minmax(150px, 1fr)); } }
        @media (max-width: 640px) { .sts-filters, .sts-dates, .sts-subject-list, .sts-subject-footer { grid-template-columns: 1fr; } .sts-workspace .sts-filters { padding:18px 16px; } .sts-workspace .sts-subject-section > details > summary { flex-wrap:wrap; padding:18px 16px; } .sts-subject-section > details > summary > .sts-badge { margin:0; } .sts-workspace .sts-band-footer { align-items:stretch; } }
    </style>
    @include('rapor-sts._workspace-style')
    <div class="sts-workspace">
    <div class="page-header">
        <div><p class="eyebrow">Penilaian</p><h1 class="page-title">Rapor STS</h1></div>
        <div class="actions"><a class="button button-muted" href="{{ route('leger-sts.index', ['kegiatan_id' => $kegiatan?->id, 'kelas_id' => $kelas?->id]) }}">Buka Leger STS</a>
            @if($laporan)<a class="button button-primary" href="{{ route('rapor-sts.publikasi.index', [$kegiatan, $kelas]) }}">Kelola publikasi</a>@endif
        </div>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger sts-feedback" id="sts-feedback" role="alert" tabindex="-1">
            <strong>Perubahan rapor belum tersimpan. Perbaiki bagian yang ditandai, lalu simpan kembali.</strong>
            <ul>@foreach ($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach</ul>
        </div>
    @elseif (session('berhasil'))
        <div class="alert sts-feedback" id="sts-feedback" role="status" tabindex="-1">{{ session('berhasil') }}</div>
    @endif
    <form method="GET" action="{{ route('rapor-sts.index') }}" class="feature-filter sts-section sts-filters">
        <div class="field"><label for="sts-kegiatan">Kegiatan STS</label>
            <select class="select" id="sts-kegiatan" name="kegiatan_id" required>
                @forelse ($daftarKegiatan as $item)
                    <option value="{{ $item->id }}" @selected($kegiatan?->id === $item->id)>{{ $item->nama }} · {{ $item->tahunPelajaran?->nama }}</option>
                @empty<option value="">Belum ada kegiatan STS</option>@endforelse
            </select>
        </div>
        <div class="field"><label for="sts-kelas">Kelas</label><select class="select" id="sts-kelas" name="kelas_id">
            @forelse ($daftarKelas as $item)<option value="{{ $item->id }}" @selected($kelas?->id === $item->id)>{{ $item->nama }}</option>@empty<option value="">Belum ada kelas</option>@endforelse
        </select></div>
        <button class="button button-primary" @disabled($daftarKegiatan->isEmpty())>Tampilkan rekap</button>
    </form>
    @if ($laporan)
        @php
            $pengaturan = $laporan['pengaturan'];
            $baris = $laporan['baris'];
            $siap = $baris->where('siap', true)->count();
            $diperiksa = $baris->where('diperiksa', true)->count();
        @endphp
        <div class="sts-context"><strong>{{ $kelas->nama }}</strong><span>{{ $kegiatan->nama }}</span><span>Wali kelas: {{ $kelas->waliKelas?->nama_lengkap ?? 'Belum ditetapkan' }}</span></div>
        <div class="sts-summary" aria-label="Ringkasan kesiapan rapor">
            <div class="sts-metric"><span>Siswa</span><strong>{{ $baris->count() }}</strong></div>
            <div class="sts-metric sts-metric--blue"><span>Rekap nilai lengkap</span><strong>{{ $baris->where('nilai_tuntas', true)->count() }}</strong></div>
            <div class="sts-metric sts-metric--amber"><span>Kehadiran diperiksa</span><strong>{{ $diperiksa }}</strong></div>
            <div class="sts-metric sts-metric--teal"><span>Siap cetak</span><strong>{{ $siap }}</strong></div>
        </div>
        @include('rapor-sts._mapel')
        <section class="sts-section sts-band sts-band--blue">
            <div class="sts-band-head"><div class="sts-band-title"><span class="sts-step" aria-hidden="true">01</span><div><h2>Periode dan tanggal rapor</h2><p>{{ $kegiatan->tahunPelajaran?->nama }} · Semester {{ ucfirst($kegiatan->semester) }}</p></div></div></div>
            <div class="sts-band-body">
            <form method="POST" action="{{ route('rapor-sts.pengaturan', [$kegiatan, $kelas]) }}" class="sts-dates" id="sts-periode-form">
                @csrf @method('PUT')<input type="hidden" name="versi" value="{{ $pengaturan->versi }}">
                <div class="field"><label for="sts-awal">Awal rekap presensi</label><input class="input" id="sts-awal" type="date" name="tanggal_awal_presensi" value="{{ old('tanggal_awal_presensi', $pengaturan->tanggal_awal_presensi->toDateString()) }}" required></div>
                <div class="field"><label for="sts-akhir">Batas rekap presensi</label><input class="input" id="sts-akhir" type="date" name="tanggal_akhir_presensi" value="{{ old('tanggal_akhir_presensi', $pengaturan->tanggal_akhir_presensi->toDateString()) }}" required></div>
                <div class="field"><label for="sts-tanggal">Tanggal pembagian rapor</label><input class="input" id="sts-tanggal" type="date" name="tanggal_rapor" value="{{ old('tanggal_rapor', $pengaturan->tanggal_rapor->toDateString()) }}" required></div>
            </form>
            @if (! $pengaturan->exists)<p class="sts-notice">Periode belum disimpan. Pemeriksaan kehadiran dan cetak rapor belum tersedia.</p>@endif
            @if (! $pengaturan->tanggal_akhir_presensi->copy()->endOfDay()->isPast())<p class="sts-notice">Periode presensi belum berakhir. Rapor belum dapat dicetak.</p>@endif
            <div class="sts-band-footer"><p class="help-text">Nilai: hasil final CBT atau STS manual untuk mapel non-CBT.</p><button class="button button-primary" form="sts-periode-form">Simpan periode</button></div>
            </div>
        </section>
        <section class="sts-section sts-band sts-band--amber">
            <div class="sts-band-head"><div class="sts-band-title"><span class="sts-step" aria-hidden="true">02</span><div><h2>Lampiran perilaku dan pembinaan</h2><p>{{ $perilaku['labelIsiLampiran'] }}</p></div></div>
                @if(\App\Services\Nilai\LampiranPerilakuStsService::petugas(auth()->user()))<a class="button button-muted" href="{{ route('lampiran-perilaku-sts.index', ['kegiatan_id' => $kegiatan->id, 'kelas_id' => $kelas->id]) }}">Pemeriksaan BK</a>@endif
            </div>
            <div class="sts-band-body">
                <span class="sts-badge {{ $perilaku['baris']->every(fn ($b) => $b['siap']) ? 'is-complete' : '' }}">{{ $perilaku['baris']->where('siap', true)->count() }}/{{ $baris->count() }} lampiran diperiksa</span>
                @if($perilaku['guruBk']->isEmpty())<p class="sts-notice">Guru BK tingkat {{ $kelas->tingkat }} belum ditetapkan untuk tanggal rapor.</p>@endif
                @if($perilaku['wakilKesiswaan']->isEmpty())<p class="sts-notice">Penandatangan Wakil Kesiswaan belum ditetapkan.</p>@endif
            </div>
        </section>
        <section class="sts-section sts-band sts-band--teal">
            <div class="sts-band-head"><div class="sts-band-title"><span class="sts-step" aria-hidden="true">03</span><div><h2>Pemeriksaan nilai dan kehadiran</h2><p>{{ $diperiksa }} dari {{ $baris->count() }} siswa sudah diperiksa</p></div></div><button class="button button-primary" form="sts-kehadiran-form" @disabled(! $pengaturan->exists || $baris->isEmpty())>Simpan pemeriksaan</button></div>
            <div class="sts-band-body">
            <p class="sts-section-note">Rekap awal mengikuti presensi sekolah. Hari presensi aktif tanpa catatan atau konfirmasi dihitung alfa setelah hari berakhir. Koreksi di sini hanya berlaku pada rapor, tidak mengubah presensi harian.</p>
            @if ($baris->where('rekap_diperbarui', true)->isNotEmpty())
                <p class="sts-notice" role="status">Periode atau presensi berubah: rekap {{ $baris->where('rekap_diperbarui', true)->count() }} siswa telah mengikuti sumber terbaru dan perlu diperiksa ulang. Koreksi manual yang tersimpan tetap dipertahankan.</p>
            @endif
            <form method="POST" action="{{ route('rapor-sts.kehadiran', [$kegiatan, $kelas]) }}" id="sts-kehadiran-form">
                @csrf @method('PUT')<input type="hidden" name="versi" value="{{ $pengaturan->versi }}">
                <div class="sts-actions">
                    <label class="sts-check"><input type="checkbox" id="sts-periksa-semua" @disabled(! $pengaturan->exists || $baris->isEmpty())> Semua siswa sudah diperiksa</label>
                    <span class="sts-badge {{ $siap === $baris->count() && $siap ? 'is-complete' : '' }}">{{ $siap }}/{{ $baris->count() }} siap cetak</span>
                </div>
                <div class="sts-table-wrap" tabindex="0" aria-label="Rekap kehadiran dan kelengkapan nilai STS">
                    <table class="sts-table">
                        <colgroup><col style="width: 24%"><col style="width: 25%"><col style="width: 22%"><col style="width: 17%"><col style="width: 12%"></colgroup>
                        <thead><tr>
                        <th scope="col">Siswa & nilai STS</th><th scope="col">Ketidakhadiran <span style="font-weight: 400">(hari)</span></th><th scope="col">Alasan koreksi</th><th scope="col">Pemeriksaan</th><th scope="col">Rapor</th>
                    </tr></thead><tbody>
                    @forelse ($baris as $item)
                        @php $id = $item['anggota']->id; $nama = $item['anggota']->siswa->nama_lengkap; @endphp
                        <tr data-sts-row @class(['sts-row-has-error' => $errors->has('siswa.'.$id.'.*')])>
                            <td class="sts-person">
                                <div class="sts-identity"><span class="sts-order">{{ $item['anggota']->nomor_absen }}</span><strong>{{ $nama }}</strong></div>
                                <span class="sts-badge {{ $item['nilai_lengkap'] ? 'is-complete' : '' }}">{{ $item['nilai']->whereNotNull('nilai')->count() }}/{{ $laporan['mapel']->count() }} mapel final</span>
                                @if ($item['jumlah_pengecualian'])<span class="sts-original">{{ $item['jumlah_pengecualian'] }} mapel: tidak mengikuti STS</span>@endif
                                <div><button type="button" class="button button-muted sts-detail-button" data-sts-grades="sts-nilai-{{ $id }}" data-sts-name="{{ $nama }}" aria-haspopup="dialog" aria-controls="sts-grade-dialog" aria-label="Rincian nilai {{ $nama }}">Rincian nilai</button></div>
                            </td>
                            <td>
                                <div class="sts-attendance">
                            @foreach (['sakit' => 'Sakit', 'izin' => 'Izin', 'alfa' => 'Alfa'] as $jenis => $label)
                                    <div><label for="sts-{{ $jenis }}-{{ $id }}">{{ $label }}</label><input class="input sts-number" id="sts-{{ $jenis }}-{{ $id }}" type="number" name="siswa[{{ $id }}][{{ $jenis }}]" value="{{ old('siswa.'.$id.'.'.$jenis, $item['kehadiran'][$jenis]) }}" min="0" max="366" step="1" required aria-label="{{ $label }} {{ $nama }}" @if ($errors->has('siswa.'.$id.'.'.$jenis)) aria-invalid="true" aria-describedby="sts-error-{{ $jenis }}-{{ $id }}" @endif data-sts-original="{{ $item['sumber'][$jenis] }}" @disabled(! $pengaturan->exists)><span class="sts-original">Rekap awal: {{ $item['sumber'][$jenis] }}</span>@error('siswa.'.$id.'.'.$jenis)<span class="sts-field-error" id="sts-error-{{ $jenis }}-{{ $id }}">{{ $message }}</span>@enderror</div>
                            @endforeach
                                </div>
                                <span class="sts-original">{{ $item['sumber']['hari_tercatat'] }} hari presensi tercatat</span>
                                <dl class="sts-lateness" aria-label="Keterlambatan {{ $nama }}">
                                    <div><dt>Terlambat</dt><dd><span data-sts-late-count>{{ $item['keterlambatan']['jumlah'] }}</span> kali</dd></div>
                                    <div><dt>Total keterlambatan</dt><dd><span data-sts-late-minutes>{{ $item['keterlambatan']['total_menit'] }}</span> menit</dd></div>
                                </dl>
                            </td>
                            <td><textarea class="input sts-note" id="sts-catatan-{{ $id }}" name="siswa[{{ $id }}][catatan_koreksi]" maxlength="500" placeholder="Catatan koreksi..." aria-label="Alasan koreksi {{ $nama }}" @if ($errors->has('siswa.'.$id.'.catatan_koreksi')) aria-invalid="true" aria-describedby="sts-error-catatan-{{ $id }}" @endif @disabled(! $pengaturan->exists)>{{ old('siswa.'.$id.'.catatan_koreksi', $item['koreksi']?->catatan_koreksi) }}</textarea>@error('siswa.'.$id.'.catatan_koreksi')<span class="sts-field-error" id="sts-error-catatan-{{ $id }}">{{ $message }}</span>@enderror<button type="button" class="sts-reset" data-sts-reset @disabled(! $pengaturan->exists)>Kembalikan ke rekap awal</button></td>
                            <td>
                                <input type="hidden" name="siswa[{{ $id }}][sidik_sumber]" value="{{ $item['sidik_sumber'] }}">
                                <input type="hidden" name="siswa[{{ $id }}][diperiksa]" value="0">
                                <label class="sts-check sts-review"><input type="checkbox" name="siswa[{{ $id }}][diperiksa]" value="1" data-sts-reviewed data-sts-saved="{{ (int) $item['diperiksa'] }}" data-sts-source-changed="{{ (int) $item['sumber_berubah'] }}" @checked(old('siswa.'.$id.'.diperiksa', $item['diperiksa'])) @disabled(! $pengaturan->exists)> Diperiksa</label>
                                <span class="sts-status sts-review-state" data-sts-review-state>{{ $item['sumber_berubah'] ? 'Rekap presensi berubah. Periksa ulang.' : ($item['diperiksa'] ? 'Tersimpan' : 'Belum diperiksa') }}</span>
                                @error('siswa.'.$id.'.sidik_sumber')<span class="sts-field-error">{{ $message }}</span>@enderror
                                @error('siswa.'.$id.'.diperiksa')<span class="sts-field-error">{{ $message }}</span>@enderror
                                @if ($item['koreksi']?->diperiksa_pada)<span class="sts-original">{{ $item['koreksi']->pemeriksa?->nama }}<br>{{ $item['koreksi']->diperiksa_pada->format('d-m-Y H:i') }}</span>@endif
                            </td>
                            <td><div class="sts-report-actions"><a class="button button-muted" target="_blank" rel="noopener" aria-label="Pratinjau rapor {{ $nama }}" href="{{ route('rapor-sts.cetak', [$kegiatan, $kelas, 'anggota_id' => $id, 'pratinjau' => 1]) }}">Pratinjau</a>
                                @if ($item['siap'])<a class="button button-primary" data-sts-print target="_blank" rel="noopener" aria-label="Cetak rapor {{ $nama }}" href="{{ route('rapor-sts.cetak', [$kegiatan, $kelas, 'anggota_id' => $id]) }}">Cetak rapor</a>@endif
                                @if($perilaku['baris']->firstWhere('anggota.id', $id)['siap'] ?? false)
                                    <a class="button button-muted" target="_blank" rel="noopener" href="{{ route('rapor-sts.cetak', [$kegiatan, $kelas, 'anggota_id' => $id, 'pratinjau' => 1, 'perilaku' => 1]) }}">Pratinjau gabungan</a>
                                    @if($item['siap'])<a class="button button-primary" data-sts-print target="_blank" rel="noopener" href="{{ route('rapor-sts.cetak', [$kegiatan, $kelas, 'anggota_id' => $id, 'perilaku' => 1]) }}">Rapor + perilaku</a>@endif
                                @else<span class="sts-original">Lampiran menunggu pemeriksaan BK.</span>@endif
                                </div>
                            </td>
                        </tr>
                    @empty<tr><td colspan="5">Belum ada siswa aktif pada kelas ini.</td></tr>@endforelse
                    </tbody></table>
                </div>
                <p class="help-text" id="sts-unsaved" hidden>Ada perubahan yang belum disimpan. Cetak menggunakan pemeriksaan terakhir yang tersimpan.</p>
                <div class="sts-band-footer"><p class="help-text">{{ $baris->count() - $diperiksa }} siswa belum diperiksa</p><button class="button button-primary" @disabled(! $pengaturan->exists || $baris->isEmpty())>Simpan pemeriksaan</button></div>
            </form>
            </div>
        </section>
        <dialog id="sts-grade-dialog" class="sts-dialog" aria-labelledby="sts-grade-title" aria-describedby="sts-grade-student" data-sts-reopen="{{ session('rincian_siswa', old('anggota_id')) }}">
            <div class="sts-dialog-header">
                <div><h2 id="sts-grade-title">Rincian nilai STS</h2><p id="sts-grade-student"></p></div>
                <button type="button" class="button button-muted" data-sts-close autofocus>Tutup</button>
            </div>
            <div class="sts-dialog-body" id="sts-grade-content" tabindex="0" aria-label="Daftar nilai mata pelajaran"></div>
            <div class="sts-unsaved-warning" data-sts-unsaved-warning hidden role="alert">
                <p>Keterangan STS yang diubah belum disimpan.</p>
                <div class="actions"><button type="button" class="button button-muted" data-sts-discard>Tutup tanpa menyimpan</button><button type="button" class="button button-primary" data-sts-continue>Tetap mengedit</button></div>
            </div>
        </dialog>
        @foreach ($baris as $item)
            <template id="sts-nilai-{{ $item['anggota']->id }}">
                @php
                    $bisaCatat = $item['nilai']->contains(fn ($n) => $n['dapat_dikecualikan'] || $n['pengecualian']?->aktif);
                    $isOldStudent = (int) old('anggota_id') === $item['anggota']->id;
                @endphp
                <div class="sts-grade-summary"><span>{{ $kelas->nama }} · {{ $kegiatan->nama }}</span><span class="sts-badge {{ $item['nilai_lengkap'] ? 'is-complete' : '' }}">{{ $item['nilai']->whereNotNull('nilai')->count() }}/{{ $laporan['mapel']->count() }} mapel final</span></div>
                @if ($isOldStudent && $errors->any())
                    <div class="sts-exception-error" role="alert">@foreach ($errors->all() as $pesan)<p>{{ $pesan }}</p>@endforeach</div>
                @endif
                <form method="POST" action="{{ route('rapor-sts.pengecualian', [$kegiatan, $kelas]) }}" data-sts-exception-form>
                @csrf @method('PUT')
                <input type="hidden" name="versi" value="{{ $pengaturan->versi }}">
                <input type="hidden" name="anggota_id" value="{{ $item['anggota']->id }}">
                <p class="sts-exception-error" data-sts-exception-message role="alert" hidden>Simpan perubahan periode dan pemeriksaan kehadiran terlebih dahulu, kemudian simpan keterangan STS.</p>
                <table class="sts-grade-table">
                    <colgroup><col style="width: 39%"><col style="width: 80px"><col></colgroup>
                    <thead><tr><th scope="col">Mata pelajaran</th><th scope="col" class="sts-grade-score">Nilai</th><th scope="col">Keterangan</th></tr></thead>
                    <tbody>
                        @forelse ($item['nilai'] as $nilai)
                            <tr><td>{{ $nilai['mapel']->nama }}</td><td class="sts-grade-score"><strong>{{ $nilai['nilai'] === null ? '-' : number_format($nilai['nilai'], 2, ',', '.') }}</strong></td><td>
                                {{ $nilai['nilai'] === null ? $nilai['status'] : $nilai['keterangan'] }}
                                @if ($nilai['dapat_dikecualikan'] || $nilai['pengecualian']?->aktif)
                                    @php
                                        $mid = $nilai['mapel']->id;
                                        $catatan = $nilai['pengecualian'];
                                        $dicentang = $isOldStudent ? old('pengecualian.'.$mid.'.tidak_mengikuti', $catatan?->aktif ?? false) : ($catatan?->aktif ?? false);
                                    @endphp
                                    <div class="sts-exception" data-sts-exception>
                                        <input type="hidden" name="pengecualian[{{ $mid }}][sidik_kondisi]" value="{{ $nilai['sidik_kondisi'] }}">
                                        <input type="hidden" name="pengecualian[{{ $mid }}][tidak_mengikuti]" value="0">
                                        <label class="sts-check"><input type="checkbox" name="pengecualian[{{ $mid }}][tidak_mengikuti]" value="1" data-sts-exempt @checked($dicentang) @disabled(! $pengaturan->exists)> Tidak mengikuti STS</label>
                                        @if ($catatan?->aktif && ! $nilai['dikecualikan'])<p class="sts-exception-stale">Keterangan sebelumnya tidak berlaku karena data ujian berubah. Periksa ulang atau hapus centangnya.</p>@endif
                                        <label class="sts-exception-reason" @if (! $dicentang) hidden @endif>Alasan tidak mengikuti STS dan susulan
                                            <textarea class="input" name="pengecualian[{{ $mid }}][alasan]" maxlength="500" @required($dicentang) @disabled(! $pengaturan->exists || ! $dicentang)>{{ $isOldStudent ? old('pengecualian.'.$mid.'.alasan', $catatan?->alasan) : $catatan?->alasan }}</textarea>
                                        </label>
                                        @if ($catatan?->aktif)<span class="sts-original">Dicatat oleh {{ $catatan->penetap?->nama ?? '-' }}<br>{{ $catatan->ditetapkan_pada->format('d-m-Y H:i') }}</span>@endif
                                    </div>
                                @endif
                            </td></tr>
                        @empty<tr><td colspan="3">Belum ada mata pelajaran.</td></tr>@endforelse
                    </tbody>
                    <tfoot><tr><th scope="row">Rata-rata</th><td class="sts-grade-score"><strong>{{ $item['rata'] === null ? '-' : number_format($item['rata'], 2, ',', '.') }}</strong></td><td>{{ $item['nilai_tuntas'] ? ($item['jumlah_bernilai'] ? 'Dari '.$item['jumlah_bernilai'].' mapel bernilai' : 'Tidak ada mapel bernilai') : 'Menunggu nilai / keterangan lengkap' }}</td></tr></tfoot>
                </table>
                @if (! $item['nilai_lengkap'])<p class="sts-dialog-footnote">Nilai yang belum tersedia tidak dihitung sebagai nol.</p>@endif
                @if ($bisaCatat)
                    <p class="sts-dialog-footnote">Keterangan ini berlaku bagi siswa yang tidak mengikuti STS maupun susulan. Rapor mencantumkan "Tidak mengikuti STS"; alasan disimpan sebagai catatan sekolah.</p>
                    <div class="sts-exception-save"><button class="button button-primary" @disabled(! $pengaturan->exists)>Simpan keterangan STS</button></div>
                @endif
                </form>
            </template>
        @endforeach
        <section class="sts-section sts-band">
            <div class="sts-band-head"><div class="sts-band-title"><span class="sts-step" aria-hidden="true">04</span><div><h2>Cetak rapor kelas</h2><p>{{ $siap }} dari {{ $baris->count() }} siswa siap cetak · A4</p></div></div></div>
            <div class="sts-band-body">
            <div class="actions">
                @if ($baris->isNotEmpty())<a class="button button-muted" target="_blank" rel="noopener" href="{{ route('rapor-sts.cetak', [$kegiatan, $kelas, 'pratinjau' => 1]) }}">Pratinjau seluruh kelas</a>@endif
                @if ($siap > 0 && $siap === $baris->count())<a class="button button-primary" data-sts-print target="_blank" rel="noopener" href="{{ route('rapor-sts.cetak', [$kegiatan, $kelas]) }}">Cetak seluruh kelas</a>
                @else<button class="button button-primary" disabled>Cetak seluruh kelas</button>@endif
                @if($baris->isNotEmpty() && $perilaku['baris']->every(fn ($b) => $b['siap']))
                    <a class="button button-muted" target="_blank" rel="noopener" href="{{ route('rapor-sts.cetak', [$kegiatan, $kelas, 'pratinjau' => 1, 'perilaku' => 1]) }}">Pratinjau gabungan kelas</a>
                    @if($siap === $baris->count())<a class="button button-primary" data-sts-print target="_blank" rel="noopener" href="{{ route('rapor-sts.cetak', [$kegiatan, $kelas, 'perilaku' => 1]) }}">Cetak kelas + perilaku</a>@endif
                @endif
            </div>
            <p class="help-text">{{ $baris->count() - $siap }} siswa belum siap cetak · A4, satu halaman per siswa.</p>
            @if ($baris->contains(fn ($item) => ! $item['nilai_tuntas']))<p class="help-text">{{ $baris->where('nilai_tuntas', false)->count() }} siswa memiliki nilai yang belum tersedia.</p>@endif
            </div>
        </section>
        <section class="sts-section sts-publish-band">
            <div><h2>Publikasi untuk orang tua dan siswa</h2><p>Rapor tidak tampil pada akun penerima sebelum dipublikasikan. Koreksi setelah penerbitan perlu dipublikasikan ulang.</p></div>
            <a class="button" href="{{ route('rapor-sts.publikasi.index', [$kegiatan, $kelas]) }}">Publikasi rapor</a>
        </section>
    @else
        <p class="sts-notice">Belum ada kegiatan STS atau kelas dalam kewenangan Anda.</p>
    @endif
    <script>
        document.getElementById('sts-kegiatan')?.addEventListener('change', function () {
            document.getElementById('sts-kelas').disabled = true;
        });
        (() => {
            document.getElementById('sts-feedback')?.focus();
            const form = document.getElementById('sts-kehadiran-form');
            if (!form) return;
            const checks = [...form.querySelectorAll('[data-sts-reviewed]')];
            const all = document.getElementById('sts-periksa-semua');
            const mapelForm = document.getElementById('sts-mapel-form');
            const mapelChecks = [...document.querySelectorAll('[data-sts-mapel-choice]')];
            let mapelDirty = false;
            let submitting = false;
            const dialog = document.getElementById('sts-grade-dialog');
            const content = document.getElementById('sts-grade-content');
            let gradeTrigger = null;
            form.querySelectorAll('[data-sts-grades]').forEach(button => button.addEventListener('click', () => {
                const template = document.getElementById(button.dataset.stsGrades);
                if (!template) return;
                gradeTrigger = button;
                exceptionDirty = false;
                dialog.querySelector('[data-sts-unsaved-warning]').hidden = true;
                document.getElementById('sts-grade-student').textContent = button.dataset.stsName;
                content.replaceChildren(template.content.cloneNode(true));
                dialog.showModal();
                content.scrollTop = 0;
            }));
            const closeGrades = () => {
                if (!exceptionDirty) { dialog.close(); return; }
                dialog.querySelector('[data-sts-unsaved-warning]').hidden = false;
                dialog.querySelector('[data-sts-continue]').focus();
            };
            dialog.querySelector('[data-sts-close]').addEventListener('click', closeGrades);
            dialog.querySelector('[data-sts-discard]').addEventListener('click', () => dialog.close());
            dialog.querySelector('[data-sts-continue]').addEventListener('click', () => {
                dialog.querySelector('[data-sts-unsaved-warning]').hidden = true;
                content.focus();
            });
            dialog.addEventListener('close', () => gradeTrigger?.focus({ preventScroll: true }));
            let dirty = false;
            let exceptionDirty = false;
            const syncMapel = () => {
                if (!mapelForm) return;
                const count = mapelChecks.filter(c => c.checked).length;
                mapelDirty = mapelChecks.some(c => c.checked !== (c.dataset.saved === '1')) || mapelForm.elements.alasan_mapel.value.trim() !== '';
                mapelForm.querySelector('[data-sts-mapel-count]').textContent = `${count} mapel diikutkan · ${mapelChecks.length - count} dikecualikan`;
                mapelForm.querySelector('[data-sts-mapel-unsaved]').hidden = !mapelDirty;
                mapelForm.querySelector('button[type="submit"], button:not([type])').disabled = count === 0;
            };
            mapelForm?.addEventListener('input', syncMapel);
            syncMapel();
            mapelForm?.addEventListener('submit', event => {
                const warning = dirty || exceptionDirty ? 'Perubahan periode, kehadiran, atau keterangan siswa yang belum disimpan akan ditinggalkan. ' : '';
                if (!confirm(warning + `Simpan pilihan mapel untuk seluruh kelas tingkat ${mapelForm.dataset.tingkat}? Rapor, leger, statistik, dan ranking akan diperbarui.`)) {
                    event.preventDefault();
                    return;
                }
                submitting = true;
            });
            [form, document.getElementById('sts-periode-form')].forEach(other => other?.addEventListener('submit', event => {
                if (mapelDirty && !confirm('Pilihan mapel belum disimpan. Tinggalkan perubahan pilihan mapel dan simpan formulir ini?')) event.preventDefault();
                else submitting = true;
            }));
            window.addEventListener('beforeunload', event => {
                if (submitting || !mapelDirty) return;
                event.preventDefault(); event.returnValue = '';
            });
            window.addEventListener('pageshow', () => { submitting = false; syncMapel(); });
            content.addEventListener('change', event => {
                if (!event.target.matches('[data-sts-exempt]')) return;
                const field = event.target.closest('[data-sts-exception]').querySelector('.sts-exception-reason');
                field.hidden = !event.target.checked;
                field.querySelector('textarea').disabled = !event.target.checked;
                field.querySelector('textarea').required = event.target.checked;
            });
            content.addEventListener('input', () => { exceptionDirty = true; });
            content.addEventListener('submit', event => {
                if (dirty) {
                    event.preventDefault();
                    event.target.querySelector('[data-sts-exception-message]').hidden = false;
                }
            });
            dialog.addEventListener('cancel', event => {
                event.preventDefault();
                closeGrades();
            });
            const changedRows = new Set();
            const sync = () => {
                all.checked = checks.length > 0 && checks.every(c => c.checked);
                all.indeterminate = checks.some(c => c.checked) && !all.checked;
                checks.forEach(check => {
                    const row = check.closest('[data-sts-row]');
                    const state = row.querySelector('[data-sts-review-state]');
                    const pending = changedRows.has(row) || check.checked !== (check.dataset.stsSaved === '1');
                    state.textContent = pending ? 'Belum disimpan' : (check.dataset.stsSourceChanged === '1' ? 'Rekap presensi berubah. Periksa ulang.' : (check.checked ? 'Tersimpan' : 'Belum diperiksa'));
                    state.classList.toggle('is-pending', pending);
                });
            };
            const changed = () => { dirty = true; document.getElementById('sts-unsaved').hidden = false; sync(); };
            form.addEventListener('input', event => {
                if (event.target === all) return;
                const row = event.target.closest('[data-sts-row]');
                if (row) changedRows.add(row);
                changed();
            });
            document.getElementById('sts-periode-form')?.addEventListener('input', changed);
            all.addEventListener('change', () => {
                checks.forEach(check => {
                    if (check.checked !== all.checked) changedRows.add(check.closest('[data-sts-row]'));
                    check.checked = all.checked;
                });
                changed();
            });
            form.querySelectorAll('[data-sts-reset]').forEach(button => button.addEventListener('click', () => {
                const row = button.closest('[data-sts-row]');
                row.querySelectorAll('[data-sts-original]').forEach(input => input.value = input.dataset.stsOriginal);
                row.querySelector('textarea').value = '';
                row.querySelector('[data-sts-reviewed]').checked = false;
                changedRows.add(row);
                changed();
            }));
            document.querySelectorAll('[data-sts-print]').forEach(link => link.addEventListener('click', event => {
                if (dirty || mapelDirty) { event.preventDefault(); alert('Simpan perubahan pilihan mapel, periode, dan pemeriksaan kehadiran sebelum mencetak rapor.'); }
            }));
            sync();
            if (dialog.dataset.stsReopen) {
                [...form.querySelectorAll('[data-sts-grades]')].find(button => button.dataset.stsGrades === 'sts-nilai-' + dialog.dataset.stsReopen)?.click();
            }
        })();
    </script>
    </div>
@endsection
