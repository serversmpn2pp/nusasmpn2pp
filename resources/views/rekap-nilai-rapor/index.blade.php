@extends('layouts.app')

@section('title', 'Rekap Nilai Rapor - NUSA')

@section('content')
    @php
        $labelGuruMapel = function ($item) {
            return collect([
                $item->tahunPelajaran?->nama,
                $item->kelas?->nama,
                $item->mataPelajaran?->nama,
                $item->pegawai?->nama_lengkap,
            ])->filter()->join(' - ');
        };

        $formatNilai = fn (mixed $nilai) => $nilai === null ? '-' : rtrim(rtrim(number_format((float) $nilai, 2, ',', '.'), '0'), ',');
        $labelStatus = fn (string $status) => $status === 'Lengkap' ? 'badge badge-active' : 'badge badge-inactive';
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian</p>
            <h1 class="page-title">Rekap nilai rapor</h1>
        </div>

        <div class="actions">
            @izin('nilai.input')
                <a href="{{ route('input-nilai.index') }}" class="button button-muted">Input nilai</a>
            @endizin
            @izin('nilai.skema_kelola')
                <a href="{{ route('skema-bobot-nilai.index') }}" class="button button-muted">Bobot nilai</a>
            @endizin
        </div>
    </div>

    <form action="{{ route('rekap-nilai-rapor.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid">
            <div class="field">
                <label for="guru_mata_pelajaran_id">Guru mata pelajaran</label>
                <select id="guru_mata_pelajaran_id" name="guru_mata_pelajaran_id" class="select" required>
                    <option value="">Pilih guru mata pelajaran</option>
                    @foreach ($daftarGuruMataPelajaran as $item)
                        <option value="{{ $item->id }}" @selected((string) $guruMataPelajaranId === (string) $item->id)>
                            {{ $labelGuruMapel($item) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="semester">Semester</label>
                <select id="semester" name="semester" class="select" required>
                    <option value="ganjil" @selected($semester === 'ganjil')>Ganjil</option>
                    <option value="genap" @selected($semester === 'genap')>Genap</option>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('rekap-nilai-rapor.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    @if ($daftarGuruMataPelajaran->isEmpty())
        <section class="panel panel-pad">
            <h2 class="panel-title">Belum ada guru mata pelajaran</h2>
            <p class="help-text" style="margin-top: 8px;">Buat penugasan guru mata pelajaran terlebih dahulu agar rekap nilai rapor dapat dihitung.</p>
        </section>
    @elseif (! $guruMataPelajaranDipilih)
        <section class="panel panel-pad">
            <h2 class="panel-title">Pilih data rekap</h2>
            <p class="help-text" style="margin-top: 8px;">Rekap dihitung per guru mata pelajaran dan semester berdasarkan nilai yang sudah diinput pada setiap komponen.</p>
        </section>
    @else
        <div class="stats-grid">
            <div class="panel stat">
                <p class="stat-label">Siswa</p>
                <p class="stat-value">{{ $jumlahSiswa }}</p>
            </div>
            <div class="panel stat active">
                <p class="stat-label">Lengkap</p>
                <p class="stat-value">{{ $jumlahLengkap }}</p>
            </div>
            <div class="panel stat inactive">
                <p class="stat-label">Belum lengkap</p>
                <p class="stat-value">{{ $jumlahBelumLengkap }}</p>
            </div>
            <div class="panel stat">
                <p class="stat-label">Rata-rata akhir</p>
                <p class="stat-value">{{ $rataRataAkhir === null ? '-' : $formatNilai($rataRataAkhir) }}</p>
            </div>
        </div>

        @if (! $skemaBobotNilai)
            <div class="alert alert-danger">
                Skema bobot nilai aktif untuk tahun pelajaran, semester, dan tingkat ini belum ada. Buat skema bobot dulu agar nilai akhir bisa dihitung.
            </div>
        @elseif ($komponenNilai->isEmpty())
            <div class="alert alert-danger">
                Komponen nilai aktif untuk pilihan ini belum ada. Buat komponen nilai terlebih dahulu sebelum melakukan rekap.
            </div>
        @endif

        <div class="detail-shell">
            <aside class="panel panel-pad">
                <div class="detail-profile">
                    <div class="avatar avatar-lg">RP</div>
                    <h2>{{ $guruMataPelajaranDipilih->mataPelajaran?->nama ?: '-' }}</h2>
                    <p>{{ $guruMataPelajaranDipilih->kelas?->nama ?: '-' }} - {{ ucfirst($semester) }}</p>

                    <div style="margin-top: 16px;">
                        <span class="badge badge-active">{{ $guruMataPelajaranDipilih->tahunPelajaran?->nama ?: '-' }}</span>
                    </div>
                </div>

                <dl class="quick-facts" style="margin-top: 20px;">
                    <div>
                        <dt>Guru</dt>
                        <dd>{{ $guruMataPelajaranDipilih->pegawai?->nama_lengkap ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt>Tingkat</dt>
                        <dd>{{ $teks($guruMataPelajaranDipilih->kelas?->tingkat) }}</dd>
                    </div>
                    <div>
                        <dt>Formatif</dt>
                        <dd>{{ $jumlahKomponen['formatif'] }} komponen{{ $skemaBobotNilai ? ' - ' . $skemaBobotNilai->bobot_formatif . '%' : '' }}</dd>
                    </div>
                    <div>
                        <dt>Sumatif</dt>
                        <dd>{{ $jumlahKomponen['sumatif'] }} komponen{{ $skemaBobotNilai ? ' - ' . $skemaBobotNilai->bobot_sumatif . '%' : '' }}</dd>
                    </div>
                    <div>
                        <dt>STS</dt>
                        <dd>{{ $jumlahKomponen['sts'] }} komponen{{ $skemaBobotNilai ? ' - ' . $skemaBobotNilai->bobot_sts . '%' : '' }}</dd>
                    </div>
                    <div>
                        <dt>{{ $labelNilaiAkhir }}</dt>
                        <dd>{{ $jumlahKomponen['sas_saj'] }} komponen{{ $skemaBobotNilai ? ' - ' . $skemaBobotNilai->bobot_sas_saj . '%' : '' }}</dd>
                    </div>
                </dl>
            </aside>

            <section class="panel">
                @if ($rekapNilai->isEmpty())
                    <div class="empty-state">Belum ada siswa aktif di kelas ini.</div>
                @else
                    <div class="table-wrap">
                        <table class="employee-table placement-table" style="min-width: 1180px;">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Siswa</th>
                                    <th>Formatif</th>
                                    <th>Sumatif</th>
                                    <th>STS</th>
                                    <th>{{ $labelNilaiAkhir }}</th>
                                    <th>Nilai akhir</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rekapNilai as $item)
                                    @php
                                        $anggota = $item['anggota_kelas'];
                                        $kategori = $item['kategori'];
                                    @endphp
                                    <tr>
                                        <td data-label="No.">{{ $anggota->nomor_absen ?: '-' }}</td>
                                        <td data-label="Siswa">
                                            <p class="person-name">{{ $anggota->siswa?->nama_lengkap ?: '-' }}</p>
                                            <p class="person-meta">NIS: {{ $anggota->siswa?->nis ?: '-' }} - NISN: {{ $anggota->siswa?->nisn ?: '-' }}</p>
                                        </td>
                                        <td data-label="Formatif">
                                            <p class="person-name">{{ $formatNilai($kategori['formatif']['rata']) }}</p>
                                            <p class="person-meta">{{ $kategori['formatif']['terisi'] }}/{{ $kategori['formatif']['target'] }} nilai</p>
                                        </td>
                                        <td data-label="Sumatif">
                                            <p class="person-name">{{ $formatNilai($kategori['sumatif']['rata']) }}</p>
                                            <p class="person-meta">{{ $kategori['sumatif']['terisi'] }}/{{ $kategori['sumatif']['target'] }} nilai</p>
                                        </td>
                                        <td data-label="STS">
                                            <p class="person-name">{{ $formatNilai($kategori['sts']['rata']) }}</p>
                                            <p class="person-meta">{{ $kategori['sts']['terisi'] }}/{{ $kategori['sts']['target'] }} nilai</p>
                                        </td>
                                        <td data-label="{{ $labelNilaiAkhir }}">
                                            <p class="person-name">{{ $formatNilai($kategori['sas_saj']['rata']) }}</p>
                                            <p class="person-meta">{{ $kategori['sas_saj']['terisi'] }}/{{ $kategori['sas_saj']['target'] }} nilai</p>
                                        </td>
                                        <td data-label="Nilai akhir">
                                            <p class="stat-value" style="font-size: 1.2rem;">{{ $formatNilai($item['nilai_akhir']) }}</p>
                                        </td>
                                        <td data-label="Status">
                                            <span class="{{ $labelStatus($item['status']) }}">{{ $item['status'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    @endif
@endsection
