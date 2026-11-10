@php $anggotaDipilih = array_map('intval', (array) old('anggota_kelas_ids', request('anggota_kelas_ids', []))); @endphp
<div class="field wide" id="exception-students" hidden>
    <label>Pilih siswa yang ditugaskan</label>
    <div class="exception-grid">
        <input class="input" type="search" id="exception-student-search" placeholder="Cari nama, NIS, atau NISN" aria-label="Cari siswa penugasan">
        <select class="select" id="exception-student-class" aria-label="Filter kelas siswa"><option value="">Semua kelas</option>@foreach ($kelas as $k)<option value="{{ $k->id }}" data-year="{{ $k->tahun_pelajaran_id }}">{{ $k->nama }}</option>@endforeach</select>
    </div>
    <div class="exception-actions"><button type="button" class="button button-muted button-sm" id="exception-select-visible">Pilih semua hasil</button><button type="button" class="button button-muted button-sm" id="exception-clear-students">Kosongkan pilihan</button><strong id="exception-selected-count" aria-live="polite">0 siswa dipilih</strong></div>
    <div class="exception-student-list" style="max-height:320px;overflow:auto;border:1px solid var(--line);border-radius:8px;margin-top:12px;">
        @foreach ($anggotaPilihan as $a)
            <label class="exception-student-row" style="display:flex;gap:12px;padding:12px;border-bottom:1px solid var(--line);" data-student-row data-year="{{ $a->tahun_pelajaran_id }}" data-class="{{ $a->kelas_id }}" data-label="{{ $a->siswa?->nama_lengkap }} ({{ $a->kelas?->nama }})" data-search="{{ mb_strtolower($a->siswa?->nama_lengkap.' '.$a->siswa?->nis.' '.$a->siswa?->nisn) }}">
                <input type="checkbox" name="anggota_kelas_ids[]" value="{{ $a->id }}" @checked(in_array($a->id, $anggotaDipilih, true))>
                <span><strong>{{ $a->siswa?->nama_lengkap }}</strong><br><small>{{ $a->kelas?->nama }} · NIS {{ $a->siswa?->nis ?: '-' }} · NISN {{ $a->siswa?->nisn ?: '-' }}</small></span>
            </label>
        @endforeach
    </div>
    <p class="exception-note" id="exception-selected-names" style="max-height:110px;overflow:auto;"></p>
    <p class="exception-note">Pilihan tetap tersimpan saat berpindah filter kelas. Periksa nama dan jumlah peserta pada pratinjau.</p>
</div>
