<?php

return [
    'direktori' => 'cadangan-database/berkas',
    'log_aktivitas' => 'cadangan-database/aktivitas.jsonl',

    'pg_bin_path' => env('NUSA_PG_BIN_PATH'),
    'pg_dump_path' => env('NUSA_PG_DUMP_PATH'),
    'pg_restore_path' => env('NUSA_PG_RESTORE_PATH'),

    'timeout_detik' => (int) env('NUSA_BACKUP_TIMEOUT', 900),
    'maksimal_unggahan_mb' => (int) env('NUSA_BACKUP_MAX_UPLOAD_MB', 250),

    'otomatis_aktif' => env('NUSA_BACKUP_OTOMATIS', true),
    'jadwal_otomatis' => env('NUSA_BACKUP_JAM', '01:00'),
    'retensi_otomatis_hari' => (int) env('NUSA_BACKUP_RETENSI_HARI', 30),
];
