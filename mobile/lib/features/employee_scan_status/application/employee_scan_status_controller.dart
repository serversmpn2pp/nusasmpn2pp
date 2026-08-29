import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/employee_scan_status/data/employee_scan_status_repository.dart';
import 'package:nusa/features/employee_scan_status/domain/employee_scan_status.dart';

class EmployeeScanStatusController
    extends AsyncNotifier<EmployeeScanStatusDashboard> {
  String? _employeeType;
  String _status = 'semua';
  String _query = '';
  int _requestVersion = 0;

  @override
  Future<EmployeeScanStatusDashboard> build() => _fetch();

  Future<void> filterEmployeeType(String? value) async {
    if (_employeeType == value) return;
    _employeeType = value;
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> search(String value) async {
    final query = value.trim();
    if (_query == query) return;
    _query = query;
    await refresh();
  }

  Future<void> refresh({bool silent = false}) async {
    if (silent && state.isLoading) return;
    final version = ++_requestVersion;
    final previous = state.value;
    if (!silent) state = const AsyncLoading();
    try {
      final result = await _fetch();
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version != _requestVersion) return;
      if (silent && previous != null) {
        state = AsyncData(previous);
      } else {
        state = AsyncError(error, stackTrace);
      }
    }
  }

  Future<EmployeeScanStatusDashboard> _fetch() async {
    try {
      return await ref
          .read(employeeScanStatusRepositoryProvider)
          .fetch(employeeType: _employeeType, status: _status, query: _query);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final employeeScanStatusControllerProvider =
    AsyncNotifierProvider.autoDispose<
      EmployeeScanStatusController,
      EmployeeScanStatusDashboard
    >(EmployeeScanStatusController.new);
