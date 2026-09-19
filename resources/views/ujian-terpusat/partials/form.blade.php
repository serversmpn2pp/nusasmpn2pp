@php
    $nilai = fn (string $field, $default = '') => old($field, isset($kegiatan) ? data_get($kegiatan, $field, $default) : $default);
    $formatTanggal = fn (string $field) => old($field, isset($kegiatan) ? data_get($kegiatan, $field)?->format('Y-m-d') : '');
    $inputClass = fn (string $field) => 'input'.($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select'.($errors->has($field) ? ' is-invalid' : '');
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Ada data yang perlu diperbaiki.</strong>
        <ul>
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="form-shell">
    <aside class="panel panel-pad">
        <h2 class="panel-title">Status kegiatan</h2>
        <p class="help-text">Gunakan Persiapan sampai panitia, sesi, dan ruang selesai disusun.</p>

        <div class="field" style="margin-top: 16px;">
            <label for="status">Status</label>
            <select id="status" name="status" class="{{ $selectClass('status') }}" required>
                @foreach ($daftarStatus as $kode => $label)
                    <option value="{{ $kode }}" @selected($nilai('status', 'draft') === $kode)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <dl class="quick-facts" style="margin-top: 18px;">
            <div><dt>Langkah berikutnya</dt><dd>Panitia</dd></div>
            <div><dt>Setelah panitia</dt><dd>Sesi dan ruang</dd></div>
        </dl>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Ujian Terpusat</h2>
            <p class="help-text" style="margin-top: 6px;">Cukup isi identitas rangkaian ujian. Mata pelajaran dan paket soal ditambahkan pada tahap jadwal.</p>

            <div class="form-grid">
                <div class="field span-2">
                    <label for="nama">Nama kegiatan</label>
                    <input id="nama" name="nama" value="{{ $nilai('nama') }}" class="{{ $inputClass('nama') }}" placeholder="Contoh: Sumatif Akhir Semester Ganjil 2026/2027" required autofocus>
                    @error('nama')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="jenis_ujian_cbt_id">Jenis ujian</label>
                    <select id="jenis_ujian_cbt_id" name="jenis_ujian_cbt_id" class="{{ $selectClass('jenis_ujian_cbt_id') }}" required>
                        <option value="">Pilih jenis ujian</option>
                        @foreach ($daftarJenis as $jenis)
                            <option value="{{ $jenis->id }}" @selected((string) $nilai('jenis_ujian_cbt_id') === (string) $jenis->id)>{{ $jenis->nama }}</option>
                        @endforeach
                    </select>
                    @error('jenis_ujian_cbt_id')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                    <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="{{ $selectClass('tahun_pelajaran_id') }}" required>
                        <option value="">Pilih tahun pelajaran</option>
                        @foreach ($daftarTahunPelajaran as $tahun)
                            <option value="{{ $tahun->id }}" @selected((string) $nilai('tahun_pelajaran_id', $tahunPelajaranAwal?->id) === (string) $tahun->id)>{{ $tahun->nama }}{{ $tahun->aktif ? ' - aktif' : '' }}</option>
                        @endforeach
                    </select>
                    @error('tahun_pelajaran_id')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" class="{{ $selectClass('semester') }}" required>
                        <option value="ganjil" @selected($nilai('semester', 'ganjil') === 'ganjil')>Ganjil</option>
                        <option value="genap" @selected($nilai('semester') === 'genap')>Genap</option>
                    </select>
                </div>
                <div class="field">
                    <label for="tanggal_mulai">Tanggal mulai</label>
                    <input id="tanggal_mulai" name="tanggal_mulai" type="date" value="{{ $formatTanggal('tanggal_mulai') }}" class="{{ $inputClass('tanggal_mulai') }}" required>
                    @error('tanggal_mulai')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="tanggal_selesai">Tanggal selesai</label>
                    <input id="tanggal_selesai" name="tanggal_selesai" type="date" value="{{ $formatTanggal('tanggal_selesai') }}" class="{{ $inputClass('tanggal_selesai') }}" required>
                    @error('tanggal_selesai')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div class="field span-2">
                    <label for="keterangan">Catatan panitia</label>
                    <textarea id="keterangan" name="keterangan" class="textarea" placeholder="Opsional">{{ $nilai('keterangan') }}</textarea>
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Penilaian Pilihan Ganda Kompleks</h2>
            @php $pgkTerkunci = isset($kegiatan) && $kegiatan->penilaianPgkTerkunci(); @endphp
            <div class="field" style="margin-top:16px;">
                <label for="penilaian_pgk">Aturan untuk seluruh mapel pada kegiatan ini</label>
                <select id="penilaian_pgk" name="penilaian_pgk" class="{{ $selectClass('penilaian_pgk') }}" @disabled($pgkTerkunci)>
                    @foreach (\App\Models\KegiatanUjianCbt::PENILAIAN_PGK as $kode => $label)
                        <option value="{{ $kode }}" @selected(($pgkTerkunci ? $kegiatan->penilaian_pgk : $nilai('penilaian_pgk', 'dikotomi')) === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('penilaian_pgk')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <dl class="quick-facts" style="margin-top:14px;">
                <div><dt>Dikotomi</dt><dd>Semua pilihan harus sesuai kunci untuk mendapat skor penuh. Selain itu, skor 0.</dd></div>
                <div><dt>Parsial</dt><dd>(Pilihan benar dicentang - pilihan salah dicentang) / jumlah pilihan benar dalam kunci, dikalikan bobot soal. Skor minimum 0.</dd></div>
            </dl>
            <p class="help-text" style="margin-top:12px;">Contoh kunci A dan C, bobot 3: memilih A saja mendapat 0 pada dikotomi atau 1,50 pada parsial. Tidak menjawab mendapat 0. Jenis soal lain tidak berubah.</p>
            @if ($pgkTerkunci)
                <p class="alert" style="margin-top:12px;" role="status">Pengaturan dikunci karena peserta sudah mulai mengerjakan ujian.</p>
            @else
                <p class="help-text" style="margin-top:12px;">Hanya admin yang mengatur. Aturan dikunci setelah peserta pertama mulai mengerjakan.</p>
            @endif
        </section>

        <div class="form-actions">
            <a href="{{ route('ujian-terpusat.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol }}</button>
        </div>
    </div>
</div>
