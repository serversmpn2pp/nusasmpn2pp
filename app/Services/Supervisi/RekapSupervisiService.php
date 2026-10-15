<?php

namespace App\Services\Supervisi;

use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\ProgramSupervisi;
use App\Models\SesiSupervisi;
use App\Models\TahunPelajaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RekapSupervisiService
{
    public const TAHAP = [
        'draf_jadwal' => 'Jadwal belum terbit', 'menunggu_praobservasi' => 'Menunggu praobservasi',
        'menunggu_penilaian' => 'Menunggu hasil final', 'perlu_periksa' => 'Nilai perlu diperiksa',
        'menunggu_refleksi' => 'Menunggu refleksi', 'tinjau_refleksi' => 'Refleksi perlu ditinjau',
        'penyesuaian_hasil' => 'Perlu penyesuaian hasil', 'tindak_lanjut' => 'Tindak lanjut berlangsung',
        'menunggu_verifikasi' => 'Menunggu verifikasi', 'selesai' => 'Pascaobservasi selesai', 'dibatalkan' => 'Dibatalkan',
    ];

    public function __construct(private AksesSupervisi $akses, private PengisianSupervisiService $pengisian) {}

    public function bangun(Pengguna $u, array $input): array
    {
        $this->akses->staff($u);
        $f = Validator::make($input, [
            'tahun_pelajaran_id' => ['nullable', 'integer', 'min:1'], 'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
            'program_id' => ['nullable', 'integer', 'min:1'], 'guru_id' => ['nullable', 'integer', 'min:1'], 'supervisor_id' => ['nullable', 'integer', 'min:1'],
            'tahap' => ['nullable', Rule::in(array_keys(self::TAHAP))], 'terlambat' => ['nullable', 'boolean'],
            'kata_kunci' => ['nullable', 'string', 'max:120'], 'tab' => ['nullable', Rule::in(['sesi', 'belum_terjadwal'])],
            'halaman' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        $global = $this->akses->global($u);
        $tahun = TahunPelajaran::query()->when(! $global, fn ($q) => $q->whereIn('id', $this->dasar($u)->join('program_supervisi as ps', 'sesi_supervisi.program_supervisi_id', '=', 'ps.id')->select('ps.tahun_pelajaran_id')))
            ->orderByDesc('aktif')->orderByDesc('id')->get(['id', 'nama', 'aktif']);
        $f['tahun_pelajaran_id'] = isset($f['tahun_pelajaran_id']) ? (int) $f['tahun_pelajaran_id'] : $tahun->first()?->id;
        if ($f['tahun_pelajaran_id']) {
            abort_unless($tahun->contains('id', $f['tahun_pelajaran_id']), 404);
        }
        $f['semester'] ??= now()->month >= 7 ? 'ganjil' : 'genap';
        $f['tab'] ??= 'sesi';
        $f['terlambat'] = (bool) ($f['terlambat'] ?? false);
        $f['kata_kunci'] = trim($f['kata_kunci'] ?? '');
        if ($f['tab'] === 'belum_terjadwal') {
            abort_unless($global, 403);
            if (filled($f['supervisor_id'] ?? null) || filled($f['tahap'] ?? null) || $f['terlambat']) {
                throw ValidationException::withMessages(['tab' => 'Filter supervisor, tahap, dan tenggat hanya berlaku untuk rekap sesi.']);
            }
        }
        $q = $this->dasar($u)->whereHas('program', fn ($q) => $q->where('tahun_pelajaran_id', $f['tahun_pelajaran_id'] ?? 0)->where('semester', $f['semester']));
        $program = ProgramSupervisi::where('tahun_pelajaran_id', $f['tahun_pelajaran_id'] ?? 0)->where('semester', $f['semester'])
            ->when(! $global, fn ($q) => $q->whereIn('id', $this->dasar($u)->select('program_supervisi_id')))->orderBy('nama')->get(['id', 'nama']);
        if (filled($f['program_id'] ?? null)) {
            abort_unless($program->contains('id', (int) $f['program_id']), 404);
            $q->where('program_supervisi_id', $f['program_id']);
        }
        $roster = $global ? $this->roster($f['tahun_pelajaran_id'] ?? 0) : collect();
        $guruIds = (clone $q)->select('guru_pegawai_id');
        $guru = Pegawai::whereIn('id', $global ? $roster->pluck('id')->merge((clone $q)->pluck('guru_pegawai_id'))->unique() : $guruIds)->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']);
        $supervisor = Pegawai::whereIn('id', (clone $q)->select('supervisor_pegawai_id'))->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']);
        foreach (['guru_id' => $guru, 'supervisor_id' => $supervisor] as $key => $pilihan) {
            if (filled($f[$key] ?? null)) {
                abort_unless($pilihan->contains('id', (int) $f[$key]), 404);
            }
        }
        $judul = ($tahun->firstWhere('id', $f['tahun_pelajaran_id'])?->nama ?? 'Belum ada tahun pelajaran').' · Semester '.ucfirst($f['semester']);
        $label = [$judul];
        if (filled($f['program_id'] ?? null)) {
            $label[] = $program->firstWhere('id', (int) $f['program_id'])->nama;
        }
        if (filled($f['guru_id'] ?? null)) {
            $label[] = 'Guru: '.$guru->firstWhere('id', (int) $f['guru_id'])->nama_lengkap;
        }
        if (filled($f['supervisor_id'] ?? null)) {
            $label[] = 'Supervisor: '.$supervisor->firstWhere('id', (int) $f['supervisor_id'])->nama_lengkap;
        }
        if (filled($f['tahap'] ?? null)) {
            $label[] = self::TAHAP[$f['tahap']];
        }
        if ($f['terlambat']) {
            $label[] = 'Tindak lanjut melewati tenggat';
        }
        if ($f['kata_kunci'] !== '') {
            $label[] = 'Pencarian: '.$f['kata_kunci'];
        }
        $hasil = ['filter' => $f, 'label_filter' => $label, 'pilihan' => ['tahun' => $tahun, 'program' => $program, 'guru' => $guru, 'supervisor' => $supervisor, 'tahap' => self::TAHAP], 'global' => $global];

        if ($f['tab'] === 'belum_terjadwal') {
            $terjadwal = (clone $q)->where('status', 'terjadwal')->whereNotNull('diterbitkan_pada')->pluck('guru_pegawai_id')->unique();
            $acuan = $roster->when(filled($f['guru_id'] ?? null), fn ($c) => $c->where('id', (int) $f['guru_id']))
                ->filter(fn ($g) => $this->cocok($f['kata_kunci'], $g['guru'].' '.$g['mapel'].' '.$g['kelas']));
            $daftar = $acuan->whereNotIn('id', $terjadwal)->values();

            return [...$hasil, 'semua' => $daftar, 'statistik' => ['guru_aktif' => $acuan->count(), 'sudah_terjadwal' => $acuan->whereIn('id', $terjadwal)->count(), 'belum_terjadwal' => $daftar->count()]];
        }
        $q->when(filled($f['guru_id'] ?? null), fn ($q) => $q->where('guru_pegawai_id', $f['guru_id']))
            ->when(filled($f['supervisor_id'] ?? null), fn ($q) => $q->where('supervisor_pegawai_id', $f['supervisor_id']));
        if ((clone $q)->count() > 1500) {
            throw ValidationException::withMessages(['program_id' => 'Maksimal 1.500 sesi per rekap. Persempit filter program atau guru.']);
        }
        $rows = collect();
        foreach ($q->with($this->relasi())->orderBy('id')->lazy(20) as $s) {
            $r = $this->baris($s);
            if (filled($f['tahap'] ?? null) && $r['tahap'] !== $f['tahap']) {
                continue;
            }
            if ($f['terlambat'] && $r['tindak_lanjut_terlambat'] === 0) {
                continue;
            }
            if (! $this->cocok($f['kata_kunci'], $r['guru'].' '.$r['mapel'].' '.$r['kelas'].' '.$r['program'])) {
                continue;
            }
            $rows->push($r);
        }
        $rows = $rows->sortBy([['waktu_mulai', 'asc'], ['id', 'asc']])->values();
        $nilai = $rows->pluck('nilai_akhir')->filter(fn ($v) => $v !== null);

        return [...$hasil, 'semua' => $rows, 'statistik' => ['sesi' => $rows->count(), 'guru' => $rows->pluck('guru_id')->unique()->count(),
            'hasil_final' => $nilai->count(), 'selesai' => $rows->where('tahap', 'selesai')->count(), 'rata_rata' => $nilai->isNotEmpty() ? round($nilai->avg(), 2) : null,
            'melewati_tenggat' => $rows->sum('tindak_lanjut_terlambat'), 'belum_final' => $rows->whereNotIn('tahap', ['dibatalkan', 'draf_jadwal'])->whereNull('nilai_akhir')->count()]];
    }

    private function dasar(Pengguna $u): Builder
    {
        return $this->akses->sesi(SesiSupervisi::query(), $u);
    }

    private function cocok(string $cari, string $isi): bool
    {
        return $cari === '' || str_contains(mb_strtolower($isi), mb_strtolower($cari));
    }

    private function roster(int $tahun): Collection
    {
        $penugasan = fn ($q) => $q->where('tahun_pelajaran_id', $tahun)->where('aktif', true)->whereHas('kelas', fn ($q) => $q->where('aktif', true))->whereHas('mataPelajaran', fn ($q) => $q->where('aktif', true));

        return Pegawai::where('aktif', true)->whereHas('guruMataPelajaran', $penugasan)->with(['guruMataPelajaran' => fn ($q) => $penugasan($q)->with(['kelas:id,nama', 'mataPelajaran:id,nama'])])->orderBy('nama_lengkap')->get()
            ->map(fn ($g) => ['id' => $g->id, 'guru' => $g->nama_lengkap, 'mapel' => $g->guruMataPelajaran->pluck('mataPelajaran.nama')->unique()->sort()->implode(', '), 'kelas' => $g->guruMataPelajaran->pluck('kelas.nama')->unique()->sort()->implode(', '), 'status' => 'Belum memiliki jadwal terbit']);
    }

    public function relasi(): array
    {
        return ['program.tahunPelajaran', 'guru:id,nama_lengkap,nip,nuptk', 'supervisor:id,nama_lengkap,nip,nuptk', 'instrumen',
            'praobservasi:id,sesi_supervisi_id,status,versi', 'penilaian:id,sesi_supervisi_id,status,versi,praobservasi_versi,nilai',
            'pascaobservasi:id,sesi_supervisi_id,status,penilaian_versi', 'tindakLanjut:id,sesi_supervisi_id,status,penilaian_versi,diterbitkan_pada,tenggat'];
    }

    public function baris(SesiSupervisi $s): array
    {
        $p = $s->penilaian;
        $valid = $s->status === 'terjadwal' && $p?->status === 'final' && $s->praobservasi?->status === 'terkirim' && $p->praobservasi_versi === $s->praobservasi->versi;
        $angka = $valid ? $this->pengisian->hitung($s, $p->nilai) : null;
        $valid = $valid && ($angka['lengkap'] ?? false);
        $pasca = $s->pascaobservasi;
        $terbaru = $valid && $pasca && $pasca->penilaian_versi === $p->versi;
        $tugas = $s->tindakLanjut->filter(fn ($t) => $valid && $t->penilaian_versi === $p->versi && $t->diterbitkan_pada && $t->status !== 'dibatalkan');
        $terlambat = $tugas->filter(fn ($t) => in_array($t->status, ['ditugaskan', 'dikembalikan'], true) && $t->tenggat?->lt(today()))->count();
        $tahap = match (true) {
            $s->status === 'dibatalkan' => 'dibatalkan', $s->status === 'draf' => 'draf_jadwal',
            $p?->status === 'final' && ! $valid => 'perlu_periksa',
            $s->praobservasi?->status !== 'terkirim' => 'menunggu_praobservasi', ! $valid => 'menunggu_penilaian',
            $pasca && ! $terbaru => 'penyesuaian_hasil', $terbaru && $pasca->status === 'selesai' => 'selesai',
            $pasca?->status === 'terkirim' => 'tinjau_refleksi', $tugas->contains('status', 'diajukan') => 'menunggu_verifikasi',
            $pasca?->status === 'diterima' => 'tindak_lanjut', default => 'menunggu_refleksi',
        };

        return ['id' => $s->id, 'guru_id' => $s->guru_pegawai_id, 'supervisor_id' => $s->supervisor_pegawai_id,
            'guru' => $s->konteks['guru'] ?? $s->guru->nama_lengkap, 'supervisor' => $s->supervisor->nama_lengkap,
            'mapel' => $s->konteks['mapel'], 'kelas' => $s->konteks['kelas'], 'program' => $s->program->nama,
            'waktu_mulai' => $s->waktu_mulai->toIso8601String(), 'tanggal' => $s->waktu_mulai->format('d-m-Y'),
            'nilai_perencanaan' => $valid ? $angka['tahap']['perencanaan']['nilai'] : null,
            'nilai_pelaksanaan' => $valid ? $angka['tahap']['pelaksanaan']['nilai'] : null,
            'nilai_akhir' => $valid ? $angka['nilai_akhir'] : null, 'kategori' => $valid ? $angka['kategori'] : null,
            'tahap' => $tahap, 'label_tahap' => self::TAHAP[$tahap], 'tindak_lanjut' => $tugas->count(),
            'tindak_lanjut_selesai' => $tugas->where('status', 'selesai')->count(), 'tindak_lanjut_terlambat' => $terlambat, 'bisa_cetak' => $valid];
    }
}
