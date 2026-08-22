@php
    $soalCbt = $soalCbt ?? null;
    $nilaiAwal = $nilaiAwal ?? [];
    $nilai = fn (string $field, mixed $default = '') => old($field, $soalCbt?->{$field} ?? ($nilaiAwal[$field] ?? $default));
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
    $sedangEdit = filled($soalCbt);

    $mataPelajaranId = (int) old('mata_pelajaran_id', $soalCbt?->mata_pelajaran_id ?? ($konteksTerpilih['mata_pelajaran_id'] ?? 0));
    $tingkatTerpilih = (int) old('tingkat', $soalCbt?->tingkat ?? ($konteksTerpilih['tingkat'] ?? 0));
    $kunciKonteks = $mataPelajaranId && $tingkatTerpilih ? $mataPelajaranId . '-' . $tingkatTerpilih : '';
    $konteksAktif = collect($daftarKonteks ?? [])->firstWhere('kunci', $kunciKonteks) ?? $konteksTerpilih;

    $jenisTerpilih = $nilai('jenis_soal', 'pilihan_ganda');
    $jenisUtama = collect($daftarJenisSoal)->only(['pilihan_ganda', 'pilihan_ganda_kompleks', 'benar_salah', 'isian_singkat']);
    $jenisLainnya = collect($daftarJenisSoal)->except($jenisUtama->keys()->all());
    $deskripsiJenis = [
        'pilihan_ganda' => 'Satu jawaban benar dari pilihan A-D.',
        'pilihan_ganda_kompleks' => 'Lebih dari satu jawaban dapat benar.',
        'benar_salah' => 'Nilai benar atau salah untuk beberapa pernyataan.',
        'isian_singkat' => 'Jawaban berupa kata atau kalimat pendek.',
        'menjodohkan' => 'Pasangkan pernyataan dengan jawaban.',
        'uraian' => 'Jawaban diperiksa manual memakai rubrik.',
        'numerik' => 'Jawaban berupa angka atau hasil perhitungan.',
        'upload_file' => 'Unggah hasil tugas pada Asesmen Kelas.',
    ];

    $opsiPilihan = old('opsi', $soalCbt?->opsi['pilihan'] ?? ['A' => '', 'B' => '', 'C' => '', 'D' => '']);
    $opsiPilihan = array_merge(['A' => '', 'B' => '', 'C' => '', 'D' => ''], $opsiPilihan ?: []);
    $jawaban = $soalCbt?->kunci_jawaban['jawaban'] ?? null;
    $kunciPg = old('kunci_pg', is_string($jawaban) ? $jawaban : '');
    $kunciPgk = old('kunci_pgk', is_array($jawaban) ? $jawaban : []);

    $pernyataanAwal = collect($soalCbt?->opsi['pernyataan'] ?? [])->pluck('teks')->all();
    $jawabanBsAwal = collect($soalCbt?->kunci_jawaban['jawaban'] ?? [])->map(fn ($value) => $value ? 'benar' : 'salah')->values()->all();
    $pernyataan = array_pad(old('pernyataan', $pernyataanAwal), 4, '');
    $jawabanBs = array_pad(old('jawaban_bs', $jawabanBsAwal), 4, 'benar');

    $pasanganAwal = collect($soalCbt?->opsi['pasangan'] ?? []);
    $pasanganKiri = array_pad(old('pasangan_kiri', $pasanganAwal->pluck('kiri')->all()), 4, '');
    $pasanganKanan = array_pad(old('pasangan_kanan', $pasanganAwal->pluck('kanan')->all()), 4, '');
    $kunciTeks = old('kunci_teks', is_string($jawaban) ? $jawaban : '');
    $rubrikTeks = old('rubrik_teks', $soalCbt?->rubrik['catatan'] ?? '');
    $mediaSoal = $soalCbt?->media ?? [];
    $gambarSoal = data_get($mediaSoal, 'gambar');
    $gambarSoalUrl = filled(data_get($gambarSoal, 'path')) ? \Illuminate\Support\Facades\Storage::disk('public')->url(data_get($gambarSoal, 'path')) : '';
    $tabelSoal = data_get($mediaSoal, 'tabel.baris', []);
    $mediaTabel = old('media_tabel', $tabelSoal === [] ? '' : json_encode($tabelSoal, JSON_UNESCAPED_UNICODE));
    $jumlahBarisTabel = max(2, min(10, count($tabelSoal) ?: 3));
    $jumlahKolomTabel = max(2, min(8, count($tabelSoal[0] ?? []) ?: 3));
    $punyaMedia = filled($gambarSoalUrl) || filled($mediaTabel) || filled(data_get($mediaSoal, 'rumus.latex'));

    $bukaPengaturanTambahan = $errors->hasAny([
        'tingkat_kesulitan', 'kategori', 'materi', 'tujuan_pembelajaran',
        'stimulus', 'pembahasan', 'rubrik_teks',
    ]);
