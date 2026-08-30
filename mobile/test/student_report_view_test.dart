import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_report/data/student_report_evidence_saver.dart';
import 'package:nusa/features/student_report/data/student_report_remote_data_source.dart';
import 'package:nusa/features/student_report/domain/student_report.dart';
import 'package:nusa/features/student_report/presentation/student_report_detail_view.dart';
import 'package:nusa/features/student_report/presentation/student_report_list_view.dart';

void main() {
  test('domain membaca ringkasan, tenggat, fakta, dan linimasa laporan', () {
    final page = StudentReportPage.fromJson(_pageJson());
    final detail = StudentReportDetail.fromJson(_detailJson());

    expect(page.summary.total, 2);
    expect(page.summary.waitingCounseling, 1);
    expect(page.items.single.student?.name, 'Siswa Laporan Native');
    expect(page.items.single.deadline.stageLabel, 'Pemeriksaan BK');
    expect(detail.evidence.single.fileName, 'bukti-kejadian.pdf');
    expect(detail.witnesses.single.name, 'Saksi Guru');
    expect(detail.timeline.single.title, 'Laporan dibuat');
  });

  testWidgets('daftar dan filter laporan rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStudentReportRemoteDataSource();

    await _pumpList(tester, remote);

    expect(find.text('Daftar Laporan Siswa'), findsOneWidget);
    expect(find.text('Siswa Laporan Native'), findsOneWidget);
    expect(find.text('Menunggu BK'), findsOneWidget);
    expect(
      find.byKey(const Key('student-report-verification-filter')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);

    await tester.tap(find.byKey(const Key('open-student-report-filters')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('student-report-type-filter')), findsOneWidget);
    expect(
      find.byKey(const Key('student-report-class-filter')),
      findsOneWidget,
    );
    await tester.tap(find.byKey(const Key('apply-student-report-filters')));
    await tester.pumpAndSettle();

    expect(remote.fetchCalls, 2);
    expect(tester.takeException(), isNull);
  });

  testWidgets('detail menampilkan fakta dan mengunduh bukti privat', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStudentReportRemoteDataSource();
    final saver = _FakeStudentReportEvidenceSaver();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentReportRemoteDataSourceProvider.overrideWithValue(remote),
          studentReportEvidenceSaverProvider.overrideWithValue(saver),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentReportDetailView(reportId: 1),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Detail Laporan Siswa'), findsOneWidget);
    expect(find.text('PB-20260830-0001'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Kronologi'),
      240,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Kronologi'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('download-student-report-evidence-10')),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(
      find.byKey(const Key('download-student-report-evidence-10')),
    );
    await tester.pumpAndSettle();

    expect(remote.downloadCalls, 1);
    expect(saver.saveCalls, 1);
    expect(saver.lastDownload?.fileName, 'bukti-kejadian.pdf');
    expect(find.text('Bukti berhasil disimpan.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpList(
  WidgetTester tester,
  StudentReportRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        studentReportRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const StudentReportListView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Map<String, dynamic> _pageJson() => {
  'items': [_reportJson()],
  'ringkasan': {
    'total': 2,
    'kejadian': 2,
    'pembinaan': 0,
    'pelanggaran': 0,
    'menunggu_bk': 1,
    'menunggu_wakil': 0,
    'disahkan': 1,
  },
  'pilihan': {
    'status': [
      {'kode': 'baru', 'label': 'Baru'},
      {'kode': 'selesai', 'label': 'Selesai'},
    ],
    'tingkat': [
      {'kode': 'ringan', 'label': 'Ringan'},
    ],
    'jenis_laporan': [
      {'kode': 'kejadian', 'label': 'Laporan Kejadian'},
    ],
    'status_verifikasi': [
      {'kode': 'diajukan', 'label': 'Diajukan'},
      {'kode': 'disahkan', 'label': 'Disahkan'},
    ],
    'tahun_pelajaran': [
      {'id': 1, 'nama': '2026/2027', 'aktif': true},
    ],
    'kelas': [
      {'id': 1, 'tahun_pelajaran_id': 1, 'nama': 'VII.A', 'tingkat': 7},
    ],
  },
  'filter': {
    'kata_kunci': '',
    'status': 'semua',
    'tingkat': 'semua',
    'jenis_laporan': 'semua',
    'status_verifikasi': 'semua',
    'tahun_pelajaran_id': null,
    'kelas_id': null,
  },
  'paginasi': {
    'halaman': 1,
    'per_halaman': 15,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
  'hak_akses': {'cakupan_luas': true, 'dapat_melaporkan': true},
};

Map<String, dynamic> _reportJson() => {
  'id': 1,
  'nomor_laporan': 'PB-20260830-0001',
  'jenis_laporan': 'kejadian',
  'label_jenis_laporan': 'Laporan Kejadian',
  'sumber_laporan': 'manual',
  'tanggal_kejadian': '2026-08-30',
  'waktu_kejadian': '08:10',
  'tempat_kejadian': 'Koridor sekolah',
  'siswa': {
    'id': 1,
    'nama': 'Siswa Laporan Native',
    'nis': 'LAP-001',
    'nisn': '0088550001',
  },
  'kelas': {'id': 1, 'nama': 'VII.A', 'tingkat': 7},
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'kategori': null,
  'pelapor': {
    'id': 2,
    'nama': 'Pegawai Pelapor Native',
    'nip': '198606062016061006',
  },
  'tingkat': 'ringan',
  'label_tingkat': 'Ringan',
  'status': 'baru',
  'label_status': 'Baru',
  'status_verifikasi': 'diajukan',
  'label_status_verifikasi': 'Diajukan',
  'total_poin': 0,
  'tenggat': {
    'tahap': 'pemeriksaan_bk',
    'label_tahap': 'Pemeriksaan BK',
    'pada': '2026-09-01T08:10:00+07:00',
    'terlambat': false,
  },
  'jumlah_butir': 0,
  'jumlah_bukti': 1,
  'jumlah_saksi': 1,
  'jumlah_klarifikasi': 0,
  'jumlah_tindak_lanjut': 0,
  'dibuat_pada': '2026-08-30T08:20:00+07:00',
};

Map<String, dynamic> _detailJson() => {
  'laporan': {
    ..._reportJson(),
    'kronologi': 'Kronologi kejadian tercatat secara faktual.',
    'tindakan_awal': 'Siswa diarahkan kembali ke kelas.',
    'wali_kelas': {'id': 3, 'nama': 'Wali Kelas', 'nip': null},
    'guru_wali': null,
  },
  'butir_pelanggaran': [],
  'pemeriksaan_bk': [],
  'persetujuan': [],
  'bukti': [
    {
      'id': 10,
      'jenis': 'dokumen',
      'nama_file': 'bukti-kejadian.pdf',
      'tipe_file': 'application/pdf',
      'ukuran_file': 2048,
      'ukuran_ringkas': '2 KB',
      'keterangan': 'Bukti koridor.',
      'diunggah_pada': '2026-08-30T08:21:00+07:00',
    },
  ],
  'saksi': [
    {
      'id': 20,
      'jenis': 'pegawai',
      'label_jenis': 'Pegawai',
      'nama': 'Saksi Guru',
      'pernyataan': 'Melihat kejadian secara langsung.',
      'dicatat_pada': '2026-08-30T08:21:00+07:00',
    },
  ],
  'klarifikasi': [],
  'tindak_lanjut': [],
  'linimasa': [
    {
      'id': 30,
      'kode': 'laporan_dibuat',
      'judul': 'Laporan dibuat',
      'keterangan': 'Laporan siap diperiksa.',
      'status_sebelum': null,
      'status_sesudah': 'diajukan',
      'pengguna': 'Pegawai Pelapor Native',
      'terjadi_pada': '2026-08-30T08:20:00+07:00',
    },
  ],
  'hak_akses': {
    'dapat_kelola_fakta': true,
    'dapat_mencatat_klarifikasi': false,
  },
};

final class _FakeStudentReportRemoteDataSource
    implements StudentReportRemoteDataSource {
  int fetchCalls = 0;
  int downloadCalls = 0;

  @override
  Future<StudentReportPage> fetch({
    required String query,
    required String status,
    required String level,
    required String type,
    required String verificationStatus,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) async {
    fetchCalls++;
    return StudentReportPage.fromJson(_pageJson());
  }

  @override
  Future<StudentReportDetail> fetchDetail(int id) async =>
      StudentReportDetail.fromJson(_detailJson());

  @override
  Future<StudentReportEvidenceDownload> downloadEvidence({
    required int id,
    required String fileName,
    required String mimeType,
  }) async {
    downloadCalls++;
    return StudentReportEvidenceDownload(
      fileName: fileName,
      mimeType: mimeType,
      bytes: Uint8List.fromList([37, 80, 68, 70]),
    );
  }
}

final class _FakeStudentReportEvidenceSaver
    implements StudentReportEvidenceSaver {
  int saveCalls = 0;
  StudentReportEvidenceDownload? lastDownload;

  @override
  Future<bool> save(StudentReportEvidenceDownload download) async {
    saveCalls++;
    lastDownload = download;
    return true;
  }
}
