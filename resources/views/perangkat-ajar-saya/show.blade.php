@extends('layouts.app')

@section('title', 'Detail Perangkat Ajar - NUSA')

@section('content')
    @php
        $pemilikDokumen = auth()->user()?->pegawai_id
            && (int) auth()->user()->pegawai_id === (int) $perangkatAjar->pegawai_id;
        $kembaliKePemeriksaan = ! $pemilikDokumen
            && auth()->user()?->memilikiIzin(['perangkat_ajar.lihat', 'perangkat_ajar.periksa']);
    @endphp

    <style>
        .teaching-document-detail,
        .teaching-document-detail .detail-shell > *,
        .teaching-document-detail .section-stack,
        .teaching-document-detail .panel {
            min-width: 0;
        }

        .teaching-document-file-name,
        .teaching-document-detail .detail-item dd {
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .teaching-history-mobile {
            display: none;
        }

        @media (max-width: 900px) {
            .teaching-document-detail .page-header {
                gap: 14px;
            }

            .teaching-document-detail .page-header .actions {
                width: 100%;
            }

            .teaching-document-detail .detail-shell,
            .teaching-document-detail .section-stack {
                gap: 14px;
            }

            .teaching-document-detail .detail-profile .avatar-lg {
                width: 88px;
                height: 88px;
                margin-inline: auto;
                font-size: 1.65rem;
            }

            .teaching-document-detail .detail-profile h2 {
                margin-top: 12px;
                overflow-wrap: anywhere;
            }

            .teaching-document-detail .detail-grid {
                gap: 0;
                margin-top: 10px;
            }

            .teaching-document-detail .detail-item {
                min-width: 0;
                padding: 11px 0;
                border-top: 1px solid var(--line);
            }

            .teaching-document-detail .detail-item:first-child {
                border-top: 0;
            }

            .teaching-history-desktop {
                display: none;
            }

            .teaching-history-mobile {
                display: grid;
            }

            .teaching-history-card {
                min-width: 0;
                padding: 16px;
                border-top: 1px solid var(--line);
            }

            .teaching-history-card-head {
                display: grid;
                gap: 8px;
            }

            .teaching-history-card-title {
                margin: 0;
                color: var(--primary-dark);
                font-weight: 800;
                line-height: 1.4;
                overflow-wrap: anywhere;
                word-break: break-word;
            }

            .teaching-history-card-time {
                margin: 4px 0 0;
                color: var(--muted);
                font-size: .86rem;
            }

            .teaching-history-card .quick-facts {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .teaching-history-card .quick-facts div {
                min-width: 0;
            }

            .teaching-history-card .quick-facts dd {
                overflow-wrap: anywhere;
            }

            .teaching-history-card .button {
                width: 100%;
                margin-top: 14px;
            }
        }

        @media (max-width: 520px) {
            .teaching-history-card .quick-facts {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="teaching-document-detail">
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
                        <dt>Tingkat</dt>
                        <dd>{{ $perangkatAjar->tingkatTampil() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>File terbaru</dt>
                        <dd class="teaching-document-file-name">{{ $perangkatAjar->nama_file_asli }}</dd>
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

                <div class="table-wrap teaching-history-desktop">
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

                <div class="teaching-history-mobile">
                    @forelse ($perangkatAjar->riwayatFile as $riwayat)
                        <article class="teaching-history-card">
                            <div class="teaching-history-card-head">
                                <div>
                                    <p class="teaching-history-card-title">{{ $riwayat->nama_file_asli }}</p>
                                    <p class="teaching-history-card-time">Diunggah {{ $riwayat->diunggah_pada?->format('d M Y H:i') ?? '-' }}</p>
                                </div>
                                <span class="badge badge-muted">{{ $riwayat->ukuranFileTampil() }}</span>
                            </div>

                            <dl class="quick-facts">
                                <div>
                                    <dt>Pengunggah</dt>
                                    <dd>{{ $riwayat->pengunggah?->nama ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt>Waktu unggah</dt>
                                    <dd>{{ $riwayat->diunggah_pada?->format('d M Y H:i') ?? '-' }}</dd>
                                </div>
                            </dl>

                            <a href="{{ route('perangkat-ajar-saya.download-riwayat', $riwayat) }}" class="button button-muted button-sm">Unduh file</a>
                        </article>
                    @empty
                        <div class="empty-state">Riwayat file belum tersedia.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
    </div>
@endsection
