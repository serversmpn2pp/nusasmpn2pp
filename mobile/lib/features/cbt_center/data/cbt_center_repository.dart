import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/cbt_center/data/cbt_center_remote_data_source.dart';
import 'package:nusa/features/cbt_center/domain/cbt_center.dart';

final class CbtCenterRepository {
  const CbtCenterRepository(this._remote);

  final CbtCenterRemoteDataSource _remote;

  Future<CbtCenterData> fetch() => _remote.fetch();
}

final cbtCenterRepositoryProvider = Provider<CbtCenterRepository>(
  (ref) => CbtCenterRepository(ref.watch(cbtCenterRemoteDataSourceProvider)),
);
