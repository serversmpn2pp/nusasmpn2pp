@extends('layouts.app')

@section('title', 'Ujian Saya - NUSA')

@section('content')
    <style>
        .student-exam-page {
            display: grid;
            gap: 22px;
        }

        .student-exam-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
        }

        .student-exam-head h1 {
            margin: 3px 0 0;
        }

        .student-exam-identity {
            color: #48627c;
            font-size: 14px;
            text-align: right;
        }

        .student-exam-identity strong {
            color: #123c67;
            display: block;
            font-size: 15px;
        }

        .student-exam-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            border: 1px solid #cbd9e8;
            background: #fff;
        }

        .student-exam-summary-item {
            min-width: 0;
            padding: 18px 20px;
            border-right: 1px solid #cbd9e8;
        }

        .student-exam-summary-item:last-child {
            border-right: 0;
        }

        .student-exam-summary-item span {
            color: #61758a;
            display: block;
            font-size: 13px;
        }

        .student-exam-summary-item strong {
            color: #0b3f70;
            display: block;
            font-size: 28px;
            line-height: 1.1;
            margin-top: 5px;
        }

        .student-exam-summary-item.is-active {
            background: #eef8f2;
        }

        .student-exam-summary-item.is-upcoming {
            background: #fff8dc;
        }

        .student-exam-section {
            display: grid;
            gap: 12px;
        }

        .student-exam-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid #d4dfeb;
        }

        .student-exam-section-head h2 {
            color: #102f50;
            font-size: 18px;
            margin: 0;
        }

        .student-exam-section-head span {
            color: #61758a;
            font-size: 13px;
        }

        .student-exam-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .student-exam-card {
            background: #fff;
            border: 1px solid #cbd9e8;
            border-left: 5px solid #6c8298;
            border-radius: 7px;
            display: grid;
            gap: 15px;
            padding: 18px;
            min-width: 0;
        }

        .student-exam-card.is-active {
            border-left-color: #16815a;
        }

        .student-exam-card.is-upcoming {
            border-left-color: #e4ad00;
        }

        .student-exam-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .student-exam-card-head > div {
            min-width: 0;
        }

        .student-exam-card-head small {
            color: #61758a;
            display: block;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .student-exam-card-head h3 {
            color: #102f50;
            font-size: 17px;
            line-height: 1.35;
            margin: 0;
            overflow-wrap: anywhere;
        }

        .student-exam-status {
            border-radius: 999px;
            flex: 0 0 auto;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 9px;
            white-space: nowrap;
        }

        .student-exam-status.is-active {
            background: #dff4e8;
            color: #126644;
        }

        .student-exam-status.is-waiting {
            background: #fff3bd;
            color: #775800;
        }

        .student-exam-status.is-complete {
            background: #e8eef5;
            color: #456078;
        }

        .student-exam-status.is-danger {
            background: #fde8e8;
            color: #a42828;
        }

        .student-exam-time {
            background: #f3f7fb;
            border: 1px solid #d8e2ec;
            display: grid;
            gap: 3px;
            padding: 12px 14px;
        }

        .student-exam-time strong {
            color: #15477a;
            font-size: 15px;
        }

        .student-exam-time span {
            color: #61758a;
            font-size: 13px;
        }

        .student-exam-facts {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .student-exam-fact {
            min-width: 0;
        }

        .student-exam-fact span {
            color: #6a7c8e;
            display: block;
            font-size: 11px;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        .student-exam-fact strong {
            color: #203b56;
            display: block;
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .student-exam-access {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: end;
            gap: 10px;
            border-top: 1px solid #d8e2ec;
            padding-top: 14px;
        }

        .student-exam-token {
            display: grid;
            gap: 5px;
            min-width: 0;
        }

        .student-exam-token label {
            color: #344d66;
            font-size: 12px;
            font-weight: 700;
        }

        .student-exam-token input {
            width: 100%;
            min-height: 42px;
            border: 1px solid #b9c9d9;
            border-radius: 7px;
            background: #fff;
            color: #102f50;
            padding: 9px 11px;
            text-transform: uppercase;
        }

        .student-exam-token input:focus {
            border-color: #15477a;
            box-shadow: 0 0 0 3px rgba(21, 71, 122, .12);
            outline: none;
        }

        .student-exam-enter {
            min-height: 42px;
            white-space: nowrap;
        }

        .student-exam-access.is-resume {
            grid-template-columns: 1fr;
        }

        .student-exam-access.is-resume .student-exam-enter {
            justify-self: end;
        }

        .student-exam-empty {
            border: 1px dashed #bdccdc;
            color: #64798d;
            padding: 20px;
            text-align: center;
        }

        @media (max-width: 820px) {
            .student-exam-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .student-exam-identity {
                text-align: left;
            }

            .student-exam-list {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 560px) {
            .student-exam-summary {
                grid-template-columns: 1fr;
            }

            .student-exam-summary-item {
                border-bottom: 1px solid #cbd9e8;
                border-right: 0;
            }

            .student-exam-summary-item:last-child {
                border-bottom: 0;
            }

            .student-exam-card-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .student-exam-facts {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .student-exam-access {
                grid-template-columns: 1fr;
            }

            .student-exam-enter,
            .student-exam-access.is-resume .student-exam-enter {
                width: 100%;
                justify-self: stretch;
            }
        }
    </style>

    @php
        $bagianUjian = [
            [
                'judul' => 'Sedang Aktif',
                'keterangan' => 'Ujian yang sudah masuk waktu pelaksanaan',
                'daftar' => $ujianAktif,
                'kelas' => 'is-active',
                'kosong' => 'Tidak ada ujian aktif saat ini.',
            ],
            [
                'judul' => 'Akan Datang',
                'keterangan' => 'Ujian yang sudah dijadwalkan untuk siswa',
                'daftar' => $ujianAkanDatang,
                'kelas' => 'is-upcoming',
                'kosong' => 'Belum ada ujian yang akan datang.',
            ],
            [
                'judul' => 'Selesai',
                'keterangan' => 'Riwayat ujian siswa',
                'daftar' => $ujianSelesai,
                'kelas' => 'is-complete',
                'kosong' => 'Belum ada riwayat ujian selesai.',
            ],
        ];
    @endphp

    <div class="student-exam-page">
        <header class="student-exam-head">
            <div>
                <p class="eyebrow">Ujian & Asesmen</p>
                <h1 class="page-title">Ujian Saya</h1>
                <p class="help-text" style="margin-top: 7px;">Jadwal dan penempatan ujian yang terhubung dengan akun siswa.</p>
            </div>
            <div class="student-exam-identity">
                <strong>{{ $siswa->nama_lengkap }}</strong>
                NISN {{ $siswa->nisn ?: '-' }}
            </div>
        </header>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <section class="student-exam-summary" aria-label="Ringkasan ujian">
            <div class="student-exam-summary-item is-active">
                <span>Sedang aktif</span>
                <strong>{{ $ringkasanUjian['aktif'] }}</strong>
            </div>
            <div class="student-exam-summary-item is-upcoming">
                <span>Akan datang</span>
                <strong>{{ $ringkasanUjian['akan_datang'] }}</strong>
            </div>
            <div class="student-exam-summary-item">
                <span>Selesai</span>
                <strong>{{ $ringkasanUjian['selesai'] }}</strong>
            </div>
        </section>

        @foreach ($bagianUjian as $bagian)
            <section class="student-exam-section">
                <header class="student-exam-section-head">
                    <h2>{{ $bagian['judul'] }}</h2>
                    <span>{{ $bagian['keterangan'] }}</span>
                </header>

                @if ($bagian['daftar']->isEmpty())
                    <div class="student-exam-empty">{{ $bagian['kosong'] }}</div>
                @else
                    <div class="student-exam-list">
                        @foreach ($bagian['daftar'] as $item)
                            @php
                                $peserta = $item['peserta'];
                                $ujian = $item['ujian'];
                                $jadwal = $item['jadwal'];
                                $ruang = $peserta->ruangUjianCbt;
                                $kelas = $peserta->kelasUjianCbt?->kelas;
                                $kelasStatus = match ($item['nada_status']) {
                                    'aktif' => 'is-active',
                                    'menunggu' => 'is-waiting',
                                    'bahaya' => 'is-danger',
                                    default => 'is-complete',
                                };
                            @endphp

                            <article class="student-exam-card {{ $bagian['kelas'] }}">
                                <header class="student-exam-card-head">
                                    <div>
                                        <small>{{ $ujian?->jenisUjianCbt?->nama ?: 'Ujian CBT' }}</small>
                                        <h3>{{ $ujian?->mataPelajaran?->nama ?: $ujian?->nama ?: 'Mata pelajaran belum ditentukan' }}</h3>
                                    </div>
                                    <span class="student-exam-status {{ $kelasStatus }}">{{ $item['label_status'] }}</span>
                                </header>

                                <div class="student-exam-time">
                                    <strong>
                                        {{ $item['waktu_mulai']?->locale('id')->translatedFormat('l, d F Y') ?: 'Tanggal belum ditentukan' }}
                                    </strong>
                                    <span>
                                        @if ($item['waktu_mulai'] || $item['waktu_selesai'])
                                            {{ $item['waktu_mulai']?->format('H:i') ?: '-' }}
                                            -
                                            {{ $item['waktu_selesai']?->format('H:i') ?: '-' }} WIB
                                        @else
                                            Waktu pelaksanaan belum ditentukan
                                        @endif
                                    </span>
                                </div>

                                <div class="student-exam-facts">
                                    <div class="student-exam-fact">
                                        <span>Kelas</span>
                                        <strong>{{ $kelas?->nama ?: '-' }}</strong>
                                    </div>
                                    <div class="student-exam-fact">
                                        <span>Ruang</span>
                                        <strong>{{ $ruang?->nama ?: $ruang?->kode ?: 'Belum diatur' }}</strong>
                                    </div>
                                    <div class="student-exam-fact">
                                        <span>Nomor meja</span>
                                        <strong>{{ $peserta->nomor_meja ?: 'Belum diatur' }}</strong>
                                    </div>
                                    <div class="student-exam-fact">
                                        <span>Durasi</span>
                                        <strong>{{ $ujian?->durasi_menit ? $ujian->durasi_menit.' menit' : '-' }}</strong>
                                    </div>
                                    <div class="student-exam-fact">
                                        <span>Sesi</span>
                                        <strong>{{ $peserta->sesiUjianCbt?->nama ?: $jadwal?->label_sesi ?: 'Umum' }}</strong>
                                    </div>
                                    <div class="student-exam-fact">
                                        <span>Kegiatan</span>
                                        <strong>{{ $jadwal?->kegiatanUjianCbt?->nama ?: $ujian?->nama ?: '-' }}</strong>
                                    </div>
                                </div>

                                @if ($bagian['kelas'] === 'is-active' && in_array($peserta->status, ['aktif', 'sedang_mengerjakan'], true))
                                    @php
                                        $sedangMengerjakan = $peserta->status === 'sedang_mengerjakan';
                                        $memerlukanToken = (bool) $ujian?->jenisUjianCbt?->memerlukan_token && ! $sedangMengerjakan;
                                    @endphp
                                    <form class="student-exam-access {{ $sedangMengerjakan ? 'is-resume' : '' }}" action="{{ route('ujian-saya.masuk', $peserta) }}" method="POST">
                                        @csrf
                                        @if ($memerlukanToken)
                                            <div class="student-exam-token">
                                                <label for="token-ujian-{{ $peserta->id }}">Token dari pengawas</label>
                                                <input
                                                    id="token-ujian-{{ $peserta->id }}"
                                                    name="token"
                                                    type="text"
                                                    maxlength="20"
                                                    autocomplete="one-time-code"
                                                    placeholder="Masukkan token"
                                                    required
                                                >
                                            </div>
                                        @endif
                                        <button type="submit" class="button button-primary student-exam-enter">
                                            {{ $sedangMengerjakan ? 'Lanjutkan Ujian' : 'Masuk Ujian' }}
                                        </button>
                                    </form>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>
@endsection
