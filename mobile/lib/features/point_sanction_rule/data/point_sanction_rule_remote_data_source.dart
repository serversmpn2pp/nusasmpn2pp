import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/point_sanction_rule/domain/point_sanction_rule.dart';

abstract interface class PointSanctionRuleRemoteDataSource {
  Future<PointSanctionRulePage> fetch({
    required String query,
    required String status,
  });

  Future<void> create(PointSanctionRuleFormValue value);

  Future<void> update({
    required int id,
    required PointSanctionRuleFormValue value,
  });

  Future<void> deactivate(int id);
}

final class DioPointSanctionRuleRemoteDataSource
    implements PointSanctionRuleRemoteDataSource {
  DioPointSanctionRuleRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<PointSanctionRulePage> fetch({
    required String query,
    required String status,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'aturan-sanksi-poin',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
        },
      );
      return PointSanctionRulePage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(PointSanctionRuleFormValue value) =>
      _save(path: 'aturan-sanksi-poin', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required PointSanctionRuleFormValue value,
  }) => _save(path: 'aturan-sanksi-poin/$id', method: 'PATCH', value: value);

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('aturan-sanksi-poin/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _save({
    required String path,
    required String method,
    required PointSanctionRuleFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          'batas_poin': value.pointThreshold,
          'nama': value.name.trim(),
          'deskripsi': value.description.trim(),
          'urutan': value.order,
          'aktif': value.active,
        },
        options: Options(method: method),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final pointSanctionRuleRemoteDataSourceProvider =
    Provider<PointSanctionRuleRemoteDataSource>(
      (ref) => DioPointSanctionRuleRemoteDataSource(ref.watch(dioProvider)),
    );
