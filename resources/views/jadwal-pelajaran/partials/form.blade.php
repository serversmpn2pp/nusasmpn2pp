@php
    $jadwalPelajaran = $jadwalPelajaran ?? null;
    $tahunAktifId = $tahunPelajaran->firstWhere('aktif', true)?->id;
    $nilai = fn (string $field, mixed $default = '') => old($field, $jadwalPelajaran?->{$field} ?? $default);
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
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
                                        <option value="{{ $item->id }}" @selected((string) $nilai('kelas_id', $kelasDipilih ?? null) === (string) $item->id)>
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
                    <select id="jam_pelajaran_id" name="jam_pelajaran_id" class="{{ $selectClass('jam_pelajaran_id') }}" required>
                        <option value="">Pilih jam</option>
                        @foreach ($daftarHari as $kodeHari => $labelHari)
                            @php
                                $jamHariIni = $jamPelajaran->where('hari', $kodeHari);
                            @endphp
                            @if ($jamHariIni->isNotEmpty())
                                <optgroup label="{{ $labelHari }}">
                                    @foreach ($jamHariIni as $item)
                                        <option value="{{ $item->id }}" @selected((string) $nilai('jam_pelajaran_id') === (string) $item->id)>
                                            {{ $item->labelJam() }} - {{ $item->labelJenis() }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    <p class="help-text">Pilih slot jenis Pelajaran yang sesuai dengan hari.</p>
                    @error('jam_pelajaran_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="guru_mata_pelajaran_id">Guru mata pelajaran</label>
                    <select id="guru_mata_pelajaran_id" name="guru_mata_pelajaran_id" class="{{ $selectClass('guru_mata_pelajaran_id') }}" required>
                        <option value="">Pilih guru mapel</option>
                        @foreach ($guruMataPelajaran->groupBy('kelas_id') as $kelasMapelId => $items)
                            @php
                                $kelasLabel = $items->first()?->kelas?->nama ?? 'Kelas tidak ditemukan';
                                $tahunLabel = $items->first()?->tahunPelajaran?->nama ?? '-';
                            @endphp
                            <optgroup label="{{ $kelasLabel }} - {{ $tahunLabel }}">
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}" @selected((string) $nilai('guru_mata_pelajaran_id') === (string) $item->id)>
                                        {{ $item->mataPelajaran?->nama ?? '-' }} - {{ $item->pegawai?->nama_lengkap ?? '-' }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="help-text">Guru mapel harus sudah dibuat pada menu Guru Mapel untuk kelas dan tahun pelajaran yang sama.</p>
                    @error('guru_mata_pelajaran_id')
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
