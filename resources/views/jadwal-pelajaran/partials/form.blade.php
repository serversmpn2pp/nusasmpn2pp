@php
    $jadwalPelajaran = $jadwalPelajaran ?? null;
    $tahunAktifId = $tahunPelajaran->firstWhere('aktif', true)?->id;
    $nilai = fn (string $field, mixed $default = '') => old($field, $jadwalPelajaran?->{$field} ?? $default);
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
    $pilihanJadwalTerpilih = old(
        'pilihan_jadwal',
        old('mata_pelajaran_id')
            ? 'kegiatan:'.old('mata_pelajaran_id')
            : (
                old('guru_mata_pelajaran_id')
                    ? 'guru:'.old('guru_mata_pelajaran_id')
                    : (
                        $jadwalPelajaran?->mata_pelajaran_id
                            ? 'kegiatan:'.$jadwalPelajaran->mata_pelajaran_id
                            : ($jadwalPelajaran?->guru_mata_pelajaran_id ? 'guru:'.$jadwalPelajaran->guru_mata_pelajaran_id : '')
                    )
            ),
    );
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
        <h2 class="panel-title">Status jadwal</h2>
        <p class="help-text">Jadwal aktif akan dipakai sebagai acuan pembelajaran kelas.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Jadwal aktif</span>
                <span class="help-text">Tampil dalam jadwal kelas</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Jadwal</h2>
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
                    <label for="kelas_id">Kelas</label>
                    <select id="kelas_id" name="kelas_id" class="{{ $selectClass('kelas_id') }}" required>
                        <option value="">Pilih kelas</option>
                        @foreach ($tahunPelajaran as $tahun)
                            @php
                                $kelasTahunIni = $kelas->where('tahun_pelajaran_id', $tahun->id);
                            @endphp
                            @if ($kelasTahunIni->isNotEmpty())
                                <optgroup label="{{ $tahun->nama }}{{ $tahun->aktif ? ' - aktif' : '' }}">
                                    @foreach ($kelasTahunIni as $item)
                                        <option
                                            value="{{ $item->id }}"
                                            data-year="{{ $item->tahun_pelajaran_id }}"
                                            @selected((string) $nilai('kelas_id', $kelasDipilih ?? null) === (string) $item->id)
                                        >
                                            {{ $item->nama }}{{ $item->tingkat ? ' - kelas ' . $item->tingkat : '' }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    @error('kelas_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="hari">Hari</label>
                    <select id="hari" name="hari" class="{{ $selectClass('hari') }}" required>
                        <option value="">Pilih hari</option>
                        @foreach ($daftarHari as $kode => $label)
                            <option value="{{ $kode }}" @selected($nilai('hari') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('hari')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_pelajaran_id">Jam pelajaran</label>
                    <select
                        id="jam_pelajaran_id"
                        name="jam_pelajaran_id"
                        class="{{ $selectClass('jam_pelajaran_id') }}"
                        data-selected="{{ $nilai('jam_pelajaran_id') }}"
                        required
                    >
                        <option value="">Pilih jam</option>
                        @foreach ($jamPelajaran as $item)
                            <option
                                value="{{ $item->id }}"
                                data-day="{{ $item->hari }}"
                                data-type="{{ $item->jenis }}"
                                @selected((string) $nilai('jam_pelajaran_id') === (string) $item->id)
                            >
                                {{ $item->labelJam() }} - {{ $item->labelJenis() }}
                            </option>
                        @endforeach
                    </select>
                    <p class="help-text">Pilih slot jenis Pelajaran yang sesuai dengan hari.</p>
                    @error('jam_pelajaran_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="pilihan_jadwal">Pelajaran atau kegiatan</label>
                    <select
                        id="pilihan_jadwal"
                        name="pilihan_jadwal"
                        class="{{ $errors->hasAny(['pilihan_jadwal', 'guru_mata_pelajaran_id', 'mata_pelajaran_id']) ? 'select is-invalid' : 'select' }}"
                        data-selected="{{ $pilihanJadwalTerpilih }}"
                        required
                    >
                        <option value="">Pilih pelajaran atau kegiatan</option>
                        @foreach ($guruMataPelajaran as $item)
                            <option
                                value="guru:{{ $item->id }}"
                                data-year="{{ $item->tahun_pelajaran_id }}"
                                data-class="{{ $item->kelas_id }}"
                                @selected($pilihanJadwalTerpilih === 'guru:'.$item->id)
                            >
                                {{ $item->mataPelajaran?->nama ?? '-' }} - {{ $item->pegawai?->nama_lengkap ?? '-' }}
                            </option>
                        @endforeach
                        @foreach ($kegiatanJadwal as $item)
                            @php
                                $kelasKegiatan = $kelas
                                    ->filter(function ($kelasItem) use ($item) {
                                        if ($item->pengaturanTingkat->isNotEmpty()) {
                                            return $item->pengaturanTingkat->contains(fn ($pengaturan) => (
                                                (int) $pengaturan->tahun_pelajaran_id === (int) $kelasItem->tahun_pelajaran_id
                                                && (int) $pengaturan->tingkat === (int) $kelasItem->tingkat
                                                && $pengaturan->aktif
                                            ));
                                        }

                                        return ! $item->tingkat || (int) $item->tingkat === (int) $kelasItem->tingkat;
                                    })
                                    ->pluck('id')
                                    ->implode(',');
                            @endphp
                            <option
                                value="kegiatan:{{ $item->id }}"
                                data-classes="{{ $kelasKegiatan }}"
                                @selected($pilihanJadwalTerpilih === 'kegiatan:'.$item->id)
                            >
                                [{{ $item->kelompok }}] {{ $item->nama }}
                            </option>
                        @endforeach
                    </select>
                    <p class="help-text" id="pilihan-jadwal-help">Pilih kelas untuk menampilkan pelajaran, kokurikuler, dan ekstrakurikuler yang tersedia.</p>
                    @error('pilihan_jadwal')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                    @error('guru_mata_pelajaran_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                    @error('mata_pelajaran_id')
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
            <a href="{{ route('jadwal-pelajaran.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (() => {
            const tahun = document.getElementById('tahun_pelajaran_id');
            const kelas = document.getElementById('kelas_id');
            const hari = document.getElementById('hari');
            const jam = document.getElementById('jam_pelajaran_id');
            const pilihanJadwal = document.getElementById('pilihan_jadwal');
            const bantuanPilihan = document.getElementById('pilihan-jadwal-help');

            const dataKelas = Array.from(kelas.querySelectorAll('option[data-year]')).map((option) => ({
                value: option.value,
                label: option.textContent.trim(),
                year: option.dataset.year,
            }));
            const dataJam = Array.from(jam.querySelectorAll('option[data-day]')).map((option) => ({
                value: option.value,
                label: option.textContent.trim(),
                day: option.dataset.day,
                type: option.dataset.type,
            }));
            const dataPilihan = Array.from(pilihanJadwal.querySelectorAll('option[data-class], option[data-classes]')).map((option) => ({
                value: option.value,
                label: option.textContent.trim(),
                classIds: option.dataset.class
                    ? [option.dataset.class]
                    : (option.dataset.classes || '').split(',').filter(Boolean),
            }));
            const pilihanAwal = {
                kelas: kelas.value,
                jam: jam.dataset.selected || jam.value,
                pilihanJadwal: pilihanJadwal.dataset.selected || pilihanJadwal.value,
            };

            const isiPilihan = (select, daftar, placeholder, nilaiTerpilih = '') => {
                select.replaceChildren();
                const pilihanKosong = document.createElement('option');
                pilihanKosong.value = '';
                pilihanKosong.textContent = placeholder;
                select.appendChild(pilihanKosong);

                daftar.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = item.label;
                    select.appendChild(option);
                });

                select.value = daftar.some((item) => item.value === String(nilaiTerpilih))
                    ? String(nilaiTerpilih)
                    : '';
            };

            const saringPilihanJadwal = (nilaiTerpilih = pilihanJadwal.value) => {
                const kelasId = kelas.value;
                const sesuai = dataPilihan.filter((item) => item.classIds.includes(kelasId));
                const placeholder = ! kelasId
                    ? 'Pilih kelas terlebih dahulu'
                    : (sesuai.length ? 'Pilih pelajaran atau kegiatan' : 'Belum ada pilihan untuk kelas ini');

                isiPilihan(pilihanJadwal, sesuai, placeholder, nilaiTerpilih);
                pilihanJadwal.disabled = ! kelasId;
                bantuanPilihan.textContent = ! kelasId
                    ? 'Pilih kelas untuk menampilkan pelajaran, kokurikuler, dan ekstrakurikuler yang tersedia.'
                    : (sesuai.length
                        ? `${sesuai.length} pelajaran atau kegiatan tersedia untuk kelas yang dipilih.`
                        : 'Belum ada guru pengampu, kokurikuler, atau ekstrakurikuler untuk kelas ini.');
            };

            const saringKelas = (nilaiTerpilih = kelas.value, pilihanTerpilih = pilihanJadwal.value) => {
                const tahunId = tahun.value;
                const sesuai = dataKelas.filter((item) => item.year === tahunId);
                const placeholder = tahunId
                    ? (sesuai.length ? 'Pilih kelas' : 'Belum ada kelas pada tahun ini')
                    : 'Pilih tahun pelajaran terlebih dahulu';

                isiPilihan(kelas, sesuai, placeholder, nilaiTerpilih);
                kelas.disabled = ! tahunId;
                saringPilihanJadwal(pilihanTerpilih);
            };

            const saringJam = (nilaiTerpilih = jam.value) => {
                const hariDipilih = hari.value;
                const sesuai = dataJam.filter((item) => (
                    item.day === hariDipilih && item.type === 'pelajaran'
                ));
                const placeholder = ! hariDipilih
                    ? 'Pilih hari terlebih dahulu'
                    : (sesuai.length ? 'Pilih jam pelajaran' : 'Belum ada slot Pelajaran pada hari ini');

                isiPilihan(jam, sesuai, placeholder, nilaiTerpilih);
                jam.disabled = ! hariDipilih;
            };

            tahun.addEventListener('change', () => saringKelas());
            kelas.addEventListener('change', () => saringPilihanJadwal());
            hari.addEventListener('change', () => saringJam());

            saringKelas(pilihanAwal.kelas, pilihanAwal.pilihanJadwal);
            saringJam(pilihanAwal.jam);
        })();
    </script>
@endpush
