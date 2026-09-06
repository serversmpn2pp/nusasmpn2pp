import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/inventory_acquisition_source/data/inventory_acquisition_source_repository.dart';
import 'package:nusa/features/inventory_acquisition_source/domain/inventory_acquisition_source.dart';

class InventoryAcquisitionSourceController
    extends AsyncNotifier<InventoryAcquisitionSourcePage> {
  String _query = '';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<InventoryAcquisitionSourcePage> build() => _fetch(page: 1);

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

  Future<InventoryAcquisitionSourcePage> _fetch({required int page}) async {
    try {
      return await ref
          .read(inventoryAcquisitionSourceRepositoryProvider)
          .fetch(query: _query, status: _status, page: page);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final inventoryAcquisitionSourceControllerProvider =
    AsyncNotifierProvider.autoDispose<
      InventoryAcquisitionSourceController,
      InventoryAcquisitionSourcePage
    >(InventoryAcquisitionSourceController.new);

final inventoryAcquisitionSourceActionsProvider =
    Provider<InventoryAcquisitionSourceActions>(
      InventoryAcquisitionSourceActions.new,
    );

class InventoryAcquisitionSourceActions {
  InventoryAcquisitionSourceActions(this._ref);

  final Ref _ref;

  Future<void> create(InventoryAcquisitionSourceFormValue value) => _guard(
    () => _ref.read(inventoryAcquisitionSourceRepositoryProvider).create(value),
  );

  Future<void> update({
    required int id,
    required InventoryAcquisitionSourceFormValue value,
  }) => _guard(
    () => _ref
        .read(inventoryAcquisitionSourceRepositoryProvider)
        .update(id: id, value: value),
  );

  Future<void> deactivate(int id) => _guard(
    () =>
        _ref.read(inventoryAcquisitionSourceRepositoryProvider).deactivate(id),
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
