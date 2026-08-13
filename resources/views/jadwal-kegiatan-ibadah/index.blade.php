@extends('layouts.app')

@section('title', 'Jadwal Kegiatan Ibadah - NUSA')

@section('content')
    <style>
        .worship-schedule-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
        .worship-day { min-height:210px; border:1px solid var(--line); border-top:4px solid var(--primary); border-radius:8px; background:#fff; box-shadow:var(--shadow); }
        .worship-day.inactive { border-top-color:#a1a1aa; }
        .worship-day-head { display:flex; align-items:center; justify-content:space-between; gap:12px; border-bottom:1px solid var(--line); padding:14px 16px; }
        .worship-day-head h2 { margin:0; font-size:1rem; font-weight:900; }
        .worship-day-body { padding:16px; }.worship-time { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .worship-time div { border:1px solid var(--line); border-radius:8px; background:var(--soft); padding:11px; }
        .worship-time span,.worship-time strong { display:block; }.worship-time span { color:var(--muted); font-size:.78rem; font-weight:700; }.worship-time strong { margin-top:4px; color:var(--primary-dark); }
        .worship-note { margin:12px 0 0; min-height:36px; color:var(--muted); font-size:.84rem; }
        .empty-worship-day { display:grid; min-height:155px; place-content:center; gap:10px; padding:16px; text-align:center; color:var(--muted); }
        @media(max-width:1050px){.worship-schedule-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
        @media(max-width:640px){.worship-schedule-grid{grid-template-columns:1fr;}}
    </style>

    <div class="page-header">
        <div><p class="eyebrow">Kehadiran Siswa</p><h1 class="page-title">Jadwal Kegiatan Ibadah</h1><p class="page-subtitle">Atur waktu pemindaian dan pelaksanaan kegiatan untuk setiap hari.</p></div>
        <div class="actions"><a href="{{ route('kegiatan-ibadah.index') }}" class="button button-muted">Daftar kegiatan</a><a href="{{ route('rekap-kegiatan-ibadah.index') }}" class="button button-muted">Rekap harian</a><a href="{{ route('scan-kegiatan-ibadah.index') }}" target="_blank" rel="noopener" class="button button-dark">Buka scanner</a>@if($kegiatanIbadahId)<a href="{{ route('jadwal-kegiatan-ibadah.create',['tahun_pelajaran_id'=>$tahunPelajaranId,'kegiatan_ibadah_id'=>$kegiatanIbadahId]) }}" class="button button-primary">Atur jadwal</a>@endif</div>
    </div>

    @if(session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif

    <form action="{{ route('jadwal-kegiatan-ibadah.index') }}" method="GET" class="panel panel-pad" style="margin-bottom:18px;">
        <div class="filter-grid">
            <div class="field"><label for="tahun_pelajaran_id">Tahun pelajaran</label><select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select" onchange="this.form.submit()">@foreach($tahunPelajaran as $item)<option value="{{ $item->id }}" @selected((int)$tahunPelajaranId===(int)$item->id)>{{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}</option>@endforeach</select></div>
            <div class="field"><label for="kegiatan_ibadah_id">Kegiatan ibadah</label><select id="kegiatan_ibadah_id" name="kegiatan_ibadah_id" class="select" onchange="this.form.submit()">@foreach($kegiatanIbadah as $item)<option value="{{ $item->id }}" @selected((int)$kegiatanIbadahId===(int)$item->id)>{{ $item->nama }}{{ $item->aktif ? '' : ' - nonaktif' }}</option>@endforeach</select></div>
        </div>
    </form>

    <div class="stats-grid">
        <div class="panel stat"><p class="stat-label">Kegiatan</p><p class="stat-value" style="font-size:1.15rem;line-height:1.25;">{{ $kegiatanDipilih?->nama ?? '-' }}</p></div>
        <div class="panel stat active"><p class="stat-label">Hari aktif</p><p class="stat-value">{{ $jumlahAktif }} / 6</p></div>
        <div class="panel stat inactive"><p class="stat-label">Tahun pelajaran</p><p class="stat-value" style="font-size:1.25rem;">{{ $tahunPelajaran->firstWhere('id',$tahunPelajaranId)?->nama ?? '-' }}</p></div>
    </div>

    <section class="worship-schedule-grid">
        @foreach($daftarHari as $kodeHari=>$informasiHari)
            @php $jadwal=$jadwalPerHari->get($kodeHari); @endphp
            <article class="worship-day {{ $jadwal && !$jadwal->aktif ? 'inactive' : '' }}">
                <header class="worship-day-head"><h2>{{ $informasiHari['label'] }}</h2>@if($jadwal)<span class="badge {{ $jadwal->aktif ? 'badge-active' : 'badge-inactive' }}">{{ $jadwal->aktif ? 'Aktif' : 'Nonaktif' }}</span>@else<span class="badge badge-muted">Belum diatur</span>@endif</header>
                @if($jadwal)
                    <div class="worship-day-body"><div class="worship-time"><div><span>Waktu pelaksanaan</span><strong>{{ $jadwal->formatJam($jadwal->jam_pelaksanaan) }}</strong></div><div><span>Waktu scan</span><strong>{{ $jadwal->rentangScan() }}</strong></div></div><p class="worship-note">{{ $jadwal->keterangan ?: 'Tidak ada keterangan tambahan.' }}</p><div class="actions" style="margin-top:13px;"><a href="{{ route('jadwal-kegiatan-ibadah.edit',$jadwal) }}" class="button button-dark button-sm">Edit</a>@if($jadwal->aktif)<form action="{{ route('jadwal-kegiatan-ibadah.destroy',$jadwal) }}" method="POST" onsubmit="return confirm('Nonaktifkan jadwal hari {{ $informasiHari['label'] }}?')">@csrf @method('DELETE')<button class="button button-muted button-sm" type="submit">Nonaktifkan</button></form>@endif</div></div>
                @else
                    <div class="empty-worship-day"><span>Belum ada jadwal.</span><a href="{{ route('jadwal-kegiatan-ibadah.create',['tahun_pelajaran_id'=>$tahunPelajaranId,'kegiatan_ibadah_id'=>$kegiatanIbadahId,'hari'=>$kodeHari]) }}" class="button button-muted button-sm">Atur hari ini</a></div>
                @endif
            </article>
        @endforeach
    </section>
@endsection
