<div class="field" data-status-masuk-manual hidden>
    <label>
        Status masuk
        <select name="status_masuk_manual" class="select" disabled>
            <option value="">Pilih status masuk</option>
            @foreach (\App\Models\AbsensiSiswa::STATUS_MASUK_MANUAL as $kode => $label)
                <option value="{{ $kode }}" @selected(old('status_masuk_manual', $absensi?->hadirManualTanpaJam() ? $absensi->status_masuk : null) === $kode)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    @error('status_masuk_manual')<p class="error-text">{{ $message }}</p>@enderror
</div>
