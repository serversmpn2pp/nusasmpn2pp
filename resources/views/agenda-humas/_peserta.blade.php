<div class="agenda-metrics">
    @foreach (\App\Models\PesertaPertemuanHumas::KEHADIRAN as $kode => $label)
        <div class="agenda-metric"><span>{{ $label }}</span><strong>{{ $rekapPresensi[$kode] }}</strong></div>
    @endforeach
</div>
<section class="agenda-section">
    <div class="page-header"><h2 style="margin:0">Daftar peserta</h2><a class="button button-muted button-sm" target="_blank" rel="noopener" href="{{ route('agenda-humas.cetak', [$agendaHumas, 'jenis' => 'daftar-hadir']) }}">Cetak daftar hadir</a></div>
    @if ($daftarPeserta->isEmpty())
        <p class="agenda-muted">Belum ada peserta terdaftar.</p>
    @else
        @if ($bolehUbah)
            <form id="form_presensi" method="POST" action="{{ route('agenda-humas.presensi', $agendaHumas) }}" data-agenda-submit>
                @csrf @method('PUT')<input type="hidden" name="jumlah_baris" value="{{ $daftarPeserta->count() }}">
            </form>
        @endif
        <div class="table-wrap">
            <table class="agenda-table agenda-presensi">
                <thead><tr><th>Peserta</th><th>Kehadiran</th><th>Catatan</th>@if ($bolehUbah)<th></th>@endif</tr></thead>
                <tbody>@foreach ($daftarPeserta as $index => $peserta)
                    @php($nilaiLama = (int) old('kehadiran.'.$index.'.id') === $peserta->id && (int) old('kehadiran.'.$index.'.versi_presensi', -1) === $peserta->versi_presensi)
                    <tr>
                        <td><strong>{{ $peserta->nama }}</strong><small>{{ collect([$peserta->instansi, $peserta->peran])->filter()->join(' / ') }}</small>
                            @foreach ($peserta->anak_undangan ?? [] as $anak)<small>{{ $anak['nama'] }} &middot; {{ $anak['kelas'] }}</small>@endforeach
                            @if ($bolehUbah)
                                <input form="form_presensi" type="hidden" name="kehadiran[{{ $index }}][id]" value="{{ $peserta->id }}">
                                <input form="form_presensi" type="hidden" name="kehadiran[{{ $index }}][versi_presensi]" value="{{ $peserta->versi_presensi }}">
                                <details style="margin-top:8px"><summary class="agenda-muted">Ubah identitas</summary>
                                    @foreach (['nama' => 'Nama peserta', 'instansi' => 'Instansi / kelas', 'peran' => 'Peran / jabatan'] as $field => $label)
                                        <div class="field" style="margin-top:8px"><label for="peserta_{{ $peserta->id }}_{{ $field }}">{{ $label }}</label><input form="form_presensi" id="peserta_{{ $peserta->id }}_{{ $field }}" class="input" name="kehadiran[{{ $index }}][{{ $field }}]" value="{{ $nilaiLama ? old('kehadiran.'.$index.'.'.$field, $peserta->$field) : $peserta->$field }}" maxlength="{{ $field === 'peran' ? 120 : 180 }}" @required($field === 'nama')></div>
                                    @endforeach
                                </details>
                            @endif
                        </td>
                        <td>@if ($bolehUbah)<select form="form_presensi" class="select" name="kehadiran[{{ $index }}][status_kehadiran]" aria-label="Kehadiran {{ $peserta->nama }}">@foreach (\App\Models\PesertaPertemuanHumas::KEHADIRAN as $kode => $label)<option value="{{ $kode }}" @selected(($nilaiLama ? old('kehadiran.'.$index.'.status_kehadiran', $peserta->status_kehadiran) : $peserta->status_kehadiran) === $kode)>{{ $label }}</option>@endforeach</select>@else{{ \App\Models\PesertaPertemuanHumas::KEHADIRAN[$peserta->status_kehadiran] }}@endif
                            @if ($peserta->hadir_pada)<small>{{ $peserta->hadir_pada->format('d-m-Y H:i') }}</small>@endif
                            @if ($peserta->sumber_kehadiran)<small>{{ $peserta->sumber_kehadiran === 'qr' ? 'Konfirmasi QR' : 'Dicatat petugas' }}</small>@endif
                        </td>
                        <td>@if ($bolehUbah)<input form="form_presensi" class="input" name="kehadiran[{{ $index }}][catatan]" value="{{ $nilaiLama ? old('kehadiran.'.$index.'.catatan', $peserta->catatan) : $peserta->catatan }}" maxlength="500" aria-label="Catatan {{ $peserta->nama }}">@else{{ $peserta->catatan ?: '-' }}@endif</td>
                        @if ($bolehUbah)<td>@if ($peserta->status_kehadiran === 'belum_dicatat')<button type="submit" form="hapus_peserta_{{ $peserta->id }}" class="button button-muted button-sm agenda-danger">Hapus</button>@endif</td>@endif
                    </tr>
                @endforeach</tbody>
            </table>
        </div>
        @if ($bolehUbah)<div class="agenda-actions"><button type="submit" form="form_presensi" class="button button-primary">Simpan kehadiran halaman ini</button></div>@endif
        <div style="margin-top:18px">{{ $daftarPeserta->links() }}</div>
        @if ($bolehUbah)@foreach ($daftarPeserta->where('status_kehadiran', 'belum_dicatat') as $peserta)
            <form id="hapus_peserta_{{ $peserta->id }}" method="POST" action="{{ route('agenda-humas.peserta.destroy', [$agendaHumas, $peserta]) }}" onsubmit="return confirm('Hapus peserta ini dari daftar undangan?')">@csrf @method('DELETE')</form>
        @endforeach @endif
    @endif
