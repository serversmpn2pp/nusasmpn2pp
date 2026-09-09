import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/bk_grade_assignment/data/bk_grade_assignment_remote_data_source.dart';
import 'package:nusa/features/bk_grade_assignment/domain/bk_grade_assignment.dart';
import 'package:nusa/features/bk_grade_assignment/presentation/bk_grade_assignment_view.dart';

void main() {
  test('domain membaca pembagian Guru BK per tingkat', () {
    final page = BkGradeAssignmentPage.fromJson(_pageJson());

    expect(page.selectedAcademicYear?.name, '2026/2027');
    expect(page.summary.assignmentCount, 1);
    expect(page.levels, hasLength(3));
    expect(page.levels.first.assignments.single.teacher.name, 'Guru BK Native');
    expect(page.teachers.single.activeGrades, [7]);
    expect(page.access.canManage, isTrue);
  });

  testWidgets('halaman penugasan Guru BK rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeBkGradeAssignmentRemoteDataSource();

    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();

    expect(find.text('Penugasan Tingkat Guru BK'), findsOneWidget);
    expect(find.byKey(const Key('bk-grade-assignment-year')), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Pembagian Aktif'),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Pembagian Aktif'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Guru BK Native'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Guru BK Native'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin dapat memilih Guru BK dan beberapa tingkat', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeBkGradeAssignmentRemoteDataSource();

    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.byKey(const Key('bk-grade-assignment-teacher')),
      350,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('bk-grade-assignment-teacher')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Guru BK Native · 198001012010011234').last);
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('bk-grade-assignment-level-8')));
    await tester.tap(find.byKey(const Key('bk-grade-assignment-level-9')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(
      find.byKey(const Key('bk-grade-assignment-submit')),
    );
    await tester.tap(find.byKey(const Key('bk-grade-assignment-submit')));
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(remote.lastPayload?.academicYearId, 7);
    expect(remote.lastPayload?.teacherId, 12);
    expect(remote.lastPayload?.grades, [8, 9]);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin dapat mengakhiri penugasan tanpa menghapus riwayat', (
    tester,
  ) async {
    final remote = _FakeBkGradeAssignmentRemoteDataSource();
    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.byKey(const Key('bk-grade-assignment-end-31')),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('bk-grade-assignment-end-31')));
    await tester.pumpAndSettle();
    expect(find.text('Akhiri penugasan?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('bk-grade-assignment-confirm-end')));
    await tester.pumpAndSettle();

    expect(remote.endedId, 31);
    expect(tester.takeException(), isNull);
  });
}

Widget _app(BkGradeAssignmentRemoteDataSource remote) => ProviderScope(
  overrides: [
    bkGradeAssignmentRemoteDataSourceProvider.overrideWithValue(remote),
  ],
  child: MaterialApp(
    theme: AppTheme.light,
    home: const BkGradeAssignmentView(),
  ),
);

final class _FakeBkGradeAssignmentRemoteDataSource
    implements BkGradeAssignmentRemoteDataSource {
  int createCalls = 0;
  int? endedId;
  BkGradeAssignmentPayload? lastPayload;

  @override
  Future<BkGradeAssignmentPage> fetch({int? academicYearId}) async =>
      BkGradeAssignmentPage.fromJson(_pageJson());

  @override
  Future<BkGradeAssignmentMutation> create(
    BkGradeAssignmentPayload payload,
  ) async {
    createCalls++;
    lastPayload = payload;
    return const BkGradeAssignmentMutation(
      message: '2 penugasan Guru BK berhasil disimpan.',
    );
  }

  @override
  Future<BkGradeAssignmentMutation> end(int id) async {
    endedId = id;
    return const BkGradeAssignmentMutation(
      message: 'Penugasan Guru BK berhasil diakhiri.',
    );
  }
}

Map<String, dynamic> _pageJson() => {
  'tahun_pelajaran': const [
    {'id': 7, 'nama': '2026/2027', 'aktif': true},
  ],
  'tahun_pelajaran_dipilih': const {
    'id': 7,
    'nama': '2026/2027',
    'aktif': true,
  },
  'tingkat': [
    {
      'tingkat': 7,
      'label': 'Tingkat 7',
      'penugasan': [_assignmentJson()],
    },
    const {'tingkat': 8, 'label': 'Tingkat 8', 'penugasan': []},
    const {'tingkat': 9, 'label': 'Tingkat 9', 'penugasan': []},
  ],
  'guru_bk': const [
    {
      'id': 12,
      'nama': 'Guru BK Native',
      'nip': '198001012010011234',
      'jabatan': 'Guru BK',
      'tingkat_aktif': [7],
    },
  ],
  'ringkasan': const {
    'jumlah_penugasan': 1,
    'jumlah_guru_bk': 1,
    'tingkat_terisi': 1,
    'pembagian_aktif': true,
  },
  'hak_akses': const {'dapat_kelola': true},
};

Map<String, dynamic> _assignmentJson() => const {
  'id': 31,
  'tingkat': 7,
  'guru_bk': {
    'id': 12,
    'nama': 'Guru BK Native',
    'nip': '198001012010011234',
    'jabatan': 'Guru BK',
  },
  'tanggal_mulai': '2026-09-10',
  'tanggal_selesai': null,
  'aktif': true,
};
