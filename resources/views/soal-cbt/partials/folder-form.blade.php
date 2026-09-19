<div style="grid-column:1 / -1; border-top:1px solid var(--line); padding-top:14px;">
    <input type="hidden" name="folder_selection" value="1">
    <details open>
        <summary style="cursor:pointer; font-weight:800;">Folder soal (opsional)</summary>
        <p class="help-text">Pilih satu atau beberapa folder. Soal tetap satu meskipun digunakan di beberapa folder.</p>
        <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;">
            @foreach ($folderForm as $folder)
                <label data-question-folder data-context="{{ $folder->mata_pelajaran_id }}-{{ $folder->tingkat }}" style="border:1px solid var(--line); border-radius:7px; padding:10px;">
                    <input type="checkbox" name="folder_ids[]" value="{{ $folder->id }}" @checked(in_array($folder->id, old('folder_ids', old('folder_selection') ? [] : $folderPilihan)))>
                    {{ $folder->nama }}
                </label>
            @endforeach
        </div>
        <p class="help-text" data-no-question-folder hidden>Belum ada folder untuk bank soal ini. Folder dapat dibuat melalui halaman Bank Soal.</p>
        @error('folder_ids')<p class="error-text">{{ $message }}</p>@enderror
    </details>
</div>
@push('scripts')
<script>
(() => {
    const mapel = document.querySelector('[data-context-mapel]');
    const tingkat = document.querySelector('[data-context-level]');
    const sync = () => {
        let visible = 0;
        document.querySelectorAll('[data-question-folder]').forEach(label => {
            const active = label.dataset.context === `${mapel?.value}-${tingkat?.value}`;
            label.hidden = !active;
            label.querySelector('input').disabled = !active;
            if (active) visible++;
        });
        document.querySelector('[data-no-question-folder]').hidden = visible > 0;
    };
    document.querySelector('[data-question-context]')?.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
