@extends('layouts.app')

@section('title', 'Notifikasi WA Absensi - NUSA')

@section('content')
    <style>
        .notification-filter-grid {
            grid-template-columns: minmax(220px, 320px) auto;
        }

        @media (max-width: 900px) {
            .notification-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @php
        $formatAngka = fn (int|float $nilai) => number_format($nilai, 0, ',', '.');
        $badgeStatus = fn (string $status) => match ($status) {
            'terkirim' => 'badge badge-active',
            'simulasi' => 'badge badge-warning',
            'gagal' => 'badge badge-danger',
            'dilewati' => 'badge badge-muted',
            default => 'badge badge-inactive',
        };
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Absensi</p>
            <h1 class="page-title">Notifikasi WA Absensi Siswa</h1>
            <p class="help-text">Default sistem saat ini adalah mode simulasi. Pesan dicatat dan belum dikirim sungguhan ke WhatsApp.</p>
        </div>

        <div class="actions">
            <a href="{{ route('scan-absensi.index') }}" target="_blank" rel="noopener" class="button button-primary">Scan absensi</a>
        </div>
    </div>

    <section class="stats-grid">
        @foreach ($daftarStatus as $kode => $label)
            <article class="panel stat {{ $kode === $status ? 'active' : '' }}">
                <p class="stat-label">{{ $label }}</p>
                <p class="stat-value">{{ $formatAngka($ringkasan[$kode] ?? 0) }}</p>
            </article>
        @endforeach
    </section>

    <section class="panel panel-pad" style="margin-bottom: 20px;">
        <form method="GET" class="filter-grid notification-filter-grid">
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="semua" @selected($status === 'semua')>Semua</option>
                    @foreach ($daftarStatus as $kode => $label)
                        <option value="{{ $kode }}" @selected($status === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-primary">Terapkan</button>
                <a href="{{ route('notifikasi-absensi-siswa.index') }}" class="button button-muted">Reset</a>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="table-wrap desktop-only">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Tanggal</th>
                        <th>Tujuan</th>
                        <th>Status</th>
                        <th>Pesan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notifikasi as $item)
                        <tr>
                            <td>
                                <div class="person">
                                    <div>
                                        <p class="person-name">{{ $item->siswa?->nama_lengkap ?? 'Siswa tidak ditemukan' }}</p>
                                        <p class="person-meta">{{ $item->siswa?->nisn ?: '-' }} - {{ $item->absensiSiswa?->kelas?->nama ?? '-' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                {{ $item->tanggal?->format('d-m-Y') ?? '-' }}<br>
                                <span class="muted">{{ $item->absensiSiswa?->jam_masuk ? substr($item->absensiSiswa->jam_masuk, 0, 5) : '-' }}</span>
                            </td>
                            <td>
                                <strong>{{ $item->nama_penerima ?: '-' }}</strong><br>
                                <span class="muted">{{ $item->nomor_tujuan ?: '-' }}</span>
                            </td>
                            <td>
                                <span class="{{ $badgeStatus($item->status) }}">{{ $item->labelStatus() }}</span>
                                <p class="help-text">{{ $item->mode_pengiriman }}</p>
                            </td>
                            <td style="min-width: 320px;">
                                <div style="white-space: pre-line;">{{ $item->pesan }}</div>
                                @if ($item->pesan_error)
                                    <p class="error-text">{{ $item->pesan_error }}</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">Belum ada notifikasi absensi siswa.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($notifikasi as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->siswa?->nama_lengkap ?? 'Siswa tidak ditemukan' }}</p>
                            <p class="person-meta">{{ $item->tanggal?->format('d-m-Y') ?? '-' }} - {{ $item->absensiSiswa?->kelas?->nama ?? '-' }}</p>
                        </div>
                        <span class="{{ $badgeStatus($item->status) }}">{{ $item->labelStatus() }}</span>
                    </div>
                    <dl class="quick-facts">
                        <div>
                            <dt>Tujuan</dt>
                            <dd>{{ $item->nama_penerima ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Nomor</dt>
                            <dd>{{ $item->nomor_tujuan ?: '-' }}</dd>
                        </div>
                    </dl>
                    <p class="help-text" style="white-space: pre-line; margin-top: 12px;">{{ $item->pesan }}</p>
                    @if ($item->pesan_error)
                        <p class="error-text">{{ $item->pesan_error }}</p>
                    @endif
                </article>
            @empty
                <div class="empty-state">Belum ada notifikasi absensi siswa.</div>
            @endforelse
        </div>

        <div class="panel-pad">
            {{ $notifikasi->links() }}
        </div>
    </section>
@endsection
