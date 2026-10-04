<?php

namespace App\Http\Controllers\Api\V1\Humas;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Services\Humas\KelolaAgendaHumasService;
use App\Services\Humas\KelolaDokumenHumasService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;

class AgendaDokumenController extends HumasController
{
    public function index(Request $r, AgendaHumas $agendaHumas, HumasMobileService $mobile, KelolaDokumenHumasService $dokumen)
    {
        $this->staff($r, ['agenda_humas.lihat', 'agenda_humas.kelola']);
        $this->staff($r, ['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        $p = $this->halaman($r);

        return $this->json($mobile->paginasi($agendaHumas->dokumen()->withMax('riwayat', 'versi')->orderBy('judul')->orderBy('dokumen_humas.id')
            ->paginate($p['per_halaman'] ?? 20, ['dokumen_humas.*'], 'halaman', $p['halaman'] ?? 1), fn ($d) => $mobile->dokumen($d, $dokumen->sidik($d))));
    }

    public function store(Request $r, AgendaHumas $agendaHumas, KelolaAgendaHumasService $kelola)
    {
        $this->staff($r, 'agenda_humas.kelola');
        $this->staff($r, ['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        $kelola->hubungkanDokumen($r, $agendaHumas);

        return $this->json(['agenda_id' => $agendaHumas->id], 'Dokumen dihubungkan ke agenda.');
    }

    public function destroy(Request $r, AgendaHumas $agendaHumas, DokumenHumas $dokumenHumas, KelolaAgendaHumasService $kelola)
    {
        $this->staff($r, 'agenda_humas.kelola');
        $this->staff($r, ['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        $kelola->lepasDokumen($agendaHumas, $dokumenHumas);

        return $this->json(['agenda_id' => $agendaHumas->id], 'Tautan dilepas; dokumen tetap tersimpan.');
    }
}
