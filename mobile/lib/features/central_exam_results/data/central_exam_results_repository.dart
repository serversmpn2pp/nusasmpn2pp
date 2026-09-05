import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/central_exam_results/domain/central_exam_results.dart';

class CentralExamResultsRepository {
  const CentralExamResultsRepository(this._dio);
  final Dio _dio;

  Future<CentralExamResultsPage> events({
    required String query,
    required String status,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'hasil-ujian-terpusat',
        queryParameters: {
          'kata_kunci': query,
          'status': status,
          'halaman': page,
        },
      );
      return CentralExamResultsPage.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<CentralExamResultsDetail> detail({
    required int eventId,
    required int? scheduleId,
    required int? classId,
    required String status,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'hasil-ujian-terpusat/$eventId',
        queryParameters: {
          'jadwal_id': scheduleId,
          'kelas_id': classId,
          'status': status,
        },
      );
      return CentralExamResultsDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<CentralExamApplyResult> apply({
    required int eventId,
    required int scheduleId,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'hasil-ujian-terpusat/$eventId/jadwal/$scheduleId/terapkan-nilai',
      );
      return CentralExamApplyResult.fromJson(
        response.data ?? <String, dynamic>{},
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final centralExamResultsRepositoryProvider = Provider(
  (ref) => CentralExamResultsRepository(ref.watch(dioProvider)),
);
