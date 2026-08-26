@extends('layouts.app')

@section('title', 'Susun Paket Soal Terpusat - NUSA')

@section('content')
    @php
        $jumlahDipilih = $soalDipilih->count();
        $totalBobot = $soalDipilih->sum(fn ($item) => (float) $item->bobot);
        $paketSiap = $paket && in_array($paket->status, ['terjadwal', 'berlangsung', 'selesai'], true);
    @endphp

    <style>
        .package-context { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(260px,.6fr); overflow:hidden; margin-bottom:18px; background:var(--primary); color:#fff; }
        .package-context-main, .package-context-side { padding:20px 22px; }
        .package-context-side { border-left:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.08); }
        .package-context h2 { margin:2px 0 0; color:#fff; font-size:1.25rem; }
        .package-context p { margin:6px 0 0; color:rgba(255,255,255,.8); }
        .package-context .quick-facts { margin-top:14px; }
        .package-context .quick-facts div { border-color:rgba(255,255,255,.18); background:rgba(255,255,255,.07); }
        .package-context .quick-facts dt { color:rgba(255,255,255,.7); }
        .package-context .quick-facts dd { color:#fff; }
        .package-auto { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:18px; }
        .package-auto-item { padding:13px 14px; border:1px solid var(--line); border-radius:7px; background:#fff; }
        .package-auto-item strong, .package-auto-item span { display:block; }
        .package-auto-item strong { color:var(--primary-dark); font-size:.78rem; }
        .package-auto-item span { margin-top:4px; color:var(--muted); font-size:.72rem; font-weight:700; }
        .shuffle-settings { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:12px; margin-bottom:18px; padding:16px; }
        .shuffle-setting { display:grid; grid-template-columns:auto minmax(0,1fr); gap:11px; align-items:start; padding:13px 14px; border:1px solid var(--line); border-radius:7px; background:#f8fafc; cursor:pointer; }
        .shuffle-setting:has(input:checked) { border-color:#8eb8df; background:var(--primary-soft); box-shadow:inset 4px 0 0 var(--primary); }
        .shuffle-setting input { width:19px; height:19px; margin-top:2px; accent-color:var(--primary); }
        .shuffle-setting strong,.shuffle-setting span { display:block; }
        .shuffle-setting strong { color:var(--primary-dark); font-size:.82rem; }
        .shuffle-setting span { margin-top:4px; color:var(--muted); font-size:.74rem; line-height:1.45; }
        .question-toolbar { display:grid; grid-template-columns:minmax(220px,1fr) 210px 210px auto; gap:10px; align-items:end; padding:15px 16px; border-bottom:1px solid var(--line); background:#f8fafc; }
        .question-toolbar-actions { display:flex; gap:7px; }
        .question-list { display:grid; }
        .question-row { display:grid; grid-template-columns:28px minmax(0,1fr) 125px 120px 96px 105px; gap:14px; align-items:start; padding:15px 16px; border-bottom:1px solid var(--line); }
        .question-row:last-child { border-bottom:0; }
        .question-row.is-selected { background:#f5f9fd; box-shadow:inset 4px 0 0 var(--primary); }
        .question-row.is-hidden { display:none; }
        .question-check { width:18px; height:18px; margin-top:3px; accent-color:var(--primary); }
        .question-main strong, .question-main span { display:block; }
        .question-main strong { color:var(--primary-dark); font-size:.8rem; }
        .question-main span { margin-top:5px; color:var(--ink); font-size:.8rem; line-height:1.55; }
        .question-main small { display:block; margin-top:7px; color:var(--muted); font-size:.7rem; font-weight:700; }
        .question-meta strong, .question-meta span { display:block; }
        .question-meta strong { font-size:.75rem; }
        .question-meta span { margin-top:4px; color:var(--muted); font-size:.7rem; font-weight:700; }
        .question-preview-action .button { width:100%; min-width:0; }
        .question-weight label { display:block; margin-bottom:5px; color:var(--muted); font-size:.68rem; font-weight:800; }
        .package-preview-dialog { width:min(760px,calc(100% - 28px)); max-height:calc(100vh - 32px); overflow:hidden; border:0; border-radius:8px; padding:0; box-shadow:0 24px 70px rgba(15,53,92,.26); }
        .package-preview-dialog::backdrop { background:rgba(15,35,55,.58); }
        .package-preview-head { display:flex; align-items:center; justify-content:space-between; gap:12px; border-bottom:1px solid var(--line); padding:15px 18px; }
        .package-preview-head h2 { margin:0; font-size:1rem; }
        .package-preview-body { max-height:calc(100vh - 112px); overflow-y:auto; padding:18px; }
        .package-preview-meta { display:flex; flex-wrap:wrap; gap:7px; margin-bottom:14px; }
        .package-preview-stimulus { margin-bottom:14px; border-left:4px solid var(--accent); background:var(--accent-soft); padding:12px 14px; color:var(--ink); font-size:.82rem; line-height:1.6; white-space:pre-line; }
        .package-preview-question { margin:15px 0; color:var(--dark); font-size:1rem; font-weight:800; line-height:1.65; white-space:pre-line; }
        .package-preview-options { display:grid; gap:8px; }
        .package-preview-option { display:grid; grid-template-columns:30px minmax(0,1fr) auto; gap:9px; align-items:start; border:1px solid var(--line); border-radius:7px; padding:10px 12px; }
        .package-preview-option > strong { color:var(--primary-dark); }
        .package-preview-option.is-answer { border-color:#86c7a4; background:#f0fbf5; }
        .package-preview-answer { margin-top:14px; border:1px solid #bfd5ea; border-radius:7px; background:var(--primary-soft); padding:12px 14px; }
        .package-preview-answer strong,.package-preview-answer span { display:block; }
        .package-preview-answer strong { color:var(--primary-dark); font-size:.74rem; }
        .package-preview-answer span { margin-top:5px; color:var(--ink); font-size:.82rem; line-height:1.55; white-space:pre-line; }
        .package-preview-notes { margin-top:14px; border-top:1px solid var(--line); padding-top:14px; }
        .package-preview-notes strong,.package-preview-notes span { display:block; }
        .package-preview-notes strong { color:var(--primary-dark); font-size:.74rem; }
        .package-preview-notes span { margin-top:5px; color:var(--muted); font-size:.8rem; line-height:1.55; white-space:pre-line; }
        .package-form-actions { position:sticky; bottom:0; display:flex; justify-content:space-between; gap:12px; align-items:center; margin-top:16px; padding:13px 15px; border:1px solid var(--line); border-radius:7px; background:rgba(255,255,255,.96); box-shadow:0 -8px 24px rgba(15,53,92,.08); z-index:3; }
        .package-form-summary { display:flex; flex-wrap:wrap; gap:8px; }
        .package-form-summary span { padding:7px 9px; border-radius:6px; background:var(--primary-soft); color:var(--primary-dark); font-size:.74rem; font-weight:800; }
        .package-form-buttons { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:8px; }
        .question-empty { padding:34px 18px; text-align:center; color:var(--muted); }
        @media (max-width:1000px) {
            .package-auto { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .question-toolbar { grid-template-columns:1fr 1fr; }
            .question-toolbar-actions { grid-column:1 / -1; }
            .question-row { grid-template-columns:28px minmax(0,1fr) 96px 105px; }
            .question-type-meta { grid-column:2; grid-row:2; }
            .question-status-meta { grid-column:2; grid-row:3; }
            .question-preview-action { grid-column:3; grid-row:1; }
            .question-weight { grid-column:4; grid-row:1 / span 3; }
        }
        @media (max-width:680px) {
            .package-context { grid-template-columns:1fr; }
            .package-context-side { border-top:1px solid rgba(255,255,255,.18); border-left:0; }
            .package-auto, .question-toolbar, .shuffle-settings { grid-template-columns:1fr; }
            .question-toolbar-actions { grid-column:auto; }
            .question-toolbar-actions .button { flex:1 1 0; }
            .question-row { grid-template-columns:24px minmax(0,1fr); gap:10px; padding:14px; }
            .question-type-meta,.question-status-meta,.question-preview-action,.question-weight { grid-column:2; grid-row:auto; }
            .question-preview-action .button { width:auto; }
            .question-weight { max-width:150px; }
            .package-preview-head { display:grid; grid-template-columns:minmax(0,1fr) auto; }
            .package-preview-head .button { width:auto; min-width:74px; }
            .package-preview-option { grid-template-columns:26px minmax(0,1fr); }
            .package-preview-option .badge { grid-column:2; justify-self:start; }
            .package-form-actions { position:static; display:grid; }
            .package-form-buttons { display:grid; grid-template-columns:1fr; }
            .package-form-buttons .button { width:100%; }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian Terpusat · Tahap 8</p>
            <h1 class="page-title">{{ $bolehKelola ? 'Susun paket soal' : 'Detail paket soal' }}</h1>
            <p class="page-subtitle">{{ $bolehKelola ? 'Centang soal yang akan dikerjakan siswa. Informasi lainnya sudah diambil dari jadwal.' : 'Panitia dapat memantau isi dan kesiapan paket tanpa mengubah soal.' }}</p>
        </div>
        <div class="actions">
            @if ($bolehKelola)<a href="{{ route('soal-cbt.index') }}" class="button button-muted">Buka Bank Soal</a>@endif
            @if ($paketSiap)<a href="{{ route('ujian-terpusat.pelaksanaan-nilai.index', $jadwal->kegiatan_ujian_cbt_id) }}" class="button button-muted">Pelaksanaan ujian</a>@endif
            <a href="{{ route('paket-soal-terpusat.index', ['kegiatan' => $jadwal->kegiatan_ujian_cbt_id]) }}" class="button button-primary">Daftar paket</a>
        </div>
    </div>

    @if (session('berhasil')) <div class="alert">{{ session('berhasil') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-danger"><strong>Ada bagian yang perlu diperbaiki.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if ($jadwal->kegiatanUjianCbt?->dapatDiaksesOleh(auth()->user()))
        @include('ujian-terpusat.partials.alur', ['kegiatan' => $jadwal->kegiatanUjianCbt, 'tahapAktif' => 8])
    @endif

    <section class="panel package-context">
        <div class="package-context-main">
            <p class="eyebrow" style="color:var(--accent);">{{ $jadwal->kegiatanUjianCbt?->nama }}</p>
            <h2>{{ $jadwal->mataPelajaran?->nama }} · Tingkat {{ $jadwal->tingkat }}</h2>
            <p>{{ $jadwal->tanggal?->locale('id')->translatedFormat('l, d F Y') }} · {{ $jadwal->sesiKegiatanUjianCbt?->nama }} {{ $jadwal->sesiKegiatanUjianCbt?->labelWaktu() }}</p>
            <dl class="quick-facts">
                <div><dt>Kelas</dt><dd>{{ $jadwal->kelas->pluck('nama')->join(', ') }}</dd></div>
                <div><dt>Tahun pelajaran</dt><dd>{{ $jadwal->kegiatanUjianCbt?->tahunPelajaran?->nama }}</dd></div>
            </dl>
        </div>
        <div class="package-context-side">
            <span class="badge {{ $paketSiap ? 'badge-active' : ($paket ? 'badge-warning' : 'badge-muted') }}">{{ $paketSiap ? 'Paket siap' : ($paket ? 'Masih draf' : 'Belum disusun') }}</span>
            <dl class="quick-facts">
                <div><dt>Soal terpilih</dt><dd data-selected-count>{{ $jumlahDipilih }}</dd></div>
                <div><dt>Total bobot</dt><dd data-selected-weight>{{ number_format($totalBobot, 2, ',', '.') }}</dd></div>
                @if ($paket?->token)<div><dt>Token otomatis</dt><dd>{{ $paket->token }}</dd></div>@endif
            </dl>
        </div>
    </section>

    <div class="package-auto" aria-label="Pengaturan otomatis paket">
        <div class="package-auto-item"><strong>Jumlah soal</strong><span>Mengikuti jumlah soal yang dicentang.</span></div>
        <div class="package-auto-item"><strong>Durasi & peserta</strong><span>Mengikuti sesi serta kelas pada jadwal.</span></div>
        <div class="package-auto-item"><strong>Pengacakan</strong><span>Soal: {{ ($paket?->acak_soal ?? true) ? 'diacak' : 'tetap' }} · Pilihan: {{ ($paket?->acak_jawaban ?? true) ? 'diacak' : 'tetap' }}.</span></div>
        <div class="package-auto-item"><strong>Komponen nilai</strong><span>Dibuat otomatis saat paket diterbitkan.</span></div>
    </div>

    @if ($bolehKelola)
        <form action="{{ route('paket-soal-terpusat.update', $jadwal) }}" method="POST" data-package-form>
            @csrf
            @method('PUT')

            <section class="panel shuffle-settings" aria-labelledby="pengaturan-pengacakan">
                <div style="grid-column:1 / -1;">
                    <h2 id="pengaturan-pengacakan" class="panel-title">Pengacakan untuk siswa</h2>
                    <p class="help-text" style="margin-top:4px;">Pengaturan berlaku ketika siswa membuka paket. Urutan tidak berubah saat halaman direfresh.</p>
                </div>
                <label class="shuffle-setting">
                    <input type="hidden" name="acak_soal" value="0">
                    <input type="checkbox" name="acak_soal" value="1" @checked(old('acak_soal', $paket?->acak_soal ?? true))>
                    <span><strong>Acak urutan soal</strong><span>Setiap siswa memperoleh urutan soal yang berbeda.</span></span>
                </label>
                <label class="shuffle-setting">
                    <input type="hidden" name="acak_jawaban" value="0">
                    <input type="checkbox" name="acak_jawaban" value="1" @checked(old('acak_jawaban', $paket?->acak_jawaban ?? true))>
                    <span><strong>Acak pilihan jawaban</strong><span>Pilihan A-D pada PG dan PG kompleks disusun berbeda untuk setiap siswa.</span></span>
                </label>
            </section>
    @endif

    <section class="panel">
        @if ($bolehKelola && $soal->isNotEmpty())
            <div class="question-toolbar">
                <div class="field"><label for="cari_soal_paket">Cari soal</label><input id="cari_soal_paket" class="input" placeholder="Kode, topik, atau isi soal" data-question-search></div>
                <div class="field"><label for="jenis_soal_paket">Jenis soal</label><select id="jenis_soal_paket" class="input" data-question-type><option value="">Semua jenis</option>@foreach($daftarJenisSoal as $kode => $label)<option value="{{ $kode }}">{{ $label }}</option>@endforeach</select></div>
                <div class="field"><label for="kesulitan_soal_paket">Kesulitan</label><select id="kesulitan_soal_paket" class="input" data-question-difficulty><option value="">Semua kesulitan</option>@foreach($daftarKesulitan as $kode => $label)<option value="{{ $kode }}">{{ $label }}</option>@endforeach</select></div>
                <div class="question-toolbar-actions"><button type="button" class="button button-muted" data-select-visible>Pilih tampil</button><button type="button" class="button button-muted" data-clear-selection>Kosongkan</button></div>
            </div>
        @endif

        <div class="question-list" data-question-list>
            @forelse ($soal as $item)
                @php
                    $relasi = $soalDipilih->get($item->id);
                    $dipilih = filter_var(old("soal.{$item->id}.dipilih", (bool) $relasi), FILTER_VALIDATE_BOOLEAN);
                    $bobot = old("soal.{$item->id}.bobot", $relasi?->bobot ?? $item->skor_maksimal);
                    $bisaDipilih = $item->aktif && $item->status === 'siap';
                @endphp
                <div class="question-row {{ $dipilih ? 'is-selected' : '' }}" data-question-row data-type="{{ $item->jenis_soal }}" data-difficulty="{{ $item->tingkat_kesulitan }}" data-search="{{ mb_strtolower($item->kode.' '.$item->topik.' '.$item->materi.' '.strip_tags($item->pertanyaan)) }}">
                    @if ($bolehKelola)
                        <input type="hidden" name="soal[{{ $item->id }}][dipilih]" value="0">
                        <input id="soal_{{ $item->id }}" class="question-check" type="checkbox" name="soal[{{ $item->id }}][dipilih]" value="1" @checked($dipilih) @disabled(! $bisaDipilih) data-question-check>
                    @else
                        <span class="question-check" aria-hidden="true">✓</span>
                    @endif
                    <label class="question-main" @if($bolehKelola) for="soal_{{ $item->id }}" @endif>
                        <strong>{{ $item->kode }}</strong>
                        <span>{{ str(strip_tags($item->pertanyaan))->limit(220) }}</span>
                        <small>{{ $item->topik ?: ($item->materi ?: 'Tanpa topik') }}</small>
                    </label>
                    <div class="question-meta question-type-meta"><strong>{{ $item->labelJenis() }}</strong><span>{{ $item->labelKesulitan() }}</span></div>
                    <div class="question-meta question-status-meta"><strong>{{ $item->labelKategori() }}</strong><span>{{ $bisaDipilih ? 'Siap digunakan' : 'Tidak aktif' }}</span></div>
                    <div class="question-preview-action">
                        <button type="button" class="button button-muted" data-open-package-preview="{{ $item->id }}" data-preview-code="{{ $item->kode }}">Pratinjau</button>
                    </div>
                    <div class="question-weight">
                        <label for="bobot_{{ $item->id }}">Bobot</label>
                        @if ($bolehKelola)<input id="bobot_{{ $item->id }}" name="soal[{{ $item->id }}][bobot]" type="number" min="0.25" max="100" step="0.25" value="{{ $bobot }}" class="input" data-question-weight @disabled(! $dipilih)>@else<strong>{{ number_format((float) $relasi?->bobot, 2, ',', '.') }}</strong>@endif
                    </div>
                </div>
                <template data-package-preview-template="{{ $item->id }}">
                    <article>
                        <div class="package-preview-meta">
                            <span class="badge badge-muted">{{ $item->labelJenis() }}</span>
                            <span class="badge badge-muted">{{ $item->labelKesulitan() }}</span>
                            <span class="badge badge-muted">{{ $item->topik ?: ($item->materi ?: 'Tanpa topik') }}</span>
                        </div>

                        @if ($item->stimulus)
                            <div class="package-preview-stimulus">{{ $item->stimulus }}</div>
                        @endif

                        <x-media-soal :media="$item->media" />
                        <div class="package-preview-question">{{ $item->pertanyaan }}</div>

                        @if (isset($item->opsi['pilihan']))
                            @php $jawabanPilihan = $item->kunci_jawaban['jawaban'] ?? null; @endphp
                            <div class="package-preview-options">
                                @foreach ($item->opsi['pilihan'] as $kode => $isi)
                                    @php $jawabanBenar = (is_string($jawabanPilihan) && $jawabanPilihan === $kode) || (is_array($jawabanPilihan) && in_array($kode, $jawabanPilihan, true)); @endphp
                                    <div class="package-preview-option {{ $jawabanBenar ? 'is-answer' : '' }}">
                                        <strong>{{ $kode }}</strong>
                                        <span>{{ $isi }}</span>
                                        @if ($jawabanBenar)<span class="badge badge-active">Kunci</span>@endif
                                    </div>
                                @endforeach
                            </div>
                        @elseif (isset($item->opsi['pernyataan']))
                            <div class="package-preview-options">
                                @foreach ($item->opsi['pernyataan'] as $pernyataan)
                                    @php $jawabanBenarSalah = (bool) ($item->kunci_jawaban['jawaban'][$pernyataan['nomor']] ?? false); @endphp
                                    <div class="package-preview-option">
                                        <strong>{{ $pernyataan['nomor'] }}</strong>
                                        <span>{{ $pernyataan['teks'] }}</span>
                                        <span class="badge {{ $jawabanBenarSalah ? 'badge-active' : 'badge-muted' }}">{{ $jawabanBenarSalah ? 'Benar' : 'Salah' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @elseif (isset($item->opsi['pasangan']))
                            <div class="package-preview-options">
                                @foreach ($item->opsi['pasangan'] as $pasangan)
                                    <div class="package-preview-option is-answer">
                                        <strong>{{ $pasangan['nomor'] }}</strong>
                                        <span>{{ $pasangan['kiri'] }} → {{ $pasangan['kanan'] }}</span>
                                        <span class="badge badge-active">Pasangan</span>
                                    </div>
                                @endforeach
                            </div>
                        @elseif ($item->jenis_soal === 'upload_file')
                            <div class="package-preview-answer"><strong>Bentuk jawaban</strong><span>Siswa mengunggah berkas jawaban.</span></div>
                        @else
                            <div class="package-preview-answer"><strong>Kunci jawaban</strong><span>{{ filled($item->kunci_jawaban['jawaban'] ?? null) ? $item->kunci_jawaban['jawaban'] : 'Diperiksa manual oleh guru.' }}</span></div>
                        @endif

                        @if ($item->pembahasan)
                            <div class="package-preview-notes"><strong>Pembahasan</strong><span>{{ $item->pembahasan }}</span></div>
                        @endif
                    </article>
                </template>
            @empty
                <div class="question-empty"><strong>{{ $bolehKelola ? 'Belum ada soal siap untuk mapel dan tingkat ini.' : 'Paket belum memiliki soal.' }}</strong>@if($bolehKelola)<p class="help-text" style="margin-top:6px;">Tambahkan atau ubah soal menjadi Siap digunakan melalui Bank Soal.</p>@endif</div>
            @endforelse
        </div>
    </section>

    @if ($bolehKelola)
        <div class="package-form-actions">
            <div class="package-form-summary"><span><b data-selected-count>{{ $jumlahDipilih }}</b> soal dipilih</span><span>Bobot <b data-selected-weight>{{ number_format($totalBobot, 2, ',', '.') }}</b></span></div>
            <div class="package-form-buttons">
                <a href="{{ route('paket-soal-terpusat.index', ['kegiatan' => $jadwal->kegiatan_ujian_cbt_id]) }}" class="button button-muted">Batal</a>
                @if ($paketSiap)
                    <button type="submit" name="aksi" value="draf" class="button button-muted">Kembalikan ke draf</button>
                    <button type="submit" name="aksi" value="simpan" class="button button-primary">Simpan perubahan</button>
                @else
                    <button type="submit" name="aksi" value="draf" class="button button-muted">Simpan draf</button>
                    <button type="submit" name="aksi" value="terbitkan" class="button button-primary">Terbitkan paket</button>
                @endif
            </div>
        </div>
        </form>
    @endif

    <dialog class="package-preview-dialog" data-package-preview-dialog aria-labelledby="package-preview-title">
        <div class="package-preview-head">
            <h2 id="package-preview-title" data-package-preview-title>Pratinjau soal</h2>
            <button type="button" class="button button-muted" data-close-package-preview>Tutup</button>
        </div>
        <div class="package-preview-body" data-package-preview-body></div>
    </dialog>

    <script>
        (() => {
            const dialog = document.querySelector('[data-package-preview-dialog]');
            const body = dialog?.querySelector('[data-package-preview-body]');
            const title = dialog?.querySelector('[data-package-preview-title]');

            document.querySelectorAll('[data-open-package-preview]').forEach((button) => {
                button.addEventListener('click', () => {
                    const template = document.querySelector(`[data-package-preview-template="${button.dataset.openPackagePreview}"]`);
                    if (!dialog || !body || !template) return;

                    body.replaceChildren(template.content.cloneNode(true));
                    if (title) title.textContent = `Pratinjau ${button.dataset.previewCode || 'soal'}`;

                    window.renderRumusSoal?.(body);
                    if (typeof dialog.showModal === 'function') dialog.showModal();
                    else dialog.setAttribute('open', '');
                });
            });

            dialog?.querySelector('[data-close-package-preview]')?.addEventListener('click', () => dialog.close());
            dialog?.addEventListener('click', (event) => {
                if (event.target === dialog) dialog.close();
            });
        })();
    </script>

    @if ($bolehKelola)
        <script>
            (() => {
                const rows = [...document.querySelectorAll('[data-question-row]')];
                const search = document.querySelector('[data-question-search]');
                const type = document.querySelector('[data-question-type]');
                const difficulty = document.querySelector('[data-question-difficulty]');
                const counters = document.querySelectorAll('[data-selected-count]');
                const weights = document.querySelectorAll('[data-selected-weight]');

                const refreshSummary = () => {
                    const selected = rows.filter((row) => row.querySelector('[data-question-check]')?.checked);
                    const total = selected.reduce((sum, row) => sum + Number(row.querySelector('[data-question-weight]')?.value || 0), 0);
                    counters.forEach((item) => item.textContent = selected.length);
                    weights.forEach((item) => item.textContent = total.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    rows.forEach((row) => {
                        const check = row.querySelector('[data-question-check]');
                        const weight = row.querySelector('[data-question-weight]');
                        row.classList.toggle('is-selected', Boolean(check?.checked));
                        if (weight) weight.disabled = ! check?.checked;
                    });
                };

                const applyFilters = () => {
                    const keyword = (search?.value || '').trim().toLowerCase();
                    rows.forEach((row) => {
                        const visible = (! keyword || row.dataset.search.includes(keyword))
                            && (! type?.value || row.dataset.type === type.value)
                            && (! difficulty?.value || row.dataset.difficulty === difficulty.value);
                        row.classList.toggle('is-hidden', ! visible);
                    });
                };

                document.querySelectorAll('[data-question-check], [data-question-weight]').forEach((input) => input.addEventListener('change', refreshSummary));
                [search, type, difficulty].forEach((input) => input?.addEventListener(input === search ? 'input' : 'change', applyFilters));
                document.querySelector('[data-select-visible]')?.addEventListener('click', () => {
                    rows.filter((row) => ! row.classList.contains('is-hidden')).forEach((row) => {
                        const check = row.querySelector('[data-question-check]');
                        if (check && ! check.disabled) check.checked = true;
                    });
                    refreshSummary();
                });
                document.querySelector('[data-clear-selection]')?.addEventListener('click', () => {
                    rows.forEach((row) => {
                        const check = row.querySelector('[data-question-check]');
                        if (check && ! check.disabled) check.checked = false;
                    });
                    refreshSummary();
                });

                applyFilters();
                refreshSummary();
            })();
        </script>
    @endif
@endsection
