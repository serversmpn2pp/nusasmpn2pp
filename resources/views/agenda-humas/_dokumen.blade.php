<section class="agenda-section">
    <h2>Dokumen pertemuan</h2>
    @if (! $bolehDokumen)
        <p class="agenda-muted">Akses berkas memerlukan izin Pusat Dokumen Humas.</p>
    @else
        @forelse ($agendaHumas->dokumen as $dokumen)
            <div class="agenda-line">
                <strong>{{ $dokumen->judul }}</strong><p class="agenda-muted">{{ \App\Models\DokumenHumas::KATEGORI[$dokumen->kategori] ?? 'Dokumen' }} &middot; {{ $dokumen->nama_file_asli }}</p>
                <div class="agenda-actions"><a href="{{ route('dokumen-humas.show', $dokumen) }}" class="button button-muted button-sm">Rincian dokumen</a><a href="{{ route('dokumen-humas.unduh', $dokumen) }}" class="button button-primary button-sm">Unduh</a>
                    @if ($bolehUbah)<form method="POST" action="{{ route('agenda-humas.dokumen.destroy', [$agendaHumas, $dokumen]) }}" onsubmit="return confirm('Lepas hubungan dokumen ini dari agenda? Berkas tetap tersimpan di Pusat Dokumen Humas.')">@csrf @method('DELETE')<button type="submit" class="button button-muted button-sm">Lepas dari agenda</button></form>@endif
                </div>
            </div>
        @empty<p class="agenda-muted">Belum ada dokumen yang dihubungkan.</p>@endforelse
    @endif
</section>
@if ($bolehUbah && $bolehDokumen)
    <section class="agenda-section">
        <h2>Hubungkan dokumen</h2>
        <form method="GET" action="{{ route('agenda-humas.show', $agendaHumas) }}" class="agenda-actions" style="align-items:end">
            <input type="hidden" name="tab" value="dokumen">
            <div class="field" style="flex:1;min-width:160px"><label for="cari_dokumen">Cari judul / nomor dokumen</label><input id="cari_dokumen" name="cari_dokumen" class="input" value="{{ request('cari_dokumen') }}" maxlength="120"></div><button type="submit" class="button button-muted">Cari</button>
        </form>
        @if ($pilihanDokumen->isNotEmpty())
            <form method="POST" action="{{ route('agenda-humas.dokumen.store', $agendaHumas) }}" data-agenda-submit style="margin-top:16px">
                @csrf<div class="field"><label for="dokumen_humas_id">Dokumen tersimpan</label><select id="dokumen_humas_id" name="dokumen_humas_id" class="select" required><option value="">Pilih dokumen</option>@foreach ($pilihanDokumen as $pilihan)<option value="{{ $pilihan->id }}">{{ $pilihan->judul }}{{ $pilihan->nomor_dokumen ? ' / '.$pilihan->nomor_dokumen : '' }}</option>@endforeach</select></div>
                <div class="agenda-actions"><button type="submit" class="button button-primary">Hubungkan dokumen</button></div>
            </form>
        @else<p class="agenda-muted">Tidak ada dokumen aktif yang cocok dan belum terhubung.</p>@endif
        @izin('dokumen_humas.kelola')<div class="agenda-actions"><a href="{{ route('dokumen-humas.create', ['agenda_humas_id' => $agendaHumas->id]) }}" class="button button-muted">Unggah dokumen baru</a></div>@endizin
    </section>
@endif
