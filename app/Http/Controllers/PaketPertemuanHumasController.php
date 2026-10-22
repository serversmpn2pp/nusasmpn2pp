<?php

namespace App\Http\Controllers;

use App\Models\AgendaHumas;
use App\Services\Humas\PaketPertemuanHumasService;
use Illuminate\Http\Request;

class PaketPertemuanHumasController extends Controller
{
    public function show(Request $r, AgendaHumas $agendaHumas, PaketPertemuanHumasService $service)
    {
        $data = $service->data($agendaHumas, $r->user());

        return $r->routeIs('api.v1.*') ? response()->json(['data' => $data])->header('Cache-Control', 'private, no-store')
            : response()->view('agenda-humas.paket', ['paket' => $data, 'agendaHumas' => $agendaHumas], 200, ['Cache-Control' => 'private, no-store']);
    }
}
