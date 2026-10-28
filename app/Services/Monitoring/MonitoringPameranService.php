<?php

namespace App\Services\Monitoring;

use App\Models\PerangkatMonitoring;
use App\Models\TahunPelajaran;
use App\Services\Humas\AcaraBukuTamuService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class MonitoringPameranService
{
    public function __construct(private readonly MonitoringSiswaService $siswa, private readonly AcaraBukuTamuService $tamu) {}

    public function batasTanggal(): array
    {
        $tahun = TahunPelajaran::where('aktif', true)->orderByDesc('tanggal_mulai')->first();
        $min = $tahun?->tanggal_mulai?->toDateString() ?? now()->toDateString();
        $max = min($tahun?->tanggal_selesai?->toDateString() ?? now()->toDateString(), now()->toDateString());
        if ($min > $max) {
            $min = $max;
        }

        return compact('min', 'max');
    }

    public function aturanTanggal(): array
    {
        $batas = $this->batasTanggal();

        return ['date_format:Y-m-d', 'after_or_equal:'.$batas['min'], 'before_or_equal:'.$batas['max']];
    }

    public function tanggal(PerangkatMonitoring $layar): string
    {
        $batas = $this->batasTanggal();
        $pilihan = $layar->pameran_tanggal?->toDateString() ?? now()->toDateString();

        return max($batas['min'], min($batas['max'], $pilihan));
    }

    private function mentah(PerangkatMonitoring $layar): array
    {
        $tanggal = $this->tanggal($layar);
        $tahun = TahunPelajaran::where('aktif', true)->orderByDesc('tanggal_mulai')->value('id') ?? 0;
        $key = 'monitor-pameran:v1:'.$tahun.':'.$tanggal.':'.intdiv(now()->timestamp, 5);

        return Cache::remember($key, 5, fn () => $this->siswa->bangun(true, Carbon::parse($tanggal)));
    }

    public function aktivitas(PerangkatMonitoring $layar, bool $rincian = false): array
    {
        $data = $this->amankan($this->mentah($layar), $layar);
        $data['batas_tanggal'] = $this->batasTanggal();
        $data['identitas_ditampilkan'] = (bool) $layar->pameran_identitas;
        if (! $rincian) {
            unset($data['rincian']);
        }

        return $data;
    }

    private function amankan(array $data, PerangkatMonitoring $layar): array
    {
        if (isset($data['_siswa'])) {
            $siswa = $data['_siswa'];
            if ($layar->pameran_identitas) {
                if (! empty($data['foto_url'])) {
                    $data['foto_url'] = route('monitoring-pameran.foto', $siswa);
                }
            } else {
                $kode = strtoupper(substr(hash_hmac('sha256', (string) $siswa, config('app.key').':'.$layar->id), 0, 6));
                $data['nama'] = 'Siswa '.$kode;
                $data['foto_url'] = null;
                if (isset($data['id'])) {
                    $data['id'] = hash_hmac('sha256', $data['id'], config('app.key').':'.$layar->id);
                }
            }
            unset($data['_siswa']);
        }
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->amankan($value, $layar);
            }
        }

        return $data;
    }

    public function fotoDiizinkan(PerangkatMonitoring $layar, int $siswa): bool
    {
        return $layar->pameran_identitas && collect($this->mentah($layar)['rincian']['presensi'])->contains('_siswa', $siswa);
    }

    public function pengunjung(PerangkatMonitoring $layar): array
    {
        $acara = $layar->acaraBukuTamu;
        abort_unless($acara && $acara->status !== 'draf', 410, 'Acara buku tamu tidak lagi tersedia untuk layar ini.');
        $key = 'monitor-pameran-tamu:v1:'.$acara->id.':'.intdiv(now()->timestamp, 5);

        return Cache::remember($key, 5, function () use ($acara) {
            $statistik = $this->tamu->statistik($acara, ['dari' => $acara->mulai_pada->toDateString(), 'sampai' => $acara->selesai_pada->toDateString()]);
            $hariIni = $acara->respon()->where('status', 'valid')->where('created_at', '>=', now()->startOfDay())->where('created_at', '<', now()->addDay()->startOfDay())->count();

            return ['acara' => ['nama' => $acara->nama, 'lokasi' => $acara->lokasi,
                'mulai' => $acara->mulai_pada->toDateString(), 'selesai' => $acara->selesai_pada->toDateString()],
                'statistik' => $statistik, 'pengunjung_hari_ini' => $hariIni, 'tanggal_hari_ini' => now()->toDateString(),
                'diperbarui_pada' => now()->toIso8601String(), 'zona_waktu' => config('app.timezone')];
        });
    }
}
