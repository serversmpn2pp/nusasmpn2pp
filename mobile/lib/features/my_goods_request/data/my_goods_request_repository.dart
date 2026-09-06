import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/my_goods_request/data/my_goods_request_remote_data_source.dart';
import 'package:nusa/features/my_goods_request/domain/my_goods_request.dart';

class MyGoodsRequestRepository {
  const MyGoodsRequestRepository(this._remote);
  final MyGoodsRequestRemoteDataSource _remote;
  Future<MyGoodsRequestPage> fetch({
    required String status,
    required int page,
  }) => _remote.fetch(status: status, page: page);
  Future<MyGoodsCatalogPage> catalog({
    required String query,
    required int page,
  }) => _remote.catalog(query: query, page: page);
  Future<MyGoodsRequestDetail> detail(int id) => _remote.detail(id);
  Future<MyGoodsRequestDetail> create(MyGoodsRequestFormValue value) =>
      _remote.create(value);
  Future<MyGoodsRequestDetail> cancel(int id) => _remote.cancel(id);
}

final myGoodsRequestRepositoryProvider = Provider<MyGoodsRequestRepository>(
  (ref) => MyGoodsRequestRepository(
    ref.watch(myGoodsRequestRemoteDataSourceProvider),
  ),
);
