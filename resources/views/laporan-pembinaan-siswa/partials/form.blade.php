@php
    $laporanPembinaanSiswa = $laporanPembinaanSiswa ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $laporanPembinaanSiswa?->{$field} ?? $default);
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');

    $tanggalValue = old(
        'tanggal_kejadian',
        filled($laporanPembinaanSiswa?->tanggal_kejadian)
            ? \Illuminate\Support\Carbon::parse($laporanPembinaanSiswa->tanggal_kejadian)->format('Y-m-d')
            : now()->toDateString(),
    );
    $waktuValue = old('waktu_kejadian', $laporanPembinaanSiswa?->waktuKejadianRingkas());
@endphp

<style>
    .student-picker-help {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: space-between;
    }

    .student-picker-count {
        color: var(--primary-dark);
        font-weight: 800;
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
        <h2 class="panel-title">Status laporan</h2>
        <p class="help-text">Status membantu BK, wali kelas, dan pimpinan melihat tahapan penanganan.</p>

        <div class="field" style="margin-top: 18px;">
            <label for="status">Status</label>
            <select id="status" name="status" class="{{ $selectClass('status') }}" required>
                @foreach ($daftarStatus as $kode => $label)
                    <option value="{{ $kode }}" @selected((string) $nilai('status', 'baru') === (string) $kode)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="field" style="margin-top: 14px;">
            <label for="tingkat">Tingkat</label>
            <select id="tingkat" name="tingkat" class="{{ $selectClass('tingkat') }}" required>
                @foreach ($daftarTingkat as $kode => $label)
                    <option value="{{ $kode }}" @selected((string) $nilai('tingkat', 'ringan') === (string) $kode)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="help-text">Ringan, sedang, atau berat dapat diubah sesuai hasil penanganan.</p>
            @error('tingkat')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Data kejadian</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="tanggal_kejadian">Tanggal kejadian</label>
                    <input id="tanggal_kejadian" name="tanggal_kejadian" type="date" value="{{ $tanggalValue }}" class="{{ $inputClass('tanggal_kejadian') }}" required>
                    @error('tanggal_kejadian')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="waktu_kejadian">Waktu kejadian</label>
                    <input id="waktu_kejadian" name="waktu_kejadian" type="time" value="{{ $waktuValue }}" class="{{ $inputClass('waktu_kejadian') }}">
                    @error('waktu_kejadian')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="tempat_kejadian">Tempat kejadian</label>
                    <input id="tempat_kejadian" name="tempat_kejadian" type="text" value="{{ $nilai('tempat_kejadian') }}" placeholder="Contoh: halaman sekolah, kantin, koridor lantai 2, luar sekolah" class="{{ $inputClass('tempat_kejadian') }}">
                    <p class="help-text">Diisi sesuai lokasi sebenarnya. Kelas tetap boleh dikosongkan jika kejadian tidak terkait ruang kelas.</p>
                    @error('tempat_kejadian')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kategori_pembinaan_siswa_id">Kategori</label>
                    <select id="kategori_pembinaan_siswa_id" name="kategori_pembinaan_siswa_id" class="{{ $selectClass('kategori_pembinaan_siswa_id') }}" required>
                        <option value="">Pilih kategori</option>
                        @foreach ($daftarKategoriPembinaan as $kategori)
                            <option value="{{ $kategori->id }}" @selected((string) $nilai('kategori_pembinaan_siswa_id') === (string) $kategori->id)>
                                {{ $kategori->nama }}
                            </option>
                        @endforeach
                    </select>
                    @error('kategori_pembinaan_siswa_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="tahun_pelajaran_id">Tahun pelajaran siswa</label>
                    <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="{{ $selectClass('tahun_pelajaran_id') }}">
                        <option value="">Semua tahun / otomatis dari tahun aktif</option>
                        @foreach ($daftarTahunPelajaran as $tahunPelajaran)
                            <option value="{{ $tahunPelajaran->id }}" @selected((string) $nilai('tahun_pelajaran_id') === (string) $tahunPelajaran->id)>
                                {{ $tahunPelajaran->nama }}{{ $tahunPelajaran->aktif ? ' (aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('tahun_pelajaran_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kelas_id">Kelas siswa</label>
                    <select id="kelas_id" name="kelas_id" class="{{ $selectClass('kelas_id') }}">
                        <option value="">Semua kelas / otomatis dari data anggota kelas</option>
                        @foreach ($daftarKelas as $kelas)
                            <option value="{{ $kelas->id }}" data-tahun-id="{{ $kelas->tahun_pelajaran_id }}" @selected((string) $nilai('kelas_id') === (string) $kelas->id)>
                                {{ $kelas->nama }}{{ $kelas->tahunPelajaran ? ' - ' . $kelas->tahunPelajaran->nama : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="help-text">Ini adalah konteks kelas siswa, bukan harus lokasi kejadian.</p>
                    @error('kelas_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="cari_siswa_pembinaan">Cari siswa</label>
                    <input id="cari_siswa_pembinaan" type="search" class="input" placeholder="Ketik nama, NIS, atau NISN">
                    <p class="help-text student-picker-help">
                        <span>Pilih tahun dan kelas untuk mempersempit daftar.</span>
                        <span class="student-picker-count"><span id="jumlah_siswa_terlihat">{{ $daftarSiswa->count() }}</span> siswa</span>
                    </p>
                </div>

                <div class="field">
                    <label for="siswa_id">Siswa</label>
                    <select id="siswa_id" name="siswa_id" class="{{ $selectClass('siswa_id') }}" required>
                        <option value="">Pilih siswa</option>
                        @foreach ($daftarSiswa as $siswa)
                            @php
                                $kelasIds = $siswa->anggotaKelas->pluck('kelas_id')->filter()->unique()->implode(',');
                                $tahunIds = $siswa->anggotaKelas->pluck('tahun_pelajaran_id')->filter()->unique()->implode(',');
                                $teksCariSiswa = str($siswa->nama_lengkap . ' ' . $siswa->nis . ' ' . $siswa->nisn)->lower()->toString();
                            @endphp
                            <option
                                value="{{ $siswa->id }}"
                                data-kelas-ids="{{ $kelasIds }}"
                                data-tahun-ids="{{ $tahunIds }}"
                                data-pencarian="{{ $teksCariSiswa }}"
                                @selected((string) $nilai('siswa_id') === (string) $siswa->id)
                            >
                                {{ $siswa->nama_lengkap }} - NISN {{ $siswa->nisn ?: '-' }}{{ $siswa->nis ? ' - NIS ' . $siswa->nis : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p id="pesan_siswa_kosong" class="help-text" style="display: none;">Tidak ada siswa yang cocok dengan pilihan tahun, kelas, atau pencarian.</p>
                    @error('siswa_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="pelapor_pegawai_id">Pelapor / pencatat</label>
                    <select id="pelapor_pegawai_id" name="pelapor_pegawai_id" class="{{ $selectClass('pelapor_pegawai_id') }}">
                        <option value="">Belum ditentukan</option>
                        @foreach ($daftarPegawai as $pegawai)
                            <option value="{{ $pegawai->id }}" @selected((string) $nilai('pelapor_pegawai_id') === (string) $pegawai->id)>
                                {{ $pegawai->nama_lengkap }}{{ $pegawai->nip ? ' - NIP ' . $pegawai->nip : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('pelapor_pegawai_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Kronologi dan tindakan</h2>

            <div class="form-grid">
                <div class="field span-2">
                    <label for="kronologi">Kronologi</label>
                    <textarea id="kronologi" name="kronologi" class="{{ $textareaClass('kronologi') }}" placeholder="Tuliskan kejadian secara singkat, jelas, dan faktual." required>{{ $nilai('kronologi') }}</textarea>
                    @error('kronologi')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="tindakan_awal">Tindakan awal</label>
                    <textarea id="tindakan_awal" name="tindakan_awal" class="{{ $textareaClass('tindakan_awal') }}" placeholder="Contoh: dinasihati wali kelas, dipanggil BK, mediasi singkat.">{{ $nilai('tindakan_awal') }}</textarea>
                    @error('tindakan_awal')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="catatan_rahasia">Catatan rahasia</label>
                    <textarea id="catatan_rahasia" name="catatan_rahasia" class="{{ $textareaClass('catatan_rahasia') }}" placeholder="Catatan internal untuk BK/pimpinan. Boleh dikosongkan.">{{ $nilai('catatan_rahasia') }}</textarea>
                    @error('catatan_rahasia')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('laporan-pembinaan-siswa.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tahunSelect = document.getElementById('tahun_pelajaran_id');
        const kelasSelect = document.getElementById('kelas_id');
        const cariSiswaInput = document.getElementById('cari_siswa_pembinaan');
        const siswaSelect = document.getElementById('siswa_id');
        const jumlahSiswaTerlihat = document.getElementById('jumlah_siswa_terlihat');
        const pesanSiswaKosong = document.getElementById('pesan_siswa_kosong');

        if (!tahunSelect || !kelasSelect || !cariSiswaInput || !siswaSelect) {
            return;
        }

        const kelasOptions = Array.from(kelasSelect.options).filter((option) => option.value);
        const siswaOptions = Array.from(siswaSelect.options).filter((option) => option.value);
        const normalize = (value) => (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
        const hasId = (csv, id) => !id || (csv || '').split(',').filter(Boolean).includes(id);

        const perbaruiKelas = () => {
            const tahunId = tahunSelect.value;

            kelasOptions.forEach((option) => {
                const tampil = !tahunId || option.dataset.tahunId === tahunId;
                option.hidden = !tampil;
                option.disabled = !tampil;
            });

            if (kelasSelect.value && kelasSelect.selectedOptions[0]?.disabled) {
                kelasSelect.value = '';
            }
        };

        const perbaruiSiswa = () => {
            const tahunId = tahunSelect.value;
            const kelasId = kelasSelect.value;
            const kataKunci = normalize(cariSiswaInput.value.trim());
            let jumlah = 0;

            siswaOptions.forEach((option) => {
                const cocokTahun = hasId(option.dataset.tahunIds, tahunId);
                const cocokKelas = hasId(option.dataset.kelasIds, kelasId);
                const cocokCari = !kataKunci || normalize(option.dataset.pencarian || option.textContent).includes(kataKunci);
                const tampil = cocokTahun && cocokKelas && cocokCari;

                option.hidden = !tampil;
                option.disabled = !tampil;

                if (tampil) {
                    jumlah++;
                }
            });

            if (siswaSelect.value && siswaSelect.selectedOptions[0]?.disabled) {
                siswaSelect.value = '';
            }

            if (jumlahSiswaTerlihat) {
                jumlahSiswaTerlihat.textContent = jumlah;
            }

            if (pesanSiswaKosong) {
                pesanSiswaKosong.style.display = jumlah === 0 ? 'block' : 'none';
            }
        };

        tahunSelect.addEventListener('change', () => {
            perbaruiKelas();
            perbaruiSiswa();
        });
        kelasSelect.addEventListener('change', perbaruiSiswa);
        cariSiswaInput.addEventListener('input', perbaruiSiswa);

        perbaruiKelas();
        perbaruiSiswa();
    });
</script>
