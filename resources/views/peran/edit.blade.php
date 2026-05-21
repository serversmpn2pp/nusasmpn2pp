@extends('layouts.app')

@section('title', 'Edit Peran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Hak akses</p>
            <h1 class="page-title">Edit peran</h1>
        </div>

        <a href="{{ route('peran.index') }}" class="button button-muted">Kembali</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Data belum bisa disimpan.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('peran.update', $peran) }}" method="POST" class="panel panel-pad">
        @method('PUT')
        @include('peran._form')
    </form>
@endsection
