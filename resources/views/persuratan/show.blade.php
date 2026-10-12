@extends('layouts.app')
@section('title', ($pribadi ? 'Penugasan Saya' : 'Detail Surat').' - NUSA')
@section('content')
    @include('persuratan._style')
    <div class="page-header"><div><p class="eyebrow">{{ $pribadi ? 'Surat & Penugasan Saya' : 'Tata Usaha / Persuratan & Arsip' }}</p><h1 class="page-title">{{ \App\Models\SuratTu::JENIS[$surat->jenis] }}</h1></div><div class="surat-actions"><a class="button button-muted" href="{{ route($pribadi ? 'surat-saya.index' : 'persuratan.index') }}">Kembali</a>@if(!$pribadi) @izin('surat.kelola') @if($surat->dapatDiedit())<a class="button button-primary" href="{{ route('persuratan.edit', $surat) }}">Perbarui surat</a>@elseif($surat->disahkan_pada && !$revisi->whereNotNull('disahkan_pada')->count())<a class="button button-primary" href="{{ route('persuratan.create', ['revisi_dari_id' => $surat->id]) }}">Buat revisi</a>@endif @endizin @endif</div></div>
    @include('persuratan._messages')
    <header class="surat-detail-head"><div><h2>{{ $surat->nomor_surat }}</h2><span class="surat-muted">{{ $surat->perihal }}</span></div><span class="surat-status surat-status--{{ $surat->status }}">{{ \App\Models\SuratTu::STATUS[$surat->status] }}</span></header>
    @if($surat->catatan_status)<div class="surat-notice"><strong>Catatan keputusan</strong><p class="surat-text">{{ $surat->catatan_status }}</p></div>@endif
    @if($asal)<p class="surat-muted">Revisi dari <a href="{{ route('persuratan.show', $asal) }}">{{ $asal->nomor_surat }}</a></p>@endif
    @foreach($revisi as $item)<p class="surat-muted">Surat revisi: <a href="{{ route('persuratan.show', $item) }}">{{ $item->nomor_surat }}</a> &middot; {{ \App\Models\SuratTu::STATUS[$item->status] }}</p>@endforeach
    <section class="surat-section"><h2>Informasi surat</h2><dl class="surat-data">
        <div><dt>Tanggal surat</dt><dd>{{ $surat->tanggal_surat->locale('id')->translatedFormat('d F Y') }}</dd></div>
        <div><dt>{{ $surat->jenis === 'masuk' ? 'Pengirim' : 'Penerima / tujuan' }}</dt><dd>{{ $surat->pihak }}</dd></div>
        <div><dt>Sifat</dt><dd>{{ \App\Models\SuratTu::SIFAT[$surat->sifat] }}</dd></div>
        @if($surat->jenis === 'masuk')<div><dt>Nomor agenda sekolah</dt><dd>{{ $surat->nomor_agenda }}</dd></div><div><dt>Tanggal diterima</dt><dd>{{ $surat->tanggal_diterima?->format('d-m-Y') }}</dd></div>
        @else<div><dt>Pejabat pengesah</dt><dd>{{ $surat->pengesah?->nama ?? '-' }}</dd></div><div><dt>Disahkan pada</dt><dd>{{ $surat->disahkan_pada?->format('d-m-Y H:i') ?? '-' }}</dd></div><div><dt>Pengiriman dicatat</dt><dd>{{ $surat->dikirim_pada?->format('d-m-Y H:i') ?? '-' }}</dd></div>@endif
    </dl>@if($surat->keterangan)<p class="surat-text" style="margin-top:20px">{{ $surat->keterangan }}</p>@endif</section>
    @if($surat->jenis === 'tugas')
        <section class="surat-section"><h2>Pelaksanaan tugas</h2><dl class="surat-data"><div><dt>Mulai</dt><dd>{{ $surat->mulai_tugas?->format('d-m-Y') }}</dd></div><div><dt>Selesai</dt><dd>{{ $surat->selesai_tugas?->format('d-m-Y') }}</dd></div><div><dt>Tempat</dt><dd>{{ $surat->tempat_tugas }}</dd></div></dl><p class="surat-text" style="margin-top:20px">{{ $surat->uraian_tugas }}</p>
            @if($pribadi)
                <div class="surat-actions" style="margin-top:18px">@if($surat->penugasan->first()?->dibaca_pada)<span class="surat-status surat-status--disahkan">Sudah dibaca {{ $surat->penugasan->first()->dibaca_pada->format('d-m-Y H:i') }}</span>@elseif(in_array($surat->status,['disahkan','dikirim']))<form method="POST" action="{{ route('surat-saya.baca', $surat) }}">@csrf<button class="button button-primary">Tandai sudah membaca</button></form>@endif</div>
            @else<div class="surat-shell" style="margin-top:20px"><table class="surat-table"><thead><tr><th>Guru / pegawai</th><th>Konfirmasi membaca</th></tr></thead><tbody>@foreach($surat->penugasan as $tugas)<tr><td>{{ $tugas->nama_pegawai }}</td><td>{{ $tugas->dibaca_pada?->format('d-m-Y H:i') ?? 'Belum dibaca' }}</td></tr>@endforeach</tbody></table></div>@endif
        </section>
    @endif
    @if(!$pribadi && $surat->jenis === 'masuk')
        @include('persuratan.tindak-lanjut._disposisi')
    @elseif($surat->jenis === 'tugas' && $surat->tindakLanjut->isNotEmpty())
        <section class="surat-section"><h2>Laporan pelaksanaan</h2><ul class="surat-file-list">@foreach($surat->tindakLanjut as $t)<li><span>{{ $t->nama_pegawai }}<small class="surat-muted">{{ \App\Models\TindakLanjutSuratTu::STATUS[$t->status] }}</small></span><a class="button button-muted button-sm" href="{{ route('tindak-lanjut-surat.show',$t) }}">Lihat tindak lanjut</a></li>@endforeach</ul></section>
    @endif
    <section class="surat-section"><h2>Berkas & lampiran</h2><div class="surat-shell"><table class="surat-table surat-table--berkas"><thead><tr><th>Berkas</th><th>Jenis</th><th>Ukuran</th><th></th></tr></thead><tbody>@foreach($surat->berkas->where('aktif',true) as $file)<tr><td>{{ $file->nama_file_asli }}<small class="surat-mobile-meta">{{ $file->jenis === 'utama' ? 'Surat utama' : 'Lampiran' }} &middot; {{ number_format($file->ukuran_file / 1024 / 1024, 2, ',', '.') }} MB</small></td><td>{{ $file->jenis === 'utama' ? 'Surat utama' : 'Lampiran' }}</td><td>{{ number_format($file->ukuran_file / 1024 / 1024, 2, ',', '.') }} MB</td><td><a class="button button-muted button-sm" href="{{ route($pribadi ? 'surat-saya.berkas' : 'persuratan.berkas', [$surat, $file]) }}">Unduh</a></td></tr>@endforeach</tbody></table></div>
        @if(!$pribadi && $surat->berkas->where('aktif',false)->isNotEmpty())<details style="margin-top:16px"><summary>Riwayat berkas terdahulu</summary><ul>@foreach($surat->berkas->where('aktif',false) as $file)<li><a href="{{ route('persuratan.berkas', [$surat, $file]) }}">{{ $file->nama_file_asli }}</a> &middot; {{ $file->created_at->format('d-m-Y H:i') }}</li>@endforeach</ul></details>@endif
    </section>
    @if(!$pribadi)
        @php($akun = auth()->user())
        @php($pemeriksa = $akun->memilikiIzin('surat.sahkan') && $surat->pengesah_pengguna_id === $akun->id)
        <section class="surat-section"><h2>Keputusan & pengiriman</h2>
            @if($akun->memilikiIzin('surat.kelola') && $surat->jenis !== 'masuk' && in_array($surat->status, ['draf','dikembalikan']))
                @include('persuratan._tindakan', ['aksi'=>'ajukan','label'=>'Ajukan pengesahan','alasan'=>false])
            @endif
            @if($pemeriksa && $surat->status === 'diajukan')
                @include('persuratan._tindakan', ['aksi'=>'sahkan','label'=>'Sahkan surat','alasan'=>false])
                @include('persuratan._tindakan', ['aksi'=>'kembalikan','label'=>'Kembalikan untuk perbaikan','alasan'=>true])
            @endif
            @if($akun->memilikiIzin('surat.kelola') && $surat->status === 'diajukan')
                @include('persuratan._tindakan', ['aksi'=>'tarik','label'=>'Tarik pengajuan','alasan'=>true])
            @endif
            @if($akun->memilikiIzin('surat.kelola') && $surat->status === 'disahkan')
                @include('persuratan._tindakan', ['aksi'=>'kirim','label'=>'Catat pengiriman','alasan'=>true])
            @endif
            @if($surat->status !== 'dibatalkan' && ($surat->disahkan_pada ? ($pemeriksa || $akun->administrator()) : $akun->memilikiIzin('surat.kelola')))
                @include('persuratan._tindakan', ['aksi'=>'batal','label'=>'Batalkan surat','alasan'=>true])
            @endif
            @if(in_array($surat->status,['disahkan','dikirim']))<p class="surat-muted">Surat telah disahkan dan dikunci.</p>@endif
        </section>
        <section class="surat-section"><h2>Riwayat surat</h2><ul class="surat-timeline">@foreach($surat->riwayat as $log)<li><strong>{{ ['dicatat'=>'Surat dicatat','diperbarui'=>'Surat diperbarui','ajukan'=>'Diajukan untuk pengesahan','sahkan'=>'Surat disahkan','kembalikan'=>'Dikembalikan untuk perbaikan','tarik'=>'Pengajuan ditarik','kirim'=>'Pengiriman dicatat','batal'=>'Surat dibatalkan','dibaca'=>'Surat dibaca','digantikan'=>'Digantikan oleh revisi','disposisi'=>'Disposisi diberikan'][$log->aksi] ?? $log->aksi }}</strong><div class="surat-muted">{{ $log->created_at->format('d-m-Y H:i') }} &middot; {{ $log->pengguna?->nama }} &middot; Versi {{ $log->versi }}</div>@if($log->catatan)<p>{{ $log->catatan }}</p>@endif</li>@endforeach</ul></section>
    @endif
    @include('persuratan.tindak-lanjut._scripts')
@endsection
