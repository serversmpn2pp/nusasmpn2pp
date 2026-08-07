@extends('layouts.app')

@section('title', 'Hasil Survei Saya - NUSA')

@section('content')
    <style>
        .survey-result-shell{display:grid;gap:18px}
        .survey-result-shell>.stats-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
        .survey-result-filter{grid-template-columns:210px 150px minmax(280px,1fr) auto}
        .survey-result-context{align-items:center;background:#15477a;border-radius:8px;color:#fff;display:flex;gap:18px;justify-content:space-between;padding:18px 20px}
        .survey-result-context h2{color:#fff;font-size:1.08rem;letter-spacing:0;margin:0}
        .survey-result-context p{color:#dbeafe;font-size:.76rem;line-height:1.5;margin:5px 0 0}
        .survey-result-context .badge{background:#fff7cf;color:#655000;flex:0 0 auto}
        .survey-result-lock{align-items:center;background:#fff8d8;border:1px solid #f1c40f;border-radius:8px;display:flex;gap:16px;justify-content:space-between;padding:18px 20px}
        .survey-result-lock strong{color:#172536;display:block;font-size:.9rem}
        .survey-result-lock p{color:#675d3c;font-size:.75rem;line-height:1.5;margin:4px 0 0}
        .survey-result-lock-count{color:#15477a;font-size:1.45rem;font-weight:900;white-space:nowrap}
        .survey-result-section-title{align-items:flex-end;display:flex;gap:12px;justify-content:space-between;margin-top:2px}
        .survey-result-section-title h2{font-size:1.08rem;letter-spacing:0;margin:0}
        .survey-result-section-title p{color:#64748b;font-size:.74rem;margin:0}
        .survey-question-list{display:grid;gap:14px}
        .survey-question-card{background:#fff;border:1px solid #dce4eb;border-radius:8px;padding:18px}
        .survey-question-head{align-items:flex-start;display:flex;gap:18px;justify-content:space-between}
        .survey-question-head h3{color:#172536;font-size:.88rem;letter-spacing:0;line-height:1.5;margin:0;max-width:820px}
        .survey-question-meta{color:#64748b;font-size:.7rem;margin:5px 0 0}
        .survey-question-average{background:#eef5fb;border:1px solid #b9d1e6;border-radius:7px;color:#15477a;flex:0 0 92px;padding:9px;text-align:center}
        .survey-question-average strong{display:block;font-size:1.2rem}
        .survey-question-average span{font-size:.64rem;font-weight:800}
        .survey-distribution{display:grid;gap:8px;margin-top:16px}
        .survey-distribution-row{align-items:center;display:grid;gap:10px;grid-template-columns:22px minmax(0,1fr) 92px}
        .survey-distribution-label{color:#172536;font-size:.72rem;font-weight:900;text-align:center}
        .survey-distribution-track{background:#edf1f5;border-radius:4px;height:9px;overflow:hidden}
        .survey-distribution-fill{height:100%;min-width:0}
        .survey-distribution-fill.score-1{background:#c2413b}.survey-distribution-fill.score-2{background:#d97706}.survey-distribution-fill.score-3{background:#f1c40f}.survey-distribution-fill.score-4{background:#2b7fbd}.survey-distribution-fill.score-5{background:#27835b}
        .survey-distribution-value{color:#526579;font-size:.68rem;font-weight:800;text-align:right;white-space:nowrap}
        .survey-suggestion-list{margin:0;padding:0}
        .survey-suggestion-item{border-bottom:1px solid #e2e8ee;list-style:none;padding:14px 0}
        .survey-suggestion-item:first-child{padding-top:0}.survey-suggestion-item:last-child{border-bottom:0;padding-bottom:0}
        .survey-suggestion-item p{color:#27384b;font-size:.8rem;line-height:1.6;margin:0}
        .survey-suggestion-item time{color:#7b8b9b;display:block;font-size:.65rem;font-weight:700;margin-top:6px}
        .survey-anonymous-note{align-items:center;color:#64748b;display:flex;font-size:.7rem;font-weight:700;gap:7px}
        .survey-anonymous-dot{background:#27835b;border-radius:50%;height:8px;width:8px}
        @media(max-width:980px){.survey-result-filter{grid-template-columns:1fr 150px}.survey-result-filter .field:nth-child(3){grid-column:1/-1}.survey-result-filter .actions{grid-column:1/-1}.survey-result-shell>.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:620px){.survey-result-filter{grid-template-columns:1fr}.survey-result-filter .field:nth-child(3),.survey-result-filter .actions{grid-column:auto}.survey-result-filter .actions{display:grid;grid-template-columns:1fr}.survey-result-filter .button{justify-content:center;width:100%}.survey-result-context{align-items:flex-start;flex-direction:column}.survey-result-lock{align-items:flex-start;flex-direction:column}.survey-result-section-title{align-items:flex-start;flex-direction:column}.survey-question-head{align-items:stretch;flex-direction:column}.survey-question-average{align-items:baseline;display:flex;gap:7px;justify-content:center}.survey-distribution-row{grid-template-columns:22px minmax(0,1fr) 76px}.survey-result-shell>.stats-grid{grid-template-columns:1fr}}
    </style>

    <div class="survey-result-shell">
        <div class="page-header">
            <div>
                <p class="eyebrow">Pembelajaran</p>
                <h1 class="page-title">Hasil Survei Saya</h1>
            </div>
        </div>

        @if ($daftarPenugasan->isNotEmpty())
            <form method="GET" action="{{ route('hasil-survei-saya.index') }}" class="panel panel-pad">
                <div class="filter-grid survey-result-filter">
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
                        <label for="guru_mata_pelajaran_id">Mata pelajaran dan kelas</label>
                        <select id="guru_mata_pelajaran_id" name="guru_mata_pelajaran_id" class="select">
                            @foreach ($daftarPenugasan as $penugasan)
                                <option value="{{ $penugasan->id }}" @selected($penugasanDipilih?->id === $penugasan->id)>
                                    {{ $penugasan->mataPelajaran?->nama ?: '-' }} - {{ $penugasan->kelas?->nama ?: '-' }}{{ $penugasan->aktif ? '' : ' (Selesai)' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="actions">
                        <button type="submit" class="button button-primary">Tampilkan</button>
                    </div>
                </div>
            </form>

            <section id="rincian-survei" class="survey-result-context">
                <div>
                    <h2>{{ $penugasanDipilih?->mataPelajaran?->nama ?: '-' }} - {{ $penugasanDipilih?->kelas?->nama ?: '-' }}</h2>
                    <p>{{ $tahunPelajaranDipilih?->nama ?: '-' }} · Semester {{ ucfirst($semester) }}</p>
                </div>
                <span class="badge">Hasil anonim</span>
            </section>

            <div class="stats-grid">
                <div class="panel stat">
                    <p class="stat-label">Siswa kelas</p>
                    <p class="stat-value">{{ $jumlahSiswa }}</p>
                </div>
                <div class="panel stat active">
                    <p class="stat-label">Sudah mengisi</p>
                    <p class="stat-value">{{ $jumlahPengisi }}</p>
                </div>
                <div class="panel stat inactive">
                    <p class="stat-label">Tingkat pengisian</p>
                    <p class="stat-value">{{ number_format($persentasePengisian, 1, ',', '.') }}%</p>
                </div>
                <div class="panel stat">
                    <p class="stat-label">Rata-rata keseluruhan</p>
                    <p class="stat-value">{{ $hasilTerbuka && $rataRataKeseluruhan !== null ? number_format($rataRataKeseluruhan, 2, ',', '.') : '-' }}</p>
                </div>
            </div>

            @if (! $hasilTerbuka)
                <section class="survey-result-lock">
                    <div>
                        <strong>Hasil rinci belum ditampilkan</strong>
                        <p>Hasil akan terbuka setelah minimal {{ $minimalResponden }} siswa mengisi agar identitas jawaban tetap terlindungi.</p>
                    </div>
                    <div class="survey-result-lock-count">{{ $jumlahPengisi }}/{{ $minimalResponden }}</div>
                </section>
            @else
                <div class="survey-result-section-title">
                    <div>
                        <h2>Rincian pernyataan</h2>
                        <p>Skala 1 sangat tidak sesuai sampai 5 sangat sesuai.</p>
                    </div>
                    <div class="survey-anonymous-note"><span class="survey-anonymous-dot"></span>Tanpa nama dan NISN siswa</div>
                </div>

                <div class="survey-question-list">
                    @forelse ($rincianPertanyaan as $item)
                        <article class="survey-question-card">
                            <div class="survey-question-head">
                                <div>
                                    <h3>{{ $item['pernyataan'] }}</h3>
                                    <p class="survey-question-meta">{{ $item['jumlah_jawaban'] }} jawaban</p>
                                </div>
                                <div class="survey-question-average">
                                    <strong>{{ number_format($item['rata_rata'], 2, ',', '.') }}</strong>
                                    <span>Rata-rata</span>
                                </div>
                            </div>
                            <div class="survey-distribution" aria-label="Distribusi jawaban">
                                @foreach ($item['distribusi'] as $nilai => $distribusi)
                                    <div class="survey-distribution-row">
                                        <span class="survey-distribution-label" title="{{ $daftarPilihan[$nilai] }}">{{ $nilai }}</span>
                                        <div class="survey-distribution-track">
                                            <div class="survey-distribution-fill score-{{ $nilai }}" style="width:{{ $distribusi['persentase'] }}%"></div>
                                        </div>
                                        <span class="survey-distribution-value">{{ $distribusi['jumlah'] }} · {{ number_format($distribusi['persentase'], 1, ',', '.') }}%</span>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @empty
                        <div class="panel panel-pad empty-state">Belum ada rincian jawaban untuk periode ini.</div>
                    @endforelse
                </div>

                <div class="survey-result-section-title">
                    <div>
                        <h2>Saran siswa</h2>
                        <p>{{ $daftarSaran->count() }} saran tertulis</p>
                    </div>
                </div>

                <section class="panel panel-pad">
                    @if ($daftarSaran->isNotEmpty())
                        <ol class="survey-suggestion-list">
                            @foreach ($daftarSaran as $saran)
                                <li class="survey-suggestion-item">
                                    <p>{{ $saran['saran'] }}</p>
                                    <time datetime="{{ $saran['diisi_pada']?->toIso8601String() }}">{{ $saran['diisi_pada']?->format('d/m/Y') }}</time>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <div class="empty-state">Belum ada saran tertulis dari siswa.</div>
                    @endif
                </section>
            @endif
        @else
            <section class="panel panel-pad empty-state">
                Belum ada penugasan mata pelajaran yang terhubung dengan akun Anda.
            </section>
        @endif
    </div>
@endsection
