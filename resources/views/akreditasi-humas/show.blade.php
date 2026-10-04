@extends('layouts.app')
@section('title', 'Portofolio Akreditasi - NUSA')
@section('ak-actions')<a class="button button-muted" href="{{ route('akreditasi-humas.index') }}">Daftar portofolio</a><a class="button button-muted" href="{{ route('akreditasi-humas.cetak', $portofolio) }}" target="_blank" rel="noopener">Cetak ringkasan</a>@endsection
@section('content')
<main class="ak-page">
    @include('akreditasi-humas._header', ['judulHalaman' => $portofolio->nama])
    <span class="ak-badge ak-badge--{{ $portofolio->status }}">{{ \App\Models\PortofolioAkreditasiHumas::STATUS[$portofolio->status] }}</span>
    <dl class="ak-facts"><div><dt>Instrumen</dt><dd>{{ $portofolio->instrumen }}</dd></div><div><dt>Tahun pelajaran</dt><dd>{{ $portofolio->tahunPelajaran->nama }}</dd></div><div><dt>Penanggung jawab</dt><dd>{{ $portofolio->penanggung_jawab }}</dd></div><div><dt>Versi portofolio</dt><dd>{{ $portofolio->versi }}</dd></div></dl>
    @if($portofolio->catatan)<p class="agenda-text">{{ $portofolio->catatan }}</p>@endif
    <div class="agenda-metrics"><div class="agenda-metric"><span>Butir terpenuhi</span><strong>{{ $ringkasan['terpenuhi'] }} / {{ $ringkasan['berlaku'] }}</strong></div><div class="agenda-metric"><span>Kesiapan pemeriksaan</span><strong>{{ $ringkasan['persen'] }}%</strong></div><div class="agenda-metric"><span>Bukti tertaut</span><strong>{{ $ringkasan['bukti'] }}</strong></div><div class="agenda-metric"><span>Tidak berlaku</span><strong>{{ $ringkasan['tidak_berlaku'] }}</strong></div></div>
    @if($ringkasan['hilang'])<p class="ak-note ak-note--warning" role="alert">{{ $ringkasan['hilang'] }} berkas bukti tidak tersedia atau formatnya tidak didukung. Bundel tidak dapat dibuat bila bukti yang diperlukan tidak tersedia.</p>@endif
    @if($portofolio->status === 'siap')<p class="ak-note">Portofolio siap dan terkunci. Bundel berisi bukti dari butir terpenuhi serta indeks dokumen.</p>@endif
    <section class="ak-section">
        <div class="ak-toolbar"><h2>Butir akreditasi</h2><div class="actions">
        @if($bolehKelola && $portofolio->status === 'draf')<a class="button button-muted" href="{{ route('akreditasi-humas.edit', $portofolio) }}">Edit identitas</a><a class="button button-primary" href="{{ route('akreditasi-humas.butir.create', $portofolio) }}">Tambah butir</a>@endif
        @if($bolehEkspor && $portofolio->status === 'siap')<form method="POST" action="{{ route('akreditasi-humas.export', $portofolio) }}">@csrf<input type="hidden" name="versi" value="{{ $portofolio->versi }}"><button type="submit" class="button button-primary">Unduh bundel ZIP</button></form>@endif
        </div></div>
        @forelse($portofolio->butir as $b)<article class="ak-row"><div><span class="ak-badge ak-badge--{{ $b->status }}">{{ \App\Models\ButirAkreditasiHumas::STATUS[$b->status] }}</span>
            <h2>{{ $b->kode }} &middot; {{ $b->judul }}</h2><p class="agenda-muted">{{ $b->bukti->count() }} / {{ $b->target_bukti }} bukti &middot; {{ $b->diperiksa_pada ? 'Diperiksa '.$b->diperiksa_pada->format('d-m-Y H:i') : 'Belum diperiksa' }}</p>
            @if($b->catatan_pemeriksaan)<p>{{ $b->catatan_pemeriksaan }}</p>@endif</div><a class="button button-muted" href="{{ route('akreditasi-humas.butir', [$portofolio, $b]) }}">Buka butir</a></article>
        @empty<div class="agenda-empty"><strong>Belum ada butir akreditasi.</strong></div>@endforelse
    </section>
    @if($bolehKelola)<details class="ak-disclosure"><summary>{{ $portofolio->status === 'draf' ? 'Kesiapan & arsip' : 'Revisi & arsip' }}</summary>
        <form method="POST" action="{{ route('akreditasi-humas.status', $portofolio) }}" class="ak-grid" data-save data-confirm="Simpan perubahan status portofolio ini?">@csrf<input type="hidden" name="versi" value="{{ $portofolio->versi }}">
            <div class="field"><label for="status_baru">Status berikutnya</label><select class="select" id="status_baru" name="status" required>
                @if($portofolio->status === 'draf')@if($bolehDokumen)<option value="siap">Tandai siap</option>@endif<option value="arsip">Arsipkan</option>
                @elseif($portofolio->status === 'siap')<option value="draf">Buka revisi</option><option value="arsip">Arsipkan</option>
                @else<option value="draf">Buka kembali sebagai draf</option>@endif
            </select></div><div class="field"><label for="alasan_status">Alasan / catatan kesiapan</label><textarea class="input" id="alasan_status" name="alasan" minlength="5" maxlength="2000" required></textarea></div>
            <div class="actions ak-wide"><button type="submit" class="button button-primary">Simpan status</button></div>
        </form>
    </details>@endif
    <details class="ak-disclosure" @if(request()->has('riwayat')) open @endif><summary>Riwayat portofolio</summary><ul class="ak-history">@foreach($riwayat as $r)<li><strong>{{ $r->aksi }}</strong><p class="agenda-muted">{{ $r->created_at->format('d-m-Y H:i') }} &middot; {{ $r->pengguna?->nama ?? 'Akun tidak tersedia' }} &middot; versi {{ $r->versi }}</p>@if($r->catatan)<p>{{ $r->catatan }}</p>@endif</li>@endforeach</ul>{{ $riwayat->links() }}</details>
</main>
@endsection
