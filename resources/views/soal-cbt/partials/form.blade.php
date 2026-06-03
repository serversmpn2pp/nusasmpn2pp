@php
    $soalCbt = $soalCbt ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $soalCbt?->{$field} ?? $default);
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');

    $opsiPilihan = old('opsi', $soalCbt?->opsi['pilihan'] ?? ['A' => '', 'B' => '', 'C' => '', 'D' => '']);
    $opsiPilihan = array_merge(['A' => '', 'B' => '', 'C' => '', 'D' => ''], $opsiPilihan ?: []);
    $jawaban = $soalCbt?->kunci_jawaban['jawaban'] ?? null;
    $kunciPg = old('kunci_pg', is_string($jawaban) ? $jawaban : '');
    $kunciPgk = old('kunci_pgk', is_array($jawaban) ? $jawaban : []);

    $pernyataanAwal = collect($soalCbt?->opsi['pernyataan'] ?? [])->pluck('teks')->all();
    $jawabanBsAwal = collect($soalCbt?->kunci_jawaban['jawaban'] ?? [])->map(fn ($value) => $value ? 'benar' : 'salah')->values()->all();
    $pernyataan = array_pad(old('pernyataan', $pernyataanAwal), 4, '');
    $jawabanBs = array_pad(old('jawaban_bs', $jawabanBsAwal), 4, 'benar');

    $pasanganAwal = collect($soalCbt?->opsi['pasangan'] ?? []);
    $pasanganKiri = array_pad(old('pasangan_kiri', $pasanganAwal->pluck('kiri')->all()), 4, '');
    $pasanganKanan = array_pad(old('pasangan_kanan', $pasanganAwal->pluck('kanan')->all()), 4, '');

    $kunciTeks = old('kunci_teks', is_string($jawaban) ? $jawaban : '');
    $rubrikTeks = old('rubrik_teks', $soalCbt?->rubrik['catatan'] ?? '');
@endphp

