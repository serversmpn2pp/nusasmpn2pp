<style>
    .bank-folders { margin:0 0 24px; border-block:1px solid var(--line); padding:18px 0; }
    .bank-folder-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
    .bank-folder-head h2 { margin:0; font-size:1rem; }
    .bank-folder-list { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
    .bank-folder-link { display:flex; justify-content:space-between; align-items:center; gap:12px; border:1px solid #c3d3e3; border-radius:7px; padding:12px; background:white; overflow-wrap:anywhere; }
    .bank-folder-link[aria-current=page] { border-color:var(--primary); background:var(--primary-soft); box-shadow:inset 3px 0 var(--primary); }
    .bank-folder-link small { display:block; color:var(--muted); }
    .bank-folder-tools { display:grid; grid-template-columns:minmax(250px,1fr) minmax(280px,1.4fr) auto; align-items:end; gap:16px; margin-top:18px; padding:18px 0 0; border-top:1px solid #c3d3e3; }
    .bank-folder-selection, .bank-folder-destination { display:grid; gap:8px; min-width:0; }
    .bank-folder-tools .bank-folder-caption { display:block; margin:0; font-size:13px; font-weight:700; line-height:20px; }
    .bank-folder-tools .bank-folder-check { display:flex; align-items:center; gap:10px; min-height:44px; margin:0; font-size:14px; font-weight:400; cursor:pointer; }
    .bank-folder-check .bank-soal-select { flex:0 0 18px; margin:0; }
    .bank-folder-tools select { width:100%; min-width:0; height:44px; margin:0; border-color:#b5c9dd; }
    .bank-folder-tools > .button { min-height:44px; margin:0; }
    .bank-folder-tools .button:disabled { background:#e9eef3; border-color:#ccd6e0; color:#627386; box-shadow:none; cursor:not-allowed; opacity:1; }
    .bank-folder-secondary { grid-column:1 / -1; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; padding-top:12px; border-top:1px solid var(--line); }
    .bank-folder-count { color:var(--muted); font-size:13px; font-weight:600; font-variant-numeric:tabular-nums; }
    .bank-folder-tools[data-has-selection=true] .bank-folder-count { color:var(--primary); }
    @media(min-width:761px) and (max-width:1100px) { .bank-folder-tools { grid-template-columns:minmax(0,1fr) minmax(0,1.4fr); } .bank-folder-tools > .button { grid-column:2; justify-self:end; } }
    .bank-folder-dialog { width:min(540px,calc(100% - 24px)); max-height:90vh; overflow:auto; border:1px solid var(--line); border-radius:8px; padding:20px; }
    .bank-folder-dialog::backdrop { background:rgba(15,35,55,.55); }
    .bank-folder-dialog h2 { margin:0; font-size:1.1rem; }
    .bank-folder-dialog .field { margin:14px 0; }
    .bank-soal-select { width:18px; height:18px; accent-color:var(--primary); }
    @media(max-width:760px) { .bank-folder-list { grid-template-columns:1fr; } .bank-folder-tools { grid-template-columns:minmax(0,1fr); } }
</style>
@if ($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif
<section class="bank-folders">
    <div class="bank-folder-head">
        <h2>Folder bank soal</h2>
        @if ($bisaKelolaSoal)
            <button type="button" class="button button-muted" onclick="document.getElementById('folder-baru').showModal()">Buat folder</button>
        @endif
    </div>
    <div class="bank-folder-list">
        @foreach (['semua' => 'Semua soal', 'belum' => 'Belum dikelompokkan'] as $key => $label)
            <a class="bank-folder-link" href="{{ route('soal-cbt.index', array_merge(request()->except(['page', 'folder']), ['mata_pelajaran_id' => $mataPelajaranId, 'tingkat' => $tingkat, 'folder' => $key])) }}" @if($folderFilter === $key) aria-current="page" @endif><strong>{{ $label }}</strong></a>
        @endforeach
        @foreach ($daftarFolder as $folder)
            <a class="bank-folder-link" href="{{ route('soal-cbt.index', array_merge(request()->except(['page']), ['folder' => $folder->id])) }}" @if($folderAktif?->id === $folder->id) aria-current="page" @endif>
                <span><strong>{{ $folder->nama }}</strong><small>{{ $folder->mataPelajaran?->nama }} · Tingkat {{ $folder->tingkat }}</small></span>
                <span class="badge badge-muted">{{ $folder->soal_count }} soal</span>
            </a>
        @endforeach
    </div>
    @if ($folderAktif)
        <div class="bank-folder-head" style="margin-top:18px;">
            <div><h2>{{ $folderAktif->nama }}</h2>@if($folderAktif->keterangan)<p class="help-text">{{ $folderAktif->keterangan }}</p>@endif</div>
            @if ($bisaKelolaSoal)
                <button type="button" class="button button-muted" onclick="document.getElementById('folder-edit').showModal()">Kelola folder</button>
            @endif
        </div>
    @endif
    @if ($bisaKelolaSoal && $soalCbt->isNotEmpty())
        <form method="POST" id="folder-bulk" class="bank-folder-tools">
            @csrf
            <div class="bank-folder-selection">
                <span class="bank-folder-caption">Pilihan soal</span>
                <label class="bank-folder-check"><input type="checkbox" class="bank-soal-select" data-folder-select-all> Pilih semua di halaman ini</label>
            </div>
            <div class="bank-folder-destination">
            <label class="bank-folder-caption" for="folder-tujuan">Folder tujuan</label>
            <select id="folder-tujuan" class="select" data-folder-target>
                <option value="">Pilih folder tujuan</option>
                @foreach ($daftarFolder as $folder)
                    <option value="{{ route('folder-soal-cbt.anggota', $folder) }}">{{ $folder->nama }} · {{ $folder->mataPelajaran?->nama }} · {{ $folder->tingkat }}</option>
                @endforeach
            </select>
            </div>
            <button type="submit" name="aksi" value="masukkan" class="button button-primary" data-folder-add disabled>Masukkan ke folder</button>
            <div class="bank-folder-secondary">
                <span class="bank-folder-count" data-folder-selected role="status" aria-live="polite">Belum ada soal dipilih</span>
            @if ($folderAktif)
                <button type="submit" name="aksi" value="keluarkan" formaction="{{ route('folder-soal-cbt.anggota', $folderAktif) }}" class="button button-muted" data-folder-remove disabled>Keluarkan dari folder ini</button>
            @endif
            </div>
            <span data-folder-payload hidden></span>
        </form>
    @endif
</section>
@if ($bisaKelolaSoal)
    <dialog id="folder-baru" class="bank-folder-dialog">
        <form method="POST" action="{{ route('folder-soal-cbt.store') }}">
            @csrf
            <h2>Buat folder</h2>
            <div class="field">
                <label for="folder-konteks">Mata pelajaran dan tingkat</label>
                <select id="folder-konteks" class="select" required>
                    <option value="">Pilih bank soal</option>
                    @foreach ($daftarKonteks as $k)
                        <option value="{{ $k['kunci'] }}" @selected($kunciKonteks === $k['kunci'])>{{ $k['label'] }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="mata_pelajaran_id" data-folder-mapel>
                <input type="hidden" name="tingkat" data-folder-level>
            </div>
            <div class="field"><label for="folder-nama">Nama folder</label><input id="folder-nama" name="nama" class="input" required maxlength="120" placeholder="Contoh: Ulangan BAB 1" value="{{ old('nama') }}"></div>
            <div class="field"><label for="folder-keterangan">Keterangan (opsional)</label><textarea id="folder-keterangan" name="keterangan" class="textarea" maxlength="500">{{ old('keterangan') }}</textarea></div>
            <div class="actions"><button type="button" class="button button-muted" onclick="this.closest('dialog').close()">Batal</button><button class="button button-primary">Buat folder</button></div>
        </form>
    </dialog>
    @if ($folderAktif)
        <dialog id="folder-edit" class="bank-folder-dialog">
            <form method="POST" action="{{ route('folder-soal-cbt.update', $folderAktif) }}">
                @csrf @method('PUT')
                <h2>Kelola folder</h2>
                <div class="field"><label for="folder-edit-nama">Nama folder</label><input id="folder-edit-nama" name="nama" class="input" value="{{ $folderAktif->nama }}" required maxlength="120"></div>
                <div class="field"><label for="folder-edit-keterangan">Keterangan (opsional)</label><textarea id="folder-edit-keterangan" name="keterangan" class="textarea" maxlength="500">{{ $folderAktif->keterangan }}</textarea></div>
                <div class="actions"><button type="button" class="button button-muted" onclick="this.closest('dialog').close()">Tutup</button><button class="button button-primary">Simpan perubahan</button></div>
            </form>
            <form method="POST" action="{{ route('folder-soal-cbt.destroy', $folderAktif) }}" style="margin-top:20px;" onsubmit="return confirm('Hapus folder ini? Semua soal tetap tersimpan di Bank Soal.')">
                @csrf @method('DELETE')
                <p class="help-text">Menghapus folder tidak menghapus soal di dalamnya.</p>
                <button class="button button-danger" style="margin-top:8px;">Hapus folder</button>
            </form>
        </dialog>
    @endif
@endif
@push('scripts')
<script>
(() => {
    const context = document.getElementById('folder-konteks');
    const syncContext = () => {
        const [mapel = '', level = ''] = (context?.value || '').split('-');
        if (!context) return;
        context.form.querySelector('[data-folder-mapel]').value = mapel;
        context.form.querySelector('[data-folder-level]').value = level;
    };
    context?.addEventListener('change', syncContext);
    syncContext();
    const checks = [...document.querySelectorAll('[data-folder-soal]')];
    const selected = new Set();
    const bulk = document.getElementById('folder-bulk');
    const target = document.querySelector('[data-folder-target]');
    const all = document.querySelector('[data-folder-select-all]');
    const refresh = () => {
        if (!bulk) return;
        checks.forEach(input => input.checked = selected.has(input.value));
        bulk.querySelector('[data-folder-selected]').textContent = selected.size ? `${selected.size} soal dipilih` : 'Belum ada soal dipilih';
        bulk.dataset.hasSelection = String(selected.size > 0);
        bulk.querySelector('[data-folder-add]').disabled = !selected.size || !target.value;
        const remove = bulk.querySelector('[data-folder-remove]');
        if (remove) remove.disabled = !selected.size;
        const ids = new Set(checks.map(input => input.value));
        all.checked = ids.size > 0 && ids.size === selected.size;
        all.indeterminate = selected.size > 0 && selected.size < ids.size;
    };
    checks.forEach(input => input.addEventListener('change', () => {
        input.checked ? selected.add(input.value) : selected.delete(input.value);
        refresh();
    }));
    all?.addEventListener('change', () => {
        selected.clear();
        if (all.checked) checks.forEach(input => selected.add(input.value));
        refresh();
    });
    target?.addEventListener('change', refresh);
    bulk?.addEventListener('submit', event => {
        if (!selected.size || (event.submitter?.value === 'masukkan' && !target.value)) { event.preventDefault(); return; }
        if (event.submitter?.value === 'masukkan') bulk.action = target.value;
        const payload = bulk.querySelector('[data-folder-payload]');
        payload.replaceChildren();
        selected.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'soal_ids[]'; input.value = id; payload.append(input);
        });
    });
    refresh();
})();
</script>
@endpush
