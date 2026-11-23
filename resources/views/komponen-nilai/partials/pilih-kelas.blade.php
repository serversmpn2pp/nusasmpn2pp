@php
    $kelompok = $guruMataPelajaran->groupBy(fn ($item) => implode(':', [$item->tahun_pelajaran_id, $item->mata_pelajaran_id, $item->pegawai_id]));
    $daftarTahun = $guruMataPelajaran->pluck('tahunPelajaran')->unique('id');
    $idDipilih = collect((array) old('guru_mata_pelajaran_ids', [$guruMataPelajaranDipilih]))->filter(fn ($id) => is_scalar($id))->map(fn ($id) => (string) $id);
    $kelompokDipilih = $kelompok->search(fn ($kelas) => $kelas->contains(fn ($item) => $idDipilih->contains((string) $item->id)));
    $tahunDipilih = $kelompok->get($kelompokDipilih)?->first()->tahun_pelajaran_id ?? $daftarTahun->first()?->id;
    $tampilkanGuru = $guruMataPelajaran->unique('pegawai_id')->count() > 1;
@endphp

<section class="panel panel-pad feature-section">
    <h2 class="panel-title">Kelas yang diampu</h2>
    @if ($kelompok->isEmpty())
        <p class="help-text">Belum ada penugasan mengajar pada kelas aktif.</p>
    @else
        <div class="form-grid">
            <div class="field">
                <label for="komponen-tahun">Tahun pelajaran</label>
                <select id="komponen-tahun" class="select">
                    @foreach ($daftarTahun as $tahun)
                        <option value="{{ $tahun->id }}" @selected($tahun->id === $tahunDipilih)>{{ $tahun->nama }}{{ $tahun->aktif ? ' (aktif)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="komponen-mapel">Mata pelajaran</label>
                <select id="komponen-mapel" class="select" required>
                    <option value="">Pilih mata pelajaran</option>
                    @foreach ($kelompok as $key => $kelas)
                        @php($item = $kelas->first())
                        <option value="{{ $key }}" data-tahun="{{ $item->tahun_pelajaran_id }}" @selected($key === $kelompokDipilih)>
                            {{ $item->mataPelajaran->nama }}{{ $tampilkanGuru ? ' - '.$item->pegawai->nama_lengkap : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            @foreach ($kelompok as $key => $kelas)
                <div class="field span-2" data-kelompok-kelas="{{ $key }}" @if ($key !== $kelompokDipilih) hidden @endif>
                    <label class="status-toggle" style="margin: 0 0 12px;">
                        <span>Pilih semua kelas yang diampu</span>
                        <input type="checkbox" data-pilih-semua-kelas>
                    </label>
                    <div class="form-grid" role="group" aria-label="Kelas tujuan">
                        @foreach ($kelas->sortBy('kelas.nama', SORT_NATURAL | SORT_FLAG_CASE) as $item)
                            <label class="status-toggle" style="margin: 0;">
                                <span>{{ $item->kelas->nama }}</span>
                                <input type="checkbox" name="guru_mata_pelajaran_ids[]" value="{{ $item->id }}"
                                    @checked($idDipilih->contains((string) $item->id)) @disabled($key !== $kelompokDipilih)>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-form-komponen-kolektif]');
            const tahun = form?.querySelector('#komponen-tahun');
            const mapel = form?.querySelector('#komponen-mapel');
            if (!tahun || !mapel) return;

            const kelompok = [...form.querySelectorAll('[data-kelompok-kelas]')];
            const opsi = [...mapel.querySelectorAll('[data-tahun]')];
            const simpan = form.querySelector('button[type="submit"]');

            function perbaruiKelas() {
                let adaPilihan = false;
                for (const bagian of kelompok) {
                    const aktif = bagian.dataset.kelompokKelas === mapel.value;
                    const semua = bagian.querySelector('[data-pilih-semua-kelas]');
                    const kelas = [...bagian.querySelectorAll('input[name="guru_mata_pelajaran_ids[]"]')];
                    bagian.hidden = !aktif;
                    for (const input of kelas) input.disabled = !aktif;
                    const jumlah = kelas.filter(input => input.checked).length;
                    semua.checked = jumlah === kelas.length;
                    semua.indeterminate = jumlah > 0 && !semua.checked;
                    adaPilihan ||= aktif && jumlah > 0;
                }
                simpan.disabled = !adaPilihan;
            }

            function perbaruiMapel() {
                for (const item of opsi) item.hidden = item.disabled = item.dataset.tahun !== tahun.value;
                const tersedia = opsi.filter(item => !item.disabled);
                if (!tersedia.some(item => item.value === mapel.value)) {
                    mapel.value = tersedia.length === 1 ? tersedia[0].value : '';
                }
                perbaruiKelas();
            }

            form.addEventListener('change', ({target}) => {
                if (target === tahun) return perbaruiMapel();
                if (target.hasAttribute('data-pilih-semua-kelas')) {
                    for (const input of target.closest('[data-kelompok-kelas]').querySelectorAll('input[name="guru_mata_pelajaran_ids[]"]')) {
                        input.checked = target.checked;
                    }
                }
                if (target === mapel || target.closest('[data-kelompok-kelas]')) perbaruiKelas();
            });
            perbaruiMapel();
        })();
    </script>
@endpush
