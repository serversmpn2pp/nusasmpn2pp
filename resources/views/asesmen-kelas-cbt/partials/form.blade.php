@php
    $ujianCbt = $ujianCbt ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $ujianCbt?->{$field} ?? $default);
    $tanggalWaktu = fn (string $field) => $nilai($field) instanceof \Carbon\CarbonInterface
        ? $nilai($field)->format('Y-m-d\TH:i')
        : $nilai($field);
    $kelasTersimpan = $ujianCbt?->kelasUjianCbt
        ?->mapWithKeys(fn ($item) => [$item->kelas_id => [
            'dipilih' => '1',
            'komponen_nilai_id' => (string) $item->komponen_nilai_id,
        ]])
        ->all() ?? [];
    $kelasDipilih = old('kelas_peserta', $kelasTersimpan);
    $penugasanPertama = $ujianCbt?->kelasUjianCbt?->first()?->komponenNilai?->guruMataPelajaran;
    $kelompokAwal = $penugasanPertama
        ? implode('-', [$penugasanPertama->pegawai_id, $penugasanPertama->mata_pelajaran_id, $penugasanPertama->kelas?->tingkat])
        : '';
    $kelompokDipilih = old('kelompok_pengajaran', $kelompokAwal);
@endphp

<style>
    .assessment-form-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(280px, .65fr);
        gap: 18px;
        align-items: start;
    }

    .assessment-class-list,
    .assessment-toggle-list {
        display: grid;
        gap: 10px;
        margin-top: 14px;
    }

    .assessment-class {
        display: grid;
        grid-template-columns: minmax(180px, .7fr) minmax(220px, 1.3fr);
        gap: 14px;
        align-items: center;
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 13px;
        background: #fff;
    }

    .assessment-class[hidden] {
        display: none;
    }

    .assessment-class-check {
        display: flex;
        gap: 10px;
        align-items: center;
        color: var(--primary-dark);
        font-weight: 750;
    }

    .assessment-toggle {
        display: flex;
        gap: 14px;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--line);
        padding-bottom: 10px;
    }

    .assessment-toggle:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    @media (max-width: 860px) {
        .assessment-form-grid {
            grid-template-columns: 1fr;
        }

        .assessment-form-grid > aside {
            order: 2;
        }
    }

    @media (max-width: 620px) {
        .assessment-class {
            grid-template-columns: 1fr;
        }
    }
