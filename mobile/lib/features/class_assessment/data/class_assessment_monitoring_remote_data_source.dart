import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_monitoring.dart';

abstract interface class ClassAssessmentMonitoringRemoteDataSource {
  Future<AssessmentMonitoringData> monitoring({
    required int assessmentId,
    required int? classId,
    required String status,
  });

  Future<AssessmentResultsData> results({
    required int assessmentId,
    required int? classId,
    required String status,
  });

  Future<void> unlockParticipant(int participantId);
}

final class DioClassAssessmentMonitoringRemoteDataSource
    implements ClassAssessmentMonitoringRemoteDataSource {
  const DioClassAssessmentMonitoringRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<AssessmentMonitoringData> monitoring({
    required int assessmentId,
    required int? classId,
    required String status,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'asesmen-kelas/$assessmentId/monitoring',
        queryParameters: {'kelas_id': ?classId, 'status': status},
      );
      return AssessmentMonitoringData.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<AssessmentResultsData> results({
    required int assessmentId,
    required int? classId,
    required String status,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'asesmen-kelas/$assessmentId/hasil',
        queryParameters: {'kelas_id': ?classId, 'status': status},
      );
      return AssessmentResultsData.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> unlockParticipant(int participantId) async {
    try {
      await _dio.post<Map<String, dynamic>>(
        'keamanan-ujian/peserta/$participantId/buka',
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final classAssessmentMonitoringRemoteDataSourceProvider =
    Provider<ClassAssessmentMonitoringRemoteDataSource>(
      (ref) =>
          DioClassAssessmentMonitoringRemoteDataSource(ref.watch(dioProvider)),
    );
