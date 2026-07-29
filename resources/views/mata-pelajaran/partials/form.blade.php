@php
    $mataPelajaran = $mataPelajaran ?? null;
    $pengaturanTersimpan = $mataPelajaran?->pengaturanTingkat?->keyBy('tingkat') ?? collect();
    $nilai = fn (string $field, mixed $default = '') => old($field, $mataPelajaran?->{$field} ?? $default);
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
    $labelTingkat = [7 => 'VII', 8 => 'VIII', 9 => 'IX'];
    $menggunakanPredikatAwal = \App\Models\MataPelajaran::kelompokMenggunakanPredikat(
        old('kelompok', $mataPelajaran?->kelompok),
    );
    $urlGantiTahun = $mataPelajaran
        ? route('mata-pelajaran.edit', $mataPelajaran)
        : route('mata-pelajaran.create');
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
        <h2 class="panel-title">Status mapel</h2>
        <p class="help-text">Satu nama mata pelajaran dapat digunakan pada beberapa tingkat dengan pengaturan yang sesuai jenis penilaiannya.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Mata pelajaran aktif</span>
                <span class="help-text">Tampil dalam proses akademik</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>

        <div class="field" style="margin-top: 22px;">
            <label for="tahun_pelajaran_id">Tahun pengaturan</label>
            <select
                id="tahun_pelajaran_id"
                name="tahun_pelajaran_id"
                class="{{ $selectClass('tahun_pelajaran_id') }}"
                data-year-switch="{{ $urlGantiTahun }}"
                required
            >
                @foreach ($tahunPelajaran as $item)
                    <option value="{{ $item->id }}" @selected((int) old('tahun_pelajaran_id', $tahunPelajaranId) === (int) $item->id)>
                        {{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}
                    </option>
                @endforeach
            </select>
            <p class="help-text">Kode dan pengaturan nilai disimpan per tahun pelajaran.</p>
            @error('tahun_pelajaran_id')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Identitas Mata Pelajaran</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="nama">Nama mata pelajaran</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" placeholder="Contoh: Matematika" class="{{ $inputClass('nama') }}" required autofocus>
                    <p class="help-text">Tidak perlu menambahkan VII, VIII, atau IX pada nama.</p>
                    @error('nama')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kelompok">Jenis / kelompok</label>
                    <select id="kelompok" name="kelompok" class="{{ $selectClass('kelompok') }}">
                        <option value="">Belum dipilih</option>
                        <optgroup label="Mata pelajaran">
                            @foreach (['Umum', 'Agama dan Budi Pekerti', 'Muatan Lokal', 'Pilihan'] as $kelompok)
                                <option value="{{ $kelompok }}" @selected($nilai('kelompok') === $kelompok)>{{ $kelompok }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Kegiatan nonpelajaran">
                            @foreach (['Kokurikuler', 'Ekstrakurikuler', 'Pengembangan Diri'] as $kelompok)
                                <option value="{{ $kelompok }}" @selected($nilai('kelompok') === $kelompok)>{{ $kelompok }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                    <p class="help-text">Kegiatan nonpelajaran memakai predikat dan tidak memerlukan KKM.</p>
                    @error('kelompok')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label>Jenis penilaian</label>
                    <div class="input" data-assessment-label style="display: flex; align-items: center; min-height: 44px;">
                        Angka (0-100)
                    </div>
                    <p class="help-text" data-assessment-help">KKM/KKTP wajib diisi untuk setiap tingkat aktif.</p>
                </div>

                <div class="field">
                    <label for="urutan">Urutan tampil</label>
                    <input id="urutan" name="urutan" type="number" min="0" max="999" value="{{ $nilai('urutan', 0) }}" class="{{ $inputClass('urutan') }}">
                    @error('urutan')
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

        <section class="panel panel-pad">
            <div style="margin-bottom: 8px;">
                <h2 class="panel-title" data-level-title>Pengaturan per Tingkat</h2>
                <p class="help-text" data-level-help>Aktifkan tingkat yang memakai mata pelajaran ini, lalu isi kode dan KKM/KKTP-nya.</p>
            </div>

            @error('pengaturan')
                <p class="error-text" style="margin-bottom: 12px;">{{ $message }}</p>
            @enderror

            @foreach ($labelTingkat as $tingkat => $label)
                @php
                    $tersimpan = $pengaturanTersimpan->get($tingkat);
                    $aktif = (bool) old("pengaturan.{$tingkat}.aktif", $tersimpan?->aktif ?? ($mataPelajaran === null));
                    $kode = old("pengaturan.{$tingkat}.kode", $tersimpan?->kode);
                    $kkm = old("pengaturan.{$tingkat}.kkm", $tersimpan?->kkm);
                @endphp

                <div style="border-top: 1px solid #dce5ee; padding: 18px 0;">
                    <div class="form-grid" style="align-items: end;">
                        <label class="status-toggle" style="margin: 0;">
                            <span>
                                <span class="form-label" style="margin-bottom: 0;">Kelas {{ $label }}</span>
                                <span class="help-text">Gunakan pada tingkat {{ $label }}</span>
                            </span>
                            <input type="hidden" name="pengaturan[{{ $tingkat }}][aktif]" value="0">
                            <input
                                type="checkbox"
                                name="pengaturan[{{ $tingkat }}][aktif]"
                                value="1"
                                data-level-toggle="{{ $tingkat }}"
                                @checked($aktif)
                            >
                        </label>

                        <div class="field">
                            <label for="kode_{{ $tingkat }}">Kode</label>
                            <input
                                id="kode_{{ $tingkat }}"
                                name="pengaturan[{{ $tingkat }}][kode]"
                                type="text"
                                value="{{ $kode }}"
                                placeholder="Contoh: MTK{{ $tingkat }} atau PRAM{{ $tingkat }}"
                                class="{{ $inputClass("pengaturan.{$tingkat}.kode") }}"
                                data-level-input="{{ $tingkat }}"
                            >
                            @error("pengaturan.{$tingkat}.kode")
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field" data-kkm-field @if($menggunakanPredikatAwal) hidden @endif>
                            <label for="kkm_{{ $tingkat }}">KKM/KKTP</label>
                            <input
                                id="kkm_{{ $tingkat }}"
                                name="pengaturan[{{ $tingkat }}][kkm]"
                                type="number"
                                min="0"
                                max="100"
                                value="{{ $kkm }}"
                                placeholder="Contoh: 75"
                                class="{{ $inputClass("pengaturan.{$tingkat}.kkm") }}"
                                data-level-input="{{ $tingkat }}"
                                data-kkm-input
                            >
                            @error("pengaturan.{$tingkat}.kkm")
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        <div class="form-actions">
            <a href="{{ route('mata-pelajaran.index', ['tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (() => {
            const yearSelect = document.querySelector('[data-year-switch]');
            const originalYear = yearSelect?.value;
            const groupSelect = document.getElementById('kelompok');
            const assessmentLabel = document.querySelector('[data-assessment-label]');
            const assessmentHelp = document.querySelector('[data-assessment-help]');
            const levelTitle = document.querySelector('[data-level-title]');
            const levelHelp = document.querySelector('[data-level-help]');
            const predicateGroups = @json(\App\Models\MataPelajaran::KELOMPOK_PENILAIAN_PREDIKAT);
            let usesPredicate = false;

            yearSelect?.addEventListener('change', () => {
                if (yearSelect.value === originalYear) {
                    return;
                }

                const url = new URL(yearSelect.dataset.yearSwitch, window.location.origin);
                url.searchParams.set('tahun_pelajaran_id', yearSelect.value);
                window.location.href = url.toString();
            });

            const syncLevel = (toggle) => {
                document.querySelectorAll(`[data-level-input="${toggle.dataset.levelToggle}"]`)
                    .forEach((input) => {
                        const disabledForPredicate = usesPredicate && input.hasAttribute('data-kkm-input');
                        input.disabled = !toggle.checked || disabledForPredicate;
                        input.required = toggle.checked && !disabledForPredicate;
                    });
            };

            document.querySelectorAll('[data-level-toggle]').forEach((toggle) => {
                toggle.addEventListener('change', () => syncLevel(toggle));
            });

            const syncAssessment = () => {
                usesPredicate = predicateGroups.includes(groupSelect?.value);

                document.querySelectorAll('[data-kkm-field]').forEach((field) => {
                    field.hidden = usesPredicate;
                });

                if (assessmentLabel) {
                    assessmentLabel.textContent = usesPredicate
                        ? 'Predikat (SB / B / C / K)'
                        : 'Angka (0-100)';
                }

                if (assessmentHelp) {
                    assessmentHelp.textContent = usesPredicate
                        ? 'Tidak menggunakan KKM/KKTP.'
                        : 'KKM/KKTP wajib diisi untuk setiap tingkat aktif.';
                }

                if (levelHelp) {
                    levelHelp.textContent = usesPredicate
                        ? 'Pilih tingkat VII, VIII, dan/atau IX yang mengikuti kegiatan ini, lalu isi kodenya.'
                        : 'Aktifkan tingkat yang memakai mata pelajaran ini, lalu isi kode dan KKM/KKTP-nya.';
                }

                if (levelTitle) {
                    levelTitle.textContent = usesPredicate
                        ? 'Diterapkan pada Tingkat'
                        : 'Pengaturan per Tingkat';
                }

                document.querySelectorAll('[data-level-toggle]').forEach(syncLevel);
            };

            groupSelect?.addEventListener('change', syncAssessment);
            syncAssessment();
        })();
    </script>
@endpush
