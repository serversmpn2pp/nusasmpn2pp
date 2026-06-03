@extends('layouts.app')

@section('title', 'Rekap Peminjaman Barang - NUSA')

@section('content')
    <style>
        .loan-report-filter-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 12px;
            align-items: end;
        }

        .loan-filter-search,
        .loan-filter-borrower,
        .loan-filter-item {
            grid-column: span 4;
        }

        .loan-filter-monitoring,
        .loan-filter-kind {
            grid-column: span 2;
        }

        .loan-filter-date {
            grid-column: span 3;
        }

        .loan-filter-actions {
            display: flex;
            grid-column: span 2;
            gap: 8px;
            justify-content: flex-end;
        }

        .loan-monitoring-list {
            display: grid;
            gap: 4px;
        }

        .loan-monitoring-list p {
            margin: 0;
        }

        .loan-overdue-row {
            background: #fff7f7;
        }

        .loan-overdue-row:hover {
            background: #fff1f2 !important;
        }

        .loan-report-modal {
            position: fixed;
            inset: 0;
            z-index: 80;
            display: grid;
            place-items: center;
            background: rgba(15, 23, 42, .58);
            padding: 18px;
        }

        .loan-report-modal[hidden] {
            display: none;
        }

        .loan-report-dialog {
            width: min(720px, 100%);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 22px 68px rgba(15, 23, 42, .24);
        }

        .loan-report-dialog-head,
        .loan-report-dialog-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px;
        }

        .loan-report-dialog-head {
            border-bottom: 1px solid var(--line);
        }

        .loan-report-dialog-body {
            padding: 16px;
        }

        .loan-report-dialog-foot {
            border-top: 1px solid var(--line);
        }

        .loan-report-textarea {
            min-height: 340px;
            font-family: Arial, sans-serif;
            line-height: 1.5;
        }

        .loan-copy-status {
            margin: 0;
            color: var(--primary-dark);
            font-size: .86rem;
            font-weight: 800;
        }

        @media (max-width: 1080px) {
            .loan-filter-search,
            .loan-filter-borrower,
            .loan-filter-item {
                grid-column: span 6;
            }

            .loan-filter-monitoring,
            .loan-filter-kind,
            .loan-filter-date {
                grid-column: span 3;
            }

            .loan-filter-actions {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 760px) {
            .loan-filter-search,
            .loan-filter-monitoring,
            .loan-filter-kind,
            .loan-filter-borrower,
            .loan-filter-item,
            .loan-filter-date,
            .loan-filter-actions {
                grid-column: 1 / -1;
            }

            .loan-filter-actions {
                flex-direction: column;
            }

            .loan-report-dialog-head,
            .loan-report-dialog-foot {
                align-items: stretch;
                flex-direction: column;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Rekap peminjaman barang</h1>
        </div>

        <div class="actions">
            <a href="{{ route('peminjaman-barang.index') }}" class="button button-muted">Transaksi</a>
            <button type="button" class="button button-muted" data-loan-report-open>Salin daftar terlambat</button>
            <a href="{{ route('rekap-peminjaman-barang.cetak', request()->query()) }}" class="button button-primary" target="_blank" rel="noopener">Cetak rekap</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="panel stat active">
            <p class="stat-label">Masih dipinjam</p>
            <p class="stat-value">{{ $jumlahAktif }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Terlambat</p>
            <p class="stat-value">{{ $jumlahTerlambat }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Jatuh tempo 7 hari</p>
            <p class="stat-value">{{ $jumlahJatuhTempo }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Tanpa rencana kembali</p>
            <p class="stat-value">{{ $jumlahTanpaRencana }}</p>
        </div>
    </div>

    <form action="{{ route('rekap-peminjaman-barang.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="loan-report-filter-grid">
            <div class="field loan-filter-search">
                <label for="kata_kunci">Cari rekap</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kata_kunci }}" placeholder="Nomor, nama, identitas, barang" class="input">
            </div>

            <div class="field loan-filter-monitoring">
                <label for="status_pemantauan">Pemantauan</label>
                <select id="status_pemantauan" name="status_pemantauan" class="select">
                    @foreach ($daftarStatusPemantauan as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($status_pemantauan === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field loan-filter-kind">
                <label for="jenis_peminjam">Jenis peminjam</label>
                <select id="jenis_peminjam" name="jenis_peminjam" class="select">
                    <option value="semua">Semua</option>
                    @foreach (\App\Models\PeminjamanBarang::DAFTAR_JENIS_PEMINJAM as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($jenis_peminjam === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field loan-filter-borrower">
                <label for="peminjam">Riwayat peminjam</label>
                <select id="peminjam" name="peminjam" class="select">
                    <option value="">Semua peminjam</option>
                    @if ($daftarSiswa->isNotEmpty())
                        <optgroup label="Siswa">
                            @foreach ($daftarSiswa as $item)
                                <option value="siswa:{{ $item->id }}" @selected($peminjam === 'siswa:' . $item->id)>{{ $item->nama_lengkap }} - NISN {{ $item->nisn ?: '-' }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    @if ($daftarPegawai->isNotEmpty())
                        <optgroup label="Pegawai">
                            @foreach ($daftarPegawai as $item)
                                <option value="pegawai:{{ $item->id }}" @selected($peminjam === 'pegawai:' . $item->id)>{{ $item->nama_lengkap }} - NIP {{ $item->nip ?: '-' }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
            </div>

            <div class="field loan-filter-item">
                <label for="barang_id">Barang</label>
                <select id="barang_id" name="barang_id" class="select">
                    <option value="">Semua barang</option>
                    @foreach ($daftarBarang as $item)
                        <option value="{{ $item->id }}" @selected((string) $barang_id === (string) $item->id)>{{ $item->nama }} - {{ $item->kode }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field loan-filter-date">
                <label for="tanggal_mulai">Dipinjam mulai</label>
                <input id="tanggal_mulai" name="tanggal_mulai" type="date" value="{{ $tanggal_mulai }}" class="input">
            </div>

            <div class="field loan-filter-date">
                <label for="tanggal_selesai">Dipinjam sampai</label>
                <input id="tanggal_selesai" name="tanggal_selesai" type="date" value="{{ $tanggal_selesai }}" class="input">
            </div>

            <div class="loan-filter-actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('rekap-peminjaman-barang.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="panel-pad" style="border-bottom: 1px solid var(--line);">
            <h2 class="panel-title">{{ $daftarStatusPemantauan[$status_pemantauan] }}</h2>
            <p class="help-text" style="margin-top: 6px;">Urutan rekap mengutamakan rencana kembali terdekat. Baris merah perlu segera ditindaklanjuti.</p>
        </div>

        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width: 1160px;">
                <thead>
                    <tr>
                        <th>Transaksi</th>
                        <th>Peminjam</th>
                        <th>Barang belum kembali</th>
                        <th>Rencana kembali</th>
                        <th>Pemantauan</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($peminjamanBarang as $item)
                        @php
                            $terlambat = $item->terlambat();
                            $detailBelumKembali = $item->detailPeminjamanBarang
                                ->filter(fn ($detail) => $detail->wajib_dikembalikan && $detail->jumlahBelumDikembalikan() > 0);
                        @endphp
                        <tr class="{{ $terlambat ? 'loan-overdue-row' : '' }}">
                            <td>
                                <p class="person-name">{{ $item->nomor_peminjaman }}</p>
                                <p class="person-meta">{{ $item->tanggal_peminjaman->locale('id')->translatedFormat('d M Y') }}</p>
                            </td>
                            <td>
                                <p class="person-name">{{ $item->namaPeminjam() }}</p>
                                <p class="person-meta">{{ $item->identitasPeminjam() }}</p>
                            </td>
                            <td>
                                <div class="loan-monitoring-list">
                                    @forelse ($detailBelumKembali as $detail)
                                        @php
                                            $satuan = $detail->tipe_pengelolaan === 'aset_individual' ? 'unit' : $detail->barang->satuanBarang->nama;
                                        @endphp
                                        <p><strong>{{ $detail->barang->nama }}</strong> <span class="person-meta">{{ number_format($detail->jumlahBelumDikembalikan(), 2, ',', '.') }} {{ $satuan }}</span></p>
                                    @empty
                                        <p class="person-meta">Tidak ada barang yang perlu kembali.</p>
                                    @endforelse
                                </div>
                            </td>
                            <td>{{ $item->rencana_kembali?->locale('id')->translatedFormat('d M Y') ?: '-' }}</td>
                            <td>
                                <span class="badge {{ $terlambat ? 'badge-danger' : ($item->status === 'selesai' ? 'badge-active' : 'badge-warning') }}">{{ $item->labelPemantauan() }}</span>
                                <p class="person-meta" style="margin-top: 5px;">{{ $item->labelStatus() }}</p>
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('peminjaman-barang.show', $item) }}" class="button button-muted button-sm">Lihat</a>
                                    @izin('barang.peminjaman_kelola')
                                        @if ($item->status !== 'selesai')
                                            <a href="{{ route('pengembalian-barang.create', $item) }}" class="button button-dark button-sm">Kembalikan</a>
                                        @endif
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada transaksi pada pilihan rekap ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($peminjamanBarang as $item)
                @php
                    $terlambat = $item->terlambat();
                    $detailBelumKembali = $item->detailPeminjamanBarang
                        ->filter(fn ($detail) => $detail->wajib_dikembalikan && $detail->jumlahBelumDikembalikan() > 0);
                @endphp
                <article class="mobile-card {{ $terlambat ? 'loan-overdue-row' : '' }}">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->namaPeminjam() }}</p>
                            <p class="person-meta">{{ $item->nomor_peminjaman }}</p>
                        </div>
                        <span class="badge {{ $terlambat ? 'badge-danger' : ($item->status === 'selesai' ? 'badge-active' : 'badge-warning') }}">{{ $item->labelPemantauan() }}</span>
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Identitas</dt>
                            <dd>{{ $item->identitasPeminjam() }}</dd>
                        </div>
                        <div>
                            <dt>Rencana kembali</dt>
                            <dd>{{ $item->rencana_kembali?->locale('id')->translatedFormat('d M Y') ?: '-' }}</dd>
                        </div>
                    </dl>

                    <div class="loan-monitoring-list" style="margin-top: 12px;">
                        @forelse ($detailBelumKembali as $detail)
                            @php
                                $satuan = $detail->tipe_pengelolaan === 'aset_individual' ? 'unit' : $detail->barang->satuanBarang->nama;
                            @endphp
                            <p><strong>{{ $detail->barang->nama }}</strong> <span class="person-meta">{{ number_format($detail->jumlahBelumDikembalikan(), 2, ',', '.') }} {{ $satuan }}</span></p>
                        @empty
                            <p class="person-meta">Tidak ada barang yang perlu kembali.</p>
                        @endforelse
                    </div>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('peminjaman-barang.show', $item) }}" class="button button-muted">Lihat detail</a>
                        @izin('barang.peminjaman_kelola')
                            @if ($item->status !== 'selesai')
                                <a href="{{ route('pengembalian-barang.create', $item) }}" class="button button-dark">Kembalikan</a>
                            @endif
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada transaksi pada pilihan rekap ini.</div>
            @endforelse
        </div>
    </section>

    @if ($peminjamanBarang->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $peminjamanBarang->currentPage() }} dari {{ $peminjamanBarang->lastPage() }}</div>
            <div class="actions">
                @if ($peminjamanBarang->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $peminjamanBarang->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($peminjamanBarang->hasMorePages())
                    <a href="{{ $peminjamanBarang->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif

    <div id="loan-report-modal" class="loan-report-modal" hidden>
        <div class="loan-report-dialog" role="dialog" aria-modal="true" aria-labelledby="loan-report-title">
            <div class="loan-report-dialog-head">
                <div>
                    <h2 id="loan-report-title" class="panel-title">Daftar Barang Terlambat</h2>
                    <p class="help-text" style="margin-top: 4px;">{{ $jumlahTerlambatTersaring }} transaksi sesuai filter siap disalin.</p>
                </div>
                <button type="button" class="button button-muted button-sm" data-loan-report-close>Tutup</button>
            </div>

            <div class="loan-report-dialog-body">
                <label for="loan-report-text" class="form-label">Rangkuman siap salin</label>
                <textarea id="loan-report-text" class="textarea loan-report-textarea" readonly>{{ $teksDaftarTerlambat }}</textarea>
            </div>

            <div class="loan-report-dialog-foot">
                <p id="loan-copy-status" class="loan-copy-status" aria-live="polite"></p>
                <div class="actions">
                    <button type="button" class="button button-muted" data-loan-report-close>Batal</button>
                    <button type="button" class="button button-primary" data-loan-report-copy>Salin daftar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('loan-report-modal');
            const textArea = document.getElementById('loan-report-text');
            const statusText = document.getElementById('loan-copy-status');

            const bukaModal = () => {
                modal.hidden = false;
                statusText.textContent = '';
                window.setTimeout(() => textArea.focus(), 50);
            };

            const tutupModal = () => {
                modal.hidden = true;
            };

            const salinDaftar = async () => {
                textArea.focus();
                textArea.select();

                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(textArea.value);
                    } else {
                        document.execCommand('copy');
                    }

                    statusText.textContent = 'Daftar berhasil disalin.';
                } catch (error) {
                    statusText.textContent = 'Belum bisa menyalin otomatis. Pilih teks lalu tekan Ctrl+C.';
                }
            };

            document.querySelectorAll('[data-loan-report-open]').forEach((button) => button.addEventListener('click', bukaModal));
            document.querySelectorAll('[data-loan-report-close]').forEach((button) => button.addEventListener('click', tutupModal));
            document.querySelector('[data-loan-report-copy]')?.addEventListener('click', salinDaftar);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    tutupModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && ! modal.hidden) {
                    tutupModal();
                }
            });
        })();
    </script>
@endsection
