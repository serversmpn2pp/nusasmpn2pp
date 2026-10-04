<div class="komite-member" data-komite-member>
    <div class="komite-member-head"><h3>Pengurus <span data-member-number>{{ $index + 1 }}</span></h3>@if (empty($row['id']))<button class="button button-muted button-sm" type="button" data-remove-member>Batalkan baris</button>@endif</div>
    <input type="hidden" data-member-field="id" name="pengurus[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="komite-member-fields">
        <div class="field"><label data-member-label="nama" for="pengurus_{{ $index }}_nama">Nama pengurus</label><input class="input" data-member-field="nama" id="pengurus_{{ $index }}_nama" name="pengurus[{{ $index }}][nama]" maxlength="180" value="{{ $row['nama'] ?? '' }}"></div>
        <div class="field"><label data-member-label="jabatan" for="pengurus_{{ $index }}_jabatan">Jabatan</label><select class="select" data-member-field="jabatan" id="pengurus_{{ $index }}_jabatan" name="pengurus[{{ $index }}][jabatan]">@foreach (\App\Models\PengurusKomiteHumas::JABATAN as $key => $label)<option value="{{ $key }}" @selected(($row['jabatan'] ?? 'anggota') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label data-member-label="nomor_telepon" for="pengurus_{{ $index }}_nomor_telepon">Kontak privat (opsional)</label><input class="input" type="tel" data-member-field="nomor_telepon" id="pengurus_{{ $index }}_nomor_telepon" name="pengurus[{{ $index }}][nomor_telepon]" maxlength="30" value="{{ $row['nomor_telepon'] ?? '' }}"></div>
        <div class="field"><label data-member-label="aktif" for="pengurus_{{ $index }}_aktif">Status pengurus</label><select class="select" data-member-field="aktif" id="pengurus_{{ $index }}_aktif" name="pengurus[{{ $index }}][aktif]"><option value="1" @selected($row['aktif'] ?? true)>Aktif</option><option value="0" @selected(!($row['aktif'] ?? true))>Tidak aktif</option></select></div>
    </div>
</div>
