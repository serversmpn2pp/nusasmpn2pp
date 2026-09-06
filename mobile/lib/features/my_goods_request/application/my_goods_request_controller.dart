import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/my_goods_request/data/my_goods_request_repository.dart';
import 'package:nusa/features/my_goods_request/domain/my_goods_request.dart';

class MyGoodsRequestController extends AsyncNotifier<MyGoodsRequestPage> {
  String _status = 'semua';
  int _version = 0;
  @override
  Future<MyGoodsRequestPage> build() => _fetch(1);
  Future<void> filter(String status) async {
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

  Future<MyGoodsRequestPage> _fetch(int page) => _guard(
    () => ref
        .read(myGoodsRequestRepositoryProvider)
        .fetch(status: _status, page: page),
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

class MyGoodsRequestActions {
  MyGoodsRequestActions(this._ref);
  final Ref _ref;
  Future<MyGoodsCatalogPage> catalog({String query = '', int page = 1}) =>
      _guard(
        () => _ref
            .read(myGoodsRequestRepositoryProvider)
            .catalog(query: query, page: page),
      );
  Future<MyGoodsRequestDetail> detail(int id) =>
      _guard(() => _ref.read(myGoodsRequestRepositoryProvider).detail(id));
  Future<MyGoodsRequestDetail> create(MyGoodsRequestFormValue value) =>
      _guard(() => _ref.read(myGoodsRequestRepositoryProvider).create(value));
  Future<MyGoodsRequestDetail> cancel(int id) =>
      _guard(() => _ref.read(myGoodsRequestRepositoryProvider).cancel(id));
  Future<T> _guard<T>(Future<T> Function() action) async {
    try {
      return await action();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final myGoodsRequestControllerProvider =
    AsyncNotifierProvider.autoDispose<
      MyGoodsRequestController,
      MyGoodsRequestPage
    >(MyGoodsRequestController.new);
final myGoodsRequestActionsProvider = Provider<MyGoodsRequestActions>(
  MyGoodsRequestActions.new,
);
