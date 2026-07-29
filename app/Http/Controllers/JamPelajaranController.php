<?php

namespace App\Http\Controllers;

use App\Models\JamPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JamPelajaranController extends Controller
{
    public function index(Request $request)
    {
        $hari = $request->input('hari', 'semua');
        $status = $request->input('status', 'semua');

        if (! array_key_exists($hari, JamPelajaran::DAFTAR_HARI) && $hari !== 'semua') {
            $hari = 'semua';
        }

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        $jamPelajaran = JamPelajaran::query()
            ->when($hari !== 'semua', fn ($query) => $query->where('hari', $hari))
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->orderBy('nomor_jam')
            ->paginate(15)
            ->withQueryString();

        return view('jam-pelajaran.index', [
            'jamPelajaran' => $jamPelajaran,
            'hari' => $hari,
            'status' => $status,
            'daftarHari' => JamPelajaran::DAFTAR_HARI,
            'jumlahJamPelajaran' => JamPelajaran::count(),
            'jumlahAktif' => JamPelajaran::where('aktif', true)->count(),
            'jumlahNonaktif' => JamPelajaran::where('aktif', false)->count(),
        ]);
    }

    public function create()
    {
        return view('jam-pelajaran.create', [
            'daftarHari' => JamPelajaran::DAFTAR_HARI,
            'daftarJenis' => JamPelajaran::DAFTAR_JENIS,
            'nomorUrutMaksimal' => min(
                19,
                max(1, (int) JamPelajaran::query()->max('nomor_jam')),
            ),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturanValidasiMassal());
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanJamValid($data);

        $daftarHari = collect($data['hari'])->unique()->values();
        $posisiSisip = $data['posisi_sisip']
            ?? 'sebelum:'.$data['nomor_jam'];
        $dataJam = collect($data)
            ->except(['hari', 'nomor_jam', 'posisi_sisip'])
            ->all();
        [$jumlahBaru, $jumlahDigeser] = DB::transaction(
            fn () => $this->sisipkanKeHari($daftarHari, $dataJam, $posisiSisip),
        );

        return redirect()
            ->route('jam-pelajaran.index')
            ->with(
                'berhasil',
                $this->pesanPenyisipan($daftarHari->count(), $jumlahBaru, $jumlahDigeser),
            );
    }

    public function show(JamPelajaran $jamPelajaran)
    {
        $jamPelajaran->loadCount('jadwalPelajaran');

        return view('jam-pelajaran.show', compact('jamPelajaran'));
    }

    public function edit(JamPelajaran $jamPelajaran)
    {
        return view('jam-pelajaran.edit', [
            'jamPelajaran' => $jamPelajaran,
            'daftarHari' => JamPelajaran::DAFTAR_HARI,
            'daftarJenis' => JamPelajaran::DAFTAR_JENIS,
            'nomorUrutMaksimal' => max(
                1,
                (int) JamPelajaran::query()
                    ->where('hari', $jamPelajaran->hari)
                    ->max('nomor_jam'),
            ),
        ]);
    }

    public function update(Request $request, JamPelajaran $jamPelajaran)
    {
        $data = $request->validate($this->aturanValidasi($jamPelajaran));
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanJamValid($data);

        $hariTujuan = collect($data['hari_tujuan'] ?? [])
            ->push($jamPelajaran->hari)
            ->unique()
            ->values();
        $dataJam = collect($data)
            ->except(['hari', 'hari_tujuan', 'posisi_pindah'])
            ->all();
        $posisiPindah = $data['posisi_pindah'] ?? 'tetap';
        [$jumlahBaru, $jumlahDiperbarui, $jumlahDipindahkan] = DB::transaction(
            fn () => $posisiPindah === 'tetap'
                ? [...$this->terapkanKeHari($hariTujuan, $dataJam, $jamPelajaran), 0]
                : $this->pindahkanKeHari(
                    $hariTujuan,
                    $dataJam,
                    $jamPelajaran,
                    $posisiPindah,
                ),
        );

        return redirect()
            ->route('jam-pelajaran.show', $jamPelajaran)
            ->with(
                'berhasil',
                $jumlahDipindahkan > 0
                    ? $this->pesanPemindahan(
                        $hariTujuan->count(),
                        $jumlahBaru,
                        $jumlahDiperbarui,
                        $jumlahDipindahkan,
                    )
                    : $this->pesanPenerapan($hariTujuan->count(), $jumlahBaru, $jumlahDiperbarui),
            );
    }

    public function destroy(JamPelajaran $jamPelajaran)
    {
        $jamPelajaran->update(['aktif' => false]);

        return redirect()
            ->route('jam-pelajaran.index')
            ->with('berhasil', 'Jam pelajaran berhasil dinonaktifkan.');
    }

    private function aturanValidasi(?JamPelajaran $jamPelajaran = null): array
    {
        return [
            'hari' => ['required', Rule::in([$jamPelajaran?->hari])],
            'hari_tujuan' => ['nullable', 'array'],
            'hari_tujuan.*' => [
                'required',
                'distinct',
                Rule::in(array_keys(JamPelajaran::DAFTAR_HARI)),
                Rule::notIn([$jamPelajaran?->hari]),
            ],
            'nomor_jam' => [
                'required',
                'integer',
                'min:1',
                'max:20',
                Rule::unique('jam_pelajaran', 'nomor_jam')
                    ->where('hari', request('hari'))
                    ->ignore($jamPelajaran),
            ],
            'posisi_pindah' => [
                'nullable',
                'string',
                Rule::in(array_merge(
                    ['tetap', 'awal', 'akhir'],
                    collect(range(1, 20))->map(fn ($nomor) => "urutan:{$nomor}")->all(),
                )),
            ],
            'label' => ['nullable', 'string', 'max:100'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i'],
            'jenis' => ['required', Rule::in(array_keys(JamPelajaran::DAFTAR_JENIS))],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function aturanValidasiMassal(): array
    {
        return [
            'hari' => ['required', 'array', 'min:1'],
            'hari.*' => [
                'required',
                'distinct',
                Rule::in(array_keys(JamPelajaran::DAFTAR_HARI)),
            ],
            'posisi_sisip' => [
                'nullable',
                'required_without:nomor_jam',
                'string',
                Rule::in(array_merge(
                    ['akhir', 'awal'],
                    collect(range(1, 19))->map(fn ($nomor) => "setelah:{$nomor}")->all(),
                )),
            ],
            'nomor_jam' => [
                'nullable',
                'required_without:posisi_sisip',
                'integer',
                'min:1',
                'max:20',
            ],
            'label' => ['nullable', 'string', 'max:100'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i'],
            'jenis' => ['required', Rule::in(array_keys(JamPelajaran::DAFTAR_JENIS))],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function terapkanKeHari(
        Collection $daftarHari,
        array $dataJam,
        ?JamPelajaran $jamUtama = null,
    ): array {
        $jumlahBaru = 0;
        $jumlahDiperbarui = 0;

        foreach ($daftarHari as $hari) {
            $jamPelajaran = $jamUtama && $hari === $jamUtama->hari
                ? $jamUtama
                : JamPelajaran::query()
                    ->where('hari', $hari)
                    ->where('nomor_jam', $dataJam['nomor_jam'])
                    ->first();

            if ($jamPelajaran) {
                $jamPelajaran->update($dataJam);
                $jumlahDiperbarui++;

                continue;
            }

            JamPelajaran::create([
                ...$dataJam,
                'hari' => $hari,
            ]);
            $jumlahBaru++;
        }

        return [$jumlahBaru, $jumlahDiperbarui];
    }

    private function sisipkanKeHari(
        Collection $daftarHari,
        array $dataJam,
        string $posisiSisip,
    ): array {
        $jumlahBaru = 0;
        $jumlahDigeser = 0;

        foreach ($daftarHari as $hari) {
            $slotHari = JamPelajaran::query()
                ->where('hari', $hari)
                ->orderByDesc('nomor_jam')
                ->lockForUpdate()
                ->get();
            $nomorMaksimal = (int) ($slotHari->max('nomor_jam') ?? 0);
            $nomorSisip = $this->nomorSisip($posisiSisip, $nomorMaksimal);
            $slotDigeser = $slotHari
                ->where('nomor_jam', '>=', $nomorSisip)
                ->sortByDesc('nomor_jam');

            if ($slotDigeser->isNotEmpty() && $nomorMaksimal >= 20) {
                throw ValidationException::withMessages([
                    'posisi_sisip' => "Urutan {$hari} sudah mencapai batas 20 slot. Nonaktifkan atau rapikan slot terlebih dahulu.",
                ]);
            }

            foreach ($slotDigeser as $slot) {
                $nomorBaru = $slot->nomor_jam + 1;
                $slot->update([
                    'nomor_jam' => $nomorBaru,
                    'label' => $this->labelSetelahPergeseran($slot->label, $nomorBaru),
                ]);
                $jumlahDigeser++;
            }

            JamPelajaran::create([
                ...$dataJam,
                'hari' => $hari,
                'nomor_jam' => $nomorSisip,
            ]);
            $jumlahBaru++;
        }

        return [$jumlahBaru, $jumlahDigeser];
    }

    private function pindahkanKeHari(
        Collection $daftarHari,
        array $dataJam,
        JamPelajaran $jamUtama,
        string $posisiPindah,
    ): array {
        $jumlahBaru = 0;
        $jumlahDiperbarui = 0;
        $jumlahDipindahkan = 0;
        $nomorAsal = $jamUtama->nomor_jam;
        $dataTanpaNomor = collect($dataJam)->except('nomor_jam')->all();

        foreach ($daftarHari as $hari) {
            $slotHari = JamPelajaran::query()
                ->where('hari', $hari)
                ->orderBy('nomor_jam')
                ->lockForUpdate()
                ->get();
            $nomorMaksimal = (int) ($slotHari->max('nomor_jam') ?? 0);
            $slotDipindahkan = $hari === $jamUtama->hari
                ? $jamUtama
                : $slotHari->firstWhere('nomor_jam', $nomorAsal);

            if (! $slotDipindahkan) {
                if ($nomorMaksimal >= 20) {
                    throw ValidationException::withMessages([
                        'posisi_pindah' => "Urutan {$hari} sudah mencapai batas 20 slot.",
                    ]);
                }

                $nomorTujuan = $this->nomorTujuanBaru($posisiPindah, $nomorMaksimal);
                $jumlahDigeser = $this->geserUntukSisipan($slotHari, $nomorTujuan, 'posisi_pindah');

                JamPelajaran::create([
                    ...$dataTanpaNomor,
                    'hari' => $hari,
                    'nomor_jam' => $nomorTujuan,
                ]);
                $jumlahBaru++;
                $jumlahDipindahkan += $jumlahDigeser > 0 ? 1 : 0;

                continue;
            }

            $nomorTujuan = $this->nomorTujuanPindah(
                $posisiPindah,
                $nomorMaksimal,
                $slotDipindahkan->nomor_jam,
            );

            if ($nomorTujuan !== $slotDipindahkan->nomor_jam) {
                $this->pindahkanSlot($slotDipindahkan, $slotHari, $nomorTujuan);
                $jumlahDipindahkan++;
            }

            $slotDipindahkan->update([
                ...$dataTanpaNomor,
                'nomor_jam' => $nomorTujuan,
                'label' => $this->labelSetelahPergeseran(
                    $dataTanpaNomor['label'] ?? $slotDipindahkan->label,
                    $nomorTujuan,
                ),
            ]);
            $jumlahDiperbarui++;
        }

        return [$jumlahBaru, $jumlahDiperbarui, $jumlahDipindahkan];
    }

    private function pindahkanSlot(
        JamPelajaran $slotDipindahkan,
        Collection $slotHari,
        int $nomorTujuan,
    ): void {
        $nomorAsal = $slotDipindahkan->nomor_jam;
        $slotDipindahkan->update(['nomor_jam' => 100]);

        if ($nomorTujuan < $nomorAsal) {
            $slotDigeser = $slotHari
                ->filter(fn (JamPelajaran $slot) => (
                    $slot->id !== $slotDipindahkan->id
                    && $slot->nomor_jam >= $nomorTujuan
                    && $slot->nomor_jam < $nomorAsal
                ))
                ->sortByDesc('nomor_jam');

            foreach ($slotDigeser as $slot) {
                $nomorBaru = $slot->nomor_jam + 1;
                $slot->update([
                    'nomor_jam' => $nomorBaru,
                    'label' => $this->labelSetelahPergeseran($slot->label, $nomorBaru),
                ]);
            }
        } else {
            $slotDigeser = $slotHari
                ->filter(fn (JamPelajaran $slot) => (
                    $slot->id !== $slotDipindahkan->id
                    && $slot->nomor_jam > $nomorAsal
                    && $slot->nomor_jam <= $nomorTujuan
                ))
                ->sortBy('nomor_jam');

            foreach ($slotDigeser as $slot) {
                $nomorBaru = $slot->nomor_jam - 1;
                $slot->update([
                    'nomor_jam' => $nomorBaru,
                    'label' => $this->labelSetelahPergeseran($slot->label, $nomorBaru),
                ]);
            }
        }
    }

    private function nomorTujuanPindah(
        string $posisiPindah,
        int $nomorMaksimal,
        int $nomorSaatIni,
    ): int {
        return match ($posisiPindah) {
            'awal' => 1,
            'akhir' => max(1, $nomorMaksimal),
            default => str_starts_with($posisiPindah, 'urutan:')
                ? min(max(1, (int) str($posisiPindah)->after('urutan:')->toString()), max(1, $nomorMaksimal))
                : $nomorSaatIni,
        };
    }

    private function nomorTujuanBaru(string $posisiPindah, int $nomorMaksimal): int
    {
        return match ($posisiPindah) {
            'awal' => 1,
            'akhir' => $nomorMaksimal + 1,
            default => min(
                max(1, (int) str($posisiPindah)->after('urutan:')->toString()),
                $nomorMaksimal + 1,
            ),
        };
    }

    private function geserUntukSisipan(
        Collection $slotHari,
        int $nomorSisip,
        string $kunciKesalahan,
    ): int {
        $nomorMaksimal = (int) ($slotHari->max('nomor_jam') ?? 0);
        $slotDigeser = $slotHari
            ->where('nomor_jam', '>=', $nomorSisip)
            ->sortByDesc('nomor_jam');

        if ($slotDigeser->isNotEmpty() && $nomorMaksimal >= 20) {
            throw ValidationException::withMessages([
                $kunciKesalahan => 'Urutan jam sudah mencapai batas 20 slot.',
            ]);
        }

        foreach ($slotDigeser as $slot) {
            $nomorBaru = $slot->nomor_jam + 1;
            $slot->update([
                'nomor_jam' => $nomorBaru,
                'label' => $this->labelSetelahPergeseran($slot->label, $nomorBaru),
            ]);
        }

        return $slotDigeser->count();
    }

    private function nomorSisip(string $posisiSisip, int $nomorMaksimal): int
    {
        if ($posisiSisip === 'akhir') {
            if ($nomorMaksimal >= 20) {
                throw ValidationException::withMessages([
                    'posisi_sisip' => 'Urutan jam sudah mencapai batas 20 slot.',
                ]);
            }

            return $nomorMaksimal + 1;
        }

        if ($posisiSisip === 'awal') {
            return 1;
        }

        if (str_starts_with($posisiSisip, 'sebelum:')) {
            $nomor = (int) str($posisiSisip)->after('sebelum:')->toString();

            return min(max(1, $nomor), $nomorMaksimal + 1);
        }

        $nomorSetelah = (int) str($posisiSisip)->after('setelah:')->toString();

        return min(max(1, $nomorSetelah + 1), $nomorMaksimal + 1);
    }

    private function labelSetelahPergeseran(?string $label, int $nomorBaru): ?string
    {
        if (! $label) {
            return $label;
        }

        if (preg_match('/^Jam ke-\d+$/i', $label)) {
            return "Jam ke-{$nomorBaru}";
        }

        if (preg_match('/^Jam \d+$/i', $label)) {
            return "Jam {$nomorBaru}";
        }

        return $label;
    }

    private function pesanPenerapan(
        int $jumlahHari,
        int $jumlahBaru,
        int $jumlahDiperbarui,
    ): string {
        $rincian = collect([
            $jumlahBaru > 0 ? "{$jumlahBaru} ditambahkan" : null,
            $jumlahDiperbarui > 0 ? "{$jumlahDiperbarui} diperbarui" : null,
        ])->filter()->implode(', ');

        return "Jam pelajaran berhasil diterapkan ke {$jumlahHari} hari ({$rincian}).";
    }

    private function pesanPenyisipan(
        int $jumlahHari,
        int $jumlahBaru,
        int $jumlahDigeser,
    ): string {
        $rincianGeser = $jumlahDigeser > 0
            ? " {$jumlahDigeser} slot berikutnya digeser otomatis."
            : '';

        return "{$jumlahBaru} jam pelajaran berhasil disisipkan ke {$jumlahHari} hari.{$rincianGeser}";
    }

    private function pesanPemindahan(
        int $jumlahHari,
        int $jumlahBaru,
        int $jumlahDiperbarui,
        int $jumlahDipindahkan,
    ): string {
        $rincian = collect([
            $jumlahBaru > 0 ? "{$jumlahBaru} ditambahkan" : null,
            $jumlahDiperbarui > 0 ? "{$jumlahDiperbarui} diperbarui" : null,
            "{$jumlahDipindahkan} dipindahkan",
        ])->filter()->implode(', ');

        return "Jam pelajaran berhasil diterapkan ke {$jumlahHari} hari ({$rincian}). Urutan lain digeser otomatis.";
    }

    private function pastikanJamValid(array $data): void
    {
        if ($this->menitDariJam($data['jam_selesai']) <= $this->menitDariJam($data['jam_mulai'])) {
            throw ValidationException::withMessages([
                'jam_selesai' => 'Jam selesai harus lebih besar dari jam mulai.',
            ]);
        }
    }

    private function menitDariJam(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($hour * 60) + $minute;
    }
}
