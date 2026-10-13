@extends('layouts.app')
@section('title', 'Publikasi Rapor STS')
@section('content')
    <style>
        .rp-controls { display:grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap:20px; padding:20px 0; border-block:1px solid var(--line); }
        .rp-list { overflow:auto; border:1px solid var(--line); border-radius:6px; background:#fff; margin:20px 0; }
        .rp-list table { width:100%; border-collapse:collapse; min-width:680px; }
        .rp-list td,.rp-list th { padding:14px 16px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
        .rp-list th { background:#eef3f8; font-size:.8rem; }
        .rp-list td small { display:block; margin-top:6px; color:var(--muted); }
        .rp-list input[type=checkbox],.rp-check input { width:18px; height:18px; accent-color:var(--primary); flex-shrink:0; }
        .rp-check { display:flex; align-items:flex-start; gap:10px; margin:14px 0; }
        .rp-actions { display:flex; align-items:center; flex-wrap:wrap; gap:12px; margin:16px 0; }
        .rp-state { font-size:.8rem; font-weight:600; color:#23754e; }
        .rp-pending { color:#906000; }
        .rp-list details { font-size:.8rem; margin-top:10px; }
        .rp-list summary { cursor:pointer; }
        @media(max-width:640px) { .rp-controls { grid-template-columns:1fr; } }
    </style>
    <div class="page-header"><div><p class="eyebrow">Penilaian</p><h1 class="page-title">Publikasi Rapor STS</h1><p>{{ $data['kegiatan'] }} · {{ $data['kelas'] }}</p></div>
        <a class="button button-muted" href="{{ route('rapor-sts.index', ['kegiatan_id' => $kegiatan->id, 'kelas_id' => $kelas->id]) }}">Kembali ke rapor</a></div>
    <form method="POST" action="{{ route('rapor-sts.publikasi.store', [$kegiatan, $kelas]) }}" id="rp-form">
        @csrf
        <div class="rp-controls">
            <div class="field"><label for="rp-audience">Penerima rapor</label><select class="select" id="rp-audience" name="penerima">@foreach($data['penerima'] as $kode => $label)<option value="{{ $kode }}" @selected(old('penerima', 'orang_tua') === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div><input type="hidden" name="sertakan_perilaku" value="0"><label class="rp-check"><input type="checkbox" id="rp-behavior" name="sertakan_perilaku" value="1" @checked(old('sertakan_perilaku'))> Sertakan lampiran perilaku yang sudah diperiksa BK</label></div>
        </div>
        <div class="rp-actions"><button class="button button-muted" type="button" id="rp-ready">Pilih semua yang siap</button><button class="button button-muted" type="button" id="rp-active">Pilih yang terpublikasi</button><button class="button button-muted" type="button" id="rp-reset">Kosongkan pilihan</button><span id="rp-count">0 siswa dipilih</span></div>
        <div class="field"><label for="rp-search">Cari siswa</label><input class="input" id="rp-search" type="search" placeholder="Nama siswa"></div>
        <div class="rp-list"><table><thead><tr><th>Pilih</th><th>Siswa</th><th>Kesiapan</th><th>Publikasi</th><th>Penerima terakhir</th></tr></thead><tbody>
            @forelse($data['siswa'] as $i => $s)
                <tr data-rp-row data-name="{{ $s['nama'] }}" data-ready="{{ (int) $s['siap'] }}" data-behavior="{{ (int) $s['perilaku_siap'] }}" data-active="{{ (int) $s['aktif'] }}">
                    <td><input type="checkbox" data-rp-select aria-label="Pilih {{ $s['nama'] }}">
                        <input disabled data-rp-field type="hidden" name="siswa[{{ $i }}][anggota_id]" value="{{ $s['anggota_id'] }}">
                        <input disabled data-rp-field type="hidden" name="siswa[{{ $i }}][versi_publikasi]" value="{{ $s['versi_publikasi'] }}">
                        <input disabled data-rp-field data-rp-hash data-report="{{ $s['sidik_rapor'] }}" data-combined="{{ $s['sidik_gabungan'] }}" type="hidden" name="siswa[{{ $i }}][sidik_sumber]" value="{{ $s['sidik_rapor'] }}"></td>
                    <td><strong>{{ $s['nama'] }}</strong><small>No. absen {{ $s['nomor_absen'] ?? '-' }}</small></td>
                    <td><span class="rp-state {{ $s['siap'] ? '' : 'rp-pending' }}">{{ $s['siap'] ? 'Siap diterbitkan' : 'Periode / kehadiran belum siap' }}</span><small>{{ $s['perilaku_siap'] ? 'Lampiran diperiksa BK' : 'Lampiran belum siap' }}</small></td>
                    <td><strong>{{ $s['aktif'] ? 'Terpublikasi' : ($s['versi_publikasi'] ? 'Publikasi dibatalkan' : 'Draf') }}</strong>
                        @if($s['versi_publikasi'])<small>Versi {{ $s['versi_publikasi'] }} · {{ \Carbon\Carbon::parse($s['dipublikasikan_pada'])->format('d-m-Y H:i') }}</small>@endif
                        @if($s['berubah'])<small class="rp-pending">Ada perubahan belum dipublikasikan</small>@endif
                        @if($s['riwayat'])<details><summary>Riwayat penerbitan</summary>@foreach($s['riwayat'] as $r)<p>Versi {{ $r['versi'] }} · {{ $data['penerima'][$r['penerima']] }} · {{ \Carbon\Carbon::parse($r['dipublikasikan_pada'])->format('d-m-Y H:i') }}@if($r['alasan_pembatalan'])<br>{{ $r['alasan_pembatalan'] }}@endif</p>@endforeach</details>@endif</td>
                    <td>{{ $s['penerima'] ? $data['penerima'][$s['penerima']] : '-' }}<small>{{ $s['sertakan_perilaku'] ? 'Dengan lampiran perilaku' : 'Rapor akademik saja' }}</small></td>
                </tr>
            @empty<tr><td colspan="5">Belum ada siswa aktif di kelas ini.</td></tr>@endforelse
        </tbody></table></div>
        <label class="rp-check"><input type="checkbox" name="konfirmasi" value="1" required> Saya sudah memeriksa rapor dan mengonfirmasi tindakan untuk siswa yang dipilih.</label>
        <div class="rp-actions"><button class="button button-primary" type="submit" id="rp-publish">Publikasikan / perbarui rapor terpilih</button></div>
        <details><summary>Batalkan publikasi</summary><div class="field" style="margin-top:16px"><label for="rp-reason">Alasan pembatalan</label><textarea class="input" id="rp-reason" name="alasan" maxlength="500" minlength="10" rows="2"></textarea></div>
            <p class="help-text">Akses melalui NUSA dihentikan. Salinan yang sudah diunduh tidak dapat ditarik kembali.</p>
            <button class="button button-muted" type="submit" name="_method" value="DELETE" id="rp-cancel">Batalkan publikasi siswa terpilih</button></details>
    </form>
    <script>
        (() => {
            const form = document.querySelector('#rp-form');
            const rows = [...document.querySelectorAll('[data-rp-row]')];
            const behavior = document.querySelector('#rp-behavior');
            function sync() {
                rows.forEach(row => {
                    const selected = row.querySelector('[data-rp-select]').checked;
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
@endsection
