@php
    $ujianCbt = $ujianCbt ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $ujianCbt?->{$field} ?? $default);
    $tanggalWaktu = function (string $field) use ($nilai) {
        $value = $nilai($field);

        return $value instanceof \Carbon\CarbonInterface ? $value->format('Y-m-d\TH:i') : $value;
    };
    $kelasPesertaDipilih = old(
        'kelas_peserta',
        $ujianCbt?->kelasUjianCbt
            ->mapWithKeys(fn ($item) => [
                $item->kelas_id => [
                    'dipilih' => '1',
                    'komponen_nilai_id' => $item->komponen_nilai_id,
                ],
            ])
            ->all() ?? [],
    );
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
@endphp

<style>
    .cbt-class-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 14px;
    }

    .cbt-class-item {
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 12px;
        background: #fff;
    }

    .cbt-class-item[hidden] {
        display: none;
    }

    .cbt-class-head {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .cbt-class-check {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        color: var(--primary-dark);
        font-weight: 700;
    }

    .cbt-class-check input {
        margin-top: 3px;
    }

    .cbt-setting-stack {
        display: grid;
        gap: 12px;
        margin-top: 16px;
    }

    @media (max-width: 760px) {
        .cbt-class-grid {
            grid-template-columns: 1fr;
        }

        .cbt-class-head {
            display: block;
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
        <h2 class="panel-title">Status dan keamanan</h2>
        <p class="help-text">Pengaturan ini akan dipakai saat siswa mulai mengerjakan CBT.</p>

        <div class="field" style="margin-top: 16px;">
            <label for="status">Status</label>
            <select id="status" name="status" class="{{ $selectClass('status') }}" required>
                @foreach ($daftarStatus as $kode => $label)
                    <option value="{{ $kode }}" @selected($nilai('status', 'draft') === $kode)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="cbt-setting-stack">
            @foreach ([
                'acak_soal' => ['Acak soal', 'Urutan soal berbeda untuk setiap siswa', true],
                'acak_jawaban' => ['Acak jawaban', 'Pilihan jawaban PG dapat berbeda urutan', true],
                'batasi_satu_perangkat' => ['Batasi satu perangkat', 'Satu akun siswa hanya aktif di satu perangkat', false],
                'deteksi_pindah_tab' => ['Deteksi pindah tab', 'Catat saat siswa keluar dari halaman ujian', false],
                'wajib_fullscreen' => ['Wajib layar penuh', 'Siswa diminta masuk mode fullscreen', false],
                'tampilkan_hasil' => ['Tampilkan hasil', 'Nilai dapat dilihat siswa setelah selesai', false],
            ] as $field => [$label, $helper, $default])
                <label class="status-toggle">
                    <span>
                        <span class="form-label" style="margin-bottom:0">{{ $label }}</span>
                        <span class="help-text">{{ $helper }}</span>
                    </span>
                    <input type="hidden" name="{{ $field }}" value="0">
                    <input type="checkbox" name="{{ $field }}" value="1" @checked((bool) $nilai($field, $default))>
                </label>
            @endforeach
        </div>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Paket</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="kode">Kode paket</label>
                    <input id="kode" name="kode" type="text" value="{{ $nilai('kode', $kodeSaran) }}" class="{{ $inputClass('kode') }}" maxlength="50" required autofocus>
                    @error('kode') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="nama">Nama paket</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" placeholder="Contoh: STS Matematika Semester Ganjil" class="{{ $inputClass('nama') }}" required>
                    @error('nama') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="jenis_ujian_cbt_id">Jenis ujian</label>
                    <select id="jenis_ujian_cbt_id" name="jenis_ujian_cbt_id" class="{{ $selectClass('jenis_ujian_cbt_id') }}" required>
                        <option value="">Pilih jenis ujian</option>
                        @foreach ($daftarJenisUjianCbt as $item)
                            <option
                                value="{{ $item->id }}"
                                data-token="{{ $item->memerlukan_token ? '1' : '0' }}"
                                data-nilai="{{ $item->dapat_diterapkan_ke_nilai ? '1' : '0' }}"
                                @selected((string) $nilai('jenis_ujian_cbt_id') === (string) $item->id)
                            >
                                {{ $item->nama }}
                            </option>
                        @endforeach
                    </select>
                    @error('jenis_ujian_cbt_id') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                    <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="{{ $selectClass('tahun_pelajaran_id') }}" required>
                        <option value="">Pilih tahun pelajaran</option>
                        @foreach ($daftarTahunPelajaran as $item)
                            <option value="{{ $item->id }}" @selected((string) $nilai('tahun_pelajaran_id') === (string) $item->id)>{{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}</option>
                        @endforeach
                    </select>
                    @error('tahun_pelajaran_id') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="mata_pelajaran_id">Mata pelajaran</label>
                    <select id="mata_pelajaran_id" name="mata_pelajaran_id" class="{{ $selectClass('mata_pelajaran_id') }}" required>
                        <option value="">Pilih mata pelajaran</option>
                        @foreach ($daftarMataPelajaran as $item)
                            <option value="{{ $item->id }}" @selected((string) $nilai('mata_pelajaran_id') === (string) $item->id)>{{ $item->nama }}</option>
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
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" class="{{ $selectClass('semester') }}" required>
                        <option value="">Pilih semester</option>
                        <option value="ganjil" @selected($nilai('semester') === 'ganjil')>Ganjil</option>
                        <option value="genap" @selected($nilai('semester') === 'genap')>Genap</option>
                    </select>
                    @error('semester') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="jumlah_soal">Jumlah soal</label>
                    <input id="jumlah_soal" name="jumlah_soal" type="number" min="1" max="120" value="{{ $nilai('jumlah_soal', 50) }}" class="{{ $inputClass('jumlah_soal') }}" required>
                    @error('jumlah_soal') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="durasi_menit">Durasi</label>
                    <input id="durasi_menit" name="durasi_menit" type="number" min="10" max="300" value="{{ $nilai('durasi_menit', 120) }}" class="{{ $inputClass('durasi_menit') }}" required>
                    <p class="help-text">Dalam menit.</p>
                    @error('durasi_menit') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="kkm">KKM</label>
                    <input id="kkm" name="kkm" type="number" min="0" max="100" value="{{ $nilai('kkm') }}" placeholder="Opsional" class="{{ $inputClass('kkm') }}">
                    @error('kkm') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Jadwal dan Token</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="tanggal_mulai">Dibuka</label>
                    <input id="tanggal_mulai" name="tanggal_mulai" type="datetime-local" value="{{ $tanggalWaktu('tanggal_mulai') }}" class="{{ $inputClass('tanggal_mulai') }}">
                    @error('tanggal_mulai') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="tanggal_selesai">Ditutup</label>
                    <input id="tanggal_selesai" name="tanggal_selesai" type="datetime-local" value="{{ $tanggalWaktu('tanggal_selesai') }}" class="{{ $inputClass('tanggal_selesai') }}">
                    @error('tanggal_selesai') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="token">Token ujian</label>
                    <input id="token" name="token" type="text" value="{{ $nilai('token') }}" placeholder="Kosongkan untuk otomatis" class="{{ $inputClass('token') }}" maxlength="20">
                    <p class="help-text">Jika jenis ujian membutuhkan token dan kolom ini kosong, NUSA akan membuat token 6 digit.</p>
                    @error('token') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field span-2">
                    <label for="petunjuk">Petunjuk untuk siswa</label>
                    <textarea id="petunjuk" name="petunjuk" class="{{ $textareaClass('petunjuk') }}" placeholder="Contoh: Bacalah soal dengan cermat dan jangan menutup browser sebelum selesai.">{{ $nilai('petunjuk') }}</textarea>
                    @error('petunjuk') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field span-2">
                    <label for="keterangan">Catatan internal</label>
                    <textarea id="keterangan" name="keterangan" class="{{ $textareaClass('keterangan') }}">{{ $nilai('keterangan') }}</textarea>
                    @error('keterangan') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Kelas Peserta dan Tujuan Nilai</h2>
            <p class="help-text">Pilih kelas peserta. Jika jenis ujian diterapkan ke nilai, setiap kelas wajib memiliki komponen nilai tujuan yang sesuai.</p>

            @error('kelas_peserta')
                <p class="error-text" style="margin-top: 10px;">{{ $message }}</p>
            @enderror

            <div class="cbt-class-grid">
                @foreach ($daftarKelas as $kelas)
                    @php
                        $state = $kelasPesertaDipilih[$kelas->id] ?? [];
                        $dipilih = filter_var($state['dipilih'] ?? false, FILTER_VALIDATE_BOOLEAN) || filled($state['komponen_nilai_id'] ?? null);
                        $komponenDipilih = $state['komponen_nilai_id'] ?? '';
                    @endphp
                    <div class="cbt-class-item" data-cbt-class data-tahun="{{ $kelas->tahun_pelajaran_id }}" data-tingkat="{{ $kelas->tingkat }}">
                        <div class="cbt-class-head">
                            <label class="cbt-class-check" for="kelas_peserta_{{ $kelas->id }}">
                                <input type="hidden" name="kelas_peserta[{{ $kelas->id }}][dipilih]" value="0">
                                <input id="kelas_peserta_{{ $kelas->id }}" type="checkbox" name="kelas_peserta[{{ $kelas->id }}][dipilih]" value="1" @checked($dipilih) data-cbt-class-check>
                                <span>
                                    {{ $kelas->nama }}
                                    <span class="person-meta" style="display:block; font-weight:500;">Kelas {{ $kelas->tingkat }} - {{ $kelas->tahunPelajaran?->nama }}</span>
                                </span>
                            </label>
                        </div>

                        <label for="komponen_nilai_{{ $kelas->id }}">Komponen nilai tujuan</label>
                        <select id="komponen_nilai_{{ $kelas->id }}" name="kelas_peserta[{{ $kelas->id }}][komponen_nilai_id]" class="select" data-cbt-component>
                            <option value="">Tanpa target nilai</option>
                            @foreach ($daftarKomponenNilai->filter(fn ($komponen) => (int) $komponen->guruMataPelajaran?->kelas_id === (int) $kelas->id) as $komponen)
                                <option
                                    value="{{ $komponen->id }}"
                                    data-tahun="{{ $komponen->guruMataPelajaran?->tahun_pelajaran_id }}"
                                    data-mapel="{{ $komponen->guruMataPelajaran?->mata_pelajaran_id }}"
                                    data-semester="{{ $komponen->semester }}"
                                    @selected((string) $komponenDipilih === (string) $komponen->id)
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
            <a href="{{ route('ujian-cbt.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol }}</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const tahun = document.getElementById('tahun_pelajaran_id');
        const mapel = document.getElementById('mata_pelajaran_id');
        const semester = document.getElementById('semester');
        const tingkat = document.getElementById('tingkat');
        const jenis = document.getElementById('jenis_ujian_cbt_id');
        const rows = document.querySelectorAll('[data-cbt-class]');

        const jenisButuhNilai = () => jenis.selectedOptions[0]?.dataset.nilai === '1';

        const sinkronkan = () => {
            rows.forEach((row) => {
                const sesuaiTahun = tahun.value && row.dataset.tahun === tahun.value;
                const sesuaiTingkat = tingkat.value && row.dataset.tingkat === tingkat.value;
                const select = row.querySelector('[data-cbt-component]');
                const check = row.querySelector('[data-cbt-class-check]');
                let punyaPilihan = false;

                select.querySelectorAll('option').forEach((option) => {
                    if (! option.value) {
                        return;
                    }

                    const cocok = sesuaiTahun
                        && mapel.value
                        && semester.value
                        && option.dataset.tahun === tahun.value
                        && option.dataset.mapel === mapel.value
                        && option.dataset.semester === semester.value;

                    option.hidden = ! cocok;
                    option.disabled = ! cocok;
                    punyaPilihan = punyaPilihan || cocok;

                    if (! cocok && option.selected) {
                        select.value = '';
                    }
                });

                row.hidden = ! sesuaiTahun || ! sesuaiTingkat;

                if (row.hidden) {
                    check.checked = false;
                    select.value = '';
                }

                select.required = jenisButuhNilai() && check.checked;
                select.disabled = ! check.checked;

                if (jenisButuhNilai() && check.checked && ! punyaPilihan) {
                    select.title = 'Belum ada komponen nilai yang sesuai untuk kelas ini.';
                } else {
                    select.title = '';
                }
            });
        };

        [tahun, mapel, semester, tingkat, jenis].forEach((input) => input.addEventListener('change', sinkronkan));
        rows.forEach((row) => row.querySelector('[data-cbt-class-check]').addEventListener('change', sinkronkan));
        sinkronkan();
    })();
</script>
