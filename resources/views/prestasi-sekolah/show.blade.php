@extends('layouts.app')
@section('title', 'Detail Prestasi - NUSA')
@section('prestasi-actions')<a class="button button-muted" href="{{ route('prestasi-sekolah.index') }}">Kembali</a>@izin('prestasi_sekolah.kelola')<a class="button button-primary" href="{{ route('prestasi-sekolah.edit', $prestasi) }}">Edit prestasi</a>@endizin @endsection
@section('content')
<div class="publikasi-page prestasi-page">
    @include('prestasi-sekolah._header', ['judulHalaman' => $prestasi->capaian])
    <span class="prestasi-badge prestasi-badge--{{ $prestasi->status }}">{{ \App\Models\PrestasiSekolah::STATUS[$prestasi->status] }}</span>
    <div class="prestasi-columns" style="margin-top:20px"><section class="agenda-section"><h2>Kegiatan & capaian</h2><dl class="agenda-facts">
        @foreach(['nama_kegiatan' => 'Kegiatan / lomba', 'cabang' => 'Cabang / bidang', 'capaian' => 'Capaian', 'penyelenggara' => 'Penyelenggara', 'tempat' => 'Tempat', 'pembina' => 'Pembina'] as $key => $label)<dt>{{ $label }}</dt><dd>{{ $prestasi->$key ?: '-' }}</dd>@endforeach
        @foreach(['kategori' => ['Kategori', \App\Models\PrestasiSekolah::KATEGORI], 'tingkat' => ['Tingkat', \App\Models\PrestasiSekolah::TINGKAT], 'perolehan' => ['Perolehan', \App\Models\PrestasiSekolah::PEROLEHAN]] as $key => [$label, $options])<dt>{{ $label }}</dt><dd>{{ $options[$prestasi->$key] }}</dd>@endforeach
        <dt>Tanggal prestasi</dt><dd>{{ $prestasi->tanggal_prestasi->translatedFormat('d F Y') }}</dd><dt>Tahun pelajaran</dt><dd>{{ $prestasi->tahunPelajaran?->nama ?: '-' }}</dd><dt>Diverifikasi</dt><dd>{{ $prestasi->diverifikasi_pada?->translatedFormat('d M Y H:i') ?: '-' }}</dd>
    </dl></section><section class="agenda-section"><h2>Penerima prestasi</h2><p>{{ \App\Models\PrestasiSekolah::PENERIMA[$prestasi->penerima] }} &middot; {{ \App\Models\PrestasiSekolah::BENTUK[$prestasi->bentuk] }}</p>
        @if($prestasi->nama_tim)<h3>{{ $prestasi->nama_tim }}</h3>@endif
        @if($prestasi->penerima === 'sekolah')<p><strong>SMP Negeri 2 Padang Panjang</strong></p>@else<ul class="prestasi-peserta-names">@foreach($prestasi->peserta as $p)<li><strong>{{ $p->nama }}</strong>{{ $p->kelas ? ' - '.$p->kelas : '' }}<span class="agenda-muted"> &middot; {{ $p->siswa_id || $p->pegawai_id ? 'Identitas NUSA' : 'Input manual' }}</span></li>@endforeach</ul>@endif
    </section></div>
    <section class="agenda-section"><h2>Bukti & catatan</h2>
        @if($prestasi->tautan)<p><a class="kliping-url" href="{{ $prestasi->tautan }}" target="_blank" rel="noopener noreferrer">Buka pengumuman / sumber publik</a></p>@endif
        @if($prestasi->riwayat_dokumen_humas_id)
            @if($bolehDokumen)<div class="agenda-actions"><a class="button button-muted" target="_blank" rel="noopener" href="{{ route('prestasi-sekolah.berkas', [$prestasi, $prestasi->riwayat_dokumen_humas_id]) }}">Pratinjau bukti</a><a class="button button-muted" href="{{ route('prestasi-sekolah.berkas', [$prestasi, $prestasi->riwayat_dokumen_humas_id, 'unduh' => 1]) }}">Unduh bukti</a></div><p class="agenda-muted">{{ $prestasi->berkas?->nama_file_asli }}</p>
                @if(str_starts_with($prestasi->berkas?->tipe_file ?? '', 'image/'))<img class="kliping-preview" src="{{ route('prestasi-sekolah.berkas', [$prestasi, $prestasi->riwayat_dokumen_humas_id]) }}" alt="Bukti prestasi {{ $prestasi->capaian }}">@endif
            @else<p class="agenda-muted">Bukti tersimpan (akses dokumen privat).</p>@endif
        @elseif(!$prestasi->tautan)<p class="agenda-muted">Bukti belum dilengkapi.</p>@endif
        <p class="agenda-text">{{ $prestasi->catatan ?: '-' }}</p>
    </section>
    <section class="agenda-section publikasi-history"><h2>Riwayat perubahan</h2>@foreach($riwayat as $r)<details><summary>{{ $r->aksi }} &middot; {{ $r->created_at->translatedFormat('d M Y H:i') }} &middot; {{ $r->pengguna?->nama ?: 'Pengguna tidak tersedia' }}</summary><dl class="agenda-facts publikasi-snapshot">
        @foreach(['nama_kegiatan' => 'Kegiatan', 'capaian' => 'Capaian', 'cabang' => 'Cabang', 'tanggal_prestasi' => 'Tanggal', 'tahun_pelajaran' => 'Tahun pelajaran', 'nama_tim' => 'Nama tim', 'penyelenggara' => 'Penyelenggara', 'tempat' => 'Tempat', 'pembina' => 'Pembina', 'catatan' => 'Catatan'] as $key => $label)<dt>{{ $label }}</dt><dd>{{ $r->snapshot[$key] ?? '-' }}</dd>@endforeach
        @foreach(['kategori' => ['Kategori', \App\Models\PrestasiSekolah::KATEGORI], 'tingkat' => ['Tingkat', \App\Models\PrestasiSekolah::TINGKAT], 'perolehan' => ['Perolehan', \App\Models\PrestasiSekolah::PEROLEHAN], 'penerima' => ['Penerima', \App\Models\PrestasiSekolah::PENERIMA], 'bentuk' => ['Bentuk', \App\Models\PrestasiSekolah::BENTUK], 'status' => ['Status', \App\Models\PrestasiSekolah::STATUS]] as $key => [$label, $options])<dt>{{ $label }}</dt><dd>{{ $options[$r->snapshot[$key] ?? ''] ?? '-' }}</dd>@endforeach
        <dt>Penerima</dt><dd>@if(($r->snapshot['penerima'] ?? '') === 'sekolah')SMP Negeri 2 Padang Panjang @else @foreach($r->snapshot['peserta'] ?? [] as $p)<div>{{ $p['nama'] }}{{ ($p['kelas'] ?? null) ? ' - '.$p['kelas'] : '' }}</div>@endforeach @endif</dd><dt>Alasan perubahan</dt><dd class="agenda-text">{{ $r->catatan_perubahan ?: '-' }}</dd>
        @if($r->riwayat_dokumen_humas_id && $bolehDokumen)<dt>Bukti versi ini</dt><dd><a href="{{ route('prestasi-sekolah.berkas', [$prestasi, $r->riwayat_dokumen_humas_id]) }}" target="_blank" rel="noopener">{{ $r->berkas?->nama_file_asli ?: 'Buka bukti' }}</a></dd>@endif
    </dl></details>@endforeach {{ $riwayat->links() }}</section>
</div>
@endsection
