@extends('layouts.app')

@section('title', 'Penugasan Tingkat Guru BK - NUSA')

@section('content')
    <style>
        .bk-assignment-intro { align-items:start; display:grid; gap:18px; grid-template-columns:minmax(0,1fr) minmax(220px,320px); margin-bottom:20px; }
        .bk-assignment-notes { display:grid; gap:10px; }
        .bk-assignment-note { align-items:start; background:#f4f8fc; border-left:3px solid var(--primary); border-radius:6px; display:grid; gap:3px; padding:11px 13px; }
        .bk-assignment-note strong { color:var(--primary-dark); font-size:13px; }
        .bk-assignment-note span { color:var(--muted); font-size:13px; line-height:1.45; }
        .bk-assignment-form { align-items:end; display:grid; gap:14px; grid-template-columns:minmax(240px,1fr) minmax(300px,1.15fr) auto; }
        .bk-grade-options { display:grid; gap:8px; grid-template-columns:repeat(3,minmax(0,1fr)); }
        .bk-grade-option { align-items:center; border:1px solid var(--line); border-radius:7px; cursor:pointer; display:flex; font-size:14px; font-weight:750; gap:8px; min-height:44px; padding:9px 11px; }
        .bk-grade-option:has(input:checked) { background:#edf5fc; border-color:#91b9dd; color:var(--primary); }
        .bk-grade-option input { accent-color:var(--primary); margin:0; }
        .bk-grade-grid { display:grid; gap:14px; grid-template-columns:repeat(3,minmax(0,1fr)); margin-top:20px; }
        .bk-grade-card { background:#fff; border:1px solid var(--line); border-radius:8px; min-width:0; overflow:hidden; }
        .bk-grade-head { align-items:center; background:#edf4fa; border-bottom:1px solid #d8e4ef; display:flex; justify-content:space-between; padding:15px 16px; }
        .bk-grade-head h2 { color:var(--primary-dark); font-size:17px; margin:0; }
        .bk-assignee-list { display:grid; }
        .bk-assignee { align-items:center; border-bottom:1px solid var(--line); display:grid; gap:10px; grid-template-columns:minmax(0,1fr) auto; padding:14px 16px; }
        .bk-assignee:last-child { border-bottom:0; }
        .bk-assignee-name { font-weight:800; margin:0; overflow-wrap:anywhere; }
        .bk-assignee-meta { color:var(--muted); font-size:12px; margin:3px 0 0; }
        .bk-grade-empty { color:var(--muted); font-size:13px; line-height:1.5; padding:20px 16px; text-align:center; }
        .bk-unassigned { background:#fff7d6; border:1px solid #ecd56b; border-radius:6px; color:#685200; font-size:13px; margin-top:20px; padding:11px 13px; }
        @media(max-width:980px){.bk-assignment-intro,.bk-assignment-form{grid-template-columns:1fr}.bk-grade-grid{grid-template-columns:1fr}.bk-assignment-form .button{width:100%}}
        @media(max-width:520px){.bk-grade-options{grid-template-columns:1fr}}
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
            <div class="bk-assignment-note"><strong>Tetap dapat melihat semua laporan</strong><span>Semua Guru BK dapat membuka laporan seluruh tingkat untuk pemantauan.</span></div>
            <div class="bk-assignment-note"><strong>Tindakan mengikuti penugasan</strong><span>Keputusan, klarifikasi, bukti, dan tindak lanjut hanya dapat dikerjakan oleh Guru BK tingkat terkait.</span></div>
            <div class="bk-assignment-note"><strong>Notifikasi lebih terarah</strong><span>Laporan baru dan pengingat hanya dikirim kepada Guru BK yang ditugaskan pada tingkat siswa.</span></div>
        </div>
        <form method="GET" class="panel panel-pad">
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

    <section class="panel panel-pad">
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
