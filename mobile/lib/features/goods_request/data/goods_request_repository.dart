import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/goods_request/data/goods_request_remote_data_source.dart';
import 'package:nusa/features/goods_request/domain/goods_request.dart';

class GoodsRequestRepository {
  const GoodsRequestRepository(this._remote);
  final GoodsRequestRemoteDataSource _remote;
  Future<GoodsRequestPage> fetch({
    required String query,
    required String type,
    required String status,
    required int page,
  }) => _remote.fetch(query: query, type: type, status: status, page: page);
  Future<GoodsRequestDetail> detail(int id) => _remote.detail(id);
  Future<GoodsRequestDetail> fulfill(int id, GoodsRequestFulfillValue value) =>
      _remote.fulfill(id, value);
  Future<GoodsRequestDetail> reject(int id, String reason) =>
      _remote.reject(id, reason);
}

final goodsRequestRepositoryProvider = Provider<GoodsRequestRepository>(
  (ref) =>
      GoodsRequestRepository(ref.watch(goodsRequestRemoteDataSourceProvider)),
);
