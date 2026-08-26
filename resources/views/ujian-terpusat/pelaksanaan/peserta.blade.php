@extends('layouts.app')

@section('title', 'Pembagian Peserta Tingkat '.$kelompok->tingkat.' - NUSA')

@section('content')
    <style>
        .placement-intro { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:18px; align-items:center; margin-bottom:18px; padding:20px 22px; overflow:hidden; background:var(--primary); color:#fff; }
        .placement-intro h2 { margin:3px 0 0; color:#fff; font-size:1.3rem; }
        .placement-intro p { margin:6px 0 0; color:rgba(255,255,255,.8); }
        .placement-intro-level { display:grid; min-width:92px; min-height:72px; place-items:center; border:1px solid rgba(255,255,255,.22); border-radius:7px; background:rgba(255,255,255,.1); color:#fff; font-size:1.35rem; font-weight:900; }
        .placement-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:22px; }
        .placement-stat { min-width:0; padding:15px 16px; border:1px solid var(--line); border-top:3px solid var(--primary); border-radius:7px; background:#fff; box-shadow:0 8px 20px rgba(21,71,122,.05); }
        .placement-stat span,.placement-stat strong { display:block; }
        .placement-stat span { color:var(--muted); font-size:.75rem; font-weight:700; }
        .placement-stat strong { margin-top:5px; overflow-wrap:anywhere; color:var(--primary-dark); font-size:1.15rem; }
        .room-placement { margin-top:18px; overflow:hidden; padding:0; }
        .room-placement-head { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:18px; align-items:start; padding:18px 20px 15px; border-bottom:1px solid var(--line); background:#f8fafc; }
        .room-placement-title { display:flex; flex-wrap:wrap; align-items:center; gap:9px; }
        .room-placement-title h2 { margin:0; font-size:1.02rem; }
        .room-placement-head p { margin:5px 0 0; color:var(--muted); font-size:.78rem; }
        .room-capacity { min-width:180px; }
        .room-capacity-copy { display:flex; justify-content:space-between; gap:10px; margin-bottom:7px; color:var(--muted); font-size:.7rem; font-weight:800; }
        .room-capacity-track { height:7px; overflow:hidden; border-radius:4px; background:#dfe9f3; }
        .room-capacity-fill { height:100%; border-radius:inherit; background:var(--accent); }
        .room-placement-tools { display:grid; min-width:200px; gap:10px; }
        .room-placement-tools .button { justify-self:stretch; text-align:center; }
        .participant-table { width:100%; table-layout:fixed; border-collapse:collapse; }
        .participant-table th { padding:11px 14px; border-bottom:1px solid var(--line); background:var(--primary-soft); color:var(--primary-dark); font-size:.7rem; text-align:left; text-transform:uppercase; }
        .participant-table td { padding:12px 14px; border-bottom:1px solid var(--line); color:var(--text); font-size:.8rem; vertical-align:middle; }
        .participant-table tbody tr:last-child td { border-bottom:0; }
        .participant-table tbody tr:hover { background:#fbfdff; }
        .participant-table th:nth-child(1),.participant-table td:nth-child(1) { width:72px; text-align:center; }
        .participant-table th:nth-child(2),.participant-table td:nth-child(2) { width:235px; }
        .participant-table th:nth-child(4),.participant-table td:nth-child(4) { width:100px; }
        .participant-table th:nth-child(5),.participant-table td:nth-child(5) { width:135px; }
        .seat-number { display:inline-grid; width:34px; height:30px; place-items:center; border-radius:6px; background:var(--accent-soft); color:var(--accent-text); font-weight:900; }
        .participant-number { color:var(--primary-dark); font-size:.76rem; font-weight:850; }
        .participant-name { color:var(--primary-dark); font-weight:800; }
        .participant-empty { padding:28px!important; color:var(--muted)!important; text-align:center!important; }
        @media(max-width:760px){
            .placement-intro { grid-template-columns:1fr; }
            .placement-intro-level { min-width:0; min-height:48px; justify-self:stretch; }
            .placement-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .room-placement-head { grid-template-columns:1fr; }
            .room-capacity,.room-placement-tools { min-width:0; }
            .participant-table thead { display:none; }
            .participant-table,.participant-table tbody,.participant-table tr,.participant-table td { display:block; width:100%; }
            .participant-table tr { padding:12px 14px; border-bottom:1px solid var(--line); }
            .participant-table tr:last-child { border-bottom:0; }
            .participant-table td { display:grid; grid-template-columns:108px minmax(0,1fr); gap:10px; padding:4px 0; border:0; overflow-wrap:anywhere; text-align:left!important; }
            .participant-table td::before { content:attr(data-label); color:var(--muted); font-size:.7rem; font-weight:800; }
            .participant-table td:nth-child(n) { width:100%; }
            .seat-number { width:auto; height:auto; justify-self:start; padding:4px 9px; }
        }
    </style>

    <div class="page-header">
        <div><p class="eyebrow">Ujian Terpusat · Tahap 6</p><h1 class="page-title">Daftar peserta ujian</h1><p class="page-subtitle">Susunan siswa tingkat {{ $kelompok->tingkat }} berdasarkan ruang dan nomor meja.</p></div>
        <div class="actions"><a href="{{ route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 6]) }}" class="button button-muted">Kembali</a></div>
    </div>

    <section class="panel placement-intro">
        <div><p class="eyebrow" style="color:var(--accent);">{{ $kegiatan->jenisUjianCbt?->nama ?: 'Ujian Terpusat' }}</p><h2>{{ $kegiatan->nama }}</h2><p>{{ $kegiatan->tahunPelajaran?->nama }} · {{ $kelompok->sesiKegiatanUjianCbt?->nama }} {{ $kelompok->sesiKegiatanUjianCbt?->labelWaktu() }}</p></div>
        <div class="placement-intro-level">T{{ $kelompok->tingkat }}</div>
    </section>

    <div class="placement-summary">
        <div class="placement-stat"><span>Sesi ujian</span><strong>{{ $kelompok->sesiKegiatanUjianCbt?->nama ?: '-' }}</strong></div>
        <div class="placement-stat"><span>Kelas peserta</span><strong>{{ $kelompok->kelas->count() }} kelas</strong></div>
        <div class="placement-stat"><span>Jumlah peserta</span><strong>{{ $kelompok->jumlah_peserta }} siswa</strong></div>
        <div class="placement-stat"><span>Kapasitas tersedia</span><strong>{{ $kelompok->total_kapasitas }} kursi</strong></div>
    </div>

    @foreach ($kelompok->ruangKegiatanUjianCbt as $ruang)
        @php
            $daftar = $penempatanPerRuang->get($ruang->id, collect());
            $persentaseTerisi = $ruang->kapasitas > 0 ? min(100, (int) round(($daftar->count() / $ruang->kapasitas) * 100)) : 0;
        @endphp
        <section class="panel room-placement">
            <div class="room-placement-head">
                <div>
                    <div class="room-placement-title"><h2>{{ $ruang->nama }}</h2><span class="badge badge-active">{{ $ruang->kode }}</span></div>
                    <p>{{ $ruang->lokasi ?: 'Lokasi belum diisi' }} · meja diurutkan otomatis oleh NUSA</p>
                </div>
                <div class="room-placement-tools">
                    <div class="room-capacity">
                        <div class="room-capacity-copy"><span>Keterisian ruang</span><strong>{{ $daftar->count() }}/{{ $ruang->kapasitas }} kursi</strong></div>
                        <div class="room-capacity-track" aria-label="Keterisian {{ $persentaseTerisi }} persen"><div class="room-capacity-fill" style="width:{{ $persentaseTerisi }}%"></div></div>
                    </div>
                    @if($daftar->isNotEmpty())
                        <a href="{{ route('ujian-terpusat.peserta.label-meja', [$kegiatan, $kelompok, $ruang]) }}" target="_blank" rel="noopener" class="button button-muted">Cetak label meja</a>
                    @endif
                </div>
            </div>
            <div class="table-wrap">
                <table class="participant-table">
                    <thead><tr><th>Meja</th><th>Kode meja</th><th>Nama siswa</th><th>Kelas</th><th>NISN</th></tr></thead>
                    <tbody>
                        @forelse($daftar as $penempatan)
                            <tr>
                                <td data-label="Meja"><span class="seat-number">{{ $penempatan->nomor_meja }}</span></td>
                                <td data-label="Kode meja"><span class="participant-number">{{ $penempatan->kode_meja }}</span></td>
                                <td data-label="Nama siswa" class="participant-name">{{ $penempatan->anggotaKelas?->siswa?->nama_lengkap }}</td>
                                <td data-label="Kelas">{{ $penempatan->anggotaKelas?->kelas?->nama }}</td>
                                <td data-label="NISN">{{ $penempatan->anggotaKelas?->siswa?->nisn ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="participant-empty">Ruang ini belum memiliki peserta.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
@endsection
