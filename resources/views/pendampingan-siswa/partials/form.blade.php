@php
    $nilai = fn (string $field, $bawaan = '') => old($field, data_get($pendampinganSiswa, $field, $bawaan));
    $inputClass = fn (string $field, string $kelas) => $kelas.($errors->has($field) ? ' is-invalid' : '');
    $sedangEdit = $pendampinganSiswa->exists;
    $dalamKonteksGuruWali = $konteksGuruWali ?? false;
@endphp

<style>
    .follow-student{background:#eef4f9;border:1px solid #d7e3ed;border-radius:8px;display:grid;gap:14px;grid-template-columns:minmax(0,1fr) auto;margin-bottom:20px;padding:16px 18px}
    .follow-student strong{color:var(--primary-dark);display:block;font-size:17px}
    .follow-student span{color:var(--muted);display:block;font-size:13px;line-height:1.5;margin-top:4px}
    .follow-source{align-self:center;background:#fff;border:1px solid var(--border);border-radius:6px;color:var(--primary-dark);font-size:12px;font-weight:800;max-width:280px;padding:8px 10px;text-align:right}
    .follow-form-grid{display:grid;gap:18px;grid-template-columns:repeat(2,minmax(0,1fr))}
    .follow-full{grid-column:1/-1}
    @media(max-width:720px){.follow-student{grid-template-columns:1fr}.follow-source{text-align:left}.follow-form-grid{grid-template-columns:1fr}.follow-full{grid-column:auto}}
</style>

<div class="follow-student">
    <div>
        <strong>{{ $siswa->nama_lengkap }}</strong>
        <span>
            {{ $anggotaKelas?->kelas?->nama ?: 'Belum ditempatkan di kelas' }}
            &middot; NISN {{ $siswa->nisn ?: '-' }}
            &middot; Tahun {{ $tahunPelajaran->nama }}
        </span>
    </div>
    @if($peringatan)
        <div class="follow-source">Sumber: {{ $peringatan->labelJenis() }}</div>
    @endif
</div>

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:18px">
        Periksa kembali data yang masih belum lengkap.
    </div>
@endif

<form method="POST" action="{{ $sedangEdit ? route($dalamKonteksGuruWali ? 'pendampingan-siswa-wali.update' : 'pendampingan-siswa.update', $pendampinganSiswa) : route('pendampingan-siswa.store') }}" class="panel panel-pad">
    @csrf
    @if($sedangEdit)
        @method('PUT')
    @else
        <input type="hidden" name="siswa_id" value="{{ $siswa->id }}">
        <input type="hidden" name="tahun_pelajaran_id" value="{{ $tahunPelajaran->id }}">
        <input type="hidden" name="peringatan_dini_siswa_id" value="{{ $peringatan?->id }}">
    @endif

    <div class="follow-form-grid">
        <div class="field">
            <label for="jenis_tindakan">Jenis tindakan</label>
            <select id="jenis_tindakan" name="jenis_tindakan" class="{{ $inputClass('jenis_tindakan', 'select') }}">
                @foreach($daftarJenisTindakan as $kode => $label)
                    <option value="{{ $kode }}" @selected($nilai('jenis_tindakan') === $kode)>{{ $label }}</option>
                @endforeach
            </select>
            @error('jenis_tindakan')<p class="error-text">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="tanggal_tindak_lanjut">Tanggal tindak lanjut</label>
            <input id="tanggal_tindak_lanjut" type="date" name="tanggal_tindak_lanjut"
                value="{{ old('tanggal_tindak_lanjut', $pendampinganSiswa->tanggal_tindak_lanjut?->format('Y-m-d')) }}"
                class="{{ $inputClass('tanggal_tindak_lanjut', 'input') }}">
            @error('tanggal_tindak_lanjut')<p class="error-text">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="petugas_pegawai_id">Petugas penanggung jawab</label>
            <select id="petugas_pegawai_id" name="petugas_pegawai_id" class="{{ $inputClass('petugas_pegawai_id', 'select') }}">
                <option value="">Pilih petugas</option>
                @foreach($daftarPegawai as $pegawai)
                    <option value="{{ $pegawai->id }}" @selected((string)$nilai('petugas_pegawai_id') === (string)$pegawai->id)>
                        {{ $pegawai->nama_lengkap }}{{ $pegawai->jabatan_utama ? ' - '.$pegawai->jabatan_utama : '' }}
                    </option>
                @endforeach
            </select>
            @error('petugas_pegawai_id')<p class="error-text">{{ $message }}</p>@enderror
        </div>

        @if($sedangEdit)
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="{{ $inputClass('status', 'select') }}">
                    @foreach($daftarStatus as $kode => $label)
                        <option value="{{ $kode }}" @selected($nilai('status') === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<p class="error-text">{{ $message }}</p>@enderror
            </div>
        @endif

        <div class="field follow-full">
            <label for="catatan">Catatan singkat</label>
            <textarea id="catatan" name="catatan" class="{{ $inputClass('catatan', 'textarea') }}"
                placeholder="Contoh: Siswa diajak berdiskusi bersama wali kelas mengenai keterlambatan berulang.">{{ $nilai('catatan') }}</textarea>
            <p class="help-text">Tuliskan tindakan yang dilakukan atau rencana pertemuan secara singkat.</p>
            @error('catatan')<p class="error-text">{{ $message }}</p>@enderror
        </div>

        @if($sedangEdit)
            <div class="field follow-full">
                <label for="hasil">Hasil penanganan</label>
                <textarea id="hasil" name="hasil" class="{{ $inputClass('hasil', 'textarea') }}"
                    placeholder="Wajib diisi ketika status diubah menjadi Selesai.">{{ $nilai('hasil') }}</textarea>
                @error('hasil')<p class="error-text">{{ $message }}</p>@enderror
            </div>
        @endif
    </div>

    <div class="actions" style="justify-content:flex-end;margin-top:20px">
        <a class="button button-muted" href="{{ route($dalamKonteksGuruWali ? 'pendampingan-siswa-wali.index' : 'pendampingan-siswa.index', ['tahun_pelajaran_id' => $tahunPelajaran->id]) }}">Kembali</a>
        <button class="button button-primary">{{ $sedangEdit ? 'Simpan Perubahan' : 'Mulai Tindak Lanjut' }}</button>
    </div>
</form>
