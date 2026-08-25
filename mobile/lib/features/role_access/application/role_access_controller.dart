import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/role_access/data/role_access_repository.dart';
import 'package:nusa/features/role_access/domain/role_access.dart';

class RoleAccessController extends AsyncNotifier<RoleAccessPage> {
  String _query = '';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<RoleAccessPage> build() => _fetch(page: 1);

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

  Future<RoleAccessPage> _fetch({required int page}) => _guard(
    () => ref
        .read(roleAccessRepositoryProvider)
        .fetch(query: _query, status: _status, page: page),
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

final roleAccessControllerProvider =
    AsyncNotifierProvider.autoDispose<RoleAccessController, RoleAccessPage>(
      RoleAccessController.new,
    );

final roleAccessReferenceProvider = FutureProvider.autoDispose((ref) async {
  try {
    return await ref.read(roleAccessRepositoryProvider).fetchReference();
  } on UnauthorizedException {
    await ref.read(authControllerProvider.notifier).logout();
    rethrow;
  }
});

final roleAccessDetailProvider = FutureProvider.autoDispose
    .family<RoleAccessDetail, int>((ref, roleId) async {
      try {
        return await ref.read(roleAccessRepositoryProvider).fetchDetail(roleId);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final roleAccessActionsProvider = Provider<RoleAccessActions>(
  RoleAccessActions.new,
);

class RoleAccessActions {
  RoleAccessActions(this._ref);

  final Ref _ref;

  Future<int> create(RoleAccessFormValue value) =>
      _guard(() => _ref.read(roleAccessRepositoryProvider).create(value));

  Future<void> update({
    required int roleId,
    required RoleAccessFormValue value,
  }) => _guard(
    () => _ref
        .read(roleAccessRepositoryProvider)
        .update(roleId: roleId, value: value),
  );

  Future<void> deactivate(int roleId) =>
      _guard(() => _ref.read(roleAccessRepositoryProvider).deactivate(roleId));

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
