@extends('layouts.app')

@section('title', 'Pernyataan Survei Pembelajaran - NUSA')

@section('content')
    <style>
        .survey-statement-filter{grid-template-columns:minmax(220px,1fr) 190px auto}
        .survey-statement-text{color:#172536;font-size:.84rem;font-weight:800;line-height:1.5;margin:0;max-width:720px}
        .survey-statement-code{color:#7b8b9b;font-size:.68rem;margin:5px 0 0}
        .survey-statement-mobile{border-bottom:1px solid #dce4eb;padding:16px}
        .survey-statement-mobile:last-child{border-bottom:0}
        .survey-statement-mobile-head{align-items:flex-start;display:flex;gap:12px;justify-content:space-between}
        .survey-statement-mobile .actions{margin-top:13px}
        @media(max-width:780px){.survey-statement-filter{grid-template-columns:1fr 170px}.survey-statement-filter .actions{grid-column:1/-1}}
        @media(max-width:560px){.survey-statement-filter{grid-template-columns:1fr}.survey-statement-filter .actions{display:grid;grid-column:auto;grid-template-columns:1fr 1fr}.survey-statement-filter .button{justify-content:center;width:100%}.page-header .button{justify-content:center;width:100%}}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kurikulum</p>
            <h1 class="page-title">Pernyataan survei pembelajaran</h1>
        </div>
        <a href="{{ route('pertanyaan-survei-pembelajaran.create') }}" class="button button-primary">Tambah pernyataan</a>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total pernyataan</p>
            <p class="stat-value">{{ $jumlahPertanyaan }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Aktif</p>
            <p class="stat-value">{{ $jumlahAktif }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Nonaktif</p>
            <p class="stat-value">{{ $jumlahNonaktif }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('pertanyaan-survei-pembelajaran.index') }}" class="panel panel-pad" style="margin-bottom:24px">
        <div class="filter-grid survey-statement-filter">
            <div class="field">
                <label for="kata_kunci">Cari pernyataan</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" class="input" placeholder="Isi pernyataan">
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="semua" @selected($status === 'semua')>Semua</option>
                    <option value="aktif" @selected($status === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('pertanyaan-survei-pembelajaran.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th style="width:90px">Urutan</th>
                        <th>Pernyataan</th>
                        <th style="width:110px">Status</th>
                        <th class="text-right" style="width:230px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pertanyaan as $item)
                        <tr>
                            <td>{{ $item->urutan }}</td>
                            <td>
                                <p class="survey-statement-text">{{ $item->pernyataan }}</p>
                                <p class="survey-statement-code">{{ $item->kode }}</p>
                            </td>
                            <td><span class="badge {{ $item->aktif ? 'badge-active' : 'badge-inactive' }}">{{ $item->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td>
                                <div class="actions" style="justify-content:flex-end">
                                    <a href="{{ route('pertanyaan-survei-pembelajaran.edit', $item) }}" class="button button-dark">Edit</a>
                                    <form method="POST" action="{{ route('pertanyaan-survei-pembelajaran.status', $item) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="button button-muted">{{ $item->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">Belum ada pernyataan survei.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only">
            @forelse ($pertanyaan as $item)
                <article class="survey-statement-mobile">
                    <div class="survey-statement-mobile-head">
                        <div>
                            <p class="survey-statement-code">Urutan {{ $item->urutan }}</p>
                            <p class="survey-statement-text">{{ $item->pernyataan }}</p>
                        </div>
                        <span class="badge {{ $item->aktif ? 'badge-active' : 'badge-inactive' }}">{{ $item->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                    </div>
                    <div class="actions">
                        <a href="{{ route('pertanyaan-survei-pembelajaran.edit', $item) }}" class="button button-dark">Edit</a>
                        <form method="POST" action="{{ route('pertanyaan-survei-pembelajaran.status', $item) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="button button-muted">{{ $item->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada pernyataan survei.</div>
            @endforelse
        </div>
    </section>

    @if ($pertanyaan->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $pertanyaan->currentPage() }} dari {{ $pertanyaan->lastPage() }}</div>
            <div class="actions">
                @if ($pertanyaan->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $pertanyaan->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif
                @if ($pertanyaan->hasMorePages())
                    <a href="{{ $pertanyaan->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
