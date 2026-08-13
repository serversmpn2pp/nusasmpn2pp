@extends('layouts.app')

@section('title', 'Kenaikan Kelas - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Kenaikan kelas</h1>
        </div>

        <a href="{{ route('kelas.index') }}" class="button button-muted">Data kelas</a>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Proses belum bisa dijalankan.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('ringkasan_kenaikan'))
        @php
            $ringkasan = session('ringkasan_kenaikan');
        @endphp
        <div class="panel panel-pad" style="margin-bottom: 20px;">
            <h2 class="panel-title">Ringkasan penempatan</h2>
            <div class="stats-grid" style="margin: 16px 0 0;">
                <div class="panel stat">
                    <p class="stat-label">Diproses</p>
                    <p class="stat-value">{{ $ringkasan['diproses'] }}</p>
                </div>
                <div class="panel stat active">
                    <p class="stat-label">Ditempatkan</p>
                    <p class="stat-value">{{ $ringkasan['ditempatkan'] }}</p>
                </div>
                <div class="panel stat inactive">
                    <p class="stat-label">Dilewati</p>
                    <p class="stat-value">{{ $ringkasan['dilewati'] }}</p>
                </div>
            </div>

            @if (! empty($ringkasan['catatan']))
                <div class="alert alert-danger" style="margin: 16px 0 0;">
                    <strong>Catatan proses</strong>
                    <ul>
                        @foreach (array_slice($ringkasan['catatan'], 0, 10) as $catatan)
                            <li>{{ $catatan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    <section class="panel panel-pad" style="margin-bottom: 24px;">
        <h2 class="panel-title">Pilih Data Asal dan Tujuan</h2>
        <form action="{{ route('kenaikan-kelas.index') }}" method="GET">
            <div class="form-grid">
                <div class="field">
                    <label for="tahun_asal_id">Tahun pelajaran asal</label>
                    <select id="tahun_asal_id" name="tahun_asal_id" class="select" required>
                        <option value="">Pilih tahun asal</option>
                        @foreach ($tahunPelajaran as $item)
                            <option value="{{ $item->id }}" {{ (string) $tahunAsalId === (string) $item->id ? 'selected' : '' }}>
                                {{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="tahun_tujuan_id">Tahun pelajaran tujuan</label>
                    <select id="tahun_tujuan_id" name="tahun_tujuan_id" class="select" required>
                        <option value="">Pilih tahun tujuan</option>
                        @foreach ($tahunPelajaran as $item)
                            <option value="{{ $item->id }}" {{ (string) $tahunTujuanId === (string) $item->id ? 'selected' : '' }}>
                                {{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field span-2">
                    <label for="kelas_asal_id">Kelas asal</label>
                    <select id="kelas_asal_id" name="kelas_asal_id" class="select" required>
                        <option value="">Pilih kelas asal</option>
                        @foreach ($kelasAsalPilihan as $item)
                            <option value="{{ $item->id }}" {{ (string) $kelasAsalId === (string) $item->id ? 'selected' : '' }}>
                                {{ $item->nama }} - {{ $item->anggota_kelas_count }} siswa
                            </option>
                        @endforeach
                    </select>
                    <p class="help-text">Jika pilihan kelas kosong, pilih tahun asal lalu tekan Tampilkan siswa.</p>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="button button-primary">Tampilkan siswa</button>
            </div>
        </form>
    </section>

    @if ($tahunPelajaran->count() < 2)
        <div class="alert alert-danger">
            Kenaikan kelas membutuhkan minimal dua tahun pelajaran. Buat tahun pelajaran tujuan terlebih dahulu, lalu buat kelas-kelasnya.
        </div>
    @elseif ($tahunAsal && ! $tahunTujuan)
        <div class="alert">
            Pilih tahun pelajaran tujuan untuk mulai menempatkan siswa.
        </div>
    @elseif ($tahunTujuan && $kelasTujuan->isEmpty())
        <div class="alert alert-danger">
            Tahun tujuan {{ $tahunTujuan->nama }} belum memiliki kelas aktif. Buat kelas tujuan terlebih dahulu.
        </div>
    @elseif ($kelasAsal && $tahunTujuan)
        <form action="{{ route('kenaikan-kelas.store') }}" method="POST" class="panel">
            @csrf
            <input type="hidden" name="tahun_asal_id" value="{{ $tahunAsal->id }}">
            <input type="hidden" name="tahun_tujuan_id" value="{{ $tahunTujuan->id }}">
            <input type="hidden" name="kelas_asal_id" value="{{ $kelasAsal->id }}">

            <div class="panel-pad">
                <h2 class="panel-title">{{ $kelasAsal->nama }} ke {{ $tahunTujuan->nama }}</h2>
                <p class="help-text">NUSA memberi saran awal berdasarkan tingkat dan rombel. Bapak/Ibu tetap bisa mengubah kelas tujuan; nomor absen akan disusun otomatis berdasarkan nama A-Z.</p>
            </div>

            <div class="table-wrap">
                <table class="employee-table placement-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Siswa</th>
                            <th>Status saat ini</th>
                            <th>Kelas tujuan</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($anggotaKelas as $anggota)
                            @php
                                $tujuanSaatIni = $anggotaTujuan->get($anggota->siswa_id);
                                $kelasDipilih = old('tujuan.' . $anggota->id, $tujuanSaatIni?->kelas_id ?? $saranKelasId);
                            @endphp
                            <tr>
                                <td data-label="No.">{{ $anggota->nomor_absen ?: '-' }}</td>
                                <td data-label="Siswa">
                                    <p class="person-name">{{ $anggota->siswa?->nama_lengkap ?: '-' }}</p>
                                    <p class="person-meta">NIS: {{ $anggota->siswa?->nis ?: '-' }} - NISN: {{ $anggota->siswa?->nisn ?: '-' }}</p>
                                </td>
                                <td data-label="Status saat ini">
                                    @if ($tujuanSaatIni)
                                        Sudah di {{ $tujuanSaatIni->kelas?->nama ?: '-' }}
                                    @else
                                        Belum ditempatkan
                                    @endif
                                </td>
                                <td data-label="Kelas tujuan">
                                    <select name="tujuan[{{ $anggota->id }}]" class="select select-sm">
                                        <option value="">Belum ditempatkan</option>
                                        @foreach ($kelasTujuan as $kelas)
                                            <option value="{{ $kelas->id }}" {{ (string) $kelasDipilih === (string) $kelas->id ? 'selected' : '' }}>
                                                {{ $kelas->nama }} ({{ $kelas->anggota_kelas_count }}/{{ $kelas->kapasitas ?: '-' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td data-label="Keterangan">
                                    <input name="keterangan[{{ $anggota->id }}]" type="text" value="{{ old('keterangan.' . $anggota->id, 'Penempatan massal') }}" class="input input-sm">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state">Belum ada siswa di kelas asal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($anggotaKelas->isNotEmpty())
                <div class="form-actions panel-pad">
                    <button type="submit" class="button button-primary" onclick="return confirm('Proses penempatan siswa ke tahun tujuan?')">Proses penempatan</button>
                </div>
            @endif
        </form>
    @endif
@endsection
