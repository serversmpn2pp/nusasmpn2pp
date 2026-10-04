<div class="agenda-line"><strong>{{ $dataAset['nama'] }}</strong><p class="agenda-muted">{{ \App\Models\AsetPromosiHumas::KATEGORI[$dataAset['kategori']] }} &middot; Aset versi {{ $dataAset['versi'] + 1 }}</p>
    @if ($dataAset['kredit'])<p>Pembuat / pemilik: {{ $dataAset['kredit'] }}</p>@endif
    @if ($dataAset['ketentuan_penggunaan'])<div class="agenda-text">{{ $dataAset['ketentuan_penggunaan'] }}</div>@endif
    @if ($dataAset['sumber'] === 'tautan')<a href="{{ $dataAset['tautan'] }}" target="_blank" rel="noopener noreferrer">Buka tautan aset</a>
    @elseif ($bolehDokumen && $dataAset['berkas_id'])<a href="{{ route('aset-promosi-humas.berkas', [$dataAset['aset_id'], $dataAset['berkas_id'], 'unduh' => 1]) }}">Unduh berkas versi ini</a>
    @else<p class="agenda-muted">Berkas privat. Akses Pusat Dokumen Humas diperlukan.</p>@endif
</div>
