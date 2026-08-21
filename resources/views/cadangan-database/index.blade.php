@extends('layouts.app')

@section('title', 'Backup & Restore Database - NUSA')

@section('content')
    @include('auth.partials.password-toggle-assets')

    <style>
        .database-backup-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(300px, .75fr);
            gap: 18px;
            margin-bottom: 20px;
        }

        .database-backup-stack {
            display: grid;
            gap: 18px;
            align-content: start;
        }

        .database-backup-list {
            min-width: 0;
            container-type: inline-size;
        }

        .database-backup-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid var(--line);
            padding: 18px;
        }

        .database-backup-head p,
        .database-backup-copy {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: .85rem;
            line-height: 1.55;
        }

        .database-backup-body {
            padding: 18px;
        }

        .database-backup-status {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .database-backup-status-item {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fafc;
            padding: 13px;
        }

        .database-backup-status-item span {
            display: block;
            margin-bottom: 4px;
            color: var(--muted);
            font-size: .75rem;
        }

        .database-backup-status-item strong {
            display: block;
            color: var(--text);
            font-size: .88rem;
            overflow-wrap: anywhere;
        }

        .database-backup-warning {
            margin-top: 14px;
            border: 1px solid #f1c40f;
            border-radius: 8px;
            background: #fff8d7;
            padding: 13px 14px;
            color: #5f4b00;
            font-size: .82rem;
            line-height: 1.55;
        }

        .database-backup-warning code {
            overflow-wrap: anywhere;
        }

        .database-backup-file {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) 90px 105px minmax(220px, auto);
            gap: 10px;
            align-items: center;
            border-top: 1px solid var(--line);
            padding: 14px 18px;
        }

        .database-backup-file > * {
            min-width: 0;
        }

        .database-backup-file .actions {
            flex-wrap: nowrap;
            gap: 6px;
        }

        .database-backup-file:first-child {
            border-top: 0;
        }

        .database-backup-file-name {
            margin: 0;
            color: var(--text);
            font-size: .88rem;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .database-backup-file-meta {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: .77rem;
        }

        .database-backup-upload {
            display: grid;
            gap: 14px;
        }

        .database-backup-danger-note {
            border-left: 4px solid #c54848;
            background: #fff3f3;
            padding: 12px 14px;
            color: #842029;
            font-size: .82rem;
            line-height: 1.55;
        }

        .database-backup-activity {
            display: grid;
        }

        .database-backup-activity-item {
            display: grid;
            grid-template-columns: 145px minmax(120px, .45fr) minmax(230px, 1.4fr) 110px;
            gap: 14px;
            align-items: start;
            border-top: 1px solid var(--line);
            padding: 14px 18px;
            font-size: .82rem;
        }

        .database-backup-activity-item:first-child {
            border-top: 0;
        }

        .database-backup-modal {
            position: fixed;
            z-index: 1200;
            inset: 0;
            display: grid;
            place-items: center;
            background: rgba(15, 35, 55, .62);
            padding: 18px;
        }

        .database-backup-modal[hidden] {
            display: none;
        }

        .database-backup-dialog {
            width: min(520px, 100%);
            max-height: calc(100vh - 36px);
            overflow: auto;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 24px 60px rgba(5, 29, 51, .24);
        }

        .database-backup-dialog-head,
        .database-backup-dialog-body,
        .database-backup-dialog-foot {
            padding: 18px;
        }

        .database-backup-dialog-head {
            border-bottom: 1px solid var(--line);
        }

        .database-backup-dialog-body {
            display: grid;
            gap: 14px;
        }

        .database-backup-dialog-foot {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            border-top: 1px solid var(--line);
        }

        @container (max-width: 720px) {
            .database-backup-file {
                grid-template-columns: minmax(180px, 1fr) 90px 105px;
            }

            .database-backup-file .actions {
                grid-column: 1 / -1;
                justify-content: flex-end !important;
                border-top: 1px dashed var(--line);
                padding-top: 10px;
            }
        }

        @media (max-width: 1020px) {
            .database-backup-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .database-backup-head {
                align-items: stretch;
                flex-direction: column;
            }

            .database-backup-head .button,
            .database-backup-head form,
            .database-backup-head form .button {
                width: 100%;
            }

            .database-backup-status {
                grid-template-columns: 1fr;
            }

            .database-backup-file {
                grid-template-columns: 1fr;
            }

            .database-backup-file .actions {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .database-backup-file .actions .button,
            .database-backup-file .actions form,
            .database-backup-file .actions form .button {
                width: 100%;
            }

            .database-backup-activity-item {
                grid-template-columns: 1fr;
                gap: 5px;
            }

            .database-backup-dialog-foot {
                display: grid;
                grid-template-columns: 1fr;
            }

            .database-backup-dialog-foot .button {
                width: 100%;
            }
        }
    </style>

    @php
        $formatUkuran = static function (int $byte): string {
            if ($byte >= 1024 * 1024 * 1024) return number_format($byte / (1024 * 1024 * 1024), 2, ',', '.').' GB';
            if ($byte >= 1024 * 1024) return number_format($byte / (1024 * 1024), 2, ',', '.').' MB';
            if ($byte >= 1024) return number_format($byte / 1024, 1, ',', '.').' KB';
            return $byte.' B';
        };
        $labelAksi = ['backup' => 'Membuat cadangan', 'restore' => 'Pemulihan', 'unggah' => 'Unggah', 'hapus' => 'Hapus'];
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Keamanan Sistem</p>
            <h1 class="page-title">Backup & Restore Database</h1>
            <p class="page-description">Lindungi data utama NUSA dengan cadangan PostgreSQL yang tersimpan privat.</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Proses belum dapat diselesaikan.</strong>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="alert" style="margin-bottom:18px;">
        <strong>Cadangan database:</strong>
        Cadangan ini mencakup seluruh data yang tersimpan di PostgreSQL, seperti siswa, pegawai, nilai, presensi, dan inventaris. Foto serta dokumen PDF tidak termasuk dan perlu dicadangkan sebagai berkas secara terpisah.
    </div>

    <div class="stats-grid">
        <article class="panel stat">
            <p class="stat-label">Jumlah cadangan</p>
            <p class="stat-value">{{ $daftarCadangan->count() }}</p>
        </article>
        <article class="panel stat active">
            <p class="stat-label">Cadangan terbaru</p>
            <p class="stat-value" style="font-size:1.08rem;line-height:1.3;">
                {{ $cadanganTerbaru ? $cadanganTerbaru['waktu']->locale('id')->translatedFormat('d M Y, H:i') : '-' }}
            </p>
        </article>
        <article class="panel stat">
            <p class="stat-label">Total penyimpanan</p>
            <p class="stat-value">{{ $formatUkuran((int) $totalUkuran) }}</p>
        </article>
        <article class="panel stat inactive">
            <p class="stat-label">Backup otomatis</p>
            <p class="stat-value" style="font-size:1.08rem;line-height:1.3;">
                {{ $statusServer['otomatis_aktif'] ? 'Setiap '.$statusServer['jadwal_otomatis'] : 'Nonaktif' }}
            </p>
        </article>
    </div>

    <div class="database-backup-grid">
        <section class="panel database-backup-list">
            <div class="database-backup-head">
                <div>
                    <h2 class="panel-title">Cadangan di server</h2>
                    <p>Cadangan terbaru ditampilkan di bagian atas.</p>
                </div>
                <form method="POST" action="{{ route('cadangan-database.store') }}" data-processing-form>
                    @csrf
                    <button type="submit" class="button button-primary" @disabled(! $statusServer['siap_backup']) data-processing-button>
                        Buat backup sekarang
                    </button>
                </form>
            </div>

            <div>
                @forelse ($daftarCadangan as $cadangan)
                    <article class="database-backup-file">
                        <div>
                            <p class="database-backup-file-name">{{ $cadangan['nama_file'] }}</p>
                            <p class="database-backup-file-meta">{{ $cadangan['waktu']->locale('id')->translatedFormat('d F Y, H:i:s') }}</p>
                        </div>
                        <div><span class="badge badge-muted">{{ $cadangan['jenis'] }}</span></div>
                        <div>
                            <strong style="font-size:.86rem;">{{ $cadangan['ukuran_label'] }}</strong><br>
                            <span class="badge {{ $cadangan['valid'] ? 'badge-active' : 'badge-danger' }}" style="margin-top:5px;">
                                {{ $cadangan['valid'] ? 'Valid' : 'Tidak valid' }}
                            </span>
                        </div>
                        <div class="actions" style="justify-content:flex-end;">
                            <a href="{{ route('cadangan-database.download', $cadangan['nama_file']) }}" class="button button-muted button-sm">Unduh</a>
                            <button
                                type="button"
                                class="button button-primary button-sm"
                                data-restore-open
                                data-restore-file="{{ $cadangan['nama_file'] }}"
                                data-restore-url="{{ route('cadangan-database.restore', $cadangan['nama_file']) }}"
                                @disabled(! $cadangan['valid'] || ! $statusServer['siap_restore'])
                            >Pulihkan</button>
                            <form method="POST" action="{{ route('cadangan-database.destroy', $cadangan['nama_file']) }}" onsubmit="return confirm('Hapus cadangan {{ $cadangan['nama_file'] }} dari server?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button button-danger button-sm">Hapus</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="empty-state">Belum ada cadangan database di server.</div>
                @endforelse
            </div>
        </section>

        <div class="database-backup-stack">
            <section class="panel">
                <div class="database-backup-head">
                    <div>
                        <h2 class="panel-title">Kesiapan server</h2>
                        <p>Peralatan PostgreSQL yang dipakai NUSA.</p>
                    </div>
                    <span class="badge {{ $statusServer['siap_restore'] ? 'badge-active' : 'badge-danger' }}">
                        {{ $statusServer['siap_restore'] ? 'Siap' : 'Perlu diatur' }}
                    </span>
                </div>
                <div class="database-backup-body">
                    <div class="database-backup-status">
                        <div class="database-backup-status-item"><span>Database</span><strong>{{ $statusServer['database'] ?: '-' }}</strong></div>
                        <div class="database-backup-status-item"><span>Driver</span><strong>{{ strtoupper($statusServer['driver'] ?: '-') }}</strong></div>
                        <div class="database-backup-status-item"><span>pg_dump</span><strong>{{ $statusServer['pg_dump'] ?: 'Belum ditemukan' }}</strong></div>
                        <div class="database-backup-status-item"><span>pg_restore</span><strong>{{ $statusServer['pg_restore'] ?: 'Belum ditemukan' }}</strong></div>
                    </div>

                    @if (! $statusServer['siap_restore'])
                        <div class="database-backup-warning">
                            Isi <code>NUSA_PG_BIN_PATH</code> di <code>.env</code> dengan folder <code>bin</code> PostgreSQL. Setelah itu jalankan <code>php artisan optimize:clear</code>.
                        </div>
                    @endif

                    <p class="database-backup-copy">
                        Backup otomatis menyimpan cadangan selama {{ $statusServer['retensi_hari'] }} hari dan berjalan bila Laravel Scheduler server aktif.
                    </p>
                </div>
            </section>

            <section class="panel">
                <div class="database-backup-head">
                    <div>
                        <h2 class="panel-title">Pulihkan dari perangkat</h2>
                        <p>Gunakan berkas <code>.dump</code> yang dibuat NUSA.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('cadangan-database.restore-upload') }}" enctype="multipart/form-data" class="database-backup-body database-backup-upload" data-upload-restore data-processing-form>
                    @csrf
                    <div class="field">
                        <label for="berkas_cadangan">Berkas cadangan</label>
                        <input id="berkas_cadangan" name="berkas_cadangan" type="file" class="input" accept=".dump,application/octet-stream" required data-backup-file>
                        <small>Maksimal {{ $batasUnggah['label'] }}.</small>
                        <p class="error-text" hidden data-backup-file-error></p>
                    </div>
                    <x-password-field
                        id="kata_sandi_upload"
                        name="kata_sandi"
                        label="Kata sandi akun Anda"
                        autocomplete="current-password"
                        required
                    />
                    <div class="field">
                        <label for="konfirmasi_upload">Ketik PULIHKAN</label>
                        <input id="konfirmasi_upload" name="konfirmasi" type="text" class="input" autocomplete="off" placeholder="PULIHKAN" required>
                    </div>
                    <div class="database-backup-danger-note">
                        Data database saat ini akan diganti dengan isi cadangan. NUSA otomatis membuat backup pengaman sebelum proses dimulai.
                    </div>
                    <button type="submit" class="button button-danger button-full" @disabled(! $statusServer['siap_restore']) data-processing-button>Unggah dan pulihkan</button>
                </form>
            </section>
        </div>
    </div>

    <section class="panel">
        <div class="database-backup-head">
            <div>
                <h2 class="panel-title">Riwayat tindakan</h2>
                <p>Catatan ini disimpan terpisah dari database sehingga tetap tersedia setelah pemulihan.</p>
            </div>
        </div>
        <div class="database-backup-activity">
            @forelse ($daftarAktivitas as $aktivitas)
                <article class="database-backup-activity-item">
                    <div>
                        <strong>{{ \Carbon\Carbon::parse($aktivitas['waktu'])->locale('id')->translatedFormat('d M Y, H:i') }}</strong>
                        <div class="person-meta">{{ $aktivitas['pengguna'] ?? 'Sistem otomatis' }}</div>
                    </div>
                    <div><strong>{{ $labelAksi[$aktivitas['aksi'] ?? ''] ?? str($aktivitas['aksi'] ?? '-')->headline() }}</strong></div>
                    <div>
                        <strong style="overflow-wrap:anywhere;">{{ $aktivitas['nama_file'] ?? '-' }}</strong>
                        <div class="person-meta">{{ $aktivitas['pesan'] ?? '-' }}</div>
                    </div>
                    <div><span class="badge {{ ($aktivitas['status'] ?? '') === 'berhasil' ? 'badge-active' : 'badge-danger' }}">{{ ucfirst($aktivitas['status'] ?? '-') }}</span></div>
                </article>
            @empty
                <div class="empty-state">Belum ada tindakan backup atau restore.</div>
            @endforelse
        </div>
    </section>

    <div class="database-backup-modal" hidden data-restore-modal>
        <form method="POST" class="database-backup-dialog" role="dialog" aria-modal="true" aria-labelledby="restore-title" data-restore-form data-processing-form>
            @csrf
            <div class="database-backup-dialog-head">
                <h2 id="restore-title" class="panel-title">Konfirmasi pemulihan</h2>
                <p class="database-backup-copy">Cadangan: <strong data-restore-name></strong></p>
            </div>
            <div class="database-backup-dialog-body">
                <div class="database-backup-danger-note">
                    Database aktif akan diganti. Pengguna lain tidak dapat memakai NUSA selama proses berlangsung.
                </div>
                <x-password-field
                    id="kata_sandi_restore"
                    name="kata_sandi"
                    label="Kata sandi akun Anda"
                    autocomplete="current-password"
                    required
                    data-restore-password
                />
                <div class="field">
                    <label for="konfirmasi_restore">Ketik PULIHKAN</label>
                    <input id="konfirmasi_restore" name="konfirmasi" type="text" class="input" autocomplete="off" placeholder="PULIHKAN" required>
                </div>
            </div>
            <div class="database-backup-dialog-foot">
                <button type="button" class="button button-muted" data-restore-close>Batal</button>
                <button type="submit" class="button button-danger" data-processing-button>Ya, pulihkan database</button>
            </div>
        </form>
    </div>

    <script>
        (() => {
            const modal = document.querySelector('[data-restore-modal]');
            const form = document.querySelector('[data-restore-form]');
            const name = document.querySelector('[data-restore-name]');
            const password = document.querySelector('[data-restore-password]');

            document.querySelectorAll('[data-restore-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    form.action = button.dataset.restoreUrl;
                    name.textContent = button.dataset.restoreFile;
                    form.reset();
                    modal.hidden = false;
                    document.body.style.overflow = 'hidden';
                    window.setTimeout(() => password.focus(), 50);
                });
            });

            const closeModal = () => {
                modal.hidden = true;
                document.body.style.overflow = '';
                form.reset();
            };

            document.querySelector('[data-restore-close]')?.addEventListener('click', closeModal);
            modal?.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal && !modal.hidden) closeModal();
            });

            const backupFile = document.querySelector('[data-backup-file]');
            const backupFileError = document.querySelector('[data-backup-file-error]');
            const uploadForm = document.querySelector('[data-upload-restore]');
            const maxBytes = {{ (int) $batasUnggah['byte'] }};

            backupFile?.addEventListener('change', () => {
                backupFileError.hidden = true;
                backupFileError.textContent = '';

                if (backupFile.files[0] && backupFile.files[0].size > maxBytes) {
                    backupFileError.textContent = `Ukuran berkas melebihi batas {{ $batasUnggah['label'] }}.`;
                    backupFileError.hidden = false;
                    backupFile.value = '';
                }
            });

            document.querySelectorAll('[data-processing-form]').forEach((item) => {
                item.addEventListener('submit', (event) => {
                    if (item === uploadForm && !backupFile.files.length) return;

                    const button = item.querySelector('[data-processing-button]');
                    if (!button) return;

                    button.disabled = true;
                    button.dataset.originalText = button.textContent;
                    button.textContent = item === uploadForm || item === form ? 'Memproses...' : 'Membuat backup...';
                });
            });
        })();
    </script>
@endsection
