@php
    $laporanPembinaanSiswa = $laporanPembinaanSiswa ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $laporanPembinaanSiswa?->{$field} ?? $default);
    $tanggalValue = old('tanggal_kejadian', $laporanPembinaanSiswa?->tanggal_kejadian?->format('Y-m-d') ?? now()->toDateString());
    $waktuValue = old('waktu_kejadian', $laporanPembinaanSiswa?->waktuKejadianRingkas());
    $jenisTerpilih = old('jenis_laporan', $laporanPembinaanSiswa?->jenis_laporan ?? 'kejadian');
    $siswaTerpilih = collect(old('siswa_ids', filled(old('siswa_id')) ? [old('siswa_id')] : []))->map(fn ($id) => (int) $id)->all();
@endphp

<style>
    .pembinaan-create-header > div { min-width:0; }
    .pembinaan-page-title { max-width:100%; overflow-wrap:anywhere; }
    .student-picker-help { align-items:center; display:flex; flex-wrap:wrap; gap:8px; justify-content:space-between; }
    .student-picker-count { color:var(--primary-dark); font-weight:800; }
    .collective-student-toolbar { align-items:center; display:flex; flex-wrap:wrap; gap:8px; justify-content:space-between; margin-bottom:10px; }
    .collective-student-list { border:1px solid var(--line); border-radius:8px; display:grid; max-height:360px; overflow-y:auto; }
    .collective-student-option { align-items:center; border-bottom:1px solid var(--line); cursor:pointer; display:grid; gap:10px; grid-template-columns:20px minmax(0,1fr) auto; min-width:0; padding:11px 12px; }
    .collective-student-option:last-child { border-bottom:0; }
    .collective-student-option:hover { background:#f7f9fc; }
    .collective-student-option input { height:18px; margin:0; width:18px; }
    .collective-student-option strong,.collective-student-option span { min-width:0; overflow-wrap:anywhere; }
    .collective-student-option small { color:var(--muted); }
    .collective-selected-count { color:var(--primary-dark); font-size:13px; font-weight:800; }
    .section-stack { min-width:0; }
    .initial-witness-list { display:grid; gap:12px; margin-top:14px; min-width:0; }
    .initial-witness-row { background:#f7f9fc; border:1px solid var(--line); border-radius:8px; display:grid; gap:12px; grid-template-columns:120px minmax(0,.8fr) minmax(0,1.4fr) auto; min-width:0; padding:12px; }
    .initial-witness-remove { align-self:end; }
    @media(max-width:900px){.initial-witness-row{grid-template-columns:1fr}.initial-witness-remove{justify-self:start}}
</style>

@if ($errors->any())
    <div class="alert alert-danger"><strong>Ada data yang perlu diperbaiki.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="form-shell">
    <aside class="panel panel-pad">
        <input type="hidden" name="jenis_laporan" value="{{ $jenisTerpilih }}">
        <h2 class="panel-title">{{ $jenisTerpilih === 'pembinaan' ? 'Catatan Pembinaan' : 'Laporan Kejadian' }}</h2>
        <p class="help-text">{{ $jenisTerpilih === 'pembinaan' ? 'Catat kegiatan pembinaan yang dilakukan langsung oleh BK.' : 'Pilih satu atau beberapa siswa dalam kejadian yang sama. Setiap siswa tetap memperoleh laporan tersendiri.' }}</p>
        <div class="panel" style="background:#f7f9fc;margin-top:16px;padding:14px;">
            <p class="person-meta">Alur laporan</p>
            <p style="font-size:14px;margin:6px 0 0;">Pegawai melapor &rarr; BK memeriksa setiap siswa &rarr; poin disahkan Wakil Kesiswaan</p>
        </div>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Data kejadian</h2>
            <div class="form-grid">
                <div class="field"><label for="tanggal_kejadian">Tanggal kejadian</label><input id="tanggal_kejadian" name="tanggal_kejadian" type="date" value="{{ $tanggalValue }}" class="input" required></div>
                <div class="field"><label for="waktu_kejadian">Waktu kejadian</label><input id="waktu_kejadian" name="waktu_kejadian" type="time" value="{{ $waktuValue }}" class="input"></div>
                <div class="field span-2"><label for="tempat_kejadian">Tempat kejadian</label><input id="tempat_kejadian" name="tempat_kejadian" value="{{ $nilai('tempat_kejadian') }}" class="input" placeholder="Contoh: halaman sekolah, kantin, koridor, atau luar sekolah"></div>
                <div class="field"><label for="tahun_pelajaran_id">Tahun pelajaran</label><select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select"><option value="">Otomatis dari tahun aktif</option>@foreach($daftarTahunPelajaran as $tahun)<option value="{{ $tahun->id }}" @selected((string)$nilai('tahun_pelajaran_id')===(string)$tahun->id)>{{ $tahun->nama }}{{ $tahun->aktif?' (aktif)':'' }}</option>@endforeach</select></div>
                <div class="field"><label for="kelas_id">{{ $laporanPembinaanSiswa?->exists ? 'Kelas siswa' : 'Filter kelas' }}</label><select id="kelas_id" name="kelas_id" class="select"><option value="">{{ $laporanPembinaanSiswa?->exists ? 'Otomatis dari penempatan' : 'Semua kelas' }}</option>@foreach($daftarKelas as $kelas)<option value="{{ $kelas->id }}" data-tahun-id="{{ $kelas->tahun_pelajaran_id }}" @selected((string)$nilai('kelas_id')===(string)$kelas->id)>{{ $kelas->nama }}{{ $kelas->tahunPelajaran?' - '.$kelas->tahunPelajaran->nama:'' }}</option>@endforeach</select></div>
                <div class="field"><label for="cari_siswa_pembinaan">Cari siswa</label><input id="cari_siswa_pembinaan" type="search" class="input" placeholder="Nama, NIS, atau NISN"><p class="help-text student-picker-help"><span>Gunakan kelas untuk mempersempit.</span><span class="student-picker-count"><span id="jumlah_siswa_terlihat">{{ $daftarSiswa->count() }}</span> siswa</span></p></div>
                @if($laporanPembinaanSiswa?->exists)
                    <div class="field"><label for="siswa_id">Siswa</label><select id="siswa_id" name="siswa_id" class="select" required><option value="">Pilih siswa</option>@foreach($daftarSiswa as $siswa)@php $kelasIds=$siswa->anggotaKelas->pluck('kelas_id')->filter()->unique()->implode(',');$tahunIds=$siswa->anggotaKelas->pluck('tahun_pelajaran_id')->filter()->unique()->implode(',');@endphp<option value="{{ $siswa->id }}" data-kelas-ids="{{ $kelasIds }}" data-tahun-ids="{{ $tahunIds }}" data-pencarian="{{ str($siswa->nama_lengkap.' '.$siswa->nis.' '.$siswa->nisn)->lower() }}" @selected((string)$nilai('siswa_id')===(string)$siswa->id)>{{ $siswa->nama_lengkap }} - NISN {{ $siswa->nisn?:'-' }}</option>@endforeach</select><p id="pesan_siswa_kosong" class="help-text" hidden>Tidak ada siswa yang cocok.</p></div>
                @else
                    <div class="field span-2">
                        <div class="collective-student-toolbar">
                            <div><label style="margin-bottom:2px">Siswa terlapor</label><span class="collective-selected-count"><span data-selected-student-count>{{ count($siswaTerpilih) }}</span> dipilih</span></div>
                            <div class="actions"><button type="button" class="button button-muted button-sm" data-select-visible-students>Pilih yang tampil</button><button type="button" class="button button-muted button-sm" data-clear-selected-students>Kosongkan</button></div>
                        </div>
                        <div class="collective-student-list" data-collective-student-list>
                            @foreach($daftarSiswa as $siswa)
                                @php $kelasIds=$siswa->anggotaKelas->pluck('kelas_id')->filter()->unique()->implode(',');$tahunIds=$siswa->anggotaKelas->pluck('tahun_pelajaran_id')->filter()->unique()->implode(',');$namaKelas=$siswa->anggotaKelas->first()?->kelas?->nama; @endphp
                                <label class="collective-student-option" data-collective-student data-kelas-ids="{{ $kelasIds }}" data-tahun-ids="{{ $tahunIds }}" data-pencarian="{{ str($siswa->nama_lengkap.' '.$siswa->nis.' '.$siswa->nisn)->lower() }}">
                                    <input type="checkbox" name="siswa_ids[]" value="{{ $siswa->id }}" @checked(in_array($siswa->id,$siswaTerpilih,true))>
                                    <strong>{{ $siswa->nama_lengkap }}</strong>
                                    <small>{{ $namaKelas ?: 'Belum ditempatkan' }} &middot; NISN {{ $siswa->nisn ?: '-' }}</small>
                                </label>
                            @endforeach
                        </div>
                        <p id="pesan_siswa_kosong" class="help-text" hidden>Tidak ada siswa yang cocok dengan filter.</p>
                        <p class="help-text">Maksimal 100 siswa dalam satu pengiriman. BK tetap memeriksa hasil setiap siswa secara terpisah.</p>
                    </div>
                @endif
                @izin('bk.kelola')
                    <div class="field span-2"><label for="pelapor_pegawai_id">Pelapor / pencatat</label><select id="pelapor_pegawai_id" name="pelapor_pegawai_id" class="select"><option value="">Otomatis dari akun</option>@foreach($daftarPegawai as $pegawai)<option value="{{ $pegawai->id }}" @selected((string)$nilai('pelapor_pegawai_id')===(string)$pegawai->id)>{{ $pegawai->nama_lengkap }}{{ $pegawai->nip?' - '.$pegawai->nip:'' }}</option>@endforeach</select></div>
                @else
                    <input type="hidden" name="pelapor_pegawai_id" value="{{ $nilai('pelapor_pegawai_id', auth()->user()?->pegawai_id) }}">
                @endizin
            </div>
        </section>

        @if($jenisTerpilih === 'pembinaan')
            <section class="panel panel-pad">
                <h2 class="panel-title">Klasifikasi Pembinaan</h2>
                <div class="form-grid">
                    <div class="field"><label for="kategori_pembinaan_siswa_id">Kategori</label><select id="kategori_pembinaan_siswa_id" name="kategori_pembinaan_siswa_id" class="select" required><option value="">Pilih kategori</option>@foreach($daftarKategoriPembinaan as $kategori)<option value="{{ $kategori->id }}" @selected((string)$nilai('kategori_pembinaan_siswa_id')===(string)$kategori->id)>{{ $kategori->nama }}</option>@endforeach</select></div>
                    <div class="field"><label for="tingkat">Tingkat perhatian</label><select id="tingkat" name="tingkat" class="select" required>@foreach($daftarTingkat as $kode=>$label)<option value="{{ $kode }}" @selected($nilai('tingkat','ringan')===$kode)>{{ $label }}</option>@endforeach</select></div>
                </div>
            </section>
        @endif

        <section class="panel panel-pad"><h2 class="panel-title">Kronologi dan tindakan</h2><div class="form-grid"><div class="field span-2"><label for="kronologi">Kronologi faktual</label><textarea id="kronologi" name="kronologi" class="textarea" required placeholder="Tuliskan siapa, apa yang terjadi, dan informasi saksi atau bukti yang tersedia.">{{ $nilai('kronologi') }}</textarea></div><div class="field span-2"><label for="tindakan_awal">Tindakan awal</label><textarea id="tindakan_awal" name="tindakan_awal" class="textarea" placeholder="Tindakan yang sudah dilakukan saat kejadian.">{{ $nilai('tindakan_awal') }}</textarea></div>@izin('bk.kelola')<div class="field span-2"><label for="catatan_rahasia">Catatan rahasia BK</label><textarea id="catatan_rahasia" name="catatan_rahasia" class="textarea">{{ $nilai('catatan_rahasia') }}</textarea></div>@endizin</div></section>

        <section class="panel panel-pad">
            <div class="page-header" style="margin-bottom:0">
                <div><h2 class="panel-title">Bukti pendukung</h2><p class="help-text">Opsional. Unggah foto atau PDF, maksimal 5 file dan 10 MB per file.</p></div>
            </div>
            <div class="form-grid" style="margin-top:14px">
                <div class="field"><label for="bukti_laporan">Pilih file</label><input id="bukti_laporan" name="bukti_laporan[]" type="file" class="input" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple></div>
                <div class="field"><label for="keterangan_bukti">Keterangan bukti</label><input id="keterangan_bukti" name="keterangan_bukti" class="input" value="{{ old('keterangan_bukti') }}" placeholder="Contoh: foto dari kamera koridor"></div>
            </div>
        </section>

        @if(! $laporanPembinaanSiswa?->exists)
            @php $saksiAwal = collect(old('daftar_saksi', [])); @endphp
            <section class="panel panel-pad">
                <div class="page-header" style="margin-bottom:0">
                    <div><h2 class="panel-title">Saksi awal</h2><p class="help-text">Opsional. Identitas rinci saksi masih dapat dilengkapi setelah laporan disimpan.</p></div>
                    <button type="button" class="button button-muted" data-add-initial-witness>Tambah saksi</button>
                </div>
                <div class="initial-witness-list" data-initial-witness-list>
                    @foreach($saksiAwal as $index => $saksi)
                        <div class="initial-witness-row" data-initial-witness-row>
                            <div class="field"><label>Jenis saksi</label><select class="select" name="daftar_saksi[{{ $index }}][jenis_saksi]"><option value="siswa" @selected(($saksi['jenis_saksi'] ?? '') === 'siswa')>Siswa</option><option value="pegawai" @selected(($saksi['jenis_saksi'] ?? '') === 'pegawai')>Pegawai</option><option value="lainnya" @selected(($saksi['jenis_saksi'] ?? 'lainnya') === 'lainnya')>Lainnya</option></select></div>
                            <div class="field"><label>Nama saksi</label><input class="input" name="daftar_saksi[{{ $index }}][nama_saksi]" value="{{ $saksi['nama_saksi'] ?? '' }}"></div>
                            <div class="field"><label>Pernyataan singkat</label><textarea class="textarea" name="daftar_saksi[{{ $index }}][pernyataan]">{{ $saksi['pernyataan'] ?? '' }}</textarea></div>
                            <button type="button" class="button button-muted button-sm initial-witness-remove" data-remove-initial-witness>Hapus</button>
                        </div>
                    @endforeach
                </div>
                <p class="empty-state" data-initial-witness-empty @if($saksiAwal->isNotEmpty()) hidden @endif>Belum ada saksi awal yang dicatat.</p>
                <template data-initial-witness-template>
                    <div class="initial-witness-row" data-initial-witness-row>
                        <div class="field"><label>Jenis saksi</label><select class="select" data-name="jenis_saksi"><option value="siswa">Siswa</option><option value="pegawai">Pegawai</option><option value="lainnya" selected>Lainnya</option></select></div>
                        <div class="field"><label>Nama saksi</label><input class="input" data-name="nama_saksi"></div>
                        <div class="field"><label>Pernyataan singkat</label><textarea class="textarea" data-name="pernyataan"></textarea></div>
                        <button type="button" class="button button-muted button-sm initial-witness-remove" data-remove-initial-witness>Hapus</button>
                    </div>
                </template>
            </section>
        @endif

        <div class="form-actions"><a href="{{ route('laporan-pembinaan-siswa.index') }}" class="button button-muted">Batal</a><button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan laporan' }}</button></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
    const year=document.getElementById('tahun_pelajaran_id');const classroom=document.getElementById('kelas_id');const studentSearch=document.getElementById('cari_siswa_pembinaan');const student=document.getElementById('siswa_id');const count=document.getElementById('jumlah_siswa_terlihat');const empty=document.getElementById('pesan_siswa_kosong');const classOptions=[...classroom.options].filter(o=>o.value);const studentOptions=student?[...student.options].filter(o=>o.value):[];const collectiveStudents=[...document.querySelectorAll('[data-collective-student]')];const selectedCount=document.querySelector('[data-selected-student-count]');const hasId=(csv,id)=>!id||(csv||'').split(',').filter(Boolean).includes(id);const normalize=v=>(v||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
    const updateSelectedCount=()=>{if(selectedCount)selectedCount.textContent=collectiveStudents.filter(row=>row.querySelector('input').checked).length};
    const updateClass=()=>{classOptions.forEach(o=>{const show=!year.value||o.dataset.tahunId===year.value;o.hidden=!show;o.disabled=!show});if(classroom.selectedOptions[0]?.disabled)classroom.value='';};
    const updateStudent=()=>{let visible=0;const keyword=normalize(studentSearch.value.trim());studentOptions.forEach(o=>{const show=hasId(o.dataset.tahunIds,year.value)&&hasId(o.dataset.kelasIds,classroom.value)&&(!keyword||normalize(o.dataset.pencarian).includes(keyword));o.hidden=!show;o.disabled=!show;if(show)visible++});if(student?.selectedOptions[0]?.disabled)student.value='';collectiveStudents.forEach(row=>{const show=hasId(row.dataset.tahunIds,year.value)&&hasId(row.dataset.kelasIds,classroom.value)&&(!keyword||normalize(row.dataset.pencarian).includes(keyword));row.hidden=!show;if(show)visible++});count.textContent=visible;empty.hidden=visible!==0;updateSelectedCount()};
    year.addEventListener('change',()=>{updateClass();updateStudent()});classroom.addEventListener('change',updateStudent);studentSearch.addEventListener('input',updateStudent);document.querySelector('[data-select-visible-students]')?.addEventListener('click',()=>{collectiveStudents.filter(row=>!row.hidden).forEach(row=>row.querySelector('input').checked=true);updateSelectedCount()});document.querySelector('[data-clear-selected-students]')?.addEventListener('click',()=>{collectiveStudents.forEach(row=>row.querySelector('input').checked=false);updateSelectedCount()});document.querySelector('[data-collective-student-list]')?.addEventListener('change',updateSelectedCount);updateClass();updateStudent();
    const witnessList=document.querySelector('[data-initial-witness-list]');const witnessTemplate=document.querySelector('[data-initial-witness-template]');const witnessEmpty=document.querySelector('[data-initial-witness-empty]');let witnessIndex=witnessList?.querySelectorAll('[data-initial-witness-row]').length||0;const updateWitnessEmpty=()=>{if(witnessEmpty)witnessEmpty.hidden=(witnessList?.children.length||0)>0};document.querySelector('[data-add-initial-witness]')?.addEventListener('click',()=>{if(!witnessList||!witnessTemplate)return;const fragment=witnessTemplate.content.cloneNode(true);fragment.querySelectorAll('[data-name]').forEach(field=>field.name=`daftar_saksi[${witnessIndex}][${field.dataset.name}]`);witnessIndex++;witnessList.appendChild(fragment);updateWitnessEmpty()});witnessList?.addEventListener('click',event=>{const button=event.target.closest('[data-remove-initial-witness]');if(!button)return;button.closest('[data-initial-witness-row]')?.remove();updateWitnessEmpty()});updateWitnessEmpty();
});
</script>
