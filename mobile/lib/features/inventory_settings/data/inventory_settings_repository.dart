import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/inventory_settings/data/inventory_settings_remote_data_source.dart';
import 'package:nusa/features/inventory_settings/domain/inventory_settings.dart';

final class InventorySettingsRepository {
  const InventorySettingsRepository(this._remote);

  final InventorySettingsRemoteDataSource _remote;

  Future<InventorySettings> fetch() => _remote.fetch();

  Future<InventorySettings> update(InventorySettingsFormValue value) =>
      _remote.update(value);
}

final inventorySettingsRepositoryProvider =
    Provider<InventorySettingsRepository>(
      (ref) => InventorySettingsRepository(
        ref.watch(inventorySettingsRemoteDataSourceProvider),
      ),
    );
