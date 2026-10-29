<?php

namespace App\Services\Monitoring;

use App\Models\AcaraBukuTamu;
use App\Models\PerangkatMonitoring;
use App\Models\ResponAcaraBukuTamu;
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
        $key = 'monitor-pameran:v2:'.$tahun.':'.$tanggal.':'.intdiv(now()->timestamp, 3);

        return Cache::remember($key, 3, fn () => $this->siswa->bangun(true, Carbon::parse($tanggal)));
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
        $acara = $this->acara($layar);
        $key = 'monitor-pameran-tamu:v2:'.$acara->id.':'.intdiv(now()->timestamp, 3);

        return Cache::remember($key, 3, function () use ($acara) {
            $statistik = $this->tamu->statistik($acara, ['dari' => $acara->mulai_pada->toDateString(), 'sampai' => $acara->selesai_pada->toDateString()]);
            $hariIni = $this->queryPengunjung($acara)->where('created_at', '>=', now()->startOfDay())->where('created_at', '<', now()->addDay()->startOfDay())->count();

            $terbaru = $this->queryPengunjung($acara)->orderByDesc('created_at')->orderByDesc('id')->limit(10)
                ->get(['id', 'nama', 'kategori', 'created_at'])->map(fn ($row) => $this->pengunjungPublik($row))->all();

            return ['acara' => ['nama' => $acara->nama, 'lokasi' => $acara->lokasi,
                'mulai' => $acara->mulai_pada->toDateString(), 'selesai' => $acara->selesai_pada->toDateString()],
                'statistik' => $statistik, 'pengunjung_hari_ini' => $hariIni, 'tanggal_hari_ini' => now()->toDateString(),
                'terbaru' => $terbaru,
                'diperbarui_pada' => now()->toIso8601String(), 'zona_waktu' => config('app.timezone')];
        });
    }

    private function acara(PerangkatMonitoring $layar): AcaraBukuTamu
    {
        $acara = $layar->acaraBukuTamu;
        abort_unless($acara && $acara->status !== 'draf', 410, 'Acara buku tamu tidak lagi tersedia untuk layar ini.');

        return $acara;
    }

    private function queryPengunjung(AcaraBukuTamu $acara)
    {
        return $this->tamu->query($acara, ['dari' => $acara->mulai_pada->toDateString(), 'sampai' => $acara->selesai_pada->toDateString()]);
    }

    private function pengunjungPublik(ResponAcaraBukuTamu $row): array
    {
        return ['id' => $row->id, 'nama' => $row->nama, 'kategori' => $row->kategori,
            'label_kategori' => ResponAcaraBukuTamu::KATEGORI[$row->kategori] ?? $row->kategori,
            'waktu' => $row->created_at->toIso8601String()];
    }

    public function rincianPengunjung(PerangkatMonitoring $layar, array $filter): array
    {
        $acara = $this->acara($layar);
        $query = $this->queryPengunjung($acara);
        $jenis = $filter['jenis'];
        $judul = match ($jenis) {
            'hari_ini' => 'Pengunjung hari ini', 'puas' => 'Pengunjung puas / sangat puas',
            'nusa' => 'Pengunjung yang menilai NUSA', 'harian' => 'Kunjungan per hari',
            'kepuasan' => 'Kepuasan pengunjung', 'minat' => 'Minat pengunjung',
            'terbaru' => 'Pengunjung terbaru', default => 'Pengunjung tercatat',
        };
        $konteks = 'Respons valid selama acara';
        if ($jenis === 'hari_ini' || ! empty($filter['tanggal'])) {
            $tanggal = $jenis === 'hari_ini' ? now()->toDateString() : $filter['tanggal'];
            $query->where('created_at', '>=', Carbon::parse($tanggal)->startOfDay())
                ->where('created_at', '<', Carbon::parse($tanggal)->addDay()->startOfDay());
            $konteks = Carbon::parse($tanggal)->locale('id')->translatedFormat('j F Y');
        }
        if ($jenis === 'puas') {
            $query->where('kepuasan', '>=', 4);
            $konteks = 'Penilaian stand: puas dan sangat puas';
        }
        if ($jenis === 'nusa') {
            $query->whereNotNull('nilai_nusa');
            $konteks = 'Seluruh penilai NUSA; persentase puas memakai nilai 4 dan 5';
        }
        if (isset($filter['nilai'])) {
            $query->where('kepuasan', $filter['nilai']);
            $konteks = 'Penilaian stand: '.ResponAcaraBukuTamu::KEPUASAN[$filter['nilai']];
        }
        if (! empty($filter['minat'])) {
            $query->whereJsonContains('minat', $filter['minat']);
            $konteks = 'Minat: '.($acara->labelMinat()[$filter['minat']] ?? $filter['minat']);
        }
        if (! empty($filter['kategori'])) {
            $query->where('kategori', $filter['kategori']);
        }
        if (! empty($filter['q'])) {
            $query->whereLike('nama', '%'.$filter['q'].'%');
        }
        $total = $query->count();
        $perPage = 20;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min((int) ($filter['page'] ?? 1), $lastPage);
        $items = $query->orderByDesc('created_at')->orderByDesc('id')->skip(($page - 1) * $perPage)->take($perPage)
            ->get(['id', 'nama', 'kategori', 'created_at'])->map(fn ($row) => $this->pengunjungPublik($row))->all();

        return ['jenis' => $jenis, 'judul' => $judul, 'konteks' => $konteks, 'acara' => $acara->nama,
            'items' => $items, 'kategori' => ResponAcaraBukuTamu::KATEGORI,
            'pagination' => ['total' => $total, 'per_page' => $perPage, 'page' => $page, 'last_page' => $lastPage],
            'diperbarui_pada' => now()->toIso8601String(), 'zona_waktu' => config('app.timezone')];
    }
}
