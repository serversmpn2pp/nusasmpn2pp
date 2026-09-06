import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/goods_request/data/goods_request_repository.dart';
import 'package:nusa/features/goods_request/domain/goods_request.dart';

class GoodsRequestController extends AsyncNotifier<GoodsRequestPage> {
  String _query = '';
  String _type = 'semua';
  String _status = 'menunggu';
  int _version = 0;
  @override
  Future<GoodsRequestPage> build() => _fetch(1);
  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> applyFilters({
    required String type,
    required String status,
  }) async {
    _type = type;
    _status = status;
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

  Future<GoodsRequestPage> _fetch(int page) => _guard(
    () => ref
        .read(goodsRequestRepositoryProvider)
        .fetch(query: _query, type: _type, status: _status, page: page),
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

class GoodsRequestActions {
  GoodsRequestActions(this._ref);
  final Ref _ref;
  Future<GoodsRequestDetail> detail(int id) =>
      _guard(() => _ref.read(goodsRequestRepositoryProvider).detail(id));
  Future<GoodsRequestDetail> fulfill(int id, GoodsRequestFulfillValue value) =>
      _guard(
        () => _ref.read(goodsRequestRepositoryProvider).fulfill(id, value),
      );
  Future<GoodsRequestDetail> reject(int id, String reason) => _guard(
    () => _ref.read(goodsRequestRepositoryProvider).reject(id, reason),
  );
  Future<T> _guard<T>(Future<T> Function() action) async {
    try {
      return await action();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final goodsRequestControllerProvider =
    AsyncNotifierProvider.autoDispose<GoodsRequestController, GoodsRequestPage>(
      GoodsRequestController.new,
    );
final goodsRequestActionsProvider = Provider<GoodsRequestActions>(
  GoodsRequestActions.new,
);
