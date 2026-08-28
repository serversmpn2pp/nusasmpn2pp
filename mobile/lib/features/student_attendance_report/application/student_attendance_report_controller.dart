import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_attendance_report/data/student_attendance_report_download_saver.dart';
import 'package:nusa/features/student_attendance_report/data/student_attendance_report_repository.dart';
import 'package:nusa/features/student_attendance_report/domain/student_attendance_report.dart';

class StudentAttendanceReportController
    extends AsyncNotifier<StudentAttendanceReportPage> {
  String _period = 'bulanan';
  int? _academicYearId;
  int? _classId;
  String _date = _iso(DateTime.now());
  String _month = _monthIso(DateTime.now());
  String _semester = DateTime.now().month >= 7 ? 'ganjil' : 'genap';
  String _startDate = _iso(DateTime(DateTime.now().year, DateTime.now().month));
  String _endDate = _iso(DateTime.now());
  String _query = '';
  int _page = 1;
  bool _loadingMore = false;

  @override
  Future<StudentAttendanceReportPage> build() => _fetch();

  Map<String, dynamic> get _parameters => {
    'periode': _period,
    'tahun_pelajaran_id': ?_academicYearId,
    'kelas_id': ?_classId,
    'tanggal': _date,
    'bulan': _month,
    'semester': _semester,
    'tanggal_mulai': _startDate,
    'tanggal_selesai': _endDate,
    if (_query.isNotEmpty) 'cari': _query,
    'halaman': _page,
  };

  Map<String, dynamic> parametersFor(StudentAttendanceReportPage page) => {
    'periode': page.period,
    'tahun_pelajaran_id': ?page.academicYearId,
    'kelas_id': ?page.classId,
    'tanggal': page.date,
    'bulan': page.month,
    'semester': page.semester,
    'tanggal_mulai': page.startDate,
    'tanggal_selesai': page.endDate,
  };

  Future<StudentAttendanceReportPage> _fetch() async {
    try {
      final result = await ref
          .read(studentAttendanceReportRepositoryProvider)
          .fetch(_parameters);
      _academicYearId ??= result.academicYearId;
      _classId = result.classId;
      _startDate = result.startDate;
      _endDate = result.endDate;
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  Future<void> refresh() async {
    _page = 1;
    state = const AsyncLoading();
    state = await AsyncValue.guard(_fetch);
  }

  Future<void> filterPeriod(String value) async {
    _period = value;
    _page = 1;
    await refresh();
  }

  Future<void> filterYear(int? value) async {
    _academicYearId = value;
    _classId = null;
    _page = 1;
    await refresh();
  }

  Future<void> filterClass(int? value) async {
    _classId = value;
    _page = 1;
    await refresh();
  }

  Future<void> filterDate(String value) async {
    _date = value;
    _page = 1;
    await refresh();
  }

  Future<void> filterMonth(String value) async {
    _month = value;
    _page = 1;
    await refresh();
  }

  Future<void> filterSemester(String value) async {
    _semester = value;
    _page = 1;
    await refresh();
  }

  Future<void> filterRange(String start, String end) async {
    _startDate = start;
    _endDate = end;
    _page = 1;
    await refresh();
  }

  Future<void> search(String value) async {
    _query = value.trim();
    _page = 1;
    await refresh();
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore || _loadingMore) return;
    _loadingMore = true;
    _page = current.page + 1;
    try {
      final next = await _fetch();
      state = AsyncData(
        StudentAttendanceReportPage(
          period: next.period,
          periodLabel: next.periodLabel,
          startDate: next.startDate,
          endDate: next.endDate,
          summary: next.summary,
          items: [...current.items, ...next.items],
          academicYears: next.academicYears,
          classes: next.classes,
          academicYearId: next.academicYearId,
          classId: next.classId,
          date: next.date,
          month: next.month,
          semester: next.semester,
          query: next.query,
          page: next.page,
          hasMore: next.hasMore,
          guardianScope: next.guardianScope,
          canExport: next.canExport,
        ),
      );
    } catch (error, stack) {
      _page = current.page;
      state = AsyncError(error, stack);
    } finally {
      _loadingMore = false;
    }
  }
}

final studentAttendanceReportControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StudentAttendanceReportController,
      StudentAttendanceReportPage
    >(StudentAttendanceReportController.new);

final studentAttendanceReportActionsProvider =
    Provider<StudentAttendanceReportActions>(
      StudentAttendanceReportActions.new,
    );

class StudentAttendanceReportActions {
  StudentAttendanceReportActions(this._ref);
  final Ref _ref;
  Future<StudentAttendanceReportDetail> detail(
    StudentAttendanceReportPage page,
    int id,
  ) async {
    try {
      return await _ref
          .read(studentAttendanceReportRepositoryProvider)
          .detail(
            id,
            _ref
                .read(studentAttendanceReportControllerProvider.notifier)
                .parametersFor(page),
          );
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  Future<bool> export(StudentAttendanceReportPage page) async {
    try {
      final query = _ref
          .read(studentAttendanceReportControllerProvider.notifier)
          .parametersFor(page);
      final download = await _ref
          .read(studentAttendanceReportRepositoryProvider)
          .download(query);
      return await _ref
          .read(studentAttendanceReportDownloadSaverProvider)
          .save(download);
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

String _iso(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
String _monthIso(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}';
