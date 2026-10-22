<?php

namespace App\Services\Mobile;

use App\Models\ButirAkreditasiHumas;
use App\Models\KerjaSamaHumas;
use App\Models\LaporanPelaksanaanHumas;
use App\Models\MitraHumas;
use App\Models\PeriodeKomiteHumas;
use App\Models\PortofolioAkreditasiHumas;
use App\Models\ProgramKerjaHumas;
use App\Models\ProgramKomiteHumas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class HumasTahapDuaService
{
    public const MODUL = [
        'program' => [ProgramKerjaHumas::class, 'program_kerja_humas'],
        'komite' => [PeriodeKomiteHumas::class, 'komite_humas'],
        'mitra' => [MitraHumas::class, 'kemitraan_humas'],
        'akreditasi' => [PortofolioAkreditasiHumas::class, 'akreditasi_humas'],
        'laporan' => [LaporanPelaksanaanHumas::class, 'program_kerja_humas'],
        'program-komite' => [ProgramKomiteHumas::class, 'komite_humas'],
        'mou' => [KerjaSamaHumas::class, 'kemitraan_humas'],
        'butir' => [ButirAkreditasiHumas::class, 'akreditasi_humas'],
    ];

    public static function tersimpan(Model $m)
    {
        $current = $m->fresh();

        return response()->json(['data' => ['id' => $current->id, 'versi' => $current instanceof ButirAkreditasiHumas ? $current->portofolio->versi : $current->versi, 'status' => $current->status]], $m->wasRecentlyCreated ? 201 : 200)
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function dokumen(Request $r): bool
    {
        return $r->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
    }

    public function data(Request $r, string $kind, Model $m, bool $detail = false): array
    {
        $columns = match ($kind) {
            'program' => ProgramKerjaHumas::KOLOM,
            'komite' => PeriodeKomiteHumas::KOLOM,
            'akreditasi' => PortofolioAkreditasiHumas::KOLOM,
            'laporan' => LaporanPelaksanaanHumas::KOLOM,
            'program-komite' => ProgramKomiteHumas::KOLOM,
            'butir' => ButirAkreditasiHumas::KOLOM,
            'mitra' => ['nama', 'jenis', 'alamat', 'nama_kontak', 'jabatan_kontak', 'nomor_kontak', 'email', 'website', 'catatan', 'status'],
            'mou' => ['judul', 'nomor', 'bidang', 'ruang_lingkup', 'penanggung_jawab', 'tanggal_mulai', 'tanggal_selesai', 'ingatkan_hari_sebelum', 'status', 'alasan_diakhiri'],
        };
        $data = $m->only(['id', 'versi', 'status', ...$columns]);
        foreach (['tanggal_mulai', 'tanggal_selesai', 'tanggal_sk'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $m->$key?->format('Y-m-d');
            }
        }
        $model = self::MODUL[$kind][0];
        $data['status_label'] = $model::STATUS[$m->status] ?? $m->status;
        $manage = $r->user()->memilikiIzin(self::MODUL[$kind][1].'.kelola');
        $open = match ($kind) {
            'akreditasi' => $m->status === 'draf',
            'butir' => $m->portofolio->status === 'draf',
            'laporan' => $m->program->terbuka() && $m->status === 'draf',
            'program-komite' => $m->periode->status !== 'arsip',
            default => true,
        };
        $doc = $this->dokumen($r);
        $data['hak_akses'] = ['dapat_kelola' => $manage && $open, 'dapat_lihat_dokumen' => $doc,
            'dapat_lihat_agenda' => $r->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']),
            'dapat_unggah' => $r->user()->memilikiIzin('dokumen_humas.kelola'), 'dapat_ubah_status' => $manage,
            'dapat_ekspor' => $kind === 'akreditasi' && $doc && $m->status === 'siap' && $r->user()->memilikiIzin('akreditasi_humas.ekspor')];
        if ($kind === 'program') {
            $data += ['tahun_pelajaran' => $m->tahunPelajaran?->nama, 'semester_label' => ProgramKerjaHumas::SEMESTER[$m->semester],
                'bidang_label' => ProgramKerjaHumas::BIDANG[$m->bidang], 'realisasi' => $m->laporan_final_count ?? $m->laporanFinal()->count(), 'terlambat' => $m->terlambat(), 'terbuka' => $m->terbuka()];
        } elseif ($kind === 'komite' && $detail) {
            $data['pengurus'] = $m->pengurus()->get()->map(fn ($p) => $p->only($manage ? ['id', 'nama', 'jabatan', 'nomor_telepon', 'aktif'] : ['id', 'nama', 'jabatan', 'aktif']))->all();
            $data['berkas'] = $doc && $m->berkas ? ['id' => $m->berkas->id, 'nama' => $m->berkas->nama_file_asli, 'versi' => $m->berkas->versi] : null;
        } elseif ($kind === 'akreditasi') {
            $data['tahun_pelajaran'] = $m->tahunPelajaran?->nama;
            $data['jumlah_butir'] = $m->butir_count ?? $m->butir()->count();
            $data['terpenuhi'] = $m->terpenuhi_count ?? $m->butir()->where('status', 'terpenuhi')->count();
        } elseif ($kind === 'mou') {
            $data['mitra_id'] = $m->mitra_humas_id;
            $data['mitra'] = $m->mitra->nama;
            $data['masa_berlaku'] = KerjaSamaHumas::STATUS_BERLAKU[$m->statusBerlaku()];
            if ($doc) {
                $data['dokumen_humas_id'] = $m->dokumen_humas_id;
                $data['dokumen'] = $m->dokumen ? ['id' => $m->dokumen->id, 'nama' => $m->dokumen->nama_file_asli] : null;
            }
        } elseif ($kind === 'laporan') {
            if ($m->status === 'final') {
                $data = array_replace($data, Arr::only($m->snapshot_final ?? [], $columns));
            }
            if (! $r->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola'])) {
                unset($data['agenda_humas_id']);
            }
            $data['program_id'] = $m->program_kerja_humas_id;
            $data['terbuka'] = $m->program->terbuka();
        } elseif ($kind === 'butir') {
            $data['portofolio_id'] = $m->portofolio_akreditasi_humas_id;
            $data['versi'] = $m->portofolio->versi;
            $data['catatan_pemeriksaan'] = $m->catatan_pemeriksaan;
        }
        if ($kind === 'program-komite') {
            $data['penanggung_jawab'] = $m->penanggungJawab?->nama;
        }
        if ($detail && $doc && in_array($kind, ['laporan', 'butir'])) {
            $data['bukti'] = $m->bukti()->with('berkas')->get()->map(fn ($b) => ['id' => $b->id, 'judul' => $b->judul, 'catatan' => $b->catatan,
                'id_dokumen' => $b->berkas?->dokumen_humas_id, 'id_versi' => $b->berkas?->id, 'versi_berkas' => $b->berkas?->versi, 'nama' => $b->berkas?->nama_file_asli])->all();
        }

        return $data;
    }
}
