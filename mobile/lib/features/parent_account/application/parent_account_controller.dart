import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/parent_account/data/parent_account_repository.dart';
import 'package:nusa/features/parent_account/domain/parent_account.dart';

class ParentAccountListController extends AsyncNotifier<ParentAccountPage> {
  String _query = '';
  String _status = 'semua';
  int? _classId;
  int _requestVersion = 0;

  @override
  Future<ParentAccountPage> build() => _fetch(page: 1);

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> filterClass(int? value) async {
    if (_classId == value) return;
    _classId = value;
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

  Future<ParentAccountPage> _fetch({required int page}) => _guard(
    () => ref
        .read(parentAccountRepositoryProvider)
        .fetchAccounts(
          query: _query,
          status: _status,
          classId: _classId,
          page: page,
        ),
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

final parentAccountListControllerProvider =
    AsyncNotifierProvider.autoDispose<
      ParentAccountListController,
      ParentAccountPage
    >(ParentAccountListController.new);

final parentAccountDetailProvider = FutureProvider.autoDispose
    .family<ParentAccountDetail, int>((ref, studentId) async {
      try {
        return await ref
            .read(parentAccountRepositoryProvider)
            .fetchAccount(studentId);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final parentAccountActionsProvider = Provider<ParentAccountActions>(
  ParentAccountActions.new,
);

class ParentAccountActions {
  ParentAccountActions(this._ref);

  final Ref _ref;

  Future<void> createAccount(int studentId) => _guard(
    () => _ref.read(parentAccountRepositoryProvider).createAccount(studentId),
  );

  Future<BulkParentAccountResult> createClassAccounts(int classId) => _guard(
    () =>
        _ref.read(parentAccountRepositoryProvider).createClassAccounts(classId),
  );

  Future<void> resetPassword(int studentId) => _guard(
    () => _ref.read(parentAccountRepositoryProvider).resetPassword(studentId),
  );

  Future<void> updateStatus({required int studentId, required bool active}) =>
      _guard(
        () => _ref
            .read(parentAccountRepositoryProvider)
            .updateStatus(studentId: studentId, active: active),
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