<style>
    .soal-answer-section {
        display: none;
    }

    .soal-answer-section.is-active {
        display: block;
    }

    .soal-option-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 12px;
    }

    .soal-option-row {
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 12px;
        background: #fff;
    }

    .soal-option-label {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-bottom: 8px;
        color: var(--primary-dark);
        font-weight: 700;
    }

    .soal-option-label input {
        flex: 0 0 auto;
    }

    @media (max-width: 760px) {
        .soal-option-grid {
            grid-template-columns: 1fr;
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
        <h2 class="panel-title">Status soal</h2>
        <p class="help-text">Gunakan draft selama soal masih disusun. Ubah ke siap saat sudah layak masuk paket ujian.</p>

        <div class="field" style="margin-top: 16px;">
            <label for="status">Status</label>
            <select id="status" name="status" class="{{ $selectClass('status') }}" required>
                @foreach ($daftarStatus as $kode => $label)
                    <option value="{{ $kode }}" @selected($nilai('status', 'draft') === $kode)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <label class="status-toggle" style="margin-top: 14px;">
            <span>
                <span class="form-label" style="margin-bottom:0">Soal aktif</span>
                <span class="help-text">Soal bisa dipilih untuk paket CBT</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>

        <div class="field" style="margin-top: 16px;">
            <label for="skor_maksimal">Skor maksimal</label>
            <input id="skor_maksimal" name="skor_maksimal" type="number" min="0.25" max="100" step="0.25" value="{{ $nilai('skor_maksimal', 1) }}" class="{{ $inputClass('skor_maksimal') }}" required>
            @error('skor_maksimal') <p class="error-text">{{ $message }}</p> @enderror
        </div>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Identitas Soal</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="kode">Kode soal</label>
                    <input id="kode" name="kode" type="text" value="{{ $nilai('kode', $kodeSaran) }}" class="{{ $inputClass('kode') }}" maxlength="60" required autofocus>
                    @error('kode') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                    <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="{{ $selectClass('tahun_pelajaran_id') }}">
                        <option value="">Umum/lintas tahun</option>
                        @foreach ($daftarTahunPelajaran as $item)
                            <option value="{{ $item->id }}" @selected((string) $nilai('tahun_pelajaran_id') === (string) $item->id)>{{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="mata_pelajaran_id">Mata pelajaran</label>
                    <select id="mata_pelajaran_id" name="mata_pelajaran_id" class="{{ $selectClass('mata_pelajaran_id') }}" required>
                        <option value="">Pilih mata pelajaran</option>
                        @foreach ($daftarMataPelajaran as $item)
                            <option value="{{ $item->id }}" data-tingkat="{{ $item->tingkat }}" @selected((string) $nilai('mata_pelajaran_id') === (string) $item->id)>{{ $item->nama }}{{ $item->tingkat ? ' - kelas ' . $item->tingkat : '' }}</option>
                        @endforeach
                    </select>
                    @error('mata_pelajaran_id') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="tingkat">Tingkat kelas</label>
                    <select id="tingkat" name="tingkat" class="{{ $selectClass('tingkat') }}" required>
                        <option value="">Pilih tingkat</option>
                        @foreach ([7, 8, 9] as $tingkat)
                            <option value="{{ $tingkat }}" @selected((string) $nilai('tingkat') === (string) $tingkat)>Kelas {{ $tingkat }}</option>
                        @endforeach
                    </select>
                    @error('tingkat') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="jenis_soal">Jenis soal</label>
                    <select id="jenis_soal" name="jenis_soal" class="{{ $selectClass('jenis_soal') }}" required data-soal-kind>
                        @foreach ($daftarJenisSoal as $kode => $label)
                            <option value="{{ $kode }}" @selected($nilai('jenis_soal', 'pilihan_ganda') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('jenis_soal') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="tingkat_kesulitan">Tingkat kesulitan</label>
                    <select id="tingkat_kesulitan" name="tingkat_kesulitan" class="{{ $selectClass('tingkat_kesulitan') }}" required>
                        @foreach ($daftarKesulitan as $kode => $label)
                            <option value="{{ $kode }}" @selected($nilai('tingkat_kesulitan', 'sedang') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="kategori">Kategori</label>
                    <select id="kategori" name="kategori" class="{{ $selectClass('kategori') }}" required>
                        @foreach ($daftarKategori as $kode => $label)
                            <option value="{{ $kode }}" @selected($nilai('kategori', 'umum') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="topik">Topik</label>
                    <input id="topik" name="topik" type="text" value="{{ $nilai('topik') }}" placeholder="Contoh: Getaran dan gelombang" class="{{ $inputClass('topik') }}">
                </div>
                <div class="field">
                    <label for="materi">Materi</label>
                    <input id="materi" name="materi" type="text" value="{{ $nilai('materi') }}" placeholder="Contoh: Frekuensi" class="{{ $inputClass('materi') }}">
                </div>
                <div class="field span-2">
                    <label for="tujuan_pembelajaran">Tujuan pembelajaran</label>
                    <textarea id="tujuan_pembelajaran" name="tujuan_pembelajaran" class="{{ $textareaClass('tujuan_pembelajaran') }}">{{ $nilai('tujuan_pembelajaran') }}</textarea>
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Isi Soal</h2>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="stimulus">Stimulus</label>
                    <textarea id="stimulus" name="stimulus" class="{{ $textareaClass('stimulus') }}" placeholder="Opsional: teks, data tabel, kasus, atau deskripsi gambar/grafik.">{{ $nilai('stimulus') }}</textarea>
                </div>
                <div class="field span-2">
                    <label for="pertanyaan">Pertanyaan</label>
                    <textarea id="pertanyaan" name="pertanyaan" class="{{ $textareaClass('pertanyaan') }}" required>{{ $nilai('pertanyaan') }}</textarea>
                    @error('pertanyaan') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Opsi dan Kunci Jawaban</h2>

            @error('opsi') <p class="error-text">{{ $message }}</p> @enderror
            @error('kunci_pg') <p class="error-text">{{ $message }}</p> @enderror
            @error('kunci_pgk') <p class="error-text">{{ $message }}</p> @enderror
            @error('pernyataan') <p class="error-text">{{ $message }}</p> @enderror
            @error('pasangan_kiri') <p class="error-text">{{ $message }}</p> @enderror
            @error('kunci_teks') <p class="error-text">{{ $message }}</p> @enderror

            <div class="soal-answer-section" data-answer-section="pilihan_ganda pilihan_ganda_kompleks">
                <div class="soal-option-grid">
                    @foreach (['A', 'B', 'C', 'D'] as $kode)
                        <div class="soal-option-row">
                            <label class="soal-option-label">
                                <input type="radio" name="kunci_pg" value="{{ $kode }}" @checked($kunciPg === $kode) data-pg-key>
                                <input type="checkbox" name="kunci_pgk[]" value="{{ $kode }}" @checked(in_array($kode, (array) $kunciPgk, true)) data-pgk-key>
                                <span>Opsi {{ $kode }}</span>
                            </label>
                            <textarea name="opsi[{{ $kode }}]" class="textarea" rows="2">{{ $opsiPilihan[$kode] ?? '' }}</textarea>
                        </div>
                    @endforeach
                </div>
                <p class="help-text" style="margin-top: 10px;">Untuk pilihan ganda, pilih satu radio sebagai kunci. Untuk pilihan ganda kompleks, centang semua jawaban benar.</p>
            </div>

            <div class="soal-answer-section" data-answer-section="benar_salah">
                <div class="soal-option-grid">
                    @foreach (range(0, 3) as $index)
                        <div class="soal-option-row">
                            <label for="pernyataan_{{ $index }}">Pernyataan {{ $index + 1 }}</label>
                            <textarea id="pernyataan_{{ $index }}" name="pernyataan[]" class="textarea" rows="2">{{ $pernyataan[$index] ?? '' }}</textarea>
                            <select name="jawaban_bs[]" class="select" style="margin-top: 8px;">
                                <option value="benar" @selected(($jawabanBs[$index] ?? 'benar') === 'benar')>Benar</option>
                                <option value="salah" @selected(($jawabanBs[$index] ?? 'benar') === 'salah')>Salah</option>
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="soal-answer-section" data-answer-section="menjodohkan">
                <div class="soal-option-grid">
                    @foreach (range(0, 3) as $index)
                        <div class="soal-option-row">
                            <label for="pasangan_kiri_{{ $index }}">Kolom A {{ $index + 1 }}</label>
                            <textarea id="pasangan_kiri_{{ $index }}" name="pasangan_kiri[]" class="textarea" rows="2">{{ $pasanganKiri[$index] ?? '' }}</textarea>
                            <label for="pasangan_kanan_{{ $index }}" style="margin-top: 8px;">Pasangan jawaban</label>
                            <textarea id="pasangan_kanan_{{ $index }}" name="pasangan_kanan[]" class="textarea" rows="2">{{ $pasanganKanan[$index] ?? '' }}</textarea>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="soal-answer-section" data-answer-section="isian_singkat uraian numerik upload_file">
                <div class="form-grid">
                    <div class="field span-2">
                        <label for="kunci_teks">Kunci jawaban</label>
                        <textarea id="kunci_teks" name="kunci_teks" class="textarea" placeholder="Untuk uraian/upload file boleh dikosongkan jika penilaian manual memakai rubrik.">{{ $kunciTeks }}</textarea>
                    </div>
                    <div class="field span-2">
                        <label for="rubrik_teks">Rubrik/catatan koreksi</label>
                        <textarea id="rubrik_teks" name="rubrik_teks" class="textarea">{{ $rubrikTeks }}</textarea>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Pembahasan</h2>
            <textarea id="pembahasan" name="pembahasan" class="{{ $textareaClass('pembahasan') }}" placeholder="Opsional: pembahasan yang dapat dipakai setelah ujian selesai.">{{ $nilai('pembahasan') }}</textarea>
        </section>

        <div class="form-actions">
            <a href="{{ route('soal-cbt.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol }}</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const jenis = document.querySelector('[data-soal-kind]');
        const sections = document.querySelectorAll('[data-answer-section]');
        const pgKeys = document.querySelectorAll('[data-pg-key]');
        const pgkKeys = document.querySelectorAll('[data-pgk-key]');
        const mapel = document.getElementById('mata_pelajaran_id');
        const tingkat = document.getElementById('tingkat');

        const sinkronkanJenis = () => {
            const value = jenis.value;

            sections.forEach((section) => {
                section.classList.toggle('is-active', section.dataset.answerSection.split(' ').includes(value));
            });

            pgKeys.forEach((input) => {
                input.closest('label').style.display = value === 'pilihan_ganda' ? 'flex' : 'flex';
                input.style.display = value === 'pilihan_ganda' ? '' : 'none';
                input.disabled = value !== 'pilihan_ganda';
            });

            pgkKeys.forEach((input) => {
                input.style.display = value === 'pilihan_ganda_kompleks' ? '' : 'none';
                input.disabled = value !== 'pilihan_ganda_kompleks';
            });
        };

        const sinkronkanTingkat = () => {
            const selected = mapel.selectedOptions[0];

            if (selected?.dataset.tingkat && ! tingkat.value) {
                tingkat.value = selected.dataset.tingkat;
            }
        };

        jenis.addEventListener('change', sinkronkanJenis);
        mapel.addEventListener('change', sinkronkanTingkat);
        sinkronkanJenis();
        sinkronkanTingkat();
    })();
</script>
