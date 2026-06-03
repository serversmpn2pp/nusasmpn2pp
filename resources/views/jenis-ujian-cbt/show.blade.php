@extends('layouts.app')

@section('title', 'Detail Jenis Ujian CBT - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Detail jenis ujian CBT</h1>
        </div>

        <div class="actions">
            <a href="{{ route('jenis-ujian-cbt.index') }}" class="button button-muted">Kembali</a>
            @izin('cbt.kelola')
                <a href="{{ route('jenis-ujian-cbt.edit', $jenisUjianCbt) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">CB</div>
                <h2>{{ $jenisUjianCbt->nama }}</h2>
                <p>{{ $jenisUjianCbt->kode }}</p>

                <div class="actions" style="justify-content: center; margin-top: 16px;">
                    @if ($jenisUjianCbt->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif

                    @if ($jenisUjianCbt->memerlukan_token)
                        <span class="badge badge-active">Token</span>
                    @else
                        <span class="badge badge-muted">Tanpa token</span>
                    @endif
                </div>
            </div>

            @izin('cbt.kelola')
                @if ($jenisUjianCbt->aktif)
                    <form action="{{ route('jenis-ujian-cbt.destroy', $jenisUjianCbt) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan jenis ujian CBT ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Jenis Ujian</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Kode</dt>
                        <dd>{{ $jenisUjianCbt->kode }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Urutan tampil</dt>
                        <dd>{{ $jenisUjianCbt->urutan }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Token ujian</dt>
                        <dd>{{ $jenisUjianCbt->memerlukan_token ? 'Memerlukan token proktor' : 'Tidak memerlukan token' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Penerapan nilai</dt>
                        <dd>{{ $jenisUjianCbt->dapat_diterapkan_ke_nilai ? 'Dapat diterapkan ke nilai siswa' : 'Hanya arsip atau simulasi' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kartu peserta</dt>
                        <dd>{{ $jenisUjianCbt->tampil_di_kartu_peserta ? 'Ditampilkan di kartu peserta' : 'Tidak dicetak pada kartu peserta' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Status</dt>
                        <dd>{{ $jenisUjianCbt->aktif ? 'Aktif' : 'Nonaktif' }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Deskripsi</dt>
                        <dd style="white-space: pre-line;">{{ $teks($jenisUjianCbt->deskripsi) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Penggunaan berikutnya</h2>
                <p class="help-text" style="margin-top: 8px;">Jenis ujian ini akan dipakai sebagai dasar paket CBT, bank soal, kartu peserta ujian, dan pengaturan token saat modul CBT dilanjutkan.</p>
            </section>
        </div>
    </div>
@endsection
