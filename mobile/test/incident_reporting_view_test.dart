import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/incident_reporting/data/incident_evidence_picker.dart';
import 'package:nusa/features/incident_reporting/data/incident_reporting_remote_data_source.dart';
import 'package:nusa/features/incident_reporting/domain/incident_reporting.dart';
import 'package:nusa/features/incident_reporting/presentation/incident_reporting_view.dart';

void main() {
  test('domain membaca referensi siswa, kelas, dan batas laporan', () {
    final reference = IncidentReportReference.fromJson(_referenceJson());

    expect(reference.defaultAcademicYearId, 1);
    expect(reference.limits.maxStudents, 100);
    expect(reference.classes.single.name, 'VII.A');
    expect(reference.students, hasLength(2));
    expect(reference.students.first.classLabel(academicYearId: 1), 'VII.A');
  });

  testWidgets('form laporan tetap rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeIncidentReportingRemoteDataSource());

    expect(find.text('Laporkan Kejadian'), findsOneWidget);
    expect(find.text('Catat fakta kejadian'), findsOneWidget);
    expect(find.byKey(const Key('incident-academic-year')), findsOneWidget);
    expect(find.byKey(const Key('incident-class')), findsOneWidget);
    expect(find.byKey(const Key('select-incident-students')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('pegawai memilih siswa, bukti, lalu mengirim laporan ke BK', (
    tester,
  ) async {
    final remote = _FakeIncidentReportingRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('select-incident-students')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('incident-student-option-1')));
    await tester.tap(find.byKey(const Key('incident-student-option-2')));
    await tester.tap(find.byKey(const Key('apply-incident-students')));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.byKey(const Key('incident-chronology')),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.enterText(
      find.byKey(const Key('incident-chronology')),
      'Dua siswa terlibat dalam satu kejadian yang perlu diperiksa BK.',
    );
    await tester.scrollUntilVisible(
      find.byKey(const Key('pick-incident-evidence')),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('pick-incident-evidence')));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('submit-incident-report')),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('submit-incident-report')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));

    expect(remote.submitCalls, 1);
    expect(remote.lastValue?.studentIds, [1, 2]);
    expect(remote.lastValue?.academicYearId, 1);
    expect(remote.lastValue?.evidence.single.name, 'bukti-koridor.pdf');
    expect(find.text('Laporan terkirim'), findsOneWidget);
    expect(find.textContaining('LP-2026-0001'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.byKey(const Key('close-incident-success')));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  IncidentReportingRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        incidentReportingRemoteDataSourceProvider.overrideWithValue(remote),
        incidentEvidencePickerProvider.overrideWithValue(
          const _FakeIncidentEvidencePicker(),
        ),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const IncidentReportingView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Map<String, dynamic> _referenceJson() => {
  'nilai_awal': {'tanggal_kejadian': '2026-08-30', 'tahun_pelajaran_id': 1},
  'batas': {
    'maksimal_siswa': 100,
    'maksimal_saksi': 10,
    'maksimal_bukti': 5,
    'maksimal_bukti_mb': 10,
  },
  'tahun_pelajaran': [
    {'id': 1, 'nama': '2026/2027', 'aktif': true},
  ],
  'kelas': [
    {'id': 1, 'tahun_pelajaran_id': 1, 'nama': 'VII.A', 'tingkat': 7},
  ],
  'siswa': [
    {
      'id': 1,
      'nama': 'Siswa Pertama',
      'nis': 'MOB-001',
      'nisn': '0099550001',
      'penempatan': [
        {'tahun_pelajaran_id': 1, 'kelas_id': 1, 'kelas': 'VII.A'},
      ],
    },
    {
      'id': 2,
      'nama': 'Siswa Kedua',
      'nis': 'MOB-002',
      'nisn': '0099550002',
      'penempatan': [
        {'tahun_pelajaran_id': 2, 'kelas_id': 2, 'kelas': 'VIII.B'},
      ],
    },
  ],
};

final class _FakeIncidentReportingRemoteDataSource
    implements IncidentReportingRemoteDataSource {
  int submitCalls = 0;
  IncidentReportFormValue? lastValue;

  @override
  Future<IncidentReportReference> fetchReference() async =>
      IncidentReportReference.fromJson(_referenceJson());

  @override
  Future<IncidentReportResult> submit(IncidentReportFormValue value) async {
    submitCalls++;
    lastValue = value;
    return const IncidentReportResult(
      message: '2 laporan siswa berhasil dibuat dan dikirim ke BK.',
      reportCount: 2,
      reports: [
        IncidentCreatedReport(id: 1, number: 'LP-2026-0001'),
        IncidentCreatedReport(id: 2, number: 'LP-2026-0002'),
      ],
    );
  }
}

final class _FakeIncidentEvidencePicker implements IncidentEvidencePicker {
  const _FakeIncidentEvidencePicker();

  @override
  Future<List<IncidentEvidenceFile>> pick() async => [
    IncidentEvidenceFile(
      name: 'bukti-koridor.pdf',
      bytes: Uint8List.fromList([37, 80, 68, 70]),
    ),
  ];
}
