import 'package:nusa/features/sts_reports/domain/sts_reports.dart';

// Synthetic students only; no school records or live API are used by tests.
Map<String, dynamic> stsReportFixture({
  StsReportKind kind = StsReportKind.report,
  StsReportFilter filter = const StsReportFilter(),
  bool empty = false,
  bool draft = true,
  bool partialScope = true,
  bool readyPeriod = true,
}) {
  final ledger = kind == StsReportKind.ledger;
  final gradeMode = filter.mode == StsLedgerMode.grade;
  final activity = filter.activityId ?? 1;
  final classId = filter.classId ?? (activity == 2 ? 8 : 7);
  final className = classId == 8 ? 'VIII.B' : 'VII.A';
  return {
    'filter': {
      'kegiatan_id': empty ? null : activity,
      'kelas_id': empty ? null : classId,
      if (ledger) 'mode': gradeMode ? 'tingkat' : 'kelas',
      if (ledger) 'tingkat': empty ? null : filter.grade ?? 7,
    },
    'opsi': {
      'kegiatan': empty
          ? []
          : [
              {'id': 1, 'label': 'Sumatif Tengah Semester Ganjil 2026/2027'},
              {'id': 2, 'label': 'STS Semester Genap'},
            ],
      'kelas': empty
          ? []
          : [
              if (activity == 1) {'id': 7, 'label': 'VII.A'},
              {'id': 8, 'label': 'VIII.B'},
            ],
      'tingkat': empty
          ? []
          : [
              {'id': 7, 'label': 'Tingkat 7'},
              {'id': 8, 'label': 'Tingkat 8'},
            ],
    },
    'konteks': empty
        ? null
        : {
            'kegiatan': {
              'id': activity,
              'nama': 'STS Semester Ganjil',
              'semester': 'ganjil',
              'tahun_pelajaran': '2026/2027',
            },
            'kelas': ledger && gradeMode
                ? null
                : {'id': classId, 'nama': className, 'tingkat': 7},
            'tingkat': filter.grade ?? 7,
            'wali_kelas': readyPeriod
                ? {'id': 5, 'nama': 'Wali Kelas Demo'}
                : null,
            if (!ledger)
              'periode': {
                'awal': '2026-09-01',
                'akhir': readyPeriod ? '2026-10-10' : '2026-10-14',
                'tanggal_rapor': '2026-10-16',
                'tersimpan': readyPeriod,
                'sudah_berakhir': readyPeriod,
              },
            'ranking_sementara': true,
            'cakupan_tingkat_lengkap': gradeMode ? !partialScope : null,
            'jumlah_mapel_pembagi': 2,
            'mapel': [
              {'id': 11, 'nama': 'Matematika'},
              {'id': 12, 'nama': 'Bahasa Indonesia'},
            ],
            'ringkasan': {
              'jumlah_siswa': 3,
              'nilai_lengkap': 0,
              'siap_cetak': readyPeriod ? 1 : 0,
              'kehadiran_diperiksa': 1,
              'masuk_ranking': 2,
              'belum_masuk_ranking': 1,
              'lengkap_final': 0,
              if (gradeMode) 'rata_tingkat': 22.13 else 'rata_kelas': 22.13,
              'rata_tertinggi': 44.25,
              'rata_terendah': 0,
            },
            'siswa': [
              for (var index = 0; index < 3; index++)
                {
                  'id': 71 + index,
                  'siswa_id': 101 + index,
                  'nama': [
                    'Alya Demo STS',
                    'Bima Demo STS',
                    'Citra Demo STS',
                  ][index],
                  'nomor_absen': index + 1,
                  'kelas': {'id': classId, 'nama': className, 'tingkat': 7},
                  'nilai': [
                    {
                      'mapel_id': 11,
                      'nilai': index == 0
                          ? 88.5
                          : index == 1
                          ? 0
                          : null,
                      'keterangan': index == 2
                          ? 'Belum tersedia'
                          : ledger && index == 0 && draft
                          ? 'Nilai manual draf'
                          : 'Nilai manual final',
                      'status': index == 2
                          ? 'kosong'
                          : ledger && index == 0 && draft
                          ? 'draf'
                          : 'final',
                      'draf': ledger && index == 0 && draft,
                      'dikecualikan': false,
                    },
                    {
                      'mapel_id': 12,
                      'nilai': null,
                      'keterangan': 'Belum tersedia',
                      'draf': false,
                      'dikecualikan': index == 2,
                      'alasan': index == 2
                          ? 'Tidak mengikuti STS sesuai pemeriksaan.'
                          : null,
                    },
                  ],
                  'nilai_lengkap': false,
                  'nilai_final_lengkap': false,
                  'jumlah_bernilai': index < 2 ? 1 : 0,
                  'jumlah_nilai_tersedia': index < 2 ? 1 : 0,
                  'jumlah_nilai_final': index == 1 || index == 0 && !draft
                      ? 1
                      : 0,
                  'jumlah_nilai_draf': index == 0 && draft ? 1 : 0,
                  if (ledger) ...{
                    'jumlah_leger': index == 0
                        ? 88.5
                        : index == 1
                        ? 0
                        : null,
                    'rata_leger': index == 0
                        ? 44.25
                        : index == 1
                        ? 0
                        : null,
                    'ranking': index < 2 ? index + 1 : null,
                    'status_ranking': index < 2
                        ? 'Ranking sementara'
                        : 'Belum ada nilai',
                  } else ...{
                    'jumlah': index == 0 ? 88.5 : 0,
                    'rata': index == 0
                        ? 88.5
                        : index == 1
                        ? 0
                        : null,
                  },
                  'kehadiran': {'sakit': 1, 'izin': 0, 'alfa': 2},
                  'keterlambatan': {'jumlah': 2, 'total_menit': 18},
                  'diperiksa': index == 0,
                  'sumber_berubah': index == 1,
                  'siap_cetak': index == 0 && readyPeriod,
                  'lampiran_perilaku_siap': false,
                  'catatan_koreksi': index == 0
                      ? 'Izin telah diperiksa oleh wali kelas.'
                      : null,
                },
            ],
            'statistik_mapel': [
              {
                'mapel': {'id': 11, 'nama': 'Matematika'},
                'rata': 44.25,
                'tertinggi_nilai': 88.5,
                'terendah_nilai': 0,
                'jumlah_nilai': 2,
                'jumlah_final': draft ? 1 : 2,
                'jumlah_draf': draft ? 1 : 0,
                'tertinggi': true,
              },
            ],
            'statistik_kelas': gradeMode
                ? [
                    {
                      'kelas': {'id': 7, 'nama': 'VII.A'},
                      'rata': 22.13,
                      'ranking': 1,
                      'jumlah_siswa': 3,
                      'masuk_ranking': 2,
                    },
                  ]
                : [],
          },
  };
}
