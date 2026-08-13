@extends('layouts.app')

@section('title', 'Koreksi Presensi Ibadah - NUSA')

@section('content')
    @php
        $statusSaatIni = old('status_presensi', 'sudah');
        $waktuSaatIni = old('waktu_presensi', $presensi ? substr((string) $presensi->waktu_scan, 0, 5) : ($jadwal?->formatJam($jadwal->jam_pelaksanaan) ?? ''));
        $foto = $anggotaKelas->siswa?->foto
            ? asset('storage/'.$anggotaKelas->siswa->foto)
            : asset('images/kartu-pelajar/default-user.png');
        $kembali = route('rekap-kegiatan-ibadah.index', [
            'tanggal' => $tanggal,
            'kegiatan_ibadah_id' => $kegiatan->id,
            'kelas_id' => $anggotaKelas->kelas_id,
        ]);
        $labelTindakan = ['tambah' => 'Input manual', 'ubah' => 'Dikoreksi', 'hapus' => 'Dibatalkan'];
    @endphp

    <style>
        .worship-correction-layout { display:grid; grid-template-columns:300px minmax(0,1fr); gap:22px; align-items:start; }
        .worship-student-photo { display:block; width:128px; height:154px; margin:0 auto 15px; border:3px solid #fff; border-radius:8px; background:#e5e7eb; box-shadow:0 0 0 1px var(--line),0 8px 22px rgba(21,71,122,.13); object-fit:cover; }
        .worship-student-name { margin:0; text-align:center; font-size:1.1rem; line-height:1.3; }.worship-student-meta { margin:5px 0 0; color:var(--muted); text-align:center; font-size:.86rem; }
        .worship-current { margin-top:18px; padding:13px 14px; border:1px solid var(--line); border-radius:8px; background:#f7f9fb; }.worship-current strong,.worship-current span { display:block; }.worship-current span { margin-top:3px; color:var(--muted); font-size:.8rem; }
        .worship-history { display:grid; gap:9px; margin-top:12px; }.worship-history-item { padding:11px 12px; border:1px solid var(--line); border-radius:8px; background:#fff; }.worship-history-head { display:flex; justify-content:space-between; gap:10px; }.worship-history-item p { margin:5px 0 0; color:var(--muted); font-size:.8rem; }.worship-history-item time { color:var(--muted); font-size:.75rem; white-space:nowrap; }
        .worship-form-note { margin:0 0 18px; padding:13px 14px; border-left:4px solid var(--accent); background:var(--accent-soft); color:var(--accent-text); font-size:.88rem; }
        @media(max-width:800px){.worship-correction-layout{grid-template-columns:1fr}.worship-student-summary{display:grid;grid-template-columns:92px minmax(0,1fr);gap:14px;align-items:center}.worship-student-photo{width:92px;height:110px;margin:0}.worship-student-name,.worship-student-meta{text-align:left}.worship-current{grid-column:1/-1;margin-top:2px}}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kegiatan Ibadah</p>
            <h1 class="page-title">{{ $presensi ? 'Koreksi presensi' : 'Input presensi manual' }}</h1>
            <p class="page-subtitle">{{ $kegiatan->nama }} &middot; {{ $tanggalLabel }}</p>
        </div>
        <div class="actions"><a href="{{ $kembali }}" class="button button-muted">Kembali</a></div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">Periksa kembali data koreksi.<ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="worship-correction-layout">
        <aside class="section-stack">
            <section class="panel panel-pad worship-student-summary">
                <img class="worship-student-photo" src="{{ $foto }}" alt="Foto {{ $anggotaKelas->siswa?->nama_lengkap }}">
                <div>
                    <h2 class="worship-student-name">{{ $anggotaKelas->siswa?->nama_lengkap ?: '-' }}</h2>
                    <p class="worship-student-meta">{{ $anggotaKelas->kelas?->nama }} &middot; NISN {{ $anggotaKelas->siswa?->nisn ?: '-' }}</p>
                </div>
                <div class="worship-current">
                    <strong>Status saat ini: {{ $presensi ? 'Sudah presensi' : 'Belum presensi' }}</strong>
                    <span>{{ $presensi ? 'Tercatat pukul '.substr((string) $presensi->waktu_scan, 0, 5).' melalui '.$presensi->sumber.'.' : 'Belum ada QR atau input manual yang tercatat.' }}</span>
                    @if($presensi?->dikoreksiOleh)
                        <span>Koreksi terakhir oleh {{ $presensi->dikoreksiOleh->nama }}.</span>
                    @endif
                </div>
            </section>

            @if($riwayat->isNotEmpty())
                <section class="panel panel-pad">
                    <h2 class="panel-title">Riwayat perubahan</h2>
                    <div class="worship-history">
                        @foreach($riwayat as $item)
                            <article class="worship-history-item">
                                <div class="worship-history-head"><strong>{{ $labelTindakan[$item->tindakan] ?? ucfirst($item->tindakan) }}</strong><time>{{ $item->created_at?->format('d/m/Y H:i') }}</time></div>
                                <p>{{ $item->waktu_sebelum ? substr((string) $item->waktu_sebelum, 0, 5) : 'Belum' }} &rarr; {{ $item->waktu_sesudah ? substr((string) $item->waktu_sesudah, 0, 5) : 'Belum' }}</p>
                                <p>{{ $item->alasan }} &middot; {{ $item->diubahOleh?->nama ?: 'Pengguna tidak tersedia' }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>

        <section class="panel panel-pad">
            <h2 class="panel-title">Data presensi</h2>
            <p class="worship-form-note">Pilih <strong>Sudah presensi</strong> untuk menambah atau memperbaiki waktu. Pilih <strong>Belum presensi</strong> untuk membatalkan catatan yang keliru. Alasan wajib diisi dan tersimpan dalam riwayat.</p>

            @if($jadwal)
                <div class="alert">Jadwal pelaksanaan {{ $jadwal->formatJam($jadwal->jam_pelaksanaan) }}, jendela scan {{ $jadwal->rentangScan() }}.</div>
            @else
                <div class="alert alert-danger">Tidak ada jadwal kegiatan pada tanggal ini. Catatan lama masih dapat dikoreksi, tetapi input presensi baru tidak dapat dibuat.</div>
            @endif

            <form id="form-koreksi-ibadah" action="{{ route('rekap-kegiatan-ibadah.koreksi.update', $anggotaKelas) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="tanggal" value="{{ $tanggal }}">
                <input type="hidden" name="kegiatan_ibadah_id" value="{{ $kegiatan->id }}">

                <div class="form-grid">
                    <div class="field span-2">
                        <label for="status_presensi">Status presensi</label>
                        <select id="status_presensi" name="status_presensi" class="select @error('status_presensi') is-invalid @enderror" required>
                            <option value="sudah" @selected($statusSaatIni === 'sudah')>Sudah presensi</option>
                            <option value="belum" @selected($statusSaatIni === 'belum')>Belum presensi</option>
                        </select>
                        @error('status_presensi')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                    <div class="field span-2" id="wadah-waktu-presensi">
                        <label for="waktu_presensi">Waktu presensi</label>
                        <input id="waktu_presensi" name="waktu_presensi" type="time" value="{{ $waktuSaatIni }}" class="input @error('waktu_presensi') is-invalid @enderror">
                        <p class="help-text">Isi waktu siswa mengikuti kegiatan. Untuk input manual, waktu pelaksanaan digunakan sebagai nilai awal.</p>
                        @error('waktu_presensi')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                    <div class="field span-2">
                        <label for="alasan">Alasan koreksi/input manual</label>
                        <textarea id="alasan" name="alasan" class="textarea @error('alasan') is-invalid @enderror" required placeholder="Contoh: Siswa lupa membawa kartu, tetapi kehadirannya dikonfirmasi oleh guru piket.">{{ old('alasan', $presensi?->catatan_koreksi) }}</textarea>
                        @error('alasan')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="form-actions" style="margin-top:20px;">
                    <a href="{{ $kembali }}" class="button button-muted">Batal</a>
                    <button type="submit" class="button button-primary">Simpan perubahan</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        (() => {
            const form = document.getElementById('form-koreksi-ibadah');
            const status = document.getElementById('status_presensi');
            const waktu = document.getElementById('waktu_presensi');
            const wadahWaktu = document.getElementById('wadah-waktu-presensi');

            const sinkronkan = () => {
                const sudah = status.value === 'sudah';
                waktu.disabled = !sudah;
                waktu.required = sudah;
                wadahWaktu.hidden = !sudah;
            };

            status.addEventListener('change', sinkronkan);
            form.addEventListener('submit', (event) => {
                if (status.value === 'belum' && !window.confirm('Batalkan catatan presensi siswa ini? Riwayat pembatalan tetap disimpan.')) {
                    event.preventDefault();
                }
            });
            sinkronkan();
        })();
    </script>
@endsection
