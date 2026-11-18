@extends('layouts.app')
@section('title', 'Profil Siswa Terpadu - NUSA')
@include('profil-siswa-terpadu._styles')

@section('content')
    <div class="siswa-terpadu">
        <header class="page-header">
            <div>
                <p class="eyebrow">Data Sekolah</p>
                <h1 class="page-title">Profil Siswa Terpadu</h1>
            </div>
        </header>

        <form method="get" action="{{ route('profil-siswa-terpadu.index') }}" class="terpadu-filters" role="search">
            <label class="terpadu-field terpadu-search">
                Nama, NIS, atau NISN
                <input class="input" type="search" name="q" value="{{ $filter['q'] ?? '' }}" placeholder="Cari siswa" maxlength="100">
            </label>
            <label class="terpadu-field">
                Tahun pelajaran
                <select class="select" name="tahun" onchange="this.form.elements.kelas.value = ''; this.form.requestSubmit()">
                    @forelse ($daftarTahun as $pilihan)
                        <option value="{{ $pilihan->id }}" @selected($tahun?->id === $pilihan->id)>{{ $pilihan->nama }}{{ $pilihan->aktif ? ' (aktif)' : '' }}</option>
                    @empty
                        <option value="">Belum tersedia</option>
                    @endforelse
                </select>
            </label>
            <label class="terpadu-field">
                Kelas
                <select class="select" name="kelas">
                    <option value="">Semua kelas</option>
                    @foreach ($daftarKelas as $kelas)
                        <option value="{{ $kelas->id }}" @selected((int) ($filter['kelas'] ?? 0) === $kelas->id)>{{ $kelas->nama }}</option>
                    @endforeach
                </select>
            </label>
            <div class="terpadu-actions">
                <button type="submit" class="button button-primary"><i data-lucide="search" aria-hidden="true"></i> Cari siswa</button>
                @if (! empty($filter))
                    <a class="button button-muted" href="{{ route('profil-siswa-terpadu.index') }}">Reset</a>
                @endif
            </div>
        </form>

        <section class="terpadu-section" aria-labelledby="daftar-siswa">
            <header><h2 id="daftar-siswa">Hasil pencarian @if($siswa) <span class="terpadu-pill">{{ $siswa->total() }} siswa</span>@endif</h2></header>
            @if ($siswa?->isNotEmpty())
                <table class="terpadu-tabel" role="table" aria-labelledby="daftar-siswa">
                    <thead role="rowgroup"><tr role="row"><th scope="col">Siswa</th><th scope="col">NIS</th><th scope="col">NISN</th><th scope="col">Kelas</th><th scope="col">Status</th><th scope="col">Profil</th></tr></thead>
                    <tbody role="rowgroup">
                        @foreach ($siswa as $murid)
                            @php($url = route('profil-siswa-terpadu.show', ['siswa' => $murid, 'tahun' => $tahun->id]))
                            <tr role="row">
                                <td role="cell" class="terpadu-name-cell" data-label="Siswa">
                                    <a class="terpadu-person" href="{{ $url }}">
                                        @include('profil-siswa-terpadu._avatar', ['siswa' => $murid, 'kecil' => true])
                                        <span>{{ $murid->nama_lengkap }}</span>
                                    </a>
                                </td>
                                <td role="cell" data-label="NIS"><span>{{ $murid->nis ?: '-' }}</span></td>
                                <td role="cell" data-label="NISN"><span>{{ $murid->nisn ?: '-' }}</span></td>
                                <td role="cell" data-label="Kelas"><span>{{ $murid->anggotaKelas->pluck('kelas.nama')->filter()->unique()->join(', ') ?: '-' }}</span></td>
                                <td role="cell" data-label="Status"><span>{{ $murid->aktif ? 'Aktif' : 'Tidak aktif' }}</span></td>
                                <td role="cell" class="terpadu-action-cell" data-label="Profil"><a href="{{ $url }}" class="button button-muted" aria-label="Buka profil {{ $murid->nama_lengkap }}">Buka profil <i data-lucide="arrow-right" aria-hidden="true"></i></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($siswa->hasPages())
                    <div class="terpadu-paginasi">{{ $siswa->links() }}</div>
                @endif
            @else
                <p class="terpadu-empty">{{ $tahun ? 'Tidak ada siswa yang cocok dengan pencarian.' : 'Belum ada tahun pelajaran yang tersedia.' }}</p>
            @endif
        </section>
    </div>
@endsection
