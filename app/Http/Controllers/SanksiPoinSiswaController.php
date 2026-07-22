<?php

namespace App\Http\Controllers;

use App\Models\SanksiPoinSiswa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SanksiPoinSiswaController extends Controller
{
    public function update(Request $request, SanksiPoinSiswa $sanksiPoinSiswa)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['menunggu', 'diproses', 'selesai', 'dibatalkan'])],
            'catatan' => ['nullable', 'string'],
        ]);

        $sanksiPoinSiswa->update([
            'status' => $data['status'],
            'petugas_pegawai_id' => $request->user()?->pegawai_id,
            'dilaksanakan_pada' => $data['status'] === 'selesai' ? now() : $sanksiPoinSiswa->dilaksanakan_pada,
            'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
        ]);

        return back()->with('berhasil', 'Status tindak lanjut sanksi diperbarui.');
    }
}
