import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_account/data/student_account_repository.dart';
import 'package:nusa/features/student_account/domain/student_account.dart';

class StudentAccountListController extends AsyncNotifier<StudentAccountPage> {
  String _query = '';
  String _status = 'semua';
  int? _classId;
  int _requestVersion = 0;

  @override
  Future<StudentAccountPage> build() => _fetch(page: 1);

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

  Future<StudentAccountPage> _fetch({required int page}) => _guard(
    () => ref
        .read(studentAccountRepositoryProvider)
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

final studentAccountListControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StudentAccountListController,
      StudentAccountPage
    >(StudentAccountListController.new);

final studentAccountDetailProvider = FutureProvider.autoDispose
    .family<StudentAccountDetail, int>((ref, studentId) async {
      try {
        return await ref
            .read(studentAccountRepositoryProvider)
            .fetchAccount(studentId);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final studentAccountActionsProvider = Provider<StudentAccountActions>(
  StudentAccountActions.new,
);

class StudentAccountActions {
  StudentAccountActions(this._ref);

  final Ref _ref;

  Future<void> createAccount(int studentId) => _guard(
    () => _ref.read(studentAccountRepositoryProvider).createAccount(studentId),
  );

  Future<BulkStudentAccountResult> createClassAccounts(int classId) => _guard(
    () => _ref
        .read(studentAccountRepositoryProvider)
        .createClassAccounts(classId),
  );

  Future<void> resetPassword(int studentId) => _guard(
    () => _ref.read(studentAccountRepositoryProvider).resetPassword(studentId),
  );

  Future<void> updateStatus({required int studentId, required bool active}) =>
      _guard(
        () => _ref
            .read(studentAccountRepositoryProvider)
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
