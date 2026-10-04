@extends('layouts.app')
@section('title', 'Agenda & Pertemuan Humas - NUSA')
@section('content')
    @include('agenda-humas._style')
    <div class="page-header">
        <div><p class="eyebrow">Humas</p><h1 class="page-title">Agenda & Pertemuan</h1></div>
        @izin('agenda_humas.kelola')<a class="button button-primary" href="{{ route('agenda-humas.create') }}">Tambah agenda</a>@endizin
    </div>
    @include('agenda-humas._messages')
    <div class="agenda-metrics">
        <div class="agenda-metric"><span>Akan datang / berlangsung</span><strong>{{ $jumlahMendatang }}</strong></div>
        <div class="agenda-metric"><span>Pertemuan selesai</span><strong>{{ $jumlahSelesai }}</strong></div>
        <div class="agenda-metric agenda-metric--warning"><span>Jadwal lewat, belum ditutup</span><strong>{{ $jumlahLewat }}</strong></div>
        <div class="agenda-metric agenda-metric--warning"><span>Tindak lanjut terlambat</span><strong>{{ $jumlahTerlambat }}</strong></div>
    </div>
    <form method="GET" action="{{ route('agenda-humas.index') }}">
        <div class="agenda-filter">
            <div class="field"><label for="kata_kunci">Cari agenda</label><input class="input" id="kata_kunci" name="kata_kunci" value="{{ $filter['kata_kunci'] ?? '' }}" maxlength="120" placeholder="Nama agenda, tempat, atau peserta undangan"></div>
            <div class="field"><label for="jenis">Jenis pertemuan</label><select id="jenis" name="jenis" class="select"><option value="">Semua jenis</option>@foreach (\App\Models\AgendaHumas::JENIS as $kode => $nama)<option value="{{ $kode }}" @selected(($filter['jenis'] ?? '') === $kode)>{{ $nama }}</option>@endforeach</select></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status" class="select"><option value="">Semua status</option>@foreach (\App\Models\AgendaHumas::STATUS as $kode => $nama)<option value="{{ $kode }}" @selected(($filter['status'] ?? '') === $kode)>{{ $nama }}</option>@endforeach</select></div>
        </div>
        <div class="agenda-filter-bottom">
            <div class="field"><label for="dari">Mulai tanggal</label><input type="date" id="dari" name="dari" class="input" value="{{ $filter['dari'] ?? '' }}"></div>
            <div class="field"><label for="sampai">Sampai tanggal</label><input type="date" id="sampai" name="sampai" class="input" value="{{ $filter['sampai'] ?? '' }}"></div>
            <button class="button button-primary" type="submit">Tampilkan</button><a href="{{ route('agenda-humas.index') }}" class="button button-muted">Reset</a>
        </div>
    </form>
    <div class="agenda-table-shell">
        @if ($agenda->isEmpty())
            <div class="agenda-empty"><strong>Belum ada agenda yang sesuai.</strong> @izin('agenda_humas.kelola')<a href="{{ route('agenda-humas.create') }}">Tambah agenda</a>@endizin</div>
        @else
            <table class="agenda-table agenda-table--list">
                <thead><tr><th>Agenda</th><th>Waktu & tempat</th><th>Status</th><th>Kehadiran</th><th>Tindak lanjut</th><th></th></tr></thead>
                <tbody>@foreach ($agenda as $item)
                    <tr>
                        <td><a href="{{ route('agenda-humas.show', $item) }}">{{ $item->judul }}</a><small>{{ \App\Models\AgendaHumas::JENIS[$item->jenis] }}</small>@if ($item->sasaran)<small>{{ $item->sasaran }}</small>@endif</td>
                        <td data-label="Waktu">{{ $item->waktu_mulai->locale('id')->translatedFormat('d F Y') }}<small>{{ $item->waktu_mulai->format('H:i') }} - {{ $item->waktu_selesai->format($item->waktu_mulai->isSameDay($item->waktu_selesai) ? 'H:i' : 'd-m-Y H:i') }} WIB</small><small>{{ $item->tempat }}</small></td>
                        <td data-label="Status"><span class="agenda-badge agenda-badge--{{ $item->status }}">{{ \App\Models\AgendaHumas::STATUS[$item->status] }}</span>@if ($item->status === 'terjadwal')<small class="{{ $item->labelWaktu() === 'Jadwal lewat' ? 'agenda-warning' : '' }}">{{ $item->labelWaktu() }}</small>@endif</td>
                        <td data-label="Hadir">{{ $item->hadir_count }} / {{ $item->peserta_count }}</td>
                        <td data-label="Tindak lanjut">{{ $item->tertunda_count }} belum selesai</td>
                        <td><a class="button button-muted button-sm" href="{{ route('agenda-humas.show', $item) }}">Buka agenda</a></td>
                    </tr>
                @endforeach</tbody>
            </table>
        @endif
    </div>
    <div style="margin-top:18px">{{ $agenda->links() }}</div>
@endsection
