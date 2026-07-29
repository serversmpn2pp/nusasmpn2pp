@php
    $tahunAktifId = $tahunPelajaran->firstWhere('aktif', true)?->id;
    $tahunTerpilih = old('tahun_pelajaran_id', $tahunPelajaranDipilih ?: $tahunAktifId);
    $kelasTerpilih = collect(old('kelas_ids', []))->map(fn ($id) => (string) $id);
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
    $romawi = [7 => 'VII', 8 => 'VIII', 9 => 'IX'];
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
        <h2 class="panel-title">Status penugasan</h2>
        <p class="help-text">Penugasan aktif langsung dapat dipakai pada jadwal pelajaran, komponen nilai, dan perangkat ajar.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom: 0;">Penugasan aktif</span>
                <span class="help-text">Berlaku untuk seluruh kelas terpilih</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) old('aktif', true))>
        </label>

        <dl class="quick-facts" style="margin-top: 20px;">
            <div>
                <dt>Alur singkat</dt>
                <dd>Guru → Mapel → Banyak kelas</dd>
            </div>
            <div>
                <dt>Duplikasi</dt>
                <dd>Dicegah otomatis</dd>
            </div>
        </dl>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Penugasan</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                    <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="{{ $selectClass('tahun_pelajaran_id') }}" required autofocus>
                        <option value="">Pilih tahun pelajaran</option>
                        @foreach ($tahunPelajaran as $item)
                            <option value="{{ $item->id }}" @selected((string) $tahunTerpilih === (string) $item->id)>
                                {{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('tahun_pelajaran_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="pegawai_id">Guru pengampu</label>
                    <select id="pegawai_id" name="pegawai_id" class="{{ $selectClass('pegawai_id') }}" required>
                        <option value="">Pilih guru</option>
                        @foreach ($pegawai as $item)
                            <option value="{{ $item->id }}" @selected((string) old('pegawai_id') === (string) $item->id)>
                                {{ $item->nama_lengkap }}{{ $item->nip ? ' - ' . $item->nip : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('pegawai_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="mata_pelajaran_id">Mata pelajaran</label>
                    <select id="mata_pelajaran_id" name="mata_pelajaran_id" class="{{ $selectClass('mata_pelajaran_id') }}" required>
                        <option value="">Pilih mata pelajaran</option>
                        @foreach ($mataPelajaran as $item)
                            @php
                                $tingkatPerTahun = $item->pengaturanTingkat
                                    ->groupBy('tahun_pelajaran_id')
                                    ->map(fn ($pengaturan) => $pengaturan->pluck('tingkat')->map(fn ($tingkat) => (int) $tingkat)->values());
                            @endphp
                            <option
                                value="{{ $item->id }}"
                                data-levels='@json($tingkatPerTahun)'
                                @selected((string) old('mata_pelajaran_id') === (string) $item->id)
                            >
                                {{ $item->nama }}
                            </option>
                        @endforeach
                    </select>
                    @error('mata_pelajaran_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jenis_penugasan">Jenis penugasan</label>
                    <select id="jenis_penugasan" name="jenis_penugasan" class="{{ $selectClass('jenis_penugasan') }}" required>
                        <option value="pengampu" @selected(old('jenis_penugasan', 'pengampu') === 'pengampu')>Pengampu</option>
                        <option value="pendamping" @selected(old('jenis_penugasan') === 'pendamping')>Pendamping</option>
                        <option value="koordinator" @selected(old('jenis_penugasan') === 'koordinator')>Koordinator</option>
                    </select>
                    @error('jenis_penugasan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" class="{{ $textareaClass('keterangan') }}" placeholder="Opsional">{{ old('keterangan') }}</textarea>
                    @error('keterangan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <div>
                <h2 class="panel-title">Pilih Kelas</h2>
                <p class="help-text">Kelas otomatis disaring berdasarkan tahun pelajaran dan tingkat yang tersedia untuk mata pelajaran.</p>
            </div>

            <div class="class-selection-toolbar">
                <div>
                    <strong id="jumlah-kelas-dipilih">0 kelas dipilih</strong>
                    <p class="help-text" id="jumlah-kelas-tersedia">Pilih tahun dan mata pelajaran.</p>
                </div>
                <div class="actions">
                    <button type="button" class="button button-muted" id="pilih-semua-kelas">Pilih semua</button>
                    <button type="button" class="button button-muted" id="bersihkan-kelas">Bersihkan</button>
                </div>
            </div>

            @error('kelas_ids')
                <p class="error-text" style="margin-bottom: 12px;">{{ $message }}</p>
            @enderror
            @error('kelas_ids.*')
                <p class="error-text" style="margin-bottom: 12px;">{{ $message }}</p>
            @enderror

            <div class="class-level-stack" id="daftar-kelas">
                @foreach ($kelas->where('aktif', true)->groupBy('tingkat')->sortKeys() as $tingkat => $kelasTingkat)
                    <section class="class-level-group" data-class-group data-level="{{ $tingkat }}">
                        <div class="class-level-heading">
                            <h3>Tingkat {{ $romawi[$tingkat] ?? $tingkat }}</h3>
                        </div>

                        <div class="class-selection-grid">
                            @foreach ($kelasTingkat as $item)
                                <label
                                    class="class-selection-item"
                                    data-class-item
                                    data-year="{{ $item->tahun_pelajaran_id }}"
                                    data-level="{{ $item->tingkat }}"
                                >
                                    <input
                                        type="checkbox"
                                        name="kelas_ids[]"
                                        value="{{ $item->id }}"
                                        @checked($kelasTerpilih->contains((string) $item->id))
                                    >
                                    <span>
                                        <strong>{{ $item->nama }}</strong>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <p class="class-selection-empty" id="kelas-kosong" hidden>Tidak ada kelas yang sesuai dengan pilihan ini.</p>
        </section>

        <div class="form-actions">
            <a href="{{ route('guru-mata-pelajaran.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">Simpan Penugasan</button>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (() => {
            const tahun = document.getElementById('tahun_pelajaran_id');
            const mataPelajaran = document.getElementById('mata_pelajaran_id');
            const itemKelas = Array.from(document.querySelectorAll('[data-class-item]'));
            const kelompokKelas = Array.from(document.querySelectorAll('[data-class-group]'));
            const jumlahDipilih = document.getElementById('jumlah-kelas-dipilih');
            const jumlahTersedia = document.getElementById('jumlah-kelas-tersedia');
            const kosong = document.getElementById('kelas-kosong');
            const tombolPilihSemua = document.getElementById('pilih-semua-kelas');
            const tombolBersihkan = document.getElementById('bersihkan-kelas');

            const pengaturanMapel = () => {
                const data = mataPelajaran.selectedOptions[0]?.dataset.levels;

                if (! data) {
                    return {};
                }

                try {
                    return JSON.parse(data);
                } catch {
                    return {};
                }
            };

            const perbaruiJumlah = () => {
                const dipilih = itemKelas.filter((item) => item.querySelector('input').checked).length;
                jumlahDipilih.textContent = `${dipilih} kelas dipilih`;
            };

            const saringKelas = () => {
                const konfigurasi = pengaturanMapel();
                const tingkatTersedia = (konfigurasi[tahun.value] || []).map(String);
                let tersedia = 0;

                itemKelas.forEach((item) => {
                    const sesuaiTahun = tahun.value && item.dataset.year === tahun.value;
                    const sesuaiMapel = ! mataPelajaran.value || tingkatTersedia.includes(item.dataset.level);
                    const tampil = sesuaiTahun && sesuaiMapel;
                    const input = item.querySelector('input');

                    item.hidden = ! tampil;
                    input.disabled = ! tampil;

                    if (! tampil) {
                        input.checked = false;
                    } else {
                        tersedia++;
                    }
                });

                kelompokKelas.forEach((kelompok) => {
                    kelompok.hidden = ! kelompok.querySelector('[data-class-item]:not([hidden])');
                });

                kosong.hidden = tersedia > 0;
                jumlahTersedia.textContent = tersedia > 0
                    ? `${tersedia} kelas tersedia untuk dipilih.`
                    : 'Tidak ada kelas yang sesuai.';
                tombolPilihSemua.disabled = tersedia === 0;
                perbaruiJumlah();
            };

            itemKelas.forEach((item) => {
                item.querySelector('input').addEventListener('change', perbaruiJumlah);
            });
            tahun.addEventListener('change', saringKelas);
            mataPelajaran.addEventListener('change', saringKelas);
            tombolPilihSemua.addEventListener('click', () => {
                itemKelas
                    .filter((item) => ! item.hidden)
                    .forEach((item) => {
                        item.querySelector('input').checked = true;
                    });
                perbaruiJumlah();
            });
            tombolBersihkan.addEventListener('click', () => {
                itemKelas.forEach((item) => {
                    item.querySelector('input').checked = false;
                });
                perbaruiJumlah();
            });

            saringKelas();
        })();
    </script>
@endpush
