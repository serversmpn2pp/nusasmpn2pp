@extends('layouts.app')

@section('title', 'Monitoring Poin Siswa - NUSA')

@section('content')
    <style>
        .monitor-grid{display:grid;gap:14px;grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:20px}
        .monitor-stat{background:#fff;border:1px solid var(--border);border-radius:8px;color:inherit;display:block;min-width:0;padding:18px;text-decoration:none;transition:.2s ease}
        .monitor-stat:hover{border-color:var(--primary);box-shadow:0 8px 24px rgba(21,71,122,.1);transform:translateY(-1px)}
        .monitor-stat.is-warning{border-top:4px solid var(--secondary)}
        .monitor-stat.is-danger{border-top:4px solid #c2413a}
        .monitor-stat.is-info{border-top:4px solid #2582bd}
        .monitor-stat-label{color:var(--muted);font-size:13px;font-weight:700;margin:0}
        .monitor-stat-value{color:var(--primary-dark);display:block;font-size:32px;font-weight:900;line-height:1;margin:10px 0 8px}
        .monitor-stat-note{color:var(--muted);font-size:12px;margin:0}
        .monitor-status{display:flex;flex-direction:column;gap:7px;min-width:180px}
        .monitor-status .badge{align-self:flex-start;white-space:normal}
        .badge-info{background:#e6f2fa;color:#175b88}
        .point-value{color:var(--primary-dark);font-size:25px;font-weight:900;line-height:1}
        .point-progress{background:#e8edf3;border-radius:999px;height:7px;margin-top:9px;overflow:hidden;width:150px}
        .point-progress span{background:var(--secondary);display:block;height:100%}
        .monitor-detail{color:var(--muted);font-size:12px;margin:6px 0 0}
        .class-monitor-title{margin:28px 0 12px}
        .class-monitor-title h2{font-size:19px;margin:0 0 4px}
        .class-monitor-title p{color:var(--muted);font-size:13px;margin:0}
        .mobile-point-row{align-items:center;display:flex;gap:12px;justify-content:space-between;margin-top:14px}
        @media(max-width:1020px){.monitor-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:640px){
            .monitor-grid{grid-template-columns:1fr}
            .monitor-stat{padding:15px}
            .monitor-stat-value{font-size:28px}
            .point-progress{width:min(150px,42vw)}
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Monitoring Poin Siswa</h1>
            <p class="page-subtitle">Poin resmi, proses verifikasi, dan pelaksanaan sanksi dalam satu pemantauan.</p>
        </div>
        <div class="actions">
            <a href="{{ route('sanksi-poin-siswa.index') }}" class="button button-muted">Pelaksanaan Sanksi</a>
            @izin('poin_siswa.reward_kelola')
                <a href="{{ route('pengurangan-poin-siswa.index') }}" class="button button-primary">Pengurangan Poin</a>
            @endizin
        </div>
    </div>

    @if(session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @php
        $tautanRingkasan = fn (string $status) => route('rekap-poin-siswa.index', array_filter([
            'tahun_pelajaran_id' => $tahunPelajaranId,
            'kelas_id' => $kelasId,
            'status_perhatian' => $status,
        ]));
    @endphp
    <section class="monitor-grid" aria-label="Ringkasan monitoring poin">
        <a class="monitor-stat is-info" href="{{ $tautanRingkasan('berpoin') }}">
            <p class="monitor-stat-label">Siswa memiliki poin</p>
            <strong class="monitor-stat-value">{{ number_format($ringkasan['siswa_berpoin'], 0, ',', '.') }}</strong>
            <p class="monitor-stat-note">Dari {{ number_format($ringkasan['total_siswa'], 0, ',', '.') }} siswa pada cakupan ini</p>
        </a>
        <a class="monitor-stat is-warning" href="{{ $tautanRingkasan('mendekati_sanksi') }}">
            <p class="monitor-stat-label">Mendekati ambang sanksi</p>
            <strong class="monitor-stat-value">{{ number_format($ringkasan['mendekati_sanksi'], 0, ',', '.') }}</strong>
            <p class="monitor-stat-note">Perlu pembinaan sebelum ambang berikutnya</p>
        </a>
        <a class="monitor-stat is-warning" href="{{ $tautanRingkasan('menunggu_verifikasi') }}">
            <p class="monitor-stat-label">Menunggu verifikasi</p>
            <strong class="monitor-stat-value">{{ number_format($ringkasan['laporan_menunggu'], 0, ',', '.') }}</strong>
            <p class="monitor-stat-note">Belum dihitung sebagai poin resmi</p>
        </a>
        <a class="monitor-stat is-danger" href="{{ $tautanRingkasan('sanksi_aktif') }}">
            <p class="monitor-stat-label">Sanksi aktif</p>
            <strong class="monitor-stat-value">{{ number_format($ringkasan['sanksi_aktif'], 0, ',', '.') }}</strong>
            <p class="monitor-stat-note">Menunggu atau sedang dilaksanakan</p>
        </a>
    </section>

    <form method="GET" class="panel panel-pad" style="margin-bottom:20px">
        <div class="form-grid">
            <div class="field">
                <label for="tahun">Tahun pelajaran</label>
                <select id="tahun" name="tahun_pelajaran_id" class="select">
                    @forelse($daftarTahunPelajaran as $tahun)
                        <option value="{{ $tahun->id }}" @selected((string)$tahunPelajaranId === (string)$tahun->id)>
                            {{ $tahun->nama }}{{ $tahun->aktif ? ' (aktif)' : '' }}
                        </option>
                    @empty
                        <option value="">Belum ada tahun pelajaran</option>
                    @endforelse
                </select>
            </div>
            <div class="field">
                <label for="kelas">Kelas</label>
                <select id="kelas" name="kelas_id" class="select">
                    <option value="">Semua kelas</option>
                    @foreach($daftarKelas as $kelas)
                        <option value="{{ $kelas->id }}" @selected((string)$kelasId === (string)$kelas->id)>{{ $kelas->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="status_perhatian">Status perhatian</label>
                <select id="status_perhatian" name="status_perhatian" class="select">
                    <option value="">Semua status</option>
                    <option value="berpoin" @selected($statusPerhatian === 'berpoin')>Memiliki poin</option>
                    <option value="mendekati_sanksi" @selected($statusPerhatian === 'mendekati_sanksi')>Mendekati sanksi</option>
                    <option value="menunggu_verifikasi" @selected($statusPerhatian === 'menunggu_verifikasi')>Menunggu verifikasi</option>
                    <option value="sanksi_aktif" @selected($statusPerhatian === 'sanksi_aktif')>Sanksi aktif</option>
                </select>
            </div>
            <div class="field">
                <label for="kata">Cari siswa</label>
                <input id="kata" name="kata_kunci" value="{{ $kataKunci }}" class="input" placeholder="Nama, NIS, atau NISN">
            </div>
        </div>
        <div class="actions" style="justify-content:flex-end;margin-top:12px">
            <a href="{{ route('rekap-poin-siswa.index') }}" class="button button-muted">Reset</a>
            <button class="button button-dark">Terapkan</button>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Kelas & pendamping</th>
                        <th>Status monitoring</th>
                        <th>Poin resmi</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarSiswa as $siswa)
                        @php
                            $anggota = $siswa->anggotaKelas->first();
                            $guru = $siswa->penugasanGuruWaliSiswa->first()?->guruWali;
                            $poin = max(0, (int)$siswa->total_poin);
                            $indikator = $siswa->indikator_monitoring;
                        @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $siswa->nama_lengkap }}</p>
                                <p class="person-meta">NISN {{ $siswa->nisn ?: '-' }}</p>
                            </td>
                            <td>
                                <p class="person-name">{{ $anggota?->kelas?->nama ?: '-' }}</p>
                                <p class="person-meta">Guru Wali: {{ $guru?->nama_lengkap ?: '-' }}</p>
                            </td>
                            <td>
                                <div class="monitor-status">
                                    <span class="{{ $indikator['kelas'] }}">{{ $indikator['label'] }}</span>
                                    @if($siswa->laporan_menunggu_count > 0)
                                        <span class="person-meta">{{ $siswa->laporan_menunggu_count }} laporan menunggu</span>
                                    @endif
                                    @if($siswa->sanksi_aktif_count > 0)
                                        <span class="person-meta">{{ $siswa->sanksi_aktif_count }} sanksi aktif</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="point-value">{{ $poin }}</span>
                                <div class="point-progress" aria-hidden="true">
                                    <span style="width:{{ $indikator['persentase'] }}%"></span>
                                </div>
                                <p class="monitor-detail">
                                    @if($indikator['aturanBerikutnya'])
                                        {{ $indikator['jarak'] }} poin menuju {{ $indikator['aturanBerikutnya']->nama }}
                                    @elseif($poin > 0)
                                        Ambang tertinggi
                                    @else
                                        Belum ada poin resmi
                                    @endif
                                </p>
                            </td>
                            <td class="text-right">
                                <a class="button button-primary button-sm"
                                    href="{{ route('rekap-poin-siswa.show', ['siswa' => $siswa, 'tahun_pelajaran_id' => $tahunPelajaranId]) }}">
                                    Lihat Profil
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Belum ada siswa yang sesuai dengan cakupan dan filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse($daftarSiswa as $siswa)
                @php
                    $indikator = $siswa->indikator_monitoring;
                    $poin = max(0, (int)$siswa->total_poin);
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $siswa->nama_lengkap }}</p>
                            <p class="person-meta">{{ $siswa->anggotaKelas->first()?->kelas?->nama ?: '-' }} · NISN {{ $siswa->nisn ?: '-' }}</p>
                        </div>
                        <span class="{{ $indikator['kelas'] }}">{{ $indikator['label'] }}</span>
                    </div>
                    <div class="mobile-point-row">
                        <div>
                            <span class="point-value">{{ $poin }}</span><span class="person-meta"> poin resmi</span>
                            <div class="point-progress" aria-hidden="true">
                                <span style="width:{{ $indikator['persentase'] }}%"></span>
                            </div>
                        </div>
                        <a class="button button-primary button-sm"
                            href="{{ route('rekap-poin-siswa.show', ['siswa' => $siswa, 'tahun_pelajaran_id' => $tahunPelajaranId]) }}">
                            Lihat Profil
                        </a>
                    </div>
                    @if($siswa->laporan_menunggu_count > 0 || $siswa->sanksi_aktif_count > 0)
                        <p class="monitor-detail">{{ $siswa->laporan_menunggu_count }} laporan menunggu · {{ $siswa->sanksi_aktif_count }} sanksi aktif</p>
                    @endif
                </article>
            @empty
                <div class="empty-state">Belum ada siswa yang sesuai dengan cakupan dan filter ini.</div>
            @endforelse
        </div>
    </section>

    @if($daftarSiswa->hasPages())
        <nav class="pagination-simple">
            <span>Halaman {{ $daftarSiswa->currentPage() }} dari {{ $daftarSiswa->lastPage() }}</span>
            <div class="actions">
                @if($daftarSiswa->onFirstPage())
                    <span class="button button-muted">Sebelumnya</span>
                @else
                    <a href="{{ $daftarSiswa->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif
                @if($daftarSiswa->hasMorePages())
                    <a href="{{ $daftarSiswa->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @endif
            </div>
        </nav>
    @endif

    @if($ringkasanKelas->isNotEmpty())
        <div class="class-monitor-title">
            <h2>Ringkasan per kelas</h2>
            <p>Membantu melihat kelas yang memerlukan tindak lanjut lebih dahulu.</p>
        </div>
        <section class="panel">
            <div class="table-wrap">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th>Jumlah siswa</th>
                            <th>Siswa berpoin</th>
                            <th>Total poin</th>
                            <th>Menunggu verifikasi</th>
                            <th>Sanksi aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ringkasanKelas as $item)
                            <tr>
                                <td><p class="person-name">{{ $item['kelas']->nama }}</p></td>
                                <td>{{ $item['jumlah_siswa'] }}</td>
                                <td>{{ $item['siswa_berpoin'] }}</td>
                                <td><strong>{{ $item['total_poin'] }}</strong></td>
                                <td><span class="{{ $item['menunggu'] > 0 ? 'badge badge-warning' : 'badge badge-active' }}">{{ $item['menunggu'] }}</span></td>
                                <td><span class="{{ $item['sanksi_aktif'] > 0 ? 'badge badge-danger' : 'badge badge-active' }}">{{ $item['sanksi_aktif'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
