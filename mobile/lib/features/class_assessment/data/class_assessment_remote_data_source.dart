import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment.dart';

abstract interface class ClassAssessmentRemoteDataSource {
  Future<ClassAssessmentPage> fetch({
    required String query,
    required String status,
    required int page,
  });
  Future<ClassAssessmentDetail> detail(int id);
  Future<ClassAssessmentDetail> create(ClassAssessmentPayload payload);
  Future<ClassAssessmentDetail> update(int id, ClassAssessmentPayload payload);
  Future<void> deactivate(int id);
  Future<AssessmentQuestions> questions(int id);
  Future<AssessmentQuestions> saveQuestions(
    int id,
    List<AssessmentQuestionPayload> questions,
  );
}

final class DioClassAssessmentRemoteDataSource
    implements ClassAssessmentRemoteDataSource {
  const DioClassAssessmentRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<ClassAssessmentPage> fetch({
    required String query,
    required String status,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'asesmen-kelas',
        queryParameters: {
          if (query.trim().isNotEmpty) 'kata_kunci': query.trim(),
          'status': status,
          'halaman': page,
          'per_halaman': 12,
        },
      );
      return ClassAssessmentPage.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<ClassAssessmentDetail> detail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'asesmen-kelas/$id',
      );
      return ClassAssessmentDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<ClassAssessmentDetail> create(ClassAssessmentPayload payload) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'asesmen-kelas',
        data: payload.toJson(),
      );
      return ClassAssessmentDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<ClassAssessmentDetail> update(
    int id,
    ClassAssessmentPayload payload,
  ) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(
        'asesmen-kelas/$id',
        data: payload.toJson(),
      );
      return ClassAssessmentDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('asesmen-kelas/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<AssessmentQuestions> questions(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'asesmen-kelas/$id/soal',
      );
      return AssessmentQuestions.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<AssessmentQuestions> saveQuestions(
    int id,
    List<AssessmentQuestionPayload> questions,
  ) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(
        'asesmen-kelas/$id/soal',
        data: {'soal': questions.map((item) => item.toJson()).toList()},
      );
      return AssessmentQuestions.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final classAssessmentRemoteDataSourceProvider =
    Provider<ClassAssessmentRemoteDataSource>(
      (ref) => DioClassAssessmentRemoteDataSource(ref.watch(dioProvider)),
    );
