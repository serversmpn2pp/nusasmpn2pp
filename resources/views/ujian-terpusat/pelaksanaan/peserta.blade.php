@extends('layouts.app')

@section('title', 'Pembagian Peserta Tingkat '.$kelompok->tingkat.' - NUSA')

@section('content')
    <style>
        .placement-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:20px; }
        .placement-stat { padding:15px; border:1px solid var(--line); border-radius:7px; background:#fff; }
        .placement-stat span,.placement-stat strong { display:block; }
        .placement-stat span { color:var(--muted); font-size:.75rem; font-weight:700; }
        .placement-stat strong { margin-top:5px; color:var(--primary-dark); font-size:1.15rem; }
        .room-placement { margin-top:18px; }
        .room-placement-head { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; padding-bottom:14px; border-bottom:1px solid var(--line); }
        .room-placement-head h2 { margin:0; font-size:1rem; }
        .room-placement-head p { margin:4px 0 0; color:var(--muted); font-size:.78rem; }
        .participant-name { color:var(--primary-dark); font-weight:800; }
        @media(max-width:720px){
            .placement-summary{grid-template-columns:repeat(2,minmax(0,1fr));}
            .room-placement-head{display:grid;}
            .participant-table thead{display:none;}
            .participant-table,.participant-table tbody,.participant-table tr,.participant-table td{display:block;width:100%;}
            .participant-table tr{padding:10px 0;border-bottom:1px solid var(--line);}
            .participant-table tr:last-child{border-bottom:0;}
            .participant-table td{display:grid;grid-template-columns:92px minmax(0,1fr);gap:10px;padding:3px 0;border:0;overflow-wrap:anywhere;}
            .participant-table td::before{content:attr(data-label);color:var(--muted);font-size:.7rem;font-weight:800;}
        }
    </style>

    <div class="page-header">
        <div><p class="eyebrow">Ujian Terpusat</p><h1 class="page-title">Pembagian peserta tingkat {{ $kelompok->tingkat }}</h1><p class="page-subtitle">{{ $kegiatan->nama }}</p></div>
        <div class="actions"><a href="{{ route('ujian-terpusat.pelaksanaan.index', $kegiatan) }}" class="button button-primary">Kembali ke jadwal & peserta</a></div>
    </div>

    <div class="placement-summary">
        <div class="placement-stat"><span>Sesi</span><strong>{{ $kelompok->sesiKegiatanUjianCbt?->nama }}</strong></div>
        <div class="placement-stat"><span>Kelas</span><strong>{{ $kelompok->kelas->count() }}</strong></div>
        <div class="placement-stat"><span>Peserta</span><strong>{{ $kelompok->jumlah_peserta }}</strong></div>
        <div class="placement-stat"><span>Kapasitas</span><strong>{{ $kelompok->total_kapasitas }}</strong></div>
    </div>

    @foreach ($kelompok->ruangKegiatanUjianCbt as $ruang)
        @php($daftar=$penempatanPerRuang->get($ruang->id,collect()))
        <section class="panel panel-pad room-placement">
            <div class="room-placement-head"><div><h2>{{ $ruang->nama }}</h2><p>{{ $ruang->lokasi ?: 'Lokasi belum diisi' }} · {{ $daftar->count() }} dari {{ $ruang->kapasitas }} kursi terisi</p></div><span class="badge badge-active">{{ $ruang->kode }}</span></div>
            <div class="table-wrap" style="margin-top:14px;">
                <table class="participant-table"><thead><tr><th>Meja</th><th>Nomor peserta</th><th>Nama siswa</th><th>Kelas</th><th>NISN</th></tr></thead>
                    <tbody>@forelse($daftar as $penempatan)<tr><td data-label="Meja">{{ $penempatan->nomor_meja }}</td><td data-label="Nomor peserta">{{ $penempatan->nomor_peserta }}</td><td data-label="Nama" class="participant-name">{{ $penempatan->anggotaKelas?->siswa?->nama_lengkap }}</td><td data-label="Kelas">{{ $penempatan->anggotaKelas?->kelas?->nama }}</td><td data-label="NISN">{{ $penempatan->anggotaKelas?->siswa?->nisn ?: '-' }}</td></tr>@empty<tr><td colspan="5">Ruang ini belum terisi.</td></tr>@endforelse</tbody>
                </table>
            </div>
        </section>
    @endforeach
@endsection
