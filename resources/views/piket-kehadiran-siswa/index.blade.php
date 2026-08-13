@extends('layouts.app')

@section('title', 'Piket Kehadiran Siswa - NUSA')

@section('content')
    <style>
        .attendance-form { min-width:260px; }
        .attendance-form summary { color:var(--primary); font-weight:900; cursor:pointer; }
        .attendance-form[open] summary { margin-bottom:10px; }
        .attendance-form-fields { display:grid; gap:8px; }
        .attendance-form-fields textarea { min-height:70px; }
        .picket-mobile-list { display:none; }
        @media(max-width:820px){ .picket-desktop { display:none; } .picket-mobile-list { display:grid; gap:12px; } }
    </style>

    <div class="page-header">
        <div><p class="eyebrow">Tugas Guru Piket</p><h1 class="page-title">Kehadiran Siswa Hari Ini</h1><p class="page-subtitle">{{ now()->locale('id')->translatedFormat('l, d F Y') }} &middot; {{ $tahunPelajaranAktif->nama }}</p></div>
        <a href="{{ route('jadwal-piket-saya.index') }}" class="button button-muted">Jadwal piket saya</a>
    </div>

    @if (session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger"><strong>Catatan belum dapat disimpan.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="stats-grid">
        <div class="panel stat"><p class="stat-label">Siswa dipantau</p><p class="stat-value">{{ $ringkasan['total'] }}</p></div>
        <div class="panel stat active"><p class="stat-label">Hadir</p><p class="stat-value">{{ $ringkasan['hadir'] }}</p></div>
        <div class="panel stat inactive"><p class="stat-label">Sakit</p><p class="stat-value">{{ $ringkasan['sakit'] }}</p></div>
        <div class="panel stat"><p class="stat-label">Izin</p><p class="stat-value">{{ $ringkasan['izin'] }}</p></div>
        <div class="panel stat inactive"><p class="stat-label">Belum scan</p><p class="stat-value">{{ $ringkasan['belum_scan'] }}</p></div>
    </div>

    <form action="{{ route('piket-kehadiran-siswa.index') }}" method="GET" class="panel panel-pad" style="margin-bottom:18px;" data-auto-filter>
        <div class="filter-grid">
            <div class="field"><label for="kelas_id">Kelas</label><select id="kelas_id" name="kelas_id" class="select" data-auto-submit><option value="">Semua kelas</option>@foreach ($kelas as $item)<option value="{{ $item->id }}" @selected((int) $kelasId === (int) $item->id)>{{ $item->nama }}</option>@endforeach</select></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status" class="select" data-auto-submit><option value="semua" @selected($status === 'semua')>Semua status</option><option value="belum_scan" @selected($status === 'belum_scan')>Belum scan</option><option value="hadir" @selected($status === 'hadir')>Hadir</option><option value="sakit" @selected($status === 'sakit')>Sakit</option><option value="izin" @selected($status === 'izin')>Izin</option><option value="alfa" @selected($status === 'alfa')>Alfa</option></select></div>
            <div class="field"><label for="cari">Cari siswa</label><input id="cari" name="cari" class="input" value="{{ $cari }}" placeholder="Nama, NIS, atau NISN"></div>
            <div class="actions"><button type="submit" class="button button-primary">Cari</button><a href="{{ route('piket-kehadiran-siswa.index') }}" class="button button-muted">Reset</a></div>
        </div>
    </form>

    <section class="panel picket-desktop">
        <div class="table-wrap"><table class="employee-table"><thead><tr><th>No.</th><th>Siswa</th><th>Kelas</th><th>Status hari ini</th><th>Keterangan</th><th>Aksi piket</th></tr></thead><tbody>
            @forelse ($anggotaKelas as $anggota)
                @php
                    $absensi = $absensiPerSiswa->get($anggota->siswa_id);
                    $statusHariIni = $absensi?->status_kehadiran ?? 'belum_scan';
                    $labelStatus = ['belum_scan'=>'Belum scan','hadir'=>'Hadir','sakit'=>'Sakit','izin'=>'Izin','alfa'=>'Alfa'][$statusHariIni] ?? ucfirst($statusHariIni);
                    $kelasBadge = match($statusHariIni){'hadir'=>'badge-active','sakit','izin'=>'badge-warning','alfa'=>'badge-danger',default=>'badge-muted'};
                    $bolehDicatat = ! $absensi || (in_array($statusHariIni, ['sakit','izin'], true) && $absensi->sumber === 'guru_piket');
                @endphp
                <tr>
                    <td>{{ $anggotaKelas->firstItem() + $loop->index }}</td>
                    <td><p class="person-name">{{ $anggota->siswa?->nama_lengkap }}</p><p class="person-meta">NISN {{ $anggota->siswa?->nisn ?: '-' }}</p></td>
                    <td>{{ $anggota->kelas?->nama }}</td>
                    <td><span class="badge {{ $kelasBadge }}">{{ $labelStatus }}</span></td>
                    <td>{{ $absensi?->catatan ?: '-' }}</td>
                    <td>
                        @if ($bolehDicatat)
                            <details class="attendance-form"><summary>{{ $absensi ? 'Ubah catatan' : 'Catat sakit/izin' }}</summary><form action="{{ route('piket-kehadiran-siswa.update', $anggota) }}" method="POST" class="attendance-form-fields">@csrf @method('PUT')<select name="status_kehadiran" class="select" required><option value="sakit" @selected($statusHariIni === 'sakit')>Sakit</option><option value="izin" @selected($statusHariIni === 'izin')>Izin</option></select><textarea name="catatan" class="textarea" required placeholder="Alasan atau sumber keterangan">{{ $absensi?->catatan }}</textarea><button class="button button-primary button-sm" type="submit">Simpan</button></form></details>
                        @else
                            <span class="person-meta">Tidak dapat diubah dari piket</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-state">Tidak ada siswa sesuai filter.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>

    <section class="picket-mobile-list">
        @forelse ($anggotaKelas as $anggota)
            @php
                $absensi = $absensiPerSiswa->get($anggota->siswa_id);
                $statusHariIni = $absensi?->status_kehadiran ?? 'belum_scan';
                $labelStatus = ['belum_scan'=>'Belum scan','hadir'=>'Hadir','sakit'=>'Sakit','izin'=>'Izin','alfa'=>'Alfa'][$statusHariIni] ?? ucfirst($statusHariIni);
                $kelasBadge = match($statusHariIni){'hadir'=>'badge-active','sakit','izin'=>'badge-warning','alfa'=>'badge-danger',default=>'badge-muted'};
                $bolehDicatat = ! $absensi || (in_array($statusHariIni, ['sakit','izin'], true) && $absensi->sumber === 'guru_piket');
            @endphp
            <article class="mobile-card"><div class="mobile-card-head"><div><p class="person-name">{{ $anggota->siswa?->nama_lengkap }}</p><p class="person-meta">{{ $anggota->kelas?->nama }} &middot; NISN {{ $anggota->siswa?->nisn ?: '-' }}</p></div><span class="badge {{ $kelasBadge }}">{{ $labelStatus }}</span></div>@if($absensi?->catatan)<p class="help-text" style="margin-top:10px;">{{ $absensi->catatan }}</p>@endif @if($bolehDicatat)<details class="attendance-form" style="margin-top:12px;"><summary>{{ $absensi ? 'Ubah catatan' : 'Catat sakit/izin' }}</summary><form action="{{ route('piket-kehadiran-siswa.update', $anggota) }}" method="POST" class="attendance-form-fields">@csrf @method('PUT')<select name="status_kehadiran" class="select" required><option value="sakit" @selected($statusHariIni === 'sakit')>Sakit</option><option value="izin" @selected($statusHariIni === 'izin')>Izin</option></select><textarea name="catatan" class="textarea" required placeholder="Alasan atau sumber keterangan">{{ $absensi?->catatan }}</textarea><button class="button button-primary" type="submit">Simpan</button></form></details>@endif</article>
        @empty
            <div class="panel empty-state">Tidak ada siswa sesuai filter.</div>
        @endforelse
    </section>

    @if ($anggotaKelas->hasPages())
        <nav class="pagination-simple"><div>Halaman {{ $anggotaKelas->currentPage() }} dari {{ $anggotaKelas->lastPage() }}</div><div class="actions">@if($anggotaKelas->onFirstPage())<span class="button button-muted" aria-disabled="true">Sebelumnya</span>@else<a href="{{ $anggotaKelas->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>@endif @if($anggotaKelas->hasMorePages())<a href="{{ $anggotaKelas->nextPageUrl() }}" class="button button-muted">Berikutnya</a>@else<span class="button button-muted" aria-disabled="true">Berikutnya</span>@endif</div></nav>
    @endif

    <script>document.querySelectorAll('[data-auto-submit]').forEach(element => element.addEventListener('change', () => element.form.submit()));</script>
@endsection
