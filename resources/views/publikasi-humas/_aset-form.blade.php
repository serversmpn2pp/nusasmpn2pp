@if ($bolehAset)
<section class="agenda-section"><h2>Aset promosi</h2>
    <input type="hidden" name="aset_dikirim" value="1">
    <div class="field"><label for="cari_aset">Cari aset aktif</label><input class="input" id="cari_aset" type="search" maxlength="120" data-aset-search="{{ route('publikasi-humas.pilihan-aset') }}"></div>
    @php($terpilih = array_map('intval', old('aset_ids', session()->hasOldInput('aset_dikirim') ? [] : ($asetTerpakai->keys()->all() ?: (request('aset_awal') ? [(int) request('aset_awal')] : [])))))
    <div class="aset-picks" data-aset-picks>@foreach ($pilihanAset as $item)
        @php($lama = $asetTerpakai->get($item->id))
        <div class="aset-pick" data-aset-id="{{ $item->id }}"><label><input type="checkbox" name="aset_ids[]" value="{{ $item->id }}" @checked(in_array($item->id, $terpilih))><span><strong>{{ $lama ? $lama->snapshot['nama'] : $item->nama }}</strong><br>{{ \App\Models\AsetPromosiHumas::KATEGORI[$item->kategori] }} &middot; Versi {{ ($lama ? $lama->snapshot['versi'] : $item->versi) + 1 }}{{ $item->status === 'arsip' ? ' - Diarsipkan' : '' }}</span></label>
            @if ($lama && $item->status === 'aktif' && $item->versi !== $lama->snapshot['versi'])<label style="margin-top:10px"><input type="checkbox" name="perbarui_aset[]" value="{{ $item->id }}" @checked(in_array($item->id, old('perbarui_aset', [])))>Gunakan versi terbaru ({{ $item->versi + 1 }})</label>@endif
        </div>
    @endforeach</div><p class="agenda-muted" role="status" data-aset-pick-state>{{ $pilihanAset->isEmpty() ? 'Belum ada aset aktif.' : '' }}</p>
</section>
@endif
