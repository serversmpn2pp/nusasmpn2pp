<?php

namespace App\Http\Controllers;

use App\Models\SesiUjianCbt;
use App\Models\UjianCbt;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SesiUjianCbtController extends Controller
{
    public function store(Request $request, UjianCbt $ujianCbt)
    {
        $data = $this->rapikanData($ujianCbt, $request->validate($this->aturanValidasi($ujianCbt)));

        $ujianCbt->sesiUjianCbt()->create($data);

        return redirect()
            ->route('ujian-cbt.peserta.index', $ujianCbt)
            ->with('berhasil', 'Sesi ujian CBT berhasil ditambahkan.');
    }

    public function update(Request $request, UjianCbt $ujianCbt, SesiUjianCbt $sesiUjianCbt)
    {
        $this->pastikanSesiMilikPaket($ujianCbt, $sesiUjianCbt);

        $data = $this->rapikanData($ujianCbt, $request->validate($this->aturanValidasi($ujianCbt, $sesiUjianCbt)));

        $sesiUjianCbt->update($data);

        return redirect()
            ->route('ujian-cbt.peserta.index', $ujianCbt)
            ->with('berhasil', 'Sesi ujian CBT berhasil diperbarui.');
    }

    public function destroy(UjianCbt $ujianCbt, SesiUjianCbt $sesiUjianCbt)
    {
        $this->pastikanSesiMilikPaket($ujianCbt, $sesiUjianCbt);

        if ($sesiUjianCbt->pesertaUjianCbt()->exists()) {
            $sesiUjianCbt->update(['status' => 'nonaktif']);

            return redirect()
                ->route('ujian-cbt.peserta.index', $ujianCbt)
                ->with('berhasil', 'Sesi memiliki peserta, sehingga ditandai nonaktif.');
        }

        $sesiUjianCbt->delete();

        return redirect()
            ->route('ujian-cbt.peserta.index', $ujianCbt)
            ->with('berhasil', 'Sesi ujian CBT berhasil dihapus.');
    }

    private function aturanValidasi(UjianCbt $ujianCbt, ?SesiUjianCbt $sesiUjianCbt = null): array
    {
        return [
            'kode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('sesi_ujian_cbt', 'kode')
                    ->where('ujian_cbt_id', $ujianCbt->id)
                    ->ignore($sesiUjianCbt),
            ],
            'nama' => ['required', 'string', 'max:120'],
            'waktu_mulai' => ['nullable', 'date'],
            'waktu_selesai' => ['nullable', 'date', 'after_or_equal:waktu_mulai'],
            'kapasitas' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'status' => ['required', Rule::in(array_keys(SesiUjianCbt::DAFTAR_STATUS))],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function rapikanData(UjianCbt $ujianCbt, array $data): array
    {
        $data['kode'] = filled($data['kode'] ?? null)
            ? mb_strtoupper(trim($data['kode']))
            : $this->buatKodeSaran($ujianCbt);
        $data['nama'] = trim($data['nama']);
        $data['keterangan'] = filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null;
        $data['kapasitas'] = filled($data['kapasitas'] ?? null) ? (int) $data['kapasitas'] : null;

        return $data;
    }

    private function buatKodeSaran(UjianCbt $ujianCbt): string
    {
        $urutan = $ujianCbt->sesiUjianCbt()->count() + 1;

        do {
            $kode = 'S-' . str_pad((string) $urutan, 2, '0', STR_PAD_LEFT);
            $urutan++;
        } while ($ujianCbt->sesiUjianCbt()->where('kode', $kode)->exists());

        return $kode;
    }

    private function pastikanSesiMilikPaket(UjianCbt $ujianCbt, SesiUjianCbt $sesiUjianCbt): void
    {
        abort_if((int) $sesiUjianCbt->ujian_cbt_id !== (int) $ujianCbt->id, 404);
    }
}
