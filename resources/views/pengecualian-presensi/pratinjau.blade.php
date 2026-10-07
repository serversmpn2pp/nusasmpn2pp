@extends('layouts.app')
@section('title', 'Pratinjau Pengecualian Presensi - NUSA')
@section('content')
    @include('pengecualian-presensi._style')
    <div class="page-header"><div><p class="eyebrow">Presensi siswa</p><h1 class="page-title">Pratinjau pengecualian</h1></div><a class="button button-muted" href="{{ route('pengecualian-presensi.index', $data) }}">Ubah isian</a></div>
    <section class="exception-section">
        <h2>{{ \App\Models\PengecualianPresensiSiswa::JENIS[$data['jenis']] }} &middot; {{ $kelas?->nama ?: 'Seluruh sekolah' }}</h2>
        <p class="exception-note">{{ $tahun->nama }} &middot; {{ \Carbon\Carbon::parse($data['tanggal_mulai'])->locale('id')->translatedFormat('d F Y') }} s.d. {{ \Carbon\Carbon::parse($data['tanggal_selesai'])->locale('id')->translatedFormat('d F Y') }}</p>
        <p class="exception-reason">{{ $data['alasan'] }}</p>
        <dl class="exception-stats" data-exception-stats>
            <div><dt>Siswa dalam cakupan</dt><dd>{{ $dampak['siswa_dalam_cakupan'] }}</dd></div>
            <div><dt>Siswa terdampak alfa</dt><dd>{{ $dampak['siswa_terdampak'] }}</dd></div>
            <div><dt>Alfa otomatis dikecualikan</dt><dd>{{ $dampak['alfa_otomatis_dibatalkan'] }}</dd></div>
            <div><dt>Catatan tetap tersimpan</dt><dd>{{ $dampak['catatan_dipertahankan'] }}</dd></div>
        </dl>
        <p class="exception-note">Dampak alfa dihitung pada {{ $dampak['hari_aktif_terlewati'] }} hari presensi aktif sampai hari ini. Tanggal mendatang belum menghasilkan alfa. Periode ini tidak otomatis menjadi hadir dan tidak mengubah hari aktif mingguan.</p>
        @if ($dampak['alfa_manual'] > 0)<div class="alert alert-warning">Ada {{ $dampak['alfa_manual'] }} catatan alfa manual yang tetap berlaku. Periksa catatan tersebut melalui koreksi presensi bila keliru.</div>@endif
        <p class="exception-note">Rekap dan angka dasar presensi rapor diperbarui saat dibuka. Koreksi rapor yang pernah disimpan tidak ditimpa; wali kelas perlu memeriksanya kembali.</p>
        <form action="{{ route('pengecualian-presensi.store') }}" method="POST" data-exception-confirm>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="exception-check"><input type="checkbox" name="konfirmasi" value="1" required><span>Saya sudah memeriksa tanggal, cakupan, alasan, dan dampak pengecualian.</span></label>
            <div class="exception-actions"><button class="button button-primary" type="submit">Terapkan pengecualian</button><a class="button button-muted" href="{{ route('pengecualian-presensi.index') }}">Batal</a></div>
        </form>
    </section>
    <section class="exception-section"><h2>Rincian siswa terdampak</h2>
        <table class="exception-table"><thead><tr><th>Siswa</th><th>Kelas</th><th>Alfa otomatis dikecualikan</th></tr></thead><tbody>
            @forelse ($perSiswa->where('alfa_dibatalkan', '>', 0) as $baris)<tr><td>{{ $baris['anggota']->siswa?->nama_lengkap }}</td><td>{{ $baris['anggota']->kelas?->nama }}</td><td>{{ $baris['alfa_dibatalkan'] }}</td></tr>
            @empty<tr><td colspan="3">Tidak ada alfa otomatis yang perlu dikecualikan saat ini.</td></tr>@endforelse
        </tbody></table>
    </section>
@endsection
