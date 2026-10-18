@extends('layouts.app')
@section('admin-module', 'supervisi')
@section('title', 'Pascaobservasi Supervisi - NUSA')
@section('content')
<div class="sp-workspace">
    @include('supervisi._pengisian-header', ['judul' => 'Refleksi dan tindak lanjut'])
    @php $p = $pascaobservasi; $hakPasca = $p['hak']; @endphp
    @if(!$p['hasil_final'])<p class="sp-note">Hasil observasi belum final atau sedang dikoreksi. Pengisian pascaobservasi belum tersedia.</p>
    @elseif(!$p['hasil_terbaru'])<p class="sp-note">Hasil observasi sudah diperbarui. Refleksi dan rencana lama tetap tersimpan; sesuaikan refleksi sebelum melanjutkan.</p>@endif
    <div class="sp-summary"><div class="sp-stat"><span>Refleksi guru</span><strong style="font-size:1.1rem">{{ !$p['hasil_terbaru'] && $p['status'] !== 'belum_diisi' ? 'Perlu penyesuaian' : ($p['status'] === 'selesai' ? 'Selesai' : \App\Models\PascaobservasiSupervisi::STATUS[$p['status']]) }}</strong></div><div class="sp-stat teal"><span>Tindak lanjut terverifikasi</span><strong>{{ $p['statistik']['selesai'] }} / {{ $p['statistik']['rencana'] }}</strong></div><div class="sp-stat amber"><span>Melewati tenggat</span><strong>{{ $p['statistik']['melewati_tenggat'] }}</strong></div></div>
    @if($hakPasca['sesuaikan_hasil'])
        <section class="sp-band amber"><div class="sp-head"><h2>Tinjau hasil observasi terbaru</h2><a class="button button-muted" href="{{ route('supervisi.penilaian.show', $sesi['id']) }}">Lihat hasil</a></div><form method="POST" class="sp-body" action="{{ route('supervisi.refleksi.tindakan', [$sesi['id'], 'sesuaikan-hasil']) }}">@csrf @include('supervisi._pasca-kontrol', ['versi' => $p['versi']])<div class="field"><label for="sp-align-note">Catatan penyesuaian</label><textarea class="input" id="sp-align-note" name="catatan" rows="3" minlength="10" maxlength="3000" required>{{ old('catatan') }}</textarea></div><label class="sp-check"><input type="checkbox" name="konfirmasi" value="1" required><span>Saya sudah meninjau hasil terbaru. Refleksi dibuka sebagai draf untuk diperiksa dan dikirim ulang.</span></label><button class="button button-primary">Sesuaikan refleksi</button></form></section>
    @endif
    @if($p['catatan_supervisor'])<section class="sp-band amber"><div class="sp-head"><h2>Catatan supervisor</h2></div><div class="sp-body sp-prose">{{ $p['catatan_supervisor'] }}</div></section>@endif
    @if($p['terlihat'])
        @if($hakPasca['isi_refleksi'])<form method="POST" action="{{ route('supervisi.refleksi.update', $sesi['id']) }}" data-sp-editor>@csrf @method('PUT') @include('supervisi._pasca-kontrol', ['versi' => $p['versi']])@endif
        <section class="sp-band"><details class="sp-reflection-panel" @if($hakPasca['isi_refleksi'] || $hakPasca['tinjau_refleksi'] || !in_array($p['status'], ['diterima', 'selesai'])) open @endif><summary class="sp-head"><h2>Refleksi guru</h2><span class="sp-badge {{ $p['status'] }}">{{ count($p['pertanyaan']) }} pertanyaan</span></summary><div class="sp-body"><ol class="sp-reflection">
            @foreach($p['pertanyaan'] as $i => $question)<li>@if($hakPasca['isi_refleksi'])<label for="sp-reflection-{{ $i }}">{{ $question }}</label><textarea class="input" id="sp-reflection-{{ $i }}" rows="3" maxlength="5000" name="jawaban[{{ $i }}]">{{ old('jawaban.'.$i, $p['jawaban'][$i] ?? '') }}</textarea>@else<h3>{{ $question }}</h3><p class="sp-prose">{{ $p['jawaban'][$i] ?? 'Belum diisi' }}</p>@endif</li>@endforeach
        </ol></div></details></section>
        @if($hakPasca['isi_refleksi'])<div class="sp-savebar"><span data-sp-save-state role="status">{{ $p['versi'] ? 'Draf refleksi tersimpan' : 'Belum ada draf tersimpan' }}</span><button class="button button-primary">Simpan draf refleksi</button></div></form>@endif
        @if($hakPasca['isi_refleksi'] && $p['versi'] > 0)
            <section class="sp-band amber"><div class="sp-head"><h2>Kirim refleksi</h2></div><form class="sp-body" method="POST" action="{{ route('supervisi.refleksi.tindakan', [$sesi['id'], 'kirim']) }}" data-sp-secondary>@csrf @include('supervisi._pasca-kontrol', ['versi' => $p['versi']])<label class="sp-check"><input type="checkbox" name="konfirmasi" value="1" required><span>Seluruh jawaban sudah saya periksa dan siap ditinjau supervisor.</span></label><button class="button button-primary">Kirim refleksi</button></form></section>
        @endif
    @else<div class="sp-empty"><h2>Refleksi belum dikirim</h2><p>Draf guru belum tersedia untuk ditinjau.</p></div>@endif
    @if($hakPasca['tinjau_refleksi'])
        <section class="sp-band teal"><div class="sp-head"><h2>Tinjauan refleksi</h2></div><form class="sp-body" method="POST" action="{{ route('supervisi.refleksi.tindakan', [$sesi['id'], 'terima']) }}">@csrf @include('supervisi._pasca-kontrol', ['versi' => $p['versi']])<div class="field"><label for="sp-review-reflection">Catatan tinjauan</label><textarea class="input" name="catatan" id="sp-review-reflection" rows="3" maxlength="3000" minlength="10">{{ old('catatan') }}</textarea></div><label class="sp-check"><input type="checkbox" name="konfirmasi" value="1" required><span>Saya telah meninjau refleksi guru.</span></label><div class="sp-actions"><button class="button button-primary">Terima refleksi</button><button class="button button-muted" formaction="{{ route('supervisi.refleksi.tindakan', [$sesi['id'], 'kembalikan']) }}" data-sp-return-reflection>Kembalikan untuk perbaikan</button></div></form></section>
    @endif
    <section class="sp-band teal"><div class="sp-head"><h2>Rencana tindak lanjut</h2><span class="sp-badge">{{ count($p['rencana']) }} rencana terlihat</span></div><div class="sp-body">
        @if($p['rencana'])<div class="sp-task-list">@foreach($p['rencana'] as $task)<article class="sp-task {{ $task['hasil_terbaru'] ? '' : 'sp-dimmed' }}"><div class="sp-task-top"><span class="sp-badge {{ $task['status'] }}">{{ \App\Models\TindakLanjutSupervisi::STATUS[$task['status']] }}</span><span class="sp-muted">{{ $task['hasil_terbaru'] ? 'Hasil terbaru' : 'Arsip hasil versi '.$task['penilaian_versi'] }}</span></div><div class="sp-task-body"><div><h3>{{ \Illuminate\Support\Str::limit($task['temuan'], 180) }}</h3><span class="sp-muted {{ $task['melewati_tenggat'] ? 'sp-late' : '' }}">Tenggat {{ $task['tenggat'] ? \Carbon\Carbon::parse($task['tenggat'])->format('d-m-Y') : 'belum diisi' }}{{ $task['melewati_tenggat'] ? ' · Melewati tenggat' : '' }}</span></div><a class="button button-muted" href="{{ route('supervisi.tindak-lanjut.show', [$sesi['id'], $task['id']]) }}">{{ $task['hak']['isi_pelaksanaan'] ? 'Laporkan pelaksanaan' : 'Rincian' }}</a></div></article>@endforeach</div>@else<p class="sp-muted">Belum ada rencana tindak lanjut yang tersedia.</p>@endif
        @if($hakPasca['kelola_rencana'])<details class="sp-new-plan" @if($errors->has('temuan')) open @endif><summary>Tambah rencana tindak lanjut</summary><form method="POST" action="{{ route('supervisi.tindak-lanjut.store', $sesi['id']) }}" class="sp-plan-editor" data-sp-editor>@include('supervisi._rencana-form')</form></details>@endif
    </div></section>
    @php $drafRencana = collect($p['rencana'])->where('hasil_terbaru', true)->where('status', 'draf'); @endphp
    @if($hakPasca['kelola_rencana'] && $drafRencana->isNotEmpty())
        <section class="sp-band"><div class="sp-head"><h2>Terbitkan rencana terpilih</h2></div><form class="sp-body" method="POST" action="{{ route('supervisi.tindak-lanjut.terbitkan', $sesi['id']) }}" id="sp-publish-plans" data-sp-secondary>@csrf @include('supervisi._pasca-kontrol', ['versi' => $p['versi']])
            @foreach($drafRencana->values() as $i => $task)<label class="sp-bulk-plan"><input type="checkbox" data-sp-plan-select><span>{{ \Illuminate\Support\Str::limit($task['temuan'], 160) }}</span><input type="hidden" disabled data-sp-plan-id value="{{ $task['id'] }}"><input type="hidden" disabled data-sp-plan-version value="{{ $task['versi'] }}"></label>@endforeach
            <label class="sp-check"><input type="checkbox" name="konfirmasi" value="1" required><span>Rencana dan tenggat terpilih sudah diperiksa. Guru akan menerima notifikasi.</span></label><button class="button button-primary" id="sp-publish-plans-button" disabled>Terbitkan rencana</button>
        </form></section>
    @endif
    @if($hakPasca['tutup_pascaobservasi'] || $hakPasca['buka_pascaobservasi'])
        <section class="sp-band amber"><div class="sp-head"><h2>{{ $hakPasca['buka_pascaobservasi'] ? 'Buka ulang pascaobservasi' : 'Penyelesaian pascaobservasi' }}</h2></div><form method="POST" class="sp-body" action="{{ route('supervisi.pascaobservasi.tindakan', [$sesi['id'], $hakPasca['buka_pascaobservasi'] ? 'buka-ulang' : 'selesaikan']) }}" data-sp-secondary>@csrf @include('supervisi._pasca-kontrol', ['versi' => $p['versi']])<div class="field"><label for="sp-pasca-closing">{{ $hakPasca['buka_pascaobservasi'] ? 'Alasan membuka ulang' : 'Kesimpulan pendampingan' }}</label><textarea class="input" name="catatan" id="sp-pasca-closing" rows="3" minlength="20" maxlength="3000" required>{{ old('catatan') }}</textarea></div>
            @if(!$hakPasca['buka_pascaobservasi'] && $p['statistik']['rencana'] === 0)<label class="sp-check"><input type="checkbox" name="tanpa_tindak_lanjut" value="1" required><span>Tidak diperlukan tindak lanjut tambahan; alasannya tercantum pada kesimpulan.</span></label>@endif
            <label class="sp-check"><input type="checkbox" name="konfirmasi" value="1" required><span>{{ $hakPasca['buka_pascaobservasi'] ? 'Buka kembali pendampingan dengan riwayat tetap tersimpan.' : 'Refleksi sudah diterima dan seluruh tindak lanjut telah diverifikasi.' }}</span></label><button class="button button-primary">{{ $hakPasca['buka_pascaobservasi'] ? 'Buka ulang pendampingan' : 'Selesaikan pascaobservasi' }}</button>
        </form></section>
    @endif
    @if($p['status'] === 'selesai' && $p['catatan_penutup'])<section class="sp-band teal"><div class="sp-head"><h2>{{ $p['hasil_terbaru'] ? 'Kesimpulan pendampingan' : 'Kesimpulan versi sebelumnya' }}</h2><span class="sp-badge">{{ $p['hasil_terbaru'] ? 'Selesai' : 'Arsip' }}</span></div><div class="sp-body sp-prose">{{ $p['catatan_penutup'] }}</div></section>@endif
</div>
@include('supervisi._pengisian-script')
<script>
(() => {
    document.querySelector('[data-sp-return-reflection]')?.closest('form').querySelectorAll('button').forEach(button => button.addEventListener('click', () => { document.querySelector('#sp-review-reflection').required = button.hasAttribute('data-sp-return-reflection'); }));
    const publish = document.querySelector('#sp-publish-plans');
    publish?.addEventListener('change', () => {
        let index = 0;
        publish.querySelectorAll('.sp-bulk-plan').forEach(row => {
            const checked = row.querySelector('[data-sp-plan-select]').checked;
            const id = row.querySelector('[data-sp-plan-id]'), version = row.querySelector('[data-sp-plan-version]');
            id.disabled = version.disabled = !checked;
            if (checked) { id.name = `rencana[${index}][id]`; version.name = `rencana[${index}][versi]`; index++; }
        });
        document.querySelector('#sp-publish-plans-button').disabled = index === 0;
    });
})();
</script>
@endsection
