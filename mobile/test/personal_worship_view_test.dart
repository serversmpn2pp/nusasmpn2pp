import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/personal_worship/data/personal_worship_remote_data_source.dart';
import 'package:nusa/features/personal_worship/domain/personal_worship.dart';
import 'package:nusa/features/personal_worship/presentation/personal_worship_view.dart';

void main() {
  testWidgets(
    'Ibadah Saya menampilkan ringkasan dan berhalangan tanpa overflow',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 700));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final remote = _FakePersonalWorshipRemoteDataSource(parentMode: false);

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            personalWorshipRemoteDataSourceProvider.overrideWithValue(remote),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const PersonalWorshipView(pageTitle: 'Ibadah Saya'),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Ibadah Saya'), findsWidgets);
      expect(find.text('Alya Ibadah'), findsOneWidget);
      expect(find.text('50% terlaksana'), findsOneWidget);
      expect(find.text('Berhalangan'), findsWidgets);

      await tester.scrollUntilVisible(
        find.byKey(const Key('personal-worship-privacy-notice')),
        220,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.textContaining('tanpa catatan privat'), findsOneWidget);

      await tester.scrollUntilVisible(
        find.byKey(const Key('personal-worship-record-21-2026-09-10')),
        250,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Tercatat privat 12:06 WIB'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Ibadah Anak Saya dapat mengganti anak dan bulan', (
    tester,
  ) async {
    final remote = _FakePersonalWorshipRemoteDataSource(parentMode: true);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          personalWorshipRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const PersonalWorshipView(pageTitle: 'Ibadah Anak Saya'),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Ibadah Anak Saya'), findsWidgets);
    await tester.tap(find.byKey(const Key('personal-worship-student-filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Bima Ibadah').last);
    await tester.pumpAndSettle();
    expect(remote.studentIds.last, 102);
    expect(find.text('Bima Ibadah'), findsWidgets);

    await tester.tap(find.byKey(const Key('personal-worship-previous-month')));
    await tester.pumpAndSettle();
    expect(remote.months.last, '2026-08');
    expect(tester.takeException(), isNull);
  });
}

final class _FakePersonalWorshipRemoteDataSource
    implements PersonalWorshipRemoteDataSource {
  _FakePersonalWorshipRemoteDataSource({required this.parentMode});

  final bool parentMode;
  final List<int?> studentIds = [];
  final List<String?> months = [];

  @override
  Future<PersonalWorshipPage> fetch({
    required int? studentId,
    required int? academicYearId,
    required String? month,
  }) async {
    studentIds.add(studentId);
    months.add(month);
    const students = [
      PersonalWorshipStudent(
        id: 101,
        name: 'Alya Ibadah',
        nis: '26001',
        nisn: '0011223301',
        gender: 'P',
      ),
      PersonalWorshipStudent(
        id: 102,
        name: 'Bima Ibadah',
        nis: '26002',
        nisn: '0011223302',
        gender: 'L',
      ),
    ];
    const years = [
      PersonalWorshipAcademicYear(id: 9, name: '2026/2027', active: true),
    ];
    final selectedStudent = studentId == 102 ? students[1] : students[0];
    final selectedMonth = month ?? '2026-09';

    return PersonalWorshipPage(
      mode: parentMode ? 'orang_tua' : 'siswa',
      student: selectedStudent,
      students: parentMode ? students : [selectedStudent],
      academicYears: years,
      selectedAcademicYear: years.first,
      schoolClass: const PersonalWorshipClass(
        name: 'VIII A',
        grade: 8,
        attendanceNumber: 1,
        membershipStatus: 'aktif',
      ),
      filter: PersonalWorshipFilter(
        studentId: selectedStudent.id,
        academicYearId: academicYearId ?? 9,
        month: selectedMonth,
      ),
      monthLabel: selectedMonth == '2026-08'
          ? 'Agustus 2026'
          : 'September 2026',
      summary: const PersonalWorshipSummary(
        total: 4,
        completed: 1,
        missed: 1,
        excused: 1,
        absentFromSchool: 1,
        notRequired: 0,
        requiredCount: 2,
        percentage: 50,
      ),
      records: const [
        PersonalWorshipRecord(
          scheduleId: 21,
          date: '2026-09-10',
          dateLabel: 'Kamis, 10 September 2026',
          day: 'Kamis',
          time: '12:00',
          activity: PersonalWorshipActivity(
            id: 1,
            code: 'sholat_duhur',
            name: 'Sholat Duhur Berjamaah',
            maleOnly: false,
          ),
          status: 'berhalangan',
          statusLabel: 'Berhalangan',
          schoolAttendanceLabel: 'Hadir di sekolah',
          recordedAt: '12:06',
        ),
        PersonalWorshipRecord(
          scheduleId: 21,
          date: '2026-09-03',
          dateLabel: 'Kamis, 03 September 2026',
          day: 'Kamis',
          time: '12:00',
          activity: PersonalWorshipActivity(
            id: 1,
            code: 'sholat_duhur',
            name: 'Sholat Duhur Berjamaah',
            maleOnly: false,
          ),
          status: 'sudah',
          statusLabel: 'Sudah salat',
          schoolAttendanceLabel: 'Hadir di sekolah',
          recordedAt: '12:04',
        ),
      ],
      emptyMessage: 'Belum ada kegiatan ibadah pada bulan ini.',
      privacyMessage: 'Status berhalangan ditampilkan tanpa catatan privat atau rincian konfirmasi.',
    );
  }
}
