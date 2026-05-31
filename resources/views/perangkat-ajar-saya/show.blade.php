@extends('layouts.app')

@section('title', 'Detail Perangkat Ajar - NUSA')

@section('content')
    @php
        $pemilikDokumen = auth()->user()?->pegawai_id
            && (int) auth()->user()->pegawai_id === (int) $perangkatAjar->pegawai_id;
        $kembaliKePemeriksaan = ! $pemilikDokumen
            && auth()->user()?->memilikiIzin(['perangkat_ajar.lihat', 'perangkat_ajar.periksa']);
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Kurikulum</p>
            <h1 class="page-title">Detail perangkat ajar</h1>
        </div>

        <div class="actions">
            @if ($kembaliKePemeriksaan)
                <a href="{{ route('pemeriksaan-perangkat-ajar.show', ['pegawai' => $perangkatAjar->pegawai_id, 'tahun_pelajaran_id' => $perangkatAjar->tahun_pelajaran_id, 'semester' => $perangkatAjar->semester]) }}" class="button button-muted">Kembali</a>
            @else
                <a href="{{ route('perangkat-ajar-saya.index', ['tahun_pelajaran_id' => $perangkatAjar->tahun_pelajaran_id, 'semester' => $perangkatAjar->semester]) }}" class="button button-muted">Kembali</a>
            @endif
            @if ($pemilikDokumen)
                <a href="{{ route('perangkat-ajar-saya.edit', $perangkatAjar) }}" class="button button-dark">Revisi</a>
            @endif
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">PDF</div>
                <h2>{{ $perangkatAjar->jenisPerangkatAjar?->nama ?? '-' }}</h2>
                <p>{{ $perangkatAjar->mataPelajaran?->nama ?? '-' }}</p>

                <div style="margin-top: 16px;">
                    <span class="badge {{ $perangkatAjar->kelasBadgeStatus() }}">{{ $perangkatAjar->labelStatus() }}</span>
                </div>
            </div>

            <a href="{{ route('perangkat-ajar-saya.download', $perangkatAjar) }}" class="button button-primary button-full" style="margin-top: 24px;">Unduh PDF terbaru</a>
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Dokumen</h2>
                <dl class="detail-grid">
                    <div class="detail-item span-2">
                        <dt>Judul</dt>
                        <dd>{{ $perangkatAjar->judul }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Guru</dt>
                        <dd>{{ $perangkatAjar->pegawai?->nama_lengkap ?? '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tahun pelajaran</dt>
                        <dd>{{ $perangkatAjar->tahunPelajaran?->nama ?? '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Semester</dt>
                        <dd>{{ $perangkatAjar->semester }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>File terbaru</dt>
                        <dd>{{ $perangkatAjar->nama_file_asli }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Ukuran</dt>
                        <dd>{{ $perangkatAjar->ukuranFileTampil() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Terakhir diunggah</dt>
                        <dd>{{ $perangkatAjar->diunggah_pada?->format('d M Y H:i') ?? '-' }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Catatan guru</dt>
                        <dd style="white-space: pre-line;">{{ $perangkatAjar->catatan_guru ?: '-' }}</dd>
                    </div>
                </dl>
            </section>

            @if ($perangkatAjar->catatan_pemeriksa || $perangkatAjar->diperiksa_pada)
                <section class="panel panel-pad">
                    <h2 class="panel-title">Hasil Pemeriksaan</h2>
                    <dl class="detail-grid">
                        <div class="detail-item">
                            <dt>Pemeriksa</dt>
                            <dd>{{ $perangkatAjar->pemeriksa?->nama_lengkap ?? '-' }}</dd>
                        </div>
                        <div class="detail-item">
                            <dt>Waktu pemeriksaan</dt>
                            <dd>{{ $perangkatAjar->diperiksa_pada?->format('d M Y H:i') ?? '-' }}</dd>
                        </div>
                        <div class="detail-item span-2">
                            <dt>Catatan pemeriksa</dt>
                            <dd style="white-space: pre-line;">{{ $perangkatAjar->catatan_pemeriksa ?: '-' }}</dd>
                        </div>
                    </dl>
                </section>
            @endif

            <section class="panel">
                <div class="panel-pad" style="padding-bottom: 0;">
                    <h2 class="panel-title">Riwayat File</h2>
                </div>

                <div class="table-wrap">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>Waktu unggah</th>
                                <th>Nama file</th>
                                <th>Ukuran</th>
                                <th>Pengunggah</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($perangkatAjar->riwayatFile as $riwayat)
                                <tr>
                                    <td>{{ $riwayat->diunggah_pada?->format('d M Y H:i') ?? '-' }}</td>
                                    <td>{{ $riwayat->nama_file_asli }}</td>
                                    <td>{{ $riwayat->ukuranFileTampil() }}</td>
                                    <td>{{ $riwayat->pengunggah?->nama ?? '-' }}</td>
                                    <td>
                                        <div class="actions" style="justify-content: flex-end;">
                                            <a href="{{ route('perangkat-ajar-saya.download-riwayat', $riwayat) }}" class="button button-muted button-sm">Unduh</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="empty-state">Riwayat file belum tersedia.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
@endsection
