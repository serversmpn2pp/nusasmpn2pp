@extends('layouts.app')

@section('title', ($mode === 'hasil' ? 'Nilai & Hasil Ujian Terpusat' : 'Pelaksanaan Ujian Terpusat').' - NUSA')

@section('content')
    <style>
        .execution-hero { display:grid; grid-template-columns:minmax(0,1.5fr) minmax(260px,.6fr); overflow:hidden; background:var(--primary); color:#fff; }
        .execution-hero-main,.execution-hero-side { padding:22px 24px; }
        .execution-hero-side { border-left:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.08); }
        .execution-hero h2 { margin:0; color:#fff; font-size:1.45rem; }
        .execution-hero p { margin:7px 0 0; color:rgba(255,255,255,.82); }
        .execution-flow { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin:0 0 22px; }
        .execution-flow-item { display:grid; grid-template-columns:34px minmax(0,1fr); gap:10px; align-items:center; min-height:68px; padding:12px; border:1px solid var(--line); border-left:4px solid var(--primary); border-radius:7px; background:#fff; }
        .execution-flow-number { display:grid; width:34px; height:34px; margin:0; place-items:center; align-self:center; border-radius:50%; background:var(--primary-soft); color:var(--primary-dark); font-size:.78rem; font-weight:900; line-height:1; }
        .execution-flow-item > div > strong,.execution-flow-item > div > span { display:block; }
        .execution-flow-item > div > span { margin-top:2px; color:var(--muted); font-size:.76rem; font-weight:650; }
        .execution-list { display:grid; gap:16px; }
        .execution-summary { grid-template-columns:repeat(4,minmax(0,1fr)); }
        .execution-card { overflow:hidden; padding:0; }
        .execution-card-head { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:16px; align-items:start; padding:18px 20px; border-bottom:1px solid var(--line); }
        .execution-card-title { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
        .execution-card-title h2 { margin:0; font-size:1.08rem; }
        .execution-meta { margin:6px 0 0; color:var(--muted); font-size:.82rem; font-weight:650; }
        .execution-token { min-width:128px; text-align:right; }
        .execution-token span,.execution-token strong { display:block; }
        .execution-token span { color:var(--muted); font-size:.7rem; font-weight:750; text-transform:uppercase; }
        .execution-token strong { margin-top:2px; color:var(--primary-dark); font-size:1.2rem; }
        .execution-card-body { padding:18px 20px; }
        .execution-card-stats { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; }
        .execution-stat { min-width:0; padding:11px 12px; border:1px solid var(--line); border-radius:7px; background:#f8fafc; }
        .execution-stat strong,.execution-stat span { display:block; }
        .execution-stat strong { color:var(--primary-dark); font-size:1.12rem; }
        .execution-stat span { margin-top:2px; color:var(--muted); font-size:.72rem; font-weight:700; }
        .execution-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
        .supervisor-details { margin-top:16px; border-top:1px solid var(--line); padding-top:14px; }
        .supervisor-details > summary { cursor:pointer; color:var(--primary-dark); font-weight:800; }
        .supervisor-grid { display:grid; gap:10px; margin-top:12px; }
        .supervisor-entry { display:grid; gap:8px; }
        .supervisor-row { display:grid; grid-template-columns:minmax(130px,.6fr) minmax(180px,1fr) minmax(180px,1fr) minmax(180px,.8fr) auto; gap:10px; align-items:end; padding:12px; border:1px solid var(--line); border-radius:7px; background:#f8fafc; }
        .supervisor-room strong,.supervisor-room span { display:block; }
        .supervisor-room span { margin-top:3px; color:var(--muted); font-size:.74rem; }
        .supervisor-proof { display:flex; flex-wrap:wrap; gap:6px; margin-top:7px; }
        .proof-overview { display:flex; align-items:center; justify-content:space-between; gap:16px; margin:0 0 18px; padding:15px 18px; }
        .proof-overview-copy strong,.proof-overview-copy span { display:block; }
        .proof-overview-copy span { margin-top:3px; color:var(--muted); font-size:.78rem; }
        .proof-overview-stats { display:flex; flex-wrap:wrap; gap:8px; }
        .supervisor-row .actions { display:grid; gap:7px; align-self:stretch; align-content:end; }
        .supervisor-row .actions .button { text-align:center; }
        .assigned-supervisor { min-height:44px; padding:8px 10px; border:1px solid var(--line); border-radius:7px; background:#fff; }
        .assigned-supervisor strong,.assigned-supervisor span { display:block; }
        .assigned-supervisor span { margin-top:2px; color:var(--muted); font-size:.7rem; }
        .supervisor-replacement { padding:11px 13px; border:1px solid #eab308; border-left:4px solid var(--accent); border-radius:7px; background:#fffbeb; }
        .supervisor-replacement > summary { display:flex; align-items:center; justify-content:space-between; gap:12px; cursor:pointer; color:#713f12; font-weight:850; }
        .supervisor-replacement-copy { margin:8px 0 0; color:#854d0e; font-size:.78rem; }
        .replacement-form { display:grid; grid-template-columns:minmax(170px,.65fr) minmax(210px,1fr) minmax(230px,1.2fr) auto; gap:10px; align-items:end; margin-top:12px; }
        .replacement-history { display:grid; gap:7px; margin-top:13px; padding-top:12px; border-top:1px solid #fde68a; }
        .replacement-history h4 { margin:0; color:#713f12; font-size:.8rem; }
        .replacement-history-item { padding:9px 10px; border-radius:6px; background:rgba(255,255,255,.78); }
        .replacement-history-item strong,.replacement-history-item span { display:block; }
        .replacement-history-item span { margin-top:3px; color:#854d0e; font-size:.72rem; line-height:1.45; }
        .empty-execution { padding:28px; text-align:center; color:var(--muted); }
        .central-wizard-actions { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:20px; }
        @media (max-width:1050px) { .execution-summary,.execution-flow { grid-template-columns:repeat(2,minmax(0,1fr)); } .execution-card-stats { grid-template-columns:repeat(3,minmax(0,1fr)); } .supervisor-row,.replacement-form { grid-template-columns:repeat(2,minmax(0,1fr)); } .supervisor-room,.supervisor-row .actions { grid-column:1 / -1; } .replacement-form .button { width:100%; } }
        @media (max-width:680px) { .execution-hero { grid-template-columns:1fr; } .execution-hero-side { border-top:1px solid rgba(255,255,255,.18); border-left:0; } .execution-flow,.execution-card-stats { grid-template-columns:1fr 1fr; } .execution-card-head { grid-template-columns:1fr; } .execution-token { text-align:left; } .supervisor-row,.replacement-form { grid-template-columns:1fr; } .supervisor-room,.supervisor-row .actions { grid-column:auto; } .supervisor-row .button,.replacement-form .button { width:100%; } .proof-overview { align-items:flex-start; flex-direction:column; } .central-wizard-actions { align-items:stretch; flex-direction:column-reverse; } .central-wizard-actions .button { width:100%; text-align:center; } }
        @media (max-width:480px) { .execution-summary,.execution-flow { grid-template-columns:1fr; } }
    </style>

    @php
        $halamanHasil = $mode === 'hasil';
        $judulHalaman = $halamanHasil ? 'Nilai & hasil ujian' : 'Pelaksanaan ujian';
        $subjudulHalaman = $halamanHasil
            ? 'Periksa jawaban, selesaikan koreksi manual, dan masukkan nilai final ke nilai siswa.'
            : 'Atur pengawas, lihat token, dan pantau peserta selama ujian berlangsung.';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian Terpusat · Tahap {{ $tahapAktif }}</p>
            <h1 class="page-title">{{ $judulHalaman }}</h1>
            <p class="page-subtitle">{{ $subjudulHalaman }}</p>
        </div>
        <div class="actions"><a href="{{ route('ujian-terpusat.show', $kegiatan) }}" class="button button-muted">Ringkasan ujian</a></div>
    </div>

    @if (session('berhasil')) <div class="alert">{{ session('berhasil') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger"><strong>Ada bagian yang perlu diperbaiki.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

    <section class="panel execution-hero">
        <div class="execution-hero-main">
            <p class="eyebrow" style="color:var(--accent);">{{ $kegiatan->jenisUjianCbt?->nama }}</p>
            <h2>{{ $kegiatan->nama }}</h2>
            <p>{{ $kegiatan->tahunPelajaran?->nama }} · Semester {{ ucfirst($kegiatan->semester) }} · {{ $kegiatan->labelPeriode() }}</p>
        </div>
        <div class="execution-hero-side"><strong>{{ $ringkasan['paket_siap'] }} paket siap</strong><p>{{ $ringkasan['peserta'] }} peserta tersinkron ke ruang ujian</p></div>
    </section>

    @include('ujian-terpusat.partials.alur')

    <div class="stats-grid execution-summary">
        @if ($halamanHasil)
            <div class="panel stat active"><p class="stat-label">Selesai mengerjakan</p><p class="stat-value">{{ $ringkasan['selesai'] }}</p></div>
            <div class="panel stat warning"><p class="stat-label">Perlu koreksi manual</p><p class="stat-value">{{ $ringkasan['perlu_manual'] }}</p></div>
            <div class="panel stat"><p class="stat-label">Nilai sudah dimasukkan</p><p class="stat-value">{{ $ringkasan['nilai_diterapkan'] }}</p></div>
            <div class="panel stat"><p class="stat-label">Total peserta</p><p class="stat-value">{{ $ringkasan['peserta'] }}</p></div>
        @else
            <div class="panel stat"><p class="stat-label">Belum mulai</p><p class="stat-value">{{ $ringkasan['belum_mulai'] }}</p></div>
            <div class="panel stat warning"><p class="stat-label">Sedang mengerjakan</p><p class="stat-value">{{ $ringkasan['sedang'] }}</p></div>
            <div class="panel stat active"><p class="stat-label">Selesai mengerjakan</p><p class="stat-value">{{ $ringkasan['selesai'] }}</p></div>
            <div class="panel stat"><p class="stat-label">Total peserta</p><p class="stat-value">{{ $ringkasan['peserta'] }}</p></div>
        @endif
    </div>

    @if (! $halamanHasil)
        <section class="panel proof-overview">
            <div class="proof-overview-copy"><strong>Kelengkapan bukti ruang</strong><span>Pengawas mengunggah foto daftar hadir dan berita acara dari Tugas Pengawas Saya.</span></div>
            <div class="proof-overview-stats">
                <span class="badge badge-active">{{ $ringkasan['bukti_valid'] }} valid</span>
                <span class="badge badge-warning">{{ $ringkasan['bukti_menunggu'] }} menunggu</span>
                <span class="badge badge-muted">{{ $ringkasan['bukti_belum_lengkap'] }} perlu tindakan</span>
            </div>
        </section>
    @endif

    <div class="execution-flow" aria-label="Alur {{ strtolower($judulHalaman) }}">
        @if ($halamanHasil)
            <div class="execution-flow-item"><span class="execution-flow-number">1</span><div><strong>Periksa jawaban</strong><span>Jawaban objektif dikoreksi otomatis oleh NUSA.</span></div></div>
            <div class="execution-flow-item"><span class="execution-flow-number">2</span><div><strong>Selesaikan uraian</strong><span>Guru memeriksa jawaban yang memerlukan koreksi manual.</span></div></div>
            <div class="execution-flow-item"><span class="execution-flow-number">3</span><div><strong>Masukkan nilai</strong><span>Hasil final diterapkan ke komponen nilai siswa.</span></div></div>
        @else
            <div class="execution-flow-item"><span class="execution-flow-number">1</span><div><strong>Siapkan ruang</strong><span>Tentukan pengawas dan pastikan ruang ujian siap.</span></div></div>
            <div class="execution-flow-item"><span class="execution-flow-number">2</span><div><strong>Bagikan token</strong><span>Token ditampilkan kepada peserta saat ujian dibuka.</span></div></div>
            <div class="execution-flow-item"><span class="execution-flow-number">3</span><div><strong>Pantau langsung</strong><span>Lihat siswa yang belum mulai, mengerjakan, atau selesai.</span></div></div>
        @endif
    </div>

    <div class="execution-list">
        @forelse ($jadwal as $item)
            @php
                $paket = $item->ujianCbt;
                $pengawas = $item->pengawasRuangUjianTerpusat->keyBy('ruang_kegiatan_ujian_cbt_id');
                $paketSiap = $paket && in_array($paket->status, ['terjadwal', 'berlangsung', 'selesai'], true);
                $belumMulai = max(0, ($paket?->peserta_ujian_cbt_count ?? 0) - ($paket?->peserta_sedang_count ?? 0) - ($paket?->peserta_selesai_count ?? 0));
            @endphp
            <section class="panel execution-card">
                <div class="execution-card-head">
                    <div>
                        <div class="execution-card-title"><h2>{{ $item->mataPelajaran?->nama }} · Tingkat {{ $item->tingkat }}</h2><span class="badge {{ $paketSiap ? 'badge-active' : 'badge-warning' }}">{{ $paketSiap ? 'Siap digunakan' : ($paket ? 'Paket masih draf' : 'Paket belum dibuat') }}</span></div>
                        <p class="execution-meta">{{ $item->tanggal?->locale('id')->translatedFormat('l, d F Y') }} · {{ $item->labelWaktu() }} · {{ $item->kelas->pluck('nama')->join(', ') }}</p>
                    </div>
                    <div class="execution-token"><span>{{ $halamanHasil ? 'Status paket' : 'Token ujian' }}</span><strong>{{ $halamanHasil ? ($paket?->labelStatus() ?? 'Belum siap') : ($paket?->token ?: 'Tanpa token') }}</strong></div>
                </div>
                <div class="execution-card-body">
                    <div class="execution-card-stats">
                        <div class="execution-stat"><strong>{{ $paket?->soal_ujian_cbt_count ?? 0 }}</strong><span>Soal</span></div>
                        <div class="execution-stat"><strong>{{ $paket?->peserta_ujian_cbt_count ?? 0 }}</strong><span>Peserta</span></div>
                        @if ($halamanHasil)
                            <div class="execution-stat"><strong>{{ $paket?->peserta_selesai_count ?? 0 }}</strong><span>Selesai</span></div>
                            <div class="execution-stat"><strong>{{ $item->perlu_koreksi_manual }}</strong><span>Perlu koreksi</span></div>
                            <div class="execution-stat"><strong>{{ $paket?->nilai_diterapkan_count ?? 0 }}</strong><span>Nilai masuk</span></div>
                        @else
                            <div class="execution-stat"><strong>{{ $belumMulai }}</strong><span>Belum mulai</span></div>
                            <div class="execution-stat"><strong>{{ $paket?->peserta_sedang_count ?? 0 }}</strong><span>Mengerjakan</span></div>
                            <div class="execution-stat"><strong>{{ $paket?->peserta_selesai_count ?? 0 }}</strong><span>Selesai</span></div>
                        @endif
                    </div>

                    <div class="execution-actions">
                        @if ($paketSiap)
                            @if ($halamanHasil)
                                <a href="{{ route('ujian-cbt.hasil.index', $paket) }}" class="button button-primary">Lihat hasil ujian</a>
                                @if ($item->boleh_kelola_nilai)<a href="{{ route('ujian-cbt.koreksi-manual.index', $paket) }}" class="button button-muted">Koreksi uraian</a>@endif
                            @else
                                <a href="{{ route('ujian-cbt.monitoring.index', $paket) }}" class="button button-primary">Pantau ujian</a>
                            @endif
                        @else
                            <a href="{{ route('paket-soal-terpusat.show', $item) }}" class="button button-primary">Siapkan paket soal</a>
                        @endif
                    </div>

                    @if (! $halamanHasil && $item->ruangPelaksanaan->isNotEmpty())
                        <details class="supervisor-details">
                            <summary>Pengawas ruang ({{ $item->ruangPelaksanaan->count() }} ruang)</summary>
                            <div class="supervisor-grid">
                                @foreach ($item->ruangPelaksanaan as $ruang)
                                    @php
                                        $penugasan = $pengawas->get($ruang->id);
                                        $ruangOperasional = $item->ruangOperasional->get($ruang->id);
                                        $kelasStatusBukti = match($ruangOperasional?->status_bukti) {
                                            'valid' => 'badge-active',
                                            'menunggu_pemeriksaan', 'siap_dikirim' => 'badge-warning',
                                            'perlu_diulang' => 'badge-danger',
                                            default => 'badge-muted',
                                        };
                                    @endphp
                                    @if ($bolehAturPengawas)
                                        <div class="supervisor-entry">
                                            <form class="supervisor-row" method="POST" action="{{ route('ujian-terpusat.pengawas.update', [$kegiatan, $item, $ruang]) }}">
                                                @csrf @method('PUT')
                                                <div class="supervisor-room">
                                                    <strong>{{ $ruang->nama }}</strong><span>{{ $ruang->lokasi ?: 'Lokasi belum dicatat' }}</span>
                                                    @if($ruangOperasional)<div class="supervisor-proof"><span class="badge {{ $kelasStatusBukti }}">{{ $ruangOperasional->labelStatusBukti() }}</span><span class="badge badge-muted">{{ $ruangOperasional->bukti_daftar_hadir_count }} hadir · {{ $ruangOperasional->bukti_berita_acara_count }} BA</span></div>@endif
                                                </div>
                                                <div class="field">
                                                    <label for="utama_{{ $item->id }}_{{ $ruang->id }}">Pengawas utama</label>
                                                    @if($penugasan?->pengawas_utama_pegawai_id)
                                                        <div class="assigned-supervisor"><strong>{{ $penugasan->pengawasUtama?->nama_lengkap }}</strong><span>Sudah ditugaskan</span></div>
                                                        <input type="hidden" name="pengawas_utama_pegawai_id" value="{{ $penugasan->pengawas_utama_pegawai_id }}">
                                                    @else
                                                        <select id="utama_{{ $item->id }}_{{ $ruang->id }}" name="pengawas_utama_pegawai_id" class="input"><option value="">Belum ditentukan</option>@foreach($pegawai as $orang)<option value="{{ $orang->id }}">{{ $orang->nama_lengkap }}</option>@endforeach</select>
                                                    @endif
                                                </div>
                                                <div class="field">
                                                    <label for="pendamping_{{ $item->id }}_{{ $ruang->id }}">Pendamping</label>
                                                    @if($penugasan?->pengawas_pendamping_pegawai_id)
                                                        <div class="assigned-supervisor"><strong>{{ $penugasan->pengawasPendamping?->nama_lengkap }}</strong><span>Sudah ditugaskan</span></div>
                                                        <input type="hidden" name="pengawas_pendamping_pegawai_id" value="{{ $penugasan->pengawas_pendamping_pegawai_id }}">
                                                    @else
                                                        <select id="pendamping_{{ $item->id }}_{{ $ruang->id }}" name="pengawas_pendamping_pegawai_id" class="input"><option value="">Tidak ada</option>@foreach($pegawai as $orang)<option value="{{ $orang->id }}">{{ $orang->nama_lengkap }}</option>@endforeach</select>
                                                    @endif
                                                </div>
                                                <div class="field"><label for="catatan_{{ $item->id }}_{{ $ruang->id }}">Catatan tugas</label><input id="catatan_{{ $item->id }}_{{ $ruang->id }}" name="catatan" class="input" value="{{ $penugasan?->catatan }}" placeholder="Opsional"></div>
                                                <div class="actions">
                                                    <button class="button button-primary" type="submit">{{ $penugasan?->pengawas_utama_pegawai_id && $penugasan?->pengawas_pendamping_pegawai_id ? 'Simpan catatan' : 'Simpan penugasan' }}</button>
                                                    @if($bolehCetakDokumen && $paketSiap && $penugasan?->pengawas_utama_pegawai_id)
                                                        <a href="{{ route('ujian-terpusat.dokumen-ruang.cetak', [$kegiatan, $item, $ruang]) }}" target="_blank" rel="noopener" class="button button-muted">Cetak hadir & berita acara</a>
                                                    @elseif($bolehCetakDokumen && $paketSiap)
                                                        <span class="help-text">Tentukan pengawas utama untuk mencetak.</span>
                                                    @endif
                                                    @if($ruangOperasional)<a href="{{ route('tugas-pengawas-ujian.show', ['ruangUjianCbt' => $ruangOperasional, 'kembali' => 'panitia']) }}" class="button button-muted">Periksa bukti</a>@endif
                                                </div>
                                            </form>

                                            @if($penugasan?->pengawas_utama_pegawai_id || $penugasan?->pengawas_pendamping_pegawai_id)
                                                <details class="supervisor-replacement">
                                                    <summary><span>Ganti pengawas mendadak</span>@if($penugasan->riwayatPergantian->isNotEmpty())<span class="badge badge-warning">{{ $penugasan->riwayatPergantian->count() }} riwayat</span>@endif</summary>
                                                    <p class="supervisor-replacement-copy">Gunakan hanya ketika pengawas berhalangan atau harus diganti. Tugas langsung berpindah dan kedua pengawas mendapat notifikasi.</p>
                                                    <form class="replacement-form" method="POST" action="{{ route('ujian-terpusat.pengawas.ganti', [$kegiatan, $item, $ruang]) }}" onsubmit="return confirm('Ganti pengawas sekarang? Tugas akan langsung berpindah.')">
                                                        @csrf @method('PATCH')
                                                        <div class="field"><label for="peran_ganti_{{ $item->id }}_{{ $ruang->id }}">Posisi yang diganti</label><select id="peran_ganti_{{ $item->id }}_{{ $ruang->id }}" name="peran_pengawas" class="input" required>@if($penugasan?->pengawas_utama_pegawai_id)<option value="utama">Pengawas utama</option>@endif @if($penugasan?->pengawas_pendamping_pegawai_id)<option value="pendamping">Pengawas pendamping</option>@endif</select></div>
                                                        <div class="field"><label for="pengganti_{{ $item->id }}_{{ $ruang->id }}">Pengawas pengganti</label><select id="pengganti_{{ $item->id }}_{{ $ruang->id }}" name="pegawai_pengganti_id" class="input" required><option value="">Pilih pegawai</option>@foreach($pegawai as $orang)<option value="{{ $orang->id }}">{{ $orang->nama_lengkap }}{{ $orang->nip ? ' · '.$orang->nip : '' }}</option>@endforeach</select></div>
                                                        <div class="field"><label for="alasan_ganti_{{ $item->id }}_{{ $ruang->id }}">Alasan penggantian</label><input id="alasan_ganti_{{ $item->id }}_{{ $ruang->id }}" name="alasan" class="input" required maxlength="1000" placeholder="Contoh: sakit atau berhalangan mendadak"></div>
                                                        <button class="button button-primary" type="submit">Ganti sekarang</button>
                                                    </form>

                                                    @if($penugasan->riwayatPergantian->isNotEmpty())
                                                        <div class="replacement-history">
                                                            <h4>Riwayat penggantian</h4>
                                                            @foreach($penugasan->riwayatPergantian->take(5) as $riwayat)
                                                                <div class="replacement-history-item"><strong>{{ $riwayat->labelPeran() }}: {{ $riwayat->pegawaiLama?->nama_lengkap }} → {{ $riwayat->pegawaiBaru?->nama_lengkap }}</strong><span>{{ $riwayat->diganti_pada?->locale('id')->translatedFormat('d F Y, H:i') }} oleh {{ $riwayat->digantiOleh?->nama ?: 'sistem' }} · {{ $riwayat->alasan }}</span></div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </details>
                                            @endif
                                        </div>
                                    @else
                                        <div class="supervisor-row">
                                            <div class="supervisor-room">
                                                <strong>{{ $ruang->nama }}</strong><span>{{ $ruang->lokasi ?: 'Lokasi belum dicatat' }}</span>
                                                @if($ruangOperasional)<div class="supervisor-proof"><span class="badge {{ $kelasStatusBukti }}">{{ $ruangOperasional->labelStatusBukti() }}</span><span class="badge badge-muted">{{ $ruangOperasional->bukti_daftar_hadir_count }} hadir · {{ $ruangOperasional->bukti_berita_acara_count }} BA</span></div>@endif
                                            </div>
                                            <div><span class="help-text">Pengawas utama</span><strong>{{ $penugasan?->pengawasUtama?->nama_lengkap ?: 'Belum ditentukan' }}</strong></div>
                                            <div><span class="help-text">Pendamping</span><strong>{{ $penugasan?->pengawasPendamping?->nama_lengkap ?: 'Tidak ada' }}</strong></div>
                                            @if($bolehCetakDokumen && $paketSiap && $penugasan?->pengawas_utama_pegawai_id)
                                                <div class="actions"><a href="{{ route('ujian-terpusat.dokumen-ruang.cetak', [$kegiatan, $item, $ruang]) }}" target="_blank" rel="noopener" class="button button-muted">Cetak hadir & berita acara</a>@if($ruangOperasional)<a href="{{ route('tugas-pengawas-ujian.show', ['ruangUjianCbt' => $ruangOperasional, 'kembali' => 'panitia']) }}" class="button button-muted">Periksa bukti</a>@endif</div>
                                            @elseif($bolehCetakDokumen && $paketSiap)
                                                <div class="actions"><span class="help-text">Pengawas utama belum ditentukan.</span></div>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>
            </section>
        @empty
            <section class="panel empty-execution"><strong>Belum ada jadwal dalam cakupan Anda.</strong><p class="help-text" style="margin-top:6px;">Panitia perlu menyusun jadwal dan peserta terlebih dahulu.</p></section>
        @endforelse
    </div>

    <div class="central-wizard-actions">
        @if ($halamanHasil)
            <a href="{{ route('ujian-terpusat.pelaksanaan-nilai.index', $kegiatan) }}" class="button button-muted">Kembali ke Pelaksanaan</a>
            <a href="{{ route('ujian-terpusat.show', $kegiatan) }}" class="button button-primary">Selesai</a>
        @else
            <a href="{{ route('paket-soal-terpusat.index', ['kegiatan' => $kegiatan->id]) }}" class="button button-muted">Kembali ke Paket Soal</a>
            <a href="{{ route('ujian-terpusat.nilai-hasil.index', $kegiatan) }}" class="button button-primary">Lanjut ke Nilai & Hasil</a>
        @endif
    </div>
@endsection
