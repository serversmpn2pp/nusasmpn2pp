import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/question_package/domain/question_package.dart';

abstract interface class QuestionPackageRemoteDataSource {
  Future<QuestionPackagePage> fetch({
    required String query,
    required int? eventId,
    required String status,
    required int page,
  });
  Future<QuestionPackageDetail> detail(int scheduleId);
  Future<QuestionPackageDetail> save(
    int scheduleId,
    QuestionPackagePayload payload,
  );
}

final class DioQuestionPackageRemoteDataSource
    implements QuestionPackageRemoteDataSource {
  const DioQuestionPackageRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<QuestionPackagePage> fetch({
    required String query,
    required int? eventId,
    required String status,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'paket-soal',
        queryParameters: {
          if (query.trim().isNotEmpty) 'kata_kunci': query.trim(),
          'kegiatan_id': ?eventId,
          'status': status,
          'halaman': page,
          'per_halaman': 12,
        },
      );
      return QuestionPackagePage.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<QuestionPackageDetail> detail(int scheduleId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'paket-soal/$scheduleId',
      );
      return QuestionPackageDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<QuestionPackageDetail> save(
    int scheduleId,
    QuestionPackagePayload payload,
  ) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'paket-soal/$scheduleId',
        data: payload.toJson(),
      );
      return QuestionPackageDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final questionPackageRemoteDataSourceProvider =
    Provider<QuestionPackageRemoteDataSource>(
      (ref) => DioQuestionPackageRemoteDataSource(ref.watch(dioProvider)),
    );
