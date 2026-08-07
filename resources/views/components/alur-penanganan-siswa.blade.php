@props([
    'tahap' => 'semua',
    'judul',
    'deskripsi',
    'catatan' => null,
])

@php
    $daftarTahap = [
        'laporan' => ['Laporan dicatat', 'Pegawai mencatat kejadian siswa.'],
        'pemeriksaan' => ['BK memeriksa', 'Fakta, bukti, dan keterangan diperiksa.'],
        'keputusan' => ['Keputusan BK', 'Pembinaan, poin, atau tidak terbukti.'],
        'pengesahan' => ['Pengesahan Wakil', 'Khusus rekomendasi pelanggaran berpoin.'],
        'penanganan' => ['Penanganan', 'Pembinaan atau sanksi dijalankan.'],
        'penghargaan' => ['Penghargaan', 'Pengurangan poin menunggu persetujuan.'],
    ];
@endphp

@once
    <style>
        .student-handling-guide {
            background: #fff;
            border: 1px solid var(--line);
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            margin-bottom: 20px;
            padding: 16px 18px;
        }

        .student-handling-guide-head {
            display: flex;
            gap: 18px;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .student-handling-guide-kicker {
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
            margin: 0 0 4px;
            text-transform: uppercase;
        }

        .student-handling-guide-title {
            color: var(--primary-dark);
            font-size: 17px;
            font-weight: 800;
            margin: 0;
        }

        .student-handling-guide-description {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.55;
            margin: 5px 0 0;
            max-width: 780px;
        }

        .student-handling-guide-note {
            align-self: flex-start;
            background: #fff6cc;
            border: 1px solid #f1d66a;
            border-radius: 6px;
            color: #685300;
            flex: 0 0 auto;
            font-size: 12px;
            font-weight: 700;
            max-width: 300px;
            padding: 8px 10px;
        }

        .student-handling-flow {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }

        .student-handling-step {
            background: #f4f7fa;
            border: 1px solid #dce4eb;
            border-radius: 6px;
            min-width: 0;
            padding: 10px;
        }

        .student-handling-step.is-current,
        .student-handling-step.is-all {
            background: #edf5fc;
            border-color: #8fb8df;
            box-shadow: inset 0 3px 0 var(--accent);
        }

        .student-handling-step-number {
            align-items: center;
            background: #dce7f1;
            border-radius: 50%;
            color: var(--primary-dark);
            display: inline-flex;
            font-size: 11px;
            font-weight: 800;
            height: 22px;
            justify-content: center;
            width: 22px;
        }

        .student-handling-step.is-current .student-handling-step-number,
        .student-handling-step.is-all .student-handling-step-number {
            background: var(--primary);
            color: #fff;
        }

        .student-handling-step strong {
            color: var(--primary-dark);
            display: block;
            font-size: 13px;
            line-height: 1.35;
            margin-top: 7px;
        }

        .student-handling-step p {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.4;
            margin: 3px 0 0;
        }

        @media (max-width: 960px) {
            .student-handling-flow { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }

        @media (max-width: 640px) {
            .student-handling-guide { padding: 14px; }
            .student-handling-guide-head { display: block; }
            .student-handling-guide-note { margin-top: 10px; max-width: none; }
            .student-handling-flow { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 390px) {
            .student-handling-flow { grid-template-columns: 1fr; }
        }
    </style>
@endonce

<section class="student-handling-guide" aria-label="Penjelasan alur penanganan siswa">
    <div class="student-handling-guide-head">
        <div>
            <p class="student-handling-guide-kicker">Tentang halaman ini</p>
            <h2 class="student-handling-guide-title">{{ $judul }}</h2>
            <p class="student-handling-guide-description">{{ $deskripsi }}</p>
        </div>
        @if($catatan)
            <div class="student-handling-guide-note">{{ $catatan }}</div>
        @endif
    </div>

    <div class="student-handling-flow" aria-label="Alur penanganan siswa">
        @foreach($daftarTahap as $kode => [$nama, $keterangan])
            <div class="student-handling-step {{ $tahap === $kode ? 'is-current' : '' }} {{ $tahap === 'semua' ? 'is-all' : '' }}">
                <span class="student-handling-step-number">{{ $loop->iteration }}</span>
                <strong>{{ $nama }}</strong>
                <p>{{ $keterangan }}</p>
            </div>
        @endforeach
    </div>
</section>
