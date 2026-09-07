import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/goods_catalog/data/goods_catalog_repository.dart';
import 'package:nusa/features/goods_catalog/domain/goods_catalog.dart';

class GoodsCatalogController extends AsyncNotifier<GoodsCatalogPage> {
  GoodsCatalogFilter _filter = const GoodsCatalogFilter();
  int _version = 0;

  @override
  Future<GoodsCatalogPage> build() => _fetch(1);

  Future<void> search(String query) => applyFilter(
    GoodsCatalogFilter(
      query: query,
      categoryId: _filter.categoryId,
      type: _filter.type,
      availability: _filter.availability,
    ),
  );

  Future<void> applyFilter(GoodsCatalogFilter filter) async {
    _filter = filter;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_version;
    state = const AsyncLoading();
    try {
      final value = await _fetch(1);
      if (version == _version) state = AsyncData(value);
    } catch (error, stack) {
      if (version == _version) state = AsyncError(error, stack);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    state = AsyncData(
      current.append(await _fetch(current.pagination.page + 1)),
    );
  }

  Future<GoodsCatalogPage> _fetch(int page) => _guard(
    () => ref
        .read(goodsCatalogRepositoryProvider)
        .fetch(filter: _filter, page: page),
  );

  Future<T> _guard<T>(Future<T> Function() action) async {
    try {
      return await action();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

class GoodsCatalogActions {
  GoodsCatalogActions(this._ref);
  final Ref _ref;
  Future<GoodsCatalogDetail> detail(int id) =>
      _guard(() => _ref.read(goodsCatalogRepositoryProvider).detail(id));
  Future<T> _guard<T>(Future<T> Function() action) async {
    try {
      return await action();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final goodsCatalogControllerProvider =
    AsyncNotifierProvider.autoDispose<GoodsCatalogController, GoodsCatalogPage>(
      GoodsCatalogController.new,
    );
final goodsCatalogActionsProvider = Provider<GoodsCatalogActions>(
  GoodsCatalogActions.new,
);
