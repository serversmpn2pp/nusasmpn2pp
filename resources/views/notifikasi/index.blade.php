@extends('layouts.app')

@section('title', 'Notifikasi - NUSA')

@section('content')
    <style>
        .notification-page-head {
            align-items: center;
        }

        .notification-page-summary {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--muted);
            font-size: .9rem;
        }

        .notification-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 18px;
        }

        .notification-filter-link {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 7px 12px;
            color: var(--muted);
            font-size: .86rem;
            font-weight: 800;
        }

        .notification-filter-link.active {
            border-color: rgba(21, 71, 122, .26);
            background: var(--primary-soft);
            color: var(--primary-dark);
        }

        .notification-page-list {
            display: grid;
        }

        .notification-page-item {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 14px;
            align-items: start;
            border-bottom: 1px solid var(--line);
            padding: 18px;
        }

        .notification-page-item:last-child {
            border-bottom: 0;
        }

        .notification-page-item.unread {
            background: #f8fbff;
        }

        .notification-page-mark {
            width: 11px;
            height: 11px;
            margin-top: 6px;
            border: 3px solid #d8e1eb;
            border-radius: 50%;
            background: #fff;
        }

        .notification-page-item.unread .notification-page-mark {
            border-color: var(--accent);
            background: var(--primary);
        }

        .notification-page-content {
            min-width: 0;
        }

        .notification-page-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }

        .notification-page-title {
            margin: 0;
            color: var(--text);
            font-size: 1rem;
            font-weight: 850;
            line-height: 1.35;
        }

        .notification-page-message {
            margin: 5px 0 0;
            color: var(--muted);
            overflow-wrap: anywhere;
        }

        .notification-page-time {
            color: var(--muted);
            font-size: .8rem;
            white-space: nowrap;
        }

        .notification-page-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        @media (max-width: 700px) {
            .notification-page-item {
                grid-template-columns: auto minmax(0, 1fr);
                gap: 10px;
                padding: 15px 14px;
            }

            .notification-page-actions {
                grid-column: 2;
                justify-content: flex-start;
            }
        }
    </style>

    <div class="page-header notification-page-head">
        <div>
            <p class="eyebrow">Pusat informasi</p>
            <h1 class="page-title">Notifikasi</h1>
            <p class="notification-page-summary">
                <span>{{ $jumlahBelumDibaca }} belum dibaca</span>
                <span aria-hidden="true">&bull;</span>
                <span>{{ $notifikasi->total() }} ditampilkan</span>
            </p>
        </div>

        @if ($jumlahBelumDibaca > 0)
            <form action="{{ route('notifikasi.baca-semua') }}" method="POST">
                @csrf
                @method('PATCH')
                <button type="submit" class="button button-muted">Tandai semua dibaca</button>
            </form>
        @endif
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <nav class="notification-filter" aria-label="Filter notifikasi">
        <a href="{{ route('notifikasi.index') }}" class="notification-filter-link {{ $status === 'semua' ? 'active' : '' }}">Semua</a>
        <a href="{{ route('notifikasi.index', ['status' => 'belum_dibaca']) }}" class="notification-filter-link {{ $status === 'belum_dibaca' ? 'active' : '' }}">Belum dibaca</a>
        <a href="{{ route('notifikasi.index', ['status' => 'sudah_dibaca']) }}" class="notification-filter-link {{ $status === 'sudah_dibaca' ? 'active' : '' }}">Sudah dibaca</a>
    </nav>

    <section class="panel notification-page-list" aria-label="Daftar notifikasi">
        @forelse ($notifikasi as $item)
            <article class="notification-page-item {{ $item->masihBelumDibaca() ? 'unread' : '' }}">
                <span class="notification-page-mark" aria-hidden="true"></span>

                <div class="notification-page-content">
                    <div class="notification-page-meta">
                        <span class="badge {{ $item->jenis === 'penting' ? 'badge-danger' : ($item->jenis === 'berhasil' ? 'badge-active' : 'badge-inactive') }}">
                            {{ $item->labelJenis() }}
                        </span>
                        <time class="notification-page-time" datetime="{{ $item->created_at->toIso8601String() }}">
                            {{ $item->created_at->diffForHumans() }}
                        </time>
                    </div>
                    <h2 class="notification-page-title">{{ $item->judul }}</h2>
                    <p class="notification-page-message">{{ $item->pesan }}</p>
                </div>

                <div class="notification-page-actions">
                    @if ($item->tautan)
                        <form action="{{ route('notifikasi.buka', $item) }}" method="POST">
                            @csrf
                            <button type="submit" class="button button-dark button-sm">Buka</button>
                        </form>
                    @endif

                    @if ($item->masihBelumDibaca())
                        <form action="{{ route('notifikasi.baca', $item) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="button button-muted button-sm">Tandai dibaca</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state">
                {{ $status === 'belum_dibaca' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi untuk akun ini.' }}
            </div>
        @endforelse
    </section>

    @if ($notifikasi->hasPages())
        <div style="margin-top: 18px;">
            {{ $notifikasi->links() }}
        </div>
    @endif
@endsection
