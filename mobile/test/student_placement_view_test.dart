import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_placement/data/student_placement_remote_data_source.dart';
import 'package:nusa/features/student_placement/domain/student_placement.dart';
import 'package:nusa/features/student_placement/presentation/student_placement_view.dart';

void main() {
  testWidgets('penempatan siswa rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeStudentPlacementRemoteDataSource());

    expect(find.text('Penempatan Siswa'), findsOneWidget);
    expect(
      find.byKey(const Key('student-placement-academic-year')),
      findsOneWidget,
    );
    expect(find.byKey(const Key('student-placement-class')), findsOneWidget);
    expect(find.text('Calon Penempatan Satu'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin memilih siswa dan menempatkannya secara massal', (
    tester,
  ) async {
    final remote = _FakeStudentPlacementRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('available-student-11')));
    await tester.pumpAndSettle();
    expect(find.text('Tempatkan (1)'), findsOneWidget);

    await tester.tap(find.byKey(const Key('place-selected-students')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('student-placement-notes')),
      'Penempatan dari aplikasi Android',
    );
    await tester.tap(find.byKey(const Key('confirm-student-placement')));
    await tester.pumpAndSettle();

    expect(remote.placeCalls, 1);
    expect(remote.lastPlacement?.studentIds, [11]);
    expect(remote.lastPlacement?.notes, 'Penempatan dari aplikasi Android');

    await tester.tap(find.byKey(const Key('student-placement-member-tab')));
    await tester.pumpAndSettle();
    expect(find.text('Calon Penempatan Satu'), findsOneWidget);
    expect(find.byKey(const Key('placed-student-11')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('pilih semua menghormati sisa kapasitas kelas', (tester) async {
    final remote = _FakeStudentPlacementRemoteDataSource(includeThird: true);
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('select-visible-students')));
    await tester.pumpAndSettle();

    expect(find.text('Tempatkan (2)'), findsOneWidget);
    expect(find.textContaining('sesuai sisa kapasitas kelas'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  StudentPlacementRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        studentPlacementRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const StudentPlacementView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeStudentPlacementRemoteDataSource
    implements StudentPlacementRemoteDataSource {
  _FakeStudentPlacementRemoteDataSource({bool includeThird = false})
    : _available = [
        const StudentPlacementStudent(
          id: 11,
          name: 'Calon Penempatan Satu',
          nis: '1101',
          nisn: '991101',
          gender: 'L',
        ),
        const StudentPlacementStudent(
          id: 12,
          name: 'Calon Penempatan Dua',
          nis: '1102',
          nisn: '991102',
          gender: 'P',
        ),
        if (includeThird)
          const StudentPlacementStudent(
            id: 13,
            name: 'Calon Penempatan Tiga',
            nis: '1103',
            nisn: '991103',
            gender: 'L',
          ),
      ];

  static const _academicYear = StudentPlacementAcademicYear(
    id: 7,
    name: '2028/2029',
    active: true,
    classCount: 1,
  );
  StudentPlacementClass _selectedClass = const StudentPlacementClass(
    id: 8,
    name: 'VII.A Mobile',
    level: 7,
    active: true,
    capacity: 3,
    memberCount: 1,
    remainingSeats: 2,
    homeroomTeacher: 'Wali Kelas Mobile',
  );
  final List<StudentPlacementStudent> _available;
  final List<StudentPlacementMember> _members = [
    const StudentPlacementMember(
      id: 20,
      rollNumber: 1,
      status: 'aktif',
      entryDate: '2028-07-15',
      student: StudentPlacementStudent(
        id: 10,
        name: 'Anggota Lama Mobile',
        nis: '1100',
        nisn: '991100',
        gender: 'L',
      ),
    ),
  ];

  int placeCalls = 0;
  StudentPlacementFormValue? lastPlacement;

  @override
  Future<StudentPlacementPage> fetch({
    int? academicYearId,
    int? classId,
    required String query,
  }) async {
    final normalized = query.toLowerCase();
    final filtered = _available
        .where(
          (student) =>
              normalized.isEmpty ||
              student.name.toLowerCase().contains(normalized) ||
              (student.nisn ?? '').contains(normalized),
        )
        .toList(growable: false);
    return StudentPlacementPage(
      academicYears: const [_academicYear],
      classes: [_selectedClass],
      selectedClass: _selectedClass,
      members: List.unmodifiable(_members),
      availableStudents: filtered,
      summary: StudentPlacementSummary(
        activeStudents: _members.length + _available.length,
        placed: _members.length,
        unplaced: _available.length,
      ),
      selectedAcademicYearId: academicYearId ?? _academicYear.id,
      selectedClassId: classId ?? _selectedClass.id,
      query: query,
      canManage: true,
      homeroomScope: false,
    );
  }

  @override
  Future<int> place(StudentPlacementFormValue value) async {
    placeCalls++;
    lastPlacement = value;
    for (final id in value.studentIds) {
      final student = _available.firstWhere((item) => item.id == id);
      _members.add(
        StudentPlacementMember(
          id: 20 + _members.length,
          status: 'aktif',
          entryDate: '2028-07-15',
          student: student,
        ),
      );
      _available.removeWhere((item) => item.id == id);
    }
    _selectedClass = StudentPlacementClass(
      id: _selectedClass.id,
      name: _selectedClass.name,
      level: _selectedClass.level,
      active: _selectedClass.active,
      capacity: _selectedClass.capacity,
      memberCount: _members.length,
      remainingSeats:
          (_selectedClass.capacity ?? _members.length) - _members.length,
      homeroomTeacher: _selectedClass.homeroomTeacher,
    );
    return value.studentIds.length;
  }
}
