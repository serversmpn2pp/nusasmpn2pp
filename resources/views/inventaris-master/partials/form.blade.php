@php
    $item = $item ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $item?->{$field} ?? $default);
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Ada data yang perlu diperbaiki.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="form-shell">
    <aside class="panel panel-pad">
        <h2 class="panel-title">Status {{ $judulSingular }}</h2>
        <p class="help-text">Data aktif akan tersedia saat barang inventaris dicatat.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">{{ ucfirst($judulSingular) }} aktif</span>
                <span class="help-text">Dapat dipilih pada data barang</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi {{ $judul }}</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="nama">{{ $labelNama }}</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" placeholder="{{ $placeholderNama }}" class="{{ $inputClass('nama') }}" required autofocus>
                    @error('nama')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kode">{{ $labelKode }}</label>
                    <input id="kode" name="kode" type="text" value="{{ $nilai('kode') }}" placeholder="{{ $placeholderKode }}" class="{{ $inputClass('kode') }}" required>
                    <p class="help-text">Kode akan dirapikan menjadi huruf besar tanpa spasi.</p>
                    @error('kode')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" class="{{ $textareaClass('deskripsi') }}" placeholder="Tuliskan keterangan singkat jika diperlukan.">{{ $nilai('deskripsi') }}</textarea>
                    @error('deskripsi')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route($routePrefix . '.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
