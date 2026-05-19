@php
    $skemaBobotNilai = $skemaBobotNilai ?? null;
    $tahunAktifId = $tahunPelajaran->firstWhere('aktif', true)?->id;
    $nilai = fn (string $field, mixed $default = '') => old($field, $skemaBobotNilai?->{$field} ?? $default);
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
    $totalBobot = (int) $nilai('bobot_formatif', 35)
        + (int) $nilai('bobot_sumatif', 25)
        + (int) $nilai('bobot_sts', 15)
        + (int) $nilai('bobot_sas_saj', 25);
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
        <h2 class="panel-title">Status skema</h2>
        <p class="help-text">Skema aktif akan dipakai sebagai acuan hitung nilai rapor untuk semester dan tingkat terkait.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Skema aktif</span>
                <span class="help-text">Tampil dalam proses nilai</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>

        <div class="panel" style="margin-top: 16px; padding: 14px; box-shadow: none;">
            <p class="stat-label">Total bobot</p>
            <p class="stat-value" style="font-size: 1.4rem;">{{ $totalBobot }}%</p>
            <p class="help-text">Total harus 100%.</p>
        </div>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Ruang Lingkup</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                    <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="{{ $selectClass('tahun_pelajaran_id') }}" required autofocus>
                        <option value="">Pilih tahun pelajaran</option>
                        @foreach ($tahunPelajaran as $item)
                            <option value="{{ $item->id }}" @selected((string) $nilai('tahun_pelajaran_id', $tahunPelajaranDipilih ?? $tahunAktifId) === (string) $item->id)>
                                {{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('tahun_pelajaran_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" class="{{ $selectClass('semester') }}" required>
                        <option value="">Pilih semester</option>
                        <option value="ganjil" @selected($nilai('semester') === 'ganjil')>Ganjil</option>
                        <option value="genap" @selected($nilai('semester') === 'genap')>Genap</option>
                    </select>
                    @error('semester')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="tingkat">Tingkat</label>
                    <select id="tingkat" name="tingkat" class="{{ $selectClass('tingkat') }}">
                        <option value="">Semua tingkat</option>
                        <option value="7" @selected((string) $nilai('tingkat') === '7')>VII</option>
                        <option value="8" @selected((string) $nilai('tingkat') === '8')>VIII</option>
                        <option value="9" @selected((string) $nilai('tingkat') === '9')>IX</option>
                    </select>
                    @error('tingkat')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Bobot Nilai Rapor</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="bobot_formatif">Formatif (%)</label>
                    <input id="bobot_formatif" name="bobot_formatif" type="number" min="0" max="100" value="{{ $nilai('bobot_formatif', 35) }}" class="{{ $inputClass('bobot_formatif') }}" required>
                    @error('bobot_formatif')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="bobot_sumatif">Sumatif (%)</label>
                    <input id="bobot_sumatif" name="bobot_sumatif" type="number" min="0" max="100" value="{{ $nilai('bobot_sumatif', 25) }}" class="{{ $inputClass('bobot_sumatif') }}" required>
                    @error('bobot_sumatif')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="bobot_sts">STS (%)</label>
                    <input id="bobot_sts" name="bobot_sts" type="number" min="0" max="100" value="{{ $nilai('bobot_sts', 15) }}" class="{{ $inputClass('bobot_sts') }}" required>
                    @error('bobot_sts')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="bobot_sas_saj">SAS/SAJ (%)</label>
                    <input id="bobot_sas_saj" name="bobot_sas_saj" type="number" min="0" max="100" value="{{ $nilai('bobot_sas_saj', 25) }}" class="{{ $inputClass('bobot_sas_saj') }}" required>
                    @error('bobot_sas_saj')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" class="{{ $textareaClass('keterangan') }}">{{ $nilai('keterangan') }}</textarea>
                    @error('keterangan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('skema-bobot-nilai.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
