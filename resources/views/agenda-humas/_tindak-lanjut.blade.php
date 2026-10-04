<section class="agenda-section">
    <h2>Tindak lanjut pertemuan</h2>
    @forelse ($agendaHumas->tindakLanjut as $item)
        @php($sedangKoreksi = (int) old('id_tugas') === $item->id)
        <div class="agenda-line">
            <span class="agenda-badge {{ $item->status === 'selesai' ? 'agenda-badge--selesai' : '' }}">{{ \App\Models\TindakLanjutAgendaHumas::STATUS[$item->status] }}</span>
            @if ($item->terlambat())<span class="agenda-warning" style="margin-left:8px;font-size:.85rem">Melewati batas waktu</span>@endif
            <p style="margin:12px 0 8px;font-weight:700" class="agenda-text">{{ $item->uraian }}</p>
            <p class="agenda-muted">Penanggung jawab: {{ $item->penanggung_jawab }} &middot; Batas: {{ $item->batas_tanggal?->format('d-m-Y') ?? 'Belum ditentukan' }}</p>
            @if ($item->catatan)<div class="agenda-text">{{ $item->catatan }}</div>@endif
            @if ($item->selesai_pada)<p class="agenda-muted">Selesai dicatat {{ $item->selesai_pada->format('d-m-Y H:i') }}.</p>@endif
            @if ($bolehUbah)
                <details @if ($sedangKoreksi) open @endif><summary class="button button-muted button-sm" style="display:inline-flex;margin-top:8px">Perbarui tindak lanjut</summary>
                    <form method="POST" action="{{ route('agenda-humas.tindak-lanjut.update', [$agendaHumas, $item]) }}" style="margin-top:16px" data-agenda-submit>
                        @csrf @method('PUT')<input type="hidden" name="id_tugas" value="{{ $item->id }}">
                        <div class="agenda-field-grid">
                            <div class="field span-2"><label for="uraian_{{ $item->id }}">Tindak lanjut</label><textarea id="uraian_{{ $item->id }}" name="uraian" class="textarea" maxlength="5000" required>{{ $sedangKoreksi ? old('uraian', $item->uraian) : $item->uraian }}</textarea></div>
                            <div class="field"><label for="pj_{{ $item->id }}">Penanggung jawab</label><input id="pj_{{ $item->id }}" name="penanggung_jawab" class="input" value="{{ $sedangKoreksi ? old('penanggung_jawab', $item->penanggung_jawab) : $item->penanggung_jawab }}" maxlength="180" required></div>
                            <div class="field"><label for="batas_{{ $item->id }}">Batas tanggal</label><input id="batas_{{ $item->id }}" type="date" name="batas_tanggal" class="input" value="{{ $sedangKoreksi ? old('batas_tanggal', $item->batas_tanggal?->toDateString()) : $item->batas_tanggal?->toDateString() }}"></div>
                            <div class="field"><label for="status_{{ $item->id }}">Status</label><select id="status_{{ $item->id }}" name="status" class="select">@foreach (\App\Models\TindakLanjutAgendaHumas::STATUS as $kode => $label)<option value="{{ $kode }}" @selected(($sedangKoreksi ? old('status', $item->status) : $item->status) === $kode)>{{ $label }}</option>@endforeach</select></div>
                            <div class="field span-2"><label for="catatan_{{ $item->id }}">Catatan perkembangan / hasil</label><textarea id="catatan_{{ $item->id }}" name="catatan" class="textarea" maxlength="5000">{{ $sedangKoreksi ? old('catatan', $item->catatan) : $item->catatan }}</textarea></div>
                        </div>
                        <div class="agenda-actions"><button type="submit" class="button button-primary">Simpan perubahan</button></div>
                    </form>
                </details>
            @endif
        </div>
    @empty<p class="agenda-muted">Belum ada tindak lanjut yang dicatat.</p>@endforelse
</section>
@if ($bolehUbah)
    <section class="agenda-section">
        <h2>Tambah tindak lanjut</h2>
        <form method="POST" action="{{ route('agenda-humas.tindak-lanjut.store', $agendaHumas) }}" data-agenda-submit>
            @csrf
            <div class="agenda-field-grid">
                <div class="field span-2"><label for="uraian_baru">Tindak lanjut</label><textarea id="uraian_baru" name="uraian" class="textarea" maxlength="5000" required>{{ old('id_tugas') ? '' : old('uraian') }}</textarea></div>
                <div class="field"><label for="pj_baru">Penanggung jawab</label><input id="pj_baru" name="penanggung_jawab" class="input" value="{{ old('id_tugas') ? '' : old('penanggung_jawab') }}" maxlength="180" required></div>
                <div class="field"><label for="batas_baru">Batas tanggal</label><input id="batas_baru" name="batas_tanggal" type="date" class="input" value="{{ old('id_tugas') ? '' : old('batas_tanggal') }}"></div>
            </div>
            <div class="agenda-actions"><button type="submit" class="button button-primary">Simpan tindak lanjut</button></div>
        </form>
    </section>
@endif
