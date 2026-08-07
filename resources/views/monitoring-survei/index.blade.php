@extends('layouts.app')

@section('title', 'Monitoring Survei - NUSA')

@section('content')
    <style>
        .survey-monitor-shell{display:grid;gap:18px}
        .survey-monitor-shell>.stats-grid,.survey-monitor-detail>.stats-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
        .survey-monitor-filter{grid-template-columns:190px 140px minmax(230px,1fr) 165px auto}
        .survey-monitor-progress{align-items:center;display:grid;gap:8px;grid-template-columns:minmax(80px,1fr) 58px;min-width:150px}
        .survey-monitor-track{background:#e7edf2;border-radius:4px;height:8px;overflow:hidden}
        .survey-monitor-fill{background:#2b7fbd;height:100%}
        .survey-monitor-progress span{color:#526579;font-size:.68rem;font-weight:800;text-align:right}
        .survey-monitor-mobile{border-bottom:1px solid #dce4eb;padding:16px}
        .survey-monitor-mobile:last-child{border-bottom:0}
        .survey-monitor-mobile-head{align-items:flex-start;display:flex;gap:12px;justify-content:space-between}
        .survey-monitor-mobile h2{font-size:.86rem;letter-spacing:0;line-height:1.4;margin:0}
        .survey-monitor-mobile p{color:#64748b;font-size:.7rem;line-height:1.45;margin:4px 0 0}
        .survey-monitor-mobile .survey-monitor-progress{margin-top:14px}
        .survey-monitor-mobile .actions{margin-top:13px}
        .survey-monitor-detail{display:grid;gap:16px;scroll-margin-top:18px}
        .survey-monitor-context{align-items:center;background:#15477a;border-radius:8px;color:#fff;display:flex;gap:18px;justify-content:space-between;padding:18px 20px}
        .survey-monitor-context h2{color:#fff;font-size:1.04rem;letter-spacing:0;margin:0}
        .survey-monitor-context p{color:#dbeafe;font-size:.74rem;line-height:1.5;margin:5px 0 0}
        .survey-monitor-context .badge{background:#fff7cf;color:#655000;flex:0 0 auto}
        .survey-monitor-lock{align-items:center;background:#fff8d8;border:1px solid #f1c40f;border-radius:8px;display:flex;gap:16px;justify-content:space-between;padding:18px 20px}
        .survey-monitor-lock strong{color:#172536;display:block;font-size:.9rem}.survey-monitor-lock p{color:#675d3c;font-size:.75rem;line-height:1.5;margin:4px 0 0}.survey-monitor-lock-count{color:#15477a;font-size:1.45rem;font-weight:900;white-space:nowrap}
        .survey-monitor-section-title{align-items:flex-end;display:flex;gap:12px;justify-content:space-between}.survey-monitor-section-title h2{font-size:1.03rem;letter-spacing:0;margin:0}.survey-monitor-section-title p{color:#64748b;font-size:.72rem;margin:3px 0 0}
        .survey-monitor-question-list{display:grid;gap:14px}.survey-monitor-question{background:#fff;border:1px solid #dce4eb;border-radius:8px;padding:18px}.survey-monitor-question-head{align-items:flex-start;display:flex;gap:18px;justify-content:space-between}.survey-monitor-question h3{color:#172536;font-size:.86rem;letter-spacing:0;line-height:1.5;margin:0}.survey-monitor-question-meta{color:#64748b;font-size:.68rem;margin:5px 0 0}.survey-monitor-average{background:#eef5fb;border:1px solid #b9d1e6;border-radius:7px;color:#15477a;flex:0 0 92px;padding:9px;text-align:center}.survey-monitor-average strong{display:block;font-size:1.2rem}.survey-monitor-average span{font-size:.64rem;font-weight:800}
        .survey-monitor-distribution{display:grid;gap:8px;margin-top:16px}.survey-monitor-distribution-row{align-items:center;display:grid;gap:10px;grid-template-columns:22px minmax(0,1fr) 92px}.survey-monitor-distribution-label{color:#172536;font-size:.72rem;font-weight:900;text-align:center}.survey-monitor-distribution-track{background:#edf1f5;border-radius:4px;height:9px;overflow:hidden}.survey-monitor-distribution-fill{height:100%}.survey-monitor-distribution-fill.score-1{background:#c2413b}.survey-monitor-distribution-fill.score-2{background:#d97706}.survey-monitor-distribution-fill.score-3{background:#f1c40f}.survey-monitor-distribution-fill.score-4{background:#2b7fbd}.survey-monitor-distribution-fill.score-5{background:#27835b}.survey-monitor-distribution-value{color:#526579;font-size:.68rem;font-weight:800;text-align:right;white-space:nowrap}
        .survey-monitor-suggestions{margin:0;padding:0}.survey-monitor-suggestion{border-bottom:1px solid #e2e8ee;list-style:none;padding:14px 0}.survey-monitor-suggestion:first-child{padding-top:0}.survey-monitor-suggestion:last-child{border-bottom:0;padding-bottom:0}.survey-monitor-suggestion p{color:#27384b;font-size:.8rem;line-height:1.6;margin:0}.survey-monitor-suggestion time{color:#7b8b9b;display:block;font-size:.65rem;font-weight:700;margin-top:6px}
        @media(max-width:1120px){.survey-monitor-filter{grid-template-columns:1fr 150px 170px}.survey-monitor-filter .field:nth-child(3){grid-column:1/-1}.survey-monitor-filter .actions{grid-column:1/-1}}
        @media(max-width:820px){.survey-monitor-shell>.stats-grid,.survey-monitor-detail>.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:620px){.survey-monitor-filter{grid-template-columns:1fr}.survey-monitor-filter .field:nth-child(3),.survey-monitor-filter .actions{grid-column:auto}.survey-monitor-filter .actions{display:grid;grid-template-columns:1fr 1fr}.survey-monitor-filter .button{justify-content:center;width:100%}.survey-monitor-shell>.stats-grid,.survey-monitor-detail>.stats-grid{grid-template-columns:1fr}.survey-monitor-context,.survey-monitor-lock,.survey-monitor-section-title,.survey-monitor-question-head{align-items:flex-start;flex-direction:column}.survey-monitor-average{align-items:baseline;display:flex;gap:7px;justify-content:center;width:100%}.survey-monitor-distribution-row{grid-template-columns:22px minmax(0,1fr) 76px}.survey-monitor-mobile .actions{display:grid;grid-template-columns:1fr}.survey-monitor-mobile .button{justify-content:center;width:100%}}
    </style>

    @php
        $parameterFilter = array_filter([
            'tahun_pelajaran_id' => $tahunPelajaranDipilih?->id,
            'semester' => $semester,
            'kata_kunci' => $kataKunci,
            'status' => $status !== 'semua' ? $status : null,
        ], fn ($nilai) => filled($nilai));
    @endphp

    <div class="survey-monitor-shell">
        <div class="page-header">
            <div>
                <p class="eyebrow">Akademik</p>
                <h1 class="page-title">Monitoring Survei</h1>
            </div>
        </div>

        <form method="GET" action="{{ route('monitoring-survei.index') }}" class="panel panel-pad">
            <div class="filter-grid survey-monitor-filter">
                <div class="field">
                    <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                    <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                        @foreach ($daftarTahunPelajaran as $tahun)
                            <option value="{{ $tahun->id }}" @selected($tahunPelajaranDipilih?->id === $tahun->id)>{{ $tahun->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" class="select">
                        <option value="ganjil" @selected($semester === 'ganjil')>Ganjil</option>
                        <option value="genap" @selected($semester === 'genap')>Genap</option>
                    </select>
                </div>
                <div class="field">
                    <label for="kata_kunci">Cari guru atau penugasan</label>
                    <input id="kata_kunci" name="kata_kunci" type="search" class="input" value="{{ $kataKunci }}" placeholder="Nama guru, NIP, mata pelajaran, kelas">
                </div>
                <div class="field">
                    <label for="status">Status pengisian</label>
                    <select id="status" name="status" class="select">
                        <option value="semua" @selected($status === 'semua')>Semua</option>
                        <option value="belum" @selected($status === 'belum')>Belum dimulai</option>
                        <option value="berjalan" @selected($status === 'berjalan')>Sedang berjalan</option>
                        <option value="lengkap" @selected($status === 'lengkap')>Lengkap</option>
                    </select>
                </div>
                <div class="actions">
                    <button type="submit" class="button button-primary">Terapkan</button>
                    <a href="{{ route('monitoring-survei.index') }}" class="button button-muted">Reset</a>
                </div>
            </div>
        </form>

        <div class="stats-grid">
            <div class="panel stat"><p class="stat-label">Penugasan terpantau</p><p class="stat-value">{{ $ringkasanMonitoring['penugasan'] }}</p></div>
            <div class="panel stat"><p class="stat-label">Target respons</p><p class="stat-value">{{ $ringkasanMonitoring['target_respons'] }}</p></div>
            <div class="panel stat active"><p class="stat-label">Respons masuk</p><p class="stat-value">{{ $ringkasanMonitoring['respons_masuk'] }}</p></div>
            <div class="panel stat inactive"><p class="stat-label">Hasil terbuka</p><p class="stat-value">{{ $ringkasanMonitoring['hasil_terbuka'] }}</p></div>
        </div>

        <section class="panel">
            <div class="desktop-only table-wrap">
                <table class="employee-table">
                    <thead><tr><th>Guru</th><th>Mata pelajaran / kelas</th><th>Pengisian</th><th>Rata-rata</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($monitoring as $item)
                            @php($penugasan = $item['penugasan'])
                            <tr>
                                <td><p class="person-name">{{ $penugasan->pegawai?->nama_lengkap ?: '-' }}</p><p class="person-meta">{{ $penugasan->pegawai?->nip ?: 'NIP belum diisi' }}</p></td>
                                <td><p class="person-name">{{ $penugasan->mataPelajaran?->nama ?: '-' }}</p><p class="person-meta">{{ $penugasan->kelas?->nama ?: '-' }}{{ $penugasan->aktif ? '' : ' · Penugasan selesai' }}</p></td>
                                <td>
                                    <div class="survey-monitor-progress">
                                        <div class="survey-monitor-track"><div class="survey-monitor-fill" style="width:{{ $item['persentasePengisian'] }}%"></div></div>
                                        <span>{{ $item['jumlahPengisi'] }}/{{ $item['jumlahSiswa'] }}</span>
                                    </div>
                                </td>
                                <td>{{ $item['hasilTerbuka'] && $item['rataRataKeseluruhan'] !== null ? number_format($item['rataRataKeseluruhan'], 2, ',', '.') : '-' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('monitoring-survei.index', $parameterFilter + ['guru_mata_pelajaran_id' => $penugasan->id]).'#rincian-survei' }}" class="button {{ $penugasanDipilih?->id === $penugasan->id ? 'button-primary' : 'button-muted' }}">Rincian</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">Belum ada penugasan yang sesuai dengan filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mobile-only">
                @forelse ($monitoring as $item)
                    @php($penugasan = $item['penugasan'])
                    <article class="survey-monitor-mobile">
                        <div class="survey-monitor-mobile-head">
                            <div><h2>{{ $penugasan->pegawai?->nama_lengkap ?: '-' }}</h2><p>{{ $penugasan->mataPelajaran?->nama ?: '-' }} · {{ $penugasan->kelas?->nama ?: '-' }}</p></div>
                            <span class="badge {{ $item['persentasePengisian'] >= 100 ? 'badge-active' : 'badge-inactive' }}">{{ $item['jumlahPengisi'] === 0 ? 'Belum' : ($item['persentasePengisian'] >= 100 ? 'Lengkap' : 'Proses') }}</span>
                        </div>
                        <div class="survey-monitor-progress"><div class="survey-monitor-track"><div class="survey-monitor-fill" style="width:{{ $item['persentasePengisian'] }}%"></div></div><span>{{ $item['jumlahPengisi'] }}/{{ $item['jumlahSiswa'] }}</span></div>
                        <div class="actions"><a href="{{ route('monitoring-survei.index', $parameterFilter + ['guru_mata_pelajaran_id' => $penugasan->id]).'#rincian-survei' }}" class="button button-dark">Lihat rincian</a></div>
                    </article>
                @empty
                    <div class="empty-state">Belum ada penugasan yang sesuai dengan filter.</div>
                @endforelse
            </div>
        </section>

        @if ($monitoring->hasPages())
            <nav class="pagination-simple">
                <div>Halaman {{ $monitoring->currentPage() }} dari {{ $monitoring->lastPage() }}</div>
                <div class="actions">
                    @if ($monitoring->onFirstPage())<span class="button button-muted" aria-disabled="true">Sebelumnya</span>@else<a href="{{ $monitoring->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>@endif
                    @if ($monitoring->hasMorePages())<a href="{{ $monitoring->nextPageUrl() }}" class="button button-muted">Berikutnya</a>@else<span class="button button-muted" aria-disabled="true">Berikutnya</span>@endif
                </div>
            </nav>
        @endif

        @if ($penugasanDipilih && $hasilDipilih)
            <section id="rincian-survei" class="survey-monitor-detail">
                <div class="survey-monitor-context">
                    <div>
                        <h2>{{ $penugasanDipilih->pegawai?->nama_lengkap ?: '-' }}</h2>
                        <p>{{ $penugasanDipilih->mataPelajaran?->nama ?: '-' }} · {{ $penugasanDipilih->kelas?->nama ?: '-' }} · Semester {{ ucfirst($semester) }}</p>
                    </div>
                    <span class="badge">Rincian anonim</span>
                </div>

                <div class="stats-grid">
                    <div class="panel stat"><p class="stat-label">Siswa kelas</p><p class="stat-value">{{ $hasilDipilih['jumlahSiswa'] }}</p></div>
                    <div class="panel stat active"><p class="stat-label">Sudah mengisi</p><p class="stat-value">{{ $hasilDipilih['jumlahPengisi'] }}</p></div>
                    <div class="panel stat inactive"><p class="stat-label">Tingkat pengisian</p><p class="stat-value">{{ number_format($hasilDipilih['persentasePengisian'], 1, ',', '.') }}%</p></div>
                    <div class="panel stat"><p class="stat-label">Rata-rata</p><p class="stat-value">{{ $hasilDipilih['hasilTerbuka'] && $hasilDipilih['rataRataKeseluruhan'] !== null ? number_format($hasilDipilih['rataRataKeseluruhan'], 2, ',', '.') : '-' }}</p></div>
                </div>

                @if (! $hasilDipilih['hasilTerbuka'])
                    <div class="survey-monitor-lock"><div><strong>Hasil rinci belum ditampilkan</strong><p>Minimal {{ $minimalResponden }} siswa harus mengisi agar identitas jawaban tetap terlindungi.</p></div><div class="survey-monitor-lock-count">{{ $hasilDipilih['jumlahPengisi'] }}/{{ $minimalResponden }}</div></div>
                @else
                    <div class="survey-monitor-section-title"><div><h2>Rincian pernyataan</h2><p>Skala 1 sangat tidak sesuai sampai 5 sangat sesuai.</p></div></div>
                    <div class="survey-monitor-question-list">
                        @foreach ($hasilDipilih['rincianPertanyaan'] as $item)
                            <article class="survey-monitor-question">
                                <div class="survey-monitor-question-head"><div><h3>{{ $item['pernyataan'] }}</h3><p class="survey-monitor-question-meta">{{ $item['jumlah_jawaban'] }} jawaban</p></div><div class="survey-monitor-average"><strong>{{ number_format($item['rata_rata'], 2, ',', '.') }}</strong><span>Rata-rata</span></div></div>
                                <div class="survey-monitor-distribution">
                                    @foreach ($item['distribusi'] as $nilai => $distribusi)
                                        <div class="survey-monitor-distribution-row"><span class="survey-monitor-distribution-label" title="{{ $daftarPilihan[$nilai] }}">{{ $nilai }}</span><div class="survey-monitor-distribution-track"><div class="survey-monitor-distribution-fill score-{{ $nilai }}" style="width:{{ $distribusi['persentase'] }}%"></div></div><span class="survey-monitor-distribution-value">{{ $distribusi['jumlah'] }} · {{ number_format($distribusi['persentase'], 1, ',', '.') }}%</span></div>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="survey-monitor-section-title"><div><h2>Saran siswa</h2><p>{{ $hasilDipilih['daftarSaran']->count() }} saran tertulis</p></div></div>
                    <section class="panel panel-pad">
                        @if ($hasilDipilih['daftarSaran']->isNotEmpty())
                            <ol class="survey-monitor-suggestions">
                                @foreach ($hasilDipilih['daftarSaran'] as $saran)
                                    <li class="survey-monitor-suggestion"><p>{{ $saran['saran'] }}</p><time datetime="{{ $saran['diisi_pada']?->toIso8601String() }}">{{ $saran['diisi_pada']?->format('d/m/Y') }}</time></li>
                                @endforeach
                            </ol>
                        @else
                            <div class="empty-state">Belum ada saran tertulis dari siswa.</div>
                        @endif
                    </section>
                @endif
            </section>
        @endif
    </div>
@endsection
