@props([
    'kunci' => null,
    'media' => [],
    'label' => 'Media pendukung',
    'compact' => false,
])

@php
    $bersarang = filled($kunci);
    $idEditor = 'media-soal-' . ($bersarang ? $kunci : 'utama');
    $nama = function (string $field) use ($bersarang, $kunci): string {
        if ($bersarang) {
            return "media_konten[{$kunci}][{$field}]";
        }

        return match ($field) {
            'gambar' => 'gambar_soal',
            'hapus_gambar' => 'hapus_gambar_soal',
            'tabel' => 'media_tabel',
            default => $field,
        };
    };
    $kunciLama = function (string $field) use ($bersarang, $kunci): string {
        if ($bersarang) {
            return "media_konten.{$kunci}.{$field}";
        }

        return match ($field) {
            'gambar' => 'gambar_soal',
            'hapus_gambar' => 'hapus_gambar_soal',
            'tabel' => 'media_tabel',
            default => $field,
        };
    };
    $gambar = data_get($media, 'gambar');
    $gambarUrl = filled(data_get($gambar, 'path'))
        ? \Illuminate\Support\Facades\Storage::disk('public')->url(data_get($gambar, 'path'))
        : '';
    $barisTabel = data_get($media, 'tabel.baris', []);
    $nilaiTabel = old($kunciLama('tabel'), $barisTabel === [] ? '' : json_encode($barisTabel, JSON_UNESCAPED_UNICODE));
    $jumlahBaris = max(2, min(10, count($barisTabel) ?: 3));
    $jumlahKolom = max(2, min(8, count($barisTabel[0] ?? []) ?: 3));
    $punyaMedia = filled($gambarUrl) || filled($nilaiTabel) || filled(old($kunciLama('rumus_latex'), data_get($media, 'rumus.latex')));
@endphp

<div
    class="question-media-editor{{ $compact ? ' is-compact' : '' }}"
    data-question-media-editor
    data-media-key="{{ $kunci ?: 'utama' }}"
    data-current-image="{{ $gambarUrl }}"
