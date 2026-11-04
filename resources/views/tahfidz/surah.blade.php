@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Surah Juz 30 - NUSA')
@include('tahfidz._assets')

@section('content')
    <div class="tf-page">
        <div class="page-header"><div><p class="eyebrow">Tahfidz</p><h1 class="page-title">Surah Juz 30</h1></div></div>
        @include('tahfidz._nav')
        <section class="tf-surface" aria-labelledby="tf-surah">
            <header class="tf-band"><h2 id="tf-surah">Daftar surah</h2><span>{{ $surah->count() }} surah</span></header>
            <ol class="tf-list tf-surah-grid" start="78">
                @foreach ($surah as $item)
                    <li><span class="tf-number">{{ $item->nomor }}</span><span class="person-name">{{ $item->nama }}</span></li>
                @endforeach
            </ol>
        </section>
    </div>
@endsection
