<div data-question-media-editor data-media-key="{{ $key }}">
    <div data-image-preview>
        @if (filled(data_get($media, 'gambar.path')))
            <img src="{{ Storage::disk('public')->url(data_get($media, 'gambar.path')) }}" alt="{{ data_get($media, 'gambar.alt', 'Gambar pendukung soal') }}">
        @endif
    </div>
    <input name="gambar_alt" value="{{ data_get($media, 'gambar.alt') }}">
    <input name="gambar_keterangan" value="{{ data_get($media, 'gambar.keterangan') }}">
    <input name="tabel_judul" value="{{ data_get($media, 'tabel.judul') }}">
    <input data-table-value value="{{ filled(data_get($media, 'tabel.baris')) ? json_encode(data_get($media, 'tabel.baris')) : '' }}">
    <input data-formula-input value="{{ data_get($media, 'rumus.latex') }}">
    <input name="rumus_keterangan" value="{{ data_get($media, 'rumus.keterangan') }}">
</div>
