import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/parent_child_exams/data/parent_child_exams_remote_data_source.dart';
import 'package:nusa/features/parent_child_exams/domain/parent_child_exams.dart';
import 'package:nusa/features/parent_child_exams/presentation/parent_child_exams_view.dart';
import 'package:nusa/features/home/presentation/home_view.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';

void main() {
  test('kartu ujian orang tua langsung menuju Ujian Anak Saya', () {
    const group = MenuGroup(
      code: 'ujian-asesmen',
      label: 'Ujian Anak Saya',
      description: 'Pantau ujian anak.',
      icon: 'quiz',
      items: [
        MenuEntry(
          code: 'ujian-anak-saya',
          label: 'Ujian Anak Saya',
          description: 'Pantau ujian anak.',
          initials: 'UA',
          subgroup: 'Pemantauan Anak',
          icon: null,
          status: 'tersedia',
          route: '/ujian-anak-saya',
        ),
      ],
    );

    expect(nusaMenuGroupDestination(group), '/ujian-anak-saya');
  });

  testWidgets(
    'orang tua memilih anak dan hanya melihat hasil ujian terpublikasi',
    (tester) async {
      tester.view.physicalSize = const Size(320, 700);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final remote = _FakeParentChildExamsRemoteDataSource();

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            parentChildExamsRemoteDataSourceProvider.overrideWithValue(remote),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const ParentChildExamsView(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Ujian Anak Saya'), findsOneWidget);
      expect(find.text('Alya Ujian'), findsWidgets);
      expect(find.byKey(const Key('parent-exams-child-filter')), findsOne);
      expect(find.text('Asesmen Matematika'), findsOneWidget);
      expect(find.text('Nilai 88'), findsOneWidget);
      expect(find.text('Token ujian'), findsNothing);
      expect(find.text('Mulai ujian'), findsNothing);
      expect(tester.takeException(), isNull);

      await tester.tap(find.byKey(const Key('parent-exams-child-filter')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Bima Ujian').last);
      await tester.pumpAndSettle();

      expect(remote.studentIds.last, 102);
      expect(find.text('Bima Ujian'), findsWidgets);
      expect(find.text('Ujian IPA'), findsOneWidget);
      expect(find.text('Hasil belum dipublikasikan'), findsOneWidget);

      await tester.tap(find.byKey(const Key('parent-exam-202')));
      await tester.pumpAndSettle();
      expect(find.text('Nilai belum dipublikasikan oleh sekolah.'), findsOne);
      expect(find.text('RAHASIA'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
}

final class _FakeParentChildExamsRemoteDataSource
    implements ParentChildExamsRemoteDataSource {
  final List<int?> studentIds = [];

  @override
  Future<ParentChildExamsPage> fetch({int? studentId}) async {
    studentIds.add(studentId);
    final bima = studentId == 102;
    return ParentChildExamsPage(
      monitoringOnly: true,
      student: bima ? _bima : _alya,
      selectedStudentId: bima ? 102 : 101,
      children: const [_alya, _bima],
      summary: ParentExamSummary(
        active: bima ? 0 : 1,
        upcoming: 0,
        completed: 1,
        total: bima ? 1 : 2,
      ),
      exams: bima ? const [_hiddenExam] : const [_publishedExam, _activeExam],
      notice:
          'Orang tua hanya dapat memantau jadwal, status, dan hasil yang '
          'telah dipublikasikan.',
    );
  }
}

const _alya = ExamChild(
  id: 101,
  name: 'Alya Ujian',
  nis: '26001',
  nisn: '0011223301',
);
const _bima = ExamChild(
  id: 102,
  name: 'Bima Ujian',
  nis: '26002',
  nisn: '0011223302',
);
const _publishedExam = ParentExam(
  id: 201,
  examId: 11,
  name: 'Asesmen Matematika',
  subject: 'Matematika',
  group: 'selesai',
  statusLabel: 'Selesai dikerjakan',
  statusTone: 'selesai',
  workStatus: 'selesai',
  durationMinutes: 60,
  date: '2026-09-12',
  time: '07:30 - 08:30',
  schoolClass: 'VIII.A',
  participantNumber: 'UA-001',
  progress: ParentExamProgress(questionCount: 40, answered: 40, unanswered: 0),
  result: ParentExamResult(
    visible: true,
    waitingForCorrection: false,
    score: 88,
    minimumScore: 75,
    passed: true,
  ),
);
const _activeExam = ParentExam(
  id: 203,
  examId: 13,
  name: 'Kuis Bahasa Indonesia',
  subject: 'Bahasa Indonesia',
  group: 'aktif',
  statusLabel: 'Sedang dikerjakan',
  statusTone: 'aktif',
  workStatus: 'sedang_mengerjakan',
  durationMinutes: 30,
  progress: ParentExamProgress(questionCount: 20, answered: 12, unanswered: 8),
  result: ParentExamResult(visible: false, waitingForCorrection: false),
);
const _hiddenExam = ParentExam(
  id: 202,
  examId: 12,
  name: 'Ujian IPA',
  subject: 'Ilmu Pengetahuan Alam',
  group: 'selesai',
  statusLabel: 'Selesai dikerjakan',
  statusTone: 'selesai',
  workStatus: 'selesai',
  durationMinutes: 60,
  date: '2026-09-11',
  schoolClass: 'VIII.B',
  participantNumber: 'UA-002',
  progress: ParentExamProgress(questionCount: 35, answered: 35, unanswered: 0),
  result: ParentExamResult(visible: false, waitingForCorrection: false),
);
