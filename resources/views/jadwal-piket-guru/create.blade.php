@extends('layouts.app')

@section('title', 'Tambah Guru Piket - NUSA')

@section('content')
    <style>
        .form-layout { display:grid; grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr); gap:18px; align-items:start; }
        .field-full { grid-column:1 / -1; }
        .section-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
        .check-row { display:flex; align-items:flex-start; gap:10px; border:1px solid var(--line); border-radius:8px; padding:13px; background:var(--soft); }
        .check-row input { margin-top:3px; }.check-row strong,.check-row small { display:block; }.check-row small { margin-top:3px; color:var(--muted); }
        .teacher-picker { max-height:440px; overflow:auto; border:1px solid var(--line); border-radius:8px; background:#fff; }
        .teacher-option { display:flex; align-items:flex-start; gap:11px; padding:13px 14px; cursor:pointer; }
        .teacher-option + .teacher-option { border-top:1px solid var(--line); }
        .teacher-option:hover { background:var(--primary-soft); }
        .teacher-option input { margin-top:3px; }
        .teacher-option strong { display:block; color:var(--primary-dark); }
        .teacher-option small { display:block; margin-top:3px; color:var(--muted); }
        @media(max-width:900px){ .form-layout { grid-template-columns:1fr; } }
    </style>

    <div class="page-header">
        <div><p class="eyebrow">Kehadiran Siswa</p><h1 class="page-title">Tambah Guru Piket</h1><p class="page-subtitle">Pilih beberapa guru sekaligus untuk satu hari piket.</p></div>
        <a href="{{ route('jadwal-piket-guru.index', ['tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-muted">Kembali</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><strong>Data belum dapat disimpan.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ route('jadwal-piket-guru.store') }}" method="POST">
        @csrf
        <div class="form-layout">
            <section class="panel panel-pad">
                <h2 class="panel-title">Jadwal Piket</h2>
                <div class="form-grid" style="margin-top:16px;">
                    <div class="field">
                        <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                        <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select" required>
                            @foreach ($tahunPelajaran as $item)
                                <option value="{{ $item->id }}" @selected((int) old('tahun_pelajaran_id', $tahunPelajaranId) === (int) $item->id)>{{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}</option>
                            @endforeach
                        </select>
                        <p class="help-text">Mengganti tahun akan memuat daftar guru mapel pada tahun tersebut.</p>
                    </div>
                    <div class="field">
                        <label for="hari">Hari</label>
                        <select id="hari" name="hari" class="select" required>
                            @foreach ($daftarHari as $kode => $label)<option value="{{ $kode }}" @selected(old('hari') === $kode)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field field-full">
                        <label for="keterangan">Keterangan</label>
                        <textarea id="keterangan" name="keterangan" class="textarea" rows="3" placeholder="Opsional, misalnya koordinator piket">{{ old('keterangan') }}</textarea>
                    </div>
                    <label class="check-row field-full"><input type="checkbox" name="aktif" value="1" @checked(old('aktif', true))><span><strong>Jadwal aktif</strong><small>Guru dapat menjalankan tugas piket pada hari ini.</small></span></label>
                </div>
            </section>

            <section class="panel panel-pad">
                <div class="section-heading"><div><h2 class="panel-title">Pilih Guru Mata Pelajaran</h2><p class="help-text">Hanya guru dengan penugasan mapel aktif yang ditampilkan.</p></div><span class="badge badge-muted">{{ $guruMapel->count() }} guru</span></div>
                <div class="field" style="margin:16px 0 10px;"><label for="cari_guru">Cari guru</label><input id="cari_guru" type="search" class="input" placeholder="Ketik nama atau NIP" autocomplete="off"></div>
                <div class="actions" style="margin-bottom:10px;"><button type="button" class="button button-muted button-sm" data-select-all>Pilih semua</button><button type="button" class="button button-muted button-sm" data-clear-all>Kosongkan</button></div>
                <div class="teacher-picker" data-teacher-list>
                    @forelse ($guruMapel as $guru)
                        <label class="teacher-option" data-search="{{ mb_strtolower($guru->nama_lengkap.' '.$guru->nip) }}">
                            <input type="checkbox" name="pegawai_ids[]" value="{{ $guru->id }}" @checked(in_array($guru->id, old('pegawai_ids', [])))>
                            <span><strong>{{ $guru->nama_lengkap }}</strong><small>{{ $guru->nip ?: 'NIP belum diisi' }}</small></span>
                        </label>
                    @empty
                        <p class="empty-state">Belum ada guru mata pelajaran aktif pada tahun pelajaran ini.</p>
                    @endforelse
                </div>
            </section>
        </div>
        <div class="actions" style="justify-content:flex-end; margin-top:18px;"><a href="{{ route('jadwal-piket-guru.index', ['tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-muted">Batal</a><button type="submit" class="button button-primary">Simpan jadwal</button></div>
    </form>

    <script>
        (() => {
            const year = document.getElementById('tahun_pelajaran_id');
            const search = document.getElementById('cari_guru');
            const options = [...document.querySelectorAll('.teacher-option')];
            year?.addEventListener('change', () => { window.location.href = `{{ route('jadwal-piket-guru.create') }}?tahun_pelajaran_id=${encodeURIComponent(year.value)}`; });
            search?.addEventListener('input', () => { const q = search.value.trim().toLowerCase(); options.forEach(option => { option.hidden = q !== '' && !option.dataset.search.includes(q); }); });
            document.querySelector('[data-select-all]')?.addEventListener('click', () => options.filter(option => !option.hidden).forEach(option => option.querySelector('input').checked = true));
            document.querySelector('[data-clear-all]')?.addEventListener('click', () => options.forEach(option => option.querySelector('input').checked = false));
        })();
    </script>
@endsection