>
    <div class="question-media-toolbar">
        <div class="question-media-toolbar-copy">
            <strong>{{ $label }}</strong>
            <span>Opsional. Tambahkan gambar, tabel, atau rumus jika diperlukan.</span>
        </div>
        <button type="button" class="button button-muted question-media-button" data-media-toggle="gambar">Gambar</button>
        <button type="button" class="button button-muted question-media-button" data-media-toggle="tabel">Tabel</button>
        <button type="button" class="button button-muted question-media-button" data-media-toggle="rumus">Rumus</button>
        <span class="question-media-status" data-media-status @if (! $punyaMedia) hidden @endif>Media ditambahkan</span>
    </div>

    <section class="question-media-panel" data-media-panel="gambar" hidden>
        <div class="question-media-panel-head">
            <h3>Gambar</h3>
            <p>Gunakan JPG, PNG, atau WebP dengan ukuran maksimal 5 MB.</p>
        </div>
        <div class="question-image-editor">
            <div class="question-image-preview" data-image-preview>
                @if ($gambarUrl)
                    <img src="{{ $gambarUrl }}" alt="{{ data_get($gambar, 'alt', 'Gambar pendukung soal') }}">
                @else
                    <span>Belum ada gambar</span>
                @endif
            </div>
            <div class="section-stack" style="gap: 11px;">
                <div class="field">
                    <label for="{{ $idEditor }}-gambar">Pilih gambar</label>
                    <input id="{{ $idEditor }}-gambar" name="{{ $nama('gambar') }}" type="file" accept="image/jpeg,image/png,image/webp" class="input" data-image-input>
                    @error($kunciLama('gambar')) <p class="error-text">{{ $message }}</p> @enderror
                    <p class="error-text" data-image-error hidden></p>
                </div>
                <div class="field">
                    <label for="{{ $idEditor }}-alt">Deskripsi gambar</label>
                    <input id="{{ $idEditor }}-alt" name="{{ $nama('gambar_alt') }}" type="text" maxlength="160" value="{{ old($kunciLama('gambar_alt'), data_get($gambar, 'alt')) }}" class="input" placeholder="Contoh: Grafik hubungan waktu dan simpangan">
                    <p class="help-text">Membantu pembaca layar. Jika kolom berikutnya kosong, deskripsi ini juga menjadi keterangan cadangan.</p>
                </div>
                <div class="field">
                    <label for="{{ $idEditor }}-keterangan">Keterangan yang tampil di bawah gambar</label>
                    <input id="{{ $idEditor }}-keterangan" name="{{ $nama('gambar_keterangan') }}" type="text" maxlength="220" value="{{ old($kunciLama('gambar_keterangan'), data_get($gambar, 'keterangan')) }}" class="input" placeholder="Contoh: Perhatikan bagian yang diberi tanda panah">
                </div>
                <input type="hidden" name="{{ $nama('hapus_gambar') }}" value="0" data-remove-image>
                <button type="button" class="button button-muted" data-clear-image>Hapus gambar</button>
            </div>
        </div>
    </section>

    <section class="question-media-panel" data-media-panel="tabel" hidden>
        <div class="question-media-panel-head">
            <h3>Tabel</h3>
            <p>Pilih jumlah baris dan kolom. Baris pertama menjadi kepala tabel.</p>
        </div>
        <div class="question-table-controls">
            <div class="field">
                <label>Baris</label>
                <select class="select" data-table-rows>
                    @foreach (range(2, 10) as $jumlah)
                        <option value="{{ $jumlah }}" @selected($jumlahBaris === $jumlah)>{{ $jumlah }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Kolom</label>
                <select class="select" data-table-columns>
                    @foreach (range(2, 8) as $jumlah)
                        <option value="{{ $jumlah }}" @selected($jumlahKolom === $jumlah)>{{ $jumlah }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="flex: 1 1 220px;">
                <label for="{{ $idEditor }}-judul-tabel">Judul tabel</label>
                <input id="{{ $idEditor }}-judul-tabel" name="{{ $nama('tabel_judul') }}" type="text" maxlength="160" value="{{ old($kunciLama('tabel_judul'), data_get($media, 'tabel.judul')) }}" class="input" placeholder="Opsional">
            </div>
            <button type="button" class="button button-muted" data-clear-table>Hapus tabel</button>
        </div>
        <input type="hidden" name="{{ $nama('tabel') }}" value="{{ $nilaiTabel }}" data-table-value>
        @error($kunciLama('tabel')) <p class="error-text">{{ $message }}</p> @enderror
        <div class="question-table-editor" data-table-editor></div>
    </section>

    <section class="question-media-panel" data-media-panel="rumus" hidden>
        <div class="question-media-panel-head">
            <h3>Rumus matematika</h3>
            <p>Rumus tampil langsung dalam bentuk yang akan dilihat siswa.</p>
        </div>
        <div class="field">
            <label>Isi rumus</label>
            <div class="question-math-field" data-formula-field>Menyiapkan editor rumus...</div>
            <input type="hidden" name="{{ $nama('rumus_latex') }}" value="{{ old($kunciLama('rumus_latex'), data_get($media, 'rumus.latex')) }}" data-formula-input>
            @error($kunciLama('rumus_latex')) <p class="error-text">{{ $message }}</p> @enderror
        </div>
        <div class="question-formula-tools" aria-label="Bentuk rumus yang sering digunakan">
            <button type="button" class="button button-muted question-formula-template" data-formula-template="\frac{#0}{#?}" title="Pecahan"><span data-rumus-latex="\frac{a}{b}">a/b</span></button>
            <button type="button" class="button button-muted question-formula-template" data-formula-template="\sqrt{#0}" title="Akar"><span data-rumus-latex="\sqrt{x}">akar x</span></button>
            <button type="button" class="button button-muted question-formula-template" data-formula-template="#0^{#?}" title="Pangkat"><span data-rumus-latex="x^{2}">x pangkat 2</span></button>
            <button type="button" class="button button-muted question-formula-template" data-formula-template="#0_{#?}" title="Indeks"><span data-rumus-latex="x_{1}">x indeks 1</span></button>
            <button type="button" class="button button-muted question-formula-template" data-formula-template="\times" title="Kali"><span data-rumus-latex="\times">kali</span></button>
            <button type="button" class="button button-muted question-formula-template" data-formula-template="\div" title="Bagi"><span data-rumus-latex="\div">bagi</span></button>
        </div>
        <div class="field" style="margin-top: 10px;">
            <label for="{{ $idEditor }}-keterangan-rumus">Keterangan rumus</label>
            <input id="{{ $idEditor }}-keterangan-rumus" name="{{ $nama('rumus_keterangan') }}" type="text" maxlength="220" value="{{ old($kunciLama('rumus_keterangan'), data_get($media, 'rumus.keterangan')) }}" class="input" placeholder="Opsional, contoh: n adalah jumlah getaran">
        </div>
    </section>
</div>