</style>

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Ada data yang perlu diperbaiki.</strong>
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="assessment-form-grid">
    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">1. Informasi asesmen</h2>
            <p class="help-text" style="margin-top: 6px;">Pilih penugasan mengajar. NUSA hanya menampilkan mata pelajaran dan kelas yang sesuai.</p>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field span-2">
                    <label for="kelompok_pengajaran">Mata pelajaran dan tingkat</label>
                    <select id="kelompok_pengajaran" name="kelompok_pengajaran" class="select" required autofocus>
                        <option value="">Pilih penugasan mengajar</option>
                        @foreach ($kelompokPengajaran as $kelompok)
                            <option value="{{ $kelompok['key'] }}" @selected($kelompokDipilih === $kelompok['key'])>{{ $kelompok['label'] }}</option>
                        @endforeach
                    </select>
                    @if ($kelompokPengajaran->isEmpty())
                        <p class="error-text">Belum ada penugasan guru mata pelajaran aktif pada tahun pelajaran ini.</p>
                    @endif
                </div>
                <div class="field span-2">
                    <label for="nama">Nama asesmen</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" class="input" placeholder="Contoh: Sumatif Bab 1 - Berpikir Komputasional" maxlength="180" required>
                </div>
                <div class="field">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" class="select" required>
                        <option value="">Pilih semester</option>
                        <option value="ganjil" @selected($nilai('semester') === 'ganjil')>Ganjil</option>
                        <option value="genap" @selected($nilai('semester') === 'genap')>Genap</option>
                    </select>
                </div>
                <div class="field">
                    <label for="jumlah_soal">Jumlah soal ditampilkan</label>
                    <input id="jumlah_soal" name="jumlah_soal" type="number" min="1" max="120" value="{{ $nilai('jumlah_soal', 20) }}" class="input" required>
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">2. Kelas dan tujuan nilai</h2>
            <p class="help-text" style="margin-top: 6px;">Pilih satu atau beberapa kelas. NUSA dapat membuat komponen Sumatif baru secara otomatis dengan nama asesmen ini.</p>
            @error('kelas_peserta')<p class="error-text" style="margin-top: 10px;">{{ $message }}</p>@enderror

            <div class="assessment-class-list">
                @foreach ($kelompokPengajaran as $kelompok)
                    @foreach ($kelompok['kelas'] as $kelas)
                        @php
                            $state = $kelasDipilih[$kelas['kelas_id']] ?? [];
                            $dipilih = filter_var($state['dipilih'] ?? false, FILTER_VALIDATE_BOOLEAN);
                            $komponenDipilih = (string) ($state['komponen_nilai_id'] ?? 'baru');
                            $rowId = str($kelompok['key'].'-'.$kelas['kelas_id'])->slug('_');
                        @endphp
                        <div class="assessment-class" data-assessment-class data-group="{{ $kelompok['key'] }}">
                            <label class="assessment-class-check" for="kelas_{{ $rowId }}">
                                <input type="hidden" name="kelas_peserta[{{ $kelas['kelas_id'] }}][dipilih]" value="0">
                                <input id="kelas_{{ $rowId }}" type="checkbox" name="kelas_peserta[{{ $kelas['kelas_id'] }}][dipilih]" value="1" @checked($dipilih) data-class-check>
                                <span>{{ $kelas['nama'] }}</span>
                            </label>
                            <div class="field">
                                <label for="komponen_{{ $rowId }}">Masuk ke nilai</label>
                                <select id="komponen_{{ $rowId }}" name="kelas_peserta[{{ $kelas['kelas_id'] }}][komponen_nilai_id]" class="select" data-component>
                                    <option value="baru" @selected($komponenDipilih === 'baru')>Buat komponen Sumatif baru otomatis</option>
                                    @foreach ($kelas['komponen'] as $komponen)
                                        <option value="{{ $komponen['id'] }}" data-semester="{{ $komponen['semester'] }}" @selected($komponenDipilih === (string) $komponen['id'])>{{ $komponen['nama'] }} - {{ ucfirst($komponen['semester']) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
            <div class="empty-state" data-class-empty hidden>Pilih mata pelajaran dan tingkat untuk menampilkan kelas.</div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">3. Waktu pelaksanaan</h2>
            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label for="tanggal_mulai">Dibuka</label>
                    <input id="tanggal_mulai" name="tanggal_mulai" type="datetime-local" value="{{ $tanggalWaktu('tanggal_mulai') }}" class="input" required>
                </div>
                <div class="field">
                    <label for="tanggal_selesai">Ditutup</label>
                    <input id="tanggal_selesai" name="tanggal_selesai" type="datetime-local" value="{{ $tanggalWaktu('tanggal_selesai') }}" class="input" required>
                </div>
                <div class="field">
                    <label for="durasi_menit">Durasi pengerjaan</label>
                    <input id="durasi_menit" name="durasi_menit" type="number" min="10" max="300" value="{{ $nilai('durasi_menit', 40) }}" class="input" required>
                    <p class="help-text">Dalam menit, dihitung sejak siswa menekan Mulai.</p>
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="select" required>
                        @foreach ($daftarStatus as $kode => $label)
                            <option value="{{ $kode }}" @selected($nilai('status', 'draft') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="help-text">Pilih Terjadwal agar tampil di akun siswa sesuai waktu buka.</p>
                </div>
                <div class="field span-2">
                    <label for="petunjuk">Petunjuk untuk siswa</label>
                    <textarea id="petunjuk" name="petunjuk" class="textarea" placeholder="Opsional">{{ $nilai('petunjuk') }}</textarea>
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('asesmen-kelas-cbt.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol }}</button>
        </div>
    </div>

    <aside class="panel panel-pad">
        <h2 class="panel-title">Pengaturan pengerjaan</h2>
        <p class="help-text" style="margin-top: 6px;">Pengaturan umum yang paling sering dibutuhkan saat asesmen di kelas.</p>
        <div class="assessment-toggle-list">
            @foreach ([
                'acak_soal' => ['Acak soal', 'Urutan berbeda untuk setiap siswa', true],
                'acak_jawaban' => ['Acak pilihan jawaban', 'Berlaku untuk pilihan ganda', true],
                'batasi_satu_perangkat' => ['Satu perangkat', 'Cegah akun aktif di dua perangkat', false],
                'deteksi_pindah_tab' => ['Catat pindah tab', 'Masuk ke log monitoring guru', false],
                'tampilkan_hasil' => ['Tampilkan hasil', 'Nilai terlihat setelah asesmen selesai', false],
            ] as $field => [$label, $helper, $default])
                <label class="assessment-toggle">
                    <span><strong>{{ $label }}</strong><span class="help-text" style="display:block;">{{ $helper }}</span></span>
                    <input type="hidden" name="{{ $field }}" value="0">
                    <input type="checkbox" name="{{ $field }}" value="1" @checked((bool) $nilai($field, $default))>
                </label>
            @endforeach
        </div>
        <div class="alert" style="margin-top: 18px;">
            <strong>Tanpa ruang dan sesi</strong>
            <p style="margin: 5px 0 0;">Semua siswa aktif di kelas terpilih langsung menjadi peserta. Mereka masuk menggunakan akun NUSA masing-masing.</p>
        </div>
    </aside>
</div>

<script>
    (() => {
        const group = document.getElementById('kelompok_pengajaran');
        const semester = document.getElementById('semester');
        const rows = document.querySelectorAll('[data-assessment-class]');
        const empty = document.querySelector('[data-class-empty]');

        const refresh = () => {
            let visible = 0;

            rows.forEach((row) => {
                const show = Boolean(group.value) && row.dataset.group === group.value;
                row.hidden = ! show;
                row.querySelectorAll('input, select').forEach((input) => input.disabled = ! show);

                if (! show) return;
                visible += 1;
                const check = row.querySelector('[data-class-check]');
                const component = row.querySelector('[data-component]');
                component.disabled = ! check.checked;
                component.required = check.checked;

                component.querySelectorAll('option[data-semester]').forEach((option) => {
                    const match = ! semester.value || option.dataset.semester === semester.value;
                    option.hidden = ! match;
                    option.disabled = ! match;
                    if (! match && option.selected) component.value = 'baru';
                });
            });

            empty.hidden = visible > 0;
        };

        group.addEventListener('change', refresh);
        semester.addEventListener('change', refresh);
        rows.forEach((row) => row.querySelector('[data-class-check]').addEventListener('change', refresh));
        refresh();
    })();
</script>
