import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/employee/data/employee_repository.dart';
import 'package:nusa/features/employee/domain/employee.dart';

class EmployeeListController extends AsyncNotifier<EmployeePage> {
  String _query = '';
  String _status = 'semua';
  String _type = 'semua';
  int _requestVersion = 0;

  @override
  Future<EmployeePage> build() => _fetch(page: 1);

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> filterType(String value) async {
    if (_type == value) return;
    _type = value;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch(page: 1);
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(page: current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<EmployeePage> _fetch({required int page}) async {
    try {
      return await ref
          .read(employeeRepositoryProvider)
          .fetchEmployees(
            query: _query,
            status: _status,
            type: _type,
            page: page,
          );
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final employeeListControllerProvider =
    AsyncNotifierProvider.autoDispose<EmployeeListController, EmployeePage>(
      EmployeeListController.new,
    );

final employeeDetailProvider = FutureProvider.autoDispose
    .family<EmployeeDetail, int>((ref, id) async {
      try {
        return await ref.read(employeeRepositoryProvider).fetchEmployee(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final employeeActionsProvider = Provider<EmployeeActions>(EmployeeActions.new);

class EmployeeActions {
  EmployeeActions(this._ref);

  final Ref _ref;

  Future<void> create(EmployeeFormValue value) =>
      _guard(() => _ref.read(employeeRepositoryProvider).create(value));

  Future<void> update({required int id, required EmployeeFormValue value}) =>
      _guard(
        () =>
            _ref.read(employeeRepositoryProvider).update(id: id, value: value),
      );

  Future<void> _guard(Future<void> Function() operation) async {
    try {
      await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
