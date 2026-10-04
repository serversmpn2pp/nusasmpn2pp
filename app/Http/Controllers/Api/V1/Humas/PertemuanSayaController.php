<?php

namespace App\Http\Controllers\Api\V1\Humas;

use App\Models\AgendaHumas;
use App\Models\PesertaPertemuanHumas;
use App\Services\Humas\PresensiPertemuanHumasService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PertemuanSayaController extends HumasController
{
    public function __construct(private readonly PresensiPertemuanHumasService $presensi, private readonly HumasMobileService $mobile) {}

    private function milik(Request $r)
    {
        $wali = $this->presensi->wali($r);
        $anakIds = $wali->siswa()->pluck('siswa.id')->all();
        // Match invitation snapshots with the same rule used by QR confirmation.
        $ids = PesertaPertemuanHumas::where('orang_tua_wali_id', $wali->id)
            ->get(['id', 'anak_undangan'])->filter(fn ($p) => array_intersect($anakIds, array_column($p->anak_undangan ?? [], 'siswa_id')))->pluck('id');

        return PesertaPertemuanHumas::where('orang_tua_wali_id', $wali->id)->whereIn('id', $ids)->with('agenda');
    }

    public function index(Request $r)
    {
        $filter = $r->validate(['tab' => ['nullable', Rule::in(['mendatang', 'riwayat'])]]) + $this->halaman($r);
        $tab = $filter['tab'] ?? 'mendatang';
        $q = $this->milik($r)->whereHas('agenda', fn ($q) => $tab === 'mendatang'
            ? $q->where('status', 'terjadwal')->where('waktu_selesai', '>=', now())
            : $q->where(fn ($q) => $q->where('status', '!=', 'terjadwal')->orWhere('waktu_selesai', '<', now())));

        return $this->json($this->mobile->paginasi($q->orderByDesc('id')->paginate($filter['per_halaman'] ?? 15, ['*'], 'halaman', $filter['halaman'] ?? 1), fn ($p) => $this->mobile->pertemuanSaya($p)) + ['tab' => $tab]);
    }

    public function show(Request $r, AgendaHumas $agendaHumas)
    {
        return $this->json($this->mobile->pertemuanSaya($this->milik($r)->where('agenda_humas_id', $agendaHumas->id)->firstOrFail()));
    }

    public function scan(Request $r, string $token)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/D', $token), 404);
        $peserta = $this->milik($r)->whereHas('agenda', fn ($q) => $q->where('token_presensi', $token))->firstOrFail();

        return $this->json($this->mobile->pertemuanSaya($peserta));
    }

    public function hadir(Request $r, string $token)
    {
        $this->scan($r, $token);
        $this->presensi->konfirmasi($r, $token);

        return $this->scan($r, $token);
    }
}
