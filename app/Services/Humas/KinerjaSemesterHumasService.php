<?php

namespace App\Services\Humas;

use App\Models\KinerjaSemesterHumas;
use App\Models\LaporanPelaksanaanHumas;
use App\Models\Pengguna;
use App\Models\ProgramKerjaHumas;
use App\Models\UmpanBalikHumas;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KinerjaSemesterHumasService
{
    private const IZIN_SUMBER = [
        'program' => ['program_kerja_humas.lihat', 'program_kerja_humas.kelola'],
        'laporan' => ['program_kerja_humas.lihat', 'program_kerja_humas.kelola'],
        'agenda' => ['agenda_humas.lihat', 'agenda_humas.kelola'],
        'kemitraan' => ['kemitraan_humas.lihat', 'kemitraan_humas.kelola'],
        'publikasi' => ['publikasi_humas.lihat', 'publikasi_humas.kelola', 'publikasi_humas.periksa'],
        'prestasi' => ['prestasi_sekolah.lihat', 'prestasi_sekolah.kelola'],
        'tamu' => ['buku_tamu.lihat', 'buku_tamu.kelola'],
        'pengaduan_masuk' => ['pengaduan_humas.lihat', 'pengaduan_humas.kelola'],
        'pengaduan_selesai' => ['pengaduan_humas.lihat', 'pengaduan_humas.kelola'],
        'pengaduan' => ['pengaduan_humas.lihat', 'pengaduan_humas.kelola'],
        'kliping' => ['kliping_berita_humas.lihat', 'kliping_berita_humas.kelola'],
        'alumni' => ['alumni_humas.lihat', 'alumni_humas.kelola'],
        'evaluasi' => ['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola'],
        'respons_evaluasi' => ['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola'],
    ];

    public function akses(Pengguna $u, bool $kelola = false): void
    {
        abort_unless($u->aktif && ! $u->akunSiswa() && ! $u->akunOrangTua()
            && $u->memilikiIzin($kelola ? 'kinerja_humas.kelola' : ['kinerja_humas.lihat', 'kinerja_humas.kelola']), 403);
    }

    public function periode(Request $r): array
    {
        $data = $r->validate(['tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'semester' => ['required', Rule::in(array_keys(KinerjaSemesterHumas::SEMESTER))]]);
        $periode = app(PeriodeDashboardHumasService::class)->periode(new Request(['tahun_pelajaran_id' => $data['tahun_pelajaran_id'], 'periode' => $data['semester']]));
        if ($periode['mulai']->isFuture()) {
            throw ValidationException::withMessages(['semester' => 'Semester belum dimulai. Pilih periode yang sudah berjalan.']);
        }

        return $periode;
    }

    public function pratinjau(Request $r): array
    {
        $this->akses($r->user(), true);

        return $this->transaksi(fn () => $this->himpun($r->user(), $this->periode($r)));
    }

    private function himpun(Pengguna $u, array $p): array
    {
        abort_unless($this->bolehSumber($u, 'program'), 403);
        $akhir = $p['selesai']->min(CarbonImmutable::today());
        $tahunId = $p['filter']['tahun_pelajaran_id'];
        $semester = $p['filter']['periode'];
        $program = ProgramKerjaHumas::where('tahun_pelajaran_id', $tahunId)->whereIn('semester', [$semester, 'tahunan'])
            ->where('status', '<>', 'dibatalkan')->whereDate('tanggal_mulai', '<=', $p['selesai'])->whereDate('tanggal_selesai', '>=', $p['mulai'])
            ->withCount(['laporanFinal as realisasi' => fn ($q) => $q->whereDate('tanggal_selesai', '>=', $p['mulai'])->whereDate('tanggal_selesai', '<=', $akhir)])
            ->orderBy('bidang')->orderBy('nama')->orderBy('id')->limit(501)->get();
        if ($program->count() > 500) {
            throw ValidationException::withMessages(['semester' => 'Laporan memuat lebih dari 500 program. Rapikan program periode ini sebelum menghimpun laporan.']);
        }
        $ringkasan = app(DashboardHumasService::class)->ringkasan($u, $p['mulai'], $akhir, $tahunId);
        $metrik = array_map(fn ($m) => Arr::only($m, ['label', 'jumlah', 'dasar']), $ringkasan['metrik']);
        $metrik['program'] = ['label' => 'Program selesai', 'jumlah' => $program->where('status', 'selesai')->count(), 'dasar' => 'Program semester terpilih dan tahunan; status saat dihimpun; program dibatalkan dikecualikan.'];
        $ids = $program->modelKeys();
        $metrik['laporan'] = ['label' => 'Laporan kegiatan final', 'jumlah' => LaporanPelaksanaanHumas::whereIn('program_kerja_humas_id', $ids)->where('status', 'final')
            ->whereDate('tanggal_selesai', '>=', $p['mulai'])->whereDate('tanggal_selesai', '<=', $akhir)->count(), 'dasar' => 'Tanggal selesai kegiatan dalam periode data; hanya program semester terpilih/tahunan yang tidak dibatalkan.'];
        if ($this->bolehSumber($u, 'evaluasi')) {
            $q = UmpanBalikHumas::where('tahun_pelajaran_id', $tahunId)->whereNotNull('dibuka_pada')
                ->whereDate('dibuka_pada', '>=', $p['mulai'])->whereDate('dibuka_pada', '<=', $akhir);
            $metrik['evaluasi'] = ['label' => 'Instrumen evaluasi dibuka', 'jumlah' => (clone $q)->count(), 'dasar' => 'Tanggal pertama instrumen dibuka; sesuai tahun pelajaran.'];
            $respons = DB::table('sasaran_umpan_balik_humas')->whereIn('umpan_balik_humas_id', UmpanBalikHumas::where('tahun_pelajaran_id', $tahunId)->select('id'))
                ->whereDate('dikirim_pada', '>=', $p['mulai'])->whereDate('dikirim_pada', '<=', $akhir)->count();
            $metrik['respons_evaluasi'] = ['label' => 'Respons evaluasi diterima', 'jumlah' => $respons, 'dasar' => 'Tanggal pengiriman; tidak menyertakan identitas atau isi jawaban.'];
        }
        // Recompute the core trend from the same program scope as the semester totals.
        $bulan = $ringkasan['bulan'];
        $jumlahBulanan = LaporanPelaksanaanHumas::whereIn('program_kerja_humas_id', $ids)->where('status', 'final')
            ->whereDate('tanggal_selesai', '>=', $p['mulai'])->whereDate('tanggal_selesai', '<=', $akhir)
            ->selectRaw('SUBSTR(CAST(tanggal_selesai AS TEXT), 1, 7) AS bulan, COUNT(*) AS jumlah')->groupByRaw('SUBSTR(CAST(tanggal_selesai AS TEXT), 1, 7)')->pluck('jumlah', 'bulan');
        foreach ($bulan as $kode => &$b) {
            $b['jumlah']['laporan'] = (int) ($jumlahBulanan[$kode] ?? 0);
        }
        unset($b);
        $metrik = array_filter($metrik, fn ($kode) => $this->bolehSumber($u, $kode), ARRAY_FILTER_USE_KEY);
        $kolom = array_filter($ringkasan['kolomBulanan'], fn ($kode) => $this->bolehSumber($u, $kode), ARRAY_FILTER_USE_KEY);
        foreach ($bulan as &$b) {
            $b['jumlah'] = array_intersect_key($b['jumlah'], $kolom);
        }
        unset($b);

        return ['skema' => 1, 'tahun_pelajaran' => $p['tahun']->firstWhere('id', $tahunId)->nama, 'semester' => $semester,
            'tanggal_mulai' => $p['mulai']->toDateString(), 'tanggal_selesai' => $p['selesai']->toDateString(), 'data_sampai' => $akhir->toDateString(),
            'sementara' => $akhir->lt($p['selesai']), 'dihimpun_pada' => now()->toIso8601String(), 'metrik' => $metrik, 'jumlah_program' => $program->count(),
            'program' => $program->map(fn ($v) => ['id' => $v->id, 'nama' => $v->nama, 'bidang' => $v->bidang, 'semester' => $v->semester, 'status' => $v->status,
                'target' => $v->target_kegiatan, 'realisasi' => $v->realisasi, 'tanggal_mulai' => $v->tanggal_mulai->toDateString(), 'tanggal_selesai' => $v->tanggal_selesai->toDateString()])->all(),
            'kolom_bulanan' => $kolom, 'bulan' => $bulan];
    }

    public function tampil(array $s, Pengguna $u): array
    {
        $metrik = array_filter($s['metrik'] ?? [], fn ($kode) => $this->bolehSumber($u, $kode), ARRAY_FILTER_USE_KEY);
        $kolom = array_filter($s['kolom_bulanan'] ?? [], fn ($kode) => $this->bolehSumber($u, $kode), ARRAY_FILTER_USE_KEY);
        $bulan = [];
        foreach ($s['bulan'] ?? [] as $key => $b) {
            $bulan[$key] = ['label' => $b['label'], 'jumlah' => array_intersect_key($b['jumlah'], $kolom)];
        }
        $bolehProgram = $this->bolehSumber($u, 'program');

        return Arr::only($s, ['skema', 'tahun_pelajaran', 'semester', 'tanggal_mulai', 'tanggal_selesai', 'data_sampai', 'sementara', 'dihimpun_pada'])
            + ['metrik' => $metrik, 'program' => $bolehProgram ? ($s['program'] ?? []) : [], 'jumlah_program' => $bolehProgram ? ($s['jumlah_program'] ?? 0) : null,
                'kolom_bulanan' => $kolom, 'bulan' => $bulan, 'akses_dibatasi' => ! $bolehProgram || count($metrik) !== count($s['metrik'] ?? [])];
    }

    public function simpan(Request $r, ?KinerjaSemesterHumas $laporan = null): KinerjaSemesterHumas
    {
        $this->akses($r->user(), true);
        $data = $r->validate($this->aturan() + ['token_pembuatan' => [$laporan ? 'prohibited' : 'required', 'uuid'],
            'versi' => [$laporan ? 'required' : 'prohibited', 'integer', 'min:0'], 'alasan' => [$laporan ? 'required' : 'nullable', 'string', 'min:5', 'max:1000']]);
        $periode = $laporan ? null : $this->periode($r);
        try {
            return $this->transaksi(function () use ($r, $data, $laporan, $periode) {
                if (! $laporan) {
                    $lama = KinerjaSemesterHumas::where('token_pembuatan', $data['token_pembuatan'])->first();
                    if ($lama) {
                        abort_unless($lama->dibuat_oleh_pengguna_id === $r->user()->id, 403);

                        return $lama;
                    }
                    if (KinerjaSemesterHumas::where('tahun_pelajaran_id', $periode['filter']['tahun_pelajaran_id'])->where('semester', $periode['filter']['periode'])->exists()) {
                        throw ValidationException::withMessages(['semester' => 'Laporan periode ini sudah ada. Buka laporan yang tersedia untuk koreksi.']);
                    }
                }
                $l = $laporan ? $this->kunci($laporan, (int) $data['versi'], $r->user()) : new KinerjaSemesterHumas;
                if ($laporan && $l->status !== 'draf') {
                    $this->gagalDraf();
                }
                $l->fill(Arr::only($data, KinerjaSemesterHumas::KOLOM));
                if (! $laporan) {
                    $l->forceFill(['token_pembuatan' => $data['token_pembuatan'], 'tahun_pelajaran_id' => $periode['filter']['tahun_pelajaran_id'],
                        'semester' => $periode['filter']['periode'], 'status' => 'draf', 'snapshot' => $this->himpun($r->user(), $periode), 'dibuat_oleh_pengguna_id' => $r->user()->id]);
                }
                if ($l->tanggal_laporan->lt(CarbonImmutable::parse($l->snapshot['data_sampai']))) {
                    throw ValidationException::withMessages(['tanggal_laporan' => 'Tanggal laporan tidak boleh mendahului batas data yang dihimpun.']);
                }
                $l->forceFill(['versi' => $laporan ? $l->versi + 1 : 0, 'diubah_oleh_pengguna_id' => $r->user()->id])->save();
                $this->catat($l, $laporan ? 'draf_diperbarui' : 'draf_dibuat', $data['alasan'] ?? 'Laporan semester dihimpun sebagai draf.', $r->user());

                return $l;
            });
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23505', '23000'], true)) {
                throw ValidationException::withMessages(['semester' => 'Laporan periode atau token ini sudah digunakan. Muat ulang daftar laporan.']);
            }
            throw $e;
        }
    }

    public function tindakan(Request $r, KinerjaSemesterHumas $laporan, string $aksi): KinerjaSemesterHumas
    {
        $this->akses($r->user(), true);
        abort_unless(in_array($aksi, ['perbarui-data', 'finalisasi', 'buka-revisi'], true), 404);
        $data = $r->validate(['versi' => ['required', 'integer', 'min:0'], 'alasan' => ['required', 'string', 'min:5', 'max:1000'], 'konfirmasi_sementara' => ['nullable', 'boolean']]);

        return $this->transaksi(function () use ($r, $laporan, $aksi, $data) {
            $l = $this->kunci($laporan, (int) $data['versi'], $r->user());
            if ($aksi === 'buka-revisi') {
                if ($l->status !== 'final') {
                    throw ValidationException::withMessages(['status' => 'Hanya laporan final yang dapat dibuka untuk revisi.']);
                }
                $l->forceFill(['status' => 'draf', 'difinalisasi_pada' => null]);
            } else {
                if ($l->status !== 'draf') {
                    $this->gagalDraf();
                }
                if ($aksi === 'perbarui-data') {
                    $p = $this->periode(new Request(['tahun_pelajaran_id' => $l->tahun_pelajaran_id, 'semester' => $l->semester]));
                    $l->snapshot = $this->himpun($r->user(), $p);
                } else {
                    if ($l->tanggal_laporan->lt(CarbonImmutable::parse($l->snapshot['data_sampai']))) {
                        throw ValidationException::withMessages(['tanggal_laporan' => 'Perbarui tanggal laporan agar tidak mendahului batas data.']);
                    }
                    foreach (['ringkasan', 'capaian', 'kendala', 'tindak_lanjut', 'rekomendasi', 'nama_pimpinan'] as $kolom) {
                        if (blank($l->$kolom)) {
                            throw ValidationException::withMessages([$kolom => 'Lengkapi evaluasi dan penandatangan sebelum finalisasi.']);
                        }
                    }
                    if (($l->snapshot['sementara'] ?? false) && ! ($data['konfirmasi_sementara'] ?? false)) {
                        throw ValidationException::withMessages(['konfirmasi_sementara' => 'Data belum mencakup akhir semester. Konfirmasikan laporan sementara atau perbarui data setelah semester berakhir.']);
                    }
                    $l->forceFill(['status' => 'final', 'difinalisasi_pada' => now()]);
                }
            }
            $l->forceFill(['versi' => $l->versi + 1, 'diubah_oleh_pengguna_id' => $r->user()->id])->save();
            $this->catat($l, $aksi, $data['alasan'], $r->user());

            return $l;
        });
    }

    private function aturan(): array
    {
        $aturan = ['judul' => ['required', 'string', 'max:180'], 'tanggal_laporan' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'nama_penyusun' => ['required', 'string', 'max:180'], 'nama_pimpinan' => ['nullable', 'string', 'max:180'],
            'nip_penyusun' => ['nullable', 'string', 'max:40'], 'nip_pimpinan' => ['nullable', 'string', 'max:40']];
        foreach (['ringkasan', 'capaian', 'kendala', 'tindak_lanjut', 'rekomendasi'] as $k) {
            $aturan[$k] = ['nullable', 'string', 'min:5', 'max:10000'];
        }

        return $aturan;
    }

    private function kunci(KinerjaSemesterHumas $l, int $versi, Pengguna $u): KinerjaSemesterHumas
    {
        $l = KinerjaSemesterHumas::lockForUpdate()->findOrFail($l->id);
        if ($l->versi !== $versi) {
            throw ValidationException::withMessages(['versi' => 'Laporan telah berubah. Muat ulang sebelum melanjutkan.']);
        }
        abort_if($this->tampil($l->snapshot, $u)['akses_dibatasi'], 403);

        return $l;
    }

    private function bolehSumber(Pengguna $u, string $kode): bool
    {
        return isset(self::IZIN_SUMBER[$kode]) && $u->memilikiIzin(self::IZIN_SUMBER[$kode]);
    }

    private function gagalDraf(): never
    {
        throw ValidationException::withMessages(['status' => 'Laporan final dikunci. Buka revisi dengan alasan sebelum mengubahnya.']);
    }

    private function transaksi(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            return $callback();
        }, 3);
    }

    private function catat(KinerjaSemesterHumas $l, string $aksi, string $alasan, Pengguna $u): void
    {
        $data = $l->only(KinerjaSemesterHumas::KOLOM);
        $data['tanggal_laporan'] = $l->tanggal_laporan->toDateString();
        DB::table('riwayat_kinerja_semester_humas')->insert(['induk_id' => $l->id, 'aksi' => $aksi, 'versi' => $l->versi, 'alasan' => $alasan,
            'data' => json_encode($data + ['snapshot' => $l->snapshot, 'status' => $l->status], JSON_THROW_ON_ERROR), 'pengguna_id' => $u->id, 'created_at' => now()]);
    }
}
