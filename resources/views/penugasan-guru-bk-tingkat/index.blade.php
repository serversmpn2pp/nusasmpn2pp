@extends('layouts.app')

@section('title', 'Penugasan Tingkat Guru BK - NUSA')

@section('content')
    <style>
        .bk-assignment-intro { align-items:stretch; display:grid; gap:16px; grid-template-columns:minmax(0,1fr) 320px; margin-bottom:20px; }
        .bk-assignment-notes { display:grid; gap:10px; grid-template-columns:repeat(3,minmax(0,1fr)); }
        .bk-assignment-note { align-items:flex-start; background:#fff; border:1px solid #cbd9e7; border-left:4px solid var(--primary); border-radius:7px; display:grid; gap:3px; grid-template-columns:32px minmax(0,1fr); min-height:112px; padding:14px; }
        .bk-assignment-note-number { align-items:center; background:var(--primary-soft); border:1px solid #c8dced; border-radius:50%; color:var(--primary-dark); display:flex; font-size:13px; font-weight:850; height:32px; justify-content:center; width:32px; }
        .bk-assignment-note-copy { display:grid; gap:4px; min-width:0; padding-top:2px; }
        .bk-assignment-note strong { color:var(--primary-dark); font-size:13px; line-height:1.35; }
        .bk-assignment-note span { color:var(--muted); font-size:13px; line-height:1.45; }
        .bk-year-filter { align-items:center; border-color:#cbd9e7; display:flex; margin:0; padding:16px 18px; }
        .bk-year-filter .field { margin:0; width:100%; }
        .bk-year-filter .select { border-color:#b9c9d9; height:46px; width:100%; }
        .bk-assignment-panel { border-color:#cbd9e7; }
        .bk-assignment-form { align-items:end; display:grid; gap:14px; grid-template-columns:minmax(250px,1.1fr) minmax(390px,1.25fr) 166px; }
        .bk-assignment-form .field { margin:0; min-width:0; }
        .bk-assignment-form .select { border-color:#b9c9d9; height:46px; width:100%; }
        .bk-assignment-form > .button { height:46px; width:100%; }
        .bk-grade-options { display:grid; gap:8px; grid-template-columns:repeat(3,minmax(0,1fr)); }
        .bk-assignment-form .bk-grade-options > label.bk-grade-option { align-items:center; background:#fff; border:1px solid #b9c9d9; border-radius:7px; cursor:pointer; display:flex; font-size:14px; font-weight:750; gap:8px; height:46px; margin:0; padding:9px 11px; }
        .bk-assignment-form .bk-grade-options > label.bk-grade-option:has(input:checked) { background:#edf5fc; border-color:#91b9dd; color:var(--primary); }
        .bk-grade-option input { accent-color:var(--primary); flex:0 0 auto; height:16px; margin:0; width:16px; }
        .bk-grade-grid { display:grid; gap:14px; grid-template-columns:repeat(3,minmax(0,1fr)); margin-top:20px; }
        .bk-grade-card { background:#fff; border:1px solid #c5d5e4; border-radius:8px; display:grid; grid-template-rows:auto 1fr; min-height:142px; min-width:0; overflow:hidden; }
        .bk-grade-head { align-items:center; background:#eaf2f9; border-bottom:1px solid #c5d5e4; display:flex; gap:12px; justify-content:space-between; min-height:56px; padding:13px 16px; }
        .bk-grade-head h2 { color:var(--primary-dark); font-size:17px; margin:0; }
        .bk-assignee-list { display:grid; }
        .bk-assignee { align-items:center; border-bottom:1px solid var(--line); display:grid; gap:10px; grid-template-columns:minmax(0,1fr) auto; padding:14px 16px; }
        .bk-assignee:last-child { border-bottom:0; }
        .bk-assignee-name { font-weight:800; margin:0; overflow-wrap:anywhere; }
        .bk-assignee-meta { color:var(--muted); font-size:12px; margin:3px 0 0; }
        .bk-grade-empty { align-items:center; color:var(--muted); display:flex; font-size:13px; justify-content:center; line-height:1.5; min-height:84px; padding:18px 20px; text-align:center; }
        .bk-unassigned { background:#fff8d9; border:1px solid #dfc552; border-left:4px solid #c69e00; border-radius:7px; color:#685200; font-size:13px; margin-top:20px; padding:12px 14px; }
        @media(max-width:1080px){.bk-assignment-intro{grid-template-columns:1fr}.bk-assignment-notes{grid-template-columns:repeat(3,minmax(0,1fr))}.bk-year-filter{min-height:auto}.bk-assignment-form{grid-template-columns:1fr 1.15fr}.bk-assignment-form>.button{grid-column:1/-1}}
        @media(max-width:760px){.bk-assignment-notes,.bk-assignment-form,.bk-grade-grid{grid-template-columns:1fr}.bk-assignment-note{min-height:auto}.bk-assignment-form>.button{grid-column:auto}}
        @media(max-width:520px){.bk-grade-options{grid-template-columns:1fr}.bk-grade-option{width:100%}}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Penugasan Tingkat Guru BK</h1>
            <p class="page-subtitle">Atur Guru BK yang menerima notifikasi dan menangani laporan siswa tingkat 7, 8, atau 9.</p>
        </div>
    </div>

    @if(session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><strong>Ada data yang perlu diperbaiki.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="bk-assignment-intro">
        <div class="bk-assignment-notes">
            <div class="bk-assignment-note"><span class="bk-assignment-note-number">1</span><div class="bk-assignment-note-copy"><strong>Tetap dapat melihat semua laporan</strong><span>Semua Guru BK dapat membuka laporan seluruh tingkat untuk pemantauan.</span></div></div>
            <div class="bk-assignment-note"><span class="bk-assignment-note-number">2</span><div class="bk-assignment-note-copy"><strong>Tindakan mengikuti penugasan</strong><span>Keputusan, klarifikasi, bukti, dan tindak lanjut hanya dapat dikerjakan oleh Guru BK tingkat terkait.</span></div></div>
            <div class="bk-assignment-note"><span class="bk-assignment-note-number">3</span><div class="bk-assignment-note-copy"><strong>Notifikasi lebih terarah</strong><span>Laporan baru dan pengingat hanya dikirim kepada Guru BK yang ditugaskan pada tingkat siswa.</span></div></div>
        </div>
        <form method="GET" class="panel panel-pad bk-year-filter">
            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select" onchange="this.form.submit()">
                    @foreach($daftarTahunPelajaran as $tahun)
                        <option value="{{ $tahun->id }}" @selected((int)$tahunPelajaran->id===(int)$tahun->id)>{{ $tahun->nama }}{{ $tahun->aktif?' (Aktif)':'' }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <section class="panel panel-pad bk-assignment-panel">
        <div class="page-header" style="margin-bottom:16px">
            <div><h2 class="panel-title">Tambah penugasan</h2><p class="help-text">Satu Guru BK boleh ditugaskan pada beberapa tingkat. Satu tingkat juga boleh ditangani bersama.</p></div>
        </div>
        @if($daftarGuruBk->isEmpty())
            <div class="empty-state">Belum ada pegawai dengan akun aktif dan role Guru BK.</div>
        @else
            <form method="POST" action="{{ route('penugasan-guru-bk-tingkat.store') }}" class="bk-assignment-form">
                @csrf
                <input type="hidden" name="tahun_pelajaran_id" value="{{ $tahunPelajaran->id }}">
                <div class="field">
                    <label for="pegawai_id">Guru BK</label>
                    <select id="pegawai_id" name="pegawai_id" class="select" required>
                        <option value="">Pilih Guru BK</option>
                        @foreach($daftarGuruBk as $guruBk)
                            <option value="{{ $guruBk->id }}" @selected((int)old('pegawai_id')===(int)$guruBk->id)>{{ $guruBk->nama_lengkap }}{{ $guruBk->nip?' - '.$guruBk->nip:'' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Tingkat yang ditangani</label>
                    <div class="bk-grade-options">
                        @foreach($daftarTingkat as $kode=>$label)
                            <label class="bk-grade-option"><input type="checkbox" name="tingkat[]" value="{{ $kode }}" @checked(in_array($kode,old('tingkat',[])))><span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                </div>
                <button class="button button-primary">Simpan penugasan</button>
            </form>
        @endif
    </section>

    @unless($pembagianAktif)
        <div class="bk-unassigned"><strong>Pembagian belum diaktifkan.</strong> Agar laporan yang sudah ada tidak terhenti, seluruh Guru BK masih dapat menangani laporan sampai penugasan pertama disimpan.</div>
    @endunless

    <div class="bk-grade-grid">
        @foreach($daftarTingkat as $kode=>$label)
            @php($daftarPetugas=$penugasan->get($kode,collect()))
            <section class="bk-grade-card">
                <div class="bk-grade-head"><h2>{{ $label }}</h2><span class="badge {{ $daftarPetugas->isEmpty()?'badge-warning':'badge-active' }}">{{ $daftarPetugas->count() }} Guru BK</span></div>
                <div class="bk-assignee-list">
                    @forelse($daftarPetugas as $item)
                        <article class="bk-assignee">
                            <div><p class="bk-assignee-name">{{ $item->pegawai?->nama_lengkap }}</p><p class="bk-assignee-meta">{{ $item->pegawai?->nip ?: 'NIP belum diisi' }}</p></div>
                            <form method="POST" action="{{ route('penugasan-guru-bk-tingkat.destroy',$item) }}" onsubmit="return confirm('Akhiri penugasan Guru BK ini?')">@csrf @method('DELETE')<button class="button button-danger button-sm">Hapus</button></form>
                        </article>
                    @empty
                        <div class="bk-grade-empty">Belum ada Guru BK untuk {{ mb_strtolower($label) }}. Tambahkan petugas agar laporan tingkat ini dapat segera diproses.</div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
@endsection
