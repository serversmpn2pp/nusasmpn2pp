@php
    $guruMataPelajaran = $guruMataPelajaran ?? null;
    $tahunAktifId = $tahunPelajaran->firstWhere('aktif', true)?->id;
    $nilai = fn (string $field, mixed $default = '') => old($field, $guruMataPelajaran?->{$field} ?? $default);
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
        <h2 class="panel-title">Status penugasan</h2>
        <p class="help-text">Penugasan aktif akan dipakai sebagai acuan guru pengampu saat input nilai dan rekap akademik.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Penugasan aktif</span>
                <span class="help-text">Tampil dalam proses nilai</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>
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
                                        <option value="{{ $item->id }}" @selected((string) $nilai('kelas_id') === (string) $item->id)>
                                            {{ $item->nama }}{{ $item->tingkat ? ' - kelas ' . $item->tingkat : '' }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    <p class="help-text">Pilih kelas yang berada pada tahun pelajaran yang sama.</p>
                    @error('kelas_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="mata_pelajaran_id">Mata pelajaran</label>
                    <select id="mata_pelajaran_id" name="mata_pelajaran_id" class="{{ $selectClass('mata_pelajaran_id') }}" required>
                        <option value="">Pilih mata pelajaran</option>
                        @foreach ($mataPelajaran as $item)
                            <option value="{{ $item->id }}" @selected((string) $nilai('mata_pelajaran_id') === (string) $item->id)>
                                {{ $item->nama }}{{ $item->pengaturanTingkat->isNotEmpty() ? ' - ' . $item->pengaturanTingkat->pluck('kode')->join(' / ') : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('mata_pelajaran_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="pegawai_id">Guru pengampu</label>
                    <select id="pegawai_id" name="pegawai_id" class="{{ $selectClass('pegawai_id') }}" required>
                        <option value="">Pilih guru</option>
                        @foreach ($pegawai as $item)
                            <option value="{{ $item->id }}" @selected((string) $nilai('pegawai_id') === (string) $item->id)>
                                {{ $item->nama_lengkap }}{{ $item->nip ? ' - ' . $item->nip : '' }}{{ $item->jabatan_utama ? ' - ' . $item->jabatan_utama : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('pegawai_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jenis_penugasan">Jenis penugasan</label>
                    <select id="jenis_penugasan" name="jenis_penugasan" class="{{ $selectClass('jenis_penugasan') }}" required>
                        <option value="pengampu" @selected($nilai('jenis_penugasan', 'pengampu') === 'pengampu')>Pengampu</option>
                        <option value="pendamping" @selected($nilai('jenis_penugasan') === 'pendamping')>Pendamping</option>
                        <option value="koordinator" @selected($nilai('jenis_penugasan') === 'koordinator')>Koordinator</option>
                    </select>
                    @error('jenis_penugasan')
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
            <a href="{{ route('guru-mata-pelajaran.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
