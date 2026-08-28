import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_attendance_recap/data/student_attendance_recap_repository.dart';
import 'package:nusa/features/student_attendance_recap/domain/student_attendance_recap.dart';

class StudentAttendanceRecapController
    extends AsyncNotifier<StudentAttendanceRecapPage> {
  String _date = _isoDate(DateTime.now());
  int? _academicYearId;
  int? _classId;
  String _status = 'semua';
  String _query = '';
  int _page = 1;
  bool _loadingMore = false;

  @override
  Future<StudentAttendanceRecapPage> build() => _fetch();
  Future<StudentAttendanceRecapPage> _fetch() async {
    try {
      final result = await ref
          .read(studentAttendanceRecapRepositoryProvider)
          .fetch(
            date: _date,
            academicYearId: _academicYearId,
            classId: _classId,
            status: _status,
            query: _query,
            page: _page,
          );
      _academicYearId ??= result.academicYearId;
      _classId = result.classId;
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

  Future<void> filterDate(String value) async {
    _date = value;
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

  Future<void> filterStatus(String value) async {
    _status = value;
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
        StudentAttendanceRecapPage(
          date: next.date,
          dateLabel: next.dateLabel,
          items: [...current.items, ...next.items],
          summary: next.summary,
          academicYears: next.academicYears,
          classes: next.classes,
          academicYearId: next.academicYearId,
          classId: next.classId,
          status: next.status,
          query: next.query,
          page: next.page,
          hasMore: next.hasMore,
          canCorrect: next.canCorrect,
          todayOnly: next.todayOnly,
          guardianScope: next.guardianScope,
        ),
      );
    } catch (error, stackTrace) {
      _page = current.page;
      state = AsyncError(error, stackTrace);
    } finally {
      _loadingMore = false;
    }
  }
}

final studentAttendanceRecapControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StudentAttendanceRecapController,
      StudentAttendanceRecapPage
    >(StudentAttendanceRecapController.new);
final studentAttendanceRecapActionsProvider =
    Provider<StudentAttendanceRecapActions>(StudentAttendanceRecapActions.new);

class StudentAttendanceRecapActions {
  StudentAttendanceRecapActions(this._ref);
  final Ref _ref;
  Future<StudentAttendanceDetail> detail({
    required int classMemberId,
    required String date,
  }) => _ref
      .read(studentAttendanceRecapRepositoryProvider)
      .detail(classMemberId: classMemberId, date: date);
  Future<void> correct({
    required int classMemberId,
    required String date,
    required AttendanceCorrectionValue value,
  }) => _guard(() async {
    await _ref
        .read(studentAttendanceRecapRepositoryProvider)
        .correct(classMemberId: classMemberId, date: date, value: value);
    _ref.invalidate(studentAttendanceRecapControllerProvider);
  });
  Future<void> _guard(Future<void> Function() operation) async {
    try {
      await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

String _isoDate(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
