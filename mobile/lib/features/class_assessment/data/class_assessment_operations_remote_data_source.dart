import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_correction.dart';

abstract interface class ClassAssessmentOperationsRemoteDataSource {
  Future<AssessmentCorrectionData> corrections({
    required int assessmentId,
    required int? classId,
    required String status,
  });

  Future<AssessmentCorrectionData> saveCorrections({
    required int assessmentId,
    required int? classId,
    required String status,
    required List<AssessmentScorePayload> scores,
  });

  Future<AssessmentApplyResult> applyResults(int assessmentId);
}

final class DioClassAssessmentOperationsRemoteDataSource
    implements ClassAssessmentOperationsRemoteDataSource {
  const DioClassAssessmentOperationsRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<AssessmentCorrectionData> corrections({
    required int assessmentId,
    required int? classId,
    required String status,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'asesmen-kelas/$assessmentId/koreksi-uraian',
        queryParameters: {'kelas_id': ?classId, 'status': status},
      );
      return AssessmentCorrectionData.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<AssessmentCorrectionData> saveCorrections({
    required int assessmentId,
    required int? classId,
    required String status,
    required List<AssessmentScorePayload> scores,
  }) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(
        'asesmen-kelas/$assessmentId/koreksi-uraian',
        data: {
          'skor': scores.map((item) => item.toJson()).toList(growable: false),
          'kelas_id': classId,
          'status': status,
        },
      );
      return AssessmentCorrectionData.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<AssessmentApplyResult> applyResults(int assessmentId) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'asesmen-kelas/$assessmentId/terapkan-nilai',
      );
      return AssessmentApplyResult.fromJson(
        response.data ?? <String, dynamic>{},
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final classAssessmentOperationsRemoteDataSourceProvider =
    Provider<ClassAssessmentOperationsRemoteDataSource>(
      (ref) =>
          DioClassAssessmentOperationsRemoteDataSource(ref.watch(dioProvider)),
    );
