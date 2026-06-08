@extends('layouts.app')

@section('title', 'Status Kelengkapan Panitia CBT - NUSA')

@section('content')
    <style>
        .committee-filter {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 2fr) auto;
            gap: 12px;
            align-items: end;
        }

        .committee-stats {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .committee-list {
            display: grid;
            gap: 16px;
        }

        .committee-card {
            display: grid;
            gap: 16px;
        }

        .committee-card-head {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .committee-progress {
            display: grid;
            gap: 7px;
            min-width: min(100%, 280px);
        }

        .committee-progress-bar {
            overflow: hidden;
            height: 10px;
            border-radius: 999px;
            background: #e5e7eb;
        }

        .committee-progress-fill {
            height: 100%;
            border-radius: inherit;
            background: var(--primary);
        }

        .committee-check-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .committee-check {
            display: grid;
            gap: 8px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fafc;
            padding: 12px;
        }

        .committee-check-head {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            justify-content: space-between;
        }

        .committee-check-title {
            margin: 0;
            color: var(--primary-dark);
            font-size: .92rem;
            font-weight: 850;
        }

        .committee-check-detail {
            margin: 0;
            color: var(--muted);
            font-size: .84rem;
            font-weight: 720;
            line-height: 1.35;
        }

        .committee-check-actions {
            margin-top: 2px;
        }

        @media (max-width: 980px) {
            .committee-filter,
            .committee-stats,
            .committee-check-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Status kelengkapan panitia</h1>
        </div>

        <div class="actions">
            <a href="{{ route('jadwal-ujian-cbt.index', array_filter(['kegiatan_ujian_cbt_id' => $kegiatanTerpilih?->id])) }}" class="button button-muted">Jadwal ujian</a>
            <a href="{{ route('ujian-cbt.index') }}" class="button button-muted">Paket CBT</a>
        </div>
    </div>

    <form action="{{ route('status-kelengkapan-panitia-cbt.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="committee-filter">
            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    <option value="">Semua tahun</option>
                    @foreach ($daftarTahunPelajaran as $tahun)
                        <option value="{{ $tahun->id }}" @selected((string) $tahunPelajaranId === (string) $tahun->id)>{{ $tahun->nama }}{{ $tahun->aktif ? ' - aktif' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="kegiatan_ujian_cbt_id">Kegiatan ujian</label>
                <select id="kegiatan_ujian_cbt_id" name="kegiatan_ujian_cbt_id" class="select">
                    <option value="">Otomatis kegiatan terbaru</option>
                    @foreach ($daftarKegiatan as $kegiatan)
                        <option value="{{ $kegiatan->id }}" @selected((int) ($kegiatanTerpilih?->id) === (int) $kegiatan->id)>{{ $kegiatan->nama }} - {{ $kegiatan->tahunPelajaran?->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('status-kelengkapan-panitia-cbt.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <div class="stats-grid committee-stats">
        <div class="panel stat">
            <p class="stat-label">Jadwal dicek</p>
            <p class="stat-value">{{ $ringkasan['jadwal'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Siap panitia</p>
            <p class="stat-value">{{ $ringkasan['lengkap'] }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Perlu dilengkapi</p>
            <p class="stat-value">{{ $ringkasan['perlu_dilengkapi'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Rata-rata lengkap</p>
            <p class="stat-value">{{ number_format($ringkasan['rata_kelengkapan'], 1, ',', '.') }}%</p>
        </div>
    </div>

    @if ($kegiatanTerpilih)
        <section class="panel panel-pad" style="margin-bottom: 24px;">
            <div style="display: flex; gap: 14px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap;">
                <div>
                    <h2 class="panel-title">{{ $kegiatanTerpilih->nama }}</h2>
                    <p class="help-text" style="margin-top: 6px;">
                        {{ $kegiatanTerpilih->jenisUjianCbt?->nama ?: '-' }} - {{ $kegiatanTerpilih->tahunPelajaran?->nama ?: '-' }} - {{ ucfirst($kegiatanTerpilih->semester) }}
                    </p>
                </div>
                <span class="badge badge-active">{{ $kegiatanTerpilih->labelStatus() }}</span>
            </div>
        </section>
    @endif

    <section class="committee-list">
        @forelse ($statusJadwal as $item)
            @php
                $jadwal = $item['jadwal'];
                $ujianCbt = $item['ujianCbt'];
                $siapPanitia = $item['siap_panitia'];
            @endphp

            <article class="panel panel-pad committee-card">
                <div class="committee-card-head">
                    <div>
                        <p class="eyebrow">{{ $jadwal->tanggal?->format('d-m-Y') ?: '-' }} - {{ $jadwal->labelWaktu() }}</p>
                        <h2 class="panel-title">{{ $jadwal->mataPelajaran?->nama ?: '-' }}{{ $jadwal->label_sesi ? ' - ' . $jadwal->label_sesi : '' }}</h2>
                        <p class="help-text">
                            Kelas {{ $jadwal->tingkat ?: '-' }} - {{ $jadwal->kelas->pluck('nama')->implode(', ') ?: 'kelas belum dipilih' }}
                        </p>
                    </div>

                    <div class="committee-progress">
                        <div class="actions" style="justify-content: flex-end;">
                            <span class="badge {{ $siapPanitia ? 'badge-active' : 'badge-warning' }}">{{ $siapPanitia ? 'Siap panitia' : 'Perlu dilengkapi' }}</span>
                            <span class="badge badge-muted">{{ $item['jumlah_beres'] }}/{{ $item['jumlah_wajib'] }} wajib</span>
                        </div>
                        <div class="committee-progress-bar" aria-label="Persentase kelengkapan {{ $item['persentase'] }} persen">
                            <div class="committee-progress-fill" style="width: {{ $item['persentase'] }}%;"></div>
                        </div>
                        <p class="help-text" style="text-align: right;">{{ $item['persentase'] }}% lengkap</p>
                    </div>
                </div>

                <dl class="quick-facts">
                    <div><dt>Paket</dt><dd>{{ $ujianCbt?->kode ?: 'Belum terhubung' }}</dd></div>
                    <div><dt>Peserta</dt><dd>{{ $item['statistik']['peserta'] }}</dd></div>
                    <div><dt>Ruang</dt><dd>{{ $item['statistik']['ruang'] }}</dd></div>
                    <div><dt>Nomor meja</dt><dd>{{ $item['statistik']['ditempatkan'] }}/{{ $item['statistik']['peserta'] }}</dd></div>
                </dl>

                <div class="committee-check-grid">
                    @foreach ($item['pemeriksaan'] as $cek)
                        <section class="committee-check">
                            <div class="committee-check-head">
                                <p class="committee-check-title">{{ $cek['label'] }}</p>
                                <span class="badge {{ $cek['beres'] ? 'badge-active' : ($cek['wajib'] ? 'badge-warning' : 'badge-muted') }}">
                                    {{ $cek['beres'] ? 'Beres' : ($cek['wajib'] ? 'Perlu' : 'Lanjutan') }}
                                </span>
                            </div>
                            <p class="committee-check-detail">{{ $cek['detail'] }}</p>
                            @if ($cek['url'])
                                <div class="committee-check-actions">
                                    <a href="{{ $cek['url'] }}" class="button button-muted button-sm">Buka</a>
                                </div>
                            @endif
                        </section>
                    @endforeach
                </div>
            </article>
        @empty
            <section class="panel panel-pad">
                <div class="empty-state">Belum ada jadwal ujian CBT untuk kegiatan ini.</div>
            </section>
        @endforelse
    </section>
@endsection
