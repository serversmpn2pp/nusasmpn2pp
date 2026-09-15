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
    $kesulitanTerpilih = (string) $nilai('tingkat_kesulitan');
    $kategoriTerpilih = (string) $nilai('kategori', 'mots');
    $jenisUtama = collect($daftarJenisSoal)->only(['pilihan_ganda', 'pilihan_ganda_kompleks', 'benar_salah', 'isian_singkat']);
    $jenisLainnya = collect($daftarJenisSoal)->except($jenisUtama->keys()->all());
    $deskripsiJenis = [
        'pilihan_ganda' => 'Satu jawaban benar dari pilihan A-D.',
        'pilihan_ganda_kompleks' => 'Lebih dari satu jawaban dapat benar.',
        'benar_salah' => 'Nilai benar atau salah untuk beberapa pernyataan.',
        'isian_singkat' => 'Untuk nama, istilah, atau jawaban pendek. Bisa memakai beberapa alternatif kunci.',
        'menjodohkan' => 'Pasangkan pernyataan dengan jawaban.',
        'uraian' => 'Jawaban diperiksa manual memakai rubrik.',
        'numerik' => 'Untuk hasil perhitungan. Kunci diisi angka saja; koma dan titik desimal dianggap sama.',
        'upload_file' => 'Unggah hasil tugas pada Asesmen Kelas.',
    ];
    $deskripsiKategori = [
        'lots' => 'Mengingat atau memahami informasi dan konsep dasar.',
        'mots' => 'Menerapkan konsep atau menghubungkan beberapa informasi.',
        'hots' => 'Menganalisis, menilai, atau menyelesaikan masalah baru.',
    ];

    $opsiPilihan = old('opsi', $soalCbt?->opsi['pilihan'] ?? ['A' => '', 'B' => '', 'C' => '', 'D' => '']);
    $opsiPilihan = array_merge(['A' => '', 'B' => '', 'C' => '', 'D' => ''], $opsiPilihan ?: []);
    $jawaban = $soalCbt?->kunci_jawaban['jawaban'] ?? null;
    $kunciPg = old('kunci_pg', is_string($jawaban) ? $jawaban : '');
    $kunciPgk = old('kunci_pgk', is_array($jawaban) ? $jawaban : []);

    $pernyataanAwalData = collect($soalCbt?->opsi['pernyataan'] ?? [])->values();
    $pernyataanAwal = $pernyataanAwalData->pluck('teks')->all();
    $jawabanBsAwal = collect($soalCbt?->kunci_jawaban['jawaban'] ?? [])->map(fn ($value) => $value ? 'benar' : 'salah')->values()->all();
    $pernyataan = array_pad(old('pernyataan', $pernyataanAwal), 4, '');
    $jawabanBs = array_pad(old('jawaban_bs', $jawabanBsAwal), 4, 'benar');
    $pernyataanMediaKeyAwal = $pernyataanAwalData->map(fn ($item, $index) => $item['media_key'] ?? 'pernyataan_' . ($index + 1))->all();
    $pernyataanMediaKey = array_pad(old('pernyataan_media_key', $pernyataanMediaKeyAwal), 4, '');
    foreach ($pernyataanMediaKey as $index => $key) {
        $pernyataanMediaKey[$index] = filled($key) ? $key : 'pernyataan_' . ($index + 1);
    }

    $pasanganAwal = collect($soalCbt?->opsi['pasangan'] ?? []);
    $pasanganKiriAwal = array_values((array) old('pasangan_kiri', $pasanganAwal->pluck('kiri')->all()));
    $pasanganKananAwal = array_values((array) old('pasangan_kanan', $pasanganAwal->pluck('kanan')->all()));
    $jumlahPasangan = min(10, max(4, count($pasanganKiriAwal), count($pasanganKananAwal)));
    $pasanganKiri = array_pad($pasanganKiriAwal, $jumlahPasangan, '');
    $pasanganKanan = array_pad($pasanganKananAwal, $jumlahPasangan, '');
    $pasanganMediaKiriAwal = $pasanganAwal->values()->map(fn ($item, $index) => $item['media_kiri_key'] ?? 'pasangan_' . ($index + 1) . '_kiri')->all();
    $pasanganMediaKananAwal = $pasanganAwal->values()->map(fn ($item, $index) => $item['media_kanan_key'] ?? 'pasangan_' . ($index + 1) . '_kanan')->all();
    $pasanganMediaKiri = array_pad(old('pasangan_media_kiri_key', $pasanganMediaKiriAwal), $jumlahPasangan, '');
    $pasanganMediaKanan = array_pad(old('pasangan_media_kanan_key', $pasanganMediaKananAwal), $jumlahPasangan, '');
    foreach (range(0, $jumlahPasangan - 1) as $index) {
        $pasanganMediaKiri[$index] = filled($pasanganMediaKiri[$index] ?? null) ? $pasanganMediaKiri[$index] : 'pasangan_' . ($index + 1) . '_kiri';
        $pasanganMediaKanan[$index] = filled($pasanganMediaKanan[$index] ?? null) ? $pasanganMediaKanan[$index] : 'pasangan_' . ($index + 1) . '_kanan';
    }
    $pengecohLama = collect($soalCbt?->opsi['pengecoh'] ?? [])->map(fn ($item) => is_array($item) ? ($item['teks'] ?? '') : $item)->values();
    $pengecohMenjodohkan = array_values((array) old('pengecoh_menjodohkan', $pengecohLama->all()));
    $pengecohMediaLama = collect($soalCbt?->opsi['pengecoh_media'] ?? [])->values();
    $pengecohMediaKeyAwal = $pengecohLama->keys()->map(fn ($index) => data_get($pengecohMediaLama, $index . '.media_key', 'pengecoh_' . ($index + 1)))->all();
    $pengecohMediaKey = array_values((array) old('pengecoh_media_key', $pengecohMediaKeyAwal));
    foreach ($pengecohMenjodohkan as $index => $_) {
        $pengecohMediaKey[$index] = filled($pengecohMediaKey[$index] ?? null) ? $pengecohMediaKey[$index] : 'pengecoh_' . ($index + 1);
    }
    $kunciTeks = old('kunci_teks', is_string($jawaban) ? $jawaban : '');
    $rubrikTeks = old('rubrik_teks', $soalCbt?->rubrik['catatan'] ?? '');
    $mediaSoal = $soalCbt?->media ?? [];
    $mediaKonten = data_get($mediaSoal, 'konten', []);

    $bukaPengaturanTambahan = $errors->hasAny([
        'materi', 'tujuan_pembelajaran', 'pembahasan', 'rubrik_teks',
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
    .question-difficulty { margin-top: 18px; border-top: 1px solid var(--line); padding-top: 16px; }
    .question-difficulty-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 11px; }
    .question-difficulty-head h3 { margin: 0; font-size: .92rem; }
    .question-difficulty-head p { margin: 3px 0 0; color: var(--muted); font-size: .75rem; }
    .question-required-badge { flex: 0 0 auto; border-radius: 6px; background: #fff2cc; padding: 6px 8px; color: #805d00; font-size: .68rem; font-weight: 900; }
    .question-difficulty-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .question-difficulty-option { display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 9px; align-items: center; min-height: 62px; border: 1px solid var(--line); border-radius: 7px; background: #fff; padding: 11px 12px; cursor: pointer; }
    .question-difficulty-option:has(input:checked) { border-color: var(--primary); background: var(--primary-soft); box-shadow: inset 0 0 0 1px var(--primary); }
    .question-difficulty-option strong, .question-difficulty-option span { display: block; }
    .question-difficulty-option strong { color: var(--dark); font-size: .8rem; }
    .question-difficulty-option span { margin-top: 3px; color: var(--primary-dark); font-size: .72rem; font-weight: 800; }
    .question-main-grid { display: grid; grid-template-columns: minmax(180px, .4fr) minmax(0, 1.6fr); gap: 14px; }
    .question-main-grid .textarea { min-height: 132px; }
    .question-category { margin-bottom: 16px; }
    .question-category-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 9px; }
    .question-category-head label { margin: 0; }
    .question-category-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .question-category-option { display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 9px; align-items: start; min-height: 76px; border: 1px solid var(--line); border-radius: 7px; background: #fff; padding: 11px 12px; cursor: pointer; }
    .question-category-option:has(input:checked) { border-color: var(--primary); background: var(--primary-soft); box-shadow: inset 0 0 0 1px var(--primary); }
    .question-category-option input { margin-top: 2px; }
    .question-category-option strong, .question-category-option span { display: block; }
    .question-category-option strong { color: var(--primary-dark); font-size: .82rem; }
    .question-category-option span { margin-top: 3px; color: var(--muted); font-size: .72rem; line-height: 1.4; }
    .question-stimulus-box { margin-bottom: 16px; border: 1px solid var(--line); border-radius: 7px; background: #f8fafc; padding: 14px; }
    .question-stimulus-box .textarea { min-height: 105px; }
    .question-media-editor { min-width: 0; max-width: 100%; overflow: hidden; margin-top: 14px; border: 1px solid var(--line); border-radius: 7px; background: #f8fafc; padding: 14px; }
    .question-media-editor.is-compact { margin-top: 10px; background: #fbfdff; padding: 10px; }
    .question-media-editor.is-compact .question-media-toolbar-copy strong { font-size: .78rem; }
    .question-media-editor.is-compact .question-media-toolbar-copy span { font-size: .68rem; }
    .question-media-editor.is-compact .question-media-button { min-height: 34px; padding: 6px 9px; font-size: .72rem; }
    .question-media-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .question-media-toolbar-copy { margin-right: auto; }
    .question-media-toolbar-copy strong, .question-media-toolbar-copy span { display: block; }
    .question-media-toolbar-copy span { margin-top: 2px; color: var(--muted); font-size: .74rem; }
    .question-media-button.is-active { border-color: var(--primary); background: var(--primary-soft); color: var(--primary-dark); }
    .question-media-status { border-radius: 6px; background: #e1f3e9; padding: 6px 8px; color: #116644; font-size: .68rem; font-weight: 900; }
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
    .question-preview-dialog { width: min(860px, calc(100% - 28px)); max-height: calc(100vh - 32px); border: 0; border-radius: 8px; box-shadow: 0 24px 70px rgba(15, 53, 92, .25); padding: 0; }
    .question-preview-dialog::backdrop { background: rgba(15, 35, 55, .58); }
    .question-preview-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--line); padding: 15px 18px; }
    .question-preview-head h2 { margin: 0; font-size: 1rem; }
    .question-preview-body { max-height: calc(100vh - 78px); overflow-y: auto; background: #f6f8fb; padding: 18px; }
    .question-preview-exam { min-height: 410px; padding: 22px; scroll-margin-top: 96px; }
    .question-preview-exam .question-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .question-preview-exam .question-number { display: inline-flex; min-width: 42px; height: 42px; align-items: center; justify-content: center; border-radius: 8px; background: var(--primary); color: #fff; font-weight: 950; }
    .question-preview-exam .badge { min-height: 30px; align-items: center; padding: 6px 10px; font-size: .78rem; font-weight: 900; white-space: nowrap; }
    .question-preview-exam .question-title { margin: 0; font-size: 1.05rem; line-height: 1.45; white-space: pre-line; }
    .question-preview-exam .stimulus { margin: 12px 0 16px; border-left: 4px solid var(--accent); border-radius: 8px; background: #fffaf0; padding: 12px 14px; color: #334155; white-space: pre-line; }
    .question-preview-exam .option-list { display: grid; gap: 10px; margin-top: 14px; }
    .question-preview-exam .option-card { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 10px; align-items: start; border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 12px; cursor: pointer; }
    .question-preview-exam .option-card:hover { border-color: rgba(21, 71, 122, .45); background: #fbfdff; }
    .question-preview-exam .option-card input { width: 19px; height: 19px; margin-top: 2px; accent-color: var(--primary); }
    .question-preview-exam .option-card-content { min-width: 0; }
    .question-preview-exam .option-code { display: inline-flex; min-width: 28px; height: 28px; align-items: center; justify-content: center; border-radius: 8px; background: var(--primary-soft); color: var(--primary-dark); font-weight: 950; }
    .question-preview-exam .option-text { color: #344054; font-weight: 760; white-space: pre-line; }
    .question-preview-exam .statement-row, .question-preview-exam .matching-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: center; border: 1px solid var(--line); border-radius: 8px; padding: 12px; }
    .question-preview-exam .statement-options { display: flex; flex-wrap: wrap; gap: 8px; }
    .question-preview-exam .pill-option { display: inline-flex; align-items: center; gap: 7px; border: 1px solid var(--line); border-radius: 999px; padding: 7px 10px; font-size: .88rem; font-weight: 900; }
    .question-preview-exam .pill-option input { accent-color: var(--primary); }
    .question-preview-exam .matching-answer-bank { margin-top: 14px; border: 1px solid #b9cde2; border-radius: 8px; background: var(--primary-soft); padding: 12px; }
    .question-preview-exam .matching-answer-bank > strong { display: block; margin-bottom: 9px; color: var(--primary-dark); }
    .question-preview-exam .matching-answer-bank > div { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .question-preview-exam .matching-answer-option { display: grid; grid-template-columns: 26px minmax(0, 1fr); gap: 7px; align-items: start; border: 1px solid rgba(21, 71, 122, .14); border-radius: 7px; background: #fff; padding: 8px 9px; color: #344054; font-size: .88rem; font-weight: 700; }
    .question-preview-exam .matching-answer-bank b { color: var(--primary-dark); }
    .question-preview-exam .matching-select { min-width: 250px; }
    .question-preview-exam .check-row { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); font-size: .9rem; font-weight: 900; }
    .question-preview-exam .check-row input { width: 18px; height: 18px; accent-color: var(--accent); }
    .question-preview-exam .field { display: grid; gap: 7px; margin-top: 14px; }
    .question-preview-exam .field label { margin: 0; color: #344054; font-size: .9rem; font-weight: 900; }
    .question-preview-exam .input, .question-preview-exam .textarea, .question-preview-exam .select { width: 100%; border: 1px solid #cfd8e3; border-radius: 8px; background: #fff; color: var(--text); outline: none; }
    .question-preview-exam .input, .question-preview-exam .select { min-height: 46px; padding: 10px 12px; }
    .question-preview-exam .textarea { min-height: 116px; resize: vertical; padding: 11px 12px; }
    .question-preview-exam .file-answer-box { display: grid; gap: 12px; margin-top: 14px; border: 1px solid #b9cde2; border-radius: 8px; background: var(--primary-soft); padding: 16px; }
    .question-preview-exam .file-answer-status { display: grid; gap: 3px; min-width: 0; }
    .question-preview-exam .file-answer-status span { color: var(--muted); font-size: .82rem; font-weight: 750; }
    .question-preview-exam .file-answer-action { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .question-preview-exam .question-media-content { display: grid; gap: 16px; margin: 16px 0; }
    .question-preview-exam .question-media-content figure { margin: 0; }
    .question-preview-exam .question-media-figure { text-align: center; }
    .question-preview-exam .question-media-figure img { display: block; width: auto; max-width: 100%; max-height: 430px; margin: 0 auto; border: 1px solid #dfe7f0; border-radius: 7px; object-fit: contain; }
    .question-preview-exam .question-media-content figcaption { margin-top: 7px; color: #71717a; font-size: .78rem; text-align: center; }
    .question-preview-exam .question-media-table-wrap > figcaption { margin: 0 0 7px; color: #18181b; font-size: .84rem; font-weight: 800; text-align: left; }
    .question-preview-exam .question-media-table-scroll { overflow-x: auto; }
    .question-preview-exam .question-media-table { width: 100%; min-width: 420px; border-collapse: collapse; }
    .question-preview-exam .question-media-table th, .question-preview-exam .question-media-table td { border: 1px solid #dfe7f0; padding: 9px 10px; text-align: left; vertical-align: top; }
    .question-preview-exam .question-media-table th { background: var(--primary-soft); color: var(--primary-dark); font-weight: 800; }
    .question-preview-exam .question-media-formula { overflow-x: auto; border: 1px solid #dfe7f0; border-radius: 7px; background: #fff; padding: 14px; text-align: center; }
    .question-preview-exam .question-media-formula [data-rumus-latex] { min-width: max-content; font-size: 1.08rem; }
    .question-preview-exam .question-media-content.is-compact { gap: 9px; margin: 9px 0 2px; }
    .question-preview-exam .question-media-content.is-compact .question-media-figure { text-align: left; }
    .question-preview-exam .question-media-content.is-compact .question-media-figure img { max-height: 230px; margin-left: 0; }
    .question-preview-exam .question-media-content.is-compact .question-media-table { min-width: 320px; }
    .question-preview-exam .question-media-content.is-compact .question-media-table th, .question-preview-exam .question-media-content.is-compact .question-media-table td { padding: 7px 8px; }
    .question-preview-exam .question-media-content.is-compact .question-media-formula { padding: 10px; text-align: left; }
    .question-preview-exam .question-media-content.is-compact figcaption { text-align: left; }
    .soal-answer-section { display: none; }
    .soal-answer-section.is-active { display: block; }
    .question-answer-guidance { margin-bottom: 14px; border-left: 4px solid var(--primary); background: var(--primary-soft); padding: 11px 13px; }
    .question-answer-guidance[hidden] { display: none; }
    .question-answer-guidance strong, .question-answer-guidance span { display: block; }
    .question-answer-guidance strong { color: var(--primary-dark); font-size: .82rem; }
    .question-answer-guidance span { margin-top: 4px; color: var(--muted); font-size: .76rem; line-height: 1.45; }
    .soal-option-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .soal-option-row { border: 1px solid var(--line); border-radius: 7px; padding: 12px; background: #fff; }
    .soal-option-label { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; color: var(--primary-dark); font-weight: 800; }
    .soal-option-label input { flex: 0 0 auto; }
    .matching-builder { display: grid; gap: 18px; }
    .matching-group + .matching-group { border-top: 1px solid var(--line); padding-top: 18px; }
    .matching-group-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 11px; }
    .matching-group-head h3 { margin: 0; color: var(--dark); font-size: .9rem; }
    .matching-group-head p { margin: 4px 0 0; color: var(--muted); font-size: .76rem; line-height: 1.45; }
    .matching-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .matching-row-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
    .matching-row-head strong { color: var(--primary-dark); font-size: .8rem; }
    .matching-row-remove { min-height: 32px; padding: 5px 9px; font-size: .72rem; }
    .matching-summary { margin: 0; border-left: 4px solid var(--accent); background: var(--accent-soft); padding: 10px 12px; color: var(--dark); font-size: .78rem; font-weight: 800; }
    .question-form-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; padding-bottom: 8px; }

    @media (max-width: 920px) {
        .question-type-grid, .question-difficulty-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .question-context { grid-template-columns: 1fr; }
        .question-context-fixed { justify-content: flex-start; }
    }

    @media (max-width: 680px) {
        .question-type-grid, .question-difficulty-grid, .question-category-grid, .question-main-grid, .soal-option-grid, .matching-list { grid-template-columns: 1fr; }
        .question-image-editor { grid-template-columns: 1fr; }
        .question-media-toolbar { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: stretch; }
        .question-media-toolbar-copy { grid-column: 1 / -1; margin: 0; }
        .question-media-button { width: 100%; min-width: 0; justify-content: center; }
        .question-formula-tools { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .question-formula-template { width: 100%; min-width: 0; }
        .question-preview-head { display: grid; grid-template-columns: minmax(0, 1fr) auto; }
        .question-preview-head .button { width: auto; min-width: 74px; }
        .question-form-actions { display: grid; grid-template-columns: 1fr; }
        .question-preview-exam { min-height: 0; padding: 17px; }
        .question-preview-exam .statement-row, .question-preview-exam .matching-row { grid-template-columns: 1fr; }
        .question-preview-exam .matching-answer-bank > div { grid-template-columns: 1fr; }
        .question-preview-exam .matching-select { min-width: 0; }
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

        <div class="question-difficulty">
            <div class="question-difficulty-head">
                <div>
                    <h3>Tingkat kesulitan dan skor</h3>
                    <p>Pilih tingkat kesulitan setiap soal. Skor ditentukan otomatis dan tidak perlu diisi lagi.</p>
                </div>
                <span class="question-required-badge">Wajib dipilih</span>
            </div>
            <div class="question-difficulty-grid">
                @foreach ($daftarKesulitan as $kode => $label)
                    <label class="question-difficulty-option">
                        <input type="radio" name="tingkat_kesulitan" value="{{ $kode }}" @checked($kesulitanTerpilih === $kode) required>
                        <span>
                            <strong>{{ $label }}</strong>
                            <span>Skor {{ \App\Models\SoalCbt::skorUntukKesulitan($kode) }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('tingkat_kesulitan') <p class="error-text">{{ $message }}</p> @enderror
        </div>
    </section>

    <section class="panel panel-pad">
        <div class="question-step-head">
            <span class="question-step-number">2</span>
            <div><h2>Tulis soal</h2><p>Materi membantu pencarian. Isi soal adalah bagian yang akan dibaca siswa.</p></div>
        </div>

        <div class="question-category">
            <div class="question-category-head">
                <label>Kategori proses berpikir</label>
                <span class="question-required-badge">Wajib dipilih</span>
            </div>
            <div class="question-category-grid">
                @foreach ($daftarKategori as $kode => $label)
                    <label class="question-category-option">
                        <input type="radio" name="kategori" value="{{ $kode }}" @checked($kategoriTerpilih === $kode) required>
                        <span><strong>{{ $label }}</strong><span>{{ $deskripsiKategori[$kode] ?? '' }}</span></span>
                    </label>
                @endforeach
            </div>
            @error('kategori') <p class="error-text">{{ $message }}</p> @enderror
        </div>

        <div class="question-stimulus-box">
            <div class="field">
                <label for="stimulus">Stimulus <span class="help-text">(opsional)</span></label>
                <textarea id="stimulus" name="stimulus" class="{{ $textareaClass('stimulus') }}" placeholder="Teks bacaan, kasus, data, atau pengantar yang dibaca siswa sebelum pertanyaan.">{{ $nilai('stimulus') }}</textarea>
                <p class="help-text">Biarkan kosong jika pertanyaan tidak membutuhkan bacaan atau data pengantar.</p>
            </div>
            <x-editor-media-soal kunci="stimulus" :media="data_get($mediaKonten, 'stimulus', [])" label="Media stimulus" compact />
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

        <x-editor-media-soal :media="$mediaSoal" label="Media isi soal" />

        <details class="question-advanced" @if ($bukaPengaturanTambahan) open @endif>
            <summary>Catatan guru dan rincian materi (opsional)</summary>
            <p class="help-text" style="margin-bottom: 14px;">Bagian ini membantu pencarian dan pemeriksaan soal, tetapi tidak wajib diisi.</p>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="materi">Rincian materi</label>
                    <input id="materi" name="materi" type="text" value="{{ $nilai('materi') }}" placeholder="Contoh: Menentukan frekuensi dari jumlah getaran" class="{{ $inputClass('materi') }}">
                </div>
                <div class="field span-2">
                    <label for="tujuan_pembelajaran">Tujuan pembelajaran</label>
                    <textarea id="tujuan_pembelajaran" name="tujuan_pembelajaran" class="{{ $textareaClass('tujuan_pembelajaran') }}">{{ $nilai('tujuan_pembelajaran') }}</textarea>
                </div>
                <div class="field span-2">
                    <label for="pembahasan">Pembahasan</label>
                    <textarea id="pembahasan" name="pembahasan" class="{{ $textareaClass('pembahasan') }}" placeholder="Pembahasan dapat ditampilkan setelah ujian selesai.">{{ $nilai('pembahasan') }}</textarea>
                </div>
            </div>
        </details>
    </section>

    <section class="panel panel-pad">
        <div class="question-step-head">
            <span class="question-step-number">3</span>
            <div><h2>Isi jawaban dan tentukan kunci</h2><p>Bentuk kolom berikut otomatis mengikuti jenis soal yang dipilih.</p></div>
        </div>

        @foreach (['opsi', 'kunci_pg', 'kunci_pgk', 'pernyataan', 'pasangan_kiri', 'pengecoh_menjodohkan', 'kunci_teks'] as $field)
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
                        <x-editor-media-soal :kunci="'pilihan_' . $kode" :media="data_get($mediaKonten, 'pilihan_' . $kode, [])" :label="'Media pilihan ' . $kode" compact />
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
                        <input type="hidden" name="pernyataan_media_key[]" value="{{ $pernyataanMediaKey[$index] }}">
                        <x-editor-media-soal :kunci="$pernyataanMediaKey[$index]" :media="data_get($mediaKonten, $pernyataanMediaKey[$index], [])" :label="'Media pernyataan ' . ($index + 1)" compact />
                        <select name="jawaban_bs[]" class="select" style="margin-top: 8px;">
                            <option value="benar" @selected(($jawabanBs[$index] ?? 'benar') === 'benar')>Benar</option>
                            <option value="salah" @selected(($jawabanBs[$index] ?? 'benar') === 'salah')>Salah</option>
                        </select>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="soal-answer-section" data-answer-section="menjodohkan">
            <div class="question-answer-guidance">
                <strong>Cara membuat soal Menjodohkan</strong>
                <span>Isi pasangan yang benar terlebih dahulu. Jawaban pengecoh boleh ditambahkan agar pilihan jawaban lebih banyak daripada jumlah pernyataan.</span>
            </div>
            <div class="matching-builder">
                <section class="matching-group">
                    <div class="matching-group-head">
                        <div>
                            <h3>Pasangan benar</h3>
                            <p>Setiap baris menjadi satu kunci jawaban dan ikut dihitung dalam skor.</p>
                        </div>
                        <button type="button" class="button button-muted" data-add-matching-pair>Tambah pasangan</button>
                    </div>
                    <div class="matching-list" data-matching-pairs>
                        @foreach ($pasanganKiri as $index => $kiri)
                            <div class="soal-option-row" data-matching-pair>
                                <div class="matching-row-head">
                                    <strong data-matching-pair-title>Pasangan {{ $index + 1 }}</strong>
                                    <button type="button" class="button button-muted matching-row-remove" data-remove-matching-pair>Hapus</button>
                                </div>
                                <label for="pasangan_kiri_{{ $index }}">Pernyataan</label>
                                <textarea id="pasangan_kiri_{{ $index }}" name="pasangan_kiri[]" class="textarea" rows="2">{{ $kiri }}</textarea>
                                <input type="hidden" name="pasangan_media_kiri_key[]" value="{{ $pasanganMediaKiri[$index] }}">
                                <x-editor-media-soal :kunci="$pasanganMediaKiri[$index]" :media="data_get($mediaKonten, $pasanganMediaKiri[$index], [])" label="Media pernyataan" compact />
                                <label for="pasangan_kanan_{{ $index }}" style="margin-top: 8px;">Jawaban yang benar</label>
                                <textarea id="pasangan_kanan_{{ $index }}" name="pasangan_kanan[]" class="textarea" rows="2">{{ $pasanganKanan[$index] ?? '' }}</textarea>
                                <input type="hidden" name="pasangan_media_kanan_key[]" value="{{ $pasanganMediaKanan[$index] }}">
                                <x-editor-media-soal :kunci="$pasanganMediaKanan[$index]" :media="data_get($mediaKonten, $pasanganMediaKanan[$index], [])" label="Media jawaban" compact />
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="matching-group">
                    <div class="matching-group-head">
                        <div>
                            <h3>Jawaban pengecoh</h3>
                            <p>Opsional dan tidak menjadi kunci. Contoh: 4 pasangan benar ditambah 1 pengecoh menghasilkan 5 pilihan jawaban.</p>
                        </div>
                        <button type="button" class="button button-muted" data-add-matching-distractor>Tambah pengecoh</button>
                    </div>
                    <div class="matching-list" data-matching-distractors>
                        @foreach ($pengecohMenjodohkan as $index => $pengecoh)
                            <div class="soal-option-row" data-matching-distractor>
                                <div class="matching-row-head">
                                    <strong data-matching-distractor-title>Pengecoh {{ $index + 1 }}</strong>
                                    <button type="button" class="button button-muted matching-row-remove" data-remove-matching-distractor>Hapus</button>
                                </div>
                                <label for="pengecoh_menjodohkan_{{ $index }}">Isi jawaban pengecoh</label>
                                <textarea id="pengecoh_menjodohkan_{{ $index }}" name="pengecoh_menjodohkan[]" class="textarea" rows="2">{{ $pengecoh }}</textarea>
                                <input type="hidden" name="pengecoh_media_key[]" value="{{ $pengecohMediaKey[$index] }}">
                                <x-editor-media-soal :kunci="$pengecohMediaKey[$index]" :media="data_get($mediaKonten, $pengecohMediaKey[$index], [])" label="Media pengecoh" compact />
                            </div>
                        @endforeach
                    </div>
                </section>

                <p class="matching-summary" data-matching-summary></p>
            </div>

            <template data-matching-pair-template>
                <div class="soal-option-row" data-matching-pair>
                    <div class="matching-row-head">
                        <strong data-matching-pair-title>Pasangan</strong>
                        <button type="button" class="button button-muted matching-row-remove" data-remove-matching-pair>Hapus</button>
                    </div>
                    <label>Pernyataan</label>
                    <textarea name="pasangan_kiri[]" class="textarea" rows="2"></textarea>
                    <input type="hidden" name="pasangan_media_kiri_key[]" value="__PAIR_LEFT_KEY__">
                    <x-editor-media-soal kunci="__PAIR_LEFT_KEY__" label="Media pernyataan" compact />
                    <label style="margin-top: 8px;">Jawaban yang benar</label>
                    <textarea name="pasangan_kanan[]" class="textarea" rows="2"></textarea>
                    <input type="hidden" name="pasangan_media_kanan_key[]" value="__PAIR_RIGHT_KEY__">
                    <x-editor-media-soal kunci="__PAIR_RIGHT_KEY__" label="Media jawaban" compact />
                </div>
            </template>
            <template data-matching-distractor-template>
                <div class="soal-option-row" data-matching-distractor>
                    <div class="matching-row-head">
                        <strong data-matching-distractor-title>Pengecoh</strong>
                        <button type="button" class="button button-muted matching-row-remove" data-remove-matching-distractor>Hapus</button>
                    </div>
                    <label>Isi jawaban pengecoh</label>
                    <textarea name="pengecoh_menjodohkan[]" class="textarea" rows="2"></textarea>
                    <input type="hidden" name="pengecoh_media_key[]" value="__DISTRACTOR_KEY__">
                    <x-editor-media-soal kunci="__DISTRACTOR_KEY__" label="Media pengecoh" compact />
                </div>
            </template>
        </div>

        <div class="soal-answer-section" data-answer-section="isian_singkat uraian numerik upload_file">
            <div class="question-answer-guidance" data-answer-guidance="isian_singkat" hidden>
                <strong>Cara mengisi Isian Singkat</strong>
                <span>Isi kunci berupa nama, istilah, atau jawaban pendek. Jika ada beberapa jawaban yang sama-sama benar, pisahkan dengan tanda |. Contoh: Soekarno | Ir. Soekarno.</span>
            </div>
            <div class="question-answer-guidance" data-answer-guidance="numerik" hidden>
                <strong>Cara mengisi Numerik</strong>
                <span>Isi kunci dengan angka saja tanpa satuan. Koma dan titik desimal dianggap sama. Contoh: kunci 2,5 juga menerima jawaban 2.5.</span>
            </div>
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

    <div class="question-form-actions">
        <a href="{{ route('soal-cbt.index', ['mata_pelajaran_id' => $mataPelajaranId ?: null, 'tingkat' => $tingkatTerpilih ?: 'semua']) }}" class="button button-muted">Batal</a>
        <button type="button" class="button button-muted" data-question-preview>Pratinjau soal</button>
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
        const answerGuidance = document.querySelectorAll('[data-answer-guidance]');
        const pgKeys = document.querySelectorAll('[data-pg-key]');
        const pgkKeys = document.querySelectorAll('[data-pgk-key]');
        const pairList = document.querySelector('[data-matching-pairs]');
        const distractorList = document.querySelector('[data-matching-distractors]');
        const pairTemplate = document.querySelector('[data-matching-pair-template]');
        const distractorTemplate = document.querySelector('[data-matching-distractor-template]');
        const addPairButton = document.querySelector('[data-add-matching-pair]');
        const addDistractorButton = document.querySelector('[data-add-matching-distractor]');
        const matchingSummary = document.querySelector('[data-matching-summary]');

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

            answerGuidance.forEach((guidance) => {
                guidance.hidden = guidance.dataset.answerGuidance !== value;
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

        const syncMatching = () => {
            const pairRows = [...(pairList?.querySelectorAll('[data-matching-pair]') ?? [])];
            const distractorRows = [...(distractorList?.querySelectorAll('[data-matching-distractor]') ?? [])];

            pairRows.forEach((row, index) => {
                row.querySelector('[data-matching-pair-title]').textContent = `Pasangan ${index + 1}`;
                row.querySelector('[data-remove-matching-pair]').disabled = pairRows.length <= 1;
            });
            distractorRows.forEach((row, index) => {
                row.querySelector('[data-matching-distractor-title]').textContent = `Pengecoh ${index + 1}`;
            });

            if (addPairButton) addPairButton.disabled = pairRows.length >= 10;
            if (addDistractorButton) addDistractorButton.disabled = distractorRows.length >= 10;
            if (!matchingSummary) return;

            const pasanganLengkap = pairRows.filter((row) => (
                row.querySelector('[name="pasangan_kiri[]"]')?.value.trim()
                && row.querySelector('[name="pasangan_kanan[]"]')?.value.trim()
            )).length;
            const pilihan = new Set([
                ...pairRows.map((row) => row.querySelector('[name="pasangan_kanan[]"]')?.value.trim().toLocaleLowerCase()),
                ...distractorRows.map((row) => row.querySelector('[name="pengecoh_menjodohkan[]"]')?.value.trim().toLocaleLowerCase()),
            ].filter(Boolean));
            matchingSummary.textContent = `${pasanganLengkap} pasangan lengkap dan ${pilihan.size} pilihan jawaban akan ditampilkan kepada siswa.`;
        };

        const addMatchingRow = (list, template) => {
            if (!list || !template || list.children.length >= 10) return;
            const unique = `${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
            const html = template.innerHTML
                .replaceAll('__PAIR_LEFT_KEY__', `pasangan_${unique}_kiri`)
                .replaceAll('__PAIR_RIGHT_KEY__', `pasangan_${unique}_kanan`)
                .replaceAll('__DISTRACTOR_KEY__', `pengecoh_${unique}`);
            list.insertAdjacentHTML('beforeend', html);
            syncMatching();
            const row = list.lastElementChild;
            window.initializeQuestionMediaEditors?.(row);
            window.renderRumusSoal?.(row);
            row?.querySelector('textarea')?.focus();
        };

        addPairButton?.addEventListener('click', () => addMatchingRow(pairList, pairTemplate));
        addDistractorButton?.addEventListener('click', () => addMatchingRow(distractorList, distractorTemplate));
        pairList?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-matching-pair]');
            if (!button || pairList.children.length <= 1) return;
            button.closest('[data-matching-pair]')?.remove();
            syncMatching();
        });
        distractorList?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-matching-distractor]');
            if (!button) return;
            button.closest('[data-matching-distractor]')?.remove();
            syncMatching();
        });
        pairList?.addEventListener('input', syncMatching);
        distractorList?.addEventListener('input', syncMatching);

        contextSelect?.addEventListener('change', syncContext);
        typeInputs.forEach((input) => input.addEventListener('change', syncType));
        syncContext();
        syncType();
        syncMatching();
    })();
</script>
