@php
    $suffix = $tindak?->id ?? 'baru';
    $pulihkan = old('form_tindak') === (string)$suffix;
    $nilaiTindak = function ($key, $default = null) use ($pulihkan) {
        $nilai = $pulihkan ? old($key, $default) : $default;
        return is_scalar($nilai) || $nilai === null ? $nilai : $default;
    };
@endphp
<form method="POST" action="{{ $tindak?->exists ? route('umpan-balik-humas.tindak.update', [$formulir, $tindak]) : route('umpan-balik-humas.tindak.store', $formulir) }}" data-save>
    @csrf
    @if($tindak?->exists)@method('PUT')@else<input type="hidden" name="token_pembuatan" value="{{ $nilaiTindak('token_pembuatan', $tokenTindak) }}">@endif
    <input type="hidden" name="form_tindak" value="{{ $suffix }}">
    <input type="hidden" name="versi" value="{{ $formulir->versi }}">
    <div class="uf-grid">
        <div class="field uf-wide"><label for="uraian-{{ $suffix }}">Tindakan perbaikan</label><textarea class="input" id="uraian-{{ $suffix }}" name="uraian" minlength="10" maxlength="5000" required>{{ $nilaiTindak('uraian', $tindak?->uraian) }}</textarea></div>
        <div class="field"><label for="pertanyaan-{{ $suffix }}">Pertanyaan terkait</label><select class="select" id="pertanyaan-{{ $suffix }}" name="pertanyaan_umpan_balik_humas_id"><option value="">Umum</option>@foreach($formulir->pertanyaan as $p)<option value="{{ $p->id }}" @selected((string)$nilaiTindak('pertanyaan_umpan_balik_humas_id', $tindak?->pertanyaan_umpan_balik_humas_id) === (string)$p->id)>{{ $p->urutan }}. {{ $p->teks }}</option>@endforeach</select></div>
        <div class="field"><label for="pic-{{ $suffix }}">Penanggung jawab</label><input class="input" id="pic-{{ $suffix }}" name="penanggung_jawab" maxlength="180" value="{{ $nilaiTindak('penanggung_jawab', $tindak?->penanggung_jawab ?? $formulir->penanggung_jawab) }}" required></div>
        <div class="field"><label for="batas-{{ $suffix }}">Batas penyelesaian</label><input class="input" type="date" id="batas-{{ $suffix }}" name="batas_tanggal" value="{{ $nilaiTindak('batas_tanggal', $tindak?->batas_tanggal?->format('Y-m-d')) }}" required></div>
        <div class="field"><label for="status-{{ $suffix }}">Status tindak lanjut</label><select class="select" id="status-{{ $suffix }}" name="status" data-follow-status>@foreach(\App\Models\TindakLanjutUmpanBalikHumas::STATUS as $k => $label)<option value="{{ $k }}" @selected($nilaiTindak('status', $tindak?->status ?? 'belum_mulai') === $k)>{{ $label }}</option>@endforeach</select></div>
        <div class="field uf-wide"><label for="hasil-{{ $suffix }}">Hasil pelaksanaan (internal)</label><textarea class="input" id="hasil-{{ $suffix }}" name="hasil" minlength="5" maxlength="5000" data-follow-result>{{ $nilaiTindak('hasil', $tindak?->hasil) }}</textarea></div>
        <label class="uf-check uf-wide"><input type="hidden" name="bagikan_ringkasan" value="0"><input type="checkbox" name="bagikan_ringkasan" value="1" data-share @checked($nilaiTindak('bagikan_ringkasan', $tindak?->bagikan_ringkasan))>Bagikan ringkasan hasil kepada orang tua</label>
        <div class="field uf-wide" data-public><label for="publik-{{ $suffix }}">Ringkasan yang tampil kepada orang tua</label><textarea class="input" id="publik-{{ $suffix }}" name="ringkasan_publik" minlength="5" maxlength="2000">{{ $nilaiTindak('ringkasan_publik', $tindak?->ringkasan_publik) }}</textarea></div>
        @if($tindak?->exists)<div class="field uf-wide"><label for="alasan-{{ $suffix }}">Alasan perubahan</label><textarea class="input" id="alasan-{{ $suffix }}" name="alasan" minlength="5" maxlength="2000" required>{{ $nilaiTindak('alasan') }}</textarea></div>@endif
    </div><div class="actions" style="margin-top:18px"><button type="submit" class="button button-primary">Simpan tindak lanjut</button></div>
</form>
