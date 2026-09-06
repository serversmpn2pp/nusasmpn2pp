import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/inventory_goods/data/inventory_goods_repository.dart';
import 'package:nusa/features/inventory_goods/domain/inventory_goods.dart';

class InventoryGoodsController extends AsyncNotifier<InventoryGoodsPage> {
  String _query = '';
  String _status = 'semua';
  String _type = 'semua';
  int? _categoryId;
  int _requestVersion = 0;

  @override
  Future<InventoryGoodsPage> build() => _fetch(page: 1);

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> applyFilters({
    required String status,
    required String type,
    required int? categoryId,
  }) async {
    if (_status == status && _type == type && _categoryId == categoryId) return;
    _status = status;
    _type = type;
    _categoryId = categoryId;
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

  Future<InventoryGoodsPage> _fetch({required int page}) async {
    try {
      return await ref
          .read(inventoryGoodsRepositoryProvider)
          .fetch(
            query: _query,
            status: _status,
            type: _type,
            categoryId: _categoryId,
            page: page,
          );
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final inventoryGoodsControllerProvider =
    AsyncNotifierProvider.autoDispose<
      InventoryGoodsController,
      InventoryGoodsPage
    >(InventoryGoodsController.new);

final inventoryGoodsActionsProvider = Provider<InventoryGoodsActions>(
  InventoryGoodsActions.new,
);

class InventoryGoodsActions {
  InventoryGoodsActions(this._ref);

  final Ref _ref;

  Future<void> create(InventoryGoodsFormValue value) =>
      _guard(() => _ref.read(inventoryGoodsRepositoryProvider).create(value));

  Future<void> update({
    required int id,
    required InventoryGoodsFormValue value,
  }) => _guard(
    () => _ref
        .read(inventoryGoodsRepositoryProvider)
        .update(id: id, value: value),
  );

  Future<void> deactivate(int id) =>
      _guard(() => _ref.read(inventoryGoodsRepositoryProvider).deactivate(id));

  Future<void> _guard(Future<void> Function() operation) async {
    try {
      await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
