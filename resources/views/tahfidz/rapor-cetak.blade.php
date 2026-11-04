@extends('tahfidz._dokumen')
@section('title', 'Rapor Tahfidz - '.$dokumen['nama'])

@section('toolbar')
    @if (!empty($aksiPublikasi))
        <form method="POST" action="{{ $aksiPublikasi }}">
            @csrf
            <input type="hidden" name="sidik" value="{{ $sidik }}">
            <button type="submit" name="bagikan" value="1"><i data-lucide="send" aria-hidden="true"></i> {{ $terbit ? 'Bagikan ulang rapor' : 'Bagikan rapor ke orang tua' }}</button>
            @if ($terbit)<button type="submit" name="bagikan" value="0" class="tf-print-secondary">Tarik publikasi</button>@endif
        </form>
        <span class="tf-print-pub-status">{{ $terbit ? 'Dibagikan: versi '.$terbit->versi : 'Belum dibagikan' }}</span>
        @if (session('berhasil'))<p class="tf-print-feedback" role="status">{{ session('berhasil') }}</p>@endif
        @if ($errors->any())<p class="tf-print-feedback tf-print-error" role="alert">{{ $errors->first() }}</p>@endif
    @endif
@endsection

@section('document')
    @include('tahfidz._rapor-lembar')
@endsection
