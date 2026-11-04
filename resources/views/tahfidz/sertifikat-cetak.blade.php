@extends('tahfidz._dokumen')
@section('title', 'Sertifikat Tahfidz - '.$lulus->nama_penerima)
@section('paper', 'A4 landscape')

@section('document')
    <article class="tf-print-sheet tf-certificate">
        @include('tahfidz._kop')
        <div>
            <div class="tf-certificate-title">
                <p class="tf-cert-event">{{ $lulus->wisuda->nama }}</p>
                <h1>Sertifikat Kelulusan Tahfidz</h1>
                <p>Nomor: {{ $lulus->nomor_sertifikat }}</p>
            </div>
            <p class="tf-cert-given">Diberikan kepada</p>
            <h2>{{ $lulus->nama_penerima }}</h2>
            <p class="tf-cert-result">Atas kelulusan Tahfidz Al-Qur'an Juz 30, setelah menyetorkan seluruh 37 surah dan dinyatakan lulus oleh guru pembimbing.</p>
        </div>
        <div>
            <p class="tf-cert-date">{{ $lulus->wisuda->lokasi }}, {{ $lulus->wisuda->tanggal->locale('id')->translatedFormat('j F Y') }}</p>
            <div class="tf-print-signatures">
                <div><p>Guru pembimbing</p><div class="tf-sign-space"></div><strong>{{ $lulus->nama_pembimbing }}</strong></div>
                <div><p>Kepala sekolah</p><div class="tf-sign-space"></div><strong>{{ $lulus->wisuda->kepala_sekolah }}</strong></div>
            </div>
        </div>
    </article>
@endsection
