<?php

namespace App\Services\Supervisi;

use App\Models\Pengguna;
use App\Models\SesiSupervisi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LaporanSupervisiService
{
    public function __construct(private AksesSupervisi $akses, private RekapSupervisiService $rekap, private PengisianSupervisiService $isian, private PascaobservasiSupervisiService $pasca) {}

    public function satu(Pengguna $u, SesiSupervisi $s): array
    {
        return $this->kolektif($u, ['sesi' => [$s->id]])[0];
    }

    public function kolektif(Pengguna $u, array $input): array
    {
        $this->akses->staff($u);
        $d = Validator::make($input, ['sesi' => ['required', 'array', 'list', 'min:1', 'max:10'], 'sesi.*' => ['required', 'integer', 'min:1', 'distinct']])->validate();

        // Coordinate with all supervision writers, so one report never mixes correction versions.
        return DB::transaction(function () use ($u, $d) {
            $this->akses->kunci();
            $sessions = $this->akses->sesi(SesiSupervisi::query(), $u)->whereKey($d['sesi'])->with([
                'program.tahunPelajaran', 'guru:id,nama_lengkap,nip,nuptk', 'supervisor:id,nama_lengkap,nip,nuptk',
                'instrumen', 'praobservasi.lampiran', 'penilaian', 'pascaobservasi', 'tindakLanjut.lampiran',
            ])->get()->keyBy('id');
            abort_unless($sessions->count() === count($d['sesi']), 404);
            $laporan = [];
            $ukuran = 0;
            foreach ($d['sesi'] as $id) {
                $s = $sessions->get($id);
                $ringkasan = $this->rekap->baris($s);
                if (! $ringkasan['bisa_cetak']) {
                    throw ValidationException::withMessages(['sesi' => 'Laporan hanya tersedia untuk hasil final yang lengkap dan sesuai praobservasi. Periksa sesi '.$id.'.']);
                }
                $p = $this->pasca->data($u, $s);
                $refleksiTerlihat = $p['hasil_terbaru'] && in_array($p['status'], ['terkirim', 'diterima', 'selesai'], true);
                $rencana = [];
                foreach ($s->tindakLanjut as $t) {
                    if (! $t->diterbitkan_pada || $t->penilaian_versi !== $s->penilaian->versi) {
                        continue;
                    }
                    $row = $this->pasca->dataTugas($u, $s, $t);
                    if (! in_array($row['status'], ['diajukan', 'selesai'], true)) {
                        $row['laporan_terlihat'] = false;
                        $row['tanggal_pelaksanaan'] = $row['laporan_pelaksanaan'] = $row['hasil_pelaksanaan'] = null;
                        $row['lampiran'] = [];
                    }
                    unset($row['hak']);
                    $row['lampiran'] = array_map(fn ($f) => collect($f)->except('unduh')->all(), $row['lampiran']);
                    $rencana[] = $row;
                }
                $pra = $this->isian->dataPra($u, $s);
                $pra['lampiran'] = array_map(fn ($f) => collect($f)->except('unduh')->all(), $pra['lampiran']);
                $nilai = $this->isian->dataPenilaian($u, $s);
                $laporan[] = $data = [
                    'sesi' => $ringkasan, 'tahun_pelajaran' => $s->program->tahunPelajaran->nama, 'semester' => $s->program->semester,
                    'tempat' => $s->tempat, 'waktu_selesai' => $s->waktu_selesai->toIso8601String(), 'tanggal_observasi' => $nilai['tanggal_observasi'],
                    'guru_nip' => $s->guru->nip, 'guru_nuptk' => $s->guru->nuptk, 'supervisor_nip' => $s->supervisor->nip,
                    'instrumen' => ['nama' => $s->instrumen->nama, 'nomor' => $s->instrumen->nomor, 'skala' => $s->instrumen->skala, 'kelompok' => $s->instrumen->kelompok],
                    'praobservasi' => $pra, 'penilaian' => $nilai,
                    'refleksi' => ['terlihat' => $refleksiTerlihat, 'status' => ! $p['hasil_terbaru'] ? 'penyesuaian_hasil' : $p['status'],
                        'pertanyaan' => $refleksiTerlihat ? $p['pertanyaan'] : [], 'jawaban' => $refleksiTerlihat ? $p['jawaban'] : [],
                        'catatan_supervisor' => $refleksiTerlihat ? $p['catatan_supervisor'] : null,
                        'catatan_penutup' => $refleksiTerlihat && $p['status'] === 'selesai' ? $p['catatan_penutup'] : null],
                    'tindak_lanjut' => $rencana, 'dicetak_pada' => now()->toIso8601String(), 'dicetak_oleh' => $u->nama,
                ];
                $ukuran += strlen(json_encode($data, JSON_THROW_ON_ERROR));
                if ($ukuran > 2_000_000) {
                    throw ValidationException::withMessages(['sesi' => 'Isi laporan terlalu besar untuk dicetak sekaligus. Kurangi jumlah sesi yang dipilih.']);
                }
            }

            return $laporan;
        });
    }
}
