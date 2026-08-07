@php
    $item = $pertanyaanSurveiPembelajaran ?? null;
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Data belum dapat disimpan.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<section class="panel panel-pad survey-statement-form">
    <div class="field">
        <label for="pernyataan">Pernyataan survei</label>
        <textarea
            id="pernyataan"
            name="pernyataan"
            class="textarea @error('pernyataan') is-invalid @enderror"
            rows="5"
            maxlength="500"
            required
            autofocus
        >{{ old('pernyataan', $item?->pernyataan) }}</textarea>
        @error('pernyataan')
            <p class="error-text">{{ $message }}</p>
        @enderror
    </div>

    <div class="field survey-statement-order">
        <label for="urutan">Urutan tampil</label>
        <input
            id="urutan"
            name="urutan"
            type="number"
            class="input @error('urutan') is-invalid @enderror"
            min="1"
            max="999"
            value="{{ old('urutan', $item?->urutan ?? $urutanBerikutnya ?? 1) }}"
            required
        >
        @error('urutan')
            <p class="error-text">{{ $message }}</p>
        @enderror
    </div>

    @unless ($item)
        <label class="survey-statement-check">
            <input type="checkbox" name="aktif" value="1" @checked(old('aktif', true))>
            <span>Aktifkan pernyataan</span>
        </label>
    @endunless
</section>

<div class="actions survey-statement-form-actions">
    <a href="{{ route('pertanyaan-survei-pembelajaran.index') }}" class="button button-muted">Batal</a>
    <button type="submit" class="button button-primary">{{ $tombolSimpan }}</button>
</div>
