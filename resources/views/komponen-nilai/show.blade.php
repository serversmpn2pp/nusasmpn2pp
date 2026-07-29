@extends('layouts.app')

@section('title', 'Detail Komponen Nilai - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $guruMapel = $komponenNilai->guruMataPelajaran;
        $pengaturanMapel = $guruMapel?->mataPelajaran?->pengaturanUntuk(
            (int) $guruMapel?->tahun_pelajaran_id,
            (int) $guruMapel?->kelas?->tingkat,
        );
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian</p>
            <h1 class="page-title">Detail komponen nilai</h1>
        </div>

        <div class="actions">
            <a href="{{ route('komponen-nilai.index') }}" class="button button-muted">Kembali</a>
            @izin('nilai.input')
                @if ($komponenNilai->aktif)
                    <a href="{{ route('input-nilai.index', ['komponen_nilai_id' => $komponenNilai->id]) }}" class="button button-primary">Input nilai</a>
                @endif
            @endizin
            @izin('nilai.komponen_kelola')
                <a href="{{ route('komponen-nilai.edit', $komponenNilai) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">KN</div>
                <h2>{{ $komponenNilai->nama }}</h2>
                <p>{{ $komponenNilai->labelJenis() }} - {{ ucfirst($komponenNilai->semester) }}</p>

                <div style="margin-top: 16px;">
                    @if ($komponenNilai->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @izin('nilai.komponen_kelola')
                @if ($komponenNilai->aktif)
                    <form action="{{ route('komponen-nilai.destroy', $komponenNilai) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan komponen nilai ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Komponen</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Nama komponen</dt>
                        <dd>{{ $komponenNilai->nama }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jenis</dt>
                        <dd>{{ $komponenNilai->labelJenis() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Semester</dt>
                        <dd>{{ ucfirst($komponenNilai->semester) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tanggal penilaian</dt>
                        <dd>{{ $komponenNilai->tanggal_penilaian ? $komponenNilai->tanggal_penilaian->format('d-m-Y') : '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Urutan tampil</dt>
                        <dd>{{ $komponenNilai->urutan }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $teks($komponenNilai->keterangan) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Guru Mata Pelajaran</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Tahun pelajaran</dt>
                        <dd>{{ $komponenNilai->guruMataPelajaran?->tahunPelajaran?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kelas</dt>
                        <dd>{{ $komponenNilai->guruMataPelajaran?->kelas?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Mata pelajaran</dt>
                        <dd>{{ $komponenNilai->guruMataPelajaran?->mataPelajaran?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kode mapel</dt>
                        <dd>{{ $teks($pengaturanMapel?->kode) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Guru</dt>
                        <dd>{{ $komponenNilai->guruMataPelajaran?->pegawai?->nama_lengkap ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>NIP</dt>
                        <dd>{{ $teks($komponenNilai->guruMataPelajaran?->pegawai?->nip) }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
@endsection
