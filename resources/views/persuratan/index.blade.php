@extends('layouts.app')
@section('title', ($pribadi ? 'Surat & Penugasan Saya' : 'Persuratan & Arsip').' - NUSA')
@section('content')
    @include('persuratan._style')
    <div class="page-header">
        <div><p class="eyebrow">{{ $pribadi ? 'Layanan Pegawai' : 'Tata Usaha' }}</p><h1 class="page-title">{{ $pribadi ? 'Surat & Penugasan Saya' : (($filter['pemeriksaan_saya'] ?? false) ? 'Pengesahan surat' : 'Arsip surat') }}</h1></div>
        @if(!$pribadi) @izin('surat.kelola')<a href="{{ route('persuratan.create') }}" class="button button-primary">Tambah surat</a>@endizin @endif
    </div>
    @include('persuratan._messages')
    <div class="surat-summary">
        @if(!$pribadi)<div><span>Draf / perlu perbaikan</span><strong>{{ ($jumlah['draf'] ?? 0) + ($jumlah['dikembalikan'] ?? 0) }}</strong></div><div><span>Menunggu pengesahan</span><strong>{{ $jumlah['diajukan'] ?? 0 }}</strong></div><div><span>Surat masuk tercatat</span><strong>{{ $jumlah['tercatat'] ?? 0 }}</strong></div>@endif
        <div><span>{{ $pribadi ? 'Penugasan aktif' : 'Disahkan / dikirim' }}</span><strong>{{ ($jumlah['disahkan'] ?? 0) + ($jumlah['dikirim'] ?? 0) }}</strong></div><div><span>Dibatalkan</span><strong>{{ $jumlah['dibatalkan'] ?? 0 }}</strong></div>
    </div>
    <form method="GET" action="{{ route($pribadi ? 'surat-saya.index' : 'persuratan.index') }}" class="surat-filter">
        <div><label for="kata_kunci">Cari surat</label><input class="input" id="kata_kunci" name="kata_kunci" value="{{ $filter['kata_kunci'] ?? '' }}" maxlength="120" placeholder="Nomor surat atau perihal"></div>
        @if(!$pribadi)<div><label for="jenis">Jenis surat</label><select class="select" id="jenis" name="jenis"><option value="">Semua jenis</option>@foreach(\App\Models\SuratTu::JENIS as $kode => $label)<option value="{{ $kode }}" @selected(($filter['jenis'] ?? '') === $kode)>{{ $label }}</option>@endforeach</select></div>@endif
        <div><label for="status">Status</label><select class="select" id="status" name="status"><option value="">Semua status</option>@foreach(\App\Models\SuratTu::STATUS as $kode => $label) @if(!$pribadi || in_array($kode, ['disahkan','dikirim','dibatalkan']))<option value="{{ $kode }}" @selected(($filter['status'] ?? '') === $kode)>{{ $label }}</option>@endif @endforeach</select></div>
        @if(!$pribadi)<div><label for="tahun">Tahun surat</label><input class="input" type="number" id="tahun" name="tahun" value="{{ $filter['tahun'] ?? '' }}" min="2000" max="2100" placeholder="Semua tahun"></div>@endif
        @if(!$pribadi)<div><label for="sifat">Sifat surat</label><select class="select" id="sifat" name="sifat"><option value="">Semua sifat</option>@foreach(\App\Models\SuratTu::SIFAT as $kode => $label) @if($kode !== 'rahasia' || auth()->user()->memilikiIzin('surat.rahasia'))<option value="{{ $kode }}" @selected(($filter['sifat'] ?? '') === $kode)>{{ $label }}</option>@endif @endforeach</select></div>@endif
        <div class="surat-filter-actions">
            @if($pribadi)<label><input type="checkbox" name="belum_dibaca" value="1" @checked($filter['belum_dibaca'] ?? false)> Belum dibaca</label>
            @else @izin('surat.sahkan')<label><input type="checkbox" name="pemeriksaan_saya" value="1" @checked($filter['pemeriksaan_saya'] ?? false)> Menunggu pengesahan saya</label>@endizin @endif
            <button class="button button-primary" type="submit">Tampilkan</button><a class="button button-muted" href="{{ route($pribadi ? 'surat-saya.index' : 'persuratan.index') }}">Reset</a>
            <span class="surat-muted">{{ $surat->total() }} surat</span>
        </div>
    </form>
    <div class="surat-shell">
        @if($surat->isEmpty())<div class="surat-empty">{{ $pribadi ? 'Belum ada surat tugas yang sesuai.' : 'Belum ada surat yang sesuai.' }}</div>
        @else
            <table class="surat-table surat-table--list"><thead><tr><th>Nomor & perihal</th><th>Tanggal</th><th>{{ $pribadi ? 'Pelaksanaan' : 'Pengirim / penerima' }}</th><th>Status</th><th>{{ $pribadi ? 'Konfirmasi' : 'Tindak lanjut' }}</th><th></th></tr></thead><tbody>
            @foreach($surat as $item)
                <tr><td><a href="{{ route($pribadi ? 'surat-saya.show' : 'persuratan.show', $item) }}">{{ $item->nomor_surat }}</a><small>{{ $item->perihal }}</small><small>{{ \App\Models\SuratTu::JENIS[$item->jenis] }} @if($item->nomor_agenda) &middot; Agenda {{ $item->nomor_agenda }} @endif @if($item->sifat !== 'biasa') &middot; {{ \App\Models\SuratTu::SIFAT[$item->sifat] }} @endif</small></td>
                <td data-label="Tanggal">{{ $item->tanggal_surat->format('d-m-Y') }} @if($item->tanggal_diterima)<small>Diterima {{ $item->tanggal_diterima->format('d-m-Y') }}</small>@endif</td>
                <td data-label="{{ $pribadi ? 'Pelaksanaan' : 'Pengirim / penerima' }}">@if($pribadi){{ $item->mulai_tugas?->format('d-m-Y') }} - {{ $item->selesai_tugas?->format('d-m-Y') }}<small>{{ $item->tempat_tugas }}</small>@else{{ $item->pihak }}@endif</td>
                <td data-label="Status"><span class="surat-status surat-status--{{ $item->status }}">{{ \App\Models\SuratTu::STATUS[$item->status] }}</span></td>
                <td data-label="{{ $pribadi ? 'Konfirmasi' : 'Tindak lanjut' }}">@if($pribadi){{ $item->penugasan->first()?->dibaca_pada ? 'Sudah dibaca' : 'Belum dibaca' }}@elseif($item->jenis === 'tugas'){{ $item->penugasan_count }} pegawai<small>{{ $item->dibaca_count }} sudah membaca</small><small>{{ $item->selesai_count }}/{{ $item->tindak_lanjut_count }} laporan selesai</small>@elseif($item->jenis === 'masuk'){{ $item->tindak_lanjut_count ? $item->tindak_lanjut_count.' penerima disposisi' : 'Belum didisposisikan' }}@if($item->tindak_lanjut_count)<small>{{ $item->selesai_count }}/{{ $item->tindak_lanjut_count }} tindak lanjut selesai</small>@endif @else<span class="surat-muted">-</span>@endif</td>
                <td><a class="button button-muted button-sm" href="{{ route($pribadi ? 'surat-saya.show' : 'persuratan.show', $item) }}">Buka</a></td></tr>
            @endforeach
            </tbody></table>
        @endif
    </div>
    <div class="surat-pagination">{{ $surat->links() }}</div>
@endsection
