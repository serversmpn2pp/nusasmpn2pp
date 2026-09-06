import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/inventory_category/data/inventory_category_repository.dart';
import 'package:nusa/features/inventory_category/domain/inventory_category.dart';

class InventoryCategoryController extends AsyncNotifier<InventoryCategoryPage> {
  String _query = '';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<InventoryCategoryPage> build() => _fetch(page: 1);

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

  Future<InventoryCategoryPage> _fetch({required int page}) async {
    try {
      return await ref
          .read(inventoryCategoryRepositoryProvider)
          .fetch(query: _query, status: _status, page: page);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final inventoryCategoryControllerProvider =
    AsyncNotifierProvider.autoDispose<
      InventoryCategoryController,
      InventoryCategoryPage
    >(InventoryCategoryController.new);

final inventoryCategoryActionsProvider = Provider<InventoryCategoryActions>(
  InventoryCategoryActions.new,
);

class InventoryCategoryActions {
  InventoryCategoryActions(this._ref);

  final Ref _ref;

  Future<void> create(InventoryCategoryFormValue value) => _guard(
    () => _ref.read(inventoryCategoryRepositoryProvider).create(value),
  );

  Future<void> update({
    required int id,
    required InventoryCategoryFormValue value,
  }) => _guard(
    () => _ref
        .read(inventoryCategoryRepositoryProvider)
        .update(id: id, value: value),
  );

  Future<void> deactivate(int id) => _guard(
    () => _ref.read(inventoryCategoryRepositoryProvider).deactivate(id),
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
