import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/survey_monitoring/domain/survey_monitoring.dart';

abstract interface class SurveyMonitoringRemoteDataSource {
  Future<SurveyMonitoringPage> fetch({
    int? academicYearId,
    required String semester,
    required String status,
    required String query,
    required int page,
    int perPage = 15,
  });

  Future<SurveyMonitoringDetail> fetchDetail({
    required int assignmentId,
    required String semester,
  });
}

final class DioSurveyMonitoringRemoteDataSource
    implements SurveyMonitoringRemoteDataSource {
  DioSurveyMonitoringRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<SurveyMonitoringPage> fetch({
    int? academicYearId,
    required String semester,
    required String status,
    required String query,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'monitoring-survei',
        queryParameters: {
          'tahun_pelajaran_id': ?academicYearId,
          'semester': semester,
          'status': status,
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return SurveyMonitoringPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<SurveyMonitoringDetail> fetchDetail({
    required int assignmentId,
    required String semester,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'monitoring-survei/$assignmentId',
        queryParameters: {'semester': semester},
      );
      return SurveyMonitoringDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final surveyMonitoringRemoteDataSourceProvider =
    Provider<SurveyMonitoringRemoteDataSource>(
      (ref) => DioSurveyMonitoringRemoteDataSource(ref.watch(dioProvider)),
    );
