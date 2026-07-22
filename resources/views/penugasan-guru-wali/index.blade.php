@extends('layouts.app')

@section('title', 'Penugasan Guru Wali - NUSA')

@section('content')
    <style>
        .guardian-layout { display: grid; gap: 20px; grid-template-columns: minmax(300px, .8fr) minmax(0, 1.7fr); }
        .student-choice-list { border: 1px solid var(--line); border-radius: 8px; max-height: 390px; overflow-y: auto; padding: 8px; }
        .student-choice { align-items: flex-start; border-bottom: 1px solid var(--line); cursor: pointer; display: grid; gap: 10px; grid-template-columns: 20px 1fr; padding: 10px 6px; }
        .student-choice:last-child { border-bottom: 0; }
        .student-choice strong, .student-choice span { display: block; }
        .student-choice span { color: var(--muted); font-size: 13px; margin-top: 2px; }
        @media (max-width: 980px) { .guardian-layout { grid-template-columns: 1fr; } }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Penugasan Guru Wali</h1>
            <p class="page-subtitle">Satu guru wali dapat mendampingi siswa dari berbagai kelas selama bersekolah.</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="guardian-layout">
        <form method="POST" action="{{ route('penugasan-guru-wali.store') }}" class="panel panel-pad">
            @csrf
            <h2 class="panel-title">Buat penugasan</h2>

            <div class="field" style="margin-top: 18px;">
                <label for="guru_wali_pegawai_id">Guru wali</label>
                <select id="guru_wali_pegawai_id" name="guru_wali_pegawai_id" class="select" required>
                    <option value="">Pilih pegawai</option>
                    @foreach ($daftarPegawai as $pegawai)
                        <option value="{{ $pegawai->id }}" @selected((string) old('guru_wali_pegawai_id') === (string) $pegawai->id)>
                            {{ $pegawai->nama_lengkap }}{{ $pegawai->nip ? ' - ' . $pegawai->nip : '' }}
                        </option>
                    @endforeach
                </select>
                @error('guru_wali_pegawai_id')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="form-grid" style="margin-top: 14px;">
                <div class="field">
                    <label for="tanggal_mulai">Mulai bertugas</label>
                    <input id="tanggal_mulai" name="tanggal_mulai" type="date" value="{{ old('tanggal_mulai', now()->toDateString()) }}" class="input" required>
                </div>
                <div class="field">
                    <label for="nomor_sk">Nomor SK</label>
                    <input id="nomor_sk" name="nomor_sk" value="{{ old('nomor_sk') }}" class="input" placeholder="Opsional">
                </div>
            </div>

            <div class="field" style="margin-top: 14px;">
                <label for="cari_siswa_guru_wali">Pilih siswa</label>
                <input id="cari_siswa_guru_wali" type="search" class="input" placeholder="Cari nama, NISN, atau kelas">
                <div class="student-choice-list" style="margin-top: 8px;" data-student-list>
                    @foreach ($daftarSiswa as $siswa)
                        @php
                            $anggotaAktif = $siswa->anggotaKelas->first();
                            $kelasNama = $anggotaAktif?->kelas?->nama ?: 'Belum ditempatkan';
                            $guruAktif = $siswa->penugasanGuruWaliSiswa->first()?->guruWali?->nama_lengkap;
                        @endphp
                        <label class="student-choice" data-student-choice data-search="{{ str($siswa->nama_lengkap . ' ' . $siswa->nisn . ' ' . $siswa->nis . ' ' . $kelasNama)->lower() }}">
                            <input type="checkbox" name="siswa_ids[]" value="{{ $siswa->id }}" @checked(in_array($siswa->id, old('siswa_ids', [])))>
                            <span>
                                <strong>{{ $siswa->nama_lengkap }}</strong>
                                <span>{{ $kelasNama }} · NISN {{ $siswa->nisn ?: '-' }}</span>
                                @if ($guruAktif)<span>Guru wali saat ini: {{ $guruAktif }}</span>@endif
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('siswa_ids')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="field" style="margin-top: 14px;">
                <label for="catatan">Catatan</label>
                <textarea id="catatan" name="catatan" class="textarea" placeholder="Opsional">{{ old('catatan') }}</textarea>
            </div>

            <button class="button button-primary button-full" type="submit" style="margin-top: 16px;">Simpan penugasan</button>
        </form>

        <div class="section-stack">
            <form method="GET" class="panel panel-pad">
                <div class="form-grid">
                    <div class="field">
                        <label for="kata_kunci">Cari penugasan</label>
                        <input id="kata_kunci" name="kata_kunci" value="{{ $kataKunci }}" class="input" placeholder="Siswa, NISN, guru wali">
                    </div>
                    <div class="field">
                        <label for="guru_wali_filter">Guru wali</label>
                        <select id="guru_wali_filter" name="guru_wali_pegawai_id" class="select">
                            <option value="">Semua guru wali</option>
                            @foreach ($daftarPegawai as $pegawai)
                                <option value="{{ $pegawai->id }}" @selected((string) $guruWaliDipilih === (string) $pegawai->id)>{{ $pegawai->nama_lengkap }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="actions" style="justify-content: flex-end; margin-top: 14px;">
                    <a href="{{ route('penugasan-guru-wali.index') }}" class="button button-muted">Reset</a>
                    <button class="button button-dark" type="submit">Terapkan</button>
                </div>
            </form>

            <section class="panel">
                <div class="table-wrap desktop-only">
                    <table class="employee-table">
                        <thead><tr><th>Siswa</th><th>Guru Wali</th><th>Mulai</th><th class="text-right">Aksi</th></tr></thead>
                        <tbody>
                            @forelse ($penugasan as $item)
                                <tr>
                                    <td><p class="person-name">{{ $item->siswa?->nama_lengkap }}</p><p class="person-meta">{{ $item->siswa?->anggotaKelas?->first()?->kelas?->nama ?: 'Belum ditempatkan' }} · NISN {{ $item->siswa?->nisn ?: '-' }}</p></td>
                                    <td><p class="person-name">{{ $item->guruWali?->nama_lengkap }}</p><p class="person-meta">{{ $item->guruWali?->nip ?: '-' }}</p></td>
                                    <td>{{ $item->tanggal_mulai?->format('d/m/Y') }}</td>
                                    <td><form method="POST" action="{{ route('penugasan-guru-wali.destroy', $item) }}" onsubmit="return confirm('Akhiri penugasan guru wali ini?')">@csrf @method('DELETE')<button class="button button-danger button-sm" type="submit">Akhiri</button></form></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="empty-state">Belum ada penugasan guru wali.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mobile-only mobile-list">
                    @forelse ($penugasan as $item)
                        <article class="mobile-card">
                            <p class="person-name">{{ $item->siswa?->nama_lengkap }}</p>
                            <p class="person-meta">Guru wali: {{ $item->guruWali?->nama_lengkap }}</p>
                            <p class="person-meta">Mulai {{ $item->tanggal_mulai?->format('d/m/Y') }}</p>
                            <form method="POST" action="{{ route('penugasan-guru-wali.destroy', $item) }}" style="margin-top: 12px;">@csrf @method('DELETE')<button class="button button-danger button-sm" type="submit">Akhiri</button></form>
                        </article>
                    @empty
                        <div class="empty-state">Belum ada penugasan guru wali.</div>
                    @endforelse
                </div>
            </section>

            @if ($penugasan->hasPages()){{ $penugasan->links() }}@endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const search = document.getElementById('cari_siswa_guru_wali');
            const choices = [...document.querySelectorAll('[data-student-choice]')];
            search?.addEventListener('input', () => {
                const keyword = search.value.toLowerCase().trim();
                choices.forEach((choice) => choice.hidden = keyword !== '' && !choice.dataset.search.includes(keyword));
            });
        });
    </script>
@endsection
