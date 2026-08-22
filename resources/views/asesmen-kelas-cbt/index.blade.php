@extends('layouts.app')

@section('title', 'Asesmen Kelas - NUSA')

@section('content')
    <style>
        .assessment-list {
            display: grid;
            gap: 12px;
        }

        .assessment-item {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(160px, .55fr) minmax(180px, .65fr) auto;
            gap: 16px;
            align-items: center;
        }

        .assessment-item-classes {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 7px;
        }

        @media (max-width: 860px) {
            .assessment-item {
                grid-template-columns: 1fr 1fr;
            }

            .assessment-item .actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 560px) {
            .assessment-item {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian & Asesmen</p>
            <h1 class="page-title">Asesmen Kelas</h1>
            <p class="help-text" style="margin-top: 8px; max-width: 720px;">Ulangan CBT yang dilaksanakan guru pada jam mengajarnya sendiri, tanpa pengaturan ruang dan panitia.</p>
        </div>
        <div class="actions">
            <a href="{{ route('pusat-cbt.index') }}" class="button button-muted">Pusat CBT</a>
            <a href="{{ route('asesmen-kelas-cbt.create') }}" class="button button-primary">Buat asesmen</a>
        </div>
    </div>

    @if (session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif

    <form method="GET" class="panel panel-pad filter-form" style="margin-bottom: 18px;">
        <div class="filter-grid" style="grid-template-columns: minmax(240px, 1fr) minmax(180px, .45fr) auto;">
            <div class="field">
                <label for="kata_kunci">Cari asesmen</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" class="input" placeholder="Nama, mapel, atau kelas">
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select" data-auto-submit>
                    <option value="semua">Semua status</option>
                    @foreach ($daftarStatus as $kode => $label)<option value="{{ $kode }}" @selected($status === $kode)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="actions" style="align-self: end;">
                <button type="submit" class="button button-primary">Cari</button>
                @if ($kataKunci !== '' || $status !== 'semua')<a href="{{ route('asesmen-kelas-cbt.index') }}" class="button button-muted">Reset</a>@endif
            </div>
        </div>
    </form>

    <div class="assessment-list">
        @forelse ($asesmen as $item)
            @php
                $badge = in_array($item->status, ['terjadwal', 'berlangsung'], true) ? 'badge-active' : ($item->status === 'nonaktif' ? 'badge-inactive' : 'badge-warning');
            @endphp
            <article class="panel panel-pad assessment-item">
                <div>
                    <p class="person-name">{{ $item->nama }}</p>
                    <p class="person-meta">{{ $item->mataPelajaran?->nama ?: '-' }} · Kelas {{ $item->tingkat }} · {{ ucfirst($item->semester) }}</p>
                    <div class="assessment-item-classes">
                        @foreach ($item->kelasUjianCbt->sortBy(fn ($kelas) => $kelas->kelas?->nama) as $kelas)
                            <span class="badge badge-muted">{{ $kelas->kelas?->nama ?: '-' }}</span>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="stat-label">Jadwal</p>
                    <p class="person-name" style="font-size: .9rem;">{{ $item->tanggal_mulai?->format('d-m-Y H:i') ?: '-' }}</p>
                    <p class="person-meta">sampai {{ $item->tanggal_selesai?->format('d-m-Y H:i') ?: '-' }}</p>
                </div>
                <div>
                    <span class="badge {{ $badge }}">{{ $item->labelStatus() }}</span>
                    <p class="person-meta" style="margin-top: 8px;">{{ $item->soal_ujian_cbt_count }} soal · {{ $item->peserta_ujian_cbt_count }} peserta</p>
                </div>
                <div class="actions"><a href="{{ route('asesmen-kelas-cbt.show', $item) }}" class="button button-primary">Buka</a></div>
            </article>
        @empty
            <div class="panel empty-state">
                <strong>Belum ada asesmen kelas.</strong>
                <p class="help-text" style="margin-top: 6px;">Buat asesmen pertama, lalu pilih soal dari Bank Soal.</p>
            </div>
        @endforelse
    </div>

    @if ($asesmen->hasPages())<div class="pagination-wrap">{{ $asesmen->links() }}</div>@endif

    <script>
        document.querySelectorAll('[data-auto-submit]').forEach((input) => input.addEventListener('change', () => input.form.submit()));
    </script>
@endsection
