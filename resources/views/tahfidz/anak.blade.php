@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Tahfidz Anak - NUSA')
@include('tahfidz._assets')

@section('content')
    <div class="tf-page">
        <div class="page-header"><div><p class="eyebrow">Informasi Anak</p><h1 class="page-title">Tahfidz Anak</h1>@if ($siswa)<p class="page-subtitle">{{ $siswa->nama_lengkap }}</p>@endif</div></div>
        @if (! $siswa)
            <p class="empty-state">Belum ada siswa yang terhubung ke akun ini.</p>
        @else
            @if ($anak->count() > 1)
                <form method="GET" action="{{ route('tahfidz-anak.index') }}" class="feature-filter tf-filters tf-anak-filter">
                    <div class="field"><label for="tf-anak">Nama anak</label><select id="tf-anak" name="siswa" class="select">
                        @foreach ($anak as $item)<option value="{{ $item->id }}" @selected($item->id === $siswa->id)>{{ $item->nama_lengkap }}</option>@endforeach
                    </select></div>
                    <button class="button button-muted" type="submit">Tampilkan</button>
                </form>
            @endif
            @if (! $ikutProgram)
                <p class="empty-state">Anak belum terdaftar di kelompok Tahfidz.</p>
            @else
            <dl class="tf-facts tf-rekap-metrics tf-rekap-siswa-metrics">
                <div><dt>Surah sudah disetor</dt><dd>{{ $sudah }}/{{ $target }}</dd></div>
                <div><dt>Surah belum disetor</dt><dd>{{ $target - $sudah }}</dd></div>
                <div><dt>Total setoran</dt><dd>{{ $riwayat->total() }}</dd></div>
            </dl>

            <section class="tf-surface tf-document-status" id="tf-dokumen-anak" aria-labelledby="tf-dokumen-judul">
                <header class="tf-band"><h2 id="tf-dokumen-judul">Rapor dan sertifikat</h2></header>
                <ul class="tf-list">
                    @foreach ($rapor as $item)
                        <li class="tf-student-row"><div><p class="person-name">Rapor Tahfidz</p><p class="person-meta">{{ $item->salinan['tahun'] }} &middot; {{ $item->dipublikasikan_pada->format('d/m/Y') }}</p></div>
                            <a class="button button-muted" href="{{ route('tahfidz-anak.rapor', $item) }}"><i data-lucide="file-text" aria-hidden="true"></i> Buka rapor</a></li>
                    @endforeach
                    @if ($sertifikat)
                        <li class="tf-student-row"><div><p class="person-name">Sertifikat Kelulusan Tahfidz</p><p class="person-meta">{{ $sertifikat->nomor_sertifikat }}</p></div>
                            <a class="button button-muted" href="{{ route('tahfidz-anak.sertifikat', $sertifikat) }}"><i data-lucide="award" aria-hidden="true"></i> Buka sertifikat</a></li>
                    @endif
                    @if ($rapor->isEmpty() && ! $sertifikat)<li class="empty-state">Belum ada dokumen yang dibagikan oleh sekolah.</li>@endif
                </ul>
            </section>

            <nav class="tf-tabs" aria-label="Filter status surah">
                @foreach (['' => 'Semua surah', 'sudah' => 'Sudah disetor', 'belum' => 'Belum disetor'] as $kode => $label)
                    <a href="{{ route('tahfidz-anak.index', ['siswa' => $siswa->id, ...($kode ? ['status' => $kode] : [])]) }}" @if($status === $kode) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            @include('tahfidz._perkembangan-surah')

            <section class="tf-surface tf-section" aria-labelledby="tf-riwayat-anak">
                <header class="tf-band"><h2 id="tf-riwayat-anak">Riwayat setoran</h2><span>{{ $riwayat->total() }} setoran</span></header>
                <ul class="tf-list">
                    @forelse ($riwayat as $item)
                        <li class="tf-setoran-row"><div><p class="person-name">{{ $item->surah->nama }}</p><p class="person-meta">{{ \Illuminate\Support\Carbon::parse($item->tanggal_setoran)->locale('id')->translatedFormat('j F Y') }}</p></div>
                            <div class="tf-setoran-score"><span>Nilai</span><strong>{{ $item->labelNilai() }}</strong></div></li>
                    @empty
                        <li class="empty-state">Belum ada setoran dari pertemuan yang selesai.</li>
                    @endforelse
                </ul>
            </section>
            @if ($riwayat->hasPages())<div class="tf-pagination">{{ $riwayat->links() }}</div>@endif
            @endif
        @endif
    </div>
@endsection
