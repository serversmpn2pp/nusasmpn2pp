@extends('layouts.app')

@section('title', 'Ganti Guru Pengampu - NUSA')

@section('content')
    @php
        $romawi = [7 => 'VII', 8 => 'VIII', 9 => 'IX'];
        $penugasanTerpilih = collect(old('penugasan_ids', [$guruMataPelajaran->id]))
            ->map(fn ($id) => (string) $id);
        $tanggalMulai = $guruMataPelajaran->tahunPelajaran?->tanggal_mulai?->format('Y-m-d');
        $tanggalSelesai = $guruMataPelajaran->tahunPelajaran?->tanggal_selesai?->format('Y-m-d');
        $tanggalMaksimal = min($tanggalSelesai ?: today()->format('Y-m-d'), today()->format('Y-m-d'));
    @endphp

    <style>
        .replacement-shell {
            display: grid;
            grid-template-columns: minmax(250px, 320px) minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .replacement-summary {
            position: sticky;
            top: 92px;
        }

        .replacement-flow {
            display: grid;
            gap: 16px;
        }

        .replacement-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid #dce5ee;
        }

        .replacement-note {
            padding: 14px;
            border-left: 4px solid #f1c40f;
            background: #f7fafc;
            color: #40566c;
            font-size: 0.9rem;
            line-height: 1.55;
        }

        .replacement-class-groups {
            display: grid;
            gap: 20px;
        }

        .replacement-level-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: #15477a;
            font-size: 0.94rem;
        }

        .replacement-level-title::after {
            width: 100%;
            height: 1px;
            background: #dce5ee;
            content: "";
        }

        .replacement-class-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .replacement-class {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 58px;
            padding: 12px 14px;
            border: 1px solid #d7e1ea;
            border-radius: 7px;
            background: #fff;
            cursor: pointer;
        }

        .replacement-class:has(input:checked) {
            border-color: #15477a;
            background: #eef5fb;
            box-shadow: inset 3px 0 0 #f1c40f;
        }

        .replacement-class input {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
            accent-color: #15477a;
        }

        @media (max-width: 920px) {
            .replacement-shell {
                grid-template-columns: 1fr;
            }

            .replacement-summary {
                position: static;
            }
        }

        @media (max-width: 720px) {
            .replacement-class-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 520px) {
            .replacement-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .replacement-toolbar .actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .replacement-class-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Ganti Guru Pengampu</h1>
            <p class="help-text">Alihkan satu atau beberapa kelas tanpa memutus jadwal dan nilai yang sudah ada.</p>
        </div>

        <a href="{{ route('guru-mata-pelajaran.index') }}" class="button button-muted">Kembali</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ada data yang perlu diperbaiki.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        action="{{ route('guru-mata-pelajaran.simpan-pergantian', $guruMataPelajaran) }}"
        method="POST"
        class="replacement-shell"
    >
        @csrf
        @method('PUT')

        <aside class="panel panel-pad replacement-summary">
            <p class="eyebrow">Penugasan Saat Ini</p>
            <h2 class="panel-title" style="margin-top: 6px;">{{ $guruMataPelajaran->pegawai?->nama_lengkap ?: '-' }}</h2>
            <p class="help-text" style="margin-top: 5px;">{{ $guruMataPelajaran->pegawai?->nip ?: 'NIP belum diisi' }}</p>

            <dl class="quick-facts" style="margin-top: 20px;">
                <div>
                    <dt>Mata pelajaran</dt>
                    <dd>{{ $guruMataPelajaran->mataPelajaran?->nama ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Tahun pelajaran</dt>
                    <dd>{{ $guruMataPelajaran->tahunPelajaran?->nama ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Kelas tersedia</dt>
                    <dd>{{ $penugasanTerkait->count() }} kelas</dd>
                </div>
            </dl>

            <div class="replacement-note" style="margin-top: 20px;">
                Guru lama kehilangan akses ke kelas yang dialihkan. Guru baru dapat melanjutkan jadwal, komponen nilai, dan nilai siswa yang sudah tersimpan.
            </div>
        </aside>

        <div class="replacement-flow">
            <section class="panel panel-pad">
                <h2 class="panel-title">Guru Pengganti</h2>
                <div class="form-grid" style="margin-top: 16px;">
                    <div class="field">
                        <label for="pegawai_baru_id">Guru baru</label>
                        <select id="pegawai_baru_id" name="pegawai_baru_id" class="select @error('pegawai_baru_id') is-invalid @enderror" required autofocus>
                            <option value="">Pilih guru pengganti</option>
                            @foreach ($pegawaiPengganti as $pegawai)
                                <option value="{{ $pegawai->id }}" @selected((string) old('pegawai_baru_id') === (string) $pegawai->id)>
                                    {{ $pegawai->nama_lengkap }}{{ $pegawai->nip ? ' - ' . $pegawai->nip : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('pegawai_baru_id')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                        @if ($pegawaiPengganti->isEmpty())
                            <p class="error-text">Belum ada pegawai aktif berjenis Guru yang dapat dipilih.</p>
                        @endif
                    </div>

                    <div class="field">
                        <label for="tanggal_efektif">Tanggal efektif</label>
                        <input
                            id="tanggal_efektif"
                            name="tanggal_efektif"
                            type="date"
                            min="{{ $tanggalMulai }}"
                            max="{{ $tanggalMaksimal }}"
                            value="{{ old('tanggal_efektif', $tanggalMaksimal) }}"
                            class="input @error('tanggal_efektif') is-invalid @enderror"
                            required
                        >
                        @error('tanggal_efektif')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field span-2">
                        <label for="alasan">Alasan pergantian</label>
                        <textarea
                            id="alasan"
                            name="alasan"
                            class="textarea @error('alasan') is-invalid @enderror"
                            placeholder="Contoh: perubahan pembagian tugas mulai semester genap"
                            required
                        >{{ old('alasan') }}</textarea>
                        @error('alasan')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="panel panel-pad">
                <div class="replacement-toolbar">
                    <div>
                        <h2 class="panel-title">Kelas yang Dialihkan</h2>
                        <p class="help-text" id="jumlah-pergantian">0 kelas dipilih</p>
                    </div>
                    <div class="actions">
                        <button type="button" class="button button-muted" id="pilih-semua-pergantian">Pilih semua</button>
                        <button type="button" class="button button-muted" id="bersihkan-pergantian">Bersihkan</button>
                    </div>
                </div>

                @error('penugasan_ids')
                    <p class="error-text" style="margin-bottom: 14px;">{{ $message }}</p>
                @enderror
                @error('penugasan_ids.*')
                    <p class="error-text" style="margin-bottom: 14px;">{{ $message }}</p>
                @enderror

                <div class="replacement-class-groups">
                    @foreach ($penugasanTerkait->groupBy(fn ($item) => $item->kelas?->tingkat) as $tingkat => $daftarPenugasan)
                        <div>
                            <h3 class="replacement-level-title">Tingkat {{ $romawi[$tingkat] ?? $tingkat }}</h3>
                            <div class="replacement-class-grid">
                                @foreach ($daftarPenugasan as $penugasan)
                                    <label class="replacement-class">
                                        <input
                                            type="checkbox"
                                            name="penugasan_ids[]"
                                            value="{{ $penugasan->id }}"
                                            @checked($penugasanTerpilih->contains((string) $penugasan->id))
                                        >
                                        <strong>{{ $penugasan->kelas?->nama ?: '-' }}</strong>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="form-actions">
                <a href="{{ route('guru-mata-pelajaran.index') }}" class="button button-muted">Batal</a>
                <button type="submit" class="button button-primary" @disabled($pegawaiPengganti->isEmpty())>Simpan Pergantian</button>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            (() => {
                const pilihan = Array.from(document.querySelectorAll('input[name="penugasan_ids[]"]'));
                const jumlah = document.getElementById('jumlah-pergantian');
                const perbaruiJumlah = () => {
                    const terpilih = pilihan.filter((input) => input.checked).length;
                    jumlah.textContent = `${terpilih} kelas dipilih`;
                };

                pilihan.forEach((input) => input.addEventListener('change', perbaruiJumlah));
                document.getElementById('pilih-semua-pergantian').addEventListener('click', () => {
                    pilihan.forEach((input) => {
                        input.checked = true;
                    });
                    perbaruiJumlah();
                });
                document.getElementById('bersihkan-pergantian').addEventListener('click', () => {
                    pilihan.forEach((input) => {
                        input.checked = false;
                    });
                    perbaruiJumlah();
                });

                perbaruiJumlah();
            })();
        </script>
    @endpush
@endsection
