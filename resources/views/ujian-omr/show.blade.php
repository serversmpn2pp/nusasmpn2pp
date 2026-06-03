@extends('layouts.app')

@section('title', 'Detail Ujian OMR - NUSA')

@section('content')
    <style>
        .omr-version-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-top: 14px;
        }

        .omr-version-card {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 14px;
        }

        .omr-version-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .omr-version-code {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: 8px;
            background: var(--primary);
            color: #fff;
            font-size: 1.15rem;
            font-weight: 900;
        }

        .omr-print-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
    </style>

    @php
        $teks = fn (mixed $nilai) => filled($nilai) ? $nilai : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian OMR</p>
            <h1 class="page-title">Detail ujian</h1>
        </div>
        <div class="actions">
            <a href="{{ route('ujian-omr.index') }}" class="button button-muted">Kembali</a>
            @izin('omr.kelola')
                <a href="{{ route('ujian-omr.edit', $ujianOmr) }}" class="button button-dark">Edit ujian</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">
            <strong>LJK belum dapat dibuat.</strong>
            <ul style="margin: 8px 0 0 18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">LJK</div>
                <h2>{{ $ujianOmr->nama }}</h2>
                <p>{{ $ujianOmr->kode }}</p>
                <div style="margin-top: 16px;">
                    <span class="badge {{ $ujianOmr->status === 'siap' ? 'badge-active' : ($ujianOmr->status === 'nonaktif' ? 'badge-inactive' : 'badge-warning') }}">{{ $ujianOmr->labelStatus() }}</span>
                </div>
            </div>

            @izin('omr.kelola')
                @if ($ujianOmr->status !== 'nonaktif')
                    <form action="{{ route('ujian-omr.destroy', $ujianOmr) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan ujian OMR ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Ujian</h2>
                <dl class="detail-grid">
                    <div class="detail-item"><dt>Nama ujian</dt><dd>{{ $ujianOmr->nama }}</dd></div>
                    <div class="detail-item"><dt>Kode ujian</dt><dd>{{ $ujianOmr->kode }}</dd></div>
                    <div class="detail-item"><dt>Tahun pelajaran</dt><dd>{{ $ujianOmr->tahunPelajaran?->nama ?: '-' }}</dd></div>
                    <div class="detail-item"><dt>Semester</dt><dd>{{ ucfirst($ujianOmr->semester) }}</dd></div>
                    <div class="detail-item"><dt>Mata pelajaran</dt><dd>{{ $ujianOmr->mataPelajaran?->nama ?: '-' }}</dd></div>
                    <div class="detail-item"><dt>Tanggal ujian</dt><dd>{{ $ujianOmr->tanggal_ujian?->format('d-m-Y') ?: '-' }}</dd></div>
                    <div class="detail-item"><dt>Jumlah soal</dt><dd>{{ $ujianOmr->jumlah_soal }} soal</dd></div>
                    <div class="detail-item"><dt>Pilihan jawaban</dt><dd>A-D</dd></div>
                    <div class="detail-item span-2"><dt>Keterangan</dt><dd style="white-space: pre-line;">{{ $teks($ujianOmr->keterangan) }}</dd></div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Kelas Peserta dan Tujuan Nilai</h2>
                <div class="table-wrap" style="margin-top: 14px;">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>Kelas</th>
                                <th>Komponen nilai tujuan</th>
                                <th>Guru mapel</th>
                                <th>LJK</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ujianOmr->kelasUjianOmr->sortBy('kelas.nama') as $item)
                                <tr>
                                    <td><strong>{{ $item->kelas?->nama ?: '-' }}</strong></td>
                                    <td>{{ $item->komponenNilai?->nama ?: '-' }} <span class="person-meta">- {{ $item->komponenNilai?->labelJenis() ?: '-' }}</span></td>
                                    <td>{{ $item->komponenNilai?->guruMataPelajaran?->pegawai?->nama_lengkap ?: '-' }}</td>
                                    <td>
                                        <strong>{{ $item->lembarJawabUjianOmr->count() }}</strong>
                                        @if ($item->lembarJawabUjianOmr->isNotEmpty())
                                            <a href="{{ route('ujian-omr.lembar-jawab.cetak', [$ujianOmr, 'kelas_id' => $item->kelas_id]) }}" target="_blank" rel="noopener" class="button button-muted button-sm" style="margin-left: 6px;">Cetak</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Lembar Jawab Komputer</h2>
                <p class="help-text">LJK dibuat personal untuk siswa aktif di kelas peserta. Setiap lembar memperoleh token QR unik dan pembagian versi soal bergantian.</p>
                <div class="detail-grid" style="margin-top: 14px;">
                    <div class="detail-item">
                        <dt>LJK siap cetak</dt>
                        <dd>{{ $ujianOmr->lembar_jawab_ujian_omr_count }} lembar</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Format cetak</dt>
                        <dd>2 LJK A5 dalam 1 A4 landscape</dd>
                    </div>
                </div>
                @izin('omr.kelola')
                    <div class="omr-print-actions">
                        @if ($ujianOmr->status === 'siap')
                            <form action="{{ route('ujian-omr.lembar-jawab.generate', $ujianOmr) }}" method="POST">
                                @csrf
                                <button type="submit" class="button button-primary">Generate LJK</button>
                            </form>
                        @endif
                        @if ($ujianOmr->lembar_jawab_ujian_omr_count)
                            <a href="{{ route('ujian-omr.lembar-jawab.cetak', $ujianOmr) }}" target="_blank" rel="noopener" class="button button-dark">Cetak semua LJK</a>
                            <a href="{{ route('ujian-omr.scan.index', $ujianOmr) }}" class="button button-muted">Proses PDF hasil scan</a>
                        @endif
                    </div>
                @endizin
                @if ($ujianOmr->batch_scan_ujian_omr_count)
                    <p class="help-text" style="margin-top: 14px;">Riwayat pemrosesan: {{ $ujianOmr->batch_scan_ujian_omr_count }} batch PDF.</p>
                @endif
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Versi Soal dan Kunci Jawaban</h2>
                <p class="help-text">Seluruh versi aktif harus memiliki kunci lengkap sebelum ujian ditandai siap digunakan.</p>
                <div class="omr-version-grid">
                    @foreach ($ujianOmr->versiSoal as $versi)
                        @php
                            $jumlahKunci = $versi->kunciJawaban->count();
                            $lengkap = $jumlahKunci === $ujianOmr->jumlah_soal;
                        @endphp
                        <article class="omr-version-card">
                            <div class="omr-version-head">
                                <span class="omr-version-code">{{ $versi->kode }}</span>
                                <span class="badge {{ ! $versi->aktif ? 'badge-inactive' : ($lengkap ? 'badge-active' : 'badge-warning') }}">
                                    {{ ! $versi->aktif ? 'Nonaktif' : ($lengkap ? 'Lengkap' : 'Belum lengkap') }}
                                </span>
                            </div>
                            <p class="person-name" style="margin-top: 14px;">{{ $jumlahKunci }} / {{ $ujianOmr->jumlah_soal }} jawaban</p>
                            @izin('omr.kelola')
                                @if ($versi->aktif)
                                    <a href="{{ route('ujian-omr.kunci-jawaban.edit', [$ujianOmr, $versi]) }}" class="button button-primary button-full" style="margin-top: 12px;">
                                        {{ $jumlahKunci ? 'Edit kunci jawaban' : 'Isi kunci jawaban' }}
                                    </a>
                                @endif
                            @endizin
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection
