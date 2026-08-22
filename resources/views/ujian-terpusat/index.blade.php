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

        .central-item-title {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            justify-content: space-between;
        }

        .central-item-title h2 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 1.05rem;
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
        <div class="panel stat active"><p class="stat-label">Aktif</p><p class="stat-value">{{ $ringkasan['aktif'] }}</p></div>
        <div class="panel stat"><p class="stat-label">Selesai</p><p class="stat-value">{{ $ringkasan['selesai'] }}</p></div>
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

    <section class="central-list">
        @forelse ($daftarKegiatan as $kegiatan)
            @php
                $panitiaSiap = $kegiatan->panitia_ujian_cbt_count > 0;
                $sesiSiap = $kegiatan->sesi_kegiatan_ujian_cbt_count > 0;
                $ruangSiap = $kegiatan->ruang_kegiatan_ujian_cbt_count > 0;
            @endphp
            <article class="panel central-item">
                <div>
                    <div class="central-item-title">
                        <div>
                            <p class="eyebrow">{{ $kegiatan->jenisUjianCbt?->nama ?: 'Ujian Terpusat' }}</p>
                            <h2>{{ $kegiatan->nama }}</h2>
                        </div>
                        <span class="badge {{ $kegiatan->status === 'aktif' ? 'badge-active' : ($kegiatan->status === 'draft' ? 'badge-warning' : 'badge-muted') }}">{{ $kegiatan->labelStatus() }}</span>
                    </div>
                    <p class="help-text" style="margin-top: 8px;">{{ $kegiatan->tahunPelajaran?->nama ?: '-' }} · Semester {{ ucfirst($kegiatan->semester) }} · {{ $kegiatan->labelPeriode() }}</p>
                </div>

                <div class="central-readiness" aria-label="Kesiapan {{ $kegiatan->nama }}">
                    <div class="{{ $panitiaSiap ? 'complete' : '' }}"><strong>{{ $kegiatan->panitia_ujian_cbt_count }}</strong><span>Panitia</span></div>
                    <div class="{{ $sesiSiap ? 'complete' : '' }}"><strong>{{ $kegiatan->sesi_kegiatan_ujian_cbt_count }}</strong><span>Sesi</span></div>
                    <div class="{{ $ruangSiap ? 'complete' : '' }}"><strong>{{ $kegiatan->ruang_kegiatan_ujian_cbt_count }}</strong><span>Ruang</span></div>
                </div>

                <div class="actions">
                    <a href="{{ route('ujian-terpusat.show', $kegiatan) }}" class="button button-primary">Buka persiapan</a>
                </div>
            </article>
        @empty
            <section class="panel panel-pad">
                <div class="empty-state">
                    <strong>Belum ada Ujian Terpusat.</strong>
                    <p style="margin-top: 6px;">Buat kegiatan pertama untuk mulai menentukan panitia, sesi, dan ruang.</p>
                </div>
            </section>
        @endforelse
    </section>

    @if ($daftarKegiatan->hasPages())
        <div class="panel panel-pad" style="margin-top: 18px;">{{ $daftarKegiatan->links() }}</div>
    @endif
@endsection
