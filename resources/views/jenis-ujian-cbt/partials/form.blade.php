@php
    $jenisUjianCbt = $jenisUjianCbt ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $jenisUjianCbt?->{$field} ?? $default);
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
        <h2 class="panel-title">Aturan CBT</h2>
        <p class="help-text">Pengaturan ini menjadi nilai bawaan saat paket ujian CBT dibuat dari jenis ujian ini.</p>

        <label class="status-toggle" style="margin-top: 16px;">
            <span>
                <span class="form-label" style="margin-bottom:0">Jenis ujian aktif</span>
                <span class="help-text">Tersedia untuk pembuatan paket ujian</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>

        <label class="status-toggle" style="margin-top: 12px;">
            <span>
                <span class="form-label" style="margin-bottom:0">Memerlukan token</span>
                <span class="help-text">Siswa perlu token dari proktor saat mulai ujian</span>
            </span>
            <input type="hidden" name="memerlukan_token" value="0">
            <input type="checkbox" name="memerlukan_token" value="1" @checked((bool) $nilai('memerlukan_token', true))>
        </label>

        <label class="status-toggle" style="margin-top: 12px;">
            <span>
                <span class="form-label" style="margin-bottom:0">Diterapkan ke nilai</span>
                <span class="help-text">Hasil ujian dapat masuk ke komponen nilai</span>
            </span>
            <input type="hidden" name="dapat_diterapkan_ke_nilai" value="0">
            <input type="checkbox" name="dapat_diterapkan_ke_nilai" value="1" @checked((bool) $nilai('dapat_diterapkan_ke_nilai', true))>
        </label>

        <label class="status-toggle" style="margin-top: 12px;">
            <span>
                <span class="form-label" style="margin-bottom:0">Tampil di kartu peserta</span>
                <span class="help-text">Nama jenis ujian dicetak pada kartu peserta</span>
            </span>
            <input type="hidden" name="tampil_di_kartu_peserta" value="0">
            <input type="checkbox" name="tampil_di_kartu_peserta" value="1" @checked((bool) $nilai('tampil_di_kartu_peserta', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Jenis Ujian</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="nama">Nama jenis ujian</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" placeholder="Contoh: Sumatif Tengah Semester" class="{{ $inputClass('nama') }}" required autofocus>
                    @error('nama')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kode">Kode jenis ujian</label>
                    <input id="kode" name="kode" type="text" value="{{ $nilai('kode') }}" placeholder="Contoh: STS" class="{{ $inputClass('kode') }}" required>
                    <p class="help-text">Kode akan dirapikan menjadi huruf besar tanpa spasi.</p>
                    @error('kode')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="urutan">Urutan tampil</label>
                    <input id="urutan" name="urutan" type="number" min="0" max="999" value="{{ $nilai('urutan', 0) }}" placeholder="Contoh: 1" class="{{ $inputClass('urutan') }}">
                    @error('urutan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" class="{{ $textareaClass('deskripsi') }}" placeholder="Tuliskan kegunaan jenis ujian ini, misalnya untuk STS, SAS, simulasi AN, atau OSN.">{{ $nilai('deskripsi') }}</textarea>
                    @error('deskripsi')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('jenis-ujian-cbt.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
