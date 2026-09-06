import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/inventory_settings/data/inventory_settings_repository.dart';
import 'package:nusa/features/inventory_settings/domain/inventory_settings.dart';

class InventorySettingsController extends AsyncNotifier<InventorySettings> {
  @override
  Future<InventorySettings> build() => _fetch();

  Future<void> refresh() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(_fetch);
  }

  void replace(InventorySettings settings) {
    state = AsyncData(settings);
  }

  Future<InventorySettings> _fetch() async {
    try {
      return await ref.read(inventorySettingsRepositoryProvider).fetch();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final inventorySettingsControllerProvider =
    AsyncNotifierProvider.autoDispose<
      InventorySettingsController,
      InventorySettings
    >(InventorySettingsController.new);

final inventorySettingsActionsProvider = Provider<InventorySettingsActions>(
  InventorySettingsActions.new,
);

class InventorySettingsActions {
  InventorySettingsActions(this._ref);

  final Ref _ref;

  Future<InventorySettings> update(InventorySettingsFormValue value) async {
    try {
      return await _ref.read(inventorySettingsRepositoryProvider).update(value);
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
