<div class="field" style="padding:16px 20px; border-bottom:1px solid var(--line);">
    <label for="folder-pilihan-ujian">Folder bank soal</label>
    <select id="folder-pilihan-ujian" class="select" style="max-width:460px;" data-exam-folder-filter>
        <option value="">Semua soal</option>
        <option value="belum">Belum dikelompokkan</option>
        @foreach ($koleksiSoal->flatMap->folders->unique('id')->sortBy('nama') as $folder)
            <option value="{{ $folder->id }}">{{ $folder->nama }}</option>
        @endforeach
    </select>
    <p class="help-text">Soal yang sudah dicentang tetap dipilih saat Anda membuka folder lain.</p>
</div>
<style>[data-exam-folders][hidden] { display:none !important; }</style>
@push('scripts')
<script>
(() => {
    const filter = document.querySelector('[data-exam-folder-filter]');
    filter?.addEventListener('change', () => {
        document.querySelectorAll('[data-exam-folders]').forEach(row => {
            const ids = JSON.parse(row.dataset.examFolders || '[]').map(String);
            row.hidden = filter.value === 'belum' ? ids.length > 0 : Boolean(filter.value && !ids.includes(filter.value));
        });
    });
})();
</script>
@endpush
