@extends('layouts.app')

@section('title', 'Detail ' . $judul . ' - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Detail {{ $judulSingular }}</h1>
        </div>

        <div class="actions">
            <a href="{{ route($routePrefix . '.index') }}" class="button button-muted">Kembali</a>
            @izin('barang.kelola')
                <a href="{{ route($routePrefix . '.edit', $item) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">{{ $inisial }}</div>
                <h2>{{ $item->nama }}</h2>
                <p>{{ $item->kode }}</p>

                <div style="margin-top: 16px;">
                    @if ($item->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @izin('barang.kelola')
                @if ($item->aktif)
                    <form action="{{ route($routePrefix . '.destroy', $item) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan {{ $judulSingular }} ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi {{ $judul }}</h2>

                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Nama</dt>
                        <dd>{{ $item->nama }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kode</dt>
                        <dd>{{ $item->kode }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>{{ $labelJumlahTerhubung }}</dt>
                        <dd>{{ $jumlahTerhubung }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Status</dt>
                        <dd>{{ $item->aktif ? 'Aktif' : 'Nonaktif' }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Deskripsi</dt>
                        <dd style="white-space: pre-line;">{{ $item->deskripsi ?: '-' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Penggunaan</h2>
                <p class="help-text" style="margin-top: 8px;">{{ $teksPenggunaan }}</p>
            </section>
        </div>
    </div>
@endsection
