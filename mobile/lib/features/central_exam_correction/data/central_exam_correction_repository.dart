import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_correction.dart';

abstract interface class CentralExamCorrectionRepository {
  Future<AssessmentCorrectionData> corrections({
    required int eventId,
    required int scheduleId,
    required int? classId,
    required String status,
  });

  Future<AssessmentCorrectionData> saveCorrections({
    required int eventId,
    required int scheduleId,
    required int? classId,
    required String status,
    required List<AssessmentScorePayload> scores,
  });
}

class DioCentralExamCorrectionRepository
    implements CentralExamCorrectionRepository {
  const DioCentralExamCorrectionRepository(this._dio);

  final Dio _dio;

  String _path(int eventId, int scheduleId) =>
      'hasil-ujian-terpusat/$eventId/jadwal/$scheduleId/koreksi-uraian';

  @override
  Future<AssessmentCorrectionData> corrections({
    required int eventId,
    required int scheduleId,
    required int? classId,
    required String status,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        _path(eventId, scheduleId),
        queryParameters: {'kelas_id': classId, 'status': status},
      );
      return AssessmentCorrectionData.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<AssessmentCorrectionData> saveCorrections({
    required int eventId,
    required int scheduleId,
    required int? classId,
    required String status,
    required List<AssessmentScorePayload> scores,
  }) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(
        _path(eventId, scheduleId),
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

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final centralExamCorrectionRepositoryProvider =
    Provider<CentralExamCorrectionRepository>(
      (ref) => DioCentralExamCorrectionRepository(ref.watch(dioProvider)),
    );
