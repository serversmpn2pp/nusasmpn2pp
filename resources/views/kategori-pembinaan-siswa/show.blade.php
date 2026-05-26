@extends('layouts.app')

@section('title', 'Detail Kategori Pembinaan - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Pembinaan</p>
            <h1 class="page-title">Detail kategori pembinaan</h1>
        </div>

        <div class="actions">
            <a href="{{ route('kategori-pembinaan-siswa.index') }}" class="button button-muted">Kembali</a>
            <a href="{{ route('kategori-pembinaan-siswa.edit', $kategoriPembinaanSiswa) }}" class="button button-dark">Edit</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">KB</div>
                <h2>{{ $kategoriPembinaanSiswa->nama }}</h2>
                <p>{{ $kategoriPembinaanSiswa->kode }}</p>

                <div style="margin-top: 16px;">
                    @if ($kategoriPembinaanSiswa->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @if ($kategoriPembinaanSiswa->aktif)
                <form action="{{ route('kategori-pembinaan-siswa.destroy', $kategoriPembinaanSiswa) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan kategori pembinaan ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                </form>
            @endif
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Kategori</h2>

                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Nama</dt>
                        <dd>{{ $kategoriPembinaanSiswa->nama }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kode</dt>
                        <dd>{{ $kategoriPembinaanSiswa->kode }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Deskripsi</dt>
                        <dd style="white-space: pre-line;">{{ $teks($kategoriPembinaanSiswa->deskripsi) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Catatan pengembangan</h2>
                <p class="help-text" style="margin-top: 8px;">Kategori ini akan menjadi pilihan saat laporan pembinaan siswa dan tindak lanjut BK dibuat.</p>
            </section>
        </div>
    </div>
@endsection
