import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/asset_unit/data/asset_unit_remote_data_source.dart';
import 'package:nusa/features/asset_unit/domain/asset_unit.dart';

final class AssetUnitRepository {
  const AssetUnitRepository(this._remote);

  final AssetUnitRemoteDataSource _remote;

  Future<AssetUnitPage> fetch({
    required String query,
    required String dataStatus,
    required String condition,
    required String unitStatus,
    required int? goodsId,
    required int? locationId,
    required int page,
  }) => _remote.fetch(
    query: query,
    dataStatus: dataStatus,
    condition: condition,
    unitStatus: unitStatus,
    goodsId: goodsId,
    locationId: locationId,
    page: page,
  );

  Future<AssetUnit> detail(int id) => _remote.detail(id);

  Future<void> create(AssetUnitFormValue value) => _remote.create(value);

  Future<void> update({required int id, required AssetUnitFormValue value}) =>
      _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final assetUnitRepositoryProvider = Provider<AssetUnitRepository>(
  (ref) => AssetUnitRepository(ref.watch(assetUnitRemoteDataSourceProvider)),
);
