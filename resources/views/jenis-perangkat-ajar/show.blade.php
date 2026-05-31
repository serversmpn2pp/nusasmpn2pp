@extends('layouts.app')

@section('title', 'Detail Jenis Perangkat Ajar - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Kurikulum</p>
            <h1 class="page-title">Detail jenis perangkat ajar</h1>
        </div>

        <div class="actions">
            <a href="{{ route('jenis-perangkat-ajar.index') }}" class="button button-muted">Kembali</a>
            <a href="{{ route('jenis-perangkat-ajar.edit', $jenisPerangkatAjar) }}" class="button button-dark">Edit</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">PA</div>
                <h2>{{ $jenisPerangkatAjar->nama }}</h2>
                <p>{{ $jenisPerangkatAjar->kode }}</p>

                <div class="actions" style="justify-content: center; margin-top: 16px;">
                    @if ($jenisPerangkatAjar->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif

                    @if ($jenisPerangkatAjar->wajib)
                        <span class="badge badge-active">Wajib</span>
                    @else
                        <span class="badge badge-inactive">Opsional</span>
                    @endif
                </div>
            </div>

            @if ($jenisPerangkatAjar->aktif)
                <form action="{{ route('jenis-perangkat-ajar.destroy', $jenisPerangkatAjar) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan jenis perangkat ajar ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                </form>
            @endif
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Jenis Perangkat</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Kode</dt>
                        <dd>{{ $jenisPerangkatAjar->kode }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Urutan tampil</dt>
                        <dd>{{ $jenisPerangkatAjar->urutan }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kewajiban unggah</dt>
                        <dd>{{ $jenisPerangkatAjar->wajib ? 'Wajib diunggah' : 'Opsional' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Status</dt>
                        <dd>{{ $jenisPerangkatAjar->aktif ? 'Aktif' : 'Nonaktif' }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Deskripsi</dt>
                        <dd style="white-space: pre-line;">{{ $teks($jenisPerangkatAjar->deskripsi) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Penggunaan berikutnya</h2>
                <p class="help-text" style="margin-top: 8px;">Jenis perangkat ini akan menjadi daftar dokumen unggahan guru dan dapat dihitung dalam progres kelengkapan perangkat ajar.</p>
            </section>
        </div>
    </div>
@endsection