</section>
@if ($bolehUbah)
    <section class="agenda-section">
        <h2>Tambah peserta undangan</h2>
        <form method="POST" action="{{ route('agenda-humas.peserta.store', $agendaHumas) }}" data-agenda-submit>
            @csrf
            <div id="daftar_undangan">
                @foreach (collect(old('peserta', [['nama' => '', 'instansi' => '', 'peran' => '']]))->values() as $index => $baris)
                    <div class="agenda-invite-row">
                        @foreach (['nama' => 'Nama peserta', 'instansi' => 'Instansi / kelas', 'peran' => 'Peran / jabatan'] as $field => $label)
                            <div class="field"><label for="undangan_{{ $index }}_{{ $field }}">{{ $label }}</label><input id="undangan_{{ $index }}_{{ $field }}" name="peserta[{{ $index }}][{{ $field }}]" class="input" value="{{ $baris[$field] ?? '' }}" maxlength="{{ $field === 'peran' ? 120 : 180 }}" @required($field === 'nama')></div>
                        @endforeach
                        <button type="button" class="button button-muted button-sm" data-hapus-baris>Hapus baris</button>
                    </div>
                @endforeach
            </div>
            <div class="agenda-actions"><button type="button" id="tambah_baris_undangan" class="button button-muted">Tambah baris peserta</button><button type="submit" class="button button-primary">Simpan peserta</button></div>
        </form>
        <template id="template_undangan"><div class="agenda-invite-row">
            @foreach (['nama' => 'Nama peserta', 'instansi' => 'Instansi / kelas', 'peran' => 'Peran / jabatan'] as $field => $label)
                <div class="field"><label>{{ $label }}</label><input data-field="{{ $field }}" class="input" maxlength="{{ $field === 'peran' ? 120 : 180 }}" @required($field === 'nama')></div>
            @endforeach
            <button type="button" class="button button-muted button-sm" data-hapus-baris>Hapus baris</button>
        </div></template>
    </section>
@endif
