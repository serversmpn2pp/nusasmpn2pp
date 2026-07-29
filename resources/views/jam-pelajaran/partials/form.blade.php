@php
    $jamPelajaran = $jamPelajaran ?? null;
    $sedangEdit = filled($jamPelajaran);
    $nilai = fn (string $field, mixed $default = '') => old($field, $jamPelajaran?->{$field} ?? $default);
    $hariBerlaku = collect(old('hari', $sedangEdit ? [$jamPelajaran->hari] : []))
        ->map(fn ($hari) => (string) $hari);
    $hariTujuan = collect(old('hari_tujuan', []))
        ->map(fn ($hari) => (string) $hari);
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
    $formatJam = function (string $field) use ($nilai) {
        $value = $nilai($field);

        return $value ? substr((string) $value, 0, 5) : '';
    };
@endphp

<style>
    .day-choice-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 9px;
        margin-top: 8px;
    }

    .day-choice {
        display: flex;
        align-items: center;
        gap: 9px;
        min-height: 48px;
        padding: 10px 12px;
        border: 1px solid #d7e1ea;
        border-radius: 7px;
        background: #fff;
        cursor: pointer;
    }

    .day-choice:has(input:checked) {
        border-color: #15477a;
        background: #eef5fb;
        box-shadow: inset 3px 0 0 #f1c40f;
    }

    .day-choice input {
        width: 18px;
        height: 18px;
        flex: 0 0 auto;
        accent-color: #15477a;
    }

    .day-primary {
        display: flex;
        align-items: center;
        min-height: 48px;
        margin-top: 8px;
        padding: 10px 12px;
        border-left: 4px solid #f1c40f;
        background: #eef5fb;
        color: #15477a;
        font-weight: 700;
    }

    @media (max-width: 760px) {
        .day-choice-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>

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
        <h2 class="panel-title">Status jam</h2>
        <p class="help-text">Jam aktif bisa dipakai saat menyusun jadwal pelajaran.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Jam aktif</span>
                <span class="help-text">Tersedia untuk jadwal</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Jam Pelajaran</h2>
            <div class="form-grid">
                @if ($sedangEdit)
                    <div class="field span-2">
                        <label>Hari utama</label>
                        <div class="day-primary">{{ $daftarHari[$jamPelajaran->hari] ?? ucfirst($jamPelajaran->hari) }}</div>
                        <input type="hidden" name="hari" value="{{ $jamPelajaran->hari }}">
                        <p class="help-text">Data utama ini tetap pada hari yang sama agar jadwal yang sudah tersusun tidak terputus.</p>
                        @error('hari')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field span-2">
                        <label>Terapkan juga ke hari lain <span class="help-text">(opsional)</span></label>
                        <div class="day-choice-grid">
                            @foreach ($daftarHari as $kode => $label)
                                @continue($kode === $jamPelajaran->hari)
                                <label class="day-choice">
                                    <input
                                        type="checkbox"
                                        name="hari_tujuan[]"
                                        value="{{ $kode }}"
                                        @checked($hariTujuan->contains($kode))
                                    >
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="help-text">Slot dengan urutan asal yang sama pada hari tujuan akan ikut diperbarui dan dipindahkan ke posisi pilihan.</p>
                        @error('hari_tujuan')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                        @error('hari_tujuan.*')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <div class="field span-2">
                        <label>Hari berlaku</label>
                        <div class="day-choice-grid">
                        @foreach ($daftarHari as $kode => $label)
                                <label class="day-choice">
                                    <input
                                        type="checkbox"
                                        name="hari[]"
                                        value="{{ $kode }}"
                                        @checked($hariBerlaku->contains($kode))
                                        @if ($loop->first) autofocus @endif
                                    >
                                    <span>{{ $label }}</span>
                                </label>
                        @endforeach
                        </div>
                        <p class="help-text">Pilih satu atau beberapa hari. Setiap hari akan mendapat slot baru dan urutan berikutnya digeser otomatis.</p>
                        @error('hari')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                        @error('hari.*')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                @if ($sedangEdit)
                    <div class="field">
                        <label for="posisi_pindah">Posisi jam</label>
                        <input type="hidden" name="nomor_jam" value="{{ $jamPelajaran->nomor_jam }}">
                        <select id="posisi_pindah" name="posisi_pindah" class="{{ $selectClass('posisi_pindah') }}">
                            <option value="tetap" @selected($nilai('posisi_pindah', 'tetap') === 'tetap')>
                                Pertahankan urutan {{ $jamPelajaran->nomor_jam }}
                            </option>
                            @for ($nomor = 1; $nomor <= ($nomorUrutMaksimal ?? $jamPelajaran->nomor_jam); $nomor++)
                                @continue($nomor === $jamPelajaran->nomor_jam)
                                <option value="urutan:{{ $nomor }}" @selected($nilai('posisi_pindah') === 'urutan:'.$nomor)>
                                    Pindahkan menjadi urutan {{ $nomor }}
                                </option>
                            @endfor
                        </select>
                        <p class="help-text">ID slot dan jadwal kelas tetap terhubung. Slot lain akan bergeser otomatis.</p>
                        @error('nomor_jam')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                        @error('posisi_pindah')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <div class="field">
                        <label for="posisi_sisip">Posisi jam baru</label>
                        <select id="posisi_sisip" name="posisi_sisip" class="{{ $selectClass('posisi_sisip') }}" required>
                            <option value="akhir" @selected($nilai('posisi_sisip', 'akhir') === 'akhir')>Di akhir jadwal</option>
                            <option value="awal" @selected($nilai('posisi_sisip') === 'awal')>Di awal jadwal</option>
                            @for ($nomor = 1; $nomor <= ($nomorUrutMaksimal ?? 1); $nomor++)
                                <option value="setelah:{{ $nomor }}" @selected($nilai('posisi_sisip') === 'setelah:'.$nomor)>
                                    Setelah urutan {{ $nomor }}
                                </option>
                            @endfor
                        </select>
                        <p class="help-text">Contoh: pilih “Setelah urutan 2” untuk menyisipkan slot baru di antara urutan 2 dan 3.</p>
                        @error('posisi_sisip')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div class="field">
                    <label for="label">Label</label>
                    <input id="label" name="label" type="text" value="{{ $nilai('label') }}" class="{{ $inputClass('label') }}" placeholder="Contoh: Jam 1">
                    @error('label')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jenis">Jenis slot</label>
                    <select id="jenis" name="jenis" class="{{ $selectClass('jenis') }}" required>
                        @foreach ($daftarJenis as $kode => $label)
                            <option value="{{ $kode }}" @selected($nilai('jenis', 'pelajaran') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('jenis')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_mulai">Jam mulai</label>
                    <input id="jam_mulai" name="jam_mulai" type="time" value="{{ $formatJam('jam_mulai') }}" class="{{ $inputClass('jam_mulai') }}" required>
                    @error('jam_mulai')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_selesai">Jam selesai</label>
                    <input id="jam_selesai" name="jam_selesai" type="time" value="{{ $formatJam('jam_selesai') }}" class="{{ $inputClass('jam_selesai') }}" required>
                    @error('jam_selesai')
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
            <a href="{{ route('jam-pelajaran.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
