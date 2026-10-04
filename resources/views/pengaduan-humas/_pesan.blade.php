<ul class="pengaduan-conversation">
    @forelse ($pesan as $item)
        <li class="pengaduan-message pengaduan-message--{{ $item->asal }}">
            <div class="pengaduan-tags"><strong>{{ $item->asal === 'humas' ? 'Balasan resmi Humas' : ($untukOrangTua ? 'Informasi dari Anda' : 'Informasi pelapor') }}</strong><time class="agenda-muted" datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->format('d-m-Y H:i') }}</time></div>
            <p class="agenda-text">{{ $item->isi }}</p>
        </li>
    @empty
        <li class="agenda-muted">Belum ada balasan resmi atau informasi tambahan.</li>
    @endforelse
</ul>
{{ $pesan->withQueryString()->links() }}
