@extends('layouts.app')

@section('title', 'Ringkasan Ibadah Bulanan - NUSA')

@section('content')
    @php
        $formatPersen = fn ($nilai) => rtrim(rtrim(number_format((float) $nilai, 1, ',', '.'), '0'), ',').'%';
        $parameterDasar = ['bulan' => $bulan, 'kegiatan_ibadah_id' => $kegiatanId];
    @endphp

    <style>
        .monthly-worship-filter { display:grid; grid-template-columns:minmax(180px,240px) minmax(220px,1fr) minmax(180px,260px) auto; gap:12px; align-items:end; }
        .monthly-worship-stats { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; margin:20px 0; }
        .monthly-worship-classes { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:20px; }
        .monthly-class-card { display:block; overflow:hidden; border:1px solid var(--line); border-radius:8px; background:#fff; box-shadow:var(--shadow); transition:border-color .15s ease,transform .15s ease; }.monthly-class-card:hover { border-color:var(--primary); transform:translateY(-1px); }.monthly-class-card.selected { border:2px solid var(--primary); }
        .monthly-class-content { padding:14px; }.monthly-class-head { display:flex; align-items:center; justify-content:space-between; gap:10px; }.monthly-class-head strong { font-size:1rem; }.monthly-class-head span { color:var(--primary-dark); font-size:.8rem; font-weight:900; }
        .monthly-class-numbers { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; margin-top:13px; }.monthly-class-numbers div { min-width:0; }.monthly-class-numbers strong,.monthly-class-numbers span { display:block; }.monthly-class-numbers strong { color:var(--primary-dark); font-size:1.1rem; }.monthly-class-numbers span { color:var(--muted); font-size:.7rem; }
        .monthly-progress { height:7px; background:#e8edf2; }.monthly-progress span { display:block; height:100%; background:var(--primary); }.monthly-class-card.complete .monthly-progress span { background:#17864b; }
        .activity-dates { display:flex; flex-wrap:wrap; gap:7px; margin-top:10px; }.activity-date { display:inline-flex; align-items:center; min-height:30px; border:1px solid #d9e3ec; border-radius:6px; background:#f7f9fb; padding:5px 9px; color:var(--primary-dark); font-size:.78rem; font-weight:800; }
        .student-monthly-name { display:flex; align-items:center; gap:10px; min-width:260px; }.student-monthly-name img { width:42px; height:50px; border-radius:7px; background:#e5e7eb; object-fit:cover; }.student-monthly-name strong,.student-monthly-name span { display:block; }.student-monthly-name span { margin-top:2px; color:var(--muted); font-size:.76rem; }
        @media(max-width:1100px){.monthly-worship-stats{grid-template-columns:repeat(3,minmax(0,1fr))}.monthly-worship-classes{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:850px){.monthly-worship-filter{grid-template-columns:repeat(2,minmax(0,1fr))}.monthly-worship-filter .actions{grid-column:1/-1}.monthly-worship-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.monthly-worship-classes{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:560px){.monthly-worship-filter{grid-template-columns:1fr}.monthly-worship-filter .actions{grid-column:auto}.monthly-worship-classes{grid-template-columns:1fr}.monthly-class-numbers strong{font-size:1rem}}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kehadiran Siswa</p>
            <h1 class="page-title">Ringkasan Ibadah Bulanan</h1>
            <p class="page-subtitle">Capaian presensi kegiatan ibadah per kelas dan siswa.</p>
        </div>
        <div class="actions"><a href="{{ route('rekap-kegiatan-ibadah.index') }}" class="button button-muted">Rekap harian</a></div>
    </div>

    <form method="GET" action="{{ route('rekap-kegiatan-ibadah.bulanan') }}" class="panel panel-pad">
        <div class="monthly-worship-filter">
            <div class="field">
                <label for="bulan">Bulan</label>
                <input id="bulan" name="bulan" type="month" value="{{ $bulan }}" min="{{ $bulanMinimum }}" max="{{ $bulanMaksimum }}" class="input @error('bulan') is-invalid @enderror" onchange="this.form.submit()">
                @error('bulan')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="kegiatan_ibadah_id">Kegiatan</label>
                <select id="kegiatan_ibadah_id" name="kegiatan_ibadah_id" class="select" onchange="this.form.submit()">
                    @foreach($daftarKegiatan as $kegiatan)<option value="{{ $kegiatan->id }}" @selected((int)$kegiatanId === (int)$kegiatan->id)>{{ $kegiatan->nama }}{{ $kegiatan->aktif ? '' : ' - nonaktif' }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select" onchange="this.form.submit()">
                    <option value="">Semua kelas</option>
                    @foreach($daftarKelas as $kelas)<option value="{{ $kelas->id }}" @selected((int)$kelasId === (int)$kelas->id)>{{ $kelas->nama }}</option>@endforeach
                </select>
            </div>
            <div class="actions"><a href="{{ route('rekap-kegiatan-ibadah.bulanan') }}" class="button button-muted">Bulan ini</a></div>
        </div>
    </form>

    @if(!$tahunPelajaran)
        <section class="panel panel-pad" style="margin-top:20px;"><h2 class="panel-title">Tahun pelajaran aktif belum tersedia</h2></section>
    @elseif(!$kegiatanDipilih)
        <section class="panel panel-pad" style="margin-top:20px;"><h2 class="panel-title">Kegiatan ibadah belum tersedia</h2></section>
    @else
        @if($kegiatanDipilih->khususLakiLaki())
            <div class="alert" style="margin-top:20px;">Sholat Jumat hanya menghitung siswa laki-laki. Siswi yang langsung pulang tidak menjadi target dan tidak dihitung sebagai belum tercatat.</div>
        @endif

        <div class="monthly-worship-stats">
            <div class="panel stat"><p class="stat-label">Hari kegiatan</p><p class="stat-value">{{ $ringkasan['hari_kegiatan'] }}</p></div>
            <div class="panel stat"><p class="stat-label">{{ $kelasDipilih ? 'Siswa wajib '.$kelasDipilih->nama : 'Siswa wajib' }}</p><p class="stat-value">{{ $ringkasan['siswa'] }}</p></div>
            <div class="panel stat active"><p class="stat-label">Presensi tercatat</p><p class="stat-value">{{ $ringkasan['tercatat'] }}</p></div>
            <div class="panel stat inactive"><p class="stat-label">Belum tercatat</p><p class="stat-value">{{ $ringkasan['belum'] }}</p></div>
            <div class="panel stat"><p class="stat-label">Capaian</p><p class="stat-value">{{ $formatPersen($ringkasan['persentase']) }}</p></div>
        </div>

        <section class="panel panel-pad" style="margin-bottom:20px;">
            <h2 class="panel-title">{{ $kegiatanDipilih->nama }} &middot; {{ $bulanLabel }}</h2>
            @if($tanggalKegiatan->isEmpty())
                <p class="help-text" style="margin-top:7px;">Belum ada jadwal yang berlangsung atau presensi yang tercatat pada bulan ini.</p>
            @else
                <p class="help-text" style="margin-top:7px;">Tanggal kegiatan yang dihitung sampai hari ini. Tanggal libur belum dikecualikan karena kalender akademik libur belum tersedia.</p>
                <div class="activity-dates">
                    @foreach($tanggalKegiatan as $item)<span class="activity-date">{{ \Carbon\Carbon::parse($item)->locale('id')->translatedFormat('d M') }}</span>@endforeach
                </div>
            @endif
        </section>

        <div class="section-heading" style="margin:0 0 12px;"><div><h2 class="panel-title">Ringkasan per kelas</h2><p class="help-text">Tekan kelas untuk melihat capaian setiap siswa.</p></div>@if($kelasId)<a href="{{ route('rekap-kegiatan-ibadah.bulanan',$parameterDasar) }}" class="button button-muted button-sm">Semua kelas</a>@endif</div>
        <section class="monthly-worship-classes">
            @forelse($ringkasanKelas as $item)
                <a href="{{ route('rekap-kegiatan-ibadah.bulanan',$parameterDasar + ['kelas_id'=>$item['kelas']->id]) }}" class="monthly-class-card {{ (int)$kelasId === (int)$item['kelas']->id ? 'selected' : '' }} {{ $item['target'] > 0 && $item['belum'] === 0 ? 'complete' : '' }}">
                    <div class="monthly-class-content">
                        <div class="monthly-class-head"><strong>{{ $item['kelas']->nama }}</strong><span>{{ $formatPersen($item['persentase']) }}</span></div>
                        <div class="monthly-class-numbers"><div><strong>{{ $item['siswa'] }}</strong><span>Siswa wajib</span></div><div><strong>{{ $item['tercatat'] }}</strong><span>Tercatat</span></div><div><strong>{{ $item['belum'] }}</strong><span>Belum</span></div></div>
                    </div>
                    <div class="monthly-progress"><span style="width:{{ $item['persentase'] }}%"></span></div>
                </a>
            @empty
                <div class="panel panel-pad" style="grid-column:1/-1;">Belum ada kelas aktif.</div>
            @endforelse
        </section>

        @if($kelasDipilih)
            <section class="panel">
                <div class="panel-pad" style="border-bottom:1px solid var(--line);"><h2 class="panel-title">Capaian Siswa Kelas {{ $kelasDipilih->nama }}</h2><p class="help-text" style="margin-top:5px;">Belum tercatat berarti tidak ada scan maupun input manual pada tanggal kegiatan yang dihitung.</p></div>
                <div class="desktop-only table-wrap"><table class="employee-table"><thead><tr><th>No.</th><th>Siswa</th><th>Hari kegiatan</th><th>Tercatat</th><th>Belum</th><th>Capaian</th><th>Terakhir</th></tr></thead><tbody>
                    @forelse($detailSiswa as $item)
                        @php $anggota=$item['anggota']; @endphp
                        <tr><td>{{ $anggota->nomor_absen ?: $loop->iteration }}</td><td><div class="student-monthly-name"><img src="{{ $anggota->siswa?->foto ? asset('storage/'.$anggota->siswa->foto) : asset('images/kartu-pelajar/default-user.png') }}" alt=""><div><strong>{{ $anggota->siswa?->nama_lengkap }}</strong><span>NISN {{ $anggota->siswa?->nisn ?: '-' }}{{ $item['manual'] ? ' · Manual '.$item['manual'].' kali' : '' }}</span></div></div></td><td>{{ $item['target'] }}</td><td>{{ $item['tercatat'] }}</td><td>{{ $item['belum'] }}</td><td><span class="badge {{ $item['persentase'] >= 80 ? 'badge-active' : 'badge-warning' }}">{{ $formatPersen($item['persentase']) }}</span></td><td>{{ $item['terakhir'] ? $item['terakhir']->locale('id')->translatedFormat('d M Y') : '-' }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">Belum ada siswa aktif pada kelas ini.</td></tr>
                    @endforelse
                </tbody></table></div>
                <div class="mobile-only mobile-list">
                    @forelse($detailSiswa as $item)
                        @php $anggota=$item['anggota']; @endphp
                        <article class="mobile-card"><div class="mobile-card-head"><div><p class="person-name">{{ $anggota->nomor_absen ?: $loop->iteration }}. {{ $anggota->siswa?->nama_lengkap }}</p><p class="person-meta">NISN {{ $anggota->siswa?->nisn ?: '-' }}</p></div><span class="badge {{ $item['persentase'] >= 80 ? 'badge-active' : 'badge-warning' }}">{{ $formatPersen($item['persentase']) }}</span></div><dl class="mobile-facts"><div><dt>Hari kegiatan</dt><dd>{{ $item['target'] }}</dd></div><div><dt>Tercatat</dt><dd>{{ $item['tercatat'] }}</dd></div><div><dt>Belum</dt><dd>{{ $item['belum'] }}</dd></div><div><dt>Input manual</dt><dd>{{ $item['manual'] }}</dd></div></dl></article>
                    @empty
                        <div class="empty-state">Belum ada siswa aktif pada kelas ini.</div>
                    @endforelse
                </div>
            </section>
        @endif
    @endif
@endsection
