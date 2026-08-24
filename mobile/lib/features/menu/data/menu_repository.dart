import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/menu/data/menu_remote_data_source.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';

final class MenuRepository {
  MenuRepository(this._remote);

  final MenuRemoteDataSource _remote;

  Future<MenuCatalog> fetchCatalog() => _remote.fetchCatalog();
}

final menuRepositoryProvider = Provider<MenuRepository>((ref) {
  return MenuRepository(ref.watch(menuRemoteDataSourceProvider));
});
