<section class="terpadu-section" aria-labelledby="{{ $id }}">
    <header><h2 id="{{ $id }}">{{ $judul }}</h2></header>
    @if ($keterangan)
        <p class="terpadu-note">{{ $keterangan }}</p>
    @endif
    @if ($baris->isNotEmpty())
        <table class="terpadu-tabel" role="table" aria-labelledby="{{ $id }}">
            <thead role="rowgroup"><tr role="row">@foreach ($kolom as $nama)<th scope="col">{{ $nama }}</th>@endforeach</tr></thead>
            <tbody role="rowgroup">
                @foreach ($baris as $row)
                    <tr role="row">
                        @foreach ($row as $index => $sel)
                            @php($barisSel = collect(is_array($sel) ? $sel : [$sel])->filter(fn ($item) => filled($item))->values())
                            <td role="cell" data-label="{{ $kolom[$index] }}">
                                <span>{{ $barisSel->first() ?? '-' }}
                                    @foreach ($barisSel->skip(1) as $teks)
                                        <span class="terpadu-secondary">{{ $teks }}</span>
                                    @endforeach
                                </span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($baris->hasPages())
            <div class="terpadu-paginasi">{{ $baris->withQueryString()->links() }}</div>
        @endif
    @else
        <p class="terpadu-empty">Tidak ada catatan pada periode yang dipilih.</p>
    @endif
</section>
