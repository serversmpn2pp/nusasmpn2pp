<section class="agenda-section">
    <h2>Notulen pertemuan</h2>
    @if ($bolehUbah)
        <form method="POST" action="{{ route('agenda-humas.notulen', $agendaHumas) }}" data-agenda-submit>
            @csrf @method('PUT')
            <div class="field"><label for="pembahasan">Pembahasan</label><textarea id="pembahasan" name="pembahasan" class="textarea" rows="9" maxlength="20000" required>{{ old('pembahasan', $agendaHumas->pembahasan) }}</textarea></div>
            <div class="field" style="margin-top:20px"><label for="keputusan">Keputusan / kesepakatan</label><textarea id="keputusan" name="keputusan" class="textarea" rows="6" maxlength="20000" required>{{ old('keputusan', $agendaHumas->keputusan) }}</textarea></div>
            <div class="agenda-actions"><button type="submit" class="button button-primary">Simpan notulen</button><a class="button button-muted" target="_blank" rel="noopener" href="{{ route('agenda-humas.cetak', [$agendaHumas, 'jenis' => 'notulen']) }}">Cetak notulen</a></div>
        </form>
    @else
        <h3>Pembahasan</h3><div class="agenda-text">{{ $agendaHumas->pembahasan ?: 'Belum dicatat.' }}</div>
        <h3>Keputusan / kesepakatan</h3><div class="agenda-text">{{ $agendaHumas->keputusan ?: 'Belum dicatat.' }}</div>
        <div class="agenda-actions"><a class="button button-muted" target="_blank" rel="noopener" href="{{ route('agenda-humas.cetak', [$agendaHumas, 'jenis' => 'notulen']) }}">Cetak notulen</a></div>
    @endif
</section>
