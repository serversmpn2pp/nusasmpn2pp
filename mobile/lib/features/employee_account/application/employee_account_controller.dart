import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/employee_account/data/employee_account_repository.dart';
import 'package:nusa/features/employee_account/domain/employee_account.dart';

class EmployeeAccountListController extends AsyncNotifier<EmployeeAccountPage> {
  String _query = '';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<EmployeeAccountPage> build() => _fetch(page: 1);

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
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

  Future<EmployeeAccountPage> _fetch({required int page}) => _guard(
    () => ref
        .read(employeeAccountRepositoryProvider)
        .fetchAccounts(query: _query, status: _status, page: page),
  );

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final employeeAccountListControllerProvider =
    AsyncNotifierProvider.autoDispose<
      EmployeeAccountListController,
      EmployeeAccountPage
    >(EmployeeAccountListController.new);

final employeeAccountDetailProvider = FutureProvider.autoDispose
    .family<EmployeeAccountDetail, int>((ref, employeeId) async {
      try {
        return await ref
            .read(employeeAccountRepositoryProvider)
            .fetchAccount(employeeId);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final employeeAccountActionsProvider = Provider<EmployeeAccountActions>(
  EmployeeAccountActions.new,
);

class EmployeeAccountActions {
  EmployeeAccountActions(this._ref);

  final Ref _ref;

  Future<void> createAccount(int employeeId) => _guard(
    () =>
        _ref.read(employeeAccountRepositoryProvider).createAccount(employeeId),
  );

  Future<BulkAccountResult> createAllAccounts() => _guard(
    () => _ref.read(employeeAccountRepositoryProvider).createAllAccounts(),
  );

  Future<void> resetPassword(int employeeId) => _guard(
    () =>
        _ref.read(employeeAccountRepositoryProvider).resetPassword(employeeId),
  );

  Future<void> updateStatus({required int employeeId, required bool active}) =>
      _guard(
        () => _ref
            .read(employeeAccountRepositoryProvider)
            .updateStatus(employeeId: employeeId, active: active),
      );

  Future<void> updateRoles({
    required int employeeId,
    required List<int> roleIds,
  }) => _guard(
    () => _ref
        .read(employeeAccountRepositoryProvider)
        .updateRoles(employeeId: employeeId, roleIds: roleIds),
  );

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
