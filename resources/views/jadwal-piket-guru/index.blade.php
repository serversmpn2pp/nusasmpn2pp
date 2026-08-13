@extends('layouts.app')

@section('title', 'Jadwal Guru Piket - NUSA')

@section('content')
    <style>
        .picket-day-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
        .picket-day { border:1px solid var(--line); border-top:4px solid var(--primary); border-radius:8px; background:#fff; box-shadow:var(--shadow); }
        .picket-day-head { display:flex; align-items:center; justify-content:space-between; gap:12px; border-bottom:1px solid var(--line); padding:15px 17px; }
        .picket-day-head h2 { margin:0; font-size:1rem; font-weight:900; }
        .picket-list { display:grid; gap:0; }
        .picket-person { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:12px; padding:14px 17px; }
        .picket-person + .picket-person { border-top:1px solid var(--line); }
        .picket-person-name { margin:0; color:var(--primary-dark); font-weight:900; }
        .picket-person-meta { margin:4px 0 0; color:var(--muted); font-size:.82rem; }
        @media (max-width:980px) { .picket-day-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:640px) { .picket-day-grid { grid-template-columns:1fr; } .picket-person { align-items:start; } }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kehadiran Siswa</p>
            <h1 class="page-title">Jadwal Guru Piket</h1>
            <p class="page-subtitle">Atur guru mata pelajaran yang memeriksa kehadiran siswa setiap hari.</p>
        </div>
        <a href="{{ route('jadwal-piket-guru.create', ['tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-primary">Tambah guru piket</a>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('jadwal-piket-guru.index') }}" method="GET" class="panel panel-pad" style="margin-bottom:18px;">
        <div class="field" style="max-width:420px;">
            <label for="tahun_pelajaran_id">Tahun pelajaran</label>
            <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select" onchange="this.form.submit()">
                @foreach ($tahunPelajaran as $item)
                    <option value="{{ $item->id }}" @selected((int) $tahunPelajaranId === (int) $item->id)>
                        {{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="stats-grid">
        <div class="panel stat"><p class="stat-label">Penugasan aktif</p><p class="stat-value">{{ $jumlahJadwalAktif }}</p></div>
        <div class="panel stat active"><p class="stat-label">Guru terjadwal</p><p class="stat-value">{{ $jumlahGuru }}</p></div>
        <div class="panel stat inactive"><p class="stat-label">Hari terisi</p><p class="stat-value">{{ $jumlahHariTerisi }} / 6</p></div>
    </div>

    <section class="picket-day-grid">
        @foreach ($daftarHari as $kodeHari => $labelHari)
            @php $jadwalHari = $jadwalPerHari->get($kodeHari, collect()); @endphp
            <article class="picket-day">
                <header class="picket-day-head">
                    <h2>{{ $labelHari }}</h2>
                    <span class="badge {{ $jadwalHari->where('aktif', true)->isNotEmpty() ? 'badge-active' : 'badge-muted' }}">
                        {{ $jadwalHari->where('aktif', true)->count() }} guru
                    </span>
                </header>
                <div class="picket-list">
                    @forelse ($jadwalHari as $jadwal)
                        <div class="picket-person">
                            <div>
                                <p class="picket-person-name">{{ $jadwal->pegawai?->nama_lengkap ?? '-' }}</p>
                                <p class="picket-person-meta">
                                    {{ $jadwal->pegawai?->nip ?: 'NIP belum diisi' }}
                                    @if ($jadwal->keterangan) &middot; {{ $jadwal->keterangan }} @endif
                                </p>
                                @unless ($jadwal->aktif)<span class="badge badge-inactive" style="margin-top:7px;">Nonaktif</span>@endunless
                            </div>
                            <div class="actions">
                                <a href="{{ route('jadwal-piket-guru.edit', $jadwal) }}" class="button button-muted button-sm">Edit</a>
                                <form action="{{ route('jadwal-piket-guru.destroy', $jadwal) }}" method="POST" onsubmit="return confirm('Keluarkan guru dari jadwal piket?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button button-danger button-sm">Hapus</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="empty-state">Belum ada guru piket.</p>
                    @endforelse
                </div>
            </article>
        @endforeach
    </section>
@endsection
