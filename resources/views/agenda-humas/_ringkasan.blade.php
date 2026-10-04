<section class="agenda-section">
    <h2>Informasi pertemuan</h2>
    <dl class="agenda-facts">
        <div><dt>Status</dt><dd><span class="agenda-badge agenda-badge--{{ $agendaHumas->status }}">{{ \App\Models\AgendaHumas::STATUS[$agendaHumas->status] }}</span> <span class="agenda-muted">{{ $agendaHumas->status === 'terjadwal' ? $agendaHumas->labelWaktu() : '' }}</span></dd></div>
        <div><dt>Jenis</dt><dd>{{ \App\Models\AgendaHumas::JENIS[$agendaHumas->jenis] }}</dd></div>
        <div><dt>Mulai</dt><dd>{{ $agendaHumas->waktu_mulai->format('d-m-Y H:i') }} WIB</dd></div>
        <div><dt>Selesai</dt><dd>{{ $agendaHumas->waktu_selesai->format('d-m-Y H:i') }} WIB</dd></div>
        <div><dt>Peserta yang diundang</dt><dd>{{ $agendaHumas->sasaran ?: '-' }}</dd></div>
        <div><dt>Kehadiran</dt><dd>{{ $rekapPresensi['hadir'] }} hadir dari {{ array_sum($rekapPresensi) }} peserta</dd></div>
        <div><dt>Pemimpin</dt><dd>{{ $agendaHumas->pemimpin ?: 'Belum ditentukan' }}</dd></div>
        <div><dt>Notulis</dt><dd>{{ $agendaHumas->notulis ?: 'Belum ditentukan' }}</dd></div>
        @if ($agendaHumas->tautan_pertemuan)<div class="span-2"><dt>Pertemuan daring</dt><dd><a href="{{ $agendaHumas->tautan_pertemuan }}" target="_blank" rel="noopener noreferrer">{{ $agendaHumas->tautan_pertemuan }}</a></dd></div>@endif
    </dl>
    <h3>Pokok agenda</h3><div class="agenda-text">{{ $agendaHumas->topik }}</div>
    <div class="agenda-actions agenda-print-actions">
        <a class="button button-muted" target="_blank" rel="noopener" href="{{ route('agenda-humas.cetak', [$agendaHumas, 'jenis' => 'daftar-hadir']) }}">Cetak daftar hadir</a>
        <a class="button button-muted" target="_blank" rel="noopener" href="{{ route('agenda-humas.cetak', [$agendaHumas, 'jenis' => 'notulen']) }}">Cetak notulen</a>
    </div>
</section>
@if ($bolehKelola)
    <section class="agenda-section">
        <h2>Status pelaksanaan</h2>
        <form method="POST" action="{{ route('agenda-humas.status', $agendaHumas) }}" data-agenda-submit>
            @csrf @method('PATCH')
            <div class="agenda-field-grid">
                <div class="field"><label for="status_agenda">Status agenda</label><select id="status_agenda" name="status" class="select">@foreach (\App\Models\AgendaHumas::STATUS as $kode => $nama)<option value="{{ $kode }}" @selected(old('status', $agendaHumas->status) === $kode)>{{ $nama }}</option>@endforeach</select></div>
                <div class="field" id="alasan_batal_field"><label for="alasan_pembatalan">Alasan pembatalan</label><textarea id="alasan_pembatalan" name="alasan_pembatalan" class="textarea" maxlength="2000">{{ old('alasan_pembatalan', $agendaHumas->alasan_pembatalan) }}</textarea></div>
            </div>
            <div class="agenda-actions"><button type="submit" class="button button-primary">Simpan status</button></div>
        </form>
    </section>
@endif
<p class="agenda-muted">Terakhir diperbarui {{ $agendaHumas->updated_at->format('d-m-Y H:i') }} oleh {{ $agendaHumas->pengubah?->nama ?: '-' }}.</p>
