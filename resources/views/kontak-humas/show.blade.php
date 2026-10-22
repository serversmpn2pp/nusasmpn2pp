@extends('layouts.app')
@section('admin-module', 'humas')
@section('title', 'Rincian Kontak Humas - NUSA')
@section('content')
@include('humas._operasional-style')
<div class="page-header"><div><p class="eyebrow">Humas / Direktori Kontak</p><h1 class="page-title">{{ $kontak['nama'] }}</h1></div><div class="actions"><a class="button button-muted" href="{{ route('kontak-humas.index') }}">Kembali</a>@izin('kontak_humas.kelola')<a class="button button-primary" href="{{ route('kontak-humas.edit',$kontak['id']) }}">Edit kontak</a>@endizin</div></div>
@include('agenda-humas._messages')
<section class="ho-band ho-band--blue"><h2><i data-lucide="contact-round" aria-hidden="true"></i>Identitas</h2><dl class="quick-facts">@foreach(['instansi'=>'Instansi','jabatan'=>'Jabatan','urusan_label'=>'Bidang urusan','status'=>'Status'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ $kontak[$key] ?: '-' }}</dd></div>@endforeach</dl></section>
<section class="ho-band"><h2><i data-lucide="shield-check" aria-hidden="true"></i>Kontak</h2>@if($kontak['kontak_terbatas'])<p>Nomor telepon dan email dibatasi untuk petugas berizin.</p>@else<dl class="quick-facts">@foreach(['nomor_kontak'=>'Telepon','email'=>'Email','dasar_penggunaan'=>'Dasar penggunaan','catatan'=>'Catatan internal'] as $key=>$label)<div><dt>{{ $label }}</dt><dd style="white-space:pre-line;overflow-wrap:anywhere">{{ $kontak[$key] ?: '-' }}</dd></div>@endforeach</dl>@endif</section>
@if($riwayat)<section class="ho-band ho-band--yellow"><h2><i data-lucide="history" aria-hidden="true"></i>Riwayat pengelolaan</h2><ul class="ho-audit">@foreach($riwayat as $item)<li><strong>{{ $item->aksi==='dibuat'?'Kontak dicatat':'Kontak diperbarui' }}</strong><div>{{ $item->alasan }}</div><small>{{ $item->created_at }}</small></li>@endforeach</ul>{{ $riwayat->links() }}</section>@endif
@endsection
