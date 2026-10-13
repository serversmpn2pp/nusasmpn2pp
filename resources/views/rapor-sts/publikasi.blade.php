@extends('layouts.app')
@section('title', 'Publikasi Rapor STS')
@section('content')
    <style>
        .rp-controls { display:grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap:24px; }
        .rp-list { overflow:auto; border:1px solid var(--line); border-radius:6px; background:#fff; margin:20px 0; }
        .rp-list table { width:100%; border-collapse:collapse; min-width:680px; }
        .rp-list td,.rp-list th { padding:14px 16px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
        .rp-list th { background:#eef3f8; font-size:.8rem; }
        .rp-list tbody tr:nth-child(even) { background:#f6f9fc; }
        .rp-list tbody tr.is-selected { background:#e8f3ef; }
        .rp-list td small { display:block; margin-top:6px; color:var(--muted); }
        .rp-list input[type=checkbox],.rp-check input { width:18px; height:18px; accent-color:var(--primary); flex-shrink:0; }
        .rp-check { display:flex; align-items:flex-start; gap:10px; margin:14px 0; }
        .rp-actions { display:flex; align-items:center; flex-wrap:wrap; gap:12px; margin:16px 0; }
        .rp-state { display:inline-block; padding:5px 8px; border-radius:4px; background:#e1f2e9; font-size:.78rem; font-weight:600; color:#23754e; }
        .rp-state.rp-pending { background:#fff0c9; }
        .rp-pending { color:#906000; }
        .rp-list details { font-size:.8rem; margin-top:10px; }
        .rp-list summary { cursor:pointer; }
        .rp-audience-note { display:block; margin-top:8px; font-size:.82rem; color:#536a81; }
        .rp-controls .rp-check { margin:0; padding-top:3px; }
        .rp-check-title { display:block; font-size:.9rem; line-height:1.5; }
        .rp-list-tools { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; margin:20px 0; }
        .rp-list-tools > .field { flex:1 1 220px; max-width:360px; }
        .rp-list-tools .rp-actions { margin:0; }
        .rp-selection { background:#d5ece3; color:#155e4e; border-radius:4px; padding:7px 10px; font-size:.85rem; font-weight:700; white-space:nowrap; }
        .rp-submit { background:#e5effa; padding:20px 24px; display:flex; align-items:center; justify-content:space-between; gap:24px; }
        .rp-submit .rp-check { margin:0; max-width:650px; font-size:.85rem; line-height:1.6; }
        .rp-submit > .button { flex-shrink:0; }
        .rp-cancel { margin-top:24px; background:#fff5f4; }
        .rp-cancel > summary { padding:18px 24px; cursor:pointer; color:#963d37; font-weight:700; background:#f9e3e0; }
        .rp-cancel .sts-band-footer { border-color:#e5c8c4; }
        .rp-list .rp-draft { color:#626d7b; background:#eaf0f6; }
        .rp-list .rp-changed { color:#805900; background:#fff0c9; padding:6px 8px; border-radius:4px; }
        @media(max-width:950px) { .rp-submit { flex-direction:column; align-items:stretch; } }
        @media(max-width:640px) { .rp-controls { grid-template-columns:1fr; } .rp-list-tools > .field { max-width:none; } .rp-list-tools .rp-actions { width:100%; gap:8px; } .rp-list-tools .button { flex:1 1 140px; } .rp-submit { flex-direction:column; align-items:stretch; padding:18px 16px; } .rp-cancel > summary { padding:18px 16px; } }
    </style>
    @include('rapor-sts._workspace-style')
    @php $siswaPublikasi = collect($data['siswa']); @endphp
    <div class="sts-workspace">
    <div class="page-header"><div><p class="eyebrow">Penilaian</p><h1 class="page-title">Publikasi Rapor STS</h1><p>{{ $data['kegiatan'] }} · {{ $data['kelas'] }}</p></div>
        <a class="button button-muted" href="{{ route('rapor-sts.index', ['kegiatan_id' => $kegiatan->id, 'kelas_id' => $kelas->id]) }}">Kembali ke rapor</a></div>
    <div class="sts-summary" aria-label="Ringkasan publikasi">
        <div class="sts-metric"><span>Siswa</span><strong>{{ $siswaPublikasi->count() }}</strong></div>
        <div class="sts-metric sts-metric--teal"><span>Siap diterbitkan</span><strong>{{ $siswaPublikasi->where('siap', true)->count() }}</strong></div>
        <div class="sts-metric sts-metric--blue"><span>Terpublikasi</span><strong>{{ $siswaPublikasi->where('aktif', true)->count() }}</strong></div>
        <div class="sts-metric sts-metric--amber"><span>Perubahan belum diterbitkan</span><strong>{{ $siswaPublikasi->where('berubah', true)->count() }}</strong></div>
    </div>
    <form method="POST" action="{{ route('rapor-sts.publikasi.store', [$kegiatan, $kelas]) }}" id="rp-form">
        @csrf
        <section class="sts-section sts-band sts-band--blue">
            <div class="sts-band-head"><div class="sts-band-title"><span class="sts-step" aria-hidden="true">01</span><div><h2>Penerima dan isi rapor</h2><p>{{ $data['kelas'] }} · {{ $data['kegiatan'] }}</p></div></div></div>
            <div class="sts-band-body rp-controls">
                <div class="field"><label for="rp-audience">Penerima rapor</label><select class="select" id="rp-audience" name="penerima">@foreach($data['penerima'] as $kode => $label)<option value="{{ $kode }}" @selected(old('penerima', 'orang_tua') === $kode)>{{ $label }}</option>@endforeach</select><span class="rp-audience-note">Orang tua selalu disertakan sebagai penerima.</span></div>
                <div><input type="hidden" name="sertakan_perilaku" value="0"><label class="rp-check"><input type="checkbox" id="rp-behavior" name="sertakan_perilaku" value="1" @checked(old('sertakan_perilaku'))><span><strong class="rp-check-title">Sertakan lampiran perilaku</strong><span class="rp-audience-note">Hanya lampiran yang sudah diperiksa BK.</span></span></label></div>
            </div>
        </section>
        <section class="sts-section sts-band sts-band--teal">
        <div class="sts-band-head"><div class="sts-band-title"><span class="sts-step" aria-hidden="true">02</span><div><h2>Pilih siswa</h2><p>Kesiapan dan riwayat publikasi rapor</p></div></div><span class="rp-selection" id="rp-count" role="status">0 siswa dipilih</span></div>
        <div class="sts-band-body">
        <div class="rp-list-tools"><div class="field"><label for="rp-search">Cari siswa</label><input class="input" id="rp-search" type="search" placeholder="Nama siswa"></div><div class="rp-actions"><button class="button button-muted" type="button" id="rp-ready">Pilih semua yang siap</button><button class="button button-muted" type="button" id="rp-active">Pilih yang terpublikasi</button><button class="button button-muted" type="button" id="rp-reset">Kosongkan pilihan</button></div></div>
        <div class="rp-list"><table><thead><tr><th>Pilih</th><th>Siswa</th><th>Kesiapan</th><th>Publikasi</th><th>Penerima terakhir</th></tr></thead><tbody>
            @forelse($data['siswa'] as $i => $s)
                <tr data-rp-row data-name="{{ $s['nama'] }}" data-ready="{{ (int) $s['siap'] }}" data-behavior="{{ (int) $s['perilaku_siap'] }}" data-active="{{ (int) $s['aktif'] }}">
                    <td><input type="checkbox" data-rp-select aria-label="Pilih {{ $s['nama'] }}">
                        <input disabled data-rp-field type="hidden" name="siswa[{{ $i }}][anggota_id]" value="{{ $s['anggota_id'] }}">
                        <input disabled data-rp-field type="hidden" name="siswa[{{ $i }}][versi_publikasi]" value="{{ $s['versi_publikasi'] }}">
                        <input disabled data-rp-field data-rp-hash data-report="{{ $s['sidik_rapor'] }}" data-combined="{{ $s['sidik_gabungan'] }}" type="hidden" name="siswa[{{ $i }}][sidik_sumber]" value="{{ $s['sidik_rapor'] }}"></td>
                    <td><strong>{{ $s['nama'] }}</strong><small>No. absen {{ $s['nomor_absen'] ?? '-' }}</small></td>
                    <td><span class="rp-state {{ $s['siap'] ? '' : 'rp-pending' }}">{{ $s['siap'] ? 'Siap diterbitkan' : 'Periode / kehadiran belum siap' }}</span><small>{{ $s['perilaku_siap'] ? 'Lampiran diperiksa BK' : 'Lampiran belum siap' }}</small></td>
                    <td><span class="rp-state {{ $s['aktif'] ? '' : 'rp-draft' }}">{{ $s['aktif'] ? 'Terpublikasi' : ($s['versi_publikasi'] ? 'Publikasi dibatalkan' : 'Draf') }}</span>
                        @if($s['versi_publikasi'])<small>Versi {{ $s['versi_publikasi'] }} · {{ \Carbon\Carbon::parse($s['dipublikasikan_pada'])->format('d-m-Y H:i') }}</small>@endif
                        @if($s['berubah'])<small class="rp-changed">Ada perubahan belum dipublikasikan</small>@endif
                        @if($s['riwayat'])<details><summary>Riwayat penerbitan</summary>@foreach($s['riwayat'] as $r)<p>Versi {{ $r['versi'] }} · {{ $data['penerima'][$r['penerima']] }} · {{ \Carbon\Carbon::parse($r['dipublikasikan_pada'])->format('d-m-Y H:i') }}@if($r['alasan_pembatalan'])<br>{{ $r['alasan_pembatalan'] }}@endif</p>@endforeach</details>@endif</td>
                    <td>{{ $s['penerima'] ? $data['penerima'][$s['penerima']] : '-' }}<small>{{ $s['sertakan_perilaku'] ? 'Dengan lampiran perilaku' : 'Rapor akademik saja' }}</small></td>
                </tr>
            @empty<tr><td colspan="5">Belum ada siswa aktif di kelas ini.</td></tr>@endforelse
        </tbody></table></div>
        </div>
        <div class="rp-submit">
        <label class="rp-check"><input type="checkbox" name="konfirmasi" value="1" required> Saya sudah memeriksa rapor dan mengonfirmasi tindakan untuk siswa yang dipilih.</label>
        <button class="button button-primary" type="submit" id="rp-publish">Publikasikan / perbarui rapor terpilih</button>
        </div>
        </section>
        <details class="rp-cancel"><summary>Batalkan publikasi</summary><div class="sts-band-body"><div class="field"><label for="rp-reason">Alasan pembatalan</label><textarea class="input" id="rp-reason" name="alasan" maxlength="500" minlength="10" rows="2"></textarea></div>
            <div class="sts-band-footer"><p class="help-text">Akses melalui NUSA dihentikan. Salinan yang sudah diunduh tidak dapat ditarik kembali.</p>
            <button class="button button-danger" type="submit" name="_method" value="DELETE" id="rp-cancel">Batalkan publikasi siswa terpilih</button></div></div></details>
    </form>
    <script>
        (() => {
            const form = document.querySelector('#rp-form');
            const rows = [...document.querySelectorAll('[data-rp-row]')];
            const behavior = document.querySelector('#rp-behavior');
            function sync() {
                rows.forEach(row => {
                    const selected = row.querySelector('[data-rp-select]').checked;
                    row.classList.toggle('is-selected', selected);
                    row.querySelectorAll('[data-rp-field]').forEach(el => el.disabled = !selected);
                    const hash = row.querySelector('[data-rp-hash]');
                    hash.value = behavior.checked ? hash.dataset.combined : hash.dataset.report;
                });
                document.querySelector('#rp-count').textContent = `${rows.filter(r => r.querySelector('[data-rp-select]').checked).length} siswa dipilih`;
            }
            form.addEventListener('change', sync);
            document.querySelector('#rp-ready').onclick = () => { rows.forEach(r => r.querySelector('[data-rp-select]').checked = r.dataset.ready === '1' && (!behavior.checked || r.dataset.behavior === '1')); sync(); };
            document.querySelector('#rp-active').onclick = () => { rows.forEach(r => r.querySelector('[data-rp-select]').checked = r.dataset.active === '1'); sync(); };
            document.querySelector('#rp-reset').onclick = () => { rows.forEach(r => r.querySelector('[data-rp-select]').checked = false); sync(); };
            document.querySelector('#rp-search').oninput = e => rows.forEach(r => r.hidden = !r.dataset.name.toLocaleLowerCase().includes(e.target.value.toLocaleLowerCase()));
            form.addEventListener('submit', e => {
                if (!rows.some(r => r.querySelector('[data-rp-select]').checked)) { e.preventDefault(); alert('Pilih minimal satu siswa.'); return; }
                const cancel = e.submitter?.id === 'rp-cancel';
                const reason = document.querySelector('#rp-reason');
                if (cancel && reason.value.trim().length < 10) { e.preventDefault(); reason.closest('details').open = true; reason.focus(); alert('Isi alasan pembatalan minimal 10 karakter.'); return; }
                form.querySelectorAll('button').forEach(b => b.style.pointerEvents = 'none');
            });
            sync();
        })();
    </script>
    </div>
@endsection
