import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_assistance/domain/student_assistance.dart';

abstract interface class StudentAssistanceRemoteDataSource {
  Future<StudentAssistancePage> fetch({
    required String query,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  });

  Future<StudentAssistanceReference> fetchReference({
    required String query,
    required int? academicYearId,
    required int? classId,
  });

  Future<StudentAssistanceDetail> fetchDetail(int id);
  Future<StudentAssistanceDetail> create(StudentAssistancePayload payload);
  Future<StudentAssistanceDetail> update(
    int id,
    StudentAssistancePayload payload,
  );
}

final class DioStudentAssistanceRemoteDataSource
    implements StudentAssistanceRemoteDataSource {
  DioStudentAssistanceRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<StudentAssistancePage> fetch({
    required String query,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pendampingan-siswa',
        queryParameters: {
          'kata_kunci': query,
          'status': status,
          'tahun_pelajaran_id': ?academicYearId,
          'kelas_id': ?classId,
          'halaman': page,
          'per_halaman': 15,
        },
      );
      return StudentAssistancePage.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentAssistanceReference> fetchReference({
    required String query,
    required int? academicYearId,
    required int? classId,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pendampingan-siswa/referensi',
        queryParameters: {
          'kata_kunci': query,
          'tahun_pelajaran_id': ?academicYearId,
          'kelas_id': ?classId,
        },
      );
      return StudentAssistanceReference.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentAssistanceDetail> fetchDetail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pendampingan-siswa/$id',
      );
      return StudentAssistanceDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentAssistanceDetail> create(
    StudentAssistancePayload payload,
  ) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'pendampingan-siswa',
        data: payload.toJson(create: true),
      );
      return StudentAssistanceDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentAssistanceDetail> update(
    int id,
    StudentAssistancePayload payload,
  ) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(
        'pendampingan-siswa/$id',
        data: payload.toJson(create: false),
      );
      return StudentAssistanceDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final studentAssistanceRemoteDataSourceProvider =
    Provider<StudentAssistanceRemoteDataSource>(
      (ref) => DioStudentAssistanceRemoteDataSource(ref.watch(dioProvider)),
    );
