@csrf

<style>
    .permission-groups {
        display: grid;
        gap: 14px;
        margin-top: 20px;
    }

    .permission-group {
        border: 1px solid var(--line);
        border-radius: 8px;
        background: #fff;
        padding: 14px;
    }

    .permission-group-title {
        margin: 0 0 10px;
        color: var(--primary);
        font-size: .9rem;
        font-weight: 800;
    }

    .permission-checks {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .permission-check {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 10px;
        background: #fafafa;
    }

    .permission-check input {
        width: 16px;
        height: 16px;
        margin-top: 2px;
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
        margin: 2px 0 0;
        color: var(--muted);
        font-size: .76rem;
        overflow-wrap: anywhere;
    }

    @media (max-width: 900px) {
        .permission-checks {
            grid-template-columns: 1fr;
        }
    }
</style>

@php
    $izinDipilih = collect($izinDipilih ?? [])->map(fn ($id) => (int) $id)->all();
    $peranAdministrator = $peran->kode === 'administrator';
@endphp

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

<section class="permission-groups">
    <div>
        <h2 class="panel-title">Izin akses</h2>
        <p class="help-text">Izin menentukan menu atau tindakan apa yang boleh dilakukan role ini. Cakupan data per kelas/mapel akan diterapkan bertahap pada modul terkait.</p>
    </div>

    @foreach ($daftarIzin as $kelompok => $izinKelompok)
        <div class="permission-group">
            <h3 class="permission-group-title">{{ $kelompok }}</h3>
            <div class="permission-checks">
                @foreach ($izinKelompok as $izin)
                    <label class="permission-check" for="izin-{{ $izin->id }}">
                        <input
                            id="izin-{{ $izin->id }}"
                            type="checkbox"
                            name="izin_ids[]"
                            value="{{ $izin->id }}"
                            @checked($peranAdministrator || in_array($izin->id, $izinDipilih, true))
                            @disabled($peranAdministrator)
                        >
                        <span>
                            <span class="permission-name">{{ $izin->nama }}</span>
                            <span class="permission-code">{{ $izin->kode }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach

    @if ($peranAdministrator)
        <p class="help-text">Administrator selalu memiliki semua izin aktif.</p>
    @endif
</section>

<div class="form-actions" style="margin-top: 20px;">
    <a href="{{ route('peran.index') }}" class="button button-muted">Batal</a>
    <button type="submit" class="button button-primary">Simpan peran</button>
</div>
