<form class="surat-decision" method="POST" action="{{ route('persuratan.tindakan', [$surat, $aksi]) }}">
    @csrf<input type="hidden" name="versi" value="{{ $surat->versi }}">
    @if($alasan)<div><label for="catatan-{{ $aksi }}">{{ $aksi === 'kirim' ? 'Tujuan, cara, dan bukti pengiriman' : 'Alasan / catatan' }}</label><textarea class="textarea" id="catatan-{{ $aksi }}" name="catatan" rows="2" maxlength="2000" required>{{ old('catatan') }}</textarea></div>@endif
    @if($aksi === 'sahkan')<label class="surat-choice"><input type="checkbox" name="konfirmasi_berkas" value="1" required><span>Saya telah memeriksa nomor, isi, penerima, dan berkas final surat.</span></label>@endif
    <button type="submit" class="button {{ $aksi === 'batal' ? 'button-muted surat-danger' : 'button-primary' }}">{{ $label }}</button>
</form>
