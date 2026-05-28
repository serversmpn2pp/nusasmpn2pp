@csrf

<style>
    .role-form-shell {
        display: grid;
        gap: 24px;
    }

    .permission-groups {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-top: 16px;
    }

    .permission-group {
        display: grid;
        gap: 12px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: #fff;
        padding: 14px;
    }

    .permission-group.is-complete {
        border-color: rgba(21, 71, 122, .28);
        background: rgba(21, 71, 122, .035);
    }

    .permission-group-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .permission-group-title {
        margin: 0;
        color: var(--primary);
        font-size: .98rem;
        font-weight: 900;
    }

    .permission-group-count {
        margin-top: 4px;
        color: var(--muted);
        font-size: .8rem;
        font-weight: 750;
    }

    .permission-group-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
    }

    .permission-mini-button {
        border: 1px solid rgba(21, 71, 122, .18);
        border-radius: 999px;
        background: #fff;
        color: var(--primary);
        cursor: pointer;
        font-size: .74rem;
        font-weight: 850;
        padding: 7px 10px;
    }

    .permission-mini-button:hover {
        background: rgba(21, 71, 122, .08);
    }

    .permission-mini-button:disabled {
        cursor: not-allowed;
        opacity: .55;
    }

    .permission-intro {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
        border-top: 1px solid var(--line);
        padding-top: 24px;
    }

    .permission-summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(110px, 1fr));
        gap: 10px;
    }

    .permission-summary-item {
        border: 1px solid var(--line);
        border-radius: 8px;
        background: #fff;
        padding: 12px;
        text-align: right;
    }

    .permission-summary-item strong {
        display: block;
        color: var(--primary);
        font-size: 1.45rem;
        line-height: 1;
    }

    .permission-summary-item span {
        display: block;
        margin-top: 5px;
        color: var(--muted);
        font-size: .78rem;
        font-weight: 800;
    }

    .permission-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px;
        align-items: end;
        margin-top: 16px;
    }

    .permission-toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
    }

    .permission-checks {
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .permission-check {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 10px;
        align-items: start;
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 11px;
        background: #fafafa;
        transition: border-color .16s ease, background-color .16s ease, box-shadow .16s ease;
    }

    .permission-check.is-selected {
        border-color: rgba(21, 71, 122, .28);
        background: #fff;
        box-shadow: inset 3px 0 0 var(--accent);
    }

    .permission-check input {
        width: 18px;
        height: 18px;
        margin-top: 1px;
        flex: 0 0 auto;
        accent-color: var(--primary);
    }

    .permission-name {
        margin: 0;
        color: #27272a;
        font-size: .84rem;
        font-weight: 800;
    }

    .permission-code {
        display: inline-flex;
        width: fit-content;
        margin: 5px 0 0;
        border-radius: 999px;
        background: rgba(241, 196, 15, .2);
        padding: 3px 8px;
        color: var(--muted);
        font-size: .72rem;
        font-weight: 850;
        overflow-wrap: anywhere;
    }

    .permission-desc {
        display: block;
        margin-top: 6px;
        color: var(--muted);
        font-size: .78rem;
        line-height: 1.35;
    }

    .permission-empty-filter {
        display: none;
        margin: 16px 0 0;
        border: 1px dashed rgba(21, 71, 122, .25);
        border-radius: 8px;
        padding: 14px;
        color: var(--muted);
        font-weight: 800;
        text-align: center;
    }

    .permission-empty-filter.is-visible {
        display: block;
    }

    .form-actions.role-actions {
        position: sticky;
        bottom: 0;
        z-index: 2;
        margin: 22px -24px -24px;
        border-top: 1px solid var(--line);
        background: rgba(255, 255, 255, .96);
        padding: 14px 24px;
        backdrop-filter: blur(10px);
    }

    @media (max-width: 1120px) {
        .permission-groups {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .permission-intro,
        .permission-toolbar {
            grid-template-columns: 1fr;
        }

        .permission-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .permission-summary-item {
            text-align: left;
        }

        .permission-toolbar-actions,
        .permission-group-actions {
            justify-content: flex-start;
        }

        .form-actions.role-actions {
            margin-right: -16px;
            margin-left: -16px;
            padding-right: 16px;
            padding-left: 16px;
        }
    }
</style>

@php
    $izinDipilih = collect($izinDipilih ?? [])->map(fn ($id) => (int) $id)->all();
    $peranAdministrator = $peran->kode === 'administrator';
    $jumlahIzin = $daftarIzin->flatten(1)->count();
    $jumlahDipilih = $peranAdministrator ? $jumlahIzin : count(array_intersect($izinDipilih, $daftarIzin->flatten(1)->pluck('id')->all()));
@endphp

<div class="role-form-shell">
    <div>
        <div class="form-grid">
            <div class="field">
                <label for="nama">Nama peran</label>
                <input id="nama" name="nama" type="text" value="{{ old('nama', $peran->nama) }}" class="input @error('nama') is-invalid @enderror" required>
                @error('nama')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="kode">Kode</label>
                <input id="kode" name="kode" type="text" value="{{ old('kode', $peran->kode) }}" class="input @error('kode') is-invalid @enderror" placeholder="otomatis dari nama" @readonly($peran->sistem)>
                <p class="help-text">Gunakan huruf kecil, angka, dan garis bawah. Contoh: guru_mapel.</p>
                @error('kode')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div class="field span-2">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" class="textarea @error('deskripsi') is-invalid @enderror">{{ old('deskripsi', $peran->deskripsi) }}</textarea>
                @error('deskripsi')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="status-toggle">
            <div>
                <p class="person-name">Aktif</p>
                <p class="help-text">Peran aktif dapat diberikan ke akun pegawai.</p>
            </div>
            <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $peran->aktif)) @disabled($peran->sistem)>
        </div>

        @if ($peran->sistem)
            <p class="help-text">Peran sistem selalu aktif dan kodenya tidak dapat diubah.</p>
        @endif
    </div>

    <section data-permission-form>
        <div class="permission-intro">
            <div>
                <h2 class="panel-title">Izin akses</h2>
                <p class="help-text">Centang izin sesuai kebutuhan role. Setiap kelompok mewakili modul utama NUSA, jadi pengaturan lebih mudah dicek sebelum disimpan.</p>
            </div>

            <div class="permission-summary">
                <div class="permission-summary-item">
                    <strong data-total-selected>{{ $jumlahDipilih }}</strong>
                    <span>Izin dipilih</span>
                </div>
                <div class="permission-summary-item">
                    <strong>{{ $jumlahIzin }}</strong>
                    <span>Izin tersedia</span>
                </div>
            </div>
        </div>

        <div class="permission-toolbar">
            <div class="field" style="margin: 0;">
                <label for="filter_izin">Filter izin</label>
                <input id="filter_izin" type="search" class="input" placeholder="Cari modul, nama izin, atau kode" data-permission-search-input>
            </div>
            <div class="permission-toolbar-actions">
                <button type="button" class="permission-mini-button" data-permission-action="select-all" @disabled($peranAdministrator)>Centang semua</button>
                <button type="button" class="permission-mini-button" data-permission-action="clear-all" @disabled($peranAdministrator)>Kosongkan semua</button>
            </div>
        </div>

        <div class="permission-groups">
            @foreach ($daftarIzin as $kelompok => $izinKelompok)
                @php
                    $izinKelompokIds = $izinKelompok->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $jumlahKelompok = count($izinKelompokIds);
                    $jumlahDipilihKelompok = $peranAdministrator ? $jumlahKelompok : count(array_intersect($izinDipilih, $izinKelompokIds));
                    $groupId = 'izin-kelompok-' . str($kelompok)->slug();
                @endphp

                <article class="permission-group" data-permission-group>
                    <div class="permission-group-head">
                        <div>
                            <h3 class="permission-group-title">{{ $kelompok }}</h3>
                            <p class="permission-group-count"><span data-group-selected>{{ $jumlahDipilihKelompok }}</span>/{{ $jumlahKelompok }} izin dipilih</p>
                        </div>
                        <div class="permission-group-actions">
                            <button type="button" class="permission-mini-button" data-permission-action="select-group" @disabled($peranAdministrator)>Pilih</button>
                            <button type="button" class="permission-mini-button" data-permission-action="clear-group" @disabled($peranAdministrator)>Kosongkan</button>
                        </div>
                    </div>

                    <div class="permission-checks">
                        @foreach ($izinKelompok as $izin)
                            @php
                                $dipilih = $peranAdministrator || in_array($izin->id, $izinDipilih, true);
                                $teksCari = str($kelompok . ' ' . $izin->nama . ' ' . $izin->kode . ' ' . $izin->deskripsi)->lower();
                            @endphp

                            <label
                                @class(['permission-check', 'is-selected' => $dipilih])
                                for="izin-{{ $izin->id }}"
                                data-permission-item
                                data-permission-search="{{ $teksCari }}"
                            >
                                <input
                                    id="izin-{{ $izin->id }}"
                                    type="checkbox"
                                    name="izin_ids[]"
                                    value="{{ $izin->id }}"
                                    data-permission-checkbox
                                    data-permission-group-id="{{ $groupId }}"
                                    @checked($dipilih)
                                    @disabled($peranAdministrator)
                                >
                                <span>
                                    <span class="permission-name">{{ $izin->nama }}</span>
                                    <span class="permission-code">{{ $izin->kode }}</span>
                                    @if ($izin->deskripsi)
                                        <span class="permission-desc">{{ $izin->deskripsi }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>

        <div class="permission-empty-filter" data-permission-empty>
            Tidak ada izin yang cocok dengan filter.
        </div>

        @if ($peranAdministrator)
            <p class="help-text" style="margin-top: 14px;">Administrator selalu memiliki semua izin aktif. Daftar izin dibuat terkunci agar tidak sengaja dikurangi.</p>
        @endif
    </section>
</div>

<div class="form-actions role-actions">
    <a href="{{ route('peran.index') }}" class="button button-muted">Batal</a>
    <button type="submit" class="button button-primary">Simpan peran</button>
</div>

<script>
    (() => {
        const root = document.querySelector('[data-permission-form]');

        if (!root) {
            return;
        }

        const checkboxes = Array.from(root.querySelectorAll('[data-permission-checkbox]'));
        const groups = Array.from(root.querySelectorAll('[data-permission-group]'));
        const totalSelected = root.querySelector('[data-total-selected]');
        const searchInput = root.querySelector('[data-permission-search-input]');
        const emptyFilter = root.querySelector('[data-permission-empty]');

        function updateCounters() {
            const selectedCount = checkboxes.filter((checkbox) => checkbox.checked).length;

            if (totalSelected) {
                totalSelected.textContent = selectedCount;
            }

            groups.forEach((group) => {
                const groupCheckboxes = Array.from(group.querySelectorAll('[data-permission-checkbox]'));
                const groupSelected = groupCheckboxes.filter((checkbox) => checkbox.checked).length;
                const groupCounter = group.querySelector('[data-group-selected]');

                if (groupCounter) {
                    groupCounter.textContent = groupSelected;
                }

                group.classList.toggle('is-complete', groupCheckboxes.length > 0 && groupSelected === groupCheckboxes.length);
                group.classList.toggle('is-empty', groupSelected === 0);

                group.querySelectorAll('[data-permission-item]').forEach((item) => {
                    const checkbox = item.querySelector('[data-permission-checkbox]');
                    item.classList.toggle('is-selected', checkbox?.checked ?? false);
                });
            });
        }

        function setCheckboxes(scope, checked) {
            scope.querySelectorAll('[data-permission-checkbox]').forEach((checkbox) => {
                if (!checkbox.disabled) {
                    checkbox.checked = checked;
                }
            });

            updateCounters();
        }

        function filterPermissions() {
            const keyword = (searchInput?.value || '').trim().toLowerCase();
            let visibleItems = 0;

            groups.forEach((group) => {
                let visibleInGroup = 0;

                group.querySelectorAll('[data-permission-item]').forEach((item) => {
                    const isVisible = !keyword || (item.dataset.permissionSearch || '').includes(keyword);
                    item.hidden = !isVisible;

                    if (isVisible) {
                        visibleItems += 1;
                        visibleInGroup += 1;
                    }
                });

                group.hidden = visibleInGroup === 0;
            });

            if (emptyFilter) {
                emptyFilter.classList.toggle('is-visible', visibleItems === 0);
            }
        }

        root.addEventListener('change', (event) => {
            if (event.target.matches('[data-permission-checkbox]')) {
                updateCounters();
            }
        });

        root.addEventListener('click', (event) => {
            const button = event.target.closest('[data-permission-action]');

            if (!button || button.disabled) {
                return;
            }

            const action = button.dataset.permissionAction;

            if (action === 'select-all') {
                setCheckboxes(root, true);
            }

            if (action === 'clear-all') {
                setCheckboxes(root, false);
            }

            if (action === 'select-group') {
                setCheckboxes(button.closest('[data-permission-group]'), true);
            }

            if (action === 'clear-group') {
                setCheckboxes(button.closest('[data-permission-group]'), false);
            }
        });

        searchInput?.addEventListener('input', filterPermissions);
        updateCounters();
        filterPermissions();
    })();
</script>