@endphp

<style>
    .question-context {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(280px, 420px);
        gap: 20px;
        align-items: center;
        margin-bottom: 18px;
    }

    .question-context-copy h2 { margin: 3px 0 5px; font-size: 1rem; }
    .question-context-copy p { margin: 0; color: var(--muted); font-size: .82rem; }
    .question-context-fixed { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
    .question-context-chip { display: inline-flex; min-height: 40px; align-items: center; padding: 9px 12px; border: 1px solid #bfd5ea; border-radius: 7px; background: var(--primary-soft); color: var(--primary-dark); font-size: .82rem; font-weight: 800; }
    .question-builder { display: grid; min-width: 0; gap: 16px; }
    .question-builder > * { min-width: 0; }
    .question-step-head { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 16px; }
    .question-step-number { display: grid; width: 32px; height: 32px; flex: 0 0 32px; place-items: center; border-radius: 50%; background: var(--primary); color: #fff; font-size: .78rem; font-weight: 900; }
    .question-step-head h2 { margin: 0; font-size: 1rem; }
    .question-step-head p { margin: 3px 0 0; color: var(--muted); font-size: .78rem; }
    .question-type-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .question-type-option { display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 9px; align-items: start; min-height: 78px; padding: 12px; border: 1px solid var(--line); border-radius: 7px; background: #fff; cursor: pointer; }
    .question-type-option:has(input:checked) { border-color: var(--primary); background: var(--primary-soft); box-shadow: inset 0 0 0 1px var(--primary); }
    .question-type-option input { margin-top: 2px; }
    .question-type-option strong, .question-type-option span { display: block; }
    .question-type-option strong { color: var(--dark); font-size: .82rem; }
    .question-type-option span { margin-top: 4px; color: var(--muted); font-size: .7rem; line-height: 1.35; }
    .question-other-types, .question-advanced { margin-top: 12px; border-top: 1px solid var(--line); }
    .question-other-types summary, .question-advanced summary { padding: 14px 0 0; color: var(--primary-dark); cursor: pointer; font-size: .82rem; font-weight: 800; }
    .question-other-types[open] summary, .question-advanced[open] summary { margin-bottom: 14px; }
    .question-main-grid { display: grid; grid-template-columns: minmax(180px, .4fr) minmax(0, 1.6fr); gap: 14px; }
    .question-main-grid .textarea { min-height: 132px; }
    .question-media-editor { min-width: 0; max-width: 100%; overflow: hidden; margin-top: 14px; border: 1px solid var(--line); border-radius: 7px; background: #f8fafc; padding: 14px; }
    .question-media-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .question-media-toolbar-copy { margin-right: auto; }
    .question-media-toolbar-copy strong, .question-media-toolbar-copy span { display: block; }
    .question-media-toolbar-copy span { margin-top: 2px; color: var(--muted); font-size: .74rem; }
    .question-media-button.is-active { border-color: var(--primary); background: var(--primary-soft); color: var(--primary-dark); }
    .question-media-panel { min-width: 0; margin-top: 14px; border-top: 1px solid var(--line); padding-top: 14px; }
    .question-media-panel[hidden] { display: none; }
    .question-media-panel-head { margin-bottom: 12px; }
    .question-media-panel-head h3 { margin: 0; font-size: .9rem; }
    .question-media-panel-head p { margin: 3px 0 0; color: var(--muted); font-size: .75rem; }
    .question-image-editor { display: grid; grid-template-columns: minmax(180px, 260px) minmax(0, 1fr); gap: 14px; align-items: start; }
    .question-image-preview { display: grid; min-height: 172px; place-items: center; overflow: hidden; border: 1px dashed #aac0d6; border-radius: 7px; background: #fff; color: var(--muted); text-align: center; }
    .question-image-preview img { display: block; width: 100%; max-height: 280px; object-fit: contain; }
    .question-table-controls { display: flex; flex-wrap: wrap; align-items: end; gap: 10px; margin-bottom: 12px; }
    .question-table-controls .field { min-width: 120px; }
    .question-table-editor { width: 100%; min-width: 0; max-width: 100%; overflow-x: auto; }
    .question-table-grid { min-width: 440px; border-collapse: collapse; }
    .question-table-grid td { border: 1px solid var(--line); padding: 0; }
    .question-table-grid input { width: 100%; min-width: 110px; border: 0; border-radius: 0; background: #fff; padding: 10px; }
    .question-table-grid tr:first-child input { background: var(--primary-soft); color: var(--primary-dark); font-weight: 800; }
    .question-formula-tools { display: flex; flex-wrap: wrap; gap: 7px; margin: 8px 0; }
    .question-math-field { display: block; width: 100%; min-height: 86px; border: 1px solid #d4d4d8; border-radius: 7px; background: #fff; padding: 14px; color: var(--dark); font-size: 1.25rem; }
    .question-math-field:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(21, 71, 122, .12); outline: 0; }
    .question-formula-template { min-width: 76px; }
    .question-formula-template .katex { font-size: 1rem; }
    .question-formula-preview { display: grid; min-height: 72px; place-items: center; overflow-x: auto; border: 1px solid var(--line); border-radius: 7px; background: #fff; padding: 12px; }
    .question-preview-dialog { width: min(720px, calc(100% - 28px)); max-height: calc(100vh - 32px); border: 0; border-radius: 8px; box-shadow: 0 24px 70px rgba(15, 53, 92, .25); padding: 0; }
    .question-preview-dialog::backdrop { background: rgba(15, 35, 55, .58); }
    .question-preview-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--line); padding: 15px 18px; }
    .question-preview-head h2 { margin: 0; font-size: 1rem; }
    .question-preview-body { max-height: calc(100vh - 130px); overflow-y: auto; padding: 18px; }
    .question-preview-meta { margin-bottom: 12px; color: var(--muted); font-size: .76rem; font-weight: 700; }
    .question-preview-stimulus { margin-bottom: 14px; border-left: 4px solid var(--accent); background: var(--accent-soft); padding: 12px 14px; white-space: pre-line; }
    .question-preview-text { margin: 15px 0; color: var(--dark); font-size: 1.05rem; font-weight: 800; white-space: pre-line; }
    .question-preview-options { display: grid; gap: 8px; }
    .question-preview-option { display: flex; gap: 9px; align-items: flex-start; border: 1px solid var(--line); border-radius: 7px; padding: 10px 12px; }
    .question-preview-option b { color: var(--primary-dark); }
    .question-preview-media { display: grid; gap: 14px; }
    .question-preview-media img { display: block; max-width: 100%; max-height: 360px; margin: 0 auto; object-fit: contain; }
    .question-preview-media table { width: 100%; border-collapse: collapse; }
    .question-preview-media th, .question-preview-media td { border: 1px solid var(--line); padding: 8px; text-align: left; }
    .question-preview-media th { background: var(--primary-soft); color: var(--primary-dark); }
    .soal-answer-section { display: none; }
    .soal-answer-section.is-active { display: block; }
    .soal-option-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .soal-option-row { border: 1px solid var(--line); border-radius: 7px; padding: 12px; background: #fff; }
    .soal-option-label { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; color: var(--primary-dark); font-weight: 800; }
    .soal-option-label input { flex: 0 0 auto; }
    .question-form-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; padding-bottom: 8px; }

    @media (max-width: 920px) {
        .question-type-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .question-context { grid-template-columns: 1fr; }
        .question-context-fixed { justify-content: flex-start; }
    }

    @media (max-width: 680px) {
        .question-type-grid, .question-main-grid, .soal-option-grid { grid-template-columns: 1fr; }
        .question-image-editor { grid-template-columns: 1fr; }
        .question-media-toolbar { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: stretch; }
        .question-media-toolbar-copy { grid-column: 1 / -1; margin: 0; }
        .question-media-button, [data-question-preview] { width: 100%; min-width: 0; justify-content: center; }
        .question-formula-tools { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .question-formula-template { width: 100%; min-width: 0; }
        .question-preview-head { display: grid; grid-template-columns: minmax(0, 1fr) auto; }
        .question-preview-head .button { width: auto; min-width: 74px; }
        .question-form-actions { display: grid; grid-template-columns: 1fr; }
    }
</style>

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Ada bagian soal yang perlu diperbaiki.</strong>
        <p style="margin-top: 5px;">Periksa kolom yang ditandai, lalu simpan kembali.</p>
    </div>
@endif

<section class="panel panel-pad question-context">
    <div class="question-context-copy">
        <p class="eyebrow">Bank soal aktif</p>
        <h2>Mata pelajaran dan tingkat</h2>
        <p>Dipilih satu kali dan tetap digunakan saat menambahkan soal berikutnya.</p>
    </div>

    @if ($sedangEdit || collect($daftarKonteks ?? [])->count() === 1)
        <div class="question-context-fixed">
            <span class="question-context-chip">{{ $konteksAktif['nama_mata_pelajaran'] ?? $soalCbt?->mataPelajaran?->nama ?? 'Mata pelajaran' }}</span>
            <span class="question-context-chip">Kelas {{ $tingkatTerpilih ?: '-' }}</span>
        </div>
    @else
        <div class="field">
            <label for="konteks_soal">Pilih bank soal</label>
            <select id="konteks_soal" class="select" data-question-context required>
                <option value="">Pilih mata pelajaran dan tingkat</option>
                @foreach ($daftarKonteks as $konteks)
                    <option value="{{ $konteks['kunci'] }}" data-mata-pelajaran-id="{{ $konteks['mata_pelajaran_id'] }}" data-tingkat="{{ $konteks['tingkat'] }}" @selected($kunciKonteks === $konteks['kunci'])>{{ $konteks['label'] }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <input type="hidden" name="mata_pelajaran_id" value="{{ $mataPelajaranId ?: '' }}" data-context-mapel>
    <input type="hidden" name="tingkat" value="{{ $tingkatTerpilih ?: '' }}" data-context-level>
    @error('mata_pelajaran_id') <p class="error-text">Pilih bank soal yang sesuai dengan penugasan Anda.</p> @enderror
    @error('tingkat') <p class="error-text">Pilih tingkat yang memang Anda ajar.</p> @enderror
</section>

<div class="question-builder">
    <section class="panel panel-pad">
        <div class="question-step-head">
            <span class="question-step-number">1</span>
            <div><h2>Pilih jenis soal</h2><p>Setelah dipilih, NUSA hanya menampilkan bentuk jawaban yang diperlukan.</p></div>
        </div>

        <div class="question-type-grid">
            @foreach ($jenisUtama as $kode => $label)
                <label class="question-type-option">
                    <input type="radio" name="jenis_soal" value="{{ $kode }}" @checked($jenisTerpilih === $kode) data-soal-kind>
                    <span><strong>{{ $label }}</strong><span>{{ $deskripsiJenis[$kode] ?? '' }}</span></span>
                </label>
            @endforeach
        </div>

        <details class="question-other-types" data-other-types @if ($jenisLainnya->has($jenisTerpilih)) open @endif>
            <summary>Jenis soal lainnya</summary>
            <div class="question-type-grid">
                @foreach ($jenisLainnya as $kode => $label)
                    <label class="question-type-option">
                        <input type="radio" name="jenis_soal" value="{{ $kode }}" @checked($jenisTerpilih === $kode) data-soal-kind>
                        <span><strong>{{ $label }}</strong><span>{{ $deskripsiJenis[$kode] ?? '' }}</span></span>
                    </label>
                @endforeach
            </div>
        </details>
        @error('jenis_soal') <p class="error-text">{{ $message }}</p> @enderror
    </section>

    <section class="panel panel-pad">
        <div class="question-step-head">
            <span class="question-step-number">2</span>
            <div><h2>Tulis soal</h2><p>Materi membantu pencarian. Isi soal adalah bagian yang akan dibaca siswa.</p></div>
        </div>

        <div class="question-main-grid">
            <div class="field">
                <label for="topik">Materi/topik</label>
                <input id="topik" name="topik" type="text" value="{{ $nilai('topik') }}" placeholder="Contoh: Getaran" class="{{ $inputClass('topik') }}">
                <p class="help-text">Opsional, tetapi disarankan agar soal mudah dicari.</p>
            </div>
            <div class="field">
                <label for="pertanyaan">Isi soal</label>
                <textarea id="pertanyaan" name="pertanyaan" class="{{ $textareaClass('pertanyaan') }}" placeholder="Tuliskan pertanyaan yang akan dikerjakan siswa." required>{{ $nilai('pertanyaan') }}</textarea>
                @error('pertanyaan') <p class="error-text">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="question-media-editor" data-question-media-editor data-current-image="{{ $gambarSoalUrl }}">
            <div class="question-media-toolbar">
                <div class="question-media-toolbar-copy">
                    <strong>Tambahkan pendukung soal</strong>
                    <span>Opsional. Pilih hanya yang diperlukan.</span>
                </div>
                <button type="button" class="button button-muted question-media-button" data-media-toggle="gambar">Gambar</button>
                <button type="button" class="button button-muted question-media-button" data-media-toggle="tabel">Tabel</button>
                <button type="button" class="button button-muted question-media-button" data-media-toggle="rumus">Rumus</button>
                <button type="button" class="button button-primary" data-question-preview>Pratinjau soal</button>
            </div>

            <section class="question-media-panel" data-media-panel="gambar" @if (! $errors->hasAny(['gambar_soal', 'gambar_alt', 'gambar_keterangan']) && ! $gambarSoalUrl) hidden @endif>
                <div class="question-media-panel-head">
                    <h3>Gambar soal</h3>
                    <p>Gunakan JPG, PNG, atau WebP dengan ukuran maksimal 5 MB.</p>
                </div>
                <div class="question-image-editor">
                    <div class="question-image-preview" data-image-preview>
                        @if ($gambarSoalUrl)
                            <img src="{{ $gambarSoalUrl }}" alt="{{ data_get($gambarSoal, 'alt', 'Gambar pendukung soal') }}">
                        @else
                            <span>Belum ada gambar</span>
                        @endif
                    </div>
                    <div class="section-stack" style="gap: 11px;">
                        <div class="field">
                            <label for="gambar_soal">Pilih gambar</label>
                            <input id="gambar_soal" name="gambar_soal" type="file" accept="image/jpeg,image/png,image/webp" class="input" data-image-input>
                            @error('gambar_soal') <p class="error-text">{{ $message }}</p> @enderror
                            <p class="error-text" data-image-error hidden></p>
                        </div>
                        <div class="field">
                            <label for="gambar_alt">Keterangan singkat gambar</label>
                            <input id="gambar_alt" name="gambar_alt" type="text" maxlength="160" value="{{ old('gambar_alt', data_get($gambarSoal, 'alt')) }}" class="input" placeholder="Contoh: Grafik hubungan waktu dan simpangan">
                        </div>
                        <div class="field">
                            <label for="gambar_keterangan">Sumber/catatan gambar</label>
                            <input id="gambar_keterangan" name="gambar_keterangan" type="text" maxlength="220" value="{{ old('gambar_keterangan', data_get($gambarSoal, 'keterangan')) }}" class="input" placeholder="Opsional">
                        </div>
                        <input type="hidden" name="hapus_gambar_soal" value="0" data-remove-image>
                        <button type="button" class="button button-muted" data-clear-image>Hapus gambar</button>
                    </div>
                </div>
            </section>

            <section class="question-media-panel" data-media-panel="tabel" @if (! $errors->hasAny(['media_tabel', 'tabel_judul']) && blank($mediaTabel)) hidden @endif>
                <div class="question-media-panel-head">
                    <h3>Tabel soal</h3>
                    <p>Pilih jumlah baris dan kolom, lalu isi sel seperti tabel biasa. Baris pertama menjadi kepala tabel.</p>
                </div>
                <div class="question-table-controls">
                    <div class="field">
                        <label for="tabel_baris">Baris</label>
                        <select id="tabel_baris" class="select" data-table-rows>
                            @foreach (range(2, 10) as $jumlah)
                                <option value="{{ $jumlah }}" @selected($jumlahBarisTabel === $jumlah)>{{ $jumlah }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="tabel_kolom">Kolom</label>
                        <select id="tabel_kolom" class="select" data-table-columns>
                            @foreach (range(2, 8) as $jumlah)
                                <option value="{{ $jumlah }}" @selected($jumlahKolomTabel === $jumlah)>{{ $jumlah }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="flex: 1 1 220px;">
                        <label for="tabel_judul">Judul tabel</label>
                        <input id="tabel_judul" name="tabel_judul" type="text" maxlength="160" value="{{ old('tabel_judul', data_get($mediaSoal, 'tabel.judul')) }}" class="input" placeholder="Opsional">
                    </div>
                    <button type="button" class="button button-muted" data-clear-table>Hapus tabel</button>
                </div>
                <input type="hidden" name="media_tabel" value="{{ $mediaTabel }}" data-table-value>
                @error('media_tabel') <p class="error-text">{{ $message }}</p> @enderror
                <div class="question-table-editor" data-table-editor></div>
            </section>

            <section class="question-media-panel" data-media-panel="rumus" @if (! $errors->hasAny(['rumus_latex', 'rumus_keterangan']) && blank(old('rumus_latex', data_get($mediaSoal, 'rumus.latex'))) ) hidden @endif>
                <div class="question-media-panel-head">
                    <h3>Rumus matematika</h3>
                    <p>Rumus tampil langsung dalam bentuk yang sama seperti yang akan dilihat siswa.</p>
                </div>
                <div class="field">
                    <label for="rumus_visual">Isi rumus</label>
                    <div id="rumus_visual" class="question-math-field" data-formula-field>Menyiapkan editor rumus...</div>
                    <input type="hidden" name="rumus_latex" value="{{ old('rumus_latex', data_get($mediaSoal, 'rumus.latex')) }}" data-formula-input>
                    @error('rumus_latex') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="question-formula-tools" aria-label="Bentuk rumus yang sering digunakan">
                    <button type="button" class="button button-muted question-formula-template" data-formula-template="\frac{#0}{#?}" aria-label="Sisipkan pecahan" title="Pecahan"><span data-rumus-latex="\frac{a}{b}">a/b</span></button>
                    <button type="button" class="button button-muted question-formula-template" data-formula-template="\sqrt{#0}" aria-label="Sisipkan akar" title="Akar"><span data-rumus-latex="\sqrt{x}">akar x</span></button>
                    <button type="button" class="button button-muted question-formula-template" data-formula-template="#0^{#?}" aria-label="Sisipkan pangkat" title="Pangkat"><span data-rumus-latex="x^{2}">x pangkat 2</span></button>
                    <button type="button" class="button button-muted question-formula-template" data-formula-template="#0_{#?}" aria-label="Sisipkan indeks" title="Indeks"><span data-rumus-latex="x_{1}">x indeks 1</span></button>
                    <button type="button" class="button button-muted question-formula-template" data-formula-template="\times" aria-label="Sisipkan tanda kali" title="Kali"><span data-rumus-latex="\times">kali</span></button>
                    <button type="button" class="button button-muted question-formula-template" data-formula-template="\div" aria-label="Sisipkan tanda bagi" title="Bagi"><span data-rumus-latex="\div">bagi</span></button>
                </div>
                <div class="field" style="margin-top: 10px;">
                    <label for="rumus_keterangan">Keterangan rumus</label>
                    <input id="rumus_keterangan" name="rumus_keterangan" type="text" maxlength="220" value="{{ old('rumus_keterangan', data_get($mediaSoal, 'rumus.keterangan')) }}" class="input" placeholder="Opsional, contoh: n adalah jumlah getaran">
                </div>
            </section>
        </div>
    </section>

    <section class="panel panel-pad">
        <div class="question-step-head">
            <span class="question-step-number">3</span>
            <div><h2>Isi jawaban dan tentukan kunci</h2><p>Bentuk kolom berikut otomatis mengikuti jenis soal yang dipilih.</p></div>
        </div>

        @foreach (['opsi', 'kunci_pg', 'kunci_pgk', 'pernyataan', 'pasangan_kiri', 'kunci_teks'] as $field)
            @error($field) <p class="error-text" style="margin-bottom: 10px;">{{ $message }}</p> @enderror
        @endforeach

        <div class="soal-answer-section" data-answer-section="pilihan_ganda pilihan_ganda_kompleks">
            <div class="soal-option-grid">
                @foreach (['A', 'B', 'C', 'D'] as $kode)
                    <div class="soal-option-row">
                        <label class="soal-option-label">
                            <input type="radio" name="kunci_pg" value="{{ $kode }}" @checked($kunciPg === $kode) data-pg-key>
                            <input type="checkbox" name="kunci_pgk[]" value="{{ $kode }}" @checked(in_array($kode, (array) $kunciPgk, true)) data-pgk-key>
                            <span>Jawaban {{ $kode }}</span>
                        </label>
                        <textarea name="opsi[{{ $kode }}]" class="textarea" rows="2" placeholder="Isi pilihan {{ $kode }}">{{ $opsiPilihan[$kode] ?? '' }}</textarea>
                    </div>
                @endforeach
            </div>
            <p class="help-text" style="margin-top: 10px;">Tandai satu jawaban benar untuk Pilihan Ganda, atau beberapa jawaban untuk Pilihan Ganda Kompleks.</p>
        </div>

        <div class="soal-answer-section" data-answer-section="benar_salah">
            <div class="soal-option-grid">
                @foreach (range(0, 3) as $index)
                    <div class="soal-option-row">
                        <label for="pernyataan_{{ $index }}">Pernyataan {{ $index + 1 }}</label>
                        <textarea id="pernyataan_{{ $index }}" name="pernyataan[]" class="textarea" rows="2">{{ $pernyataan[$index] ?? '' }}</textarea>
                        <select name="jawaban_bs[]" class="select" style="margin-top: 8px;">
                            <option value="benar" @selected(($jawabanBs[$index] ?? 'benar') === 'benar')>Benar</option>
                            <option value="salah" @selected(($jawabanBs[$index] ?? 'benar') === 'salah')>Salah</option>
                        </select>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="soal-answer-section" data-answer-section="menjodohkan">
            <div class="soal-option-grid">
                @foreach (range(0, 3) as $index)
                    <div class="soal-option-row">
                        <label for="pasangan_kiri_{{ $index }}">Pernyataan {{ $index + 1 }}</label>
                        <textarea id="pasangan_kiri_{{ $index }}" name="pasangan_kiri[]" class="textarea" rows="2">{{ $pasanganKiri[$index] ?? '' }}</textarea>
                        <label for="pasangan_kanan_{{ $index }}" style="margin-top: 8px;">Pasangan jawaban</label>
                        <textarea id="pasangan_kanan_{{ $index }}" name="pasangan_kanan[]" class="textarea" rows="2">{{ $pasanganKanan[$index] ?? '' }}</textarea>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="soal-answer-section" data-answer-section="isian_singkat uraian numerik upload_file">
            <div class="form-grid">
                <div class="field span-2">
                    <label for="kunci_teks">Kunci jawaban</label>
                    <textarea id="kunci_teks" name="kunci_teks" class="textarea" placeholder="Untuk uraian atau upload file, boleh dikosongkan jika diperiksa manual.">{{ $kunciTeks }}</textarea>
                </div>
                <div class="field span-2">
                    <label for="rubrik_teks">Rubrik/catatan pemeriksaan</label>
                    <textarea id="rubrik_teks" name="rubrik_teks" class="textarea" placeholder="Opsional untuk soal yang diperiksa manual.">{{ $rubrikTeks }}</textarea>
                </div>
            </div>
        </div>
    </section>

    <section class="panel panel-pad">
        <details class="question-advanced" @if ($bukaPengaturanTambahan) open @endif>
            <summary>Pengaturan tambahan (opsional)</summary>
            <p class="help-text" style="margin-bottom: 14px;">Gunakan bagian ini hanya jika soal memerlukan klasifikasi, stimulus, atau pembahasan khusus.</p>
            <div class="form-grid">
                <div class="field">
                    <label for="tingkat_kesulitan">Tingkat kesulitan</label>
                    <select id="tingkat_kesulitan" name="tingkat_kesulitan" class="{{ $selectClass('tingkat_kesulitan') }}">
                        @foreach ($daftarKesulitan as $kode => $label)
                            <option value="{{ $kode }}" @selected($nilai('tingkat_kesulitan', 'sedang') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="kategori">Kategori</label>
                    <select id="kategori" name="kategori" class="{{ $selectClass('kategori') }}">
                        @foreach ($daftarKategori as $kode => $label)
                            <option value="{{ $kode }}" @selected($nilai('kategori', 'umum') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field span-2">
                    <label for="materi">Rincian materi</label>
                    <input id="materi" name="materi" type="text" value="{{ $nilai('materi') }}" placeholder="Contoh: Menentukan frekuensi dari jumlah getaran" class="{{ $inputClass('materi') }}">
                </div>
                <div class="field span-2">
                    <label for="tujuan_pembelajaran">Tujuan pembelajaran</label>
                    <textarea id="tujuan_pembelajaran" name="tujuan_pembelajaran" class="{{ $textareaClass('tujuan_pembelajaran') }}">{{ $nilai('tujuan_pembelajaran') }}</textarea>
                </div>
                <div class="field span-2">
                    <label for="stimulus">Stimulus</label>
                    <textarea id="stimulus" name="stimulus" class="{{ $textareaClass('stimulus') }}" placeholder="Teks bacaan, kasus, atau data pendukung sebelum pertanyaan.">{{ $nilai('stimulus') }}</textarea>
                </div>
                <div class="field span-2">
                    <label for="pembahasan">Pembahasan</label>
                    <textarea id="pembahasan" name="pembahasan" class="{{ $textareaClass('pembahasan') }}" placeholder="Pembahasan dapat ditampilkan setelah ujian selesai.">{{ $nilai('pembahasan') }}</textarea>
                </div>
            </div>
        </details>
    </section>

    <div class="question-form-actions">
        <a href="{{ route('soal-cbt.index', ['mata_pelajaran_id' => $mataPelajaranId ?: null, 'tingkat' => $tingkatTerpilih ?: 'semua']) }}" class="button button-muted">Batal</a>
        <button type="submit" name="aksi" value="simpan_draf" class="button button-muted">Simpan draf</button>
        <button type="submit" name="aksi" value="simpan_siap" class="button button-dark">Simpan siap</button>
        @unless ($sedangEdit)
            <button type="submit" name="aksi" value="simpan_lanjut" class="button button-primary">Simpan siap & buat berikutnya</button>
        @endunless
    </div>
</div>

<dialog class="question-preview-dialog" data-question-preview-dialog>
    <div class="question-preview-head">
        <h2>Pratinjau soal</h2>
        <button type="button" class="button button-muted" data-close-question-preview>Tutup</button>
    </div>
    <div class="question-preview-body" data-question-preview-body></div>
</dialog>

@push('scripts')
    @vite('resources/js/soal-editor.js')
@endpush

<script>
    (() => {
        const contextSelect = document.querySelector('[data-question-context]');
        const mapelInput = document.querySelector('[data-context-mapel]');
        const levelInput = document.querySelector('[data-context-level]');
        const typeInputs = document.querySelectorAll('[data-soal-kind]');
        const sections = document.querySelectorAll('[data-answer-section]');
        const pgKeys = document.querySelectorAll('[data-pg-key]');
        const pgkKeys = document.querySelectorAll('[data-pgk-key]');

        const syncContext = () => {
            if (!contextSelect) return;
            const option = contextSelect.selectedOptions[0];
            mapelInput.value = option?.dataset.mataPelajaranId || '';
            levelInput.value = option?.dataset.tingkat || '';
        };

        const syncType = () => {
            const selected = document.querySelector('[data-soal-kind]:checked');
            const value = selected?.value || 'pilihan_ganda';

            sections.forEach((section) => {
                section.classList.toggle('is-active', section.dataset.answerSection.split(' ').includes(value));
            });

            pgKeys.forEach((input) => {
                input.style.display = value === 'pilihan_ganda' ? '' : 'none';
                input.disabled = value !== 'pilihan_ganda';
            });

            pgkKeys.forEach((input) => {
                input.style.display = value === 'pilihan_ganda_kompleks' ? '' : 'none';
                input.disabled = value !== 'pilihan_ganda_kompleks';
            });
        };

        contextSelect?.addEventListener('change', syncContext);
        typeInputs.forEach((input) => input.addEventListener('change', syncType));
        syncContext();
        syncType();
    })();
</script>
