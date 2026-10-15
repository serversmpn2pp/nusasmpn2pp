@csrf
@include('supervisi._pasca-kontrol', ['versi' => $rencana['versi'] ?? 0])
<input type="hidden" name="refleksi_versi" value="{{ $pascaobservasi['versi'] }}">
@if(!isset($rencana))<input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan', $token) }}">@endif
<div class="sp-grid">
    @foreach($label_rencana as $field => $label)<div class="field"><label for="sp-plan-{{ $field }}">{{ $label }}</label><textarea class="input" rows="3" maxlength="3000" id="sp-plan-{{ $field }}" name="{{ $field }}" @required($field === 'temuan')>{{ old($field, $rencana[$field] ?? '') }}</textarea></div>@endforeach
    <div class="field"><label for="sp-plan-deadline">Tenggat pelaksanaan</label><input class="input" type="date" name="tenggat" id="sp-plan-deadline" value="{{ old('tenggat', $rencana['tenggat'] ?? '') }}"></div>
</div>
<div class="sp-footer"><span class="sp-muted" data-sp-save-state role="status">Draf rencana belum ditampilkan kepada guru.</span><button class="button button-primary">Simpan draf rencana</button></div>
