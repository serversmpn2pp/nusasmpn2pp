@extends('layouts.app')

@section('title', 'Rekap Kegiatan Ibadah - NUSA')

@section('content')
    <style>
        .worship-class-grid { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:11px; margin-bottom:20px; }
        .worship-stats-grid { grid-template-columns:repeat(4,minmax(0,1fr)); }
        .worship-class-card { display:block; overflow:hidden; border:1px solid var(--line); border-radius:8px; background:#fff; color:var(--ink); text-decoration:none; box-shadow:var(--shadow); transition:border-color .15s ease,transform .15s ease; }
        .worship-class-card:hover { border-color:var(--primary); transform:translateY(-1px); }
        .worship-class-card.selected { border:2px solid var(--primary); }
        .worship-class-main { padding:13px 14px 11px; }.worship-class-head { display:flex; align-items:center; justify-content:space-between; gap:8px; }.worship-class-head strong { font-size:.98rem; }.worship-percent { color:var(--primary-dark); font-size:.78rem; font-weight:900; }
        .worship-class-count { display:flex; align-items:flex-end; gap:5px; margin-top:10px; }.worship-class-count strong { color:var(--primary-dark); font-size:1.45rem; line-height:1; }.worship-class-count span { color:var(--muted); font-size:.77rem; }
        .worship-progress { height:7px; overflow:hidden; background:#e8edf2; }.worship-progress span { display:block; height:100%; background:var(--primary); }.worship-class-card.complete .worship-progress span { background:#17864b; }
        .worship-status-layout { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:14px; align-items:end; }
        .worship-schedule-note { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:18px; padding:13px 15px; border:1px solid #eed264; border-radius:8px; background:#fff9dc; }.worship-schedule-note strong,.worship-schedule-note span { display:block; }.worship-schedule-note span { margin-top:3px; color:#675918; font-size:.82rem; }.worship-schedule-times { flex:0 0 auto; color:var(--primary-dark); font-weight:900; }
        .student-identity { display:flex; align-items:center; gap:10px; min-width:250px; }.student-avatar { width:42px; height:50px; border-radius:7px; background:#dbe4ec; object-fit:cover; }.student-identity strong,.student-identity span { display:block; }.student-identity span { margin-top:3px; color:var(--muted); font-size:.75rem; }
        @media(max-width:1150px){.worship-class-grid{grid-template-columns:repeat(4,minmax(0,1fr));}}
        @media(max-width:850px){.worship-class-grid{grid-template-columns:repeat(3,minmax(0,1fr));}.worship-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr));}.worship-status-layout{grid-template-columns:1fr;}.worship-schedule-note{align-items:flex-start;flex-direction:column;}}
        @media(max-width:580px){.worship-class-grid{grid-template-columns:repeat(2,minmax(0,1fr));}.worship-class-main{padding:11px;}.worship-class-count strong{font-size:1.25rem;}}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kehadiran Siswa</p>
            <h1 class="page-title">Rekap Kegiatan Ibadah</h1>
            <p class="page-subtitle">Pantau siswa yang sudah salat, belum salat, berhalangan, atau tidak hadir berdasarkan kelas.</p>
        </div>
        <div class="actions">
            <a href="{{ route('rekap-kegiatan-ibadah.bulanan') }}" class="button button-muted">Ringkasan bulanan</a>
            @if($dapatScanSekarang)
                <a href="{{ route('scan-kegiatan-ibadah.index') }}" target="_blank" rel="noopener" class="button button-primary">Buka scanner</a>
            @endif
            @izin('ibadah.pengaturan_kelola')
                <a href="{{ route('jadwal-kegiatan-ibadah.index') }}" class="button button-muted">Jadwal ibadah</a>
            @endizin
        </div>
    </div>

    <form method="GET" action="{{ route('rekap-kegiatan-ibadah.index') }}" class="panel panel-pad" style="margin-bottom:18px;">
        <div class="filter-grid filter-grid-wide">
            <div class="field"><label for="tanggal">Tanggal</label><input id="tanggal" name="tanggal" type="date" max="{{ now()->toDateString() }}" value="{{ $tanggal }}" class="input" onchange="this.form.submit()"></div>
            <div class="field"><label for="kegiatan_ibadah_id">Kegiatan</label><select id="kegiatan_ibadah_id" name="kegiatan_ibadah_id" class="select" onchange="this.form.submit()">@foreach($daftarKegiatan as $kegiatan)<option value="{{ $kegiatan->id }}" @selected((int)$kegiatanId===(int)$kegiatan->id)>{{ $kegiatan->nama }}{{ $kegiatan->aktif ? '' : ' - nonaktif' }}</option>@endforeach</select></div>
            <div class="field"><label for="kelas_id">Kelas</label><select id="kelas_id" name="kelas_id" class="select" onchange="this.form.submit()"><option value="">Semua kelas</option>@foreach($daftarKelas as $kelas)<option value="{{ $kelas->id }}" @selected((int)$kelasId===(int)$kelas->id)>{{ $kelas->nama }}</option>@endforeach</select></div>
            <div class="actions"><a href="{{ route('rekap-kegiatan-ibadah.index') }}" class="button button-muted">Hari ini</a></div>
        </div>
    </form>

    @if(!$tahunPelajaran)
        <section class="panel panel-pad"><h2 class="panel-title">Tahun pelajaran aktif belum tersedia</h2><p class="help-text" style="margin-top:7px;">Aktifkan tahun pelajaran terlebih dahulu agar rekap per kelas dapat dihitung.</p></section>
    @elseif(!$kegiatanDipilih)
        <section class="panel panel-pad"><h2 class="panel-title">Kegiatan ibadah belum tersedia</h2><p class="help-text" style="margin-top:7px;">Tambahkan kegiatan ibadah sebelum membuka rekap.</p></section>
    @else
        @if($jadwal)
            <div class="worship-schedule-note"><div><strong>{{ $kegiatanDipilih->nama }} &middot; {{ $tanggalLabel }}</strong><span>{{ $jadwal->aktif ? 'Jadwal aktif' : 'Jadwal saat ini nonaktif' }}{{ $jadwal->keterangan ? ' · '.$jadwal->keterangan : '' }}</span></div><div class="worship-schedule-times">Pelaksanaan {{ $jadwal->formatJam($jadwal->jam_pelaksanaan) }} &middot; Scan {{ $jadwal->rentangScan() }}</div></div>
        @else
            <div class="alert">Tidak ada jadwal {{ $kegiatanDipilih->nama }} pada {{ $tanggalLabel }}. Rekap tetap menampilkan data jika pernah ada presensi.</div>
        @endif

        <div class="stats-grid worship-stats-grid">
            <div class="panel stat"><p class="stat-label">{{ $kelasDipilih ? 'Siswa kelas '.$kelasDipilih->nama : 'Seluruh siswa' }}</p><p class="stat-value">{{ $ringkasan['total'] }}</p></div>
            <div class="panel stat"><p class="stat-label">Hadir di sekolah</p><p class="stat-value">{{ $ringkasan['hadir'] }}</p></div>
            <div class="panel stat inactive"><p class="stat-label">Tidak hadir</p><p class="stat-value">{{ $ringkasan['tidak_hadir'] }}</p></div>
            <div class="panel stat"><p class="stat-label">Berhalangan</p><p class="stat-value">{{ $ringkasan['berhalangan'] }}</p></div>
            <div class="panel stat"><p class="stat-label">Wajib salat</p><p class="stat-value">{{ $ringkasan['wajib'] }}</p></div>
            <div class="panel stat active"><p class="stat-label">Sudah salat</p><p class="stat-value">{{ $ringkasan['sudah'] }}</p></div>
            <div class="panel stat inactive"><p class="stat-label">Belum salat</p><p class="stat-value">{{ $ringkasan['belum'] }}</p></div>
            <div class="panel stat"><p class="stat-label">Capaian siswa wajib salat</p><p class="stat-value">{{ $ringkasan['persentase'] }}%</p></div>
        </div>

        <div class="section-heading" style="margin:22px 0 12px;"><div><h2 class="panel-title">Ringkasan per kelas</h2><p class="help-text">Tekan kelas untuk membuka daftar siswanya.</p></div>@if($kelasId)<a href="{{ route('rekap-kegiatan-ibadah.index',['tanggal'=>$tanggal,'kegiatan_ibadah_id'=>$kegiatanId]) }}" class="button button-muted button-sm">Semua kelas</a>@endif</div>
        <section class="worship-class-grid">
            @forelse($ringkasanKelas as $item)
                @php
                    $parameter = ['tanggal'=>$tanggal,'kegiatan_ibadah_id'=>$kegiatanId,'kelas_id'=>$item['kelas']->id];
                @endphp
                <a href="{{ route('rekap-kegiatan-ibadah.index',$parameter) }}" class="worship-class-card {{ (int)$kelasId===(int)$item['kelas']->id ? 'selected' : '' }} {{ $item['wajib'] > 0 && $item['belum'] === 0 ? 'complete' : '' }}">
                    <div class="worship-class-main">
                        <div class="worship-class-head"><strong>{{ $item['kelas']->nama }}</strong><span class="worship-percent">{{ $item['persentase'] }}%</span></div>
                        <div class="worship-class-count"><strong>{{ $item['sudah'] }}</strong><span>dari {{ $item['wajib'] }} siswa wajib salat</span></div>
                        <p class="help-text" style="margin-top:8px;">{{ $item['tidak_hadir'] }} tidak hadir &middot; {{ $item['berhalangan'] }} berhalangan</p>
                    </div>
                    <div class="worship-progress"><span style="width:{{ $item['persentase'] }}%"></span></div>
                </a>
            @empty
                <div class="panel panel-pad" style="grid-column:1/-1;">Belum ada kelas aktif pada tahun pelajaran ini.</div>
            @endforelse
        </section>

        @if($kelasDipilih)
            <form method="GET" action="{{ route('rekap-kegiatan-ibadah.index') }}" class="panel panel-pad" style="margin-bottom:16px;">
                <input type="hidden" name="tanggal" value="{{ $tanggal }}"><input type="hidden" name="kegiatan_ibadah_id" value="{{ $kegiatanId }}"><input type="hidden" name="kelas_id" value="{{ $kelasId }}">
                <div class="worship-status-layout"><div class="filter-grid"><div class="field"><label for="cari">Cari siswa</label><input id="cari" name="cari" value="{{ $cari }}" class="input" placeholder="Nama, NIS, atau NISN"></div><div class="field"><label for="status">Status ibadah</label><select id="status" name="status" class="select" onchange="this.form.submit()"><option value="semua" @selected($status==='semua')>Semua status</option><option value="sudah" @selected($status==='sudah')>Sudah salat</option><option value="belum" @selected($status==='belum')>Belum salat</option><option value="berhalangan" @selected($status==='berhalangan')>Berhalangan</option><option value="tidak_hadir" @selected($status==='tidak_hadir')>Tidak hadir sekolah</option></select></div></div><div class="actions"><button class="button button-dark" type="submit">Cari</button><a href="{{ route('rekap-kegiatan-ibadah.index',['tanggal'=>$tanggal,'kegiatan_ibadah_id'=>$kegiatanId,'kelas_id'=>$kelasId]) }}" class="button button-muted">Reset</a></div></div>
            </form>

            <section class="panel">
                <div class="panel-pad" style="border-bottom:1px solid var(--line);"><h2 class="panel-title">Siswa Kelas {{ $kelasDipilih->nama }}</h2><p class="help-text" style="margin-top:5px;">Belum salat hanya berlaku untuk siswa yang hadir di sekolah, tidak berhalangan, dan belum melakukan scan ibadah. Catatan privat berhalangan tidak ditampilkan di rekap umum.</p></div>
                <div class="desktop-only table-wrap"><table class="employee-table"><thead><tr><th>No.</th><th>Siswa</th><th>Status ibadah</th><th>Kehadiran sekolah</th><th>Waktu</th><th>Dicatat oleh</th>@if($dapatKoreksi)<th>Aksi</th>@endif</tr></thead><tbody>
                    @forelse($anggotaKelas as $anggota)
                        @php
                            $presensi = $presensiPerSiswa->get($anggota->siswa_id);
                            $statusHarian = $statusPerSiswa->get($anggota->siswa_id);
                            $statusKode = $statusHarian['status'] ?? 'tidak_hadir';
                            $kelasBadge = match($statusKode) {
                                'sudah' => 'badge-active',
                                'belum' => 'badge-warning',
                                'berhalangan' => 'badge-muted',
                                default => 'badge-inactive',
                            };
                            $dapatDikoreksiSiswa = in_array($statusKode, ['sudah', 'belum'], true);
                        @endphp
                        <tr><td>{{ $anggota->nomor_absen ?: $loop->iteration }}</td><td><div class="student-identity"><img class="student-avatar" src="{{ $anggota->siswa?->foto ? asset('storage/'.$anggota->siswa->foto) : asset('images/kartu-pelajar/default-user.png') }}" alt=""><div><strong>{{ $anggota->siswa?->nama_lengkap }}</strong><span>NISN {{ $anggota->siswa?->nisn ?: '-' }}</span></div></div></td><td><span class="badge {{ $kelasBadge }}">{{ $statusHarian['status_label'] ?? 'Tidak hadir sekolah' }}</span></td><td>{{ $statusHarian['status_kehadiran_label'] ?? 'Belum tercatat di presensi sekolah' }}</td><td>{{ $presensi ? substr((string)$presensi->waktu_scan,0,5) : '-' }}</td><td>{{ $presensi?->dipindaiOleh?->nama ?: '-' }}@if($presensi?->dikoreksiOleh)<div class="help-text">Koreksi: {{ $presensi->dikoreksiOleh->nama }}</div>@endif</td>@if($dapatKoreksi)<td>@if($dapatDikoreksiSiswa)<a class="button button-muted button-sm" href="{{ route('rekap-kegiatan-ibadah.koreksi.edit',['anggotaKelas'=>$anggota,'tanggal'=>$tanggal,'kegiatan_ibadah_id'=>$kegiatanId]) }}">{{ $presensi ? 'Koreksi' : 'Input manual' }}</a>@else<span class="help-text">Tidak perlu</span>@endif</td>@endif</tr>
                    @empty
                        <tr><td colspan="{{ $dapatKoreksi ? 7 : 6 }}" class="empty-state">Tidak ada siswa sesuai pencarian atau status.</td></tr>
                    @endforelse
                </tbody></table></div>
                <div class="mobile-only mobile-list">
                    @forelse($anggotaKelas as $anggota)
                        @php
                            $presensi = $presensiPerSiswa->get($anggota->siswa_id);
                            $statusHarian = $statusPerSiswa->get($anggota->siswa_id);
                            $statusKode = $statusHarian['status'] ?? 'tidak_hadir';
                            $kelasBadge = match($statusKode) {
                                'sudah' => 'badge-active',
                                'belum' => 'badge-warning',
                                'berhalangan' => 'badge-muted',
                                default => 'badge-inactive',
                            };
                            $dapatDikoreksiSiswa = in_array($statusKode, ['sudah', 'belum'], true);
                        @endphp
                        <article class="mobile-card"><div class="mobile-card-head"><div><p class="person-name">{{ $anggota->nomor_absen ?: $loop->iteration }}. {{ $anggota->siswa?->nama_lengkap }}</p><p class="person-meta">NISN {{ $anggota->siswa?->nisn ?: '-' }}</p></div><span class="badge {{ $kelasBadge }}">{{ $statusHarian['status_label'] ?? 'Tidak hadir sekolah' }}</span></div><dl class="mobile-facts"><div><dt>Kehadiran sekolah</dt><dd>{{ $statusHarian['status_kehadiran_label'] ?? 'Belum tercatat di presensi sekolah' }}</dd></div><div><dt>Waktu ibadah</dt><dd>{{ $presensi ? substr((string)$presensi->waktu_scan,0,5) : '-' }}</dd></div><div><dt>Dicatat oleh</dt><dd>{{ $presensi?->dipindaiOleh?->nama ?: '-' }}</dd></div></dl>@if($dapatKoreksi && $dapatDikoreksiSiswa)<div class="form-actions" style="margin-top:12px;"><a class="button button-muted button-sm button-full" href="{{ route('rekap-kegiatan-ibadah.koreksi.edit',['anggotaKelas'=>$anggota,'tanggal'=>$tanggal,'kegiatan_ibadah_id'=>$kegiatanId]) }}">{{ $presensi ? 'Koreksi presensi' : 'Input manual' }}</a></div>@endif</article>
                    @empty
                        <div class="empty-state">Tidak ada siswa sesuai pencarian atau status.</div>
                    @endforelse
                </div>
            </section>
            @if($anggotaKelas->hasPages())<div style="margin-top:16px;">{{ $anggotaKelas->links() }}</div>@endif
        @else
            <section class="panel panel-pad"><h2 class="panel-title">Pilih kelas untuk melihat siswa</h2><p class="help-text" style="margin-top:7px;">Ringkasan seluruh kelas sudah tampil di atas. Tekan salah satu kelas untuk melihat status ibadah dan kehadiran sekolah setiap siswa.</p></section>
        @endif
    @endif
@endsection
