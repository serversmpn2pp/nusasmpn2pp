@extends('layouts.app')

@section('title', 'Ujian Terpusat - NUSA')

@section('content')
    <style>
        .central-filter {
            display: grid;
            grid-template-columns: minmax(240px, 1fr) minmax(180px, .45fr) auto;
            gap: 12px;
            align-items: end;
            margin-bottom: 24px;
        }

        .central-list {
            display: grid;
            gap: 14px;
        }

        .central-section {
            margin-top: 28px;
        }

        .central-section-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 12px;
        }

        .central-section-heading h2 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 1.1rem;
        }

        .central-section-heading p {
            margin: 4px 0 0;
        }

        .stats-grid .stat.warning {
            border-color: var(--accent);
            background: #fff8d6;
        }

        .central-item {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(320px, .9fr) auto;
            gap: 20px;
            align-items: center;
            padding: 18px 20px;
        }

        .central-item.is-history {
            border-left: 4px solid #94a3b8;
            background: #fbfdff;
        }

        .central-item-title {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            justify-content: space-between;
        }

        .central-item-title h3 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 1.05rem;
        }

        .central-item-status {
            display: flex;
            flex: 0 0 auto;
            align-items: flex-end;
            flex-direction: column;
            gap: 5px;
            text-align: right;
        }

        .central-item-status > span:last-child {
            color: var(--muted);
            font-size: .7rem;
            font-weight: 750;
        }

        .central-item-period {
            margin-top: 8px;
        }

        .central-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 22px;
            background: #fff;
            color: var(--muted);
        }

        .central-empty strong {
            display: block;
            color: var(--primary-dark);
        }

        .central-readiness {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .central-readiness div {
            min-width: 0;
            padding: 10px;
            border-left: 3px solid var(--line);
            background: #f8fafc;
        }

        .central-readiness div.complete {
            border-left-color: #15803d;
            background: #f0f9f3;
        }

        .central-readiness strong,
        .central-readiness span {
            display: block;
        }

        .central-readiness strong {
            color: var(--primary-dark);
            font-size: 1.1rem;
        }

        .central-readiness span {
            margin-top: 2px;
            color: var(--muted);
            font-size: .75rem;
            font-weight: 750;
        }

        @media (max-width: 980px) {
            .central-item {
                grid-template-columns: 1fr;
            }

            .central-item > .actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 680px) {
            .central-filter {
                grid-template-columns: 1fr;
            }

            .central-filter .actions .button {
                flex: 1 1 0;
            }

            .central-item {
                padding: 16px;
            }

            .central-section-heading {
                align-items: flex-start;
            }

            .central-item-title {
                align-items: stretch;
                flex-direction: column;
            }

            .central-item-status {
                align-items: flex-start;
                text-align: left;
            }

            .central-readiness {
                grid-template-columns: 1fr;
            }

            .central-item > .actions,
            .central-item > .actions .button {
                width: 100%;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian & Asesmen</p>
            <h1 class="page-title">Ujian Terpusat</h1>
            <p class="page-subtitle">Persiapan STS, SAS, SAJ, dan ujian bersama sekolah dalam satu alur panitia.</p>
        </div>

        <div class="actions">
            <a href="{{ route('pusat-cbt.index') }}" class="button button-muted">Pusat CBT</a>
            @izin('cbt.kelola')
                <a href="{{ route('ujian-terpusat.create') }}" class="button button-primary">Buat Ujian Terpusat</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="stats-grid">
        <div class="panel stat"><p class="stat-label">Total kegiatan</p><p class="stat-value">{{ $ringkasan['total'] }}</p></div>
        <div class="panel stat warning"><p class="stat-label">Persiapan</p><p class="stat-value">{{ $ringkasan['persiapan'] }}</p></div>
        <div class="panel stat active"><p class="stat-label">Aktif & akan datang</p><p class="stat-value">{{ $ringkasan['aktif'] }}</p></div>
        <div class="panel stat"><p class="stat-label">Jadwal selesai</p><p class="stat-value">{{ $ringkasan['selesai'] }}</p></div>
    </div>

    <form action="{{ route('ujian-terpusat.index') }}" method="GET" class="panel panel-pad central-filter">
        <div class="field">
            <label for="kata_kunci">Cari Ujian Terpusat</label>
            <input id="kata_kunci" name="kata_kunci" value="{{ $kataKunci }}" type="search" class="input" placeholder="Nama atau jenis ujian">
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status" class="select" onchange="this.form.requestSubmit()">
                <option value="semua" @selected($status === 'semua')>Semua status</option>
                @foreach ($daftarStatus as $kode => $label)
                    <option value="{{ $kode }}" @selected($status === $kode)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="actions">
            <button type="submit" class="button button-dark">Cari</button>
            <a href="{{ route('ujian-terpusat.index') }}" class="button button-muted">Reset</a>
        </div>
    </form>

    <section class="central-section" aria-labelledby="kegiatan-aktif-title">
        <div class="central-section-heading">
            <div>
                <h2 id="kegiatan-aktif-title">Aktif & akan datang</h2>
                <p class="help-text">Kegiatan yang masih disiapkan, sedang berlangsung, atau masih memiliki ujian susulan.</p>
            </div>
            <span class="badge badge-active">{{ $daftarAktif->total() }} kegiatan</span>
        </div>

        <div class="central-list">
        @forelse ($daftarAktif as $kegiatan)
            @include('ujian-terpusat.partials.kartu-kegiatan', [
                'kegiatan' => $kegiatan,
                'statusKegiatan' => $statusWaktu[$kegiatan->id],
                'riwayat' => false,
            ])
        @empty
            <div class="central-empty">
                <strong>Tidak ada kegiatan aktif.</strong>
                <span>Ujian yang akan datang atau masih memiliki proses susulan akan ditampilkan di sini.</span>
            </div>
        @endforelse
        </div>

        @if ($daftarAktif->hasPages())
            <div class="panel panel-pad" style="margin-top: 14px;">{{ $daftarAktif->links() }}</div>
        @endif
    </section>

    <section class="central-section" aria-labelledby="riwayat-kegiatan-title">
        <div class="central-section-heading">
            <div>
                <h2 id="riwayat-kegiatan-title">Riwayat kegiatan</h2>
                <p class="help-text">Jadwal utama dan susulan telah lewat. Data hasil, analisis, dan dokumen tetap dapat dibuka.</p>
            </div>
            <span class="badge badge-muted">{{ $daftarRiwayat->total() }} kegiatan</span>
        </div>

        <div class="central-list">
        @forelse ($daftarRiwayat as $kegiatan)
            @include('ujian-terpusat.partials.kartu-kegiatan', [
                'kegiatan' => $kegiatan,
                'statusKegiatan' => $statusWaktu[$kegiatan->id],
                'riwayat' => true,
            ])
        @empty
            <div class="central-empty">
                <strong>Belum ada riwayat kegiatan.</strong>
                <span>Kegiatan akan berpindah ke bagian ini setelah seluruh jadwal yang relevan selesai.</span>
            </div>
        @endforelse
        </div>

        @if ($daftarRiwayat->hasPages())
            <div class="panel panel-pad" style="margin-top: 14px;">{{ $daftarRiwayat->links() }}</div>
        @endif
    </section>
@endsection
