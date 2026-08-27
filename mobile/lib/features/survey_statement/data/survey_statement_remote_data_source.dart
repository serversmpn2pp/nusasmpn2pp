import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/survey_statement/domain/survey_statement.dart';

abstract interface class SurveyStatementRemoteDataSource {
  Future<SurveyStatementPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  });

  Future<void> create(SurveyStatementFormValue value);

  Future<void> update({
    required int id,
    required SurveyStatementFormValue value,
  });

  Future<void> updateStatus({required int id, required bool active});
}

final class DioSurveyStatementRemoteDataSource
    implements SurveyStatementRemoteDataSource {
  DioSurveyStatementRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<SurveyStatementPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pernyataan-survei',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return SurveyStatementPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(SurveyStatementFormValue value) => _save(
    path: 'pernyataan-survei',
    method: 'POST',
    value: value,
    includeStatus: true,
  );

  @override
  Future<void> update({
    required int id,
    required SurveyStatementFormValue value,
  }) => _save(
    path: 'pernyataan-survei/$id',
    method: 'PATCH',
    value: value,
    includeStatus: false,
  );

  @override
  Future<void> updateStatus({required int id, required bool active}) async {
    try {
      await _dio.patch<Map<String, dynamic>>(
        'pernyataan-survei/$id/status',
        data: {'aktif': active},
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _save({
    required String path,
    required String method,
    required SurveyStatementFormValue value,
    required bool includeStatus,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          'pernyataan': value.statement.trim(),
          'urutan': value.order,
          if (includeStatus) 'aktif': value.active,
        },
        options: Options(method: method),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final surveyStatementRemoteDataSourceProvider =
    Provider<SurveyStatementRemoteDataSource>(
      (ref) => DioSurveyStatementRemoteDataSource(ref.watch(dioProvider)),
    );
