@extends('tahfidz._dokumen')
@section('title', 'Cetak Rapor Tahfidz - '.$kelompok->nama)

@section('toolbar')
    <span class="tf-print-pub-status">{{ $kelompok->nama }} &middot; {{ $dokumenList->count() }} rapor</span>
@endsection

@section('document')
    @foreach ($dokumenList as $dokumen)
        @include('tahfidz._rapor-lembar')
    @endforeach
@endsection
