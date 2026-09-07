import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/goods_catalog/data/goods_catalog_remote_data_source.dart';
import 'package:nusa/features/goods_catalog/domain/goods_catalog.dart';

class GoodsCatalogRepository {
  const GoodsCatalogRepository(this._remote);
  final GoodsCatalogRemoteDataSource _remote;
  Future<GoodsCatalogPage> fetch({
    required GoodsCatalogFilter filter,
    required int page,
  }) => _remote.fetch(filter: filter, page: page);
  Future<GoodsCatalogDetail> detail(int id) => _remote.detail(id);
}

final goodsCatalogRepositoryProvider = Provider<GoodsCatalogRepository>(
  (ref) =>
      GoodsCatalogRepository(ref.watch(goodsCatalogRemoteDataSourceProvider)),
);
