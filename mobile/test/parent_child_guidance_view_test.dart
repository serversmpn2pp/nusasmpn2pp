import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/parent_child_guidance/data/parent_child_guidance_remote_data_source.dart';
import 'package:nusa/features/parent_child_guidance/domain/parent_child_guidance.dart';
import 'package:nusa/features/parent_child_guidance/presentation/parent_child_guidance_view.dart';
import 'package:nusa/features/student_case_progress/domain/student_case_progress.dart';
import 'package:nusa/features/student_case_progress/presentation/student_case_detail_view.dart';

void main() {
  testWidgets(
    'orang tua memilih anak, melihat riwayat poin, dan membuka detail aman',
    (tester) async {
      tester.view.physicalSize = const Size(320, 700);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final remote = _FakeParentChildGuidanceRemoteDataSource();
      final router = GoRouter(
        initialLocation: '/pembinaan-poin-anak',
        routes: [
          GoRoute(
            path: '/pembinaan-poin-anak',
            builder: (context, state) => const ParentChildGuidanceView(),
            routes: [
              GoRoute(
                path: ':id',
                builder: (context, state) => StudentCaseDetailView(
                  reportId: int.parse(state.pathParameters['id']!),
                  parentMode: true,
                ),
              ),
            ],
          ),
        ],
      );
      addTearDown(router.dispose);

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            parentChildGuidanceRemoteDataSourceProvider.overrideWithValue(
              remote,
            ),
          ],
          child: MaterialApp.router(
            theme: AppTheme.light,
            routerConfig: router,
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Pembinaan & Poin Anak Saya'), findsWidgets);
      expect(find.text('Alya Pembinaan'), findsWidgets);
      expect(find.byKey(const Key('parent-guidance-child-filter')), findsOne);
      expect(tester.takeException(), isNull);

      await tester.tap(find.byKey(const Key('parent-guidance-child-filter')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Bima Pembinaan').last);
      await tester.pumpAndSettle();
      expect(remote.studentIds.last, 102);
      expect(find.text('Bima Pembinaan'), findsWidgets);

      final pointsTab = find.byKey(const Key('parent-guidance-points-tab'));
      await tester.ensureVisible(pointsTab);
      await tester.tap(pointsTab);
      await tester.pumpAndSettle();

      expect(remote.tabs.last, ParentChildGuidanceTab.points);
      expect(find.text('Pelanggaran resmi anak'), findsOneWidget);
      expect(find.text('Reward kegiatan positif'), findsOneWidget);
      expect(find.text('+15 poin'), findsOneWidget);
      expect(find.text('-5 poin'), findsOneWidget);
      expect(tester.takeException(), isNull);

      final point = find.byKey(const Key('parent-guidance-point-501'));
      await tester.ensureVisible(point);
      await tester.tap(point);
      await tester.pumpAndSettle();

      expect(find.text('Detail Pembinaan Anak'), findsOneWidget);
      expect(remote.detailId, 41);
      expect(find.text('Catatan internal BK'), findsNothing);

      final detailScroll = find.descendant(
        of: find.byKey(const Key('student-case-detail-scroll')),
        matching: find.byType(Scrollable),
      );
      await tester.scrollUntilVisible(
        find.textContaining('tidak ditampilkan pada akun orang tua'),
        280,
        scrollable: detailScroll,
      );
      expect(tester.takeException(), isNull);
    },
  );
}

final class _FakeParentChildGuidanceRemoteDataSource
    implements ParentChildGuidanceRemoteDataSource {
  final List<int?> studentIds = [];
  final List<ParentChildGuidanceTab> tabs = [];
  int? detailId;

  @override
  Future<ParentChildGuidancePage> fetch({
    required ParentChildGuidanceTab tab,
    required int page,
    int? studentId,
    int? academicYearId,
  }) async {
    tabs.add(tab);
    studentIds.add(studentId);
    final selected = studentId == 102 ? _bima : _alya;
    return ParentChildGuidancePage(
      student: selected,
      children: const [_alya, _bima],
      academicYears: const [_year],
      selectedAcademicYear: _year,
      schoolClass: const ParentChildGuidanceClass(
        id: 7,
        name: 'VIII.A',
        grade: 8,
      ),
      filter: ParentChildGuidanceFilter(
        tab: tab,
        studentId: selected.id,
        academicYearId: academicYearId ?? 9,
      ),
      summary: const ParentChildGuidanceSummary(
        reports: 1,
        inProgress: 0,
        violationPoints: 15,
        reductions: 5,
        balance: 10,
      ),
      reports: tab == ParentChildGuidanceTab.reports
          ? const [_report]
          : const [],
      points: tab == ParentChildGuidanceTab.points
          ? const [_violationPoint, _reductionPoint]
          : const [],
      pagination: const StudentCasePagination(
        page: 1,
        total: 2,
        hasNextPage: false,
      ),
      privacy: 'Halaman ini hanya menampilkan perkembangan yang dapat diketahui orang tua.',
    );
  }

  @override
  Future<StudentCaseDetail> fetchDetail(int id) async {
    detailId = id;
    return _detail;
  }
}

const _alya = StudentCasePerson(
  id: 101,
  name: 'Alya Pembinaan',
  nis: '26001',
  nisn: '0011223301',
);
const _bima = StudentCasePerson(
  id: 102,
  name: 'Bima Pembinaan',
  nis: '26002',
  nisn: '0011223302',
);
const _year = ParentChildAcademicYear(id: 9, name: '2026/2027', active: true);
const _status = StudentCaseStatus(
  label: 'Pelanggaran berpoin disahkan',
  description: 'Poin telah disahkan dan resmi tercatat.',
  color: 'danger',
  step: 4,
  finalized: true,
  handlingStatus: 'Selesai',
);
const _report = StudentCaseItem(
  id: 41,
  number: 'LP-2026-0041',
  title: 'Laporan kejadian siswa',
  status: _status,
  incidentDate: '2026-09-08',
  place: 'Gerbang sekolah',
  schoolClass: StudentCaseReference(id: 7, name: 'VIII.A'),
  academicYear: StudentCaseReference(id: 9, name: '2026/2027'),
);
const _violationPoint = ParentChildPoint(
  id: 501,
  type: 'pelanggaran',
  typeLabel: 'Poin pelanggaran resmi',
  points: 15,
  description: 'Pelanggaran resmi anak',
  recordedAt: '2026-09-09T08:30:00.000Z',
  source: 'LP-2026-0041',
  reportId: 41,
);
const _reductionPoint = ParentChildPoint(
  id: 502,
  type: 'pengurangan',
  typeLabel: 'Reward / pengurangan poin',
  points: -5,
  description: 'Reward kegiatan positif',
  recordedAt: '2026-09-10T08:30:00.000Z',
  source: 'Kegiatan sosial',
);
const _detail = StudentCaseDetail(
  student: _bima,
  report: StudentCaseReport(
    item: _report,
    type: 'pelanggaran',
    typeLabel: 'Laporan Pelanggaran',
    source: 'Laporan pegawai',
    chronology: 'Kronologi yang boleh diketahui orang tua.',
    incidentTime: '07:15',
  ),
  stages: [
    StudentCaseStage(number: 1, label: 'Laporan diterima', complete: true),
    StudentCaseStage(number: 2, label: 'Pemeriksaan BK', complete: true),
    StudentCaseStage(number: 3, label: 'Keputusan sekolah', complete: true),
    StudentCaseStage(number: 4, label: 'Selesai', complete: true),
  ],
  decision: StudentCaseDecision(
    status: 'Pelanggaran berpoin disahkan',
    description: 'Keputusan sekolah sudah ditetapkan.',
    finalized: true,
    officialPoints: 15,
    violations: [
      StudentCaseViolation(name: 'Datang terlambat tanpa alasan', points: 15),
    ],
    provisional: false,
  ),
  timeline: [
    StudentCaseTimeline(
      title: 'Poin disahkan Wakil Kesiswaan',
      description: 'Poin telah menjadi catatan resmi siswa.',
      date: '2026-09-09T08:30:00.000Z',
    ),
  ],
  followUps: [],
  privacy: 'Rincian pemeriksaan internal dikelola oleh sekolah dan tidak ditampilkan pada akun orang tua.',
);
