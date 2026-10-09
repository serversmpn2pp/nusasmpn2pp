@extends('layouts.app')
@section('title', 'Lampiran Perilaku STS - NUSA')
@section('content')
    <style>
        .behavior-filters { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr) auto; gap:16px; align-items:end; padding:20px 0; border-bottom:1px solid var(--line); }
        .behavior-summary { display:flex; flex-wrap:wrap; gap:16px 32px; padding:20px 0; }
        .behavior-summary strong { margin-right:8px; }
        .behavior-summary span { color:var(--muted); font-size:.85rem; }
        .behavior-student { border-bottom:1px solid var(--line); padding:18px 0; }
        .behavior-student > summary { cursor:pointer; font-weight:600; line-height:1.6; }
        .behavior-state { display:inline-block; font-size:.75rem; padding:3px 8px; border-radius:4px; color:#875e12; background:#fff5dc; margin-left:12px; }
        .behavior-state.is-ready { color:#236443; background:#eaf6ef; }
        .behavior-form { margin-top:16px; }
        .behavior-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; margin-bottom:18px; }
        .behavior-wrap { overflow-x:auto; border:1px solid var(--line); border-radius:6px; }
        .behavior-table { width:100%; min-width:720px; table-layout:fixed; border-collapse:collapse; font-size:.85rem; }
        .behavior-table th,.behavior-table td { text-align:left; border-bottom:1px solid var(--line); padding:12px; vertical-align:top; overflow-wrap:anywhere; }
        .behavior-table th { background:#f0f4f8; font-size:.78rem; }
        .behavior-table tr:last-child td { border-bottom:0; }
        .behavior-table textarea { width:100%; height:86px; resize:vertical; font-size:.85rem; }
        .behavior-note { margin:18px 0; }
        .behavior-note textarea { width:100%; min-height:76px; resize:vertical; }
        .behavior-actions { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px; margin-top:18px; }
        .behavior-check { display:flex; align-items:flex-start; gap:9px; font-size:.85rem; }
        .behavior-check input { width:18px; height:18px; flex:none; accent-color:var(--primary); }
        .behavior-notice { margin:16px 0; padding:12px 16px; border-left:3px solid #bf8a23; background:#fff9eb; font-size:.85rem; line-height:1.5; }
        .behavior-empty { padding:20px 0; color:var(--muted); }
        .behavior-audit { font-size:.8rem; color:var(--muted); margin-top:14px; }
        @media(max-width:640px) { .behavior-filters,.behavior-grid { grid-template-columns:minmax(0,1fr); } .behavior-actions .button { width:100%; } .behavior-state { margin:8px 0 0; } }
    </style>
    <div class="page-header"><div><p class="eyebrow">Kesiswaan & BK</p><h1 class="page-title">Lampiran Perilaku STS</h1></div></div>
    <form method="GET" class="behavior-filters" action="{{ route('lampiran-perilaku-sts.index') }}">
        <div class="field"><label for="behavior-event">Kegiatan STS</label><select class="select" id="behavior-event" name="kegiatan_id">
            @forelse($daftarKegiatan as $item)<option value="{{ $item->id }}" @selected($kegiatan?->id === $item->id)>{{ $item->nama }} · {{ $item->tahunPelajaran?->nama }}</option>@empty<option value="">Belum ada kegiatan STS</option>@endforelse
        </select></div>
        <div class="field"><label for="behavior-class">Kelas</label><select class="select" id="behavior-class" name="kelas_id">
            @forelse($daftarKelas as $item)<option value="{{ $item->id }}" @selected($kelas?->id === $item->id)>{{ $item->nama }}</option>@empty<option value="">Belum ada kelas dalam penugasan</option>@endforelse
        </select></div>
        <button class="button button-primary" @disabled($daftarKegiatan->isEmpty())>Tampilkan</button>
    </form>
    @if($konteks)
        @php $pengaturan = $konteks['pengaturan']; $siap = $konteks['baris']->where('siap', true)->count(); @endphp
        <div class="behavior-summary"><div><strong>{{ $konteks['baris']->count() }}</strong><span>Siswa</span></div><div><strong>{{ $siap }}</strong><span>Lampiran diperiksa</span></div><div><span>Periode</span> <strong>{{ $pengaturan->tanggal_awal_presensi->format('d-m-Y') }} s.d. {{ $pengaturan->tanggal_akhir_presensi->format('d-m-Y') }}</strong></div></div>
        @if(!$pengaturan->exists)<p class="behavior-notice">Periode rapor belum disimpan oleh wali kelas.</p>@endif
        @if(!$pengaturan->tanggal_akhir_presensi->copy()->endOfDay()->isPast())<p class="behavior-notice">Pemeriksaan dapat disahkan setelah batas periode laporan berakhir.</p>@endif
        @if($konteks['guruBk']->isEmpty())<p class="behavior-notice">Belum ada Guru BK aktif yang ditugaskan untuk tingkat {{ $kelas->tingkat }} pada tanggal rapor.</p>@endif
        @if($konteks['wakilKesiswaan']->isEmpty())<p class="behavior-notice">Belum ada pegawai aktif dengan peran Wakil Kepala Sekolah Bidang Kesiswaan.</p>@endif
        @foreach($konteks['baris'] as $item)
            @php
                $id = $item['anggota']->id; $simpan = $item['tersimpan'];
                $edit = $item['siap'] ? $item['baris'] : $item['sumber'];
                $disabled = !$pengaturan->exists || !$pengaturan->tanggal_akhir_presensi->copy()->endOfDay()->isPast() || $konteks['guruBk']->isEmpty() || $konteks['wakilKesiswaan']->isEmpty();
                $isOld = (int) old('anggota_id') === $id;
            @endphp
            <details class="behavior-student" data-behavior-student @if((int) session('perilaku_anggota', old('anggota_id')) === $id) open @endif>
                <summary>{{ $item['anggota']->nomor_absen }}. {{ $item['anggota']->siswa->nama_lengkap }} <span class="behavior-state {{ $item['siap'] ? 'is-ready' : '' }}">{{ $item['siap'] ? 'Diperiksa' : ($simpan ? 'Perlu diperiksa ulang' : 'Belum diperiksa') }}</span></summary>
                <form class="behavior-form" method="POST" action="{{ route('lampiran-perilaku-sts.simpan', [$kegiatan, $kelas]) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="anggota_id" value="{{ $id }}"><input type="hidden" name="versi" value="{{ $simpan?->versi ?? 0 }}"><input type="hidden" name="sidik_sumber" value="{{ $item['sidik_sumber'] }}">
                    <div class="behavior-grid">
                        <div class="field"><label for="behavior-bk-{{ $id }}">Guru BK tingkat {{ $kelas->tingkat }}</label><select class="select" id="behavior-bk-{{ $id }}" name="guru_bk_id" required @disabled($disabled)>
                            <option value="">Pilih Guru BK</option>@foreach($konteks['guruBk'] as $p)<option value="{{ $p->id }}" @selected((int) ($isOld ? old('guru_bk_id') : ($simpan?->guru_bk_id ?? ($konteks['guruBk']->count() === 1 ? $p->id : null))) === $p->id)>{{ $p->nama_lengkap }}</option>@endforeach
                        </select></div>
                        <div class="field"><label for="behavior-wakil-{{ $id }}">Wakil Kepala Sekolah Bidang Kesiswaan</label><select class="select" id="behavior-wakil-{{ $id }}" name="wakil_kesiswaan_id" required @disabled($disabled)>
                            <option value="">Pilih Wakil Kesiswaan</option>@foreach($konteks['wakilKesiswaan'] as $p)<option value="{{ $p->id }}" @selected((int) ($isOld ? old('wakil_kesiswaan_id') : ($simpan?->wakil_kesiswaan_id ?? ($konteks['wakilKesiswaan']->count() === 1 ? $p->id : null))) === $p->id)>{{ $p->nama_lengkap }}</option>@endforeach
                        </select></div>
                    </div>
                    @if($edit->isNotEmpty())
                    <div class="behavior-wrap" tabindex="0" aria-label="Kejadian {{ $item['anggota']->siswa->nama_lengkap }}"><table class="behavior-table">
                        <colgroup><col style="width:14%"><col style="width:31%"><col style="width:31%"><col style="width:8%"><col style="width:16%"></colgroup>
                        <thead><tr><th>Tanggal</th><th>Kejadian / pelanggaran</th><th>Teguran / tindak lanjut</th><th>Poin</th><th>Status</th></tr></thead><tbody>
                        @foreach($edit as $r)
                            <tr><td>{{ \Carbon\Carbon::parse($r['tanggal'])->format('d-m-Y') }}</td>
                                <td><textarea class="input" name="baris[{{ $r['kunci'] }}][kejadian]" maxlength="200" required aria-label="Ringkasan kejadian {{ $loop->iteration }}" @disabled($disabled)>{{ $isOld ? old('baris.'.$r['kunci'].'.kejadian', $r['kejadian']) : $r['kejadian'] }}</textarea></td>
                                <td><textarea class="input" name="baris[{{ $r['kunci'] }}][tindakan]" maxlength="200" required aria-label="Tindak lanjut {{ $loop->iteration }}" @disabled($disabled)>{{ $isOld ? old('baris.'.$r['kunci'].'.tindakan', $r['tindakan']) : $r['tindakan'] }}</textarea></td>
                                <td>{{ $r['poin'] ?: '—' }}</td><td>{{ $r['status'] }}</td></tr>
                        @endforeach
                        </tbody></table></div>
                    @else<p class="behavior-empty">Tidak ada catatan pelanggaran terverifikasi pada periode ini.</p>@endif
                    <div class="behavior-summary"><div><span>Poin masuk</span> <strong>{{ $item['ringkasan']['poin_masuk'] }}</strong></div><div><span>Pengurangan / koreksi</span> <strong>{{ $item['ringkasan']['poin_dikurangi'] }}</strong></div><div><span>Saldo sampai batas laporan</span> <strong>{{ $item['ringkasan']['saldo'] }}</strong></div></div>
                    <div class="field behavior-note"><label for="behavior-note-{{ $id }}">Catatan pembinaan untuk orang tua</label><textarea class="input" id="behavior-note-{{ $id }}" name="catatan" maxlength="600" @disabled($disabled)>{{ $isOld ? old('catatan', $simpan?->catatan) : $simpan?->catatan }}</textarea></div>
                    <div class="behavior-actions"><label class="behavior-check"><input type="checkbox" name="diperiksa" value="1" required @disabled($disabled)> Ringkasan dan tindak lanjut telah diperiksa serta layak disampaikan kepada orang tua.</label><button class="button button-primary" @disabled($disabled)>Simpan pemeriksaan</button></div>
                    @if($simpan)<p class="behavior-audit">Pemeriksaan terakhir: {{ $simpan->pemeriksa?->nama }} · {{ $simpan->diperiksa_pada->format('d-m-Y H:i') }} · Versi {{ $simpan->versi }}</p>@endif
                </form>
            </details>
        @endforeach
    @else<p class="behavior-empty">Belum ada kegiatan STS atau kelas sesuai penugasan BK Anda.</p>@endif
    <script>
        document.getElementById('behavior-event')?.addEventListener('change', function () {
            document.getElementById('behavior-class').disabled = true;
            this.form.submit();
        });
        let dirty = false;
        document.querySelectorAll('.behavior-form').forEach(form => {
            form.addEventListener('input', () => { dirty = true; });
            form.addEventListener('submit', () => { dirty = false; });
        });
        window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    </script>
@endsection
