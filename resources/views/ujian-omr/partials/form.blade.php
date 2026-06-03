@php
    $ujianOmr = $ujianOmr ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $ujianOmr?->{$field} ?? $default);
    $tanggal = function (string $field) use ($nilai) {
        $value = $nilai($field);

        return $value instanceof \Carbon\CarbonInterface ? $value->format('Y-m-d') : $value;
    };
    $kelasPesertaDipilih = old(
        'kelas_peserta',
        $ujianOmr?->kelasUjianOmr->pluck('komponen_nilai_id', 'kelas_id')->all() ?? [],
    );
    $kodeVersi = old(
        'kode_versi',
        $ujianOmr?->versiSoal->where('aktif', true)->pluck('kode')->implode(', ') ?: 'A',
    );
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
@endphp

<style>
    .omr-class-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 14px;
    }

    .omr-class-item {
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 12px;
    }

    .omr-class-item[hidden] {
        display: none;
    }

    .omr-class-item h3 {
        margin: 0 0 8px;
        color: var(--primary-dark);
        font-size: .94rem;
    }

    @media (max-width: 760px) {
        .omr-class-grid {
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
        <h2 class="panel-title">Status ujian</h2>
        <p class="help-text">Gunakan status draft selama kunci jawaban masih dilengkapi.</p>
        <div class="field" style="margin-top: 16px;">
            <label for="status">Status</label>
            <select id="status" name="status" class="{{ $selectClass('status') }}" required>
                @foreach ($daftarStatus as $kode => $label)
                    <option value="{{ $kode }}" @selected($nilai('status', 'draft') === $kode)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Ujian</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="kode">Kode ujian</label>
                    <input id="kode" name="kode" type="text" value="{{ $nilai('kode', $kodeSaran) }}" class="{{ $inputClass('kode') }}" maxlength="50" required autofocus>
                </div>
                <div class="field">
                    <label for="nama">Nama ujian</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" placeholder="Contoh: STS Semester Ganjil" class="{{ $inputClass('nama') }}" required>
                </div>
                <div class="field">
                    <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                    <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="{{ $selectClass('tahun_pelajaran_id') }}" required>
                        <option value="">Pilih tahun pelajaran</option>
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
                            <option value="{{ $item->id }}" @selected((string) $nilai('mata_pelajaran_id') === (string) $item->id)>{{ $item->nama }}{{ $item->tingkat ? ' - kelas ' . $item->tingkat : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" class="{{ $selectClass('semester') }}" required>
                        <option value="">Pilih semester</option>
                        <option value="ganjil" @selected($nilai('semester') === 'ganjil')>Ganjil</option>
                        <option value="genap" @selected($nilai('semester') === 'genap')>Genap</option>
                    </select>
                </div>
                <div class="field">
                    <label for="tanggal_ujian">Tanggal ujian</label>
                    <input id="tanggal_ujian" name="tanggal_ujian" type="date" value="{{ $tanggal('tanggal_ujian') }}" class="{{ $inputClass('tanggal_ujian') }}">
                </div>
                <div class="field">
                    <label for="jumlah_soal">Jumlah soal</label>
                    <input id="jumlah_soal" name="jumlah_soal" type="number" min="1" max="50" value="{{ $nilai('jumlah_soal', 50) }}" class="{{ $inputClass('jumlah_soal') }}" required>
                    <p class="help-text">Maksimal 50 soal untuk template A5.</p>
                </div>
                <div class="field">
                    <label for="jumlah_pilihan">Pilihan jawaban</label>
                    <input id="jumlah_pilihan" name="jumlah_pilihan" type="number" value="4" class="input" readonly>
                    <p class="help-text">Tetap menggunakan pilihan A-D.</p>
                </div>
                <div class="field span-2">
                    <label for="kode_versi">Versi soal</label>
                    <input id="kode_versi" name="kode_versi" type="text" value="{{ $kodeVersi }}" placeholder="Contoh: A, B" class="{{ $inputClass('kode_versi') }}" required>
                    <p class="help-text">Pisahkan beberapa versi menggunakan koma. Kunci jawaban diisi setelah ujian disimpan.</p>
                </div>
                <div class="field span-2">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" class="{{ $textareaClass('keterangan') }}">{{ $nilai('keterangan') }}</textarea>
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Kelas Peserta dan Tujuan Nilai</h2>
            <p class="help-text">Pilih komponen STS atau SAS/SAJ pada setiap kelas yang mengikuti ujian. OMR tidak digunakan untuk nilai formatif maupun sumatif harian.</p>

            @error('kelas_peserta')
                <p class="error-text" style="margin-top: 10px;">{{ $message }}</p>
            @enderror

            <div class="omr-class-grid">
                @foreach ($daftarKelas as $kelas)
                    <div class="omr-class-item" data-omr-class data-tahun="{{ $kelas->tahun_pelajaran_id }}">
                        <h3>{{ $kelas->nama }} <span class="person-meta">Kelas {{ $kelas->tingkat }}</span></h3>
                        <label for="kelas_peserta_{{ $kelas->id }}">Komponen nilai tujuan</label>
                        <select id="kelas_peserta_{{ $kelas->id }}" name="kelas_peserta[{{ $kelas->id }}]" class="select" data-omr-component>
                            <option value="">Tidak diikutkan</option>
                            @foreach ($daftarKomponenNilai->filter(fn ($komponen) => (int) $komponen->guruMataPelajaran?->kelas_id === (int) $kelas->id) as $komponen)
                                <option
                                    value="{{ $komponen->id }}"
                                    data-tahun="{{ $komponen->guruMataPelajaran?->tahun_pelajaran_id }}"
                                    data-mapel="{{ $komponen->guruMataPelajaran?->mata_pelajaran_id }}"
                                    @selected((string) ($kelasPesertaDipilih[$kelas->id] ?? '') === (string) $komponen->id)
                                >
                                    {{ $komponen->nama }} - {{ $komponen->labelJenis() }} - {{ $komponen->guruMataPelajaran?->pegawai?->nama_lengkap ?: '-' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('ujian-omr.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol }}</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const tahun = document.getElementById('tahun_pelajaran_id');
        const mapel = document.getElementById('mata_pelajaran_id');
        const rows = document.querySelectorAll('[data-omr-class]');

        const sinkronkan = () => {
            rows.forEach((row) => {
                const sesuaiTahun = tahun.value && row.dataset.tahun === tahun.value;
                const select = row.querySelector('[data-omr-component]');
                let punyaPilihan = false;

                select.querySelectorAll('option').forEach((option) => {
                    if (! option.value) {
                        return;
                    }

                    const cocok = sesuaiTahun && mapel.value && option.dataset.tahun === tahun.value && option.dataset.mapel === mapel.value;
                    option.hidden = ! cocok;
                    option.disabled = ! cocok;
                    punyaPilihan = punyaPilihan || cocok;

                    if (! cocok && option.selected) {
                        select.value = '';
                    }
                });

                row.hidden = ! sesuaiTahun || ! punyaPilihan;
            });
        };

        tahun.addEventListener('change', sinkronkan);
        mapel.addEventListener('change', sinkronkan);
        sinkronkan();
    })();
</script>
