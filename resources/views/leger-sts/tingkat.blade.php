@if ($legerTingkat && $kegiatan)
    @php
        $ringkasanTingkat = $legerTingkat['ringkasan'];
        $kelasTertinggi = $ringkasanTingkat['kelas_tertinggi'];
        $mapelTertinggiTingkat = $legerTingkat['mapel_tertinggi'];
    @endphp

    <section class="leger-summary is-level" aria-label="Ringkasan leger tingkat">
        <div class="panel stat"><p class="stat-label">Jumlah kelas</p><p class="stat-value">{{ $ringkasanTingkat['jumlah_kelas'] }}</p><p class="stat-note">Kelas paralel tingkat {{ $tingkat }}</p></div>
        <div class="panel stat"><p class="stat-label">Jumlah siswa</p><p class="stat-value">{{ $ringkasanTingkat['jumlah_siswa'] }}</p><p class="stat-note">Seluruh siswa aktif</p></div>
        <div class="panel stat is-primary"><p class="stat-label">Masuk ranking</p><p class="stat-value">{{ $ringkasanTingkat['masuk_ranking'] }}</p><p class="stat-note">{{ $ringkasanTingkat['belum_masuk_ranking'] }} belum masuk ranking</p></div>
        <div class="panel stat"><p class="stat-label">Rata-rata tingkat</p><p class="stat-value">{{ $ringkasanTingkat['rata_tingkat'] === null ? '-' : number_format($ringkasanTingkat['rata_tingkat'], 2, ',', '.') }}</p><p class="stat-note">Dari siswa yang masuk ranking</p></div>
        <div class="panel stat"><p class="stat-label">Kelas tertinggi</p><p class="stat-value" style="font-size:1rem;">{{ $kelasTertinggi['kelas']->nama ?? '-' }}</p><p class="stat-note">{{ $kelasTertinggi ? 'Rata-rata '.number_format($kelasTertinggi['rata'], 2, ',', '.') : 'Belum ada nilai lengkap' }}</p></div>
        <div class="panel stat is-accent"><p class="stat-label">Mapel tertinggi</p><p class="stat-value" style="font-size:1rem;">{{ $mapelTertinggiTingkat['mapel']->nama ?? '-' }}</p><p class="stat-note">{{ $mapelTertinggiTingkat ? 'Rata-rata '.number_format($mapelTertinggiTingkat['rata'], 2, ',', '.') : 'Belum ada nilai final' }}</p></div>
    </section>

    <div class="leger-stat-layout">
        <section class="panel leger-stat-panel">
            <h2>Sebaran capaian tingkat {{ $tingkat }}</h2>
            <p>Berdasarkan rata-rata siswa yang masuk ranking paralel.</p>
            <div class="leger-distribution">
                @foreach ($legerTingkat['distribusi'] as $item)
                    <div class="leger-distribution-row" data-level="{{ $item['kode'] }}">
                        <span class="leger-distribution-label">{{ $item['label'] }}</span>
                        <span class="leger-bar" aria-label="{{ $item['label'] }} {{ $item['persentase'] }} persen"><span style="--bar-width:{{ $item['persentase'] }}%"></span></span>
                        <span class="leger-distribution-value">{{ $item['jumlah'] }} siswa</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="panel leger-stat-panel">
            <h2>Perbandingan kelas</h2>
            <p>Rata-rata berasal dari siswa yang seluruh nilai STS-nya sudah final.</p>
            <div class="leger-subject-wrap">
                <table class="leger-subject-table">
                    <thead><tr><th class="numeric">Rank</th><th>Kelas</th><th class="numeric">Siswa</th><th class="numeric">Kelengkapan</th><th class="numeric">Rata-rata</th><th class="numeric">Tertinggi</th><th class="numeric">Terendah</th></tr></thead>
                    <tbody>
                        @forelse ($legerTingkat['statistik_kelas'] as $statistik)
                            <tr>
                                <td class="numeric"><strong>{{ $statistik['ranking'] ?? '-' }}</strong></td>
                                <td><strong>{{ $statistik['kelas']->nama }}</strong></td>
                                <td class="numeric">{{ $statistik['masuk_ranking'] }}/{{ $statistik['jumlah_siswa'] }}</td>
                                <td class="numeric"><span class="leger-completeness"><span class="leger-completeness-bar"><span style="--bar-width:{{ $statistik['kelengkapan'] }}%"></span></span>{{ number_format($statistik['kelengkapan'], 0) }}%</span></td>
                                <td class="numeric"><strong>{{ $statistik['rata'] === null ? '-' : number_format($statistik['rata'], 2, ',', '.') }}</strong></td>
                                <td class="numeric">{{ $statistik['tertinggi'] === null ? '-' : number_format($statistik['tertinggi'], 2, ',', '.') }}</td>
                                <td class="numeric">{{ $statistik['terendah'] === null ? '-' : number_format($statistik['terendah'], 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">Belum ada kelas pada tingkat ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="panel leger-stat-panel" style="margin-bottom:18px;">
        <h2>Statistik mata pelajaran tingkat {{ $tingkat }}</h2>
        <p>Bandingkan rata-rata keseluruhan tingkat dengan rata-rata pada setiap kelas.</p>
        <div class="leger-subject-wrap">
            <table class="leger-subject-table">
                <thead>
                    <tr>
                        <th>Mata pelajaran</th>
                        <th class="numeric">Nilai final</th>
                        <th class="numeric">Rata-rata tingkat</th>
                        @foreach ($legerTingkat['kelas'] as $kelasTingkat)
                            <th class="numeric">{{ $kelasTingkat->nama }}</th>
                        @endforeach
                        <th class="numeric">Tertinggi</th>
                        <th class="numeric">Terendah</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($legerTingkat['statistik_mapel'] as $statistik)
                        <tr>
                            <td><span class="leger-subject-name">{{ $statistik['mapel']->nama }} @if ($statistik['tertinggi'])<span class="leger-top-badge">Tertinggi</span>@endif</span></td>
                            <td class="numeric">{{ $statistik['jumlah_nilai'] }}/{{ $statistik['jumlah_siswa'] }}</td>
                            <td class="numeric"><strong>{{ $statistik['rata'] === null ? '-' : number_format($statistik['rata'], 2, ',', '.') }}</strong></td>
                            @foreach ($statistik['per_kelas'] as $rataKelas)
                                <td class="numeric" title="{{ $rataKelas['jumlah_nilai'] }}/{{ $rataKelas['jumlah_siswa'] }} nilai final">{{ $rataKelas['rata'] === null ? '-' : number_format($rataKelas['rata'], 2, ',', '.') }}</td>
                            @endforeach
                            <td class="numeric">{{ $statistik['tertinggi_nilai'] === null ? '-' : number_format($statistik['tertinggi_nilai'], 2, ',', '.') }}</td>
                            <td class="numeric">{{ $statistik['terendah_nilai'] === null ? '-' : number_format($statistik['terendah_nilai'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="20">Belum ada mata pelajaran pada tingkat ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel leger-main">
        <div class="leger-main-head">
            <div>
                <h2>Ranking paralel tingkat {{ $tingkat }}</h2>
                <p>{{ $kegiatan->nama }} · {{ $ringkasanTingkat['jumlah_kelas'] }} kelas · Tahun Pelajaran {{ $kegiatan->tahunPelajaran->nama }}</p>
            </div>
            <div class="field leger-search">
                <label for="leger-search">Cari siswa atau kelas</label>
                <input class="input" id="leger-search" type="search" placeholder="Nama, NIS, NISN, atau kelas" autocomplete="off" data-leger-search>
            </div>
        </div>

        @if ($legerTingkat['baris']->isNotEmpty())
            <div class="leger-table-wrap">
                <table class="leger-table">
                    <thead>
                        <tr>
                            <th class="rank-column">Rank</th>
                            <th class="student-column">Siswa</th>
                            <th class="result-column">Kelas</th>
                            @foreach ($legerTingkat['mapel'] as $mapel)
                                <th class="subject-column" title="{{ $mapel->nama }}">{{ $mapel->nama }}</th>
                            @endforeach
                            <th class="result-column">Jumlah</th>
                            <th class="result-column">Rata-rata</th>
                            <th class="status-column">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($legerTingkat['baris'] as $item)
                            @php
                                $siswaTingkat = $item['anggota']->siswa;
                                $teksPencarianTingkat = mb_strtolower(implode(' ', [$siswaTingkat->nama_lengkap, $siswaTingkat->nis, $siswaTingkat->nisn, $item['kelas']->nama]));
                            @endphp
                            <tr data-leger-row data-search="{{ $teksPencarianTingkat }}">
                                <td class="rank-column">@if ($item['ranking'])<span class="leger-rank {{ $item['ranking'] <= 3 ? 'is-top' : '' }}">{{ $item['ranking'] }}</span>@else<span class="leger-score is-empty">-</span>@endif</td>
                                <td class="student-column"><div class="leger-student"><strong>{{ $siswaTingkat->nama_lengkap }}</strong><span>NISN {{ $siswaTingkat->nisn ?: '-' }}</span></div></td>
                                <td class="result-column"><strong>{{ $item['kelas']->nama }}</strong></td>
                                @foreach ($item['nilai'] as $nilai)
                                    <td class="subject-column" title="{{ $nilai['nilai'] === null ? $nilai['status'] : $nilai['keterangan'] }}">
                                        @if ($nilai['nilai'] !== null)<span class="leger-score">{{ number_format($nilai['nilai'], 2, ',', '.') }}</span>
                                        @elseif ($nilai['dikecualikan'])<span class="leger-score is-empty">TM</span>
                                        @else<span class="leger-score is-empty">-</span>@endif
                                    </td>
                                @endforeach
                                <td class="result-column"><span class="leger-score">{{ $item['jumlah_leger'] === null ? '-' : number_format($item['jumlah_leger'], 2, ',', '.') }}</span></td>
                                <td class="result-column"><span class="leger-score">{{ $item['rata_leger'] === null ? '-' : number_format($item['rata_leger'], 2, ',', '.') }}</span></td>
                                <td class="status-column"><span class="leger-status {{ $item['layak_ranking'] ? 'is-ranked' : '' }}">{{ $item['status_ranking'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="leger-footnote"><span>Ranking paralel memakai seluruh mapel tingkat {{ $tingkat }}. Nilai sama memperoleh ranking yang sama.</span><span>TM = Tidak mengikuti STS · <span data-leger-count>{{ $legerTingkat['baris']->count() }}</span> siswa ditampilkan</span></div>
        @else
            <div class="leger-empty">Belum ada siswa aktif pada tingkat ini.</div>
        @endif
    </section>
@else
    <div class="panel leger-empty">Belum ada kegiatan STS atau tingkat dalam kewenangan Anda.</div>
@endif
