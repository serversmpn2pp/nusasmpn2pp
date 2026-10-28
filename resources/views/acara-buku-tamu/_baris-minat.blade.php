@php
    $kode = is_string($pilihan['kode'] ?? null) ? $pilihan['kode'] : '';
    $nama = is_string($pilihan['nama'] ?? null) ? $pilihan['nama'] : '';
    $ikon = is_string($pilihan['ikon'] ?? null) && isset(\App\Models\AcaraBukuTamu::IKON_MINAT[$pilihan['ikon']]) ? $pilihan['ikon'] : 'sparkles';
    $aktif = in_array($pilihan['aktif'] ?? false, [true, 1, '1'], true);
@endphp
<div class="event-interest-row" data-interest-row data-used="{{ $terpakai ? 'true' : 'false' }}">
    <input type="hidden" data-interest-field="kode" name="pilihan_minat[{{ $indeks }}][kode]" value="{{ $kode }}">
    <div class="field event-interest-name"><label for="minat-nama-{{ $indeks }}" data-interest-label="nama">Nama pilihan</label><input class="input" id="minat-nama-{{ $indeks }}" data-interest-field="nama" name="pilihan_minat[{{ $indeks }}][nama]" value="{{ $nama }}" maxlength="60" required placeholder="Contoh: Robotika">@if($terpakai)<small>Sudah digunakan pengunjung</small>@endif</div>
    <div class="field event-interest-icon"><label for="minat-ikon-{{ $indeks }}" data-interest-label="ikon">Ikon</label><div class="event-icon-select"><span data-interest-preview><i data-lucide="{{ $ikon }}" aria-hidden="true"></i></span><select class="select" id="minat-ikon-{{ $indeks }}" data-interest-field="ikon" name="pilihan_minat[{{ $indeks }}][ikon]">@foreach(\App\Models\AcaraBukuTamu::IKON_MINAT as $nilai => $label)<option value="{{ $nilai }}" @selected($ikon === $nilai)>{{ $label }}</option>@endforeach</select></div></div>
    <label class="event-interest-active"><input type="hidden" data-interest-field="aktif" name="pilihan_minat[{{ $indeks }}][aktif]" value="0"><input type="checkbox" data-interest-field="aktif" name="pilihan_minat[{{ $indeks }}][aktif]" value="1" @checked($aktif)>Aktif</label>
    <div class="event-interest-tools"><button type="button" class="button button-muted" data-interest-action="up" title="Pindahkan ke atas" aria-label="Pindahkan ke atas"><i data-lucide="arrow-up" aria-hidden="true"></i></button><button type="button" class="button button-muted" data-interest-action="down" title="Pindahkan ke bawah" aria-label="Pindahkan ke bawah"><i data-lucide="arrow-down" aria-hidden="true"></i></button><button type="button" class="button button-muted" data-interest-action="remove" title="{{ $terpakai ? 'Sudah digunakan; nonaktifkan pilihan' : 'Hapus pilihan' }}" aria-label="Hapus pilihan" @disabled($terpakai)><i data-lucide="trash-2" aria-hidden="true"></i></button></div>
</div>
