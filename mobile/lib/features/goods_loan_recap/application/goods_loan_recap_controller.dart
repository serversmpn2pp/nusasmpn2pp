import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/goods_loan_recap/data/goods_loan_recap_repository.dart';
import 'package:nusa/features/goods_loan_recap/domain/goods_loan_recap.dart';

class GoodsLoanRecapController extends AsyncNotifier<GoodsLoanRecapPage> {
  GoodsLoanRecapFilter _filter = const GoodsLoanRecapFilter();
  int _version = 0;
  GoodsLoanRecapFilter get filter => _filter;

  @override
  Future<GoodsLoanRecapPage> build() => _fetch(1);

  Future<void> search(String value) =>
      apply(_filter.copyWith(query: value.trim()));

  Future<void> apply(GoodsLoanRecapFilter value) async {
    _filter = value;
    await refresh();
  }

  Future<void> reset() => apply(const GoodsLoanRecapFilter());

  Future<void> refresh() async {
    final version = ++_version;
    state = const AsyncLoading();
    try {
      final result = await _fetch(1);
      if (version == _version) state = AsyncData(result);
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

  Future<GoodsLoanRecapPage> document() => _guard(
    () => ref.read(goodsLoanRecapRepositoryProvider).document(_filter),
  );

  Future<GoodsLoanRecapPage> _fetch(int page) => _guard(
    () => ref
        .read(goodsLoanRecapRepositoryProvider)
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

final goodsLoanRecapControllerProvider =
    AsyncNotifierProvider.autoDispose<
      GoodsLoanRecapController,
      GoodsLoanRecapPage
    >(GoodsLoanRecapController.new);
