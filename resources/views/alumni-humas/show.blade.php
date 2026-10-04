@extends('layouts.app')
@section('title', 'Detail Alumni - NUSA')
@section('alumni-actions')
    <a class="button button-muted" href="{{ route('alumni-humas.index') }}">Kembali</a>
    @if($bolehPrivat)<a class="button button-primary" href="{{ route('alumni-humas.edit', $alumni) }}">Edit alumni</a>@endif
@endsection
@section('content')
<div class="publikasi-page alumni-page">
    @include('alumni-humas._header', ['judulHalaman' => $alumni->nama_lengkap])
    <div class="alumni-columns">
        <section class="agenda-section"><h2>Identitas & kelulusan</h2><dl class="agenda-facts">
            @foreach(['nis' => 'NIS', 'nisn' => 'NISN', 'tahun_masuk' => 'Tahun masuk', 'tahun_lulus' => 'Angkatan (tahun lulus)', 'kelas_terakhir' => 'Kelas terakhir'] as $key => $label)<dt>{{ $label }}</dt><dd>{{ $alumni->$key ?: '-' }}</dd>@endforeach
            <dt>Jenis kelamin</dt><dd>{{ ['L' => 'Laki-laki', 'P' => 'Perempuan'][$alumni->jenis_kelamin] ?? '-' }}</dd>
            <dt>Tanggal lulus</dt><dd>{{ $alumni->tanggal_lulus?->translatedFormat('d F Y') ?: '-' }}</dd>
            <dt>Sumber identitas</dt><dd>{{ $alumni->siswa_id ? 'Siswa NUSA' : 'Input manual' }}</dd>
            <dt>Status data</dt><dd>{{ \App\Models\AlumniHumas::STATUS[$alumni->status] }}</dd>
        </dl></section>
        <section class="agenda-section"><h2>Sekolah lanjutan</h2><span class="alumni-tag alumni-tag--{{ $alumni->status_penelusuran }}">{{ \App\Models\AlumniHumas::PENELUSURAN[$alumni->status_penelusuran] }}</span>
            <dl class="agenda-facts">
                @if($alumni->status_penelusuran === 'melanjutkan')
                    <dt>Jenis sekolah</dt><dd>{{ \App\Models\AlumniHumas::SEKOLAH[$alumni->jenis_sekolah] }}</dd>
                    @foreach(['nama_sekolah' => 'Sekolah', 'kota_sekolah' => 'Kota / kabupaten', 'jurusan' => 'Jurusan / program'] as $key => $label)<dt>{{ $label }}</dt><dd>{{ $alumni->$key ?: '-' }}</dd>@endforeach
                @endif
                <dt>Penelusuran terakhir</dt><dd>{{ $alumni->tanggal_penelusuran?->translatedFormat('d F Y') ?: '-' }}</dd>
            </dl>
        </section>
    </div>
    @if($bolehPrivat)
        <section class="agenda-section"><h2>Kontak & catatan privat</h2><dl class="agenda-facts"><dt>Nomor WA</dt><dd>{{ $alumni->nomor_wa ?: '-' }}</dd><dt>Email</dt><dd>{{ $alumni->email ?: '-' }}</dd><dt>Catatan</dt><dd class="agenda-text">{{ $alumni->catatan_penelusuran ?: '-' }}</dd></dl></section>
    @endif
    <section class="agenda-section publikasi-history"><h2>Riwayat perubahan</h2>
        @foreach($riwayat as $r)<details><summary>{{ $r->aksi }} &middot; {{ $r->created_at->translatedFormat('d M Y H:i') }} &middot; {{ $r->pengguna?->nama ?: 'Pengguna tidak tersedia' }}</summary>
            <dl class="agenda-facts publikasi-snapshot"><dt>Versi</dt><dd>{{ $r->versi }}</dd>
                @foreach(['nama_lengkap' => 'Nama', 'nis' => 'NIS', 'nisn' => 'NISN', 'tahun_masuk' => 'Tahun masuk', 'tahun_lulus' => 'Tahun lulus', 'tanggal_lulus' => 'Tanggal lulus', 'kelas_terakhir' => 'Kelas terakhir', 'nama_sekolah' => 'Sekolah', 'kota_sekolah' => 'Kota / kabupaten', 'jurusan' => 'Jurusan', 'tanggal_penelusuran' => 'Tanggal penelusuran'] as $key => $label)<dt>{{ $label }}</dt><dd>{{ $r->snapshot[$key] ?? '-' }}</dd>@endforeach
                <dt>Jenis kelamin</dt><dd>{{ ['L' => 'Laki-laki', 'P' => 'Perempuan'][$r->snapshot['jenis_kelamin'] ?? ''] ?? '-' }}</dd>
                <dt>Sumber identitas</dt><dd>{{ ($r->snapshot['siswa_id'] ?? null) ? 'Siswa NUSA' : 'Input manual' }}</dd>
                <dt>Status data</dt><dd>{{ \App\Models\AlumniHumas::STATUS[$r->snapshot['status'] ?? ''] ?? '-' }}</dd>
                <dt>Penelusuran</dt><dd>{{ \App\Models\AlumniHumas::PENELUSURAN[$r->snapshot['status_penelusuran'] ?? ''] ?? '-' }}</dd>
                <dt>Jenis sekolah</dt><dd>{{ \App\Models\AlumniHumas::SEKOLAH[$r->snapshot['jenis_sekolah'] ?? ''] ?? '-' }}</dd>
                @if($bolehPrivat)
                    @foreach(['nomor_wa' => 'Nomor WA (privat)', 'email' => 'Email (privat)', 'catatan_penelusuran' => 'Catatan (privat)'] as $key => $label)<dt>{{ $label }}</dt><dd class="agenda-text">{{ $r->snapshot_privat[$key] ?? '-' }}</dd>@endforeach
                    <dt>Alasan perubahan</dt><dd class="agenda-text">{{ $r->catatan_perubahan ?: '-' }}</dd>
                @endif
            </dl>
        </details>@endforeach
        {{ $riwayat->links() }}
    </section>
</div>
@endsection
