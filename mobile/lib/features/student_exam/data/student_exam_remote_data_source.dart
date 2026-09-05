import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';

abstract interface class StudentExamRemoteDataSource {
  Future<StudentExamSession> detail(int participantId);
  Future<StudentExamSession> start({
    required int participantId,
    required String? token,
    required String device,
  });
  Future<StudentExamSession> resume({
    required int participantId,
    required String device,
  });
  Future<StudentExamSaveResult> saveAnswer({
    required int participantId,
    required int questionId,
    required Object? answer,
    required bool doubtful,
    required String device,
  });
  Future<StudentExamSession> finish({
    required int participantId,
    required String device,
  });
  Future<StudentExamSecurityUpdate> securityEvent({
    required int participantId,
    required String event,
    required String device,
  });
}

final class DioStudentExamRemoteDataSource
    implements StudentExamRemoteDataSource {
  const DioStudentExamRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<StudentExamSession> detail(int participantId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'ujian-saya/$participantId',
      );
      return StudentExamSession.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentExamSession> start({
    required int participantId,
    required String? token,
    required String device,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'ujian-saya/$participantId/mulai',
        data: {'token': token, 'perangkat': device},
      );
      return StudentExamSession.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentExamSession> resume({
    required int participantId,
    required String device,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'ujian-saya/$participantId/kerjakan',
        queryParameters: {'perangkat': device},
      );
      return StudentExamSession.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentExamSaveResult> saveAnswer({
    required int participantId,
    required int questionId,
    required Object? answer,
    required bool doubtful,
    required String device,
  }) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(
        'ujian-saya/$participantId/jawaban',
        data: {
          'soal_ujian_cbt_id': questionId,
          'jawaban': answer,
          'ragu': doubtful,
          'perangkat': device,
        },
      );
      return StudentExamSaveResult.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentExamSession> finish({
    required int participantId,
    required String device,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'ujian-saya/$participantId/selesai',
        data: {'perangkat': device},
      );
      return StudentExamSession.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentExamSecurityUpdate> securityEvent({
    required int participantId,
    required String event,
    required String device,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'ujian-saya/$participantId/aktivitas-keamanan',
        data: {'peristiwa': event, 'perangkat': device},
      );
      return StudentExamSecurityUpdate.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final studentExamRemoteDataSourceProvider =
    Provider<StudentExamRemoteDataSource>(
      (ref) => DioStudentExamRemoteDataSource(ref.watch(dioProvider)),
    );
