import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/asset_unit/data/asset_unit_repository.dart';
import 'package:nusa/features/asset_unit/domain/asset_unit.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';

class AssetUnitController extends AsyncNotifier<AssetUnitPage> {
  String _query = '';
  String _dataStatus = 'semua';
  String _condition = 'semua';
  String _unitStatus = 'semua';
  int? _goodsId;
  int? _locationId;
  int _requestVersion = 0;

  @override
  Future<AssetUnitPage> build() => _fetch(page: 1);

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> applyFilters({
    required String dataStatus,
    required String condition,
    required String unitStatus,
    required int? goodsId,
    required int? locationId,
  }) async {
    _dataStatus = dataStatus;
    _condition = condition;
    _unitStatus = unitStatus;
    _goodsId = goodsId;
    _locationId = locationId;
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

  Future<AssetUnitPage> _fetch({required int page}) async {
    try {
      return await ref
          .read(assetUnitRepositoryProvider)
          .fetch(
            query: _query,
            dataStatus: _dataStatus,
            condition: _condition,
            unitStatus: _unitStatus,
            goodsId: _goodsId,
            locationId: _locationId,
            page: page,
          );
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final assetUnitControllerProvider =
    AsyncNotifierProvider.autoDispose<AssetUnitController, AssetUnitPage>(
      AssetUnitController.new,
    );

final assetUnitActionsProvider = Provider<AssetUnitActions>(
  AssetUnitActions.new,
);

class AssetUnitActions {
  AssetUnitActions(this._ref);

  final Ref _ref;

  Future<AssetUnit> detail(int id) =>
      _guardResult(() => _ref.read(assetUnitRepositoryProvider).detail(id));

  Future<void> create(AssetUnitFormValue value) =>
      _guard(() => _ref.read(assetUnitRepositoryProvider).create(value));

  Future<void> update({required int id, required AssetUnitFormValue value}) =>
      _guard(
        () =>
            _ref.read(assetUnitRepositoryProvider).update(id: id, value: value),
      );

  Future<void> deactivate(int id) =>
      _guard(() => _ref.read(assetUnitRepositoryProvider).deactivate(id));

  Future<void> _guard(Future<void> Function() operation) async {
    try {
      await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  Future<T> _guardResult<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
