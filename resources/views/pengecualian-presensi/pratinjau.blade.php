@extends('layouts.app')
@section('admin-module', 'kehadiran')
@section('title', 'Pratinjau Pengecualian Presensi - NUSA')
@section('content')
    @include('pengecualian-presensi._style')
    <div class="page-header"><div><p class="eyebrow">Presensi siswa</p><h1 class="page-title">Pratinjau pengecualian</h1></div><a class="button button-muted" href="{{ route('pengecualian-presensi.index', ['tahun_pelajaran_id' => $data['tahun_pelajaran_id']]) }}">Ubah isian</a></div>
    <section class="exception-section">
        <h2>{{ \App\Models\PengecualianPresensiSiswa::jenisWeb()[$data['jenis']] }} &middot; {{ ($data['cakupan'] ?? null) === 'siswa' ? $dampak['siswa_dalam_cakupan'].' siswa terpilih' : ($kelas?->nama ?: 'Seluruh sekolah') }}</h2>
        <p class="exception-note">{{ $tahun->nama }} &middot; {{ \Carbon\Carbon::parse($data['tanggal_mulai'])->locale('id')->translatedFormat('d F Y') }} s.d. {{ \Carbon\Carbon::parse($data['tanggal_selesai'])->locale('id')->translatedFormat('d F Y') }}</p>
        <p class="exception-reason">{{ $data['alasan'] }}</p>
        <dl class="exception-stats" data-exception-stats>
            <div><dt>Siswa dalam cakupan</dt><dd>{{ $dampak['siswa_dalam_cakupan'] }}</dd></div>
            <div><dt>{{ $data['jenis'] === 'tugas_sekolah' ? 'Catatan hadir penugasan' : 'Siswa terdampak alfa' }}</dt><dd>{{ $data['jenis'] === 'tugas_sekolah' ? $dampak['hadir_penugasan'] : $dampak['siswa_terdampak'] }}</dd></div>
            <div><dt>{{ $data['jenis'] === 'tugas_sekolah' ? 'Poin presensi dikecualikan' : 'Alfa otomatis dikecualikan' }}</dt><dd>{{ $data['jenis'] === 'tugas_sekolah' ? $dampak['poin_dibatalkan'] : $dampak['alfa_otomatis_dibatalkan'] }}</dd></div>
            <div><dt>Catatan tetap tersimpan</dt><dd>{{ $dampak['catatan_dipertahankan'] }}</dd></div>
        </dl>
        @if ($data['jenis'] === 'tugas_sekolah')
            <p class="exception-note"><strong>{{ $dampak['hadir_penugasan'] }} catatan Hadir — Tugas sekolah</strong> pada hari presensi aktif. {{ $dampak['poin_dibatalkan'] }} poin presensi akan dikecualikan. Tanggal mendatang merupakan penugasan terjadwal; jam masuk/pulang tidak dibuat-buat. Jam scan dan catatan petugas tetap tersimpan.</p>
            @if ($perubahan_status->isNotEmpty())
                <div class="alert alert-warning" data-duty-status-corrections>
                    <strong>Koreksi Sakit/Izin menjadi tugas sekolah</strong>
                    <p>{{ $dampak['sakit_dikoreksi'] }} catatan Sakit dan {{ $dampak['izin_dikoreksi'] }} catatan Izin akan dikoreksi saat penetapan diterapkan. Status, catatan, dan petugas sebelumnya disimpan dalam riwayat.</p>
                </div>
                <p class="exception-note exception-correction-scroll-note">Geser tabel untuk melihat seluruh rincian koreksi.</p>
                <div class="exception-correction-scroll">
                    <table class="exception-table" style="min-width:640px;" data-duty-correction-table>
                        <thead><tr><th>Siswa</th><th>Kelas</th><th>Tanggal</th><th>Perubahan status</th></tr></thead>
                        <tbody>
                            @foreach ($perubahan_status as $baris)
                                <tr><td>{{ $baris['anggota']->siswa?->nama_lengkap }}</td><td>{{ $baris['anggota']->kelas?->nama }}</td>
                                    <td style="white-space:nowrap;">{{ \Carbon\Carbon::parse($baris['tanggal'])->format('d/m/Y') }}</td>
                                    <td><span class="badge badge-muted">{{ ucfirst($baris['status_sebelum']) }}</span> → <span class="badge badge-active">Hadir — Tugas sekolah</span></td></tr>
                                <tr><td colspan="4"><details><summary>Catatan dan pencatat sebelumnya</summary>
                                    <p class="exception-note">{{ $baris['riwayat_sebelum']['presensi_sebelum']['catatan'] ?: 'Tidak ada catatan.' }}</p>
                                    <p class="exception-note">{{ $baris['riwayat_sebelum']['pencatat_sebelum_nama'] ?? 'Pencatat tidak tercatat' }}
                                        @if ($baris['riwayat_sebelum']['waktu_sebelum']) · {{ \Carbon\Carbon::parse($baris['riwayat_sebelum']['waktu_sebelum'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }} @endif
                                    </p>
                                </details></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @else
            <p class="exception-note">Dampak alfa dihitung pada {{ $dampak['hari_aktif_terlewati'] }} hari presensi aktif sampai hari ini. Tanggal mendatang belum menghasilkan alfa. Periode ini tidak otomatis menjadi hadir dan tidak mengubah hari aktif mingguan.</p>
        @endif
        @if ($dampak['alfa_manual'] > 0)<div class="alert alert-warning">{{ $data['jenis'] === 'tugas_sekolah' ? $dampak['alfa_manual'].' catatan Alfa pada tanggal penugasan juga dikoreksi menjadi Hadir — Tugas sekolah.' : 'Ada '.$dampak['alfa_manual'].' catatan alfa manual yang tetap berlaku. Periksa catatan tersebut melalui koreksi presensi bila keliru.' }}</div>@endif
        <p class="exception-note">Rekap dan angka dasar presensi rapor diperbarui saat dibuka. Koreksi rapor yang pernah disimpan tidak ditimpa; wali kelas perlu memeriksanya kembali.</p>
        <form action="{{ route('pengecualian-presensi.store') }}" method="POST" data-exception-confirm>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="exception-check"><input type="checkbox" name="konfirmasi" value="1" required><span>Saya sudah memeriksa tanggal, cakupan, alasan, dan dampak pengecualian{{ ($dampak['sakit_dikoreksi'] ?? 0) + ($dampak['izin_dikoreksi'] ?? 0) > 0 ? ', termasuk koreksi Sakit/Izin menjadi Hadir — Tugas sekolah' : '' }}.</span></label>
            <div class="exception-actions"><button class="button button-primary" type="submit">Terapkan pengecualian</button><a class="button button-muted" href="{{ route('pengecualian-presensi.index') }}">Batal</a></div>
        </form>
    </section>
    <section class="exception-section"><h2>Rincian siswa terdampak</h2>
        <table class="exception-table"><thead><tr><th>Siswa</th><th>Kelas</th><th>{{ $data['jenis'] === 'tugas_sekolah' ? 'Status penugasan' : 'Alfa otomatis dikecualikan' }}</th></tr></thead><tbody>
            @forelse ($data['jenis'] === 'tugas_sekolah' ? $perSiswa : $perSiswa->where('alfa_dibatalkan', '>', 0) as $baris)<tr><td>{{ $baris['anggota']->siswa?->nama_lengkap }}</td><td>{{ $baris['anggota']->kelas?->nama }}</td><td>{{ $data['jenis'] === 'tugas_sekolah' ? 'Hadir — Tugas sekolah' : $baris['alfa_dibatalkan'] }}</td></tr>
            @empty<tr><td colspan="3">Tidak ada alfa otomatis yang perlu dikecualikan saat ini.</td></tr>@endforelse
        </tbody></table>
    </section>
@endsection
