@extends('layouts.app')
@section('title', 'Praobservasi Supervisi - NUSA')
@section('content')
<div class="sp-workspace">
    @include('supervisi._pengisian-header', ['judul' => 'Praobservasi pembelajaran'])
    @if($praobservasi['terlihat'])
        @if($praobservasi['catatan_supervisor'])
            <section class="sp-band amber"><div class="sp-head"><h2>Catatan perbaikan supervisor</h2></div><div class="sp-body sp-prose">{{ $praobservasi['catatan_supervisor'] }}</div></section>
        @endif
        @if($hak['isi_praobservasi'])
            <form method="POST" action="{{ route('supervisi.praobservasi.update', $sesi['id']) }}" data-sp-editor>
                @csrf @method('PUT')
                <input type="hidden" name="versi" value="{{ $praobservasi['versi'] }}">
                <input type="hidden" name="versi_sesi" value="{{ $sesi['versi'] }}">
                <section class="sp-band"><div class="sp-head"><h2>Rencana pembelajaran</h2><span class="sp-badge {{ $praobservasi['status'] }}">{{ \App\Models\PraobservasiSupervisi::STATUS[$praobservasi['status']] }}</span></div><div class="sp-body sp-grid">
                    @foreach($label_isian as $key => $label)
                        <div class="field {{ in_array($key, ['topik', 'jumlah_peserta_didik']) ? '' : 'sp-wide' }}">
                            <label for="sp-pra-{{ $key }}">{{ $label }}</label>
                            @if($key === 'jumlah_peserta_didik')
                                <input class="input" type="number" min="1" max="1000" id="sp-pra-{{ $key }}" name="isian[{{ $key }}]" value="{{ old('isian.'.$key, $praobservasi['isian'][$key] ?? '') }}">
                            @elseif($key === 'topik')
                                <input class="input" id="sp-pra-{{ $key }}" name="isian[{{ $key }}]" maxlength="500" value="{{ old('isian.'.$key, $praobservasi['isian'][$key] ?? $sesi['topik'] ?? '') }}">
                            @else
                                <textarea class="input" rows="3" maxlength="5000" id="sp-pra-{{ $key }}" name="isian[{{ $key }}]">{{ old('isian.'.$key, $praobservasi['isian'][$key] ?? '') }}</textarea>
                            @endif
                            @error('isian.'.$key)<span class="sp-field-error">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                </div></section>
                <div class="sp-savebar"><span data-sp-save-state role="status">{{ $praobservasi['versi'] ? 'Draf tersimpan' : 'Belum ada draf tersimpan' }}</span><button class="button button-primary">Simpan draf</button></div>
            </form>
        @else
            <section class="sp-band"><div class="sp-head"><h2>Rencana pembelajaran</h2><span class="sp-badge {{ $praobservasi['status'] }}">{{ \App\Models\PraobservasiSupervisi::STATUS[$praobservasi['status']] }}</span></div><dl class="sp-body sp-grid sp-readonly">
                @foreach($label_isian as $key => $label)<div><dt>{{ $label }}</dt><dd>{{ $praobservasi['isian'][$key] ?? 'Belum diisi' }}</dd></div>@endforeach
            </dl></section>
        @endif
        <section class="sp-band teal"><div class="sp-head"><h2>Perangkat ajar</h2><span class="sp-badge">{{ count($praobservasi['lampiran']) }} / 5 berkas</span></div><div class="sp-body">
            @forelse($praobservasi['lampiran'] as $file)
                <div class="sp-file-row"><div><strong>{{ $file['nama_file_asli'] }}</strong><span class="sp-muted">{{ number_format($file['ukuran_file'] / 1024, 0, ',', '.') }} KB</span></div><div class="sp-actions">
                    <a class="button button-muted" href="{{ route('supervisi.lampiran.download', [$sesi['id'], $file['id']]) }}">Unduh</a>
                    @if($hak['isi_praobservasi'])<form method="POST" action="{{ route('supervisi.lampiran.destroy', [$sesi['id'], $file['id']]) }}" data-sp-secondary data-sp-confirm="Hapus lampiran ini?">@csrf @method('DELETE')<input type="hidden" name="versi" value="{{ $praobservasi['versi'] }}"><input type="hidden" name="versi_sesi" value="{{ $sesi['versi'] }}"><button class="button button-muted">Hapus</button></form>@endif
                </div></div>
            @empty<p class="sp-muted">Belum ada perangkat ajar dilampirkan.</p>@endforelse
            @if($hak['isi_praobservasi'] && count($praobservasi['lampiran']) < 5)
                <form class="sp-upload" method="POST" enctype="multipart/form-data" action="{{ route('supervisi.lampiran.store', $sesi['id']) }}" data-sp-secondary>
                    @csrf <input type="hidden" name="versi" value="{{ $praobservasi['versi'] }}"><input type="hidden" name="versi_sesi" value="{{ $sesi['versi'] }}">
                    <div class="field"><label for="sp-file">Lampiran perangkat ajar</label><input class="input" id="sp-file" type="file" name="berkas" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" required><span class="sp-muted">PDF, Word, atau gambar · Maksimal 10 MB per berkas</span></div><button class="button button-muted">Unggah berkas</button>
                </form>
            @endif
        </div></section>
        @if($hak['isi_praobservasi'] && $praobservasi['versi'] > 0)
            <section class="sp-band amber"><div class="sp-head"><h2>Kirim kepada supervisor</h2></div><form class="sp-body" method="POST" action="{{ route('supervisi.praobservasi.tindakan', [$sesi['id'], 'kirim']) }}" data-sp-secondary>
                @csrf <input type="hidden" name="versi" value="{{ $praobservasi['versi'] }}"><input type="hidden" name="versi_sesi" value="{{ $sesi['versi'] }}">
                <label class="sp-check"><input type="checkbox" name="konfirmasi" value="1" required><span>Rencana dan perangkat ajar sudah saya periksa. Praobservasi dikunci setelah dikirim.</span></label><button class="button button-primary">Kirim praobservasi</button>
            </form></section>
        @endif
        @if($hak['kembalikan_praobservasi'])
            <section class="sp-band amber"><div class="sp-head"><h2>Perbaikan praobservasi</h2></div><form class="sp-body" method="POST" action="{{ route('supervisi.praobservasi.tindakan', [$sesi['id'], 'kembalikan']) }}">
                @csrf <input type="hidden" name="versi" value="{{ $praobservasi['versi'] }}"><input type="hidden" name="versi_sesi" value="{{ $sesi['versi'] }}">
                <div class="field"><label for="sp-return-note">Catatan perbaikan untuk guru</label><textarea class="input" id="sp-return-note" name="catatan" rows="3" minlength="10" maxlength="3000" required>{{ old('catatan') }}</textarea></div>
                <label class="sp-check"><input type="checkbox" name="konfirmasi" value="1" required><span>Kembalikan praobservasi agar dapat diperbaiki guru.</span></label><button class="button button-muted">Kembalikan untuk perbaikan</button>
            </form></section>
        @endif
    @else
        <div class="sp-empty"><h2>Praobservasi belum dikirim</h2><p>Draf guru belum tersedia untuk ditinjau.</p></div>
    @endif
</div>
@include('supervisi._pengisian-script')
@endsection
