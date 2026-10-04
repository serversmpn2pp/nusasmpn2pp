<div class="agenda-metrics" id="qr_rekap">
    @foreach (\App\Models\PesertaPertemuanHumas::KEHADIRAN as $kode => $label)
        <div class="agenda-metric"><span>{{ $label }}</span><strong data-rekap="{{ $kode }}">{{ $rekapPresensi[$kode] }}</strong></div>
    @endforeach
</div>
<section class="agenda-section">
    <div class="page-header"><h2 style="margin:0">Undangan orang tua / wali</h2>
        @if ($bolehKelola && $agendaHumas->status === 'terjadwal')<a class="button button-primary" href="{{ route('agenda-humas.undangan', $agendaHumas) }}">Pilih kelas / tingkat</a>@endif
    </div>
    <p><strong>{{ $jumlahUndanganQr }}</strong> akun orang tua diundang</p>
    <a href="{{ route('agenda-humas.show', [$agendaHumas, 'tab' => 'peserta']) }}" class="button button-muted button-sm">Daftar peserta & koreksi kehadiran</a>
</section>
<section class="agenda-section">
    <div class="page-header"><h2 style="margin:0">QR pertemuan</h2><span id="qr_status" class="agenda-badge {{ $agendaHumas->menerimaPresensiQr() ? 'agenda-badge--selesai' : 'agenda-badge--dibatalkan' }}">{{ $agendaHumas->menerimaPresensiQr() ? 'Presensi dibuka' : 'Presensi ditutup' }}</span></div>
    <div class="agenda-qr-grid">
        <div class="agenda-qr-image">@if ($qrSvg){!! $qrSvg !!}@else<p class="agenda-muted">QR tersedia setelah presensi pertama kali dibuka.</p>@endif</div>
        <div>
            <dl class="agenda-facts">
                <div><dt>Agenda</dt><dd>{{ $agendaHumas->judul }}</dd></div>
                <div><dt>Lokasi</dt><dd>{{ $agendaHumas->tempat }}</dd></div>
                <div><dt>Status agenda</dt><dd>{{ \App\Models\AgendaHumas::STATUS[$agendaHumas->status] }}</dd></div>
                <div><dt>Terakhir diubah</dt><dd>{{ $agendaHumas->presensi_diubah_pada?->format('d-m-Y H:i') ?? '-' }} WIB</dd></div>
            </dl>
            @if ($bolehKelola && $agendaHumas->status === 'terjadwal')
                <form method="POST" action="{{ route('agenda-humas.akses-presensi', $agendaHumas) }}" data-agenda-submit class="agenda-actions">
                    @csrf @method('PATCH')<input type="hidden" name="dibuka" value="{{ $agendaHumas->presensi_dibuka ? 0 : 1 }}">
                    <button class="button {{ $agendaHumas->presensi_dibuka ? 'button-muted' : 'button-primary' }}" @disabled(!$agendaHumas->presensi_dibuka && !$jumlahUndanganQr)>{{ $agendaHumas->presensi_dibuka ? 'Tutup presensi QR' : 'Buka presensi QR' }}</button>
                </form>
            @endif
            @if ($qrSvg)
                <div class="agenda-actions"><a class="button button-muted" target="_blank" rel="noopener" href="{{ route('agenda-humas.cetak-qr', $agendaHumas) }}">Cetak QR</a><button type="button" class="button button-muted" id="salin_qr" data-url="{{ route('presensi-pertemuan.masuk', $agendaHumas->token_presensi) }}">Salin tautan</button></div>
                <input readonly class="input agenda-qr-link" aria-label="Tautan presensi pertemuan" value="{{ route('presensi-pertemuan.masuk', $agendaHumas->token_presensi) }}">
                <p id="salin_status" class="agenda-muted" role="status"></p>
            @endif
        </div>
    </div>
</section>
<section class="agenda-section" id="qr_monitor" data-url="{{ route('agenda-humas.pantau-presensi', $agendaHumas) }}" data-dibuka="{{ $agendaHumas->menerimaPresensiQr() ? '1' : '0' }}">
    <div class="page-header"><h2 style="margin:0">Kehadiran terbaru</h2><button type="button" class="button button-muted button-sm" id="segarkan_presensi">Perbarui</button></div>
    <p id="pantau_status" class="agenda-muted" role="status"></p>
    <div class="table-wrap"><table class="agenda-table"><thead><tr><th>Nama peserta</th><th>Waktu hadir</th><th>Pencatatan</th></tr></thead><tbody id="qr_terbaru"></tbody></table></div>
</section>
