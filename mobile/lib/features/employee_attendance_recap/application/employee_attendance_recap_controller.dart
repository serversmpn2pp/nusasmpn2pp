import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/employee_attendance_recap/data/employee_attendance_recap_repository.dart';
import 'package:nusa/features/employee_attendance_recap/domain/employee_attendance_recap.dart';

class EmployeeAttendanceRecapController
    extends AsyncNotifier<EmployeeAttendanceRecapPage> {
  String _date = _isoDate(DateTime.now());
  String? _employeeType;
  int? _employeeId;
  String _employeeStatus = 'aktif';
  String _status = 'semua';
  String _query = '';
  int _page = 1;
  bool _loadingMore = false;

  @override
  Future<EmployeeAttendanceRecapPage> build() => _fetch();

  Future<EmployeeAttendanceRecapPage> _fetch() async {
    try {
      final result = await ref
          .read(employeeAttendanceRecapRepositoryProvider)
          .fetch(
            date: _date,
            employeeType: _employeeType,
            employeeId: _employeeId,
            employeeStatus: _employeeStatus,
            status: _status,
            query: _query,
            page: _page,
          );
      _employeeType = result.employeeType;
      _employeeId = result.employeeId;
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
    await refresh();
  }

  Future<void> filterEmployeeType(String? value) async {
    _employeeType = value;
    _employeeId = null;
    await refresh();
  }

  Future<void> filterEmployee(int? value) async {
    _employeeId = value;
    await refresh();
  }

  Future<void> filterEmployeeStatus(String value) async {
    _employeeStatus = value;
    _employeeId = null;
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    _status = value;
    await refresh();
  }

  Future<void> search(String value) async {
    _query = value.trim();
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
        EmployeeAttendanceRecapPage(
          date: next.date,
          dateLabel: next.dateLabel,
          items: [...current.items, ...next.items],
          summary: next.summary,
          employeeTypes: next.employeeTypes,
          employees: next.employees,
          employeeType: next.employeeType,
          employeeId: next.employeeId,
          employeeStatus: next.employeeStatus,
          status: next.status,
          query: next.query,
          page: next.page,
          hasMore: next.hasMore,
          privateScope: next.privateScope,
          canCorrect: next.canCorrect,
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

final employeeAttendanceRecapControllerProvider =
    AsyncNotifierProvider.autoDispose<
      EmployeeAttendanceRecapController,
      EmployeeAttendanceRecapPage
    >(EmployeeAttendanceRecapController.new);

final employeeAttendanceRecapActionsProvider =
    Provider<EmployeeAttendanceRecapActions>(
      EmployeeAttendanceRecapActions.new,
    );

class EmployeeAttendanceRecapActions {
  EmployeeAttendanceRecapActions(this._ref);
  final Ref _ref;

  Future<EmployeeAttendanceDetail> detail({
    required int employeeId,
    required String date,
  }) => _guardResult(
    () => _ref
        .read(employeeAttendanceRecapRepositoryProvider)
        .detail(employeeId: employeeId, date: date),
  );

  Future<void> correct({
    required int employeeId,
    required String date,
    required EmployeeAttendanceCorrectionValue value,
  }) => _guard(() async {
    await _ref
        .read(employeeAttendanceRecapRepositoryProvider)
        .correct(employeeId: employeeId, date: date, value: value);
    _ref.invalidate(employeeAttendanceRecapControllerProvider);
  });

  Future<void> _guard(Future<void> Function() operation) async {
    try {
      await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  Future<T> _guardResult<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

String _isoDate(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
