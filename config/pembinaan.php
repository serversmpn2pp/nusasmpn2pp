<?php

return [
    'batas_hari' => [
        'pemeriksaan_bk' => (int) env('PEMBINAAN_BATAS_HARI_BK', 2),
        'persetujuan' => (int) env('PEMBINAAN_BATAS_HARI_PERSETUJUAN', 2),
        'musyawarah' => (int) env('PEMBINAAN_BATAS_HARI_MUSYAWARAH', 3),
    ],
];
