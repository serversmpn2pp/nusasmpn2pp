@extends('layouts.app')

@section('title', 'Leger STS - NUSA')

@push('styles')
    <style>
        .leger-filter { display:grid; grid-template-columns:minmax(240px,1fr) minmax(220px,.7fr) auto; gap:14px; align-items:end; margin-bottom:18px; padding:18px; }
        .leger-filter .field { margin:0; }
        .leger-header-actions { display:flex; flex-wrap:wrap; gap:8px; }
        .leger-tabs { display:inline-grid; grid-template-columns:1fr 1fr; gap:4px; margin-bottom:14px; padding:4px; border:1px solid var(--line); border-radius:7px; background:#eef2f5; }
        .leger-tab { min-width:150px; padding:9px 14px; border-radius:5px; color:var(--muted); font-size:.8rem; font-weight:800; text-align:center; text-decoration:none; }
        .leger-tab:hover { color:var(--primary-dark); }
        .leger-tab.is-active { background:#fff; color:var(--primary-dark); box-shadow:0 1px 3px rgba(15,45,75,.12); }
        .leger-summary { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
        .leger-summary.is-level { grid-template-columns:repeat(6,minmax(0,1fr)); }
        .leger-summary .stat { min-height:104px; }
        .leger-summary .stat-value { font-size:1.45rem; }
        .leger-summary .stat-note { margin:6px 0 0; color:var(--muted); font-size:.72rem; line-height:1.35; }
        .leger-summary .is-accent { border-top:3px solid var(--accent); }
        .leger-summary .is-primary { border-top:3px solid var(--primary); }
        .leger-stat-layout { display:grid; grid-template-columns:minmax(280px,.72fr) minmax(0,1.28fr); gap:16px; margin-bottom:18px; }
        .leger-stat-panel { padding:18px; }
        .leger-stat-panel h2 { margin:0; font-size:1rem; }
        .leger-stat-panel > p { margin:5px 0 0; color:var(--muted); font-size:.78rem; }
        .leger-distribution { display:grid; gap:13px; margin-top:18px; }
        .leger-distribution-row { display:grid; grid-template-columns:112px minmax(80px,1fr) 58px; gap:10px; align-items:center; }
        .leger-distribution-label { color:var(--ink); font-size:.78rem; font-weight:750; }
        .leger-bar { height:9px; overflow:hidden; border-radius:3px; background:#edf1f4; }
        .leger-bar span { display:block; width:var(--bar-width); height:100%; background:var(--primary); }
        .leger-distribution-row[data-level="cukup"] .leger-bar span { background:#d9a915; }
        .leger-distribution-row[data-level="baik"] .leger-bar span { background:#2f7b69; }
        .leger-distribution-row[data-level="sangat_baik"] .leger-bar span { background:#17834b; }
        .leger-distribution-value { color:var(--muted); font-size:.74rem; text-align:right; font-variant-numeric:tabular-nums; }
        .leger-subject-wrap { margin-top:13px; overflow:auto; border:1px solid var(--line); border-radius:6px; }
        .leger-subject-table { width:100%; min-width:610px; border-collapse:collapse; }
        .leger-subject-table th,.leger-subject-table td { padding:10px 11px; border-bottom:1px solid var(--line); text-align:left; }
        .leger-subject-table th { background:#f4f7fa; color:var(--muted); font-size:.7rem; text-transform:uppercase; }
        .leger-subject-table tbody tr:last-child td { border-bottom:0; }
        .leger-subject-table .numeric { text-align:right; font-variant-numeric:tabular-nums; }
        .leger-subject-name { display:flex; align-items:center; gap:7px; font-weight:750; }
        .leger-top-badge { display:inline-flex; padding:2px 6px; border:1px solid #e0bb3a; border-radius:4px; background:#fff9d9; color:#695300; font-size:.63rem; font-weight:800; white-space:nowrap; }
        .leger-completeness { display:flex; align-items:center; justify-content:flex-end; gap:8px; }
        .leger-completeness-bar { width:72px; height:7px; overflow:hidden; border-radius:3px; background:#e8edf1; }
        .leger-completeness-bar span { display:block; width:var(--bar-width); height:100%; background:#23835a; }
        .leger-main { padding:0; overflow:hidden; }
        .leger-main-head { display:flex; flex-wrap:wrap; align-items:end; justify-content:space-between; gap:14px; padding:18px; border-bottom:1px solid var(--line); }
        .leger-main-head h2 { margin:0; font-size:1.05rem; }
        .leger-main-head p { margin:5px 0 0; color:var(--muted); font-size:.78rem; }
        .leger-search { width:min(100%,320px); }
        .leger-table-wrap { overflow:auto; max-height:68vh; }
        .leger-table { width:max-content; min-width:100%; border-collapse:separate; border-spacing:0; }
        .leger-table th,.leger-table td { height:44px; padding:8px 10px; border-right:1px solid var(--line); border-bottom:1px solid var(--line); background:#fff; vertical-align:middle; }
        .leger-table th { position:sticky; top:0; z-index:3; height:54px; background:#eaf1f7; color:var(--primary-dark); font-size:.69rem; line-height:1.25; text-align:center; }
        .leger-table tbody tr:hover td { background:#f8fbfd; }
        .leger-table tbody tr[hidden] { display:none; }
        .leger-table .rank-column { position:sticky; left:0; z-index:2; width:58px; min-width:58px; text-align:center; }
        .leger-table th.rank-column { z-index:5; background:#eaf1f7; }
        .leger-table .student-column { position:sticky; left:58px; z-index:2; width:230px; min-width:230px; box-shadow:2px 0 0 var(--line); }
        .leger-table th.student-column { z-index:5; background:#eaf1f7; text-align:left; }
        .leger-table .subject-column { width:98px; min-width:98px; text-align:center; font-variant-numeric:tabular-nums; }
        .leger-table .result-column { width:88px; min-width:88px; text-align:center; font-variant-numeric:tabular-nums; }
        .leger-table .status-column { width:150px; min-width:150px; }
        .leger-rank { display:inline-grid; width:30px; height:30px; place-items:center; border:1px solid #a9bed2; border-radius:50%; color:var(--primary-dark); font-weight:850; }
        .leger-rank.is-top { border-color:var(--accent); background:#fff7cf; color:#624d00; }
        .leger-student strong,.leger-student span { display:block; }
        .leger-student strong { overflow-wrap:anywhere; }
        .leger-student span { margin-top:3px; color:var(--muted); font-size:.68rem; }
        .leger-score { font-weight:750; }
        .leger-score.is-empty { color:#a1aab4; font-weight:500; }
        .leger-status { display:inline-flex; max-width:100%; padding:4px 7px; border-radius:4px; background:#edf1f4; color:#526170; font-size:.68rem; font-weight:750; line-height:1.4; }
        .leger-status.is-ranked { background:#e8f6ee; color:#146c3a; }
        .leger-status.is-provisional { background:#fff5d9; color:#805500; }
        .leger-progress { display:block; margin-top:5px; color:var(--muted); font-size:.68rem; line-height:1.5; }
        .leger-draft { display:block; margin-top:3px; color:#805500; font-size:.65rem; font-weight:600; }
        .leger-ranking-note { margin-bottom:18px; padding:12px 16px; border-left:3px solid var(--accent); background:#fff; font-size:.82rem; line-height:1.6; }
        .leger-ranking-note strong { color:var(--primary-dark); }
        .leger-ranking-note p { margin:4px 0 0; color:var(--muted); }
        .leger-empty { padding:30px; color:var(--muted); text-align:center; }
        .leger-footnote { display:flex; flex-wrap:wrap; justify-content:space-between; gap:8px 18px; padding:12px 18px; border-top:1px solid var(--line); background:#fafbfc; color:var(--muted); font-size:.72rem; }
        @media (max-width:1180px) { .leger-summary,.leger-summary.is-level { grid-template-columns:repeat(3,minmax(0,1fr)); } }
        @media (max-width:820px) {
            .leger-filter,.leger-stat-layout { grid-template-columns:1fr; }
            .leger-summary { grid-template-columns:1fr 1fr; }
            .leger-filter .button { width:100%; }
            .leger-header-actions { width:100%; }
            .leger-header-actions .button { flex:1 1 180px; }
        }
        @media (max-width:520px) {
            .leger-summary { grid-template-columns:1fr; }
            .leger-tabs { display:grid; width:100%; }
            .leger-tab { min-width:0; }
            .leger-main-head { align-items:stretch; }
            .leger-search { width:100%; }
            .leger-table .student-column { left:auto; width:190px; min-width:190px; box-shadow:none; }
        }
    </style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian</p>
            <h1 class="page-title">Leger STS</h1>
            <p class="help-text" style="margin-top:7px;">Rekap nilai, ranking, dan statistik capaian per kelas maupun tingkat.</p>
        </div>
        <div class="leger-header-actions">
            <a class="button button-muted" href="{{ route('rapor-sts.index', ['kegiatan_id' => $kegiatan?->id, 'kelas_id' => $kelas?->id]) }}">Buka Rapor STS</a>
            <a class="button button-muted" href="{{ route('leger-sts.penghargaan', ['cakupan' => $mode, 'kegiatan_id' => $kegiatan?->id, 'kelas_id' => $mode === 'kelas' ? $kelas?->id : null, 'tingkat' => $mode === 'tingkat' ? $tingkat : null]) }}">Kandidat Penghargaan</a>
            @if ($kegiatan && (($mode === 'kelas' && $kelas) || ($mode === 'tingkat' && $tingkat !== null)))
                <a class="button button-primary" target="_blank" rel="noopener" href="{{ route('leger-sts.cetak', ['mode' => $mode, 'kegiatan_id' => $kegiatan->id, 'kelas_id' => $mode === 'kelas' ? $kelas?->id : null, 'tingkat' => $mode === 'tingkat' ? $tingkat : null]) }}">Cetak Leger</a>
            @endif
        </div>
    </div>

    <nav class="leger-tabs" aria-label="Jenis leger">
        <a class="leger-tab {{ $mode === 'kelas' ? 'is-active' : '' }}" href="{{ route('leger-sts.index', ['mode' => 'kelas', 'kegiatan_id' => $kegiatan?->id, 'kelas_id' => $kelas?->id]) }}">Per Kelas</a>
        <a class="leger-tab {{ $mode === 'tingkat' ? 'is-active' : '' }}" href="{{ route('leger-sts.index', ['mode' => 'tingkat', 'kegiatan_id' => $kegiatan?->id, 'tingkat' => $tingkat]) }}">Per Tingkat</a>
    </nav>

    <form class="panel leger-filter" method="GET" action="{{ route('leger-sts.index') }}">
        <input type="hidden" name="mode" value="{{ $mode }}">
        <div class="field">
            <label for="leger-kegiatan">Kegiatan STS</label>
            <select class="select" id="leger-kegiatan" name="kegiatan_id" required>
                @forelse ($daftarKegiatan as $pilihan)
                    <option value="{{ $pilihan->id }}" @selected($kegiatan?->id === $pilihan->id)>{{ $pilihan->nama }} · {{ $pilihan->tahunPelajaran->nama }}</option>
                @empty
                    <option value="">Belum ada kegiatan STS</option>
                @endforelse
            </select>
        </div>
        <div class="field">
            @if ($mode === 'tingkat')
                <label for="leger-tingkat">Tingkat</label>
                <select class="select" id="leger-tingkat" name="tingkat" required @disabled($daftarTingkat->isEmpty())>
                    @forelse ($daftarTingkat as $pilihan)
                        <option value="{{ $pilihan }}" @selected((int) $tingkat === (int) $pilihan)>Tingkat {{ $pilihan }}</option>
                    @empty
                        <option value="">Belum ada tingkat</option>
                    @endforelse
                </select>
            @else
                <label for="leger-kelas">Kelas</label>
                <select class="select" id="leger-kelas" name="kelas_id" required @disabled($daftarKelas->isEmpty())>
                    @forelse ($daftarKelas as $pilihan)
                        <option value="{{ $pilihan->id }}" @selected($kelas?->id === $pilihan->id)>{{ $pilihan->nama }}</option>
                    @empty
                        <option value="">Belum ada kelas</option>
                    @endforelse
                </select>
            @endif
        </div>
        <button class="button button-primary" type="submit" @disabled($daftarKegiatan->isEmpty() || ($mode === 'tingkat' ? $daftarTingkat->isEmpty() : $daftarKelas->isEmpty()))>Terapkan</button>
    </form>

    @php $legerAktif = $mode === 'tingkat' ? $legerTingkat : $leger; @endphp
    @if ($legerAktif && $legerAktif['mapel']->isNotEmpty())
        <section class="leger-ranking-note" aria-label="Dasar perhitungan ranking" data-leger-calculation>
            <strong>{{ $legerAktif['ranking_sementara'] ? 'Ranking sementara' : 'Nilai lengkap dan final' }}</strong>
            <p>Rata-rata = jumlah nilai tersedia dibagi {{ $legerAktif['mapel']->count() }} mapel yang ditetapkan{{ $mode === 'tingkat' ? ' untuk seluruh siswa paralel' : '' }}. Nilai kosong tetap belum tersedia, bukan nilai nol yang disimpan.</p>
            <p>{{ $legerAktif['ringkasan']['lengkap_final'] }}/{{ $legerAktif['ringkasan']['jumlah_siswa'] }} siswa memiliki seluruh nilai final. @if ($legerAktif['ranking_sementara'])Ranking dapat berubah saat nilai masuk, dikoreksi, atau difinalisasi; belum menjadi dasar penetapan penghargaan.@endif</p>
        </section>
    @endif

    @if ($mode === 'tingkat')
        @include('leger-sts.tingkat')
    @elseif ($leger && $kelas && $kegiatan)
        @php
            $ringkasan = $leger['ringkasan'];
            $mapelTertinggi = $leger['mapel_tertinggi'];
        @endphp

        <section class="leger-summary" aria-label="Ringkasan leger">
            <div class="panel stat"><p class="stat-label">Jumlah siswa</p><p class="stat-value">{{ $ringkasan['jumlah_siswa'] }}</p><p class="stat-note">Siswa aktif di {{ $kelas->nama }}</p></div>
            <div class="panel stat is-primary"><p class="stat-label">Masuk ranking</p><p class="stat-value">{{ $ringkasan['masuk_ranking'] }}</p><p class="stat-note">{{ $ringkasan['belum_masuk_ranking'] }} siswa belum masuk ranking</p></div>
            <div class="panel stat"><p class="stat-label">Rata-rata kelas</p><p class="stat-value">{{ $ringkasan['rata_kelas'] === null ? '-' : number_format($ringkasan['rata_kelas'], 2, ',', '.') }}</p><p class="stat-note">Dari siswa yang masuk ranking</p></div>
            <div class="panel stat"><p class="stat-label">Rata-rata tertinggi</p><p class="stat-value">{{ $ringkasan['rata_tertinggi'] === null ? '-' : number_format($ringkasan['rata_tertinggi'], 2, ',', '.') }}</p><p class="stat-note">Terendah {{ $ringkasan['rata_terendah'] === null ? '-' : number_format($ringkasan['rata_terendah'], 2, ',', '.') }}</p></div>
            <div class="panel stat is-accent"><p class="stat-label">Mapel tertinggi</p><p class="stat-value" style="font-size:1rem;">{{ $mapelTertinggi['mapel']->nama ?? '-' }}</p><p class="stat-note">{{ $mapelTertinggi ? 'Rata-rata '.number_format($mapelTertinggi['rata'], 2, ',', '.').' · '.$mapelTertinggi['jumlah_nilai'].' nilai tersedia' : 'Belum ada nilai' }}</p></div>
        </section>

        <div class="leger-stat-layout">
            <section class="panel leger-stat-panel">
                <h2>Sebaran capaian siswa</h2>
                <p>Berdasarkan rata-rata {{ $leger['ranking_sementara'] ? 'sementara ' : '' }}siswa yang memiliki nilai.</p>
                <div class="leger-distribution">
                    @foreach ($leger['distribusi'] as $item)
                        <div class="leger-distribution-row" data-level="{{ $item['kode'] }}">
                            <span class="leger-distribution-label">{{ $item['label'] }}</span>
                            <span class="leger-bar" aria-label="{{ $item['label'] }} {{ $item['persentase'] }} persen"><span style="--bar-width:{{ $item['persentase'] }}%"></span></span>
                            <span class="leger-distribution-value">{{ $item['jumlah'] }} siswa</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="panel leger-stat-panel">
                <h2>Statistik mata pelajaran</h2>
                <p>Rata-rata mapel dihitung dari nilai tersedia; nilai draf ditandai.</p>
                <div class="leger-subject-wrap">
                    <table class="leger-subject-table">
                        <thead><tr><th>Mata pelajaran</th><th class="numeric">Nilai tersedia</th><th class="numeric">Rata-rata</th><th class="numeric">Tertinggi</th><th class="numeric">Terendah</th></tr></thead>
                        <tbody>
                            @forelse ($leger['statistik_mapel'] as $statistik)
                                <tr>
                                    <td><span class="leger-subject-name">{{ $statistik['mapel']->nama }} @if ($statistik['tertinggi'])<span class="leger-top-badge">Tertinggi</span>@endif</span></td>
                                    <td class="numeric">{{ $statistik['jumlah_nilai'] }}/{{ $statistik['jumlah_siswa'] }}<span class="leger-progress">{{ $statistik['jumlah_final'] }} final · {{ $statistik['jumlah_draf'] }} draf</span></td>
                                    <td class="numeric"><strong>{{ $statistik['rata'] === null ? '-' : number_format($statistik['rata'], 2, ',', '.') }}</strong></td>
                                    <td class="numeric">{{ $statistik['tertinggi_nilai'] === null ? '-' : number_format($statistik['tertinggi_nilai'], 2, ',', '.') }}</td>
                                    <td class="numeric">{{ $statistik['terendah_nilai'] === null ? '-' : number_format($statistik['terendah_nilai'], 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">Belum ada mata pelajaran untuk kelas ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="panel leger-main">
            <div class="leger-main-head">
                <div>
                    <h2>Ranking {{ $leger['ranking_sementara'] ? 'sementara ' : '' }}kelas {{ $kelas->nama }}</h2>
                    <p>{{ $kegiatan->nama }} · Tahun Pelajaran {{ $kegiatan->tahunPelajaran->nama }}</p>
                </div>
                <div class="field leger-search">
                    <label for="leger-search">Cari siswa</label>
                    <input class="input" id="leger-search" type="search" placeholder="Nama, NIS, atau NISN" autocomplete="off" data-leger-search>
                </div>
            </div>

            @if ($leger['baris']->isNotEmpty())
                <div class="leger-table-wrap">
                    <table class="leger-table">
                        <thead>
                            <tr>
                                <th class="rank-column">Rank</th>
                                <th class="student-column">Siswa</th>
                                @foreach ($leger['mapel'] as $mapel)
                                    <th class="subject-column" title="{{ $mapel->nama }}">{{ $mapel->nama }}</th>
                                @endforeach
                                <th class="result-column">Jumlah</th>
                                <th class="result-column">Rata-rata</th>
                                <th class="status-column">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($leger['baris'] as $item)
                                @php
                                    $siswa = $item['anggota']->siswa;
                                    $teksPencarian = mb_strtolower(implode(' ', [$siswa->nama_lengkap, $siswa->nis, $siswa->nisn, $item['anggota']->nomor_absen]));
                                @endphp
                                <tr data-leger-row data-search="{{ $teksPencarian }}">
                                    <td class="rank-column">
                                        @if ($item['ranking'])<span class="leger-rank {{ $item['ranking'] <= 3 ? 'is-top' : '' }}">{{ $item['ranking'] }}</span>@else<span class="leger-score is-empty">-</span>@endif
                                    </td>
                                    <td class="student-column">
                                        <div class="leger-student"><strong>{{ $siswa->nama_lengkap }}</strong><span>Absen {{ $item['anggota']->nomor_absen ?: '-' }} · NISN {{ $siswa->nisn ?: '-' }}</span></div>
                                    </td>
                                    @foreach ($item['nilai'] as $nilai)
                                        <td class="subject-column" title="{{ $nilai['nilai'] === null ? $nilai['status'] : $nilai['keterangan'] }}">
                                            @if ($nilai['nilai'] !== null)<span class="leger-score">{{ number_format($nilai['nilai'], 2, ',', '.') }}</span>@if ($nilai['draf'])<small class="leger-draft">Draf</small>@endif
                                            @elseif ($nilai['dikecualikan'])<span class="leger-score is-empty">TM</span>
                                            @else<span class="leger-score is-empty">-</span>@endif
                                        </td>
                                    @endforeach
                                    <td class="result-column"><span class="leger-score">{{ $item['jumlah_leger'] === null ? '-' : number_format($item['jumlah_leger'], 2, ',', '.') }}</span></td>
                                    <td class="result-column"><span class="leger-score">{{ $item['rata_leger'] === null ? '-' : number_format($item['rata_leger'], 2, ',', '.') }}</span></td>
                                    <td class="status-column"><span class="leger-status {{ $item['nilai_final_lengkap'] ? 'is-ranked' : ($item['layak_ranking'] ? 'is-provisional' : '') }}">{{ $item['status_ranking'] }}</span><span class="leger-progress">{{ $item['jumlah_nilai_tersedia'] }}/{{ $item['jumlah_mapel'] }} mapel · {{ $item['jumlah_nilai_final'] }} final · {{ $item['jumlah_nilai_draf'] }} draf</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="leger-footnote"><span>Ranking memakai rata-rata dua desimal. Nilai yang sama memperoleh ranking yang sama dengan urutan berikutnya dilewati.</span><span>TM = Tidak mengikuti STS · <span data-leger-count>{{ $leger['baris']->count() }}</span> siswa ditampilkan</span></div>
            @else
                <div class="leger-empty">Belum ada siswa aktif pada kelas ini.</div>
            @endif
        </section>
    @else
        <div class="panel leger-empty">Belum ada kegiatan STS atau kelas dalam kewenangan Anda.</div>
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            const search = document.querySelector('[data-leger-search]');
            if (!search) return;
            const rows = [...document.querySelectorAll('[data-leger-row]')];
            const count = document.querySelector('[data-leger-count]');

            search.addEventListener('input', () => {
                const query = search.value.trim().toLocaleLowerCase('id');
                let visible = 0;
                rows.forEach((row) => {
                    row.hidden = query !== '' && !row.dataset.search.includes(query);
                    if (!row.hidden) visible++;
                });
                if (count) count.textContent = visible;
            });
        })();
    </script>
@endpush
