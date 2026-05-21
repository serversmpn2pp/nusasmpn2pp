@extends('layouts.app')

@section('title', 'Koreksi Absensi Pegawai - NUSA')

@section('content')
    @php
        $tanggalLabel = \Carbon\Carbon::parse($tanggal)->locale('id')->translatedFormat('d F Y');
        $formatJam = fn (?string $jam) => $jam ? substr($jam, 0, 5) : '';
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $inisial = collect(explode(' ', trim($pegawai->nama_lengkap ?? 'Pegawai')))
            ->filter()
            ->take(2)
            ->map(fn ($kata) => mb_substr($kata, 0, 1))
            ->join('');
        $statusSaatIni = old('status_kehadiran', $absensi?->status_kehadiran ?? 'alfa');
        $labelStatus = \App\Models\AbsensiPegawai::DAFTAR_STATUS_KEHADIRAN;
        $kembaliParams = [
            'tanggal' => $tanggal,
            'pegawai_id' => $pegawai->id,
            'status_pegawai' => $pegawai->aktif ? 'aktif' : 'semua',
        ];
    @endphp

    <style>
        .employee-correction-facts {
            grid-template-columns: 1fr;
        }

        .employee-correction-facts > div {
            min-width: 0;
        }

        .employee-correction-facts dd {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Absensi Pegawai</p>
            <h1 class="page-title">Koreksi absensi pegawai</h1>
        </div>

        <div class="actions">
            <a href="{{ route('rekap-absensi-pegawai-harian.index', $kembaliParams) }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            Periksa kembali data koreksi pegawai.
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">
                    @if ($pegawai->foto)
                        <img src="{{ asset('storage/' . $pegawai->foto) }}" alt="Foto {{ $pegawai->nama_lengkap }}">
                    @else
                        {{ mb_strtoupper($inisial ?: 'P') }}
                    @endif
                </div>
                <h2>{{ $pegawai->nama_lengkap ?: '-' }}</h2>
                <p>{{ $teks($pegawai->jabatan_utama ?: $pegawai->jenis_pegawai) }} - {{ $tanggalLabel }}</p>

                <div style="margin-top: 16px;">
                    @if ($pegawai->aktif)
                        <span class="badge badge-active">Pegawai aktif</span>
                    @else
                        <span class="badge badge-danger">Pegawai nonaktif</span>
                    @endif
                </div>
            </div>

            <dl class="quick-facts employee-correction-facts" style="margin-top: 20px;">
                <div>
                    <dt>NIP</dt>
                    <dd>{{ $teks($pegawai->nip) }}</dd>
                </div>
                <div>
                    <dt>Jenis pegawai</dt>
                    <dd>{{ $teks($pegawai->jenis_pegawai) }}</dd>
                </div>
                <div>
                    <dt>Status pegawai</dt>
                    <dd>{{ $teks($pegawai->status_kepegawaian) }}</dd>
                </div>
                <div>
                    <dt>Sumber</dt>
                    <dd>{{ $absensi ? ucfirst($absensi->sumber) : 'Belum ada catatan' }}</dd>
                </div>
            </dl>

            @if ($pengaturanAbsensiPegawai)
                <div class="alert" style="margin: 20px 0 0;">
                    <strong>{{ $pengaturanAbsensiPegawai->nama_jadwal }}</strong><br>
                    Masuk {{ $pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_masuk) }},
                    pulang {{ $pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_pulang) }}.
                    <p class="help-text" style="margin-top: 8px;">
                        Scan masuk {{ $pengaturanAbsensiPegawai->rentangMasuk() }},
                        scan pulang {{ $pengaturanAbsensiPegawai->rentangPulang() }}.
                    </p>
                </div>
            @else
                <div class="alert alert-danger" style="margin: 20px 0 0;">
                    Jadwal absensi pegawai tanggal ini belum aktif. Jam tetap bisa disimpan, tetapi status terlambat/pulang cepat tidak dihitung.
                </div>
            @endif
        </aside>

        <section class="panel panel-pad">
            <h2 class="panel-title">Data koreksi</h2>

            <form action="{{ route('rekap-absensi-pegawai-harian.koreksi.update', $pegawai) }}" method="POST" style="margin-top: 16px;">
                @csrf
                @method('PUT')
                <input type="hidden" name="tanggal" value="{{ $tanggal }}">

                <div class="form-grid">
                    <div class="field span-2">
                        <label for="status_kehadiran">Status kehadiran</label>
                        <select id="status_kehadiran" name="status_kehadiran" class="select @error('status_kehadiran') is-invalid @enderror" required>
                            @foreach ($labelStatus as $key => $label)
                                <option value="{{ $key }}" @selected($statusSaatIni === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="help-text">Pilih izin, sakit, dinas luar, cuti, atau alfa jika pegawai tidak hadir di sekolah.</p>
                        @error('status_kehadiran')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="jam_masuk">Jam masuk</label>
                        <input id="jam_masuk" type="time" name="jam_masuk" value="{{ old('jam_masuk', $formatJam($absensi?->jam_masuk)) }}" class="input @error('jam_masuk') is-invalid @enderror">
                        <p class="help-text">Wajib diisi jika status hadir.</p>
                        @error('jam_masuk')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="jam_pulang">Jam pulang</label>
                        <input id="jam_pulang" type="time" name="jam_pulang" value="{{ old('jam_pulang', $formatJam($absensi?->jam_pulang)) }}" class="input @error('jam_pulang') is-invalid @enderror">
                        <p class="help-text">Boleh dikosongkan jika pegawai belum scan pulang.</p>
                        @error('jam_pulang')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field span-2">
                        <label for="catatan">Catatan</label>
                        <textarea id="catatan" name="catatan" class="textarea @error('catatan') is-invalid @enderror" placeholder="Contoh: Dinas luar, lupa scan, izin keluarga, atau koreksi operator.">{{ old('catatan', $absensi?->catatan) }}</textarea>
                        @error('catatan')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 20px;">
                    <a href="{{ route('rekap-absensi-pegawai-harian.index', $kembaliParams) }}" class="button button-muted">Batal</a>
                    <button type="submit" class="button button-primary">Simpan koreksi</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        const statusInput = document.getElementById('status_kehadiran');
        const jamMasukInput = document.getElementById('jam_masuk');
        const jamPulangInput = document.getElementById('jam_pulang');

        function sinkronkanJam() {
            const hadir = statusInput.value === 'hadir';
            jamMasukInput.disabled = ! hadir;
            jamPulangInput.disabled = ! hadir;

            if (! hadir) {
                jamMasukInput.value = '';
                jamPulangInput.value = '';
            }
        }

        statusInput.addEventListener('change', sinkronkanJam);
        sinkronkanJam();
    </script>
@endsection
