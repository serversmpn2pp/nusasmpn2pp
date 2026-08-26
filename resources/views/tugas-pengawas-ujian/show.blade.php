@extends('layouts.app')

@section('title', 'Bukti Ruang Ujian - NUSA')

@push('styles')
    <style>
        .proof-hero { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(240px,.55fr); overflow:hidden; background:var(--primary); color:#fff; }
        .proof-hero-main,.proof-hero-side { padding:22px 24px; }
        .proof-hero-side { border-left:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.08); }
        .proof-hero h2 { margin:0; color:#fff; font-size:1.4rem; }
        .proof-hero p { margin:7px 0 0; color:rgba(255,255,255,.84); }
        .proof-meta { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:16px; }
        .proof-meta-item { padding:12px 13px; border:1px solid var(--line); border-radius:7px; background:#fff; }
        .proof-meta-item span,.proof-meta-item strong { display:block; }
        .proof-meta-item span { color:var(--muted); font-size:.72rem; font-weight:750; }
        .proof-meta-item strong { margin-top:4px; color:var(--primary-dark); font-size:.9rem; }
        .proof-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; margin-top:18px; }
        .proof-panel { padding:0; overflow:hidden; }
        .proof-panel-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:17px 18px; border-bottom:1px solid var(--line); }
        .proof-panel-head h2 { margin:0; font-size:1.02rem; }
        .proof-panel-head p { margin:5px 0 0; color:var(--muted); font-size:.78rem; }
        .proof-panel-body { padding:17px 18px; }
        .proof-file-list { display:grid; gap:9px; }
        .proof-file { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:10px; align-items:center; padding:11px 12px; border:1px solid var(--line); border-radius:7px; background:#f8fafc; }
        .proof-file strong,.proof-file span { display:block; overflow-wrap:anywhere; }
        .proof-file span { margin-top:3px; color:var(--muted); font-size:.72rem; }
        .proof-file-actions { display:flex; flex-wrap:wrap; gap:6px; }
        .proof-upload { margin-top:14px; padding-top:14px; border-top:1px solid var(--line); }
        .proof-preview { display:none; grid-template-columns:76px minmax(0,1fr); gap:12px; align-items:center; margin-top:10px; padding:10px; border:1px solid #bfd4e8; border-radius:7px; background:var(--primary-soft); }
        .proof-preview.is-visible { display:grid; }
        .proof-preview img { width:76px; height:76px; border-radius:6px; object-fit:cover; background:#fff; }
        .proof-preview .pdf-preview { display:grid; width:76px; height:76px; place-items:center; border-radius:6px; background:#fff; color:var(--primary-dark); font-weight:900; }
        .proof-submit { margin-top:18px; padding:18px; border:1px solid #f0c84b; background:#fff9dc; }
        .proof-submit h2 { margin:0; font-size:1.02rem; }
        .proof-submit p { margin:6px 0 0; color:#5f4b00; }
        .proof-review { margin-top:18px; padding:18px; }
        .proof-review-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
        .proof-review-actions form { flex:1 1 240px; }
        .proof-review-actions .button { width:100%; }
        .proof-note { margin-top:14px; padding:13px 14px; border-left:4px solid var(--accent); background:#fff9dc; }
        .proof-lock { margin-top:14px; padding:12px; border:1px solid var(--line); border-radius:7px; background:#f8fafc; color:var(--muted); font-size:.8rem; }
        @media (max-width:900px) { .proof-meta { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:680px) {
            .proof-hero,.proof-grid { grid-template-columns:1fr; }
            .proof-hero-side { border-top:1px solid rgba(255,255,255,.18); border-left:0; }
            .proof-meta { grid-template-columns:1fr 1fr; }
            .proof-file { grid-template-columns:1fr; }
            .proof-file-actions .button { flex:1; text-align:center; }
        }
        @media (max-width:430px) { .proof-meta { grid-template-columns:1fr; } }
    </style>
@endpush

@section('content')
    @php
        $jadwal = $ruang->jadwalUjianCbt;
        $kegiatan = $jadwal?->kegiatanUjianCbt;
        $daftarHadir = $ruang->buktiRuangUjianCbt->where('jenis', 'daftar_hadir');
        $beritaAcara = $ruang->buktiRuangUjianCbt->where('jenis', 'berita_acara');
        $buktiTerkunci = in_array($ruang->status_bukti, ['menunggu_pemeriksaan', 'valid'], true);
        $kelasStatus = match($ruang->status_bukti) {
            'valid' => 'badge-active',
            'menunggu_pemeriksaan', 'siap_dikirim' => 'badge-warning',
            'perlu_diulang' => 'badge-danger',
            default => 'badge-muted',
        };
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian & Asesmen</p>
            <h1 class="page-title">Bukti ruang ujian</h1>
            <p class="page-subtitle">Foto dokumen yang sudah diisi dan ditandatangani, periksa hasilnya, kemudian kirim kepada panitia.</p>
        </div>
        <div class="actions">
            @if (request('kembali') === 'panitia' && $kegiatan)
                <a href="{{ route('ujian-terpusat.pelaksanaan-nilai.index', $kegiatan) }}" class="button button-muted">Kembali ke pelaksanaan</a>
            @elseif (auth()->user()?->pegawai_id)
                <a href="{{ route('tugas-pengawas-ujian.index') }}" class="button button-muted">Kembali ke tugas</a>
            @endif
        </div>
    </div>

    @if (session('berhasil')) <div class="alert">{{ session('berhasil') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger"><strong>Ada bagian yang perlu diperbaiki.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

    <section class="panel proof-hero">
        <div class="proof-hero-main">
            <p class="eyebrow" style="color:var(--accent);">{{ $kegiatan?->jenisUjianCbt?->nama ?: 'Ujian Terpusat' }}</p>
            <h2>{{ $kegiatan?->nama ?: $ruang->ujianCbt?->nama }}</h2>
            <p>{{ $jadwal?->mataPelajaran?->nama ?: '-' }} · Tingkat {{ $jadwal?->tingkat ?: '-' }} · {{ $ruang->kode }} - {{ $ruang->nama }}</p>
        </div>
        <div class="proof-hero-side">
            <span class="badge {{ $kelasStatus }}">{{ $ruang->labelStatusBukti() }}</span>
            <p>{{ $daftarHadir->count() }} foto/berkas daftar hadir · {{ $beritaAcara->count() }} berita acara</p>
        </div>
    </section>

    <div class="proof-meta">
        <div class="proof-meta-item"><span>Hari dan tanggal</span><strong>{{ $jadwal?->tanggal?->locale('id')->translatedFormat('l, d F Y') ?: '-' }}</strong></div>
        <div class="proof-meta-item"><span>Waktu</span><strong>{{ $jadwal?->labelWaktu() ?: '-' }}</strong></div>
        <div class="proof-meta-item"><span>Pengawas utama</span><strong>{{ $ruang->pengawasUtama?->nama_lengkap ?: '-' }}</strong></div>
        <div class="proof-meta-item"><span>Pendamping</span><strong>{{ $ruang->pengawasPendamping?->nama_lengkap ?: 'Tidak ada' }}</strong></div>
    </div>

    @if ($ruang->status_bukti === 'perlu_diulang')
        <div class="proof-note"><strong>Bukti perlu diulang.</strong><p style="margin:5px 0 0;">{{ $ruang->catatan_pemeriksaan_bukti }}</p></div>
    @elseif ($ruang->status_bukti === 'valid')
        <div class="alert"><strong>Bukti sudah dinyatakan lengkap dan valid.</strong> Diperiksa {{ $ruang->bukti_diperiksa_pada?->format('d-m-Y H:i') }} oleh {{ $ruang->buktiDiperiksaOleh?->nama ?: 'panitia' }}.</div>
    @endif

    <div class="proof-grid">
        @foreach ([
            ['jenis' => 'daftar_hadir', 'judul' => 'Daftar hadir', 'keterangan' => 'Unggah seluruh halaman. Jika ada dua lembar, tambahkan dua foto.', 'daftar' => $daftarHadir],
            ['jenis' => 'berita_acara', 'judul' => 'Berita acara', 'keterangan' => 'Pastikan isian dan tanda tangan pengawas terlihat jelas.', 'daftar' => $beritaAcara],
        ] as $bagian)
            <section class="panel proof-panel">
                <div class="proof-panel-head">
                    <div><h2>{{ $bagian['judul'] }}</h2><p>{{ $bagian['keterangan'] }}</p></div>
                    <span class="badge {{ $bagian['daftar']->isNotEmpty() ? 'badge-active' : 'badge-muted' }}">{{ $bagian['daftar']->count() }} berkas</span>
                </div>
                <div class="proof-panel-body">
                    <div class="proof-file-list">
                        @forelse ($bagian['daftar'] as $bukti)
                            <article class="proof-file">
                                <div>
                                    <strong>{{ $bukti->nama_file_asli }}</strong>
                                    <span>{{ $bukti->ukuranRingkas() }} · {{ $bukti->diunggah_pada?->format('d-m-Y H:i') }} · {{ $bukti->diunggahOleh?->nama ?: 'Pengguna NUSA' }}</span>
                                </div>
                                <div class="proof-file-actions">
                                    <a href="{{ route('tugas-pengawas-ujian.bukti.show', [$ruang, $bukti]) }}" target="_blank" rel="noopener" class="button button-muted button-sm">Lihat</a>
                                    @if ($bolehUnggah && ! $buktiTerkunci)
                                        <form method="POST" action="{{ route('tugas-pengawas-ujian.bukti.destroy', [$ruang, $bukti]) }}" onsubmit="return confirm('Hapus bukti ini?')">
                                            @csrf @method('DELETE')
                                            <button class="button button-danger button-sm" type="submit">Hapus</button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="empty-state">Belum ada berkas.</div>
                        @endforelse
                    </div>

                    @if ($bolehUnggah && ! $buktiTerkunci)
                        <form class="proof-upload" method="POST" enctype="multipart/form-data" action="{{ route('tugas-pengawas-ujian.bukti.store', $ruang) }}" data-proof-form>
                            @csrf
                            <input type="hidden" name="jenis" value="{{ $bagian['jenis'] }}">
                            <div class="field">
                                <label for="berkas_{{ $bagian['jenis'] }}">Ambil foto atau pilih berkas</label>
                                <input id="berkas_{{ $bagian['jenis'] }}" name="berkas" type="file" class="input" accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf" capture="environment" required data-proof-input>
                                <p class="help-text">JPG, PNG, WebP, atau PDF. Maksimal 10 MB per unggahan.</p>
                            </div>
                            <div class="proof-preview" data-proof-preview>
                                <div data-proof-visual></div>
                                <div><strong data-proof-name></strong><span class="help-text" data-proof-size></span></div>
                            </div>
                            <button class="button button-primary" type="submit" style="margin-top:11px;" disabled data-proof-submit>Unggah bukti</button>
                        </form>
                    @elseif ($buktiTerkunci)
                        <div class="proof-lock">Bukti dikunci karena sudah dikirim kepada panitia.</div>
                    @endif
                </div>
            </section>
        @endforeach
    </div>

    @if ($bolehUnggah && ! $buktiTerkunci)
        <section class="panel proof-submit">
            <h2>Kirim bukti kepada panitia</h2>
            <p>Tombol aktif setelah minimal satu daftar hadir dan satu berita acara tersedia. Setelah dikirim, berkas tidak dapat diubah sampai panitia selesai memeriksa.</p>
            <form method="POST" action="{{ route('tugas-pengawas-ujian.kirim', $ruang) }}" style="margin-top:13px;" onsubmit="return confirm('Kirim seluruh bukti ruang kepada panitia?')">
                @csrf @method('PATCH')
                <button class="button button-primary" type="submit" @disabled($daftarHadir->isEmpty() || $beritaAcara->isEmpty())>Kirim ke panitia</button>
            </form>
        </section>
    @endif

    @if ($bolehMemeriksa)
        <section class="panel proof-review">
            <div class="page-header" style="margin-bottom:0;">
                <div><h2 class="panel-title">Pemeriksaan panitia</h2><p class="help-text">Pastikan seluruh halaman terbaca, sesuai ruang, dan sudah ditandatangani.</p></div>
                <span class="badge {{ $kelasStatus }}">{{ $ruang->labelStatusBukti() }}</span>
            </div>
            @if ($ruang->status_bukti === 'menunggu_pemeriksaan')
                <div class="field" style="margin-top:14px;"><label for="catatan_pemeriksaan">Catatan jika perlu diulang</label><textarea id="catatan_pemeriksaan" name="catatan" class="textarea" form="form_perlu_diulang" placeholder="Contoh: Foto halaman kedua buram, mohon ambil ulang."></textarea></div>
                <div class="proof-review-actions">
                    <form method="POST" action="{{ route('tugas-pengawas-ujian.periksa', $ruang) }}" onsubmit="return confirm('Nyatakan seluruh bukti ruang ini valid?')">
                        @csrf @method('PATCH')<input type="hidden" name="hasil" value="valid"><button class="button button-primary" type="submit">Nyatakan valid</button>
                    </form>
                    <form id="form_perlu_diulang" method="POST" action="{{ route('tugas-pengawas-ujian.periksa', $ruang) }}">
                        @csrf @method('PATCH')<input type="hidden" name="hasil" value="perlu_diulang"><button class="button button-danger" type="submit">Minta foto ulang</button>
                    </form>
                </div>
            @else
                <p class="proof-lock">Pemeriksaan tersedia setelah pengawas menekan tombol <strong>Kirim ke panitia</strong>.</p>
            @endif
        </section>
    @endif
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-proof-form]').forEach((form) => {
            const input = form.querySelector('[data-proof-input]');
            const preview = form.querySelector('[data-proof-preview]');
            const visual = form.querySelector('[data-proof-visual]');
            const name = form.querySelector('[data-proof-name]');
            const size = form.querySelector('[data-proof-size]');
            const submit = form.querySelector('[data-proof-submit]');
            let objectUrl = null;

            input.addEventListener('change', () => {
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                const file = input.files[0];
                visual.replaceChildren();
                preview.classList.remove('is-visible');
                submit.disabled = true;
                if (!file) return;

                const terlaluBesar = file.size > 10 * 1024 * 1024;
                name.textContent = file.name;
                size.textContent = `${(file.size / 1024 / 1024).toFixed(1)} MB${terlaluBesar ? ' - melebihi batas 10 MB' : ''}`;
                size.style.color = terlaluBesar ? '#b42318' : '';

                if (file.type.startsWith('image/')) {
                    objectUrl = URL.createObjectURL(file);
                    const image = document.createElement('img');
                    image.src = objectUrl;
                    image.alt = 'Pratinjau bukti';
                    visual.appendChild(image);
                } else {
                    const pdf = document.createElement('div');
                    pdf.className = 'pdf-preview';
                    pdf.textContent = 'PDF';
                    visual.appendChild(pdf);
                }

                preview.classList.add('is-visible');
                submit.disabled = terlaluBesar;
            });
        });
    </script>
@endpush
