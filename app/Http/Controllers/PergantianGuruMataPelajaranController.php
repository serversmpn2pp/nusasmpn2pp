<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\Pegawai;
use App\Models\RiwayatPergantianGuruMapel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PergantianGuruMataPelajaranController extends Controller
{
    public function edit(GuruMataPelajaran $guruMataPelajaran)
    {
        $this->pastikanDapatDiganti($guruMataPelajaran);
        $guruMataPelajaran->load(['tahunPelajaran', 'kelas', 'mataPelajaran', 'pegawai']);

        $penugasanTerkait = $this->queryPenugasanTerkait($guruMataPelajaran)
            ->with('kelas')
            ->get()
            ->sortBy([
                ['kelas.tingkat', 'asc'],
                ['kelas.nama', 'asc'],
            ])
            ->values();

        $pegawaiPengganti = Pegawai::query()
            ->where('aktif', true)
            ->whereKeyNot($guruMataPelajaran->pegawai_id)
            ->whereRaw('LOWER(jenis_pegawai) LIKE ?', ['%guru%'])
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip', 'jenis_pegawai']);

        return view('guru-mata-pelajaran.ganti-guru', compact(
            'guruMataPelajaran',
            'penugasanTerkait',
            'pegawaiPengganti',
        ));
    }

    public function update(Request $request, GuruMataPelajaran $guruMataPelajaran)
    {
        $this->pastikanDapatDiganti($guruMataPelajaran);

        $data = $request->validate([
            'pegawai_baru_id' => [
                'required',
                'integer',
                Rule::exists('pegawai', 'id')->where(fn ($query) => $query->where('aktif', true)),
                Rule::notIn([(int) $guruMataPelajaran->pegawai_id]),
            ],
            'penugasan_ids' => ['required', 'array', 'min:1'],
            'penugasan_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('guru_mata_pelajaran', 'id'),
            ],
            'tanggal_efektif' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'alasan' => ['required', 'string', 'max:1000'],
        ], [
            'pegawai_baru_id.not_in' => 'Guru pengganti harus berbeda dari guru yang sedang mengajar.',
            'tanggal_efektif.before_or_equal' => 'Tanggal efektif belum boleh melewati hari ini.',
        ]);

        $jumlahDiganti = DB::transaction(function () use ($data, $guruMataPelajaran, $request) {
            $acuan = GuruMataPelajaran::query()
                ->with('tahunPelajaran')
                ->lockForUpdate()
                ->findOrFail($guruMataPelajaran->id);
            $this->pastikanDapatDiganti($acuan);
            $this->pastikanTanggalDalamTahunPelajaran($data['tanggal_efektif'], $acuan);

            $penugasanIds = collect($data['penugasan_ids'])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            $penugasan = GuruMataPelajaran::query()
                ->whereIn('id', $penugasanIds)
                ->lockForUpdate()
                ->get();

            $this->pastikanSemuaPenugasanSatuKelompok($penugasan, $penugasanIds, $acuan);
            $this->pastikanTidakAdaPenugasanGuruBaru(
                $penugasan,
                (int) $data['pegawai_baru_id'],
                $penugasanIds,
                $acuan,
            );

            foreach ($penugasan as $item) {
                RiwayatPergantianGuruMapel::create([
                    'guru_mata_pelajaran_id' => $item->id,
                    'tahun_pelajaran_id' => $item->tahun_pelajaran_id,
                    'kelas_id' => $item->kelas_id,
                    'mata_pelajaran_id' => $item->mata_pelajaran_id,
                    'pegawai_lama_id' => $item->pegawai_id,
                    'pegawai_baru_id' => $data['pegawai_baru_id'],
                    'tanggal_efektif' => $data['tanggal_efektif'],
                    'alasan' => trim($data['alasan']),
                    'diganti_oleh_pengguna_id' => $request->user()?->id,
                ]);

                $item->update(['pegawai_id' => $data['pegawai_baru_id']]);
            }

            return $penugasan->count();
        });

        return redirect()
            ->route('guru-mata-pelajaran.index', [
                'tahun_pelajaran_id' => $guruMataPelajaran->tahun_pelajaran_id,
                'status' => 'aktif',
            ])
            ->with('berhasil', "Guru pengampu berhasil diganti untuk {$jumlahDiganti} kelas. Jadwal dan nilai tetap tersambung.");
    }

    private function queryPenugasanTerkait(GuruMataPelajaran $acuan)
    {
        return GuruMataPelajaran::query()
            ->where('tahun_pelajaran_id', $acuan->tahun_pelajaran_id)
            ->where('mata_pelajaran_id', $acuan->mata_pelajaran_id)
            ->where('pegawai_id', $acuan->pegawai_id)
            ->where('jenis_penugasan', 'pengampu')
            ->where('aktif', true);
    }

    private function pastikanDapatDiganti(GuruMataPelajaran $penugasan): void
    {
        abort_unless(
            $penugasan->aktif && $penugasan->jenis_penugasan === 'pengampu',
            404,
        );
    }

    private function pastikanTanggalDalamTahunPelajaran(
        string $tanggalEfektif,
        GuruMataPelajaran $acuan,
    ): void {
        $tanggal = CarbonImmutable::createFromFormat('Y-m-d', $tanggalEfektif)->startOfDay();
        $tanggalMulai = CarbonImmutable::parse($acuan->tahunPelajaran->tanggal_mulai)->startOfDay();
        $tanggalSelesai = CarbonImmutable::parse($acuan->tahunPelajaran->tanggal_selesai)->startOfDay();

        if ($tanggal->lt($tanggalMulai) || $tanggal->gt($tanggalSelesai)) {
            throw ValidationException::withMessages([
                'tanggal_efektif' => 'Tanggal efektif harus berada dalam rentang tahun pelajaran.',
            ]);
        }
    }

    private function pastikanSemuaPenugasanSatuKelompok(
        Collection $penugasan,
        Collection $penugasanIds,
        GuruMataPelajaran $acuan,
    ): void {
        $tidakCocok = $penugasan->count() !== $penugasanIds->count()
            || $penugasan->contains(fn (GuruMataPelajaran $item) => (
                ! $item->aktif
                || $item->jenis_penugasan !== 'pengampu'
                || (int) $item->tahun_pelajaran_id !== (int) $acuan->tahun_pelajaran_id
                || (int) $item->mata_pelajaran_id !== (int) $acuan->mata_pelajaran_id
                || (int) $item->pegawai_id !== (int) $acuan->pegawai_id
            ));

        if ($tidakCocok) {
            throw ValidationException::withMessages([
                'penugasan_ids' => 'Semua kelas harus berasal dari guru, mata pelajaran, dan tahun pelajaran yang sama.',
            ]);
        }
    }

    private function pastikanTidakAdaPenugasanGuruBaru(
        Collection $penugasan,
        int $pegawaiBaruId,
        Collection $penugasanIds,
        GuruMataPelajaran $acuan,
    ): void {
        $sudahAda = GuruMataPelajaran::query()
            ->where('tahun_pelajaran_id', $acuan->tahun_pelajaran_id)
            ->where('mata_pelajaran_id', $acuan->mata_pelajaran_id)
            ->where('pegawai_id', $pegawaiBaruId)
            ->whereIn('kelas_id', $penugasan->pluck('kelas_id'))
            ->whereNotIn('id', $penugasanIds)
            ->exists();

        if ($sudahAda) {
            throw ValidationException::withMessages([
                'pegawai_baru_id' => 'Guru pengganti sudah memiliki penugasan pada salah satu kelas yang dipilih.',
            ]);
        }
    }
}
