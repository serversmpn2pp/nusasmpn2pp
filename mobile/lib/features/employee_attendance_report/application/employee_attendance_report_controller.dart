import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/employee_attendance_report/data/employee_attendance_report_repository.dart';
import 'package:nusa/features/employee_attendance_report/domain/employee_attendance_report.dart';

class EmployeeAttendanceReportController
    extends AsyncNotifier<EmployeeAttendanceReportPage> {
  String _month = _monthIso(DateTime.now());
  String? _employeeType;
  int? _employeeId;
  String _employeeStatus = 'aktif';
  String _query = '';
  int _page = 1;
  bool _loadingMore = false;

  @override
  Future<EmployeeAttendanceReportPage> build() => _fetch();

  Map<String, dynamic> get _parameters => {
    'bulan': _month,
    'jenis_pegawai': ?_employeeType,
    'pegawai_id': ?_employeeId,
    'status_pegawai': _employeeStatus,
    if (_query.isNotEmpty) 'cari': _query,
    'halaman': _page,
  };

  Future<EmployeeAttendanceReportPage> _fetch() async {
    try {
      final result = await ref
          .read(employeeAttendanceReportRepositoryProvider)
          .fetch(_parameters);
      _month = result.month;
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

  Future<void> filterMonth(String value) async {
    _month = value;
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
        EmployeeAttendanceReportPage(
          month: next.month,
          periodLabel: next.periodLabel,
          startDate: next.startDate,
          endDate: next.endDate,
          summary: next.summary,
          items: [...current.items, ...next.items],
          employeeTypes: next.employeeTypes,
          employees: next.employees,
          employeeType: next.employeeType,
          employeeId: next.employeeId,
          employeeStatus: next.employeeStatus,
          query: next.query,
          page: next.page,
          hasMore: next.hasMore,
          privateScope: next.privateScope,
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

final employeeAttendanceReportControllerProvider =
    AsyncNotifierProvider.autoDispose<
      EmployeeAttendanceReportController,
      EmployeeAttendanceReportPage
    >(EmployeeAttendanceReportController.new);

final employeeAttendanceReportActionsProvider =
    Provider<EmployeeAttendanceReportActions>(
      EmployeeAttendanceReportActions.new,
    );

class EmployeeAttendanceReportActions {
  EmployeeAttendanceReportActions(this._ref);
  final Ref _ref;

  Future<EmployeeAttendanceReportDetail> detail(
    EmployeeAttendanceReportPage page,
    int employeeId,
  ) async {
    try {
      return await _ref.read(employeeAttendanceReportRepositoryProvider).detail(
        employeeId,
        {'bulan': page.month},
      );
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

String _monthIso(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}';
