@php
    $kegiatanIbadah = $kegiatanIbadah ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $kegiatanIbadah?->{$field} ?? $default);
@endphp

@if ($errors->any())<div class="alert alert-danger"><strong>Ada data yang perlu diperbaiki.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="form-shell">
    <aside class="panel panel-pad">
        <h2 class="panel-title">Status kegiatan</h2>
        <p class="help-text">Kegiatan aktif dapat dipilih ketika membuat jadwal dan nantinya saat membuka pemindai.</p>
        <label class="status-toggle"><span><span class="form-label" style="margin-bottom:0;">Kegiatan aktif</span><span class="help-text">Siap digunakan</span></span><input type="hidden" name="aktif" value="0"><input type="checkbox" name="aktif" value="1" @checked((bool)$nilai('aktif', true))></label>
    </aside>
    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Identitas Kegiatan</h2>
            <div class="form-grid">
                <div class="field"><label for="nama">Nama kegiatan</label><input id="nama" name="nama" class="input @error('nama') is-invalid @enderror" value="{{ $nilai('nama') }}" placeholder="Contoh: Sholat Duhur Berjamaah" required autofocus>@error('nama')<p class="error-text">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="kode">Kode kegiatan</label><input id="kode" name="kode" class="input @error('kode') is-invalid @enderror" value="{{ $nilai('kode') }}" placeholder="Contoh: sholat_duhur" required>@error('kode')<p class="error-text">{{ $message }}</p>@enderror<p class="help-text">Digunakan sistem sebagai penanda unik. Kode <strong>sholat_jumat</strong> otomatis menerapkan peserta khusus siswa laki-laki.</p></div>
                <div class="field span-2"><label for="keterangan">Keterangan</label><textarea id="keterangan" name="keterangan" class="textarea @error('keterangan') is-invalid @enderror" rows="4" placeholder="Tujuan atau ketentuan kegiatan">{{ $nilai('keterangan') }}</textarea>@error('keterangan')<p class="error-text">{{ $message }}</p>@enderror</div>
            </div>
        </section>
        <div class="form-actions"><a href="{{ route('kegiatan-ibadah.index') }}" class="button button-muted">Batal</a><button type="submit" class="button button-primary">{{ $tombol }}</button></div>
    </div>
</div>
