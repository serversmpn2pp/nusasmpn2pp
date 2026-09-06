import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/inventory_label/data/inventory_label_remote_data_source.dart';
import 'package:nusa/features/inventory_label/domain/inventory_label.dart';

class InventoryLabelController extends AsyncNotifier<InventoryLabelPage> {
  var _filters = const InventoryLabelFilters();
  int _requestVersion = 0;

  @override
  Future<InventoryLabelPage> build() => _fetch();

  Future<void> apply(InventoryLabelFilters filters) async {
    _filters = filters;
    await refresh();
  }

  Future<void> switchType(String type) => apply(
    InventoryLabelFilters(
      type: type,
      receiptId: _filters.receiptId,
      categoryId: _filters.categoryId,
      locationId: _filters.locationId,
    ),
  );

  Future<void> reset() => apply(InventoryLabelFilters(type: _filters.type));

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final page = await _fetch();
      if (version == _requestVersion) state = AsyncData(page);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<InventoryLabelPage> _fetch() async {
    try {
      return await ref
          .read(inventoryLabelRemoteDataSourceProvider)
          .fetch(_filters);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final inventoryLabelControllerProvider =
    AsyncNotifierProvider.autoDispose<
      InventoryLabelController,
      InventoryLabelPage
    >(InventoryLabelController.new);
